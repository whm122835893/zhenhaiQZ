-- ============================================================================
-- 臻海艺术 · 定向发放引擎 —— 新增表 DDL
--
-- 用途：新人发放 / 活动空投 / 运营补偿，统一为「发放任务 → 发放记录」两级模型。
--   三种场景的差异只在目标人群解析（target_type）与触发方式（trigger_type）。
--
-- 幂等：grant_record 唯一索引 uk_task_uid(task_id, uid) —— 表即队列、重复执行安全。
-- 约束：只新增表，不修改现有 48 张业务表的任何列（NFT 来源追溯走 grant_record.nft_ids）。
--
-- 环境：MariaDB 10.11 / utf8mb4_general_ci
-- 执行：mysql -h127.0.0.1 -ushengtai -p shengtai < grant_schema.sql
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. grant_task —— 发放任务
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `grant_task`;
CREATE TABLE `grant_task` (
  `id`                bigint unsigned   NOT NULL COMMENT '雪花ID',
  `name`              varchar(64)       NOT NULL DEFAULT '' COMMENT '任务名，如「新人注册送蓝卡」',
  `type`              tinyint           NOT NULL DEFAULT 1 COMMENT '1=新人发放 2=活动空投 3=运营补偿',
  `iid`               bigint unsigned   NOT NULL DEFAULT 0 COMMENT '发放藏品ID(items.id)',
  `quantity_per_user` int               NOT NULL DEFAULT 1 COMMENT '每人发放数量',
  `total_quantity`    bigint unsigned   NOT NULL DEFAULT 0 COMMENT '计划总量上限(0=不限)',
  `granted_quantity`  bigint unsigned   NOT NULL DEFAULT 0 COMMENT '已发放份数(库存预占计数)',
  `target_type`       tinyint           NOT NULL DEFAULT 3 COMMENT '1=注册时间规则 2=持仓快照 3=指定uid列表',
  `target_rule`       varchar(2000)     NOT NULL DEFAULT '' COMMENT '规则JSON: 1={"registerAfter","registerBefore"} 2={"holdIid"} 3={"uids":[]}',
  `trigger_type`      tinyint           NOT NULL DEFAULT 3 COMMENT '1=实时事件(注册) 3=手动执行(服务端按type决定)',
  `start_time`        datetime          NULL DEFAULT NULL COMMENT '有效期起',
  `end_time`          datetime          NULL DEFAULT NULL COMMENT '有效期止',
  `status`            tinyint           NOT NULL DEFAULT 0 COMMENT '0草稿 1待审 2已通过 3进行中 4已完成 5已取消 6已驳回',
  `ticket_no`         varchar(64)       NOT NULL DEFAULT '' COMMENT '关联工单/客诉单号(补偿场景必填)',
  `remark`            varchar(500)      NOT NULL DEFAULT '' COMMENT '备注',
  `creator_id`        bigint unsigned   NOT NULL DEFAULT 0 COMMENT '创建管理员(admin_users.id)',
  `auditor_id`        bigint unsigned   NOT NULL DEFAULT 0 COMMENT '审核管理员(强校验≠创建人)',
  `audit_remark`      varchar(255)      NOT NULL DEFAULT '' COMMENT '审核意见(驳回必填)',
  `executed_at`       datetime          NULL DEFAULT NULL COMMENT '最近执行时间',
  `created_at`        datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`        datetime          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_type_status` (`type`, `status`),
  KEY `idx_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='定向发放任务';

-- ---------------------------------------------------------------------------
-- 2. grant_record —— 发放记录（表即队列：待发→成功/失败）
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `grant_record`;
CREATE TABLE `grant_record` (
  `id`          bigint unsigned NOT NULL COMMENT '雪花ID',
  `task_id`     bigint unsigned NOT NULL COMMENT '任务ID(事件驱动直发为0)',
  `uid`         bigint unsigned NOT NULL COMMENT '用户ID',
  `source`      varchar(32)     NOT NULL DEFAULT 'task' COMMENT '来源: task/sign_milestone/invite_reward/newbie',
  `biz_key`     varchar(64)     NOT NULL DEFAULT '' COMMENT '幂等键: task:{taskId}:{uid} / u{uid}:m{day} / inv:{inviteId}',
  `nft_ids`     varchar(500)    NOT NULL DEFAULT '' COMMENT '发放的NFT编号(逗号分隔,数量=quantity_per_user)',
  `status`      tinyint         NOT NULL DEFAULT 0 COMMENT '0待发 1发放中 2成功 3失败 4已取消(任务取消时清理)',
  `fail_reason` varchar(255)    NOT NULL DEFAULT '' COMMENT '失败原因',
  `retry_times` tinyint         NOT NULL DEFAULT 0 COMMENT '重试次数',
  `granted_at`  datetime        NULL DEFAULT NULL COMMENT '发放成功时间',
  `created_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at`  datetime        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_task_uid` (`task_id`, `uid`),
  UNIQUE KEY `uk_biz_key` (`source`, `biz_key`),
  KEY `idx_uid` (`uid`, `status`),
  KEY `idx_status` (`task_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='定向发放记录(幂等主防线)';
