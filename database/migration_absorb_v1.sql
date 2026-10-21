-- =====================================================================
-- 吸收《司南数据库文档 v2.2》/《数据库设计文档 v4.0》规范 → 本项目
-- 对应迁移：database/migration_absorb_v1.sql
USE shengtai;
-- 原则：只增不改不删，保证现有接口零破坏
--   1) 乐观锁 version（关键表）
--   2) 寄售手续费/冷却字段
--   3) 新表：验证码、运营参数、管理员、审计/操作日志、优先购、空投
-- =====================================================================

-- ---------- 1. 乐观锁版本号 ----------
ALTER TABLE `user_wallets`
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁版本号（余额并发变更防超扣）' AFTER `currency`;

ALTER TABLE `orders`
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁版本号（支付/取消幂等）' AFTER `is_deleted`;

-- ---------- 2. 寄售手续费与冷却（司南 v2.2 查缺补漏 #15） ----------
ALTER TABLE `nft_sell_orders`
  ADD COLUMN `fee_rate`       DECIMAL(6,4)  NOT NULL DEFAULT 0.0000 COMMENT '寄售手续费率（成交时快照）' AFTER `status`,
  ADD COLUMN `fee_amount`     DECIMAL(20,2) NOT NULL DEFAULT 0.00 COMMENT '手续费金额' AFTER `fee_rate`,
  ADD COLUMN `actual_amount`  DECIMAL(20,2) NOT NULL DEFAULT 0.00 COMMENT '卖家实际到账' AFTER `fee_amount`,
  ADD COLUMN `cooldown_until` BIGINT        NOT NULL DEFAULT 0    COMMENT '再次上架冷却截止（毫秒时间戳）' AFTER `actual_amount`,
  ADD COLUMN `version`        INT UNSIGNED  NOT NULL DEFAULT 0    COMMENT '乐观锁版本号（抢购防并发）' AFTER `cooldown_until`;

-- 公告分类补软删列（管理端 CMS 按 is_deleted 过滤）
ALTER TABLE `notice_categories`
  ADD COLUMN `is_deleted` TINYINT NOT NULL DEFAULT 0 COMMENT '软删：0正常 1删除' AFTER `sort`;

-- ---------- 3. 短信验证码表（司南 verification_codes） ----------
CREATE TABLE `verification_codes` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account`    VARCHAR(20)  NOT NULL                COMMENT '手机号/账号',
  `code`       VARCHAR(10)  NOT NULL                COMMENT '验证码明文（本地环境）',
  `scene`      VARCHAR(20)  NOT NULL DEFAULT 'login' COMMENT '场景：login/register/reset',
  `used`       TINYINT(1)   NOT NULL DEFAULT 0      COMMENT '0=未使用 1=已消费',
  `expires_at` BIGINT       NOT NULL DEFAULT 0      COMMENT '过期时间（毫秒）',
  `is_deleted` TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_account_scene` (`account`, `scene`, `used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短信验证码';

-- ---------- 4. 运营参数配置表（司南 system_configs，禁硬编码） ----------
CREATE TABLE `system_configs` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_key`   VARCHAR(64)  NOT NULL COMMENT '键，如 order.pay_window_ms',
  `config_value` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '值',
  `remark`       VARCHAR(100) NOT NULL DEFAULT '' COMMENT '说明',
  `is_deleted`   TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运营参数（运行时可改，禁硬编码）';

-- ---------- 5. 管理员 / 审计 / 操作日志（文档 admin 模块） ----------
CREATE TABLE `admin_users` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50) NOT NULL,
  `password`      VARCHAR(255) NOT NULL COMMENT 'bcrypt',
  `real_name`     VARCHAR(50) NOT NULL DEFAULT '',
  `role`          TINYINT NOT NULL DEFAULT 2 COMMENT '1超管 2运营 3财务 4客服',
  `status`        TINYINT NOT NULL DEFAULT 1 COMMENT '1正常 0禁用',
  `last_login_at` DATETIME     NULL,
  `last_login_ip` VARCHAR(45)  NULL,
  `login_count`   INT UNSIGNED NOT NULL DEFAULT 0,
  `is_deleted`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员';

CREATE TABLE `audit_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type` TINYINT     NOT NULL DEFAULT 1 COMMENT '1用户 2管理员 3系统',
  `actor_id`   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `module`     VARCHAR(30) NOT NULL DEFAULT '' COMMENT '模块：passport/order/nft/...',
  `action`     VARCHAR(50) NOT NULL DEFAULT '',
  `target`     VARCHAR(64) NOT NULL DEFAULT '' COMMENT '目标对象标识',
  `detail`     VARCHAR(500) NOT NULL DEFAULT '',
  `ip`         VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_type`, `actor_id`),
  KEY `idx_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='审计日志';

CREATE TABLE `operation_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `module`      VARCHAR(30) NOT NULL DEFAULT '',
  `action`      VARCHAR(50) NOT NULL DEFAULT '',
  `target`      VARCHAR(64) NOT NULL DEFAULT '' COMMENT '操作对象标识(如 order:123/item:456)',
  `before_data` TEXT        NULL,
  `after_data`  TEXT        NULL,
  `ip`          VARCHAR(45) NOT NULL DEFAULT '',
  `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台操作日志';

-- ---------- 6. 优先购（设计文档 v4.0） ----------
CREATE TABLE `priority_sales` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `iid`        BIGINT UNSIGNED NOT NULL COMMENT '关联藏品 items.iid',
  `name`       VARCHAR(100) NOT NULL COMMENT '活动名称',
  `start_time` BIGINT NOT NULL DEFAULT 0 COMMENT '开始（毫秒）',
  `end_time`   BIGINT NOT NULL DEFAULT 0 COMMENT '结束=公售开始（毫秒）',
  `status`     TINYINT NOT NULL DEFAULT 1 COMMENT '1待开始 2进行中 3已结束 4已取消',
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iid` (`iid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优先购活动';

CREATE TABLE `priority_sale_whitelists` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `priority_sale_id` BIGINT UNSIGNED NOT NULL,
  `uid`              BIGINT UNSIGNED NOT NULL,
  `max_quantity`     INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '可购上限',
  `used_quantity`    INT UNSIGNED NOT NULL DEFAULT 0,
  `status`           TINYINT NOT NULL DEFAULT 1 COMMENT '1有效 2已用完 3已取消资格',
  `version`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁（并发扣减防超购）',
  `is_deleted`       TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_uid` (`priority_sale_id`, `uid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优先购白名单';

-- ---------- 7. 空投体系（司南 v2.2 空投域 4 表） ----------
CREATE TABLE `airdrop_activities` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(100) NOT NULL,
  `iid`            BIGINT UNSIGNED NOT NULL COMMENT '空投藏品 items.iid',
  `mode`           TINYINT NOT NULL DEFAULT 1 COMMENT '1持有快照 2名单制',
  `total_quantity` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '计划空投总量',
  `start_time`     BIGINT NOT NULL DEFAULT 0,
  `end_time`       BIGINT NOT NULL DEFAULT 0,
  `status`         TINYINT NOT NULL DEFAULT 1 COMMENT '1待开始 2进行中 3已结束 4已取消',
  `remark`         VARCHAR(200) NOT NULL DEFAULT '',
  `is_deleted`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iid_status` (`iid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='空投活动';

CREATE TABLE `airdrop_snapshots` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` BIGINT UNSIGNED NOT NULL,
  `iid`         BIGINT UNSIGNED NOT NULL COMMENT '持有藏品 iid',
  `uid`         BIGINT UNSIGNED NOT NULL,
  `nft_id`      BIGINT UNSIGNED NOT NULL COMMENT '快照时的持仓 nft_id',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_nft` (`activity_id`, `nft_id`),
  KEY `idx_activity_uid` (`activity_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='空投持有快照';

CREATE TABLE `airdrop_eligibilities` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` BIGINT UNSIGNED NOT NULL,
  `uid`         BIGINT UNSIGNED NOT NULL,
  `eligible`    TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1有资格 0无',
  `reason`      VARCHAR(100) NOT NULL DEFAULT '' COMMENT '不可空投原因',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_uid` (`activity_id`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='空投资格（名单制）';

CREATE TABLE `airdrop_records` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `activity_id` BIGINT UNSIGNED NOT NULL,
  `uid`         BIGINT UNSIGNED NOT NULL,
  `nft_id`      BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发放的 nft_id（0=失败无发放）',
  `status`      TINYINT NOT NULL DEFAULT 1 COMMENT '1成功 2失败',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='空投发放记录';

-- ---------- 邀请奖励参数（活动后台 invite.*，仅藏品通道，不发余额） ----------
INSERT INTO `system_configs` (`config_key`, `config_value`, `remark`) VALUES
  ('invite.enabled',        '1',         '邀请好友奖励开关（1 开启 0 关闭）'),
  ('invite.reward_iid',     '0',         '邀请好友奖励藏品ID（items.id）'),
  ('invite.reward_qty',     '1',         '邀请好友奖励藏品数量'),
  ('invite.settle_timing',  'register',  '邀请奖励结算时机（register=注册即发 first_order=首单支付后）')
ON DUPLICATE KEY UPDATE `remark` = VALUES(`remark`);

-- 邀请奖励已取消余额通道，清理历史配置键
DELETE FROM `system_configs` WHERE `config_key` IN ('invite.reward_type', 'invite.reward_amount');

-- ---------- 签到里程碑空投参数（activity.sign_milestones） ----------
-- CSV「连续天:items.id:数量」，如 3:12:1,7:15:1；空 = 不启用
INSERT INTO `system_configs` (`config_key`, `config_value`, `remark`) VALUES
  ('activity.sign_milestones', '', '签到里程碑空投 CSV（连续天:items.id:数量），空=不启用')
ON DUPLICATE KEY UPDATE `remark` = VALUES(`remark`);
