<?php
declare(strict_types=1);

namespace app\model;

/**
 * 抽奖活动 —— 表 `activity_lotteries`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityLottery extends BaseModel
{
    protected $table = 'activity_lotteries';

    protected $autoSoftDelete = true;

    protected $type = [
        'points' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'name',
        'cover',
        'cover_url',
        'points',
        'start_time',
        'end_time',
        'rule',
        'status',
    ];
}
