<?php
// RSA 密钥配置
//
// 用途：前端用公钥 JSEncrypt 加密登录/注册/改密的明文密码，
//       后端用私钥解密。私钥严禁进代码库，只走环境变量。
//
// 生成 PKCS#8 私钥：
//   openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out rsa_private.pem
// 生成 PKCS#1 公钥：
//   openssl rsa -in rsa_private.pem -pubout -out rsa_public.pem
//
// 派生公钥（不额外存文件）：
//   openssl rsa -in rsa_private.pem -pubout

return [
    // PKCS#8 私钥 PEM（带 \n 换行，环境变量里用 \n 转义）
    'private_key' => env('RSA_PRIVATE_KEY', ''),

    // SPKI 公钥 PEM，前端拉取后用于加密
    'public_key'  => env('RSA_PUBLIC_KEY', ''),

    // 加密算法，固定 PKCS1 v1.5 —— 与 JSEncrypt 默认兼容
    'padding'     => \OPENSSL_PKCS1_PADDING,
];