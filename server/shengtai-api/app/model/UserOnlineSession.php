<?php
declare(strict_types=1);

namespace app\model;

/**
 * 在线会话 —— 表 `user_online_sessions`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class UserOnlineSession extends BaseModel
{
    protected $table = 'user_online_sessions';

    protected $type = [
        'is_online' => 'integer',
        'is_self' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'device_id',
        'device_model',
        'platform',
        'ip',
        'token',
        'signin_way',
        'login_at',
        'last_active_at',
        'is_online',
        'is_self',
    ];
}
