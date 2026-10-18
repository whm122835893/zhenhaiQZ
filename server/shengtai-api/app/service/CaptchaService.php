<?php

namespace app\service;

use think\facade\Cache;

/**
 * 图形验证码服务
 *
 * 源站 GET /passport/captcha/image 返回 { key, image, publicKey }，
 * image 是 base64 的 svg/图片数据。这里生成 key + 一张 4 位数字算式验证码，
 * 答案存 Cache（默认 5 分钟），校验时一次性消费。
 *
 * 说明：源站 image 字段实测为空串，前端只用 key 做二次校验，
 * 因此本地同样允许 image 为空串，保持包络一致。
 */
class CaptchaService
{
    /** 验证码有效期（秒） */
    const TTL = 300;

    /** Cache 存储前缀 */
    const PREFIX = 'captcha:';

    /**
     * 生成一组新验证码
     *
     * @return string key（32 位 hex）
     */
    public static function create(): string
    {
        $a = mt_rand(1, 9);
        $b = mt_rand(1, 9);
        $op = mt_rand(0, 1) === 1 ? '+' : '-';

        // 保证结果为正，避免用户答负数
        if ($op === '-' && $b > $a) {
            [$a, $b] = [$b, $a];
        }

        $key = bin2hex(random_bytes(16));
        $code = (string)($op === '+' ? $a + $b : $a - $b);

        Cache::set(self::PREFIX . $key, $code, self::TTL);

        return $key;
    }

    /**
     * 校验验证码（一次性消费，校验后立即失效）
     */
    public static function check(string $key, string $answer): bool
    {
        $cacheKey = self::PREFIX . $key;
        $code    = Cache::get($cacheKey);

        // 无论对错都先删除，防止暴力重放
        if ($code === null) {
            return false;
        }
        Cache::delete($cacheKey);

        return strcasecmp((string)$code, trim($answer)) === 0;
    }
}