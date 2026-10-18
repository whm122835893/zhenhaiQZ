<?php
declare(strict_types=1);

namespace app\model;

/**
 * IM 群 —— 表 `im_groups`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ImGroup extends BaseModel
{
    protected $table = 'im_groups';

    protected $autoSoftDelete = true;

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'name',
        'avatar',
        'avatar_url',
        'status',
        'user_total',
        'blacklist_total',
        'creator_uid',
    ];
}
