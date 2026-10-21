<?php
declare(strict_types=1);

namespace app\controller\admin;

/**
 * 数据仪表盘 —— GET /admin/dashboard
 *
 * 指标全部来自现有表实时聚合，不做缓存（管理端低频访问）。
 */
class Dashboard extends AdminBase
{
    public function index()
    {
        $today = date('Y-m-d 00:00:00');

        $cards = [
            'users' => [
                'total' => \think\facade\Db::name('users')->where('is_deleted', 0)->count(),
                'today' => \think\facade\Db::name('users')->where('is_deleted', 0)->whereTime('created_at', '>=', $today)->count(),
            ],
            'items' => [
                'total' => \think\facade\Db::name('items')->where('is_deleted', 0)->count(),
                'onSale' => \think\facade\Db::name('items')->where('is_deleted', 0)->where('status', 1)->count(),
            ],
            'nfts' => [
                'total' => \think\facade\Db::name('nfts')->where('is_deleted', 0)->count(),
                'onSell' => \think\facade\Db::name('nfts')->where('is_deleted', 0)->where('sell_status', 1)->count(),
            ],
            'orders' => [
                'total'  => \think\facade\Db::name('orders')->where('is_deleted', 0)->count(),
                'amount' => (float)\think\facade\Db::name('orders')->where('is_deleted', 0)->sum('amount'),
                'today'  => \think\facade\Db::name('orders')->where('is_deleted', 0)->whereTime('created_at', '>=', $today)->count(),
            ],
            'market' => [
                'onSale'   => \think\facade\Db::name('nft_sell_orders')->where('is_deleted', 0)->where('status', 1)->count(),
                'turnover' => (float)\think\facade\Db::name('nft_sell_orders')->where('is_deleted', 0)->where('status', 2)->sum('price'),
            ],
        ];

        // 近 7 日订单趋势
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day    = date('Y-m-d', strtotime("-{$i} day"));
            $trend[] = [
                'day'    => date('m-d', strtotime($day)),
                'orders' => \think\facade\Db::name('orders')->whereBetweenTime('created_at', $day . ' 00:00:00', $day . ' 23:59:59')->count(),
                'amount' => (float)\think\facade\Db::name('orders')->whereBetweenTime('created_at', $day . ' 00:00:00', $day . ' 23:59:59')->sum('amount'),
            ];
        }

        // 最新订单（含用户与藏品摘要）
        $latestOrders = \think\facade\Db::name('orders')
            ->order('id', 'desc')
            ->limit(8)
            ->field('id,uid,iid,order_type,status,amount,title,created_at')
            ->select()->toArray();

        $uids = array_values(array_unique(array_column($latestOrders, 'uid')));
        $users = $uids === [] ? [] : \think\facade\Db::name('user_profiles')->whereIn('uid', $uids)->column('nickname', 'uid');
        foreach ($latestOrders as &$o) {
            $o['nickname'] = (string)($users[$o['uid']] ?? ('用户' . substr((string)$o['uid'], -4)));
            $o['amount']   = (float)$o['amount'];
        }
        unset($o);

        return ['cards' => $cards, 'trend' => $trend, 'latestOrders' => $latestOrders];
    }
}
