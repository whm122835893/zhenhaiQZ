<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\exception\ApiException;
use app\helper\Snowflake;

/**
 * 活动管理 —— 抽奖 / 合成配方 / 邀请
 *
 * - 抽奖：activity_lotteries + activity_lottery_prizes（概率基点总和 ≤ 10000）
 * - 合成：nft_merge_recipes + nft_merge_needs + nft_merge_produces，
 *   时间字段为毫秒 bigint；needs/produces 的 name/image 由 items 表按 iid 自动带出
 * - 邀请：user_invites 只读列表 + 统计；奖励参数走 system_configs（invite.*），
 *   发放在注册链路（Passport）完成
 */
class Activity extends AdminBase
{
    /* ==================== 抽奖 ==================== */

    public function lotteries()
    {
        $q = \think\facade\Db::name('activity_lotteries')->where('is_deleted', 0);
        $data = $this->adminPage($q->order('id', 'desc'));

        foreach ($data['list'] as &$r) {
            $r['id'] = (string)$r['id'];
            $r['prizes'] = $this->prizesOf((string)$r['id']);
            $r['drawCount'] = \think\facade\Db::name('activity_lottery_logs')
                ->where('lottery_id', (string)$r['id'])->count();
        }
        unset($r);
        return $data;
    }

    /** POST /admin/activity/lotteries | PUT :id —— prizes 整体重建 */
    public function lotterySave()
    {
        $this->needWriter();
        $id   = (string)$this->int('id', 0);
        $body = $this->body();

        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') {
            throw ApiException::missing('name');
        }

        $prizes = is_array($body['prizes'] ?? null) ? $body['prizes'] : [];
        $sum = 0.0;
        foreach ($prizes as $p) {
            $sum += (float)($p['probability'] ?? 0);
        }
        // probability 列 decimal(6,2)，语义为百分比（43.00 = 43%）
        if ($sum > 100) {
            throw new ApiException('奖品概率总和不能超过 100%', ApiException::CODE_PARAM_TYPE);
        }

        $fields = [
            'name'       => $name,
            'cover'      => (string)($body['cover_url'] ?? ''),
            'cover_url'  => (string)($body['cover_url'] ?? ''),
            'points'     => (int)($body['points'] ?? 0),
            'start_time' => $this->nullIfEmpty($body['start_time'] ?? ''),
            'end_time'   => $this->nullIfEmpty($body['end_time'] ?? ''),
            'rule'       => (string)($body['rule'] ?? ''),
            'status'     => !empty($body['status']) ? 'active' : 'paused',
        ];

        if ($id !== '0') {
            $old = \think\facade\Db::name('activity_lotteries')->where('id', $id)->where('is_deleted', 0)->find();
            if (!$old) {
                throw ApiException::notFound('抽奖活动不存在');
            }
            \think\facade\Db::name('activity_lotteries')->where('id', $id)->update($fields);
            $this->opLog('activity', 'lottery_update', $old, $fields, 'lottery:' . $id);
        } else {
            $id = Snowflake::nextIdStr();
            \think\facade\Db::name('activity_lotteries')->insert(['id' => $id] + $fields);
            $this->opLog('activity', 'lottery_create', null, $fields, 'lottery:' . $id);
        }

        // 奖品整体重建（管理端低频操作，不做 diff）
        \think\facade\Db::name('activity_lottery_prizes')->where('lottery_id', $id)->delete();
        foreach ($prizes as $p) {
            $img = trim((string)($p['image_url'] ?? ''));
            \think\facade\Db::name('activity_lottery_prizes')->insert([
                'id'         => Snowflake::nextIdStr(),
                'lottery_id' => $id,
                'name'       => (string)($p['name'] ?? ''),
                'image'      => $img,
                'image_url'  => $img,
                'level'      => (string)($p['level'] ?? 'common'),
                'probability'=> (float)($p['probability'] ?? 0),
                'quantity'   => (int)($p['quantity'] ?? 0),
                'sort'       => (int)($p['sort'] ?? 0),
            ]);
        }

        return ['id' => $id];
    }

    public function lotteryDelete()
    {
        $this->needWriter();
        $id = (string)$this->needInt('id');
        $old = \think\facade\Db::name('activity_lotteries')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$old) {
            throw ApiException::notFound('抽奖活动不存在');
        }
        \think\facade\Db::name('activity_lotteries')->where('id', $id)->update(['is_deleted' => 1]);
        $this->opLog('activity', 'lottery_delete', $old, ['is_deleted' => 1], 'lottery:' . $id);
        return ['ok' => true];
    }

    private function prizesOf(string $lotteryId): array
    {
        $rows = \think\facade\Db::name('activity_lottery_prizes')
            ->where('lottery_id', $lotteryId)
            ->order('sort', 'asc')->order('id', 'asc')
            ->field('id,name,image,image_url,level,probability,quantity,sort')
            ->select()->toArray();
        foreach ($rows as &$p) {
            $p['id'] = (string)$p['id'];
        }
        unset($p);
        return $rows;
    }

    /* ==================== 合成配方 ==================== */

    public function merges()
    {
        $q = \think\facade\Db::name('nft_merge_recipes')->where('is_deleted', 0);
        $data = $this->adminPage($q->order('id', 'desc'));

        foreach ($data['list'] as &$r) {
            $r['id'] = (string)$r['id'];
            $r['needs'] = $this->mergeSubs('nft_merge_needs', (string)$r['id']);
            $r['produces'] = $this->mergeSubs('nft_merge_produces', (string)$r['id']);
        }
        unset($r);
        return $data;
    }

    /** POST /admin/activity/merges | PUT :id —— needs/produces 整体重建 */
    public function mergeSave()
    {
        $this->needWriter();
        $id   = (string)$this->int('id', 0);
        $body = $this->body();

        $name = trim((string)($body['name'] ?? ''));
        if ($name === '') {
            throw ApiException::missing('name');
        }

        $needs    = is_array($body['needs'] ?? null) ? $body['needs'] : [];
        $produces = is_array($body['produces'] ?? null) ? $body['produces'] : [];
        if ($needs === [] || $produces === []) {
            throw ApiException::missing('needs/produces');
        }

        $fields = [
            'name'           => $name,
            'cover'          => (string)($body['cover_url'] ?? ''),
            'cover_url'      => (string)($body['cover_url'] ?? ''),
            'content'        => (string)($body['content'] ?? ''),
            'type'           => (int)($body['type'] ?? 1),
            'merge_quantity' => max(1, (int)($body['merge_quantity'] ?? 1)),
            'pre_start_time' => $this->toMs($body['pre_start_time'] ?? 0),
            'pre_end_time'   => $this->toMs($body['pre_end_time'] ?? 0),
            'start_time'     => $this->toMs($body['start_time'] ?? 0),
            'end_time'       => $this->toMs($body['end_time'] ?? 0),
            'merge_time'     => $this->toMs($body['merge_time'] ?? 0),
            'is_pre'         => !empty($body['is_pre']) ? 1 : 0,
            'status'         => !empty($body['status']) ? 1 : 0,
        ];

        if ($id !== '0') {
            $old = \think\facade\Db::name('nft_merge_recipes')->where('id', $id)->where('is_deleted', 0)->find();
            if (!$old) {
                throw ApiException::notFound('合成配方不存在');
            }
            \think\facade\Db::name('nft_merge_recipes')->where('id', $id)->update($fields);
            $this->opLog('activity', 'merge_update', $old, $fields, 'merge:' . $id);
        } else {
            $id = Snowflake::nextIdStr();
            \think\facade\Db::name('nft_merge_recipes')->insert(['id' => $id] + $fields);
            $this->opLog('activity', 'merge_create', null, $fields, 'merge:' . $id);
        }

        \think\facade\Db::name('nft_merge_needs')->where('merge_id', $id)->delete();
        \think\facade\Db::name('nft_merge_produces')->where('merge_id', $id)->delete();
        foreach ($needs as $n) {
            $this->insertMergeSub('nft_merge_needs', $id, $n);
        }
        foreach ($produces as $p) {
            $this->insertMergeSub('nft_merge_produces', $id, $p);
        }

        return ['id' => $id];
    }

    public function mergeDelete()
    {
        $this->needWriter();
        $id = (string)$this->needInt('id');
        $old = \think\facade\Db::name('nft_merge_recipes')->where('id', $id)->where('is_deleted', 0)->find();
        if (!$old) {
            throw ApiException::notFound('合成配方不存在');
        }
        \think\facade\Db::name('nft_merge_recipes')->where('id', $id)->update(['is_deleted' => 1]);
        $this->opLog('activity', 'merge_delete', $old, ['is_deleted' => 1], 'merge:' . $id);
        return ['ok' => true];
    }

    private function mergeSubs(string $table, string $mergeId): array
    {
        $rows = \think\facade\Db::name($table)
            ->where('merge_id', $mergeId)
            ->order('id', 'asc')
            ->field('id,iid,quantity,name,image,image_url')
            ->select()->toArray();
        foreach ($rows as &$r) {
            $r['id'] = (string)$r['id'];
            $r['iid'] = (string)$r['iid'];
        }
        unset($r);
        return $rows;
    }

    /** 合成子项：iid 必须是真实藏品，name/image 自动带出（防脏数据） */
    private function insertMergeSub(string $table, string $mergeId, array $sub): void
    {
        $iid = (string)($sub['iid'] ?? '');
        if ($iid === '') {
            return;
        }
        $item = \think\facade\Db::name('items')->where('iid', $iid)->where('is_deleted', 0)
            ->field('name,image,image_url')->find();
        if (!$item) {
            throw new ApiException("藏品 {$iid} 不存在，无法配置配方", ApiException::CODE_PARAM_TYPE);
        }

        \think\facade\Db::name($table)->insert([
            'id'        => Snowflake::nextIdStr(),
            'merge_id'  => $mergeId,
            'iid'       => $iid,
            'quantity'  => max(1, (int)($sub['quantity'] ?? 1)),
            'name'      => (string)$item['name'],
            'image'     => (string)($item['image_url'] ?: $item['image']),
            'image_url' => (string)$item['image_url'],
        ]);
    }

    /* ==================== 邀请 ==================== */

    public function invites()
    {
        $q = \think\facade\Db::name('user_invites')->alias('i')
            ->leftJoin('user_profiles pp', 'pp.uid = i.uid')
            ->leftJoin('user_profiles pe', 'pe.uid = i.invitee_uid')
            ->field('i.id,i.uid,i.invitee_uid,i.invite_code,i.reward_status,i.rewarded_at,i.created_at,pp.nickname as inviter_name,pe.nickname as invitee_name');

        $kw = $this->str('keyword');
        if ($kw !== '') {
            $q->where(function ($w) use ($kw) {
                $w->whereLike('pp.nickname', "%{$kw}%")->whereOr('pe.nickname', 'like', "%{$kw}%");
            });
        }
        $q->order('i.id', 'desc');

        $data = $this->adminPage($q);
        foreach ($data['list'] as &$r) {
            $r['id']         = (string)$r['id'];
            $r['uid']        = (string)$r['uid'];
            $r['inviteeUid'] = (string)$r['invitee_uid'];
            $r['rewardedAt'] = (int)$r['rewarded_at'] > 0 ? date('Y-m-d H:i', intdiv((int)$r['rewarded_at'], 1000)) : '';
            unset($r['rewarded_at'], $r['invitee_uid']);
        }
        unset($r);

        $data['stats'] = [
            'total'        => \think\facade\Db::name('user_invites')->count(),
            'granted'      => \think\facade\Db::name('user_invites')->where('reward_status', 'granted')->count(),
            'pending'      => \think\facade\Db::name('user_invites')->where('reward_status', 'pending')->count(),
            'configEnabled'=> \app\service\SystemConfigService::int('invite.enabled', 0) === 1,
            'configItem'   => $this->inviteRewardItemName(),
            'configQty'    => \app\service\SystemConfigService::int('invite.reward_qty', 1),
        ];
        return $data;
    }

    /** 当前配置的邀请奖励藏品名（未配置返回空串） */
    private function inviteRewardItemName(): string
    {
        $iid = \app\service\SystemConfigService::int('invite.reward_iid', 0);
        if ($iid <= 0) {
            return '';
        }
        return (string)\think\facade\Db::name('items')->where('id', $iid)->value('name');
    }

    /* ---------- 内部 ---------- */

    private function toMs(mixed $v): int
    {
        $s = trim((string)$v);
        if ($s === '') {
            return 0;
        }
        if (ctype_digit($s)) {
            return (int)$s; // 已是毫秒
        }
        $ts = strtotime($s);
        return $ts === false ? 0 : $ts * 1000;
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string)$v);
        return $s === '' ? null : $s;
    }

    /** GET /admin/activity/reward-config —— 签到与邀请奖励配置 */
    public function rewardConfig()
    {
        $signCsv = \app\service\SystemConfigService::get('activity.sign_rewards', '1,2,3,5,7');
        $msCsv = \app\service\SystemConfigService::get('activity.sign_milestones', '');
        return [
            'signRewards' => array_values(array_filter(array_map('intval', explode(',', $signCsv)))),
            'signMilestones' => $this->parseMilestones($msCsv),
            'invite'      => [
                'enabled'      => \app\service\SystemConfigService::get('invite.enabled', '1') === '1',
                'rewardIid'    => (string)\app\service\SystemConfigService::get('invite.reward_iid', ''),
                'rewardQty'    => \app\service\SystemConfigService::int('invite.reward_qty', 1),
                'settleTiming' => \app\service\InviteService::timing(),
            ],
        ];
    }

    /**
     * PUT /admin/activity/reward-config
     * {
     *   signRewards:[1,2,3,5,7],
     *   signMilestones:[{day:3,iid:'12',qty:1}],
     *   invite:{enabled:true, rewardIid:'12', rewardQty:1, settleTiming:'register'|'first_order'}
     * }
     * system_configs 有 uk_config_key 唯一键，直接 upsert。
     */
    public function rewardConfigSave()
    {
        $this->needWriter();
        $body = $this->body();

        $items = [];
        $sign = (array)($body['signRewards'] ?? []);
        $sign = array_values(array_filter(array_map('intval', $sign), static fn ($v) => $v > 0));
        if ($sign === []) {
            throw new ApiException('签到奖励档位不能为空', ApiException::CODE_PARAM_TYPE);
        }
        $items['activity.sign_rewards'] = [implode(',', $sign), '签到奖励档位 CSV（第1..N天）'];

        // 签到里程碑空投：[{day,iid,qty}] → CSV「天:items.id:数量」
        $ms = [];
        foreach ((array)($body['signMilestones'] ?? []) as $m) {
            $day = (int)($m['day'] ?? 0);
            $iid = (int)($m['iid'] ?? 0);
            $qty = (int)($m['qty'] ?? 1);
            if ($day <= 0 && $iid <= 0) {
                continue; // 空行跳过
            }
            if ($day < 1 || $day > 366 || $iid <= 0 || $qty < 1 || $qty > 100) {
                throw new ApiException('签到里程碑配置不合法（天数/藏品/数量）', ApiException::CODE_PARAM_TYPE);
            }
            $item = \think\facade\Db::name('items')->where('id', $iid)->where('is_deleted', 0)->count();
            if ($item === 0) {
                throw new ApiException('签到里程碑藏品不存在（ID:' . $iid . '）', ApiException::CODE_PARAM_TYPE);
            }
            $ms[] = $day . ':' . $iid . ':' . $qty;
        }
        $items['activity.sign_milestones'] = [implode(',', $ms), '签到里程碑空投 CSV（连续天:items.id:数量）'];

        $inv = (array)($body['invite'] ?? []);
        $items['invite.enabled'] = [!empty($inv['enabled']) ? '1' : '0', '邀请好友奖励开关（1 开启 0 关闭）'];

        $iid = (int)($inv['rewardIid'] ?? 0);
        if (!empty($inv['enabled'])) {
            if ($iid <= 0) {
                throw new ApiException('邀请奖励开启时必须选择奖励藏品', ApiException::CODE_PARAM_TYPE);
            }
            if (\think\facade\Db::name('items')->where('id', $iid)->where('is_deleted', 0)->count() === 0) {
                throw new ApiException('邀请奖励藏品不存在', ApiException::CODE_PARAM_TYPE);
            }
        }
        $items['invite.reward_iid'] = [(string)$iid, '邀请好友奖励藏品ID（items.id）'];

        $qty = max(1, (int)($inv['rewardQty'] ?? 1));
        if ($qty > 100) {
            throw new ApiException('邀请奖励藏品数量上限 100', ApiException::CODE_PARAM_TYPE);
        }
        $items['invite.reward_qty'] = [(string)$qty, '邀请好友奖励藏品数量'];

        $timing = (string)($inv['settleTiming'] ?? 'register');
        if (!in_array($timing, [\app\service\InviteService::TIMING_REGISTER, \app\service\InviteService::TIMING_FIRST_ORDER], true)) {
            throw new ApiException('邀请奖励结算时机不合法', ApiException::CODE_PARAM_TYPE);
        }
        $items['invite.settle_timing'] = [$timing, '邀请奖励结算时机（register=注册即发 first_order=首单支付后）'];

        $changed = [];
        foreach ($items as $key => [$value, $remark]) {
            \think\facade\Db::name('system_configs')->duplicate([
                'config_value' => $value,
                'remark'       => $remark,
            ])->insert([
                'config_key' => $key,
                'config_value' => $value,
                'remark'     => $remark,
            ]);
            $changed[] = ['key' => $key, 'to' => $value];
        }

        $this->opLog('activity', 'reward_config_update', null, $changed);
        return $this->rewardConfig();
    }

    /** 里程碑 CSV「天:items.id:数量」→ [{day,iid,qty}] */
    private function parseMilestones(string $csv): array
    {
        $out = [];
        foreach (explode(',', $csv) as $seg) {
            $p = explode(':', trim($seg));
            if (count($p) !== 3) {
                continue;
            }
            $day = (int)$p[0];
            $iid = (int)$p[1];
            $qty = (int)$p[2];
            if ($day > 0 && $iid > 0 && $qty > 0) {
                $out[] = ['day' => $day, 'iid' => (string)$iid, 'qty' => $qty];
            }
        }
        return $out;
    }
}
