<?php
declare(strict_types=1);

namespace app\helper;

/**
 * RSA 解密 —— 对应源站前端 JSEncrypt
 *
 * 源站登录/注册/改密的 password 字段经 RSA 公钥加密后 Base64 传输。
 * 公钥来源：源站 GET /passport/captcha/image 同响应下发（或写死在配置文件）。
 *
 * 注意 padding：JSEncrypt 默认 PKCS1 padding，即 PHP 的 OPENSSL_PKCS1_PADDING。
 */
final class Rsa
{
    /**
     * 解密
     *
     * @param string $encrypted base64 密文
     * @param string $privateKey PEM 私钥（PKCS#1 或 PKCS#8 均可）
     * @return string|null 解密失败返回 null
     */
    public static function decrypt(string $encrypted, string $privateKey): ?string
    {
        if ($privateKey === '' || $encrypted === '') {
            return null;
        }

        // 统一成 PKCS#8，PHP openssl 两种都吃但 PKCS#1 需转换
        $key = @openssl_pkey_get_private(self::normalizePem($privateKey, 'PRIVATE'));
        if ($key === false) {
            return null;
        }

        $plain = '';
        $ok = @openssl_private_decrypt(
            base64_decode($encrypted, true) ?: '',
            $plain,
            $key,
            OPENSSL_PKCS1_PADDING
        );

        return $ok ? $plain : null;
    }

    /**
     * 用公钥加密（仅自测用，正常流程前端加密）
     */
    public static function encrypt(string $text, string $publicKey): ?string
    {
        $key = @openssl_pkey_get_public(self::normalizePem($publicKey, 'PUBLIC'));
        if ($key === false) {
            return null;
        }
        $out = '';
        $ok = @openssl_public_encrypt($text, $out, $key, OPENSSL_PKCS1_PADDING);
        return $ok ? base64_encode($out) : null;
    }

    /**
     * 规范化 PEM
     *
     * 要处理三种来源（.env 里都能出现）：
     *   1. 真多行 PEM（含真实换行符）
     *   2. 字面 \n 转义的单行 —— .env 用双引号包裹时，PHP 的 parse_ini_file
     *      不会把 \n 还原成换行，会留下两个字符 '\' 'n'，
     *      openssl 直接报 "key not valid" 或 "bad base64 decode"。
     *      这就是本项目踩过的坑：密钥长度读出来是 1730（含字面 \n），
     *      但 openssl_pkey_get_private() 返回 false。
     *   3. 裸 base64（连头尾都没有）
     */
    private static function normalizePem(string $pem, string $type = 'PRIVATE'): string
    {
        $pem = trim($pem);
        if ($pem === '') {
            return '';
        }

        // 字面 \n / \r / \t 转义还原
        if (str_contains($pem, '\\n')) {
            $pem = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $pem);
        }

        // 去掉可能存在的引号包裹与首尾空白
        $pem = trim($pem, "\"' \t");

        if (str_contains($pem, '-----BEGIN')) {
            // 保证头尾换行齐全，openssl 对严格性有要求
            return preg_replace('/\R+/', "\n", $pem) . "\n";
        }

        // 裸 base64：去掉所有空白后按 64 列折行
        $body = preg_replace('/\s+/', '', $pem);

        return "-----BEGIN {$type} KEY-----\n"
            . chunk_split($body, 64, "\n")
            . "-----END {$type} KEY-----\n";
    }
}
