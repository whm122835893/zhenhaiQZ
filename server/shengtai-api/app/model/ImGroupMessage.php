<?php
declare(strict_types=1);

namespace app\model;

/**
 * IM 群消息 —— 表 `im_group_messages`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ImGroupMessage extends BaseModel
{
    protected $table = 'im_group_messages';

    protected $type = [
        'type' => 'integer',
        'is_recalled' => 'integer',
    ];

    protected $allowField = [
        'id',
        'gid',
        'uid',
        'type',
        'content',
        'extra',
        'is_recalled',
        'sent_at',
    ];
}
