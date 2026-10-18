<?php
declare(strict_types=1);

namespace app\model;

/**
 * 邀请记录 —— 表 `user_invites`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 */
class UserInvite extends BaseModel
{
    protected $table = 'user_invites';

    protected $allowField = [
        'id',
        'uid',
        'invitee_uid',
        'invite_code',
        'reward_amount',
        'reward_status',
        'rewarded_at',
    ];
}
