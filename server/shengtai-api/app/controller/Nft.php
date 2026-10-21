<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\helper\Snowflake;
use app\model\Item as ItemModel;
use app\model\ItemFollow;
use app\model\Nft as NftModel;
use app\model\NftBoxOpenLog;
use app\model\NftGroup as NftGroupModel;
use app\model\NftMarketApplyOrder;
use app\model\NftMarketFollow;
use app\model\NftMarketTrustOrder;
use app\model\NftMergeNeed;
use app\model\NftMergeProduce;
use app\model\NftMergeRecipe;
use app\model\NftMergeRecord;
use app\model\NftSellOrder;
use app\model\NftTradeLog;
use app\service\Transformer;

/**
 * NFT 控制器
 *
 * 对应源站 nft 分包。
 *
 * 源站实测要点：
 *  - 二级市场统计字段单位混用：minPrice/maxPrice 用「分」，
 *    而 item.price 用「元」，todayTradeAmount 也是「分」
 *  - GET /nft/secondary/sold 的成交记录只有 5 个字段（见 Transformer::tradeLog）
 *  - nfts.status 在库里是 tinyint 数字枚举（0=普通 1=在售/挂单中 2=已售），
 *    Transformer::nft() 输出时也是 int —— 这里保持源站行为
 */
class Nft extends Base
{
    /** 旧字符串枚举兜底映射（读侧兼容） */
    private const STATUS_MAP = [
        'normal' => 0,
        'locked' => 1,
        'sold'   => 2,
    ];

    /** sell_status 枚举 */
    private const SELL_STATUS = [
        0 => '未上架',
        1 => '已上架',
        2 => '锁定中',
        3 => '已售出',
    ];

    /* ====================================================================
     * 只读端点
     * ================================================================== */

    /**
     * GET /nft —— 我的 NFT 列表
     */
    public function index()
    {
        $uid = $this->uid();

        $query = NftModel::where('uid', $uid)->where('is_deleted', 0);

        $iid = $this->str('iid');
        if ($iid !== '') {
            $query->where('iid', $iid);
        }

        $status = $this->str('status');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $rows = $query->order('id', 'desc')->select()->toArray();

        return array_map(
            static fn ($row) => Transformer::nft(self::normalizeNft($row)),
            $rows
        );
    }

    /**
     * GET /nft/group —— 藏品持仓分组
     *
     * 按 iid 聚合，ownQuantity 是该用户在该 iid 下的持有数。
     */
    public function group()
    {
        $uid = $this->uid();

        $rows = NftGroupModel::where('own_quantity', '>', 0)
            ->order('updated_at', 'desc')
            ->select()
            ->toArray();

        // 补上当前用户的实际持有数
        $counts = [];
        $nfts = NftModel::where('uid', $uid)->where('is_deleted', 0)->select()->toArray();
        foreach ($nfts as $n) {
            $iid = (string)$n['iid'];
            $counts[$iid] = ($counts[$iid] ?? 0) + 1;
        }

        $data = [];
        foreach ($rows as $row) {
            $iid = (string)$row['iid'];
            $row['own_quantity'] = $counts[$iid] ?? 0;
            if ($row['own_quantity'] === 0) {
                continue;
            }
            $data[] = Transformer::nftGroup($row);
        }

        return $data;
    }

    /**
     * GET /nft/{nftId} —— NFT 详情
     */
    public function detail()
    {
        $uid = $this->uid();
        $nftId = (string)($this->request->param('nftId') ?? '');

        if ($nftId === '') {
            throw ApiException::missing('nftId');
        }

        $row = NftModel::where('nft_id', $nftId)->where('is_deleted', 0)->find();
        if (!$row) {
            throw ApiException::notFound();
        }

        $arr = self::normalizeNft($row->toArray());

        // 补充该 NFT 的成交记录（源站详情页展示）
        $logs = NftTradeLog::where('nft_id', $nftId)
            ->order('sold_time', 'desc')
            ->select()
            ->toArray();

        $arr['tradeLogs'] = array_map(
            static fn ($l) => Transformer::tradeLog($l),
            $logs
        );

        return $arr;
    }

    /**
     * GET /nft/secondary —— 二级市场列表
     *
     * 返回藏品维度的市场统计（与 /item 的区别是多了交易统计字段）。
     */
    public function secondary()
    {
        $pageNo   = $this->pageNo();
        $pageSize = $this->pageSize();

        $query = ItemModel::where('is_deleted', 0)->where('is_secondary', 1);

        $cid = $this->str('cid');
        if ($cid !== '') {
            $query->where('cid', $cid);
        }

        $total = (clone $query)->count();

        // 源站实测默认排序为 iid 倒序（最新发布的藏品排在前面，
        // 与源站 /nft/secondary 返回顺序逐条比对确认）。
        // items 表无独立 sort 列，iid 是雪花 ID，倒序即「最新优先」。
        $rows = $query
            ->order('iid', 'desc')
            ->page($pageNo, $pageSize)
            ->select()
            ->toArray();

        $data = [];
        foreach ($rows as $row) {
            $data[] = Transformer::secondaryItem(self::withMarketStats($row));
        }

        // 源站实测返回裸数组（前端直接 res.data 展开），非 {list,total}
        return $this->pageList($data, $total);
    }

    /**
     * GET /nft/secondary/{iid} —— 二级市场藏品详情
     *
     * ⚠️ 源站实测两个与列表接口的差异（2026-10-19 四个藏品逐一验证）：
     *   1. 不返回 sells 数组（在售挂单走独立的 /nft/secondary/sell?iid=）
     *   2. lastTradeAmount 恒为 null（列表接口同一藏品却有值 500/64500/...）
     * 之前本地两处都没对齐：详情多吐 sells、lastTradeAmount 透传了缓存值。
     */
    public function secondaryDetail()
    {
        $iid = (string)($this->request->param('iid') ?? '');

        if ($iid === '') {
            throw ApiException::missing('iid');
        }

        $row = ItemModel::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$row) {
            throw ApiException::notFound();
        }

        $result = Transformer::secondaryItem(self::withMarketStats($row->toArray()));
        // 源站详情接口的 lastTradeAmount 恒为 null（与列表接口的有值行为不同）
        $result['lastTradeAmount'] = null;

        return $result;
    }

    /**
     * GET /nft/secondary/sell —— 某藏品的在售挂单
     */
    /**
     * GET /nft/secondary/sell —— 某藏品的在售挂单
     *
     * ⚠️ 源站实测：本接口返回的是 **NFT 对象数组**（18 字段），
     *    不是挂单表原始行。每条 = 一个在售 NFT：
     *      nftId/uid/iid/name/imageUrl/image/token/status/getTime/
     *      price(挂售价)/payLock/payWay/chainId/chainCompleted/buyPrice/
     *      item(null)/owner(null)/isSelf(false)
     *    之前返回的是挂单形态（nftId/iid/price/quantity/soldQuantity/
     *    sellerId/createTime），前端 `#{{ row.token }}` 直接渲染成
     *    undefined——寄售 tab 的编号列全是错的。
     */
    public function secondarySell()
    {
        $iid = $this->need('iid');

        $rows = NftSellOrder::where('iid', $iid)
            ->where('is_deleted', 0)
            ->where('status', 1)
            ->order('price', 'asc')
            ->select()
            ->toArray();

        // 一次性把关联 NFT 查出来，避免 N+1
        $nftIds = array_map(static fn ($s) => (string)$s['nft_id'], $rows);
        $nfts = [];
        if ($nftIds) {
            $nftRows = NftModel::whereIn('nft_id', $nftIds)->select()->toArray();
            foreach ($nftRows as $n) {
                $nfts[(string)$n['nft_id']] = $n;
            }
        }

        return array_map(
            static fn ($s) => Transformer::secondarySellItem($s, $nfts[(string)$s['nft_id']] ?? null),
            $rows
        );
    }

    /**
     * GET /nft/secondary/sold —— 成交记录
     *
     * ⚠️ 源站实测只有 5 个字段，别多加。
     */
    public function secondarySold()
    {
        $iid = $this->str('iid');

        $query = NftTradeLog::whereRaw('1=1');
        if ($iid !== '') {
            $query->where('iid', $iid);
        }

        $rows = $query->order('sold_time', 'desc')->limit(100)->select()->toArray();

        return array_map(static fn ($l) => Transformer::tradeLog($l), $rows);
    }

    /**
     * GET /nft/secondary/apply —— 求购单列表
     */
    public function secondaryApply()
    {
        $iid = $this->str('iid');

        $query = NftMarketApplyOrder::where('is_deleted', 0);

        if ($iid !== '') {
            $query->where('iid', $iid);
        }

        $rows = $query->order('created_ts', 'desc')->limit(100)->select()->toArray();

        return array_map(static fn ($r) => Transformer::marketOrder($r), $rows);
    }

    /**
     * GET /nft/secondary/trust —— 委托单列表
     */
    public function secondaryTrust()
    {
        $iid = $this->str('iid');

        $query = NftMarketTrustOrder::where('is_deleted', 0);

        if ($iid !== '') {
            $query->where('iid', $iid);
        }

        $rows = $query->order('created_ts', 'desc')->limit(100)->select()->toArray();

        return array_map(static fn ($r) => Transformer::marketOrder($r), $rows);
    }

    /**
     * GET /nft/secondary/follow —— 我关注的藏品
     */
    public function secondaryFollow()
    {
        $uid = $this->uid();

        $rows = NftMarketFollow::where('uid', $uid)->order('id', 'desc')->select()->toArray();

        $data = [];
        foreach ($rows as $r) {
            $item = ItemModel::where('iid', $r['iid'])->where('is_deleted', 0)->find();
            if (!$item) {
                continue;
            }
            $data[] = Transformer::item($item->toArray(), 0, true);
        }

        return $data;
    }

    /**
     * GET /nft/digitalAlbum/calendar —— 数字相册日历
     *
     * 源站该端点返回按日期聚合的铸造记录。
     */
    public function digitalAlbumCalendar()
    {
        $uid = $this->uid();

        $rows = NftModel::where('uid', $uid)
            ->where('is_deleted', 0)
            ->where('get_time', '>', 0)
            ->order('get_time', 'desc')
            ->select()
            ->toArray();

        // 按 YYYY-MM-DD 聚合
        $days = [];
        foreach ($rows as $r) {
            $ts = (int)$r['get_time'];
            if ($ts <= 0) {
                continue;
            }
            $day = date('Y-m-d', intdiv($ts, 1000));
            $days[$day] = ($days[$day] ?? 0) + 1;
        }

        $data = [];
        foreach ($days as $day => $count) {
            $data[] = ['date' => $day, 'count' => $count];
        }

        return $data;
    }

    /* ====================================================================
     * 写端点
     * ================================================================== */

    /**
     * POST /nft/box/open —— 盲盒开盒
     */
    public function openBox()
    {
        $uid = $this->uid();
        $iid = (string)$this->bodyNeed('iid');

        $item = ItemModel::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$item) {
            throw ApiException::notFound();
        }
        $item = $item->toArray();

        // 开盒时间窗校验
        $now = $this->nowMillis();
        if ((int)$item['box_open_time'] > 0 && $now < (int)$item['box_open_time']) {
            throw ApiException::system('未到开盒时间');
        }

        // 消耗一张未开盒的 nft
        $nft = NftModel::where('uid', $uid)
            ->where('iid', $iid)
            ->where('is_deleted', 0)
            ->where('chain_completed', 0)
            ->order('id', 'asc')
            ->find();

        if (!$nft) {
            throw ApiException::notFound('没有可开盒的藏品');
        }

        $nftId = (string)$nft['nft_id'];

        NftModel::where('id', $nft['id'])->update([
            'chain_completed' => 1,
            'chain_id'        => '0x' . bin2hex(random_bytes(16)),
            'get_time'        => $now,
        ]);

        NftBoxOpenLog::create([
            'uid'         => $uid,
            'iid'         => $iid,
            'nft_id'      => $nftId,
            'cost_amount' => (int)$item['price'] * 100,
            'pay_way'     => (int)($this->body()['payWay'] ?? 1),
        ]);

        return [
            'nftId'   => $nftId,
            'chainId' => '0x' . bin2hex(random_bytes(16)),
            'getTime' => (string)$now,
        ];
    }

    /**
     * POST /nft/sell/up —— 单件寄售上架
     */
    public function sellUp()
    {
        $uid = $this->uid();
        $body = $this->body();

        $nftId = (string)($body['nftId'] ?? '');
        $price = (int)($body['price'] ?? 0);

        if ($nftId === '') {
            throw ApiException::missing('nftId');
        }
        if ($price <= 0) {
            throw ApiException::paramType('pricemust be number');
        }

        $nft = NftModel::where('nft_id', $nftId)
            ->where('uid', $uid)
            ->where('is_deleted', 0)
            ->find();

        if (!$nft) {
            throw ApiException::notFound();
        }

        // 藏品级寄售开关（管理端 items.is_secondary）：关 = 该藏品禁止寄售
        $item = \app\model\Item::where('iid', $nft['iid'])->where('is_deleted', 0)->find();
        if (!$item || (int)$item['is_secondary'] !== 1) {
            throw new ApiException('该藏品未开放寄售', ApiException::CODE_SYSTEM_ERROR);
        }

        // 冷却检查（吸收司南 v2.2 #15：寄售冷却落库，参数 system_configs.sell.cooldown_minutes）
        $nowMs = (int)round(microtime(true) * 1000);
        $cooldown = (int)NftSellOrder::where('uid', $uid)
            ->where('cooldown_until', '>', $nowMs)
            ->max('cooldown_until');
        if ($cooldown > $nowMs) {
            throw new ApiException(
                '寄售冷却中，请 ' . (int)ceil(($cooldown - $nowMs) / 60000) . ' 分钟后再试',
                ApiException::CODE_SYSTEM_ERROR
            );
        }

        // 手续费快照（费率 system_configs.sell.fee_rate）
        $feeRate = \app\service\SystemConfigService::float('sell.fee_rate', 0.0);
        $feeAmount = round($price * $feeRate, 2);

        NftSellOrder::create([
            'nft_id'        => $nftId,
            'iid'           => $nft['iid'],
            'uid'           => $uid,
            'price'         => $price,
            'quantity'      => 1,
            'sold_quantity' => 0,
            'market_type'   => (int)($body['marketType'] ?? 1),
            'status'        => 1,
            'sold_at'       => 0,
            'buyer_uid'     => 0,
            'fee_rate'      => $feeRate,
            'fee_amount'    => $feeAmount,
            'actual_amount' => round($price - $feeAmount, 2),
            'cooldown_until'=> 0,
        ]);

        NftModel::where('id', $nft['id'])->update([
            'sell_status' => 1,
            'price'       => $price,
        ]);

        return [
            'nftId'      => $nftId,
            'price'      => $price,
            'sellStatus' => self::SELL_STATUS[1],
        ];
    }

    /**
     * POST /nft/sell/up/batch —— 批量寄售上架
     */
    public function sellUpBatch()
    {
        $uid = $this->uid();
        $body = $this->body();

        $nftIds = $body['nftIds'] ?? [];
        $price  = (int)($body['price'] ?? 0);

        if (!is_array($nftIds) || $nftIds === []) {
            throw ApiException::missing('nftIds');
        }
        if ($price <= 0) {
            throw ApiException::paramType('pricemust be number');
        }

        // 冷却检查（同单件上架，批量只查一次）
        $nowMs = (int)round(microtime(true) * 1000);
        $cooldown = (int)NftSellOrder::where('uid', $uid)
            ->where('cooldown_until', '>', $nowMs)
            ->max('cooldown_until');
        if ($cooldown > $nowMs) {
            throw new ApiException(
                '寄售冷却中，请 ' . (int)ceil(($cooldown - $nowMs) / 60000) . ' 分钟后再试',
                ApiException::CODE_SYSTEM_ERROR
            );
        }

        // 手续费快照（费率 system_configs.sell.fee_rate）
        $feeRate = \app\service\SystemConfigService::float('sell.fee_rate', 0.0);
        $feeAmount = round($price * $feeRate, 2);

        $success = [];

        foreach ($nftIds as $nftId) {
            $nftId = (string)$nftId;

            $nft = NftModel::where('nft_id', $nftId)
                ->where('uid', $uid)
                ->where('is_deleted', 0)
                ->find();

            if (!$nft) {
                continue;
            }

            // 藏品级寄售开关（同单件上架）：未开放寄售的藏品跳过
            $item = \app\model\Item::where('iid', $nft['iid'])->where('is_deleted', 0)->find();
            if (!$item || (int)$item['is_secondary'] !== 1) {
                continue;
            }

            NftSellOrder::create([
                'nft_id'        => $nftId,
                'iid'           => $nft['iid'],
                'uid'           => $uid,
                'price'         => $price,
                'quantity'      => 1,
                'sold_quantity' => 0,
                'market_type'   => (int)($body['marketType'] ?? 1),
                'status'        => 1,
                'sold_at'       => 0,
                'buyer_uid'     => 0,
                'fee_rate'      => $feeRate,
                'fee_amount'    => $feeAmount,
                'actual_amount' => round($price - $feeAmount, 2),
                'cooldown_until'=> 0,
            ]);

            NftModel::where('id', $nft['id'])->update([
                'sell_status' => 1,
                'price'       => $price,
            ]);

            $success[] = $nftId;
        }

        if ($success === []) {
            throw ApiException::notFound();
        }

        return [
            'success' => $success,
            'count'   => count($success),
            'price'   => $price,
        ];
    }

    /**
     * PUT /nft/sell/down —— 下架
     */
    public function sellDown()
    {
        $uid = $this->uid();
        $nftId = (string)$this->bodyNeed('nftId');

        $nft = NftModel::where('nft_id', $nftId)
            ->where('uid', $uid)
            ->where('is_deleted', 0)
            ->find();

        if (!$nft) {
            throw ApiException::notFound();
        }

        NftSellOrder::where('nft_id', $nftId)
            ->where('uid', $uid)
            ->where('is_deleted', 0)
            ->where('status', 1)
            ->update(['status' => 0]);

        NftModel::where('id', $nft['id'])->update(['sell_status' => 0]);

        return [
            'nftId'      => $nftId,
            'sellStatus' => self::SELL_STATUS[0],
        ];
    }

    /**
     * GET /nft/sell/sold —— 我售出的记录
     */
    public function sellSold()
    {
        $uid = $this->uid();

        $rows = NftSellOrder::where('uid', $uid)
            ->where('is_deleted', 0)
            ->where('status', 3)
            ->order('sold_at', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        return array_map(static fn ($s) => [
            'nftId'      => Transformer::id($s['nft_id']),
            'iid'        => Transformer::id($s['iid']),
            'price'      => (int)$s['price'],
            'quantity'   => (int)$s['quantity'],
            'soldTime'   => Transformer::ts((int)$s['sold_at']),
            'buyerId'    => Transformer::id($s['buyer_uid']),
            'marketType' => (int)$s['market_type'],
        ], $rows);
    }

    /**
     * POST /nft/sell/apply —— 提交求购/委托申请
     *
     * 源站的「申购」入口，与 GET /nft/secondary/apply（查询）配对。
     */
    public function sellApply()
    {
        $uid = $this->uid();
        $body = $this->body();

        $iid = (string)($body['iid'] ?? '');
        if ($iid === '') {
            throw ApiException::missing('iid');
        }

        $item = ItemModel::where('iid', $iid)->where('is_deleted', 0)->find();
        if (!$item) {
            throw ApiException::notFound();
        }
        $item = $item->toArray();

        $quantity = max(1, (int)($body['quantity'] ?? 1));
        $price    = (int)($body['unitPrice'] ?? 0);
        $now      = $this->nowMillis();

        $row = NftMarketApplyOrder::create([
            'uid'                => $uid,
            'iid'                => $iid,
            'title'              => $item['name'],
            'image'              => $item['image'],
            'image_url'          => $item['image_url'],
            'unit_price'         => $price,
            'quantity'           => $quantity,
            'completed_quantity' => 0,
            'amount'             => $price * $quantity,
            'created_ts'         => $now,
            'pay_way'            => (int)($body['payWay'] ?? 1),
            'status'             => 1,
            'expire_at'          => $now + 86400000,
        ]);

        return Transformer::marketOrder($row->toArray());
    }

    /**
     * POST /nft/merge/v1 —— 合成 NFT
     *
     * 源站合成规则：按配方消耗 needs 里的藏品，产出 produces 里的藏品。
     * own_quantity 字段就是记录当前账号每种材料持有多少的用途。
     */
    public function merge()
    {
        $uid = $this->uid();
        $body = $this->body();

        $mergeId = (string)($body['mergeId'] ?? '');
        if ($mergeId === '') {
            throw ApiException::missing('mergeId');
        }

        $recipe = NftMergeRecipe::where('id', $mergeId)->where('is_deleted', 0)->find();
        if (!$recipe) {
            throw ApiException::notFound();
        }

        $needs = NftMergeNeed::where('merge_id', $mergeId)->select()->toArray();
        if ($needs === []) {
            throw ApiException::notFound();
        }

        // 校验材料是否够
        $owned = [];
        $myNfts = NftModel::where('uid', $uid)->where('is_deleted', 0)->select()->toArray();
        foreach ($myNfts as $n) {
            $iid = (string)$n['iid'];
            $owned[$iid] = ($owned[$iid] ?? 0) + 1;
        }

        $consumeIds = [];

        foreach ($needs as $need) {
            $iid = (string)$need['iid'];
            $needQty = (int)$need['quantity'];
            $have = $owned[$iid] ?? 0;

            if ($have < $needQty) {
                throw ApiException::system('材料不足');
            }

            // 扣减
            $left = $needQty;
            foreach ($myNfts as $n) {
                if ($left <= 0) {
                    break;
                }
                if ((string)$n['iid'] !== $iid || $n['pay_lock']) {
                    continue;
                }
                NftModel::where('id', $n['id'])->update(['is_deleted' => 1]);
                $consumeIds[] = (string)$n['nft_id'];
                $left--;
            }
            $owned[$iid] = $have - $needQty;
        }

        // 产出
        $produces = NftMergeProduce::where('merge_id', $mergeId)->select()->toArray();
        $produceIds = [];

        foreach ($produces as $p) {
            $qty = max(1, (int)$p['quantity']);
            for ($k = 0; $k < $qty; $k++) {
                $nftId = Snowflake::nextIdStr();
                NftModel::create([
                    'nft_id'          => $nftId,
                    'uid'             => $uid,
                    'iid'             => $p['iid'],
                    'name'            => $p['name'],
                    'image'           => $p['image'],
                    'image_url'       => $p['image_url'],
                    'token'           => 1,
                    'status'          => 0, // nfts.status 是 tinyint 数字枚举 0=普通
                    'get_time'        => $this->nowMillis(),
                    'price'           => 0,
                    'buy_price'       => 0,
                    'pay_lock'        => 0,
                    'pay_way'         => null,
                    'chain_id'        => '',
                    'chain_completed' => 0,
                    'sell_status'     => 0,
                ]);
                $produceIds[] = $nftId;
            }
        }

        NftMergeRecord::create([
            'uid'              => $uid,
            'mid'              => $mergeId,
            'consume_nft_ids'  => json_encode($consumeIds),
            'produce_nft_ids'  => json_encode($produceIds),
            'status'           => 1,
        ]);

        return [
            'mergeId'      => Transformer::id($mergeId),
            'consumeNftIds' => $consumeIds,
            'produceNftIds' => $produceIds,
        ];
    }

    /* ====================================================================
     * 内部工具
     * ================================================================== */

    /**
     * nfts.status 归一化为源站输出的数字。
     * 库里是 tinyint 数字枚举（0=普通 1=在售/挂单中 2=已售），
     * 字符串值（normal/locked/sold）按旧映射兜底。
     */
    private static function normalizeNft(array $row): array
    {
        $status = $row['status'] ?? 0;
        $row['status'] = is_numeric($status)
            ? (int)$status
            : (self::STATUS_MAP[(string)$status] ?? 0);
        return $row;
    }

    /**
     * 补齐二级市场统计字段
     *
     * ⚠️ 单位对齐（源站实测，务必保留）：
     *   item.price          → 元
     *   min_price/max_price → 分
     *   trade_amount        → 分
     */
    private static function withMarketStats(array $row): array
    {
        $iid = (string)($row['iid'] ?? '');

        // 市场统计以 items 表缓存列为准（源站同款架构：items 表维护
        // sell_quantity/min_price/max_price/trade_quantity/today_trade_*
        // 等缓存列，/nft/secondary 直接透传，不对成交表实时 COUNT——
        // 源站臻海蓝卡成交 31 万+笔，实时聚合不现实）。
        //
        // 仅当缓存表明「无任何市场数据」（在售 0、成交 0、无地板价）时，
        // 才回退到挂单/成交表实时聚合——覆盖演示环境新建藏品、
        // 灌了挂单但没回填 items 缓存的场景。
        $cacheEmpty = (int)($row['sell_quantity'] ?? 0) === 0
            && (int)($row['trade_quantity'] ?? 0) === 0
            && ($row['min_price'] ?? null) === null;
        if (!$cacheEmpty) {
            return $row;
        }

        // ---- 以下为兜底：实时聚合 ----
        // 从挂单表聚合
        $sells = NftSellOrder::where('iid', $iid)
            ->where('is_deleted', 0)
            ->select()
            ->toArray();

        $sellQuantity = 0;
        $prices = [];
        foreach ($sells as $s) {
            $sellQuantity += (int)$s['quantity'] - (int)$s['sold_quantity'];
            if ((int)$s['status'] === 1) {
                $prices[] = (int)$s['price'];
            }
        }

        $row['sell_quantity'] = $sellQuantity;
        $row['min_price']     = $prices ? min($prices) : null;
        $row['max_price']     = $prices ? max($prices) : null;

        // 成交统计
        $logs = NftTradeLog::where('iid', $iid)->select()->toArray();

        $tradeQty = count($logs);
        $tradeAmt = 0;
        $todayQty = 0;
        $todayAmt = 0;
        $todayStart = (int)floor(strtotime('today midnight') * 1000);

        foreach ($logs as $l) {
            $amt = (int)$l['sold_price'];
            $tradeAmt += $amt;
            if ((int)$l['sold_time'] >= $todayStart) {
                $todayQty++;
                $todayAmt += $amt;
            }
        }

        $row['trade_quantity']       = $tradeQty;
        $row['trade_amount']         = $tradeAmt > 0 ? $tradeAmt : null;
        $row['today_trade_quantity'] = $todayQty;
        $row['today_trade_amount']   = $todayAmt > 0 ? $todayAmt : null;

        // last_trade_amount / hour_trade_quantity / today_trade_max_amount /
        // today_trade_min_amount / price_change 源站本就返回 null 或不产源，
        // 缓存列为 null 时保持 null，不做臆造
        return $row;
    }
}