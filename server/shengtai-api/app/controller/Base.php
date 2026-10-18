<?php
declare(strict_types=1);

namespace app\controller;

use app\exception\ApiException;
use think\App;

/**
 * 控制器基类
 *
 * 提供：当前登录 uid、分页参数、毫秒时间戳、分页响应等通用能力。
 */
abstract class Base
{
    protected App $app;
    protected \think\Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $app->request;
    }

    /** 当前登录用户 ID（AuthMiddleware 已保证非空，这里做二次防御） */
    protected function uid(): string
    {
        $uid = $this->request->auth_uid ?? null;
        if (empty($uid)) {
            throw ApiException::unauthorized();
        }
        return (string)$uid;
    }

    /* ---------- 参数读取（缺失即抛源站式错误） ---------- */

    /**
     * 必填参数
     *
     * 源站参数缺失时 message 形如「iidmust not be null」——
     * 字段名直接拼在 message 里，没有空格。前端直接 toast，不能改格式。
     */
    protected function need(string $name, ?string $default = null): mixed
    {
        $v = $this->request->param($name, $default);
        if ($v === null || $v === '') {
            throw ApiException::missing($name);
        }
        return $v;
    }

    protected function needInt(string $name): int
    {
        $v = $this->need($name);
        if (!is_numeric($v)) {
            throw ApiException::paramType($name . 'must be number');
        }
        return (int)$v;
    }

    protected function str(string $name, string $default = ''): string
    {
        return (string)$this->request->param($name, $default);
    }

    protected function int(string $name, int $default = 0): int
    {
        $v = $this->request->param($name, $default);
        return is_numeric($v) ? (int)$v : $default;
    }

    protected function bool(string $name, bool $default = false): bool
    {
        $v = $this->request->param($name, null);
        if ($v === null) {
            return $default;
        }
        return filter_var($v, FILTER_VALIDATE_BOOLEAN);
    }

    /** JSON body 读取（POST 场景） */
    protected function body(): array
    {
        $raw = $this->request->getContent();
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function bodyNeed(string $name): mixed
    {
        $d = $this->body();
        if (!isset($d[$name]) || $d[$name] === '' || $d[$name] === null) {
            throw ApiException::missing($name);
        }
        return $d[$name];
    }

    /* ---------- 分页 ---------- */

    protected function pageNo(): int
    {
        return max(1, $this->int('pageNo', 1));
    }

    protected function pageSize(int $default = 20): int
    {
        return min(100, max(1, $this->int('pageSize', $default)));
    }

    /**
     * 分页响应体
     *
     * 源站多数列表接口直接返回数组（不带 total），
     * 部分接口返回 {list, total}。两形态都已实测确认，由调用方决定。
     */
    /**
     * 分页列表响应 —— **默认返回裸数组**
     *
     * ⚠️ 源站的分页没有统一写法，实测结论（scripts/probe_source_shapes.sh）：
     *   绝大多数端点返回**裸数组**（前端直接 res.data.map() / 展开），
     *   只有极少数（如 GET /user/invite）返回 {list, total}。
     *
     *   本项目前端是照源站写的，所以这里默认裸数组，
     *   需要 {list,total} 的端点显式传 true。
     *
     * 历史坑：本方法原先一律返回 {list,total}，切到本地后端后
     * 公告页直接报 "(...).map is not a function" —— 
     * 前端拿到对象却当数组用，整页白屏。
     *
     * @param bool $withTotal true = 返回 {list,total}；false = 返回裸数组
     */
    protected function pageList(array $rows, int $total = 0, bool $withTotal = false): array
    {
        if ($withTotal) {
            return [
                'list'  => $rows,
                'total' => $total,
            ];
        }

        return $rows;
    }

    /* ---------- 数据类型转换（严格对齐源站） ---------- */

    /**
     * 雪花 ID → 字符串
     *
     * 源站所有 ID 都以字符串返回。PHP 的 int 在 JSON 序列化时可能
     * 变成 float 或科学计数法，必须显式转字符串。
     */
    protected function idStr(int|string|null $id): string
    {
        return (string)$id;
    }

    /**
     * 13 位毫秒时间戳 → 字符串
     *
     * 源站返回 "1791959018537"（字符串形式），不是数字。
     * 前端拿它做时间格式化，如果传数字会有一致性隐患。
     */
    protected function ts(int|float|null $millis): string
    {
        return (string)(int)$millis;
    }

    /** 当前毫秒时间戳（int，存库用） */
    protected function nowMillis(): int
    {
        return (int)floor(microtime(true) * 1000);
    }

    /**
     * 补全图片双字段
     *
     * 源站所有图片都成对返回 image（相对路径）+ imageUrl（绝对地址）。
     */
    protected function withImages(array $row, string $imageBase = ''): array
    {
        if (isset($row['image']) && $imageBase !== '' && $row['image'] !== '') {
            $row['imageUrl'] = $imageBase . $row['image'];
        }
        return $row;
    }
}
