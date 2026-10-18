<?php
/**
 * 源站数据同步 —— 按源站实测值校正本地种子数据
 *
 * 背景：
 *   items 表的 author_id / price / 时间戳 / 二次价格 等字段在早期种子里
 *   有猜测成分，与源站实际值不符（例如把「闹翻天」的藏品挂到了「臻海艺术」名下、
 *   蓝采和的售卖时间全为 0、secondaryHighPrice 单位差 100 倍）。
 *   本脚本按 GET /item/{iid} 的实测响应逐字段回写，保证与源站一致。
 *
 * 用法：
 *   php scripts/sync_items_from_source.php            # 只打印差异，不写库
 *   php scripts/sync_items_from_source.php --apply    # 实际写库
 *
 * 幂等：可重复执行。
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$apply = in_array('--apply', $argv, true);

/** 源站实测快照（2026-10 抓取，GET /item/{iid} 全字段） */
const SOURCE = [
    2109928427009183745 => [
        'author_id' => 2103038557930151938,
        'price' => 388.00, 'type' => '1',
        'buy_quantity' => 100, 'issue_quantity' => 888888, 'nft_quantity' => 171409,
        'is_sold_out' => 1, 'is_pre' => 0,
        'pre_start_time' => 0, 'pre_end_time' => 0,
        'start_time' => 1792067400000, 'end_time' => 1792078800000,
        'secondary_low_price' => 0.00, 'secondary_high_price' => 500.00,
        'secondary_apply_low_price' => 0.00, 'secondary_apply_high_price' => 0.00,
        'is_primary' => 0, 'is_primary_apply' => 0, 'is_recommend' => 0, 'is_popular' => 0,
        'is_give' => 0, 'is_resale' => 0,
        'is_secondary' => 1, 'is_secondary_batch' => 1, 'is_secondary_apply' => 0, 'is_secondary_trust' => 1,
    ],
    2111310949005721602 => [
        'author_id' => 2109922931380424706,
        'price' => 9900.00, 'type' => '1',
        'buy_quantity' => 1, 'issue_quantity' => 2500, 'nft_quantity' => 1481,
        'is_sold_out' => 1, 'is_pre' => 0,
        'pre_start_time' => 0, 'pre_end_time' => 0,
        'start_time' => 0, 'end_time' => 0,
        'secondary_low_price' => 0.00, 'secondary_high_price' => 100000.00,
        'secondary_apply_low_price' => 100000.00, 'secondary_apply_high_price' => 120000.00,
        'is_primary' => 0, 'is_primary_apply' => 0, 'is_recommend' => 0, 'is_popular' => 0,
        'is_give' => 0, 'is_resale' => 0,
        'is_secondary' => 1, 'is_secondary_batch' => 0, 'is_secondary_apply' => 1, 'is_secondary_trust' => 1,
    ],
    2109922045660860418 => [
        'author_id' => 2109922931380424706,
        'price' => 6990.00, 'type' => '1',
        'buy_quantity' => 1, 'issue_quantity' => 5000, 'nft_quantity' => 2016,
        'is_sold_out' => 1, 'is_pre' => 0,
        'pre_start_time' => 1792054800000, 'pre_end_time' => 1792056600000,
        'start_time' => 1792060200000, 'end_time' => 1792063800000,
        'secondary_low_price' => 0.00, 'secondary_high_price' => 50000.00,
        'secondary_apply_low_price' => 40000.00, 'secondary_apply_high_price' => 50000.00,
        'is_primary' => 0, 'is_primary_apply' => 0, 'is_recommend' => 0, 'is_popular' => 0,
        'is_give' => 0, 'is_resale' => 0,
        'is_secondary' => 1, 'is_secondary_batch' => 1, 'is_secondary_apply' => 1, 'is_secondary_trust' => 0,
    ],
];

$app = new \think\App();
$app->initialize();

$db = $app->db;

$changed = 0;
foreach (SOURCE as $iid => $want) {
    $row = $db->table('items')->where('iid', $iid)->find();
    if ($row === null) {
        echo "[跳过] 本地无 iid={$iid}\n";
        continue;
    }

    $diff = [];
    foreach ($want as $col => $val) {
        $cur = $row[$col] ?? null;
        $norm = static function ($v) {
            if ($v === null || $v === '') { return null; }
            return is_numeric($v) ? (string)(0 + $v) : (string)$v;
        };
        if ($norm($cur) !== $norm($val)) {
            $diff[$col] = ['cur' => $cur, 'want' => $val];
        }
    }

    if ($diff === []) {
        echo "[一致] {$row['name']} (iid={$iid})\n";
        continue;
    }

    $changed++;
    echo "[差异] {$row['name']} (iid={$iid}) 共 " . count($diff) . " 个字段\n";
    foreach ($diff as $col => $d) {
        printf("        %-26s 当前=%-22s 源站=%s\n", $col,
            var_export($d['cur'], true), var_export($d['want'], true));
    }

    if ($apply) {
        $db->table('items')->where('iid', $iid)->update($want);
        echo "        → 已写库\n";
    }
}

echo "\n" . ($changed === 0 ? "全部一致，无需改动。" : "共 $changed 条藏品存在差异。");
echo $apply ? "（已 --apply 写库）\n" : "（预演模式，加 --apply 生效）\n";
