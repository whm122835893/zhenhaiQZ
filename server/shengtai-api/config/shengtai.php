<?php
// JWT / 业务常量配置
//
// 密钥必须通过环境变量注入，禁止硬编码进代码库（安全红线）。
// 生成方式：php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"

return [
    'jwt' => [
        // HS256 密钥
        'secret'          => env('JWT_SECRET', ''),

        // access token 有效期：源站 token 无明显过期，本地定 7 天
        'ttl'             => (int)env('JWT_TTL', 7 * 24 * 3600),

        // 签发者标识（便于将来多端区分）
        'issuer'          => env('JWT_ISSUER', 'shengtai-api'),
    ],

    'rsa' => [
        // 登录/注册/改密的密码 RSA 私钥（PKCS#8 PEM）
        // 前端用配套公钥 JSEncrypt 加密
        'private_key'     => env('RSA_PRIVATE_KEY', ''),
    ],

    'shengtai' => [
        // 源站资源域名，用于拼 imageUrl
        'media_base'      => env('MEDIA_BASE', 'https://h5.shengtai.cyou/'),

        // 源站 appid（公共参数，本地沿用以兼容原前端）
        'appid'           => env('APP_ID', '__UNI__22FD095'),

        // 源站版本号
        'version'         => env('APP_VERSION', '1.3.2'),
    ],

    'order' => [
        // 支付窗口：源站实测 endPayTime - createTime = 180000ms（3 分钟）
        'pay_window_ms'   => 180000,
    ],

    'cors' => [
        // null = 回显任意 Origin，与源站 api.shengtai.cyou 实测行为一致。
        // 生产环境请改成白名单数组，例如：
        //   ['allow_origins' => ['https://h5.shengtai.cyou']]
        // 支持 fnmatch 通配：'https://*.example.com'
        // 环境变量 CORS_ORIGINS=逗号分隔；留空 = 不限制
        'allow_origins'   => (function () {
            $raw = trim((string)env('CORS_ORIGINS', ''));
            if ($raw === '') {
                return null;
            }
            $list = array_values(array_filter(array_map('trim', explode(',', $raw))));
            return $list === [] ? null : $list;
        })(),
    ],
];
