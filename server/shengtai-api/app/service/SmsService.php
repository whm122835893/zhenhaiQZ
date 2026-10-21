<?php
declare(strict_types=1);

namespace app\service;

/**
 * 短信验证码服务
 *
 * 本地开发环境：验证码落库（verification_codes 表）+ 写缓存兜底，
 * 开发模式由接口原样返回，方便测试。生产环境必须接真实短信网关
 * （阿里云/腾讯云），且**不能**在响应里返回 code。
 *
 * 吸收司南 v2.2 规范：验证码独立建表（verification_codes），
 * 每条记录有 used 标记与过期时间，便于审计与风控（发送频率分析）。
 */
final class SmsService
{
    private const TTL = 300; // 5 分钟

    private static function cacheKey(string $phone): string
    {
        return 'sms:' . $phone;
    }

    /**
     * 下发验证码
     *
     * @return string 验证码明文（仅内部/开发环境使用）
     */
    public static function send(string $phone): string
    {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        \think\facade\Cache::set(self::cacheKey($phone), $code, self::TTL);

        // 落库审计：旧记录随同失效（同账号同场景只保留最新一条有效码）
        try {
            \think\facade\Db::name('verification_codes')
                ->where('account', $phone)
                ->where('scene', 'login')
                ->where('used', 0)
                ->update(['used' => 1]);

            \think\facade\Db::name('verification_codes')->insert([
                'account'    => $phone,
                'code'       => $code,
                'scene'      => 'login',
                'used'       => 0,
                'expires_at' => (int)round(microtime(true) * 1000) + self::TTL * 1000,
            ]);
        } catch (\Throwable) {
            // 表不存在（未跑迁移）时静默降级为纯缓存模式
        }

        // TODO 生产环境：在此调用短信网关发送，代码绝不能出现在响应里

        return $code;
    }

    /** 校验验证码（校验后立即失效，防重复使用） */
    public static function verify(string $phone, string $code): bool
    {
        if ($code === '') {
            return false;
        }

        // 优先消费 DB 记录；失败再走缓存兜底（本地/迁移前兼容）
        try {
            $row = \think\facade\Db::name('verification_codes')
                ->where('account', $phone)
                ->where('scene', 'login')
                ->where('used', 0)
                ->order('id', 'desc')
                ->find();

            if ($row) {
                if ((int)$row['expires_at'] > 0 && (int)$row['expires_at'] < (int)round(microtime(true) * 1000)) {
                    return false;
                }
                if (!hash_equals((string)$row['code'], $code)) {
                    return false;
                }
                \think\facade\Db::name('verification_codes')
                    ->where('id', (int)$row['id'])
                    ->update(['used' => 1]);
                \think\facade\Cache::delete(self::cacheKey($phone));
                return true;
            }
        } catch (\Throwable) {
            // 降级到缓存校验
        }

        $key = self::cacheKey($phone);
        $cached = \think\facade\Cache::get($key);

        if ($cached === null || !hash_equals((string)$cached, $code)) {
            return false;
        }

        // 一次性消费
        \think\facade\Cache::delete($key);
        return true;
    }
}
