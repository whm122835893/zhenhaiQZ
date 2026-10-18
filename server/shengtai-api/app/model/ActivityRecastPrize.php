<?php
declare(strict_types=1);

namespace app\model;

/**
 * 重铸奖池 —— 表 `activity_recast_prizes`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ActivityRecastPrize extends BaseModel
{
    protected $table = 'activity_recast_prizes';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'type_id',
        'doll_type',
        'name',
        'image',
        'weight',
        'probability',
        'quantity',
        'status',
    ];
}
