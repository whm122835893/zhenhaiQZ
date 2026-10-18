<?php
namespace app;

use app\exception\ApiException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 *
 * ⚠️ 本项目的核心约束：**源站所有接口（含错误）都返回 HTTP 200 +
 * 固定四字段包络 { status, code, message, data }**，
 * 前端只用 res.status 判定成败，不用 HTTP 状态码。
 *
 * 因此这里**不能**用框架默认的 HTML 错误页 ——
 * 否则前端拿到的是一个 HTML 字符串而不是 JSON，解析时直接炸掉。
 * 所以所有异常都在这里转成源站包络。
 *
 * @see app/middleware/ResponseMiddleware.php （正常响应的包络）
 * @see docs/api.md 0.1 响应包络
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     *
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * 把异常渲染成源站响应包络
     */
    public function render($request, Throwable $e): Response
    {
        // 1. 业务异常：直接用其自带的业务码
        if ($e instanceof ApiException) {
            return $this->envelope(false, $e->getBizCode(), $e->getMessage(), null);
        }

        // 2. 验证器异常 → 10101 参数类型错误
        if ($e instanceof ValidateException) {
            return $this->envelope(false, ApiException::CODE_PARAM_TYPE, $e->getMessage(), null);
        }

        // 3. 控制器直接返回的 Response（例如重定向）——放行
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        // 4. 模型/数据未找到 → 10203
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return $this->envelope(false, ApiException::CODE_NOT_FOUND, '未找到', null);
        }

        // 5. HTTP 异常（404/405 等）→ 按状态码映射到源站业务码
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();

            return match ($status) {
                404 => $this->envelope(false, ApiException::CODE_NOT_FOUND, '未找到', null),
                401, 403 => $this->envelope(false, ApiException::CODE_UNAUTHORIZED, '请先登录', null),
                default => $this->envelope(false, ApiException::CODE_SYSTEM_ERROR, '系统错误', null),
            };
        }

        // 6. 未预期异常 → 记录日志，返回 20000 系统错误（不泄漏堆栈）
        \think\facade\Log::error('系统异常: ' . $e->getMessage(), [
            'type'  => get_class($e),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return $this->envelope(false, ApiException::CODE_SYSTEM_ERROR, '系统错误', null);
    }

    /**
     * 构造源站响应包络
     */
    private function envelope(bool $status, int $code, ?string $message, mixed $data): Response
    {
        return json([
            'status'  => $status,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        ]);
    }
}