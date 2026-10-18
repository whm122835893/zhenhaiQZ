<?php
declare(strict_types=1);

namespace app\service;

/**
 * 短信验证码服务
 *
 * 本地开发环境：直接把验证码写进 runtime/cache，开发模式由接口原样返回，
 * 方便测试。生产环境必须接真实短信网关（阿里云/腾讯云），
 * 且**不能**在响应里返回 code。
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

        // TODO 生产环境：在此调用短信网关发送，代码绝不能出现在响应里

        return $code;
    }

    /** 校验验证码（校验后立即失效，防重复使用） */
    public static function verify(string $phone, string $code): bool
    {
        if ($code === '') {
            return false;
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
