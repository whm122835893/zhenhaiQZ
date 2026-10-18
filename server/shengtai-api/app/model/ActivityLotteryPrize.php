<?php
declare(strict_types=1);

namespace app\model;

/**
 * 抽奖奖品 —— 表 `activity_lottery_prizes`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityLotteryPrize extends BaseModel
{
    protected $table = 'activity_lottery_prizes';

    protected $type = [
        'level' => 'integer',
        'sort' => 'integer',
    ];

    protected $allowField = [
        'id',
        'lottery_id',
        'name',
        'image',
        'image_url',
        'level',
        'probability',
        'quantity',
        'sort',
    ];
}
