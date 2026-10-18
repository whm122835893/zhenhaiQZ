<?php
declare(strict_types=1);

namespace app\model;

/**
 * 公告 —— 表 `notices`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class Notice extends BaseModel
{
    protected $table = 'notices';

    protected $autoSoftDelete = true;

    protected $type = [
        'is_top' => 'integer',
    ];

    protected $allowField = [
        'id',
        'cid',
        'title',
        'content',
        'cover',
        'cover_url',
        'view_count',
        'is_top',
        'published_ts',
    ];
}
