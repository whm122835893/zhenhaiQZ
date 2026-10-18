<?php
declare(strict_types=1);

namespace app\model;

/**
 * 签到记录 —— 表 `activity_sign_records`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivitySignRecord extends BaseModel
{
    protected $table = 'activity_sign_records';

    protected $allowField = [
        'id',
        'uid',
        'sign_year',
        'sign_month',
        'sign_day',
        'sign_days',
        'continuous_days',
        'reward_points',
        'sign_time',
    ];
}
