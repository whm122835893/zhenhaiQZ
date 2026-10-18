<?php
declare(strict_types=1);

namespace app\model;

use app\service\Transformer;

/**
 * 用户账号 —— 表 `users`
 *
 * 字段与 database/create_schema.sql 一一对应。
 * 业务时间字段统一 bigint 13 位毫秒（与源站返回一致）。
 *
 * ⚠️ 敏感字段处理红线：
 *   password_hash / salt / second_password_hash 永远不返回给前端。
 *   real_name / id_card 必须脱敏（源站返回掩码形式）。
 */
class User extends BaseModel
{
    protected $table = 'users';

    protected $autoSoftDelete = true;

    protected $type = [
        'has_password'         => 'integer',
        'has_second_password'  => 'integer',
        'has_real_name'        => 'integer',
        // status 是字符串枚举：normal / forbidden / frozen（不是整型）
    ];

    protected $hidden = [
        'password_hash',
        'salt',
        'second_password_hash',
    ];

    protected $allowField = [
        'id',
        'phone',
        'email',
        'account',
        'password_hash',
        'salt',
        'has_password',
        'has_second_password',
        'second_password_hash',
        'has_real_name',
        'real_name',
        'id_card',
        'status',
        'last_login_at',
    ];

    /**
     * 账号信息响应体
     *
     * 与源站 GET /passport/account 的字段命名对齐（10 个字段）。
     * createTime 由 created_at（datetime）转毫秒 —— users 表是本项目
     * 唯一没有 created_ts 的业务表，因为它是账号表不是交易表。
     */
    public function toAccountArray(?array $row = null): array
    {
        $row ??= $this->toArray();
        return [
            'userId'            => Transformer::id($row['id'] ?? ''),
            'phone'             => self::maskPhone($row['phone'] ?? ''),
            'email'             => self::maskEmail($row['email'] ?? ''),
            'account'           => (string)($row['account'] ?? ''),
            'hasPassword'       => (bool)($row['has_password'] ?? false),
            'hasSecondPassword' => (bool)($row['has_second_password'] ?? false),
            'hasRealName'       => (bool)($row['has_real_name'] ?? false),
            'realName'          => self::maskName($row['real_name'] ?? ''),
            'idCard'            => self::maskIdCard($row['id_card'] ?? ''),
            'status'            => (string)($row['status'] ?? ''),
            'lastLoginAt'       => (int)($row['last_login_at'] ?? 0) > 0
                ? Transformer::ts($row['last_login_at'])
                : null,
            'createTime'        => Transformer::datetimeTs($row['created_at'] ?? null),
        ];
    }

    /**
     * 手机号脱敏：138****8000
     */
    public static function maskPhone(string $phone): string
    {
        if ($phone === '' || strlen($phone) < 7) {
            return $phone;
        }
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    /**
     * 邮箱脱敏：ab***@example.com
     */
    public static function maskEmail(string $email): string
    {
        if ($email === '' || !str_contains($email, '@')) {
            return $email;
        }
        [$name, $domain] = explode('@', $email, 2);
        $keep = mb_substr($name, 0, 2);
        return $keep . '***@' . $domain;
    }

    /**
     * 姓名脱敏：张**
     */
    public static function maskName(string $name): string
    {
        if ($name === '') {
            return '';
        }
        return mb_substr($name, 0, 1) . str_repeat('*', max(1, mb_strlen($name) - 1));
    }

    /**
     * 身份证脱敏：110***********1234（保留前 3 后 4）
     */
    public static function maskIdCard(string $idCard): string
    {
        if ($idCard === '' || strlen($idCard) < 8) {
            return $idCard;
        }
        $len = strlen($idCard) - 7;
        return substr($idCard, 0, 3) . str_repeat('*', $len) . substr($idCard, -4);
    }
}