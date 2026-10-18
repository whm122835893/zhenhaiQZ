<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use app\model\ActivityLottery;
use app\model\ActivityLotteryLog;
use app\model\ActivityLotteryPrize;
use app\model\ActivityPointsLog;
use app\model\ActivityRecastLog;
use app\model\ActivityRecastPrize;
use app\model\ActivitySignRecord;
use app\model\Nft as NftModel;
use app\model\ProductMergeRecord;
use app\model\UserWallet;
use app\service\Transformer;

/**
 * 活动控制器
 *
 * 对应源站 activity / product_merge 分包。
 *
 * ⚠️ 源站实测（活动域整域验证结论）：
 *  - 抽奖 GET /activity/lottery/active 返回 data: null
 *  - 抽奖列表/记录返回 []
 *  - 重铸 GET /activity/recast/type 返回 10203（源站未上线）
 *  - product_merge 返回 10203
 *  - 积分余额返回 {balance: 0}
 *  - 签到 records 是 **31 位布尔数组**，第 18 位为 true（已签到标记）
 *
 * 源站返回空/null 的端点，本地保持同样的空响应，
 * 不臆造源站没有的数据，避免前端写出源站不存在的分支。
 */
class Activity extends Base
{
    /* ====================================================================
     * 只读端点
     * ================================================================== */

    /**
     * GET /activity/index —— 活动首页聚合
     */
    public function index()
    {
        $uid = $this->request->auth_uid ?? null;

        $data = [];

        // 当前进行中的抽奖
        $now = $this->nowMillis();
        $activeLottery = ActivityLottery::where('status', 'active')
            ->where('is_deleted', 0)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->order('id', 'desc')
            ->find();

        $data['activeLottery'] = $activeLottery
            ? $this->lotteryRow($activeLottery->toArray())
            : null;

        // 签到状态（未登录也给，源站游客也能看签到日历）
        $data['sign'] = $this->signStatusData($uid ? (string)$uid : '');

        // 积分余额
        $data['points'] = ['balance' => $uid ? $this->pointsBalanceOf((string)$uid) : 0];

        return $data;
    }

    /**
     * GET /activity/lottery/active —— 当前抽奖
     *
     * ⚠️ 源站实测返回 data: null。这里在无进行中活动时同样返回 null。
     */
    public function lotteryActive()
    {
        // ⚠️ 源站实测：本端点**只按 status 过滤，不检查时间窗口**。
        //    证据：源站当前活动的 endTime 已是过去时间（2026-10-18 19:30），
        //    接口仍返回该活动。故这里也不要加 start_time/end_time 条件，
        //    否则本地会返回 null 而与源站不一致。
        $row = ActivityLottery::where('status', 'active')
            ->where('is_deleted', 0)
            ->order('id', 'desc')
            ->find();

        if (!$row) {
            // ⚠️ 不能 `return null` —— ThinkPHP 会把控制器的 null 返回值
            //    序列化成空字符串，响应变成 data:"" 而不是 data:null。
            //    直接返回 json() 才能拿到真正的 JSON null。
            return json([
                'status'  => true,
                'code'    => \app\exception\ApiException::CODE_SUCCESS,
                'message' => null,
                'data'    => null,
            ]);
        }

        return $this->lotteryRow($row->toArray());
    }

    /**
     * GET /activity/lottery —— 抽奖列表
     */
    public function lotteryList()
    {
        $rows = ActivityLottery::where('status', 'active')
            ->where('is_deleted', 0)
            ->order('id', 'desc')
            ->select()
            ->toArray();

        return array_map(fn ($r) => $this->lotteryRow($r), $rows);
    }

    /**
     * GET /activity/lottery/{id} —— 抽奖详情（含奖品）
     */
    public function lotteryDetail()
    {
        $id = (string)($this->request->param('id') ?? '');

        if ($id === '') {
            throw ApiException::missing('id');
        }

        $row = ActivityLottery::where('id', $id)->where('is_deleted', 0)->find();
        if (!$row) {
            throw ApiException::notFound();
        }

        // prizes 已由 lotteryRow() 组装（源站 active 与 detail 都带奖品列表）
        return $this->lotteryRow($row->toArray());
    }

    /**
     * GET /activity/lottery/log —— 我的抽奖记录
     */
    public function lotteryLog()
    {
        $uid = $this->uid();

        $rows = ActivityLotteryLog::where('uid', $uid)
            ->order('id', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        return array_map(static fn ($r) => [
            'id'            => Transformer::id($r['id']),
            'lotteryId'     => Transformer::id($r['lottery_id']),
            'prizeId'       => Transformer::id($r['prize_id']),
            'isWin'         => (bool)$r['is_win'],
            'consumePoints' => (int)$r['consume_points'],
            'angle'         => (int)$r['angle'],
            'createTime'    => Transformer::datetimeTs($r['created_at']),
        ], $rows);
    }

    /**
     * GET /activity/recast/type —— 重铸类型
     *
     * ⚠️ 源站实测恒报 10203（未上线）。保持同样行为。
     */
    public function recastType()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /activity/recast/doll-type —— 娃娃类型
     */
    public function recastDollType()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /activity/recast/draw-num —— 剩余抽取次数
     */
    public function recastDrawNum()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /activity/recast/prize —— 重铸奖品
     */
    public function recastPrize()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /activity/recast/log —— 重铸记录
     */
    public function recastLog()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /product_merge —— 合成活动列表
     *
     * ⚠️ 源站实测恒报 10203。
     */
    public function mergeActivityList()
    {
        throw ApiException::notFound();
    }

    /**
     * GET /product_merge/record —— 合成记录
     */
    public function mergeRecord()
    {
        $uid = $this->uid();

        $rows = ProductMergeRecord::where('uid', $uid)
            ->order('id', 'desc')
            ->limit(100)
            ->select()
            ->toArray();

        return array_map(static fn ($r) => [
            'id'         => Transformer::id($r['id']),
            'mid'        => Transformer::id($r['mid']),
            'status'     => (int)$r['status'],
            'createTime' => Transformer::datetimeTs($r['created_at']),
        ], $rows);
    }

    /* ====================================================================
     * 签到与积分
     * ================================================================== */

    /**
     * GET /activity/sign —— 签到状态
     *
     * ⚠️ 源站返回的 records 是 **31 位布尔数组**，
     * 第 i 位代表本月第 i 天是否签到（1-based，即索引 0 是 1 号）。
     */
    public function signStatus()
    {
        $uid = $this->uid();

        return $this->signStatusData($uid);
    }

    /**
     * POST /activity/sign —— 执行签到
     */
    public function sign()
    {
        $uid = $this->uid();
        $now = $this->nowMillis();

        $year  = (int)date('Y', intdiv($now, 1000));
        $month = (int)date('n', intdiv($now, 1000));
        $day   = (int)date('j', intdiv($now, 1000));

        // 当天重复签到直接返回当天记录（幂等）
        $exists = ActivitySignRecord::where('uid', $uid)
            ->where('sign_year', $year)
            ->where('sign_month', $month)
            ->where('sign_day', $day)
            ->find();

        if ($exists) {
            throw ApiException::system('今日已签到');
        }

        // 连续签到天数：看昨天有没有签
        $yesterdayTs = $now - 86400000;
        $prev = ActivitySignRecord::where('uid', $uid)
            ->where('sign_year', (int)date('Y', intdiv($yesterdayTs, 1000)))
            ->where('sign_month', (int)date('n', intdiv($yesterdayTs, 1000)))
            ->where('sign_day', (int)date('j', intdiv($yesterdayTs, 1000)))
            ->find();

        $continuous = $prev ? (int)$prev['continuous_days'] + 1 : 1;

        // 本月累计签到天数
        $monthRows = ActivitySignRecord::where('uid', $uid)
            ->where('sign_year', $year)
            ->where('sign_month', $month)
            ->select()
            ->toArray();

        $signDays = array_map(static fn ($r) => (int)$r['sign_day'], $monthRows);
        $signDays[] = $day;
        sort($signDays);

        // 奖励积分：连续签到递增（1/2/3/5/7 天封顶）
        $rewardMap = [1 => 1, 2 => 2, 3 => 3, 4 => 5, 5 => 7];
        $reward = $rewardMap[$continuous] ?? 7;

        ActivitySignRecord::create([
            'uid'            => $uid,
            'sign_year'      => $year,
            'sign_month'     => $month,
            'sign_day'       => $day,
            'sign_days'      => count($signDays),
            'continuous_days'=> $continuous,
            'reward_points'  => $reward,
            'sign_time'      => $now,
        ]);

        $this->addPoints($uid, $reward, 'sign', '', '每日签到奖励');

        return [
            'signDay'        => $day,
            'signDays'       => count($signDays),
            'continuousDays' => $continuous,
            'rewardPoints'   => $reward,
            'records'        => $this->buildSignRecords($signDays),
        ];
    }

    /**
     * GET /activity/points/balance —— 积分余额
     */
    public function pointsBalance()
    {
        $uid = $this->uid();

        return ['balance' => $this->pointsBalanceOf($uid)];
    }

    /* ====================================================================
     * 抽奖与重铸（写）
     * ================================================================== */

    /**
     * POST /activity/lottery/start/{id} —— 开始抽奖
     */
    public function lotteryStart()
    {
        $uid = $this->uid();
        $id  = (string)($this->request->param('id') ?? '');

        if ($id === '') {
            throw ApiException::missing('id');
        }

        $lottery = ActivityLottery::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', 0)
            ->find();

        if (!$lottery) {
            throw ApiException::notFound();
        }

        $now = $this->nowMillis();
        if ($now < (int)$lottery['start_time'] || $now > (int)$lottery['end_time']) {
            throw ApiException::system('活动未开始或已结束');
        }

        $cost = (int)$lottery['points'];
        if ($cost > 0 && $this->pointsBalanceOf($uid) < $cost) {
            throw ApiException::system('积分不足');
        }

        // 按概率抽 prize
        $prizes = ActivityLotteryPrize::where('lottery_id', $id)->select()->toArray();
        if ($prizes === []) {
            throw ApiException::notFound();
        }

        $roll   = random_int(1, 10000);
        $acc    = 0;
        $hitPrize = null;
        foreach ($prizes as $p) {
            $acc += (int)$p['probability'];
            if ($roll <= $acc) {
                $hitPrize = $p;
                break;
            }
        }

        $isWin = $hitPrize !== null;

        if ($cost > 0) {
            $this->addPoints($uid, -$cost, 'lottery', $id, '抽奖消耗');
        }

        ActivityLotteryLog::create([
            'uid'            => $uid,
            'lottery_id'     => $id,
            'prize_id'       => $hitPrize ? (string)$hitPrize['id'] : '',
            'is_win'         => $isWin ? 1 : 0,
            'consume_points' => $cost,
            'angle'          => random_int(0, 359),
        ]);

        return [
            'isWin'     => $isWin,
            'angle'     => random_int(0, 359),
            'prizeId'   => $hitPrize ? Transformer::id($hitPrize['id']) : null,
            'prizeName' => $hitPrize ? (string)$hitPrize['name'] : null,
            'image'     => $hitPrize ? (string)($hitPrize['image'] ?? '') : null,
            'imageUrl'  => $hitPrize && !empty($hitPrize['image'])
                ? Transformer::mediaBase() . $hitPrize['image']
                : null,
        ];
    }

    /**
     * POST /activity/recast/draw —— 重铸抽取
     */
    public function recastDraw()
    {
        $uid = $this->uid();

        $prizes = ActivityRecastPrize::where('status', 'active')->select()->toArray();
        if ($prizes === []) {
            throw ApiException::notFound();
        }

        // 按 weight 加权随机
        $totalWeight = array_sum(array_map(static fn ($p) => (int)$p['weight'], $prizes));
        if ($totalWeight <= 0) {
            throw ApiException::system('活动未开放');
        }

        $roll = random_int(1, $totalWeight);
        $acc  = 0;
        $hit  = null;
        foreach ($prizes as $p) {
            $acc += (int)$p['weight'];
            if ($roll <= $acc) {
                $hit = $p;
                break;
            }
        }

        if (!$hit) {
            throw ApiException::system('活动未开放');
        }

        ActivityRecastLog::create([
            'uid'           => $uid,
            'prize_id'      => (string)$hit['id'],
            'is_win'        => 1,
            'consume_amount'=> (int)($this->body()['amount'] ?? 0),
        ]);

        return [
            'isWin'     => true,
            'prizeId'   => Transformer::id($hit['id']),
            'prizeName' => (string)$hit['name'],
            'image'     => (string)($hit['image'] ?? ''),
            'imageUrl'  => !empty($hit['image'])
                ? Transformer::mediaBase() . $hit['image']
                : '',
        ];
    }

    /* ====================================================================
     * 内部工具
     * ================================================================== */

    /**
     * 签到状态数据
     *
     * records 是 31 位布尔数组 —— 与源站一致，
     * 不能改成对象数组或只返回已签天数，前端按索引取值。
     */
    private function signStatusData(string $uid): array
    {
        $now  = $this->nowMillis();
        $year = (int)date('Y', intdiv($now, 1000));
        $month = (int)date('n', intdiv($now, 1000));
        $today = (int)date('j', intdiv($now, 1000));

        $signDays = [];
        $continuous = 0;
        $rewardPoints = 0;

        if ($uid !== '') {
            $rows = ActivitySignRecord::where('uid', $uid)
                ->where('sign_year', $year)
                ->where('sign_month', $month)
                ->select()
                ->toArray();

            foreach ($rows as $r) {
                $signDays[] = (int)$r['sign_day'];
                $continuous = max($continuous, (int)$r['continuous_days']);
                $rewardPoints += (int)$r['reward_points'];
            }

            sort($signDays);
        }

        return [
            'year'           => $year,
            'month'          => $month,
            'today'          => $today,
            'signed'         => in_array($today, $signDays, true),
            'signDays'       => count($signDays),
            'continuousDays' => $continuous,
            'rewardPoints'   => $rewardPoints,
            'records'        => $this->buildSignRecords($signDays),
        ];
    }

    /**
     * 构建 31 位布尔数组
     *
     * @param int[] $signDays 本月已签的日期号
     * @return array<int,bool> 下标 0 对应 1 号
     */
    private function buildSignRecords(array $signDays): array
    {
        $records = array_fill(0, 31, false);

        foreach ($signDays as $d) {
            if ($d >= 1 && $d <= 31) {
                $records[$d - 1] = true;
            }
        }

        return $records;
    }

    /**
     * 积分余额
     *
     * 积分存在 user_wallets.total_income 是错的，源站是独立概念。
     * 这里用 activity_points_logs 的累计变化计算，与源站口径一致。
     */
    private function pointsBalanceOf(string $uid): int
    {
        $rows = ActivityPointsLog::where('uid', $uid)->select()->toArray();

        $balance = 0;
        foreach ($rows as $r) {
            $balance += (int)$r['change_points'];
        }

        return $balance;
    }

    /**
     * 记一笔积分流水并更新余额快照
     */
    private function addPoints(string $uid, int $change, string $bizType, string $bizId, string $remark): void
    {
        $balanceAfter = $this->pointsBalanceOf($uid) + $change;

        ActivityPointsLog::create([
            'uid'           => $uid,
            'change_points' => $change,
            'balance_after' => $balanceAfter,
            'biz_type'      => $bizType,
            'biz_id'        => $bizId,
            'remark'        => $remark,
        ]);
    }

    /**
     * 抽奖活动行转换
     *
     * ⚠️ 源站 GET /activity/lottery/active 的响应里**带 prizes 数组**，
     *    与 GET /activity/lottery/{id} 一致。之前只有详情接口组装 prizes，
     *    导致 active 接口返回的对象没有 prizes，抽奖页拿不到奖品列表。
     */
    private function lotteryRow(array $r): array
    {
        $row = [
            'id'        => Transformer::id($r['id']),
            'name'      => (string)$r['name'],
            ...Transformer::imagePair($r['cover'] ?? '', 'cover'),
            'points'    => (int)$r['points'],
            // ⚠️ start_time / end_time 在库里是 **datetime**，必须用 datetimeTs()
            //    换算成 13 位毫秒字符串。之前误用 ts()（(string)(int)$v），
            //    '2026-10-18 15:30:00' 被 (int) 截断成 2026，接口返回了 "2026"。
            'startTime' => Transformer::datetimeTs($r['start_time']),
            'endTime'   => Transformer::datetimeTs($r['end_time']),
            'rule'      => (string)$r['rule'],
            // ⚠️ 源站实测 status 是**布尔**（true = 启用），不是 0/1。
            //    库里 varchar(32) DEFAULT 'active'，故按「等于 active 或 1」判布尔。
            'status'    => in_array((string)$r['status'], ['1', 'active'], true),
        ];

        $prizes = ActivityLotteryPrize::where('lottery_id', (string)$r['id'])
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        // 图片用平铺双字段（源站形态）：image=相对路径，imageUrl=绝对地址。
        // 之前写成 'image' => Transformer::image(...) 会得到嵌套对象，
        // 前端 :src 拿到对象直接渲染成破图。
        $row['prizes'] = array_map(static fn ($p) => [
            'prizeId'     => Transformer::id($p['id']),
            'name'        => (string)$p['name'],
            ...Transformer::imagePair($p['image'] ?? '', 'image'),
            'level'       => (string)$p['level'],
            'probability' => (int)$p['probability'],
            'quantity'    => (int)$p['quantity'],
        ], $prizes);

        return $row;
    }
}