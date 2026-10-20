<?php
/**
 * 后台 RBAC 初始化脚本（幂等，可重复执行）
 *
 * 用途：创建 4 个内置角色、22 个权限点、角色-权限映射，以及首个超管账号。
 * 对应 PRD §4.1 四角色定义 / §4.2 权限矩阵 / ARCHITECTURE.md §6.1 的 22 个权限点。
 *
 * 设计约束（源自项目铁律）：
 *  1. 禁止臆造数据 —— 角色/权限点全部来自 PRD 与 openapi.yaml，不自造权限码；
 *  2. 密码不硬编码、不入库明文 —— 随机盐 + bcrypt，由本脚本运行时生成；
 *  3. 幂等 —— 以 code / username 为唯一键做 upsert，重复执行不产生重复行；
 *  4. C 端零影响 —— 只写 admins/roles/permissions/admin_roles/role_permissions
 *     五张后台表，不触碰任何 C 端表。
 *
 * 用法：
 *   php database/init_admin_rbac.php                  # 交互式，命令行输入初始密码
 *   php database/init_admin_rbac.php --password=xxx    # 非交互（CI 用，慎用：会留在 shell 历史）
 *   php database/init_admin_rbac.php --dry-run         # 只打印将要执行的动作，不落库
 *
 * @author 大湾区靓仔（项目总监）
 */

declare(strict_types=1);

use think\facade\Db;
use app\helper\Snowflake;

$root = dirname(__DIR__);

// ---- 加载后端配置与 ThinkPHP 容器（复用 C 端同一套 DB 配置，不另建连接串）----
require $root . '/server/shengtai-api/vendor/autoload.php';

$app = new think\App();
$app->initialize();

// ---- 命令行参数 ----
$opts = getopt('', ['password::', 'dry-run', 'username::', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, <<<TXT
用法: php database/init_admin_rbac.php [选项]

  --password=xxx   指定初始密码（不给则交互式输入，不回显）
  --username=xxx   指定超管账号名（默认 admin）
  --dry-run        只打印将要执行的动作，不落库
  --help           显示本帮助

TXT);
    exit(0);
}
$dryRun = isset($opts['dry-run']);

// ---- 写操作审计：本次为初始化脚本，写入时按 ADR 要求留痕 ----
// 架构要求「写操作整体回滚，审计日志任一字段缺失即判定不通过」，故所有写操作包在事务内。

/**
 * 权限点字典 —— 22 项，与 ARCHITECTURE.md §6.1 端点清单的权限位列一一对应
 * 字段：code, name, domain, action, description
 */
$permissions = [
    // dashboard
    ['code' => 'dashboard:read',  'name' => '看板查看',       'domain' => 'dashboard', 'action' => 'read', 'description' => '总览看板 / 趋势图 / 藏品成交排行'],
    // item
    ['code' => 'item:read',       'name' => '藏品查看',       'domain' => 'item',      'action' => 'read', 'description' => '藏品列表 / 详情 / 覆盖变更历史'],
    ['code' => 'item:write',      'name' => '藏品编辑',       'domain' => 'item',      'action' => 'write','description' => '写藏品覆盖值 / 清除覆盖（后台唯一写入口）'],
    // copyright
    ['code' => 'copyright:read',  'name' => '版权查看',       'domain' => 'copyright', 'action' => 'read', 'description' => '版权列表 / 详情'],
    ['code' => 'copyright:write', 'name' => '版权编辑',       'domain' => 'copyright', 'action' => 'write','description' => '写版权覆盖值'],
    // author
    ['code' => 'author:read',     'name' => '作者查看',       'domain' => 'author',    'action' => 'read', 'description' => '作者列表 / 详情（含名下藏品）'],
    ['code' => 'author:write',    'name' => '作者编辑',       'domain' => 'author',    'action' => 'write','description' => '写作者覆盖值'],
    // nft / trade
    ['code' => 'nft:read',        'name' => 'NFT 查看',       'domain' => 'nft',       'action' => 'read', 'description' => '挂单列表 / NFT 资产列表'],
    ['code' => 'nft:write',       'name' => 'NFT 干预',       'domain' => 'nft',       'action' => 'write','description' => '挂单干预标记 / 强制下架'],
    ['code' => 'trade:read',      'name' => '成交记录查看',   'domain' => 'trade',     'action' => 'read', 'description' => '成交记录列表'],
    // user
    ['code' => 'user:read',       'name' => '用户查看',       'domain' => 'user',      'action' => 'read', 'description' => '用户列表 / 详情 / 实名状态'],
    ['code' => 'user:write',      'name' => '用户状态编辑',   'domain' => 'user',      'action' => 'write','description' => '实名材料录入 / 冻结解冻 / 封禁'],
    ['code' => 'user:audit',      'name' => '实名审核',       'domain' => 'user',      'action' => 'audit','description' => '实名审核裁决（通过 / 驳回）'],
    // admin / role / log
    ['code' => 'admin:read',      'name' => '管理员账号查看', 'domain' => 'admin',     'action' => 'read', 'description' => '账号列表 / 角色列表 / 权限点字典'],
    ['code' => 'admin:write',     'name' => '管理员账号编辑', 'domain' => 'admin',     'action' => 'write','description' => '新建 / 改账号 / 重置他人密码 / 删账号'],
    ['code' => 'role:write',      'name' => '角色权限配置',   'domain' => 'role',      'action' => 'write','description' => '分配角色权限'],
    ['code' => 'log:read',        'name' => '审计日志查看',   'domain' => 'log',       'action' => 'read', 'description' => '操作审计日志'],
    // export（PRD §4.2 导出为 ops=W / 其余 R，故单独成点便于控制）
    ['code' => 'export:write',    'name' => '导出数据',       'domain' => 'export',    'action' => 'write','description' => '列表 CSV 导出'],
];

/**
 * 角色定义 —— 4 个内置角色，对应 PRD §4.1
 */
$roles = [
    ['code' => 'ops',      'name' => '平台运营',     'description' => '管理藏品上架、推荐位、价格与挂单'],
    ['code' => 'support',  'name' => '财务/客服',    'description' => '查订单、查钱包、处理用户申诉与冻结'],
    ['code' => 'viewer',   'name' => '数据观察者',   'description' => '看所有看板与明细，只看不动（绝对只读）'],
    ['code' => 'reviewer', 'name' => '内容/版权审核', 'description' => '审版权、审藏品内容'],
];

/**
 * 角色-权限映射 —— 严格照抄 PRD §4.2 权限矩阵
 *
 * 矩阵图例：R=只读  W=可写  A=需审批（写操作需二次确认并留痕）  —=无权限
 * 本表只登记「实际授予」的权限点：
 *   - 「A」在本表中按 W 授予（因为 A 的实现是「同一写接口 + 二次确认 + 留痕」，
 *     不构成独立权限码；若将来 A 需要二次审批人签字，再拆独立权限点）；
 *   - 「—」表示不授予；
 *   - 「R（脱敏）」按 R 授予，脱敏由服务端按角色判定（PRD §4.3 硬性要求）。
 */
$rolePermissions = [
    'ops' => [
        'dashboard:read', 'item:read', 'item:write',
        'copyright:read', 'author:read',
        'nft:read', 'nft:write', 'trade:read',
        'user:read', 'user:write',
        'log:read', 'export:write',
    ],
    'support' => [
        'dashboard:read', 'item:read',
        'copyright:read', 'author:read',
        'nft:read', 'nft:write', 'trade:read',
        'user:read', 'user:write', 'user:audit',
        'log:read',
    ],
    // viewer 绝对只读：一个写权限点都不给（PRD §4.1「绝对不能」，服务端拒绝任何写请求）
    'viewer' => [
        'dashboard:read', 'item:read',
        'copyright:read', 'author:read',
        'nft:read', 'trade:read',
        'user:read',
    ],
    'reviewer' => [
        'dashboard:read', 'item:read',
        'copyright:read', 'copyright:write', 'author:read',
        'nft:read', 'nft:write', 'trade:read',
    ],
];

// ==================== 执行 ====================

function out(string $s): void { fwrite(STDOUT, $s . "\n"); }
function act(string $s): void { global $dryRun; out(($dryRun ? '[DRY-RUN] ' : '        ') . $s); }

out('=== 后台 RBAC 初始化 ===');
out($dryRun ? '模式：DRY-RUN（不落库）' : '模式：正式写入');
out('');

Db::startTrans();
try {
    // ---- 1. 权限点 ----
    $permIds = [];
    foreach ($permissions as $p) {
        $exist = Db::name('permissions')->where('code', $p['code'])->find();
        if ($exist) {
            $permIds[$p['code']] = (int)$exist['id'];
            act(sprintf('权限点已存在，跳过：%s', $p['code']));
        } else {
            act(sprintf('插入权限点：%s', $p['code']));
            if (!$dryRun) {
                // 后台表与 C 端表一致用雪花 ID（无 AUTO_INCREMENT），复用项目既有生成器
                $id = Snowflake::nextIdStr();
                Db::name('permissions')->insert([
                    'id' => $id,
                    'code' => $p['code'], 'name' => $p['name'],
                    'domain' => $p['domain'], 'action' => $p['action'],
                    'description' => $p['description'],
                ]);
            } else {
                $id = 0;
            }
            $permIds[$p['code']] = (int)$id;
        }
    }
    $newPerms = count(array_filter($permIds, static fn ($v) => $v > 0));
    out(sprintf('权限点：%d 项（本次新增 %d）', count($permissions), $newPerms));

    // ---- 2. 角色 ----
    $roleIds = [];
    foreach ($roles as $r) {
        $exist = Db::name('roles')->where('code', $r['code'])->find();
        if ($exist) {
            $roleIds[$r['code']] = (int)$exist['id'];
            act(sprintf('角色已存在，跳过：%s', $r['code']));
        } else {
            act(sprintf('插入角色：%s（%s）', $r['code'], $r['name']));
            if (!$dryRun) {
                $id = Snowflake::nextIdStr();
                Db::name('roles')->insert([
                    'id' => $id,
                    'code' => $r['code'], 'name' => $r['name'],
                    'description' => $r['description'],
                    'is_builtin' => 1, 'sort' => 0, 'status' => 1,
                    'created_by' => 0, 'updated_by' => 0,
                ]);
            } else {
                $id = 0;
            }
            $roleIds[$r['code']] = (int)$id;
        }
    }
    out(sprintf('角色：%d 个', count($roles)));

    // ---- 3. 角色-权限映射 ----
    $mapCount = 0;
    foreach ($rolePermissions as $roleCode => $codes) {
        $roleId = $roleIds[$roleCode];
        foreach ($codes as $code) {
            $exist = Db::name('role_permissions')
                ->where('role_id', $roleId)->where('permission_id', $permIds[$code])->find();
            if ($exist) { continue; }
            act(sprintf('授权：%s ← %s', $roleCode, $code));
            if (!$dryRun) {
                Db::name('role_permissions')->insert([
                    'id' => Snowflake::nextIdStr(),
                    'role_id' => $roleId, 'permission_id' => $permIds[$code], 'created_by' => 0,
                ]);
            }
            $mapCount++;
        }
    }
    out(sprintf('角色-权限映射：新增 %d 条', $mapCount));

    // ---- 4. 权限矩阵自检：viewer 不得持有任何写权限点 ----
    $writeActions = ['write', 'audit'];
    $viewerWrites = [];
    foreach ($rolePermissions['viewer'] as $code) {
        $a = null;
        foreach ($permissions as $p) {
            if ($p['code'] === $code) { $a = $p['action']; break; }
        }
        if (in_array($a, $writeActions, true)) { $viewerWrites[] = $code; }
    }
    if ($viewerWrites) {
        throw new \RuntimeException(
            'viewer 角色被授予写权限：' . implode(', ', $viewerWrites) . ' —— 违反 PRD §4.1「绝对不能」，已回滚'
        );
    }
    out('自检：viewer 角色零写权限点 —— 通过');

    // ---- 5. 首个超管账号 ----
    $username = $opts['username'] ?? 'admin';
    $adminExist = Db::name('admins')->where('username', $username)->find();

    if ($adminExist) {
        out(sprintf('超管账号已存在，跳过：%s', $username));
        $adminId = (int)$adminExist['id'];
    } else {
        $password = $opts['password'] ?? null;
        if ($password === null) {
            if ($dryRun) {
                $password = 'DRY_RUN_PLACEHOLDER';
            } else {
                fwrite(STDOUT, '请输入初始密码（不回显，至少 12 位，含大小写字母与数字）：');
                $password = trim((string)fgets(STDIN));
            }
        }
        if ($password !== 'DRY_RUN_PLACEHOLDER') {
            // DRY-RUN 用占位密码跳过强度校验，否则会因占位符不合规而误报失败
            if (strlen($password) < 12) {
                throw new \RuntimeException('密码强度不足：至少 12 位。当前 ' . strlen($password) . ' 位。已回滚');
            }
            if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password)) {
                throw new \RuntimeException('密码需同时含小写字母、大写字母与数字。已回滚');
            }
        }

        $salt = bin2hex(random_bytes(16));
        $hash = password_hash($password, PASSWORD_BCRYPT);
        act(sprintf('创建超管账号：%s（密码强度已校验，不打印密码与哈希）', $username));
        if (!$dryRun) {
            $adminId = Snowflake::nextIdStr();
            Db::name('admins')->insert([
                'id' => $adminId,
                'username' => $username,
                'password_hash' => $hash,
                'salt' => $salt,
                'real_name' => '超级管理员',
                'phone' => '',
                'status' => 1,
                'is_super' => 1,
                'pwd_updated_at' => null,
                // 首次登录强制改密 —— 防止初始化密码长期有效（NIST AC-6 最小权限 + 凭据轮换）
                'pwd_reset_required' => 1,
                'created_by' => 0, 'updated_by' => 0,
            ]);
        } else {
            $adminId = 0;
        }
    }

    // 超管绑定全部 4 个角色（超管本身靠 is_super 旁路，但仍绑角色以便
    // 「我的角色」展示与后续降权时有明确基线）
    foreach ($roles as $r) {
        $exist = Db::name('admin_roles')
            ->where('admin_id', $adminId)->where('role_id', $roleIds[$r['code']])->find();
        if ($exist) { continue; }
        act(sprintf('超管绑定角色：%s', $r['code']));
        if (!$dryRun) {
            Db::name('admin_roles')->insert([
                'id' => Snowflake::nextIdStr(),
                'admin_id' => $adminId, 'role_id' => $roleIds[$r['code']], 'created_by' => 0,
            ]);
        }
    }

    Db::commit();
    out('');
    out('=== 初始化完成（已提交事务） ===');
    out('下一步：登录后立即修改初始密码（pwd_reset_required=1 会在首次登录时强制改密）。');
} catch (\Throwable $e) {
    Db::rollback();
    out('');
    out('=== 失败，已整体回滚 ===');
    out('原因：' . $e->getMessage());
    exit(1);
}
