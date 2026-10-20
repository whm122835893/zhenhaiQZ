<?php
declare(strict_types=1);

namespace app\helper;

/**
 * 雪花 ID 生成器 —— 生成与源站格式一致的 19 位 ID
 *
 * 源站全站主键都是 19 位雪花 ID（如 2111082090104668161），
 * 超出 JS Number 安全范围（2^53-1），所以：
 *   1. 库中用 bigint 存
 *   2. 接口返回时必须转字符串
 *
 * 布局（64 bit）：
 *   1 bit 符号位（恒0）| 41 bit 毫秒时间戳 | 10 bit 机器ID | 12 bit 序列号
 */
final class Snowflake
{
    /** 起始时间戳 2024-01-01 00:00:00 UTC（毫秒） */
    private const EPOCH = 1704067200000;

    private const WORKER_BITS = 10;
    private const SEQ_BITS    = 12;

    private const MAX_WORKER = (1 << self::WORKER_BITS) - 1;  // 1023
    private const MAX_SEQ    = (1 << self::SEQ_BITS) - 1;     // 4095

    private int $workerId;
    private int $sequence = 0;
    private int $lastTimestamp = -1;

    public function __construct(int $workerId = 1)
    {
        if ($workerId < 0 || $workerId > self::MAX_WORKER) {
            throw new \InvalidArgumentException('workerId 必须在 0~' . self::MAX_WORKER . ' 之间');
        }
        $this->workerId = $workerId;
    }

    /** 生成下一个 ID（int，PHP 64 位下可安全持有） */
    public function nextId(): int
    {
        $timestamp = $this->currentMillis();

        if ($timestamp < $this->lastTimestamp) {
            // 时钟回拨：等待追平，避免生成重复 ID
            $timestamp = $this->waitUntil($this->lastTimestamp);
        }

        if ($timestamp === $this->lastTimestamp) {
            $this->sequence = ($this->sequence + 1) & self::MAX_SEQ;
            if ($this->sequence === 0) {
                $timestamp = $this->waitUntil($this->lastTimestamp + 1);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        return (($timestamp - self::EPOCH) << (self::WORKER_BITS + self::SEQ_BITS))
            | ($this->workerId << self::SEQ_BITS)
            | $this->sequence;
    }

    /**
     * 生成字符串形式的 ID —— 接口层一律用它，避免 JSON 序列化丢精度
     *
     * ⚠️ 必须复用静态单例，不能每次 new
     *
     * 序列号与 lastTimestamp 是**实例状态**。若每次调用都 new 一个新实例，
     * 则同一毫秒内每次的 sequence 都从 0 开始、lastTimestamp 都是 -1，
     * 生成出的 ID 完全相同。实测（2026-10-20）：连调 5 次 nextIdStr() 只得到
     * 1 个唯一 ID，主键冲突 Duplicate entry '...' for key 'PRIMARY'。
     *
     * 改为静态单例后：同毫秒内 sequence 递增，不同毫秒重置，
     * 既保证唯一又不阻塞等待。
     */
    public static function nextIdStr(int $workerId = 1): string
    {
        static $instances = [];
        if (!isset($instances[$workerId])) {
            $instances[$workerId] = new self($workerId);
        }
        return (string)$instances[$workerId]->nextId();
    }

    private function currentMillis(): int
    {
        return (int)floor(microtime(true) * 1000);
    }

    private function waitUntil(int $target): int
    {
        while ($this->currentMillis() < $target) {
            usleep(200);
        }
        return $this->currentMillis();
    }
}
