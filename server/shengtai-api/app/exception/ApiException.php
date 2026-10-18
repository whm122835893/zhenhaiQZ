<?php
declare(strict_types=1);

namespace app\exception;

use think\Exception;

/**
 * 业务异常 —— 由 ResponseMiddleware 捕获并转成源站响应包络
 *
 * 源站前端只用 res.status 判定成败，HTTP 状态码一律 200，
 * 所以这里不继承任何 HTTP 异常基类，只带业务码。
 */
class ApiException extends Exception
{
    /** 源站业务码 */
    public const CODE_SUCCESS        = 0;
    public const CODE_PARAM_MISSING  = 10100;   // 参数缺失
    public const CODE_PARAM_TYPE     = 10101;   // 参数类型错误
    public const CODE_NOT_FOUND      = 10203;   // 未找到
    public const CODE_UNAUTHORIZED   = 11111;   // 请先登录
    public const CODE_SYSTEM_ERROR   = 20000;   // 系统错误

    protected int $bizCode;

    public function __construct(string $message = '', int $bizCode = self::CODE_SYSTEM_ERROR, ?\Throwable $previous = null)
    {
        $this->bizCode = $bizCode;
        parent::__construct($message, 0, $previous);
    }

    public function getBizCode(): int
    {
        return $this->bizCode;
    }

    /* ---------- 快捷构造 ---------- */

    public static function unauthorized(string $msg = '请先登录'): self
    {
        return new self($msg, self::CODE_UNAUTHORIZED);
    }

    public static function notFound(string $msg = '未找到'): self
    {
        return new self($msg, self::CODE_NOT_FOUND);
    }

    public static function paramMissing(string $msg = '参数缺失'): self
    {
        return new self($msg, self::CODE_PARAM_MISSING);
    }

    public static function paramType(string $msg = '参数类型错误'): self
    {
        return new self($msg, self::CODE_PARAM_TYPE);
    }

    public static function system(string $msg = '系统错误'): self
    {
        return new self($msg, self::CODE_SYSTEM_ERROR);
    }

    /**
     * 源站参数校验失败时 message 形如「iidmust not be null」（字段名 + must not be null），
     * 注意源站**没有空格**，前端直接 toast。这里保持一致。
     */
    public static function missing(string $field): self
    {
        return new self($field . 'must not be null', self::CODE_PARAM_MISSING);
    }
}
