<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use think\facade\Db;

/**
 * 数据清理 —— 用户注销数据清除 与 平台业务数据清空（仅超级管理员）
 *
 * 设计要点：
 * - 全库无外键约束，级联清理由本控制器在事务内按归属列硬删（DELETE，非软删）
 * - 用户清除：删 users 及其全部归属数据（资产/订单/市场/流水/活动/会话），
 *   令牌校验依赖 users 行存在与 user_online_sessions，硬删后登录态即刻失效，需重新注册
 * - 平台清空：按组清业务数据（公告/藏品/交易资产/活动记录），不影响分类、作者、
 *   banner、系统配置、管理员账号等基础配置
 * - 批量删除按 500 一批拆分，避免超长 IN 列表
 */
class Cleanup extends AdminBase
{
    /** 用户归属表 => 归属列（users 用主键 id） */
    private const USER_TABLES = [
        'users'                    => 'id',
        'user_profiles'            => 'uid',
        'user_wallets'             => 'uid',
        'user_invites'             => 'uid',
        'user_login_logs'          => 'uid',
        'user_online_sessions'     => 'uid',
        'item_follows'             => 'uid',
        'nfts'                     => 'uid',
        'nft_merge_records'        => 'uid',
        'nft_box_open_logs'        => 'uid',
        'orders'                   => 'uid',
        'order_payments'           => 'uid',
        'nft_sell_orders'          => 'uid',
        'nft_market_apply_orders'  => 'uid',
        'nft_market_trust_orders'  => 'uid',
        'nft_market_follows'       => 'uid',
        'wallet_transactions'      => 'uid',
        'activity_sign_records'    => 'uid',
        'activity_lottery_logs'    => 'uid',
        'activity_points_logs'     => 'uid',
        'activity_recast_logs'     => 'uid',
        'product_merge_records'    => 'uid',
        'ai_answers'               => 'uid',
        'im_group_members'         => 'uid',
        'im_user_sessions'         => 'uid',
        'grant_record'             => 'uid',
        'priority_sale_whitelists' => 'uid',
        'airdrop_snapshots'        => 'uid',
        'airdrop_eligibilities'    => 'uid',
        'airdrop_records'          => 'uid',
    ];

    /** 平台业务数据分组 => 表清单（硬删全表数据） */
    private const SCOPES = [
        'notices'    => ['label' => '公告', 'tables' => ['notices', 'notice_item_relations']],
        'items'      => ['label' => '藏品', 'tables' => ['items', 'item_follows', 'notice_item_relations', 'grant_task', 'grant_record', 'priority_sales', 'priority_sale_whitelists', 'airdrop_activities', 'airdrop_snapshots', 'airdrop_eligibilities', 'airdrop_records']],
        'trades'     => ['label' => '交易与资产', 'tables' => ['nfts', 'nft_merge_records', 'nft_box_open_logs', 'orders', 'order_items', 'order_payments', 'nft_sell_orders', 'nft_market_apply_orders', 'nft_market_trust_orders', 'nft_market_follows', 'nft_trade_logs']],
        'activities' => ['label' => '活动记录', 'tables' => ['activity_sign_records', 'activity_lottery_logs', 'activity_points_logs', 'activity_recast_logs', 'product_merge_records']],
    ];

    /** GET /admin/cleanup/summary —— 各组数据量概览 */
    public function summary()
    {
        return [
            'users'     => Db::name('users')->where('is_deleted', 0)->count(),
            'notices'   => Db::name('notices')->count(),
            'items'     => Db::name('items')->where('is_deleted', 0)->count(),
            'nfts'      => Db::name('nfts')->count(),
            'orders'    => Db::name('orders')->count(),
            'sellOrders' => Db::name('nft_sell_orders')->count(),
            'grantTasks' => Db::name('grant_task')->count(),
            'activityLogs' => Db::name('activity_sign_records')->count()
                + Db::name('activity_lottery_logs')->count()
                + Db::name('activity_points_logs')->count()
                + Db::name('activity_recast_logs')->count(),
            'noticesCount' => Db::name('notice_item_relations')->count(),
        ];
    }

    /** POST /admin/cleanup/users —— {user_ids: [], all: false} 删除指定/全体用户 */
    public function users()
    {
        $this->needSuper();
        $body = $this->body();
        $all  = !empty($body['all']);

        if ($all) {
            $uids = Db::name('users')->column('id');
        } else {
            $ids  = $body['user_ids'] ?? [];
            if (!is_array($ids) || !$ids) {
                throw ApiException::missing('user_ids');
            }
            $uids = array_values(array_filter(array_map('strval', $ids)));
            if (!$uids) {
                throw ApiException::missing('user_ids');
            }
        }

        $deleted = 0;
        foreach (array_chunk($uids, 500) as $chunk) {
            $deleted += $this->purgeUsers($chunk);
        }

        $this->opLog('cleanup', 'users', null, [
            'all'     => $all,
            'count'   => $deleted,
            'preview' => array_slice($uids, 0, 10),
        ], 'users:' . ($all ? 'ALL' : count($uids)));
        return ['deleted' => $deleted];
    }

    /** POST /admin/cleanup/platform —— {scopes: ['notices','items','trades','activities'] 或 'all'} */
    public function platform()
    {
        $this->needSuper();
        $body   = $this->body();
        $scopes = $body['scopes'] ?? [];
        if ($scopes === 'all') {
            $scopes = array_keys(self::SCOPES);
        }
        if (!is_array($scopes) || !$scopes) {
            throw ApiException::missing('scopes');
        }
        $scopes = array_values(array_intersect($scopes, array_keys(self::SCOPES)));
        if (!$scopes) {
            throw new ApiException('无效的清理范围', ApiException::CODE_PARAM_TYPE);
        }

        $tables = [];
        foreach ($scopes as $s) {
            foreach (self::SCOPES[$s]['tables'] as $t) {
                $tables[$t] = true;
            }
        }

        $purged = [];
        Db::transaction(function () use ($tables, &$purged) {
            foreach (array_keys($tables) as $table) {
                $count = Db::name($table)->count();
                if ($count > 0) {
                    Db::name($table)->delete(true);   // 硬删全表（truncate 语义，走 delete 以兼容连杠表名）
                }
                $purged[$table] = (int)$count;
            }
        });

        $this->opLog('cleanup', 'platform', null, ['scopes' => $scopes, 'purged' => $purged], 'platform:' . implode(',', $scopes));
        return ['scopes' => $scopes, 'purged' => $purged];
    }

    /** 事务内清除一批用户及其全部归属数据 */
    private function purgeUsers(array $uids): int
    {
        return Db::transaction(function () use ($uids) {
            // 订单子表（无 uid，按 order_id 关联）
            $orderIds = Db::name('orders')->whereIn('uid', $uids)->column('id');
            if ($orderIds) {
                Db::name('order_items')->whereIn('order_id', $orderIds)->delete();
            }
            // 成交记录（无 uid，按 nft_id 关联）
            $nftIds = Db::name('nfts')->whereIn('uid', $uids)->column('id');
            if ($nftIds) {
                Db::name('nft_trade_logs')->whereIn('nft_id', $nftIds)->delete();
            }
            // 主归属表
            foreach (self::USER_TABLES as $table => $col) {
                Db::name($table)->whereIn($col, $uids)->delete();
            }
            // 多角色列（被邀请人 / 买家 / 群聊发送者）
            Db::name('user_invites')->whereIn('invitee_uid', $uids)->delete();
            Db::name('nft_sell_orders')->whereIn('buyer_uid', $uids)->delete();
            Db::name('im_group_messages')->whereIn('uid', $uids)->delete();

            return count($uids);
        });
    }
}
