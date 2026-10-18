<?php
declare(strict_types=1);

namespace app\model;

/**
 * 图谱分类 —— 表 `product_tp_categories`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ProductTpCategory extends BaseModel
{
    protected $table = 'product_tp_categories';

    protected $type = [
        'sort' => 'integer',
    ];

    protected $allowField = [
        'id',
        'name',
        'image',
        'image_url',
        'sort',
    ];
}
