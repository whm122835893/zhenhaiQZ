<?php
declare(strict_types=1);

namespace app\model;

/**
 * 版权信息（源站全局，不按iid过滤） —— 表 `item_copyrights`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ItemCopyright extends BaseModel
{
    protected $table = 'item_copyrights';

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
