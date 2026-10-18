<?php
declare(strict_types=1);

namespace app\model;

/**
 * 抽奖记录 —— 表 `activity_lottery_logs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityLotteryLog extends BaseModel
{
    protected $table = 'activity_lottery_logs';

    protected $type = [
        'is_win' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'lottery_id',
        'prize_id',
        'is_win',
        'consume_points',
        'angle',
    ];
}
