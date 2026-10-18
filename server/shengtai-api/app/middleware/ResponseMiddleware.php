<?php
declare(strict_types=1);

namespace app\middleware;

use app\exception\ApiException;
use think\Response;

/**
 * 统一响应中间件 —— 严格复刻源站响应包络
 *
 * 源站所有接口（含错误）都返回 HTTP 200 + 固定四字段结构，
 * 前端只用 res.status 判定成败，不用 HTTP 状态码。
 *
 * @see docs/api.md 0.1 响应包络
 */
class ResponseMiddleware
{
    public function handle(\think\Request $request, \Closure $next): Response
    {
        try {
            $data = $next($request);

            // 框架的 dispatchToRoute 会把控制器返回值包成 Response 对象
            // （默认 type=html，data 就是控制器裸返回的数组）。
            // 这里统一取回 data 再包成源站格式，避免 body 为空。
            if ($data instanceof Response) {
                $inner = $data->getData();

                // 异常已被 ExceptionHandle 转成源站包络了，
                // 直接放行，否则会被二次包装成 data 里的一个对象。
                if (self::isEnvelope($inner)) {
                    return $data;
                }

                $data = $inner;
            }

            return json([
                'status'  => true,
                'code'    => ApiException::CODE_SUCCESS,
                'message' => null,
                'data'    => $data,
            ]);
        } catch (ApiException $e) {
            return json([
                'status'  => false,
                'code'    => $e->getBizCode(),
                'message' => $e->getMessage(),
                'data'    => null,
            ]);
        } catch (\think\exception\ValidateException $e) {
            // ThinkPHP 验证器异常 → 10101 参数类型错误（与源站一致）
            return json([
                'status'  => false,
                'code'    => ApiException::CODE_PARAM_TYPE,
                'message' => $e->getMessage(),
                'data'    => null,
            ]);
        } catch (\Throwable $e) {
            // 未预期异常：记录日志，统一返回 20000 系统错误（不泄漏堆栈）
            \think\facade\Log::error('系统异常: ' . $e->getMessage(), [
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return json([
                'status'  => false,
                'code'    => ApiException::CODE_SYSTEM_ERROR,
                'message' => '系统错误',
                'data'    => null,
            ]);
        }
    }

    /**
     * 判断是否已经是源站响应包络
     *
     * 判据：恰好含 status/code/message/data 四个键，
     * 且 status 是 bool。业务数据里出现这四个键的概率极低，
     * 但为了严谨还要求 code 是 int。
     */
    private static function isEnvelope(mixed $data): bool
    {
        return is_array($data)
            && array_key_exists('status', $data)
            && array_key_exists('code', $data)
            && array_key_exists('message', $data)
            && array_key_exists('data', $data)
            && is_bool($data['status'])
            && is_int($data['code']);
    }
}
