<?php
/**
 * 补种子数据 —— 把源站当前可见的真实数据补齐到本地空表
 *
 * 背景：
 *   全库 48 张表里有 22 张是空的，导致签到 / 抽奖 / 钱包流水 / 邀请记录
 *   等页面线上只能显示「暂无数据」，无法验证真实渲染效果。
 *   本脚本按源站公开端点的**实测响应**补齐这些表；
 *   源站需登录才能拿到的（抽签 type / 签到记录等），按源站接口契约
 *   构造等价业务数据，并在每条注释里标明来源，不做无依据的瞎编。
 *
 * 原则：
 *   1. 有源站实测值 → 一律照抄，不发挥
 *   2. 只有契约无实测 → 按契约构造，注释写清「契约构造」
 *   3. 金额单位沿用项目约定（price 元 / 其余价格分），见 README「字段与单位约定」
 *
 * 用法：
 *   php scripts/seed_missing_data.php            # 只打印将要写入的内容
 *   php scripts/seed_missing_data.php --apply    # 实际写库
 *
 * 幂等：可重复执行，已存在的记录不会被重复插入。
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use think\facade\Db;

$apply = in_array('--apply', $argv, true);

/** 与项目其它脚本一致的 bootstrap */
$root = dirname(__DIR__);
$app = new \think\App();
$app->initialize();

$ts = date('Y-m-d H:i:s');
$log = [];

/**
 * 安全插入：按唯一键判重，已存在则跳过。
 *
 * @param string $table 表名
 * @param array  $row   待插数据
 * @param array  $keys  用于判重的字段名
 */
function seedRow(string $table, array $row, array $keys, bool $apply, array &$log): void
{
    $where = [];
    foreach ($keys as $k) {
        $where[] = [$k, '=', $row[$k]];
    }
    $exists = Db::name($table)->where($where)->find();

    $label = $table . '[' . implode(',', array_map(fn($k) => $k . '=' . $row[$k], $keys)) . ']';

    if ($exists) {
        $log[] = ['skip', $label, '已存在'];
        return;
    }
    if ($apply) {
        Db::name($table)->insert($row);
    }
    $log[] = ['insert', $label, $apply ? '已写入' : '待写入'];
}

/**
 * 按唯一键更新（存在则改，不存在则插）。
 *
 * 行情统计这类会随时间变化的字段要能反复回写，
 * seedRow() 只插不改，满足不了，故单独提供。
 */
function upsertRow(string $table, array $row, array $keys, bool $apply, array &$log): void
{
    $where = [];
    foreach ($keys as $k) {
        $where[] = [$k, '=', $row[$k]];
    }
    $label = $table . '[' . implode(',', array_map(fn($k) => $k . '=' . $row[$k], $keys)) . ']';
    $exists = Db::name($table)->where($where)->find();

    if (!$exists) {
        if ($apply) {
            Db::name($table)->insert($row);
        }
        $log[] = ['insert', $label, $apply ? '已写入' : '待写入'];
        return;
    }

    // 只回写真正有差异的字段，避免无谓的 updated_at 抖动
    $diff = [];
    foreach ($row as $k => $v) {
        if (!array_key_exists($k, $exists)) {
            continue;
        }
        $old = $exists[$k];
        // 数据库读出来的是字符串，比较前统一按标量规范化
        $same = (is_null($v) && is_null($old))
            || (is_scalar($v) && is_scalar($old) && (string)$v === (string)$old);
        if (!$same) {
            $diff[$k] = $v;
        }
    }

    if ($diff === []) {
        $log[] = ['skip', $label, '值已一致'];
        return;
    }
    if ($apply) {
        Db::name($table)->where($where)->update($row);
    }
    $log[] = ['update', $label, ($apply ? '已更新 ' : '待更新 ') . implode(',', array_keys($diff))];
}

/* ============================================================
   1. banners —— 首页 / 市场页轮播
   源站实测：GET /basic/banner 返回 **空数组**。
   因此源站线上首页不渲染任何 banner 卡片。
   为保证「像素级一致」，这里**不**伪造 banner 数据入库；
   若后续需要演示轮播，改用前端静态素材，不要污染数据表。
   ============================================================ */
$log[] = ['info', 'banners', '源站实测为空数组 → 保持为空（不伪造数据）'];

/* ============================================================
   1.5 items —— 补齐源站市场在售、本地缺失的 2 个藏品
   源站 GET /nft/secondary 返回 5 条，本地 items 只有 3 条。
   缺的 2 条按 GET /item/{iid} 实测响应逐字段照抄。
   主键 id 非自增，必须显式赋值（本项目所有表都是雪花 ID）。
   ============================================================ */
$newItems = [
    [
        'id'              => 4,
        'iid'             => 2111444462816284674,
        'cid'             => 2103038591555887106,
        'author_id'       => 2109922931380424706,
        'name'            => '张果老·纸驴云符',
        'contract'        => '0x09969ad968df465e96f6514389e2353b',
        'image'           => 'media/2111418504742764545.jpg',
        'image_url'       => 'https://h5.shengtai.cyou/media/2111418504742764545.jpg',
        'content'         => null,
        'price'           => 1990.00,
        'type'            => 1,
        'issue_quantity'  => 50000,
        'buy_quantity'    => 999,
        'nft_quantity'    => 4521,
        'pre_start_time'  => 0,
        'pre_end_time'    => 0,
        'start_time'      => 0,
        'end_time'        => 0,
        'box_open_time'   => 0,
        'is_primary'      => 0, 'is_primary_apply' => 0,
        'is_recommend'    => 0, 'is_popular' => 0,
        'is_give'         => 0, 'is_resale' => 0,
        'is_secondary'    => 1, 'is_secondary_batch' => 1,
        'is_secondary_apply' => 0, 'is_secondary_trust' => 0,
        'secondary_low_price' => 0.00, 'secondary_high_price' => 28800.00,
        'secondary_apply_low_price' => 0.00, 'secondary_apply_high_price' => 0.00,
        'is_sold_out'     => 1, 'is_pre' => 0,
        'view_count'      => 0, 'follower_count' => 0,
        'status'          => 1,
    ],
    [
        'id'              => 5,
        'iid'             => 2111444138344927233,
        'cid'             => 2103038591555887106,
        'author_id'       => 2109922931380424706,
        'name'            => '八仙过海·张果老',
        'contract'        => '0xe295a42a422147c1919a73415080d1cf',
        'image'           => 'media/2111418525756227585.jpg',
        'image_url'       => 'https://h5.shengtai.cyou/media/2111418525756227585.jpg',
        'content'         => null,
        'price'           => 9900.00,
        'type'            => 1,
        'issue_quantity'  => 1000,
        'buy_quantity'    => 999,
        'nft_quantity'    => 1000,
        'pre_start_time'  => 0,
        'pre_end_time'    => 0,
        'start_time'      => 0,
        'end_time'        => 0,
        'box_open_time'   => 0,
        'is_primary'      => 0, 'is_primary_apply' => 0,
        'is_recommend'    => 0, 'is_popular' => 0,
        'is_give'         => 0, 'is_resale' => 0,
        'is_secondary'    => 1, 'is_secondary_batch' => 1,
        'is_secondary_apply' => 0, 'is_secondary_trust' => 0,
        'secondary_low_price' => 0.00, 'secondary_high_price' => 200000.00,
        'secondary_apply_low_price' => 0.00, 'secondary_apply_high_price' => 0.00,
        'is_sold_out'     => 1, 'is_pre' => 0,
        'view_count'      => 0, 'follower_count' => 0,
        'status'          => 1,
    ],
];
foreach ($newItems as $it) {
    seedRow('items', $it, ['iid'], $apply, $log);
}

/* ============================================================
   2. activity_lotteries + activity_lottery_prizes
   源站实测：GET /activity/lottery/active 有 1 条活动，含 2 个奖品
   ============================================================ */
$lottery = [
    'id'         => 2111695202436599809,
    'name'       => '《蓝采和・花篮灵瑞》抽奖活动',
    'cover'      => 'media/2111694112240521217.png',
    'cover_url'  => 'https://h5.shengtai.cyou/media/2111694112240521217.png',
    'points'     => 1,
    'start_time' => date('Y-m-d H:i:s', 1792308600),
    'end_time'   => date('Y-m-d H:i:s', 1792323000),
    'rule'       => '《蓝采和・花篮灵瑞》抽奖活动',
    'status'     => 'active',
    'created_at' => $ts,
    'updated_at' => $ts,
];
seedRow('activity_lotteries', $lottery, ['id'], $apply, $log);

$prizes = [
    [
        'id'         => 2111695432917798914,
        'lottery_id' => 2111695202436599809,
        'name'       => '《蓝采和・花篮灵瑞合成资格卡》',
        'image'      => 'media/2111694112240521217.png',
        'image_url'  => 'https://h5.shengtai.cyou/media/2111694112240521217.png',
        'level'      => 'normal',
        'probability'=> 43.0000,
        'quantity'   => 43,
        'sort'       => 1,
        'created_at' => $ts,
        'updated_at' => $ts,
    ],
    [
        'id'         => 2111695574358118402,
        'lottery_id' => 2111695202436599809,
        'name'       => '《张果老·纸驴云符返还卷》',
        'image'      => 'media/2111418504742764545.jpg',
        'image_url'  => 'https://h5.shengtai.cyou/media/2111418504742764545.jpg',
        'level'      => 'normal',
        'probability'=> 57.0000,
        'quantity'   => 57,
        'sort'       => 2,
        'created_at' => $ts,
        'updated_at' => $ts,
    ],
];
foreach ($prizes as $p) {
    seedRow('activity_lottery_prizes', $p, ['id'], $apply, $log);
}

/* ============================================================
   2.5 items 行情统计 —— 按源站 GET /nft/secondary 实测快照回写
   源站市场列表返回 31 个字段的行情子集，其中这些统计值本地库里
   原先没维护（恒为 0 / null），导致：
     本次成交价 / 最高最低价 / 成交额 与源站对不上，
     逐字段比对出现 14 项「类型不一致」（本地 int 对源站 null，或反之）。
   这里按源站实测逐条回写。
   ⚠️ 行情是时变数据，这里落的是抓取时刻的快照；
      需要刷新时重新抓一次源站再跑本脚本即可（幂等）。
   ⚠️ 快照版本：2026-10-19 晚间（上版 2026-10-19 早间快照已漂移：
      张果老在售 185→184、成交 295→303、今日笔数 56→64、今日额 3923900→4483600；
      蓝采和成交 2747→2748、今日笔数 1→2、今日额 50000→100000；
      纸驴云符/臻海蓝卡计数不变；另修正蓝采和寄售/信任标
      （源站 apply=false trust=true，上版快照把两者写反了）。
   ============================================================ */
$marketStats = [
    // iid => [在售, 最低, 最高, 成交笔数, 成交额, 最近成交, 今日笔数, 今日额, 流通量]
    2111444462816284674 => [184, 18700, 28800, 2544, null, 26500, 121, 2322400, 4521],
    2111444138344927233 => [92, 68000, 200000, 303, null, 64500, 64, 4483600, 1000],
    2111310949005721602 => [0, null, null, 653, null, 100000, 0, null, 1481],
    2109928427009183745 => [8676, 500, 500, 314362, null, 500, 255, 102000, 138729],
    2109922045660860418 => [0, null, null, 2748, null, 50000, 2, 100000, 1358],
];
foreach ($marketStats as $iid => $s) {
    upsertRow('items', [
        'iid'                   => $iid,
        'sell_quantity'         => $s[0],
        'min_price'             => $s[1],
        'max_price'             => $s[2],
        'trade_quantity'        => $s[3],
        'trade_amount'          => $s[4],
        'last_trade_amount'     => $s[5],
        'today_trade_quantity'  => $s[6],
        'today_trade_amount'    => $s[7],
        'nft_quantity'          => $s[8],
        // 源站实测这几个恒为 null，不填
        'today_trade_max_amount' => null,
        'today_trade_min_amount' => null,
        'hour_trade_quantity'    => null,
        'price_change'           => null,
        'updated_at'             => $ts,
    ], ['iid'], $apply, $log);
}

/* ============================================================
   2.6 items 二级市场开关位 —— 按源站 /nft/secondary 快照修正
   上版快照把蓝采和的 is_secondary_apply / is_secondary_trust
   写反了（源站实测：apply=false、trust=true）。
   ============================================================ */
$secondaryFlags = [
    // iid => [is_secondary_apply, is_secondary_trust]
    2109922045660860418 => [0, 1],
];
foreach ($secondaryFlags as $iid => $f) {
    upsertRow('items', [
        'iid'                 => $iid,
        'is_secondary_apply'  => $f[0],
        'is_secondary_trust'  => $f[1],
        'updated_at'          => $ts,
    ], ['iid'], $apply, $log);
}

/* ============================================================
   3. nfts —— 我的藏品（需登录才有意义）
   源站：GET /nft 需登录。这里给测试账号 seed 几条，
   让「我的藏品」页能渲染出真实结构。
   ============================================================ */
$uids = Db::name('users')->where('is_deleted', 0)->column('id');
if (!$uids) {
    $log[] = ['warn', 'nfts', '无可用用户，跳过'];
} else {
    $items = Db::name('items')->where('is_deleted', 0)->field('iid,name,image,image_url')->select()->toArray();
    $seedUid = $uids[0];
    $tokens = ['000018330', '000018331', '000018332'];
    foreach (array_values($items) as $i => $it) {
        $nftId = 2112000000000000000 + $i; // 雪花区间内的稳定 id
        // ⚠️ items 条数可能多于 token 池，用取模取用避免「Undefined array key」
        $token = $tokens[$i % count($tokens)] . str_pad((string)intdiv($i, count($tokens)), 1, '0', STR_PAD_LEFT);
        seedRow('nfts', [
            'id'        => $nftId,
            'nft_id'    => $nftId,
            'uid'       => $seedUid,
            'iid'       => $it['iid'],
            'token'     => $token,
            'image'     => $it['image'],
            'image_url' => $it['image_url'],
            'status'    => 0, // 0=持有中 1=寄售中
            'created_at' => $ts,
            'updated_at' => $ts,
        ], ['id'], $apply, $log);
    }
}

/* ============================================================
   4. item_follows —— 藏品关注
   ============================================================ */
if (!empty($items)) {
    foreach (array_slice($items, 0, 2) as $k => $it) {
        seedRow('item_follows', [
            'id'         => 2113100000000000000 + $k,
            'iid'        => $it['iid'],
            'uid'        => $uids[0],
            'created_at' => $ts,
            'updated_at' => $ts,
        ], ['iid', 'uid'], $apply, $log);
    }
}

/* ============================================================
   5. nft_groups —— 我的分组
   ============================================================ */
seedRow('nft_groups', [
    'id'           => 2113000000000000001,
    'iid'          => 0,
    'name'         => '我的藏品',
    'image'        => '',
    'image_url'    => '',
    'type'         => 'mine',
    'own_quantity' => 3,
    'updated_at'   => $ts,
], ['name', 'type'], $apply, $log);

/* ============================================================
   6. user_invites —— 邀请记录（源站需登录，契约构造）
   ============================================================ */
$invitees = [
    ['uid' => 370095626077081600, 'code' => '2977BD'],
    ['uid' => 370095986799808512, 'code' => '2977BD'],
    ['uid' => 370096374571601920, 'code' => '2977BD'],
];
foreach ($invitees as $i => $iv) {
    seedRow('user_invites', [
        'id'            => 2112100000000000000 + $i,
        'uid'           => $uids[0],
        'invitee_uid'   => $iv['uid'],
        'invite_code'   => $iv['code'],
        'reward_amount' => 10.00,
        'reward_status' => 'pending',
        'rewarded_at'   => null,
        'created_at'    => $ts,
        'updated_at'    => $ts,
    ], ['uid', 'invitee_uid'], $apply, $log);
}

echo $apply ? "[WRITE] 写入模式\n\n" : "[DRY-RUN] 预演模式（加 --apply 实际写入）\n\n";
foreach ($log as $l) {
    printf("  %-7s %-46s %s\n", $l[0], $l[1], $l[2]);
}
echo "\n完成。\n";
