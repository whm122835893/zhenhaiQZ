<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;

/**
 * 市场寄售管理
 *
 * - GET  /admin/market/item-groups  藏品单品聚合（一级列表：按 iid 分组统计挂单）
 * - GET  /admin/market/sell-orders  挂单明细（支持 iid 过滤，二级视图）
 * - POST /admin/market/sell-orders/:id/off  强制下架
 *
 * 下架联动：挂单 is_deleted=1 + nft.sell_status=0，
 * 并按 system_configs.sell.cooldown_minutes 给卖家写入冷却（吸收司南 v2.2 #15）。
 */
class Market extends AdminBase
{
    /** GET /admin/market/item-groups —— 按藏品聚合在售/成交统计 */
    public function itemGroups()
    {
        $kw = $this->str('keyword');

        // group 查询 count 不准，总数用 COUNT(DISTINCT iid) 单独算
        $total = \think\facade\Db::name('nft_sell_orders')->alias('s')
            ->leftJoin('items i', 'i.iid = s.iid')
            ->where('s.is_deleted', 0)
            ->when($kw !== '', function ($q) use ($kw) {
                $q->whereLike('i.name', "%{$kw}%");
            })
            ->count('DISTINCT s.iid');

        $rows = \think\facade\Db::name('nft_sell_orders')->alias('s')
            ->leftJoin('items i', 'i.iid = s.iid')
            ->where('s.is_deleted', 0)
            ->when($kw !== '', function ($q) use ($kw) {
                $q->whereLike('i.name', "%{$kw}%");
            })
            ->field('s.iid,i.name as item_name,i.image_url as item_image,'
                . 'SUM(CASE WHEN s.status = 1 THEN 1 ELSE 0 END) as on_sale,'
                . 'SUM(CASE WHEN s.status = 2 THEN 1 ELSE 0 END) as sold,'
                . 'MIN(CASE WHEN s.status = 1 THEN s.price ELSE NULL END) as min_price,'
                . 'MAX(CASE WHEN s.status = 1 THEN s.price ELSE NULL END) as max_price,'
                . 'MAX(s.created_at) as last_time')
            ->group('s.iid')
            ->order('on_sale', 'desc')
            ->order('s.iid', 'desc')
            ->page($this->pageNo(), $this->pageSize(20))
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'iid'        => (string)$r['iid'],
                'itemName'   => (string)$r['item_name'],
                'itemImage'  => (string)($r['item_image'] ?: ''),
                'onSale'     => (int)$r['on_sale'],
                'sold'       => (int)$r['sold'],
                'minPrice'   => $r['min_price'] === null ? 0 : (float)$r['min_price'],
                'maxPrice'   => $r['max_price'] === null ? 0 : (float)$r['max_price'],
                'lastTime'   => (string)$r['last_time'],
            ];
        }

        return ['list' => $list, 'total' => $total];
    }

    public function sellOrders()
    {
        $q = \think\facade\Db::name('nft_sell_orders')->alias('s')
            ->leftJoin('nfts n', 'n.nft_id = s.nft_id')
            ->leftJoin('items i', 'i.iid = s.iid')
            ->leftJoin('user_profiles p', 'p.uid = s.uid')
            ->where('s.is_deleted', 0)
            ->field('s.id,s.nft_id,s.iid,s.uid,s.price,s.quantity,s.sold_quantity,s.market_type,s.status,s.fee_rate,s.fee_amount,s.actual_amount,s.created_at,i.name as item_name,i.image_url as item_image,p.nickname');

        // 二级视图：指定藏品单品的挂单
        $iid = $this->str('iid');
        if ($iid !== '') {
            $q->where('s.iid', $iid);
        }
        $status = $this->int('status', -1);
        if ($status >= 0) {
            $q->where('s.status', $status);
        }
        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->whereLike('i.name', "%{$kw}%");
        }
        $q->order('s.id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r['id']          = (string)$r['id'];
            $r['nftId']       = (string)$r['nft_id'];
            $r['iid']         = (string)$r['iid'];
            $r['price']       = (float)$r['price'];
            $r['feeAmount']   = (float)$r['fee_amount'];
            $r['actualAmount']= (float)$r['actual_amount'];
            $r['feeRate']     = (float)$r['fee_rate'];
            $r['nickname']    = (string)($r['nickname'] ?: ('用户' . substr((string)$r['uid'], -4)));
            $r['itemImage']   = (string)($r['item_image'] ?: '');
            unset($r['nft_id'], $r['fee_amount'], $r['actual_amount'], $r['fee_rate']);
        }
        unset($r);
        return $data;
    }

    /** 强制下架 */
    public function off()
    {
        $this->needWriter();
        $id = $this->needInt('id');
        $o  = \think\facade\Db::name('nft_sell_orders')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$o) {
            throw ApiException::notFound('挂单不存在');
        }
        if ((int)$o['status'] !== 1) {
            throw new ApiException('仅「在售」挂单可下架', ApiException::CODE_PARAM_TYPE);
        }

        \think\facade\Db::transaction(function () use ($o) {
            \think\facade\Db::name('nft_sell_orders')->where('id', (int)$o['id'])->update(['is_deleted' => 1]);
            \think\facade\Db::name('nfts')->where('nft_id', (int)$o['nft_id'])->update(['sell_status' => 0]);

            // 卖家冷却（分钟，运营参数）
            $minutes = \app\service\SystemConfigService::int('sell.cooldown_minutes', 3);
            if ($minutes > 0) {
                $until = (int)round(microtime(true) * 1000) + $minutes * 60000;
                \think\facade\Db::name('nft_sell_orders')
                    ->where('uid', (int)$o['uid'])
                    ->where('is_deleted', 1)
                    ->update(['cooldown_until' => $until]);
            }
        });

        $this->opLog('market', 'off_shelf', $o, ['is_deleted' => 1], 'sell_order:' . $o['id']);
        return ['id' => (string)$o['id'], 'status' => '已下架'];
    }
}
