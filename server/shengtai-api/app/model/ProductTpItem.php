<?php
declare(strict_types=1);

namespace app\model;

/**
 * 图谱项 —— 表 `product_tp_items`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ProductTpItem extends BaseModel
{
    protected $table = 'product_tp_items';

    protected $type = [
        'sort' => 'integer',
    ];

    protected $allowField = [
        'id',
        'cid',
        'iid',
        'name',
        'image',
        'image_url',
        'sort',
    ];
}
