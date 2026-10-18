<?php
declare(strict_types=1);

namespace app\model;

/**
 * 轮播图 —— 表 `banners`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class Banner extends BaseModel
{
    protected $table = 'banners';

    protected $autoSoftDelete = true;

    protected $type = [
        'sort' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'title',
        'image',
        'image_url',
        'link_type',
        'link_value',
        'position',
        'sort',
        'status',
    ];
}
