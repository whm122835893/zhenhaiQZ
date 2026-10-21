<?php
declare(strict_types=1);

namespace app\controller;

use think\facade\Db;

/**
 * 定向发放（C 端只读）
 *
 * GET /grant/my —— 我的发放到账记录
 *
 * ⚠️ 源站没有该端点（本次新增能力，不涉及源站对齐）。
 *    响应沿用源站列表惯例：裸数组。
 */
class Grant extends Base
{
    public function my()
    {
        $uid = $this->uid();

        // leftJoin：任务发放关联 grant_task；事件直发（签到/邀请，task_id=0）按 source 展示
        $rows = Db::name('grant_record')->alias('r')
            ->leftJoin('grant_task t', 't.id = r.task_id')
            ->leftJoin('items i', 'i.id = t.iid')
            ->where('r.uid', (int)$uid)
            ->where('r.status', 2) // 只展示到账（成功）记录
            ->field('r.id, r.task_id, r.source, r.nft_ids, r.granted_at, t.name AS task_name, t.type, i.iid, i.name AS item_name, i.image, i.image_url')
            ->order('r.id', 'desc')
            ->limit(100)
            ->select()->toArray();

        $sourceNames = [
            'sign_milestone' => '签到里程碑空投',
            'invite_reward'  => '邀请好友奖励',
            'newbie'         => '新人注册奖励',
        ];

        $out = [];
        foreach ($rows as $r) {
            $nftIds = array_values(array_filter(explode(',', (string)$r['nft_ids'])));
            $typeName = (int)$r['task_id'] > 0
                ? ([1 => '新人发放', 2 => '活动空投', 3 => '运营补偿'][(int)$r['type']] ?? '平台发放')
                : ($sourceNames[(string)$r['source']] ?? '平台发放');
            // 口径对齐 Transformer：items.image_url 已是绝对地址
            $imageUrl = (string)($r['image_url'] ?: $r['image']);
            $out[] = [
                'id'         => (string)$r['id'],
                'taskId'     => (string)$r['task_id'],
                'taskName'   => (string)($r['task_name'] ?: ($typeName)),
                'typeName'   => $typeName,
                'iid'        => (string)($r['iid'] ?? '0'),
                'itemName'   => (string)($r['item_name'] ?? ''),
                'imageUrl'   => $imageUrl,
                'quantity'   => count($nftIds),
                'nftIds'     => $nftIds,
                'grantedAt'  => (string)($r['granted_at'] ?? ''),
            ];
        }
        return $out;
    }
}
