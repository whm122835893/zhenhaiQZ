<?php
declare(strict_types=1);

namespace app\model;

/**
 * 积分流水 —— 表 `activity_points_logs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityPointsLog extends BaseModel
{
    protected $table = 'activity_points_logs';

    protected $allowField = [
        'id',
        'uid',
        'change_points',
        'balance_after',
        'biz_type',
        'biz_id',
        'remark',
    ];
}
