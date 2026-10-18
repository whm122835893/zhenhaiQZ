<?php
declare(strict_types=1);

namespace app\model;

/**
 * IM 单聊会话 —— 表 `im_user_sessions`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class ImUserSession extends BaseModel
{
    protected $table = 'im_user_sessions';

    protected $allowField = [
        'id',
        'uid',
        'peer_uid',
        'last_message_id',
        'last_message_at',
        'unread_count',
    ];
}
