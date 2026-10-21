<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * 订单管理 —— GET /admin/orders
 */
class Orders extends AdminBase
{
    public function index()
    {
        $q = \think\facade\Db::name('orders')->where('is_deleted', 0)
            ->field('id,uid,iid,order_type,pay_way,status,title,unit_price,quantity,amount,out_trade_no,seller_uid,pay_time,cancelled_at,created_at');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                $w->whereLike('title', "%{$kw}%")->whereOr('out_trade_no', 'like', "%{$kw}%");
            });
        }
        $status = $this->int('status', -1);
        if ($status >= 0) {
            $q->where('status', $status);
        }
        $q->order('id', 'desc');

        $data = $this->adminPage($q);
        $uids = array_values(array_unique(array_column($data['list'], 'uid')));
        $users = $uids === [] ? [] : \think\facade\Db::name('user_profiles')->whereIn('uid', $uids)->column('nickname', 'uid');
        foreach ($data['list'] as &$r) {
            $r['id']         = (string)$r['id'];
            $r['iid']        = (string)$r['iid'];
            $r['uid']        = (string)$r['uid'];
            $r['amount']     = (float)$r['amount'];
            $r['unitPrice']  = (float)$r['unit_price'];
            $r['payTime']    = (int)$r['pay_time'] > 0 ? (string)$r['pay_time'] : null;
            $r['nickname']   = (string)($users[$r['uid']] ?? ('用户' . substr((string)$r['uid'], -4)));
            unset($r['unit_price']);
        }
        unset($r);
        return $data;
    }

    /** GET /admin/orders/{id} —— 订单详情（买家/卖家 + 支付流水 + NFT 明细） */
    public function detail()
    {
        $id = $this->needInt('id');
        $row = \think\facade\Db::name('orders')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            throw ApiException::notFound('订单不存在');
        }

        $uids = array_values(array_unique([(string)$row['uid'], (string)$row['seller_uid']]));
        $users = \think\facade\Db::name('user_profiles')->whereIn('uid', $uids)->column('nickname', 'uid');

        $row['id']       = (string)$row['id'];
        $row['uid']      = (string)$row['uid'];
        $row['iid']      = (string)$row['iid'];
        $row['sellerUid']= (string)$row['seller_uid'];
        $row['relatedOrderId'] = (string)$row['related_order_id'];
        $row['amount']   = (float)$row['amount'];
        $row['unitPrice']= (float)$row['unit_price'];
        $row['nickname'] = (string)($users[(string)$row['uid']] ?? ('用户' . substr((string)$row['uid'], -4)));
        $row['sellerNickname'] = (string)($users[(string)$row['seller_uid']] ?? '');
        foreach (['pay_time', 'end_pay_time', 'cancelled_at'] as $f) {
            $row[$f] = (int)$row[$f] > 0 ? (string)$row[$f] : null;
        }

        // NFT 明细（二级市场单才有）
        $items = \think\facade\Db::name('order_items')->where('order_id', $id)->select()->toArray();
        $nftIds = array_column($items, 'nft_id');
        $nfts = $nftIds === [] ? [] : \think\facade\Db::name('nfts')
            ->whereIn('nft_id', $nftIds)
            ->column('token,nft_id,uid,sell_status', 'nft_id');
        $row['nftItems'] = array_map(static function ($it) use ($nfts) {
            $n = $nfts[$it['nft_id']] ?? null;
            return [
                'nftId'     => (string)$it['nft_id'],
                'token'     => (int)$it['token'],
                'amount'    => (float)$it['amount'],
                'sellerUid' => (string)($it['seller_uid'] ?? '0'),
                'currentUid'=> $n ? (string)$n['uid'] : null,
                'sellStatus'=> $n ? (int)$n['sell_status'] : null,
            ];
        }, $items);

        // 支付流水
        $row['payments'] = array_map(static function ($p) {
            return [
                'id'        => (string)$p['id'],
                'channel'   => (string)$p['channel'],
                'tradeNo'   => (string)$p['trade_no'],
                'amount'    => (float)$p['amount'],
                'status'    => (string)$p['status'],
                'paidAt'    => $p['paid_at'] ? (string)$p['paid_at'] : null,
                'createdAt' => (string)$p['created_at'],
            ];
        }, \think\facade\Db::name('order_payments')->where('order_id', $id)->order('id', 'asc')->select()->toArray());

        return $row;
    }

    /**
     * POST /admin/orders/{id}/cancel —— 强制取消（客服处理卡单）
     * 待付款/进行中 → 已取消；二级市场单解锁 NFT（与 C 端取消同口径）。
     */
    public function cancel()
    {
        $this->needWriter();
        $id = $this->needInt('id');
        $reason = trim((string)($this->body()['reason'] ?? ''));

        $order = \think\facade\Db::name('orders')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$order) {
            throw ApiException::notFound('订单不存在');
        }
        if (!in_array((int)$order['status'], [0, 1], true)) {
            throw new ApiException('仅待付款/进行中的订单可取消', ApiException::CODE_PARAM_TYPE);
        }

        $now = (int)round(microtime(true) * 1000);
        \think\facade\Db::name('orders')->where('id', $id)->update([
            'status'       => 3,
            'cancelled_at' => $now,
            'remark'       => $reason !== '' ? '强制取消：' . $reason : '管理员强制取消',
        ]);

        // 与 C 端 Order::cancel 同口径：解锁被下单锁定的 NFT
        $items = \think\facade\Db::name('order_items')->where('order_id', $id)->select()->toArray();
        foreach ($items as $it) {
            \think\facade\Db::name('nfts')->where('nft_id', $it['nft_id'])->update([
                'pay_lock'    => 0,
                'sell_status' => 0,
                'status'      => 0,
            ]);
        }

        $this->opLog('order', 'force_cancel', $order, ['status' => 3, 'reason' => $reason], 'order:' . $id);
        return ['id' => (string)$id, 'status' => 3];
    }

    /**
     * POST /admin/orders/{id}/mark-paid —— 标记已支付（线下补单）
     * 仅改订单/支付流水状态；NFT 分配与卖家结算依赖支付回调服务，
     * 本地环境无支付网关，此处不做过户（操作日志可追溯）。
     */
    public function markPaid()
    {
        $this->needSuper();
        $id = $this->needInt('id');
        $reason = trim((string)($this->body()['reason'] ?? ''));

        $order = \think\facade\Db::name('orders')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$order) {
            throw ApiException::notFound('订单不存在');
        }
        if (!in_array((int)$order['status'], [0, 1], true)) {
            throw new ApiException('仅待付款/进行中的订单可标记支付', ApiException::CODE_PARAM_TYPE);
        }

        $now = (int)round(microtime(true) * 1000);
        \think\facade\Db::name('orders')->where('id', $id)->update([
            'status'   => 2,
            'pay_time' => $now,
            'remark'   => $reason !== '' ? '标记已支付：' . $reason : '管理员标记已支付',
        ]);
        \think\facade\Db::name('order_payments')->where('order_id', $id)->where('status', '<>', 'paid')->update([
            'status'  => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        // 邀请奖励结算（时机=首单支付后 时在此发放给邀请人；失败不影响补单）
        try {
            \app\service\InviteService::onOrderPaid((string)$order['uid']);
        } catch (\Throwable) {
        }

        $this->opLog('order', 'mark_paid', $order, ['status' => 2, 'reason' => $reason], 'order:' . $id);
        return ['id' => (string)$id, 'status' => 2];
    }
}
