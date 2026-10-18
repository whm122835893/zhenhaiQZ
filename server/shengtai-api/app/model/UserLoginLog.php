<?php
declare(strict_types=1);

namespace app\model;

/**
 * 登录日志 —— 表 `user_login_logs`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class UserLoginLog extends BaseModel
{
    protected $table = 'user_login_logs';

    protected $type = [
        'status' => 'integer',
    ];

    protected $allowField = [
        'id',
        'uid',
        'account',
        'login_type',
        'ip',
        'device_id',
        'device_model',
        'platform',
        'status',
        'fail_reason',
    ];
}
