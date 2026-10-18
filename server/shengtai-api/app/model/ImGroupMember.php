<?php
declare(strict_types=1);

namespace app\model;

/**
 * IM 群成员 —— 表 `im_group_members`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ImGroupMember extends BaseModel
{
    protected $table = 'im_group_members';

    protected $type = [
        'is_blacklist' => 'integer',
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'gid',
        'uid',
        'nickname',
        'is_blacklist',
        'status',
        'joined_at',
    ];
}
