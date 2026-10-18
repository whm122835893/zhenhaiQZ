<?php
declare(strict_types=1);

namespace app\helper;

/**
 * 极简 JWT（HS256）—— 零第三方依赖，用 PHP 标准库实现
 *
 * 源站 token 是 32 位十六进制字符串（opaque token），本地改用无状态 JWT，
 * 好处：多实例部署无需共享 session。
 *
 * 注意：HS256 只做完整性校验，不能防伪造（密钥在服务端），
 * 若要防重放需在 payload 加 jti 并落库，这里当前场景够用。
 */
final class Jwt
{
    private const ALGO = 'sha256';

    /**
     * 生成 token
     *
     * @param array<string,mixed> $payload
     */
    public static function encode(array $payload, string $secret, int $ttlSeconds): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $now     = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $ttlSeconds;

        $segments = [
            self::b64UrlEncode(self::jsonEncode($header)),
            self::b64UrlEncode(self::jsonEncode($payload)),
        ];

        $signing = implode('.', $segments);
        $segments[] = self::b64UrlEncode(hash_hmac(self::ALGO, $signing, $secret, true));

        return implode('.', $segments);
    }

    /**
     * 解析并校验 token
     *
     * @return array<string,mixed>|null 校验失败返回 null
     */
    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$h, $p, $sig] = $parts;

        // 1. 验签名（hash_equals 防时序攻击）
        $expected = self::b64UrlEncode(hash_hmac(self::ALGO, "$h.$p", $secret, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        // 2. 解 payload
        $payload = self::b64UrlDecode($p);
        if ($payload === null) {
            return null;
        }
        $data = self::jsonDecode($payload);
        if (!is_array($data)) {
            return null;
        }

        // 3. 验过期
        if (isset($data['exp']) && (int)$data['exp'] < time()) {
            return null;
        }

        return $data;
    }

    /** 取当前登录 uid，失败返回 null */
    public static function uid(string $token, string $secret): ?string
    {
        $data = self::decode($token, $secret);
        if ($data === null || empty($data['uid'])) {
            return null;
        }
        return (string)$data['uid'];
    }

    /* ---------- 内部实现 ---------- */

    private static function jsonEncode(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function jsonDecode(string $json): mixed
    {
        return json_decode($json, true);
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
