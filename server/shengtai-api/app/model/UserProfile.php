<?php
declare(strict_types=1);

namespace app\model;

/**
 * 用户资料 —— 表 `user_profiles`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class UserProfile extends BaseModel
{
    protected $table = 'user_profiles';

    protected $allowField = [
        'id',
        'uid',
        'nickname',
        'avatar',
        'avatar_url',
        'sex',
        'birthday',
        'country',
        'region_code',
        'introduction',
        'chain_address',
        'invite_code',
        'inviter',
        'token',
    ];
}
