<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\controller\Base;
use app\exception\ApiException;

/**
 * 管理后台控制器基类
 *
 * 在用户侧 Base 之上提供：当前管理员、角色权限、写操作落 operation_logs。
 * 角色矩阵（admin_users.role）：1 超管 2 运营 3 财务 4 客服
 * 写操作门槛：role <= 2（超管/运营）；财务与客服只读。
 */
class AdminBase extends Base
{
    protected function aid(): string
    {
        $aid = $this->request->auth_aid ?? null;
        if (empty($aid)) {
            throw ApiException::unauthorized('请先登录管理后台');
        }
        return (string)$aid;
    }

    protected function role(): int
    {
        return (int)($this->request->auth_role ?? 0);
    }

    protected function username(): string
    {
        return (string)($this->request->auth_username ?? '');
    }

    /** 写操作权限（超管/运营），不满足直接 403 语义：用系统错误码+文案 */
    protected function needWriter(): void
    {
        if ($this->role() > 2) {
            throw new ApiException('当前角色无写操作权限（仅超管/运营）', ApiException::CODE_SYSTEM_ERROR);
        }
    }

    /** 超管专属 */
    protected function needSuper(): void
    {
        if ($this->role() !== 1) {
            throw new ApiException('仅超级管理员可执行该操作', ApiException::CODE_SYSTEM_ERROR);
        }
    }

    /** 写操作统一落 operation_logs（吸收文档"审计日志全覆盖"规范） */
    protected function opLog(string $module, string $action, mixed $before = null, mixed $after = null, string $target = ''): void
    {
        try {
            \think\facade\Db::name('operation_logs')->insert([
                'admin_id'    => (int)$this->aid(),
                'module'      => $module,
                'action'      => $action,
                'target'      => $target,
                'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
                'after_data'  => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
                'ip'          => (string)$this->request->ip(),
            ]);
        } catch (\Throwable) {
            // 日志失败不阻断业务
        }
    }

    /** 管理端分页响应（固定 {list,total}，前端是自研表格） */
    protected function adminPage(object $query): array
    {
        $total = (clone $query)->count();
        $rows  = $query->page($this->pageNo(), $this->pageSize(20))->select()->toArray();
        return ['list' => $rows, 'total' => $total];
    }
}
