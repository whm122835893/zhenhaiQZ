<?php
declare(strict_types=1);

namespace app\model;

/**
 * 作者 —— 表 `item_authors`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ItemAuthor extends BaseModel
{
    protected $table = 'item_authors';

    protected $allowField = [
        'id',
        'name',
        'avatar',
        'avatar_url',
        'introduction',
        'works_count',
        'follower_count',
    ];
}
