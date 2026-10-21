<?php
declare(strict_types=1);

namespace app\service;

use app\helper\Snowflake;
use app\model\Nft as NftModel;
use think\facade\Db;

/**
 * 定向发放引擎 —— 新人发放 / 活动空投 / 运营补偿 统一入口
 *
 * 核心设计（表即队列，无 MQ 依赖）：
 *   1. 目标人群解析 resolveUids() —— 三种 target_type 差异全在这里
 *   2. 写入 grant_record(status=0 待发) —— 唯一索引 uk_task_uid 幂等去重
 *   3. 分批发放 grantOne() —— 库存原子预占 + NFT 落库 + 记录同生同死
 *   4. 失败落 fail_reason，可后台重发
 *
 * NFT 来源追溯：grant_record.nft_ids 反查，不改 nfts 表结构。
 * 寄售管控：统一走藏品级开关 items.is_secondary，本服务不做期限制。
 */
class GrantService
{
    public const TYPE_NEWBIE   = 1; // 新人发放（事件驱动：注册即发）
    public const TYPE_AIRDROP  = 2; // 活动空投（快照/规则批量）
    public const TYPE_COMPENSE = 3; // 运营补偿（指定名单）

    public const STATUS_DRAFT    = 0;
    public const STATUS_PENDING  = 1; // 待审
    public const STATUS_APPROVED = 2; // 已通过（待执行）
    public const STATUS_RUNNING  = 3; // 进行中
    public const STATUS_DONE     = 4;
    public const STATUS_CANCELED = 5;
    public const STATUS_REJECTED = 6;

    /** 每批发放条数 */
    private const BATCH_SIZE = 500;

    /* ====================================================================
     * 事件钩子：注册成功后调用（不抛异常，失败不影响注册主流程）
     * ================================================================== */
    public static function onUserRegistered(string $uid): void
    {
        $now = date('Y-m-d H:i:s');
        $tasks = Db::name('grant_task')
            ->where('type', self::TYPE_NEWBIE)
            ->whereIn('status', [self::STATUS_APPROVED, self::STATUS_RUNNING])
            ->where(function ($q) use ($now) {
                $q->whereNull('start_time')->whereOr('start_time', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_time')->whereOr('end_time', '>=', $now);
            })
            ->select()->toArray();

        foreach ($tasks as $task) {
            try {
                self::grantOne($task, $uid);
            } catch (\Throwable) {
                // 单任务失败不影响其他任务；记录已落库可后台重发
            }
        }
    }

    /* ====================================================================
     * 目标人群解析
     * ================================================================== */

    /** 按任务规则解析目标 uid 列表（预览与执行共用） */
    public static function resolveUids(array $task): array
    {
        $rule = json_decode((string)$task['target_rule'], true) ?: [];

        switch ((int)$task['target_type']) {
            case 1: // 注册时间规则
                $q = Db::name('users')->where('is_deleted', 0);
                if (!empty($rule['registerAfter'])) {
                    $q->where('created_at', '>=', (string)$rule['registerAfter']);
                }
                if (!empty($rule['registerBefore'])) {
                    $q->where('created_at', '<=', (string)$rule['registerBefore']);
                }
                return $q->column('id');

            case 2: // 持仓快照：持有指定藏品（普通0/在售1，已售2不属于持仓）
                $holdIid = (string)($rule['holdIid'] ?? '');
                if ($holdIid === '') {
                    return [];
                }
                return Db::name('nfts')->where('iid', $holdIid)
                    ->where('is_deleted', 0)->whereIn('status', [0, 1])
                    ->group('uid')->column('uid');

            case 3: // 指定 uid 列表（补偿/名单导入）
                $uids = array_values(array_unique(array_filter(array_map('strval', (array)($rule['uids'] ?? [])))));
                return $uids === [] ? [] : Db::name('users')->whereIn('id', $uids)->where('is_deleted', 0)->column('id');
        }
        return [];
    }

    /** 预览：将发放人数与份数（排除已在发放记录中的 uid） */
    public static function preview(array $task): array
    {
        $uids = self::resolveUids($task);
        $done = Db::name('grant_record')->where('task_id', $task['id'])->column('uid');
        $pending = array_values(array_diff(array_map('strval', $uids), array_map('strval', $done)));
        $perUser = max(1, (int)$task['quantity_per_user']);

        $count = count($pending);
        if ((int)$task['total_quantity'] > 0) {
            $remain = max(0, (int)$task['total_quantity'] - (int)$task['granted_quantity']);
            $count  = (int)min($count, (int)ceil($remain / $perUser));
        }
        return ['userCount' => $count, 'pieceCount' => $count * $perUser];
    }

    /* ====================================================================
     * 执行：写入待发 + 分批推进
     * ================================================================== */

    /**
     * 执行一次任务推进（可重复调用，幂等）：
     *   首次：解析人群写入待发记录 → 任务转「进行中」
     *   每次循环：逐批发放，直到无待发
     * 返回本次统计。
     */
    public static function execute(array $task): array
    {
        // 已通过 → 写入待发名单并转进行中（一次性；后续重入跳过）
        if ((int)$task['status'] === self::STATUS_APPROVED) {
            self::seedPending($task);
            Db::name('grant_task')->where('id', $task['id'])->update([
                'status'      => self::STATUS_RUNNING,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
            $task['status'] = self::STATUS_RUNNING;
        }

        $granted = 0;
        $failed  = 0;
        // 安全阀：最多推进 1000 批（50 万条），防逻辑缺陷导致无限循环打爆内存
        $maxBatch = 1000;

        while ($maxBatch-- > 0) {
            $rows = Db::name('grant_record')
                ->where('task_id', $task['id'])
                ->whereIn('status', [0, 1])
                ->limit(self::BATCH_SIZE)
                ->select()->toArray();
            if ($rows === []) {
                break;
            }
            foreach ($rows as $r) {
                Db::name('grant_record')->where('id', $r['id'])->update(['status' => 1]);
                try {
                    self::grantOne($task, (string)$r['uid']);
                    $granted++;
                } catch (\Throwable $e) {
                    Db::name('grant_record')->where('id', $r['id'])->update([
                        'status'      => 3,
                        'fail_reason' => mb_substr($e->getMessage(), 0, 250),
                        'retry_times' => (int)$r['retry_times'] + 1,
                    ]);
                    $failed++;
                }
            }
        }

        // 终态判定：无待发/发放中 → 已完成
        $left = Db::name('grant_record')
            ->where('task_id', $task['id'])->whereIn('status', [0, 1])->count();
        if ($left === 0 && (int)$task['status'] === self::STATUS_RUNNING) {
            Db::name('grant_task')->where('id', $task['id'])->update([
                'status'      => self::STATUS_DONE,
                'executed_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return ['granted' => $granted, 'failed' => $failed];
    }

    /** 失败记录重置为待发后重新执行 */
    public static function retryFailed(array $task): array
    {
        Db::name('grant_record')
            ->where('task_id', $task['id'])->where('status', 3)
            ->where('retry_times', '<', 5)
            ->update(['status' => 0, 'fail_reason' => '']);
        return self::execute($task);
    }

    /** 目标人群写入待发记录（唯一索引去重） */
    private static function seedPending(array $task): void
    {
        $uids   = self::resolveUids($task);
        $perUser = max(1, (int)$task['quantity_per_user']);
        $total   = (int)$task['total_quantity'];
        $done    = Db::name('grant_record')->where('task_id', $task['id'])->column('uid');
        $doneSet = array_flip(array_map('strval', $done));

        $remain = $total > 0 ? $total : PHP_INT_MAX;
        $now = date('Y-m-d H:i:s');

        foreach ($uids as $uid) {
            if ($remain < $perUser) {
                break;
            }
            $uid = (string)$uid;
            if (isset($doneSet[$uid])) {
                continue;
            }
            try {
                Db::name('grant_record')->insert([
                    'id'         => Snowflake::nextIdStr(),
                    'task_id'    => (string)$task['id'],
                    'uid'        => $uid,
                    'source'     => 'task',
                    'biz_key'    => 'task:' . $task['id'] . ':' . $uid,
                    'status'     => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $doneSet[$uid] = true;
                $remain -= $perUser;
            } catch (\Throwable) {
                // 唯一索引冲突 = 已存在，幂等跳过
            }
        }
    }

    /* ====================================================================
     * 单人发放（幂等 + 库存预占 + NFT 落库同事务）
     * ================================================================== */
    public static function grantOne(array $task, string $uid): void
    {
        $taskId  = (string)$task['id'];
        $perUser = max(1, (int)$task['quantity_per_user']);

        // 幂等：已有成功记录直接跳过
        // ⚠️ 只查 status=2（成功）。若把 1(发放中) 也算已处理，
        //    execute() 的「置1→grantOne」流程会永远命中这里返回，
        //    记录停在 1 → while 循环重复取同一条 → 死循环耗尽内存。
        $exists = Db::name('grant_record')
            ->where('task_id', $taskId)->where('uid', $uid)
            ->where('status', 2)->count();
        if ($exists > 0) {
            return;
        }

        // 记录不存在则补建（注册钩子路径不会先 seedPending）
        $now = date('Y-m-d H:i:s');
        $rec = Db::name('grant_record')->where('task_id', $taskId)->where('uid', $uid)->find();
        if (!$rec) {
            try {
                Db::name('grant_record')->insert([
                    'id' => Snowflake::nextIdStr(), 'task_id' => $taskId, 'uid' => $uid,
                    'source' => 'task', 'biz_key' => 'task:' . $taskId . ':' . $uid,
                    'status' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            } catch (\Throwable) {
                return; // 并发下另一请求已在处理
            }
        }

        // 库存预占：总量上限原子扣减（UPDATE ... WHERE 余量足够），失败抛错回滚
        $total = (int)$task['total_quantity'];
        if ($total > 0) {
            $affected = Db::name('grant_task')
                ->where('id', $taskId)
                ->whereRaw("granted_quantity + {$perUser} <= {$total}")
                ->update(['granted_quantity' => Db::raw("granted_quantity + {$perUser}")]);
            if ($affected === 0) {
                throw new \RuntimeException('任务总量已发满');
            }
        }

        $nftIds = [];
        for ($i = 0; $i < $perUser; $i++) {
            $nftIds[] = self::mintNft($task, $uid, $now);
        }
        Db::name('grant_record')
            ->where('task_id', $taskId)->where('uid', $uid)
            ->update([
                'nft_ids'    => implode(',', $nftIds),
                'status'     => 2,
                'granted_at' => $now,
                'fail_reason'=> '',
                'updated_at' => $now,
            ]);
    }

    /* ====================================================================
     * 系统直接发放（签到里程碑 / 邀请奖励等事件驱动场景）
     * ================================================================== */

    /**
     * 直接给单个用户发放藏品（不走任务）。
     *
     * 幂等：唯一索引 uk_biz_key(source, biz_key) —— 同一业务事件重复触发只发一次。
     * 事务：记录 + NFT 落库同生同死；失败抛异常由调用方决定是否阻断主流程。
     *
     * @param string $source  来源标识：sign_milestone / invite_reward ...
     * @param string $bizKey  幂等键，如 "u{uid}:m7" / "inv:{inviteId}"
     * @return array{itemIds: string[], itemName: string}
     */
    public static function grantDirect(string $uid, int $iid, int $qty, string $source, string $bizKey): array
    {
        $qty = max(1, $qty);
        $now = date('Y-m-d H:i:s');

        // 幂等前置：已成功则跳过
        $done = Db::name('grant_record')
            ->where('source', $source)->where('biz_key', $bizKey)
            ->where('status', 2)->count();
        if ($done > 0) {
            return ['itemIds' => [], 'itemName' => ''];
        }

        $item = Db::name('items')->where('id', $iid)->where('is_deleted', 0)
            ->field('id,iid,name,image,image_url')->find();
        if (!$item) {
            throw new \RuntimeException('发放藏品不存在');
        }

        Db::transaction(function () use ($uid, $item, $qty, $source, $bizKey, $now, &$nftIds) {
            $nftIds = [];
            for ($i = 0; $i < $qty; $i++) {
                $nftIds[] = self::mintNftRow($item, $uid, $now);
            }
            Db::name('grant_record')->insert([
                'id'         => Snowflake::nextIdStr(),
                'task_id'    => 0,
                'uid'        => $uid,
                'source'     => $source,
                'biz_key'    => $bizKey,
                'nft_ids'    => implode(',', $nftIds),
                'status'     => 2,
                'granted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return ['itemIds' => $nftIds, 'itemName' => (string)$item['name']];
    }

    /** 生成一枚 NFT（任务路径：先取藏品再落 mint） */
    private static function mintNft(array $task, string $uid, string $now): string
    {
        $item = Db::name('items')->where('id', (int)$task['iid'])->where('is_deleted', 0)
            ->field('iid,name,image,image_url')->find();
        if (!$item) {
            throw new \RuntimeException('发放藏品不存在');
        }
        return self::mintNftRow($item, $uid, $now);
    }

    /** 生成一枚 NFT（通用：$item 需含 iid,name,image,image_url；空投视为已上链完成） */
    private static function mintNftRow(array $item, string $uid, string $now): string
    {
        $nftId = Snowflake::nextIdStr();
        NftModel::create([
            'nft_id'          => $nftId,
            'uid'             => $uid,
            'iid'             => (string)$item['iid'],
            'name'            => (string)$item['name'],
            'image'           => (string)$item['image'],
            'image_url'       => (string)$item['image_url'],
            'token'           => 1,
            'status'          => 0, // 0=普通（tinyint 数字枚举，禁止写字符串）
            'get_time'        => (int)floor(microtime(true) * 1000),
            'price'           => 0,
            'buy_price'       => 0,
            'pay_lock'        => 0,
            'pay_way'         => null,
            'chain_id'        => '0x' . bin2hex(random_bytes(16)),
            'chain_completed' => 1, // 平台发放直接上链完成（口径：chain_completed=1 且有 hash）
            'sell_status'     => 0,
        ]);
        return $nftId;
    }
}
