<?php
declare(strict_types=1);

namespace app\model;

/**
 * 公告分类 —— 表 `notice_categories`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NoticeCategory extends BaseModel
{
    protected $table = 'notice_categories';

    protected $type = [
        'sort' => 'integer',
    ];

    protected $allowField = [
        'id',
        'cid',
        'name',
        'cover',
        'cover_url',
        'sort',
    ];
}
