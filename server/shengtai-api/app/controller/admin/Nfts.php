<?php
declare(strict_types=1);

namespace app\controller\admin;

/**
 * NFT 持仓 —— GET /admin/nfts（只读）
 */
class Nfts extends AdminBase
{
    public function index()
    {
        $q = \think\facade\Db::name('nfts')->alias('n')
            ->leftJoin('items i', 'i.iid = n.iid')
            ->leftJoin('user_profiles p', 'p.uid = n.uid')
            ->where('n.is_deleted', 0)
            ->field('n.id,n.nft_id,n.iid,n.uid,n.price,n.sell_status,n.status,n.created_at,i.name as item_name,i.image_url as item_image,p.nickname');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->whereLike('i.name', "%{$kw}%");
        }
        $sell = $this->int('sellStatus', -1);
        if ($sell >= 0) {
            $q->where('n.sell_status', $sell);
        }
        $q->order('n.id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r['id']        = (string)$r['id'];
            $r['nftId']     = (string)$r['nft_id'];
            $r['iid']       = (string)$r['iid'];
            $r['uid']       = (string)$r['uid'];
            $r['price']     = (float)$r['price'];
            $r['nickname']  = (string)($r['nickname'] ?: ($r['uid'] == 0 ? '未归属' : '用户' . substr((string)$r['uid'], -4)));
            $r['itemImage'] = (string)($r['item_image'] ?: '');
            unset($r['nft_id']);
        }
        unset($r);
        return $data;
    }
}
