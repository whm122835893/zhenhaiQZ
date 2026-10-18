<?php
declare(strict_types=1);

namespace app\model;

/**
 * 公告-藏品关联 —— 表 `notice_item_relations`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class NoticeItemRelation extends BaseModel
{
    protected $table = 'notice_item_relations';

    protected $type = [
        'is_secondary' => 'integer',
        'sort' => 'integer',
    ];

    protected $allowField = [
        'id',
        'notice_id',
        'iid',
        'name',
        'image',
        'image_url',
        'is_secondary',
        'sort',
    ];
}
