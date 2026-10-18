<?php
/**
 * 从 .env 的 RSA 密钥对导出裸 base64 公钥，供前端 JSEncrypt 使用。
 *
 * 用法：php scripts/export_public_key.php
 * 输出可直接粘进前端 .env 的 VITE_RSA_PUBLIC_KEY
 */
require __DIR__ . '/../vendor/autoload.php';

$app = new think\App(dirname(__DIR__));
$app->initialize();

$pub = (string)config('rsa.public_key');
$pub = str_replace(['\\r\\n', '\\n', '\\r'], "\n", trim($pub, "\"' \t"));

$res = openssl_pkey_get_public($pub);
if ($res === false) {
    fwrite(STDERR, "公钥解析失败，请检查 .env 的 RSA_PUBLIC_KEY\n");
    exit(1);
}

$details = openssl_pkey_get_details($res);

// 去掉 PEM 头尾与所有空白，只留裸 base64（JSEncrypt 两种格式都接受）
echo preg_replace('/-----[^-]+-----|\s+/', '', $details['key']);
echo "\n";