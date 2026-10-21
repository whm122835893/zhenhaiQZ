<?php
declare(strict_types=1);

namespace app\service;

/**
 * 运营参数配置服务（吸收司南 v2.2 设计决策 #6：参数落库，禁止硬编码）
 *
 * 读取优先级：system_configs 表 > config/shengtai.php 默认值。
 * 进程内静态缓存，同一请求内只查一次表。
 */
final class SystemConfigService
{
    /** @var array<string,string>|null */
    private static ?array $loaded = null;

    private static function load(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        try {
            $rows = \think\facade\Db::name('system_configs')
                ->where('is_deleted', 0)
                ->column('config_value', 'config_key');
        } catch (\Throwable) {
            $rows = []; // 表不存在（未跑迁移）时退回文件默认值
        }

        return self::$loaded = is_array($rows) ? $rows : [];
    }

    /** 读取字符串参数 */
    public static function get(string $key, string $default = ''): string
    {
        $rows = self::load();
        return isset($rows[$key]) && $rows[$key] !== ''
            ? (string)$rows[$key]
            : (string)$default;
    }

    /** 读取整型参数 */
    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, '');
        return $v === '' ? $default : (int)$v;
    }

    /** 读取浮点参数（如费率） */
    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key, '');
        return $v === '' ? $default : (float)$v;
    }

    /** 全量导出（/basic/configs 用，运营参数不含敏感信息） */
    public static function all(): array
    {
        return self::load();
    }
}
