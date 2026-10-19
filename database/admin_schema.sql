-- ============================================================================
-- 臻海艺术 · 运营后台管理 —— 新增表 DDL
--
-- 约束（不可违反）：
--   1. 本脚本**只新增表**，不 ALTER、不 DROP、不修改现有 48 张表的任何一列
--   2. C 端 93 个端点的数据来源一字不改
--   3. 现有 48 张表的 DDL 一律不动
--
-- 环境：MariaDB 10.11.14 / utf8mb4_general_ci
-- 风格：与既有 48 张表一致（bigint unsigned 雪花主键、datetime 审计列、
--       is_deleted 软删除（仅对可删除的业务表）、COMMENT 全列注释）
--
-- 执行方式：
--   mysql -h127.0.0.1 -ushengtai -p shengtai < admin_schema.sql
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. admins —— 后台账号
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admins`;
CREATE TABLE `admins` (
  `id`                bigint unsigned   NOT NULL COMMENT '雪花ID',
  `username`          varchar(64)       NOT NULL DEFAULT '' COMMENT '登录名',
  `password_hash`     varchar(255)      NOT NULL DEFAULT '' COMMENT '密码散列(PHP password_hash)',
  `salt`              varchar(32)       NOT NULL DEFAULT '' COMMENT '密码盐(独立于C端users.salt)',
  `real_name`         varchar(64)       NOT NULL DEFAULT '' COMMENT '管理员姓名',
  `phone`             varchar(20)       NOT NULL DEFAULT '' COMMENT '联系电话',
  `status`            tinyint(1)        NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `is_super`          tinyint(1)        NOT NULL DEFAULT 0 COMMENT '1=超级管理员(拥有全部权限,不可删除)',
  `last_login_at`     bigint            NULL DEFAULT NULL COMMENT '最后登录时间(13位毫秒)',
  `last_login_ip`     varchar(45)       NOT NULL DEFAULT '' COMMENT '最后登录IP(兼容IPv6)',
  `pwd_updated_at`    bigint            NULL DEFAULT NULL COMMENT '密码最后修改时间(13位毫秒)',
  `pwd_reset_required` tinyint(1)       NOT NULL DEFAULT 0 COMMENT '1=下次登录必须改密',
  `created_by`        bigint unsigned   NOT NULL DEFAULT 0 COMMENT '创建者admin_id',
  `updated_by`        bigint unsigned   NOT NULL DEFAULT 0 COMMENT '最后修改者admin_id',
  `created_at`        datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`        tinyint(1)        NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admins_username` (`username`),
  KEY `idx_admins_status` (`status`,`is_deleted`),
  KEY `idx_admins_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台账号';

-- ---------------------------------------------------------------------------
-- 2. roles —— 角色
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id`          bigint unsigned NOT NULL COMMENT '雪花ID',
  `code`        varchar(64)     NOT NULL DEFAULT '' COMMENT '角色编码: ops/finance/viewer/copyright',
  `name`        varchar(64)     NOT NULL DEFAULT '' COMMENT '角色名: 平台运营/财务客服/数据观察者/内容版权审核',
  `description` varchar(255)    NOT NULL DEFAULT '' COMMENT '职责描述',
  `is_builtin`  tinyint(1)      NOT NULL DEFAULT 0 COMMENT '1=内置角色(不可删除,仅可改权限)',
  `sort`        int             NOT NULL DEFAULT 0 COMMENT '排序权重',
  `status`      tinyint(1)      NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `created_by`  bigint unsigned NOT NULL DEFAULT 0,
  `updated_by`  bigint unsigned NOT NULL DEFAULT 0,
  `created_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`  tinyint(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_code` (`code`),
  KEY `idx_roles_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台角色';

-- ---------------------------------------------------------------------------
-- 3. permissions —— 权限点
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id`          bigint unsigned NOT NULL COMMENT '雪花ID',
  `code`        varchar(64)     NOT NULL DEFAULT '' COMMENT '权限码,格式 {domain}:{action}',
  `name`        varchar(64)     NOT NULL DEFAULT '' COMMENT '权限名',
  `domain`      varchar(32)     NOT NULL DEFAULT '' COMMENT '业务域: item/nft/user/admin/...',
  `action`      varchar(32)     NOT NULL DEFAULT '' COMMENT '动作: read/write/audit/reset/assign',
  `description` varchar(255)    NOT NULL DEFAULT '',
  `created_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_code` (`code`),
  KEY `idx_permissions_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台权限点';

-- ---------------------------------------------------------------------------
-- 4. admin_roles —— 账号与角色关联
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_roles`;
CREATE TABLE `admin_roles` (
  `id`         bigint unsigned NOT NULL COMMENT '雪花ID',
  `admin_id`   bigint unsigned NOT NULL COMMENT 'admin_id',
  `role_id`    bigint unsigned NOT NULL COMMENT 'role_id',
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_roles` (`admin_id`,`role_id`),
  KEY `idx_admin_roles_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='账号角色关联';

-- ---------------------------------------------------------------------------
-- 5. role_permissions —— 角色与权限点关联
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id`            bigint unsigned NOT NULL COMMENT '雪花ID',
  `role_id`       bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_by`    bigint unsigned NOT NULL DEFAULT 0,
  `created_at`    datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permissions` (`role_id`,`permission_id`),
  KEY `idx_role_permissions_perm` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='角色权限关联';

-- ---------------------------------------------------------------------------
-- 6. admin_sessions —— 后台会话（实现「登出即失效」）
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_sessions`;
CREATE TABLE `admin_sessions` (
  `id`           bigint unsigned NOT NULL COMMENT '雪花ID',
  `admin_id`     bigint unsigned NOT NULL,
  `jti`          char(32)        NOT NULL DEFAULT '' COMMENT 'JWT payload 中的会话ID(32位hex)',
  `token_hash`   char(64)        NOT NULL DEFAULT '' COMMENT 'access token 的 sha256,防token落库被复用',
  `login_ip`     varchar(45)     NOT NULL DEFAULT '',
  `user_agent`   varchar(512)    NOT NULL DEFAULT '',
  `status`       tinyint(1)      NOT NULL DEFAULT 1 COMMENT '1=有效 0=已登出/已被顶号',
  `revoke_reason` varchar(64)    NOT NULL DEFAULT '' COMMENT '登出原因: signout/new_login/admin_kick/password_changed',
  `revoked_at`   bigint          NULL DEFAULT NULL COMMENT '失效时间(13位毫秒)',
  `expires_at`   bigint          NOT NULL DEFAULT 0 COMMENT '会话绝对过期(13位毫秒)',
  `created_at`   datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sessions_jti` (`jti`),
  KEY `idx_sessions_admin_status` (`admin_id`,`status`),
  KEY `idx_sessions_expires` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台会话(ADR-003 登出即失效)';

-- ---------------------------------------------------------------------------
-- 7. admin_operation_logs —— 操作审计
--    注意：本表不存 code（最终 code 由 ResponseMiddleware 在更外层封装，
--    审计中间件拿不到）。改存 is_success + error_code，中间件自行 catch
--    ApiException::getBizCode() 取得。详见 ADR-009。
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_operation_logs`;
CREATE TABLE `admin_operation_logs` (
  `id`             bigint unsigned NOT NULL COMMENT '雪花ID(应用生成,覆盖表需反向引用)',
  `admin_id`       bigint unsigned NOT NULL DEFAULT 0 COMMENT '0=未登录(如登录失败)',
  `admin_username` varchar(64)     NOT NULL DEFAULT '' COMMENT '账号名快照(账号被删后仍可追溯)',
  `module`         varchar(32)     NOT NULL DEFAULT '' COMMENT '业务模块: auth/item/copyright/author/nft/user/admin/role',
  `action`         varchar(32)     NOT NULL DEFAULT '' COMMENT '动作: signin/write/delete/audit/reset/assign',
  `method`         varchar(8)      NOT NULL DEFAULT '' COMMENT 'HTTP方法',
  `path`           varchar(255)    NOT NULL DEFAULT '' COMMENT '请求路径(不含query string)',
  `request_params` mediumtext      NULL COMMENT '请求体JSON快照(密码类字段已脱敏,超8KB截断)',
  `target_type`    varchar(32)     NOT NULL DEFAULT '' COMMENT '被操作对象类型: item/copyright/author/user/admin',
  `target_id`      varchar(64)     NOT NULL DEFAULT '' COMMENT '被操作对象ID(雪花ID转字符串)',
  `is_success`     tinyint(1)      NOT NULL DEFAULT 1 COMMENT '1=成功 0=失败',
  `error_code`     int             NOT NULL DEFAULT 0 COMMENT '失败时的业务码(ApiException::getBizCode)',
  `error_message`  varchar(512)    NOT NULL DEFAULT '' COMMENT '失败原因',
  `ip`             varchar(45)     NOT NULL DEFAULT '',
  `user_agent`     varchar(512)    NOT NULL DEFAULT '',
  `duration_ms`    int             NOT NULL DEFAULT 0 COMMENT '耗时(毫秒)',
  `created_at`     datetime(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT '毫秒精度',
  PRIMARY KEY (`id`),
  KEY `idx_logs_admin_time` (`admin_id`,`created_at`),
  KEY `idx_logs_module_time` (`module`,`created_at`),
  KEY `idx_logs_target` (`target_type`,`target_id`),
  KEY `idx_logs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台操作审计日志';

-- ============================================================================
-- 以下为「与 C 端隔离」的覆盖表（ADR-007）
--
-- 结构：字段级覆盖，一行 = 一个字段的覆盖值
--   is_local_override 恒为 1（存在即覆盖），不做 0/1 切换 —— 避免
--   「字段存在但值为 0」的中间态，那是最容易产生静默逻辑错误的地方
--   uk (target_id, field_key) 唯一索引 = 幂等键，重复提交走 ON DUPLICATE KEY UPDATE
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 8. admin_item_overrides —— items 表的后台覆盖
--    可覆盖字段：status / is_recommend / is_popular / price /
--                is_secondary / is_secondary_batch
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_item_overrides`;
CREATE TABLE `admin_item_overrides` (
  `id`                bigint unsigned NOT NULL COMMENT '雪花ID',
  `target_id`         bigint unsigned NOT NULL COMMENT 'items.id',
  `field_key`         varchar(64)     NOT NULL COMMENT '被覆盖字段名',
  `field_value`       varchar(512)    NOT NULL DEFAULT '' COMMENT '覆盖值(统一字符串,按field_key转型)',
  `is_local_override` tinyint(1)      NOT NULL DEFAULT 1 COMMENT '恒为1:后台运营写入的本地覆盖值',
  `admin_id`          bigint unsigned NOT NULL DEFAULT 0 COMMENT '最后写入的admin',
  `op_log_id`         bigint unsigned NOT NULL DEFAULT 0 COMMENT '关联admin_operation_logs.id',
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_item_override` (`target_id`,`field_key`),
  KEY `idx_item_override_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='items后台覆盖值(C端不读)';

-- ---------------------------------------------------------------------------
-- 9. admin_copyright_overrides —— item_copyrights 表的后台覆盖
--    可覆盖字段：name / image / image_url / sort
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_copyright_overrides`;
CREATE TABLE `admin_copyright_overrides` (
  `id`                bigint unsigned NOT NULL COMMENT '雪花ID',
  `target_id`         bigint unsigned NOT NULL COMMENT 'item_copyrights.id',
  `field_key`         varchar(64)     NOT NULL,
  `field_value`       varchar(512)    NOT NULL DEFAULT '',
  `is_local_override` tinyint(1)      NOT NULL DEFAULT 1,
  `admin_id`          bigint unsigned NOT NULL DEFAULT 0,
  `op_log_id`         bigint unsigned NOT NULL DEFAULT 0,
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_copyright_override` (`target_id`,`field_key`),
  KEY `idx_copyright_override_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='item_copyrights后台覆盖值(C端不读)';

-- ---------------------------------------------------------------------------
-- 10. admin_author_overrides —— item_authors 表的后台覆盖
--     可覆盖字段：name / avatar / avatar_url / introduction
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_author_overrides`;
CREATE TABLE `admin_author_overrides` (
  `id`                bigint unsigned NOT NULL COMMENT '雪花ID',
  `target_id`         bigint unsigned NOT NULL COMMENT 'item_authors.id',
  `field_key`         varchar(64)     NOT NULL,
  `field_value`       varchar(1024)   NOT NULL DEFAULT '' COMMENT 'introduction较长,单独放宽',
  `is_local_override` tinyint(1)      NOT NULL DEFAULT 1,
  `admin_id`          bigint unsigned NOT NULL DEFAULT 0,
  `op_log_id`         bigint unsigned NOT NULL DEFAULT 0,
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_author_override` (`target_id`,`field_key`),
  KEY `idx_author_override_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='item_authors后台覆盖值(C端不读)';

-- ---------------------------------------------------------------------------
-- 11. admin_sell_order_overrides —— nft_sell_orders 的后台干预标记
--     只写 admin_status，**不改** nft_sell_orders.status
--     （后台「标记异常挂单」与 C 端用户「自行下架」是两件事，不可混同）
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `admin_sell_order_overrides`;
CREATE TABLE `admin_sell_order_overrides` (
  `id`                bigint unsigned NOT NULL COMMENT '雪花ID',
  `target_id`         bigint unsigned NOT NULL COMMENT 'nft_sell_orders.id',
  `field_key`         varchar(64)     NOT NULL COMMENT 'MVP仅 admin_status',
  `field_value`       varchar(512)    NOT NULL DEFAULT '' COMMENT 'normal/flagged/blocked',
  `is_local_override` tinyint(1)      NOT NULL DEFAULT 1,
  `admin_id`          bigint unsigned NOT NULL DEFAULT 0,
  `op_log_id`         bigint unsigned NOT NULL DEFAULT 0,
  `remark`            varchar(512)    NOT NULL DEFAULT '' COMMENT '干预原因(flagged/blocked时必填)',
  `created_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sell_override` (`target_id`,`field_key`),
  KEY `idx_sell_override_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='nft_sell_orders后台干预标记(C端不读)';