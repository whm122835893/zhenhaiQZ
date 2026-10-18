<?php
declare(strict_types=1);

namespace app\model;

/**
 * 合成活动记录 —— 表 `product_merge_records`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ProductMergeRecord extends BaseModel
{
    protected $table = 'product_merge_records';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'mid',
        'status',
    ];
}
