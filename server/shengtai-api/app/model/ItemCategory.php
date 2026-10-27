<?php
declare(strict_types=1);

namespace app\model;

/**
 * 藏品分类 —— 表 `item_categories`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ItemCategory extends BaseModel
{
    protected $table = 'item_categories';

    protected $type = [
        'sort' => 'integer',
        // status 是字符串枚举（enabled/disabled），不能声明 integer cast——
        // 否则 where('status','enabled') 的值会被强转 0，
        // MySQL 里 varchar 与 0 比较时 'enabled'/'disabled' 都转成 0，过滤失效
    ];

    protected $allowField = [
        'id',
        'cid',
        'name',
        'cover',
        'cover_url',
        'sort',
        'status',
    ];
}
