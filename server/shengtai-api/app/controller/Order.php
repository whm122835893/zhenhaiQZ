<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\NftMarketTrustOrder;
use app\model\Order as OrderModel;
use app\model\OrderItem;
use app\service\Transformer;
use app\helper\Snowflake;

/**
 * 订单控制器
 *
 * 对应源站 order 分包。
 *
 * ⚠️ 源站实测的关键行为（必须复刻，否则前端会写出源站没有的分支）：
 *
 *  1. **列表/详情不对称**
 *     GET /order返回的 items 恒为 []；只有 GET /order/{id} 才填明细。
 *     已在 Transformer::order() 里通过 $withItems 参数体现。
 *
 *  2. **order type 七种**
 *     10 常规 / 20 市场 / 30 批量 / 40 委托 / 50 求购 / 60 转售 / 70 申购
 *
 *  3. **status 语义统一但文案不同**
 *     0 待付款 / 1 进行中（委托中·求购中·申购中，同值不同文案）
 *     2 已完成 / 3 已取消 / 4 已退款（仅市场订单）
 *
 *  4. **已完成订单 payTime 仍为 null**
 *     源站未回填支付时间，所以 pay_time 库里存0，转换时输出 null。
 *
 *  5. **支付窗口 3 分钟**
 *     endPayTime - createTime = 180000ms，超时自动取消。
 */
class Order extends Base
{
    /** 订单类型常量 */
    private const TYPE_PRIMARY   = 10;  // 常规（一级市场直购）
    private const TYPE_MARKET    = 20;  // 市场
    private const TYPE_BATCH     = 30;  // 批量
    private const TYPE_TRUST     = 40;  // 委托
    private const TYPE_APPLY     = 50;  // 求购
    private const TYPE_RESELL    = 60;  // 转售
    private const TYPE_SUBSCRIBE = 70;  // 申购

    /** 订单状态 */
    private const STATUS_UNPAID   = 0;
    private const STATUS_PROCESS  = 1;
    private const STATUS_DONE     = 2;
    private const STATUS_CANCELLED= 3;
    private const STATUS_REFUNDED = 4;

    /**
     * GET /order —— 订单列表
     *
     * 支持按 type / status 过滤。**items 恒为 []**（源站行为）。
     */
    public function index()
    {
        $uid = $this->uid();

        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = OrderModel::where('uid', $uid)->where('is_deleted', 0);

        // type 支持逗号分隔的多选（源站市场页按 tab 切换）
        $type = $this->str('type');
        if ($type !== '') {
            $types = array_filter(array_map('intval', explode(',', $type)));
            if ($types) {
                $query->whereIn('order_type', $types);
            }
        }

        $status = $this->str('status');
        if ($status !== '') {
            $statuses = array_filter(array_map('intval', explode(',', $status)));
            if ($statuses) {
                $query->whereIn('status', $statuses);
            }
        }

        $total = (clone $query)->count();

        $rows = $query
            ->order('created_ts', 'desc')
            ->order('id', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $row) {
            // 列表不传 $withItems=false → items 恒为 []
            $data[] = Transformer::order($row);
        }

        // 源站实测返回裸数组（前端直接 res.data 展开），非 {list,total}
        return $this->pageList($data, $total);
    }

    /**
     * GET /order/{id} —— 订单详情
     *
     * 与列表的差别就在这里：**items 填真实明细**。
     */
    public function detail()
    {
        $uid = $this->uid();
        $id  = (string)($this->request->param('id') ?? '');

        if ($id === '') {
            throw ApiException::missing('id');
        }

        $row = OrderModel::where('id', $id)->where('uid', $uid)->where('is_deleted', 0)->find();

        if (!$row) {
            throw ApiException::notFound();
        }

        $order = $row->toArray();

        // 超时未支付的订单，源站会自动置为已取消（3 分钟窗口）
        if (
            (int)$order['status'] === self::STATUS_UNPAID
            && (int)$order['end_pay_time'] > 0
            && (int)$order['end_pay_time'] < $this->nowMillis()
        ) {
            OrderModel::where('id', $order['id'])->update([
                'status'       => self::STATUS_CANCELLED,
                'cancelled_at' => $this->nowMillis(),
            ]);
            $order['status']       = self::STATUS_CANCELLED;
            $order['cancelled_at'] = (int)$order['end_pay_time'];
        }

        // 详情才填 items
        $itemRows = OrderItem::where('order_id', $order['id'])->order('id', 'asc')->select()->toArray();

        $items = [];
        foreach ($itemRows as $it) {
            $items[] = Transformer::orderItem($it);
        }

        return Transformer::order($order, true, $items);
    }

    /**
     * POST /order/primary —— 一级市场常规下单
     *
     * 源站流程：选数量 → 创建待付款订单 → 拉起收银台。
     * 这里只落库+ 返回订单，不做真实支付。
     */
    public function primary()
    {
        $uid = $this->uid();
        $iid = (string)$this->bodyNeed('iid');

        $quantity = (int)$this->body()['quantity'] ?? 1;
        if ($quantity < 1) {
            throw ApiException::paramType('quantitymust be number');
        }

        $item = \app\model\Item::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$item) {
            throw ApiException::notFound();
        }
        $item = $item->toArray();

        $unitPrice = (int)$item['price'];       // 源站 price 单位是「元」
        $amount    = $unitPrice * 100 * $quantity; // 订单金额单位是「分」

        $now = $this->nowMillis();

        $order = OrderModel::create([
            'uid'            => $uid,
            'iid'            => $iid,
            'order_type'     => self::TYPE_PRIMARY,
            'pay_way'        => (int)($this->body()['payWay'] ?? 1),
            'status'         => self::STATUS_UNPAID,
            'title'          => $item['name'],
            'image'          => $item['image'],
            'image_url'      => $item['image_url'],
            'unit_price'     => $unitPrice,
            'quantity'       => $quantity,
            'item_quantity'  => $quantity,
            'amount'         => $amount,
            'subject'        => $item['name'],
            'out_trade_no'   => 'ST' . $now . random_int(1000, 9999),
            'is_snap_up'     => false,
            'end_pay_time'   => $now + (int)config('shengtai.order.pay_window_ms'),
            'pay_time'       => 0,
            'cancelled_at'   => 0,
            'created_ts'     => $now,
        ]);

        return Transformer::order($order->toArray());
    }

    /**
     * POST /order/secondary —— 二级市场单件下单
     */
    public function secondary()
    {
        $uid = $this->uid();
        $body = $this->body();

        $nftId = (string)($body['nftId'] ?? '');
        if ($nftId === '') {
            throw ApiException::missing('nftId');
        }

        $nft = \app\model\Nft::where('nft_id', $nftId)->where('is_deleted', 0)->find();
        if (!$nft) {
            throw ApiException::notFound();
        }
        $nft = $nft->toArray();

        $item = \app\model\Item::where('iid', $nft['iid'])->where('is_deleted', 0)->find();
        $itemArr = $item ? $item->toArray() : [];

        $unitPrice = (int)$nft['price'];
        $amount    = (int)$nft['buy_price'] > 0 ? (int)$nft['buy_price'] : $unitPrice * 100;

        $now = $this->nowMillis();

        $order = OrderModel::create([
            'uid'           => $uid,
            'iid'           => $nft['iid'],
            'order_type'    => self::TYPE_MARKET,
            'pay_way'       => (int)($body['payWay'] ?? 1),
            'status'        => self::STATUS_UNPAID,
            'title'         => $nft['name'],
            'image'         => $nft['image'],
            'image_url'     => $nft['image_url'],
            'unit_price'    => $unitPrice,
            'quantity'      => 1,
            'item_quantity' => 1,
            'amount'        => $amount,
            'subject'       => $nft['name'],
            'out_trade_no'  => 'ST' . $now . random_int(1000, 9999),
            'seller_uid'    => (string)$nft['uid'],
            'end_pay_time'  => $now + (int)config('shengtai.order.pay_window_ms'),
            'pay_time'      => 0,
            'cancelled_at'  => 0,
            'created_ts'    => $now,
        ]);

        // 二级市场下单要锁单：把 nft 标为锁定 + 待售
        \app\model\Nft::where('id', $nft['id'])->update([
            'pay_lock'     => 1,
            'sell_status'  => 2,
            'status'       => 'locked',
        ]);

        OrderItem::create([
            'order_id'   => $order->id,
            'nft_id'     => $nft['nft_id'],
            'token'      => (int)$nft['token'],
            'amount'     => $amount,
            'seller_uid' => (string)$nft['uid'],
            'created_ts' => $now,
        ]);

        return Transformer::order($order->toArray(), true, [
            Transformer::orderItem([
                'id'         => $order->id, // 占位，真实明细 id 在下面查
                'order_id'   => $order->id,
                'nft_id'     => $nft['nft_id'],
                'token'      => (int)$nft['token'],
                'amount'     => $amount,
                'created_ts' => $now,
            ]),
        ]);
    }

    /**
     * POST /order/secondary/quick —— 二级市场一键购买
     */
    public function secondaryQuick()
    {
        $uid = $this->uid();
        $iid = (string)$this->bodyNeed('iid');

        $nft = \app\model\Nft::where('iid', $iid)
            ->where('is_deleted', 0)
            ->where('pay_lock', 0)
            ->order('price', 'asc')
            ->find();

        if (!$nft) {
            throw ApiException::notFound();
        }

        $body = $this->body();
        $body['nftId'] = $nft['nft_id'];

        $this->request->setBody(json_encode($body));

        return $this->secondary();
    }

    /**
     * POST /order/secondary/batch —— 二级市场批量购买
     */
    public function secondaryBatch()
    {
        $uid = $this->uid();
        $nftIds = $this->body()['nftIds'] ?? [];

        if (!is_array($nftIds) || $nftIds === []) {
            throw ApiException::missing('nftIds');
        }

        $now  = $this->nowMillis();
        $ids  = [];
        $data = [];

        foreach ($nftIds as $nftId) {
            $nft = \app\model\Nft::where('nft_id', (string)$nftId)
                ->where('is_deleted', 0)
                ->where('pay_lock', 0)
                ->find();

            if (!$nft) {
                continue;
            }
            $nft = $nft->toArray();

            $amount = (int)$nft['buy_price'] > 0 ? (int)$nft['buy_price'] : (int)$nft['price'] * 100;

            $order = OrderModel::create([
                'uid'           => $uid,
                'iid'           => $nft['iid'],
                'order_type'    => self::TYPE_BATCH,
                'pay_way'       => (int)($this->body()['payWay'] ?? 1),
                'status'        => self::STATUS_UNPAID,
                'title'         => $nft['name'],
                'image'         => $nft['image'],
                'image_url'     => $nft['image_url'],
                'unit_price'    => (int)$nft['price'],
                'quantity'      => 1,
                'item_quantity' => 1,
                'amount'        => $amount,
                'subject'       => $nft['name'],
                'out_trade_no'  => 'ST' . $now . random_int(1000, 9999),
                'seller_uid'    => (string)$nft['uid'],
                'end_pay_time'  => $now + (int)config('shengtai.order.pay_window_ms'),
                'pay_time'      => 0,
                'cancelled_at'  => 0,
                'created_ts'    => $now,
            ]);

            $itemId = OrderItem::create([
                'order_id'   => $order->id,
                'nft_id'     => $nft['nft_id'],
                'token'      => (int)$nft['token'],
                'amount'     => $amount,
                'seller_uid' => (string)$nft['uid'],
                'created_ts' => $now,
            ]);

            \app\model\Nft::where('id', $nft['id'])->update([
                'pay_lock'    => 1,
                'sell_status' => 2,
                'status'      => 'locked',
            ]);

            $ids[] = (string)$order->id;
            $data[] = Transformer::orderItem($itemId->toArray());
        }

        if ($ids === []) {
            throw ApiException::notFound();
        }

        return [
            'orderIds' => $ids,
            'items'    => $data,
        ];
    }

    /**
     * POST /order/secondary/apply —— 申购下单
     */
    public function secondaryApply()
    {
        $uid = $this->uid();
        $iid = (string)$this->bodyNeed('iid');

        $item = \app\model\Item::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$item) {
            throw ApiException::notFound();
        }
        $item = $item->toArray();

        $quantity = (int)($this->body()['quantity'] ?? 1);
        $amount   = (int)$item['price'] * 100 * max(1, $quantity);
        $now      = $this->nowMillis();

        $order = OrderModel::create([
            'uid'           => $uid,
            'iid'           => $iid,
            'order_type'    => self::TYPE_SUBSCRIBE,
            'pay_way'       => (int)($this->body()['payWay'] ?? 1),
            'status'        => self::STATUS_UNPAID,
            'title'         => $item['name'],
            'image'         => $item['image'],
            'image_url'     => $item['image_url'],
            'unit_price'    => (int)$item['price'],
            'quantity'      => max(1, $quantity),
            'item_quantity' => max(1, $quantity),
            'amount'        => $amount,
            'subject'       => $item['name'],
            'out_trade_no'  => 'ST' . $now . random_int(1000, 9999),
            'end_pay_time'  => $now + (int)config('shengtai.order.pay_window_ms'),
            'pay_time'      => 0,
            'cancelled_at'  => 0,
            'created_ts'    => $now,
        ]);

        return Transformer::order($order->toArray());
    }

    /**
     * POST /order/secondary/trust —— 委托（求购）下单
     *
     * 委托单落nft_market_trust_orders，不是 orders 表——
     * 源站实测该端点返回的字段与 marketOrder() 一致（无 status）。
     */
    public function secondaryTrust()
    {
        $uid  = $this->uid();
        $iid  = (string)$this->bodyNeed('iid');
        $body = $this->body();

        $item = \app\model\Item::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$item) {
            throw ApiException::notFound();
        }
        $item = $item->toArray();

        $quantity = max(1, (int)($body['quantity'] ?? 1));
        $price    = (int)($body['unitPrice'] ?? $item['price'] * 100);
        $now      = $this->nowMillis();

        $row = NftMarketTrustOrder::create([
            'uid'                => $uid,
            'iid'                => $iid,
            'title'              => $item['name'],
            'image'              => $item['image'],
            'image_url'          => $item['image_url'],
            'unit_price'         => $price,
            'quantity'           => $quantity,
            'completed_quantity' => 0,
            'amount'             => $price * $quantity,
            'pay_way'            => (int)($body['payWay'] ?? 1),
            'status'             => self::STATUS_PROCESS,
            'end_pay_time'       => 0,
            'pay_time'           => 0,
            'cancelled_at'       => 0,
            'created_ts'         => $now,
        ]);

        return Transformer::marketOrder($row->toArray());
    }

    /**
     * PUT /order/cancel —— 取消订单
     */
    public function cancel()
    {
        $uid = $this->uid();
        $id  = (string)$this->bodyNeed('id');

        $order = OrderModel::where('id', $id)->where('uid', $uid)->where('is_deleted', 0)->find();
        if (!$order) {
            throw ApiException::notFound();
        }

        // 只有待付款/进行中可取消，源站对已完成订单的取消返回未找到
        if (!in_array((int)$order->status, [self::STATUS_UNPAID, self::STATUS_PROCESS], true)) {
            throw ApiException::notFound();
        }

        $now = $this->nowMillis();

        OrderModel::where('id', $order->id)->update([
            'status'       => self::STATUS_CANCELLED,
            'cancelled_at' => $now,
        ]);

        // 取消二级市场单要解锁 nft
        $items = OrderItem::where('order_id', $order->id)->select()->toArray();
        foreach ($items as $it) {
            \app\model\Nft::where('nft_id', $it['nft_id'])->update([
                'pay_lock'    => 0,
                'sell_status' => 0,
                'status'      => 'normal',
            ]);
        }

        $order->status       = self::STATUS_CANCELLED;
        $order->cancelled_at = $now;

        return Transformer::order($order->toArray());
    }

    /**
     * POST /order/pay/hfpay —— 汇付收银台地址
     *
     * ⚠️ 源站 GET /wallet/hfpay 的 data 是**纯字符串 URL**，
     * 不是对象。这里保持一致。
     */
    public function payHfpay()
    {
        $uid = $this->uid();
        $id  = (string)$this->bodyNeed('id');

        $order = OrderModel::where('id', $id)->where('uid', $uid)->where('is_deleted', 0)->find();
        if (!$order) {
            throw ApiException::notFound();
        }

        if ((int)$order->status !== self::STATUS_UNPAID) {
            throw ApiException::notFound();
        }

        $base = rtrim((string)config('shengtai.shengtai.media_base'), '/');

        return $base . '/cashier?outTradeNo=' . urlencode((string)$order['out_trade_no']);
    }

    /**
     * POST /order/pay/yeepay —— 易宝收银台
     *
     * ⚠️ 源站 GET /wallet/yeepay **恒报 code:20000 系统错误**（易宝通道未上线）。
     * 这里保持同样的行为，不臆造一个可用通道。
     */
    public function payYeepay()
    {
        // 与源站一致：易宝通道未上线，统一返回系统错误
        throw ApiException::system('系统错误');
    }
}