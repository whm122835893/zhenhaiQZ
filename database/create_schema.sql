-- ============================================================
-- 盛泰数字藏品平台 · MySQL 建表脚本
-- 版本 v1.0  生成日期 2026-07-19
-- MySQL 8.0+ / InnoDB / utf8mb4
-- 对应文档：docs/database-design.md
--
-- 兼容说明：
--   字符集用 utf8mb4_unicode_ci 而非 utf8mb4_0900_ai_ci，
--   因后者为 MySQL 8.0 专有，MariaDB 10.x 不支持。
--   如纯 MySQL 8 环境，可自行替换为 utf8mb4_0900_ai_ci。
-- ============================================================

CREATE DATABASE IF NOT EXISTS `shengtai`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `shengtai`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. 用户与鉴权域
-- ============================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                  bigint unsigned NOT NULL COMMENT '雪花ID',
  `phone`               varchar(20)  NOT NULL DEFAULT '' COMMENT '手机号',
  `email`               varchar(128) NOT NULL DEFAULT '' COMMENT '邮箱',
  `account`             varchar(64)  NOT NULL DEFAULT '' COMMENT '登录名',
  `password_hash`       varchar(255) NOT NULL DEFAULT '' COMMENT '密码散列',
  `salt`                varchar(32)  NOT NULL DEFAULT '' COMMENT '密码盐',
  `has_password`        tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否已设密码',
  `has_second_password` tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否已设二级密码',
  `second_password_hash` varchar(255) NOT NULL DEFAULT '' COMMENT '二级密码散列',
  `has_real_name`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否已实名',
  `real_name`           varchar(64)  NOT NULL DEFAULT '' COMMENT '实名姓名',
  `id_card`             varchar(32)  NOT NULL DEFAULT '' COMMENT '身份证号',
  `status`              varchar(32)  NOT NULL DEFAULT 'normal' COMMENT 'normal/frozen/banned',
  `last_login_at`       bigint       NULL DEFAULT NULL COMMENT '最后登录时间(13位毫秒)',
  `created_at`          datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`          tinyint(1)   NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_phone` (`phone`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_status` (`status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户账号';

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id`            bigint unsigned NOT NULL,
  `uid`           bigint unsigned NOT NULL COMMENT '用户ID',
  `nickname`      varchar(64)  NOT NULL DEFAULT '' COMMENT '昵称',
  `avatar`        varchar(255) NOT NULL DEFAULT '' COMMENT '头像相对路径',
  `avatar_url`    varchar(512) NOT NULL DEFAULT '' COMMENT '头像绝对地址',
  `sex`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0未知 1男 2女',
  `birthday`      date         NULL DEFAULT NULL COMMENT '生日',
  `country`       varchar(64)  NOT NULL DEFAULT '',
  `region_code`   varchar(32)  NOT NULL DEFAULT '' COMMENT '地区码',
  `introduction`  varchar(512) NOT NULL DEFAULT '' COMMENT '个人简介',
  `chain_address` varchar(128) NOT NULL DEFAULT '' COMMENT '区块链地址',
  `invite_code`   varchar(32)  NOT NULL DEFAULT '' COMMENT '我的邀请码',
  `inviter`       bigint unsigned NOT NULL DEFAULT 0 COMMENT '邀请人UID',
  `token`         varchar(128) NOT NULL DEFAULT '' COMMENT '访问令牌',
  `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_profiles_uid` (`uid`),
  UNIQUE KEY `uk_profiles_invite_code` (`invite_code`),
  KEY `idx_profiles_inviter` (`inviter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户资料';

DROP TABLE IF EXISTS `user_wallets`;
CREATE TABLE `user_wallets` (
  `id`             bigint unsigned NOT NULL,
  `uid`            bigint unsigned NOT NULL,
  `balance`        decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '可用余额',
  `frozen_balance` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '冻结余额',
  `total_income`   decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_expense`  decimal(20,2) NOT NULL DEFAULT 0.00,
  `currency`       varchar(8)   NOT NULL DEFAULT 'CNY',
  `created_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wallet_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户钱包';

DROP TABLE IF EXISTS `user_invites`;
CREATE TABLE `user_invites` (
  `id`            bigint unsigned NOT NULL,
  `uid`           bigint unsigned NOT NULL COMMENT '邀请人',
  `invitee_uid`   bigint unsigned NOT NULL DEFAULT 0 COMMENT '被邀请人',
  `invite_code`   varchar(32)  NOT NULL DEFAULT '',
  `reward_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `reward_status` varchar(32)  NOT NULL DEFAULT 'pending' COMMENT 'pending/granted/invalid',
  `rewarded_at`   bigint       NULL DEFAULT NULL COMMENT '发放时间(13位毫秒)',
  `created_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invites_uid` (`uid`, `invitee_uid`),
  KEY `idx_invites_code` (`invite_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请记录';

DROP TABLE IF EXISTS `user_login_logs`;
CREATE TABLE `user_login_logs` (
  `id`           bigint unsigned NOT NULL,
  `uid`          bigint unsigned NOT NULL DEFAULT 0,
  `account`      varchar(64)  NOT NULL DEFAULT '',
  `login_type`   varchar(32)  NOT NULL DEFAULT 'password' COMMENT 'password/quick/oneClick/sms',
  `ip`           varchar(64)  NOT NULL DEFAULT '',
  `device_id`    varchar(64)  NOT NULL DEFAULT '',
  `device_model` varchar(64)  NOT NULL DEFAULT '',
  `platform`     varchar(32)  NOT NULL DEFAULT '',
  `status`       varchar(32)  NOT NULL DEFAULT 'success',
  `fail_reason`  varchar(255) NOT NULL DEFAULT '',
  `created_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_uid_time` (`uid`, `created_at`),
  KEY `idx_login_account_time` (`account`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='登录日志';

DROP TABLE IF EXISTS `user_online_sessions`;
CREATE TABLE `user_online_sessions` (
  `id`              bigint unsigned NOT NULL,
  `uid`             bigint unsigned NOT NULL,
  `device_id`       varchar(64) NOT NULL DEFAULT '',
  `device_model`    varchar(64) NOT NULL DEFAULT '',
  `platform`        varchar(32) NOT NULL DEFAULT '',
  `ip`              varchar(64) NOT NULL DEFAULT '',
  `token`           varchar(512) NOT NULL DEFAULT '' COMMENT '会话token(源站返回)',
  `signin_way`      tinyint(2)   NOT NULL DEFAULT 0 COMMENT '登录方式(源站signinWay: 1密码 2短信 3第三方)',
  `login_at`        bigint      NULL DEFAULT NULL COMMENT '登录时间(13位毫秒)',
  `last_active_at`  bigint      NULL DEFAULT NULL COMMENT '最后活跃(13位毫秒)',
  `is_online`       tinyint(1)  NOT NULL DEFAULT 1 COMMENT '是否在线(源站无此字段,本地按过期推导)',
  `is_self`         tinyint(1)  NOT NULL DEFAULT 0 COMMENT '是否当前会话(源站isSelf)',
  `created_at`      datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_online_uid` (`uid`, `is_online`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='在线会话';

-- ============================================================
-- 2. 藏品目录域
-- ============================================================

DROP TABLE IF EXISTS `item_categories`;
CREATE TABLE `item_categories` (
  `id`         bigint unsigned NOT NULL,
  `cid`        bigint unsigned NOT NULL COMMENT '分类ID',
  `name`       varchar(64)  NOT NULL DEFAULT '',
  `cover`      varchar(255) NOT NULL DEFAULT '',
  `cover_url`  varchar(512) NOT NULL DEFAULT '',
  `sort`       int NOT NULL DEFAULT 0,
  `status`     varchar(32) NOT NULL DEFAULT 'enabled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_item_cats_cid` (`cid`),
  KEY `idx_item_cats_sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品分类';

DROP TABLE IF EXISTS `item_authors`;
CREATE TABLE `item_authors` (
  `id`            bigint unsigned NOT NULL,
  `name`          varchar(64)   NOT NULL DEFAULT '',
  `avatar`        varchar(255)  NOT NULL DEFAULT '',
  `avatar_url`    varchar(512)  NOT NULL DEFAULT '',
  `introduction`  varchar(1024) NOT NULL DEFAULT '',
  `works_count`   int NOT NULL DEFAULT 0,
  `follower_count` int NOT NULL DEFAULT 0,
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_authors_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='作者';

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `id`                       bigint unsigned NOT NULL,
  `iid`                      bigint unsigned NOT NULL COMMENT '藏品ID',
  `cid`                      bigint unsigned NOT NULL DEFAULT 0 COMMENT '分类ID',
  `author_id`                bigint unsigned NOT NULL DEFAULT 0 COMMENT '作者ID',
  `name`                     varchar(128) NOT NULL DEFAULT '',
  `contract`                 varchar(128) NOT NULL DEFAULT '' COMMENT '合约地址',
  `image`                    varchar(255) NOT NULL DEFAULT '',
  `image_url`                varchar(512) NOT NULL DEFAULT '',
  `content`                  mediumtext   NULL COMMENT '图文详情HTML',
  `price`                    decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '一级价(单位:分,源站原样不换算,前端÷100)',
  `type`                     varchar(32)  NOT NULL DEFAULT '',
  `issue_quantity`           int NOT NULL DEFAULT 0 COMMENT '发行总量',
  `buy_quantity`             int NOT NULL DEFAULT 0 COMMENT '已购数量',
  `nft_quantity`             int NOT NULL DEFAULT 0 COMMENT '已铸造NFT数',
  `pre_start_time`           bigint NULL DEFAULT NULL COMMENT '预告开始(13位毫秒)',
  `pre_end_time`             bigint NULL DEFAULT NULL COMMENT '预告结束(13位毫秒)',
  `start_time`               bigint NULL DEFAULT NULL COMMENT '开始(13位毫秒)',
  `end_time`                 bigint NULL DEFAULT NULL COMMENT '结束(13位毫秒)',
  `box_open_time`            bigint NULL DEFAULT NULL COMMENT '开盒时间(13位毫秒)',
  `is_primary`               tinyint(1) NOT NULL DEFAULT 0,
  `is_primary_apply`         tinyint(1) NOT NULL DEFAULT 0,
  `is_recommend`             tinyint(1) NOT NULL DEFAULT 0,
  `is_popular`               tinyint(1) NOT NULL DEFAULT 0,
  `is_give`                  tinyint(1) NOT NULL DEFAULT 0,
  `is_resale`                tinyint(1) NOT NULL DEFAULT 0,
  `is_secondary`             tinyint(1) NOT NULL DEFAULT 0,
  `is_secondary_batch`       tinyint(1) NOT NULL DEFAULT 0,
  `is_secondary_apply`       tinyint(1) NOT NULL DEFAULT 0,
  `is_secondary_trust`       tinyint(1) NOT NULL DEFAULT 0,
  `secondary_low_price`      decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '二级最低价(单位:分,源站原样不换算)',
  `secondary_high_price`     decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '二级最高价(单位:分,源站原样不换算)',
  `secondary_apply_low_price`  decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '求购最低价(分)',
  `secondary_apply_high_price` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '求购最高价(分)',
  `sell_quantity`            int NOT NULL DEFAULT 0 COMMENT '在售数量',
  `min_price`                decimal(20,2) NULL DEFAULT NULL COMMENT '在售最低价(可为null)',
  `max_price`                decimal(20,2) NULL DEFAULT NULL COMMENT '在售最高价(可为null)',
  `trade_quantity`           int NOT NULL DEFAULT 0 COMMENT '成交数量',
  `trade_amount`             decimal(20,2) NULL DEFAULT NULL COMMENT '累计成交额(分,源站可为null)',
  `last_trade_amount`        decimal(20,2) NULL DEFAULT NULL COMMENT '最新成交价(分,可为null)',
  `today_trade_quantity`     int NOT NULL DEFAULT 0,
  `hour_trade_quantity`      int NULL DEFAULT NULL COMMENT '小时成交数(源站可为null)',
  `today_trade_amount`       decimal(20,2) DEFAULT NULL COMMENT '今日成交额(分)。源站“今日无成交”返回 null，故必须可空，不能 NOT NULL DEFAULT 0',
  `today_trade_max_amount`   decimal(20,2) NULL DEFAULT NULL COMMENT '今日最高价(可为null)',
  `today_trade_min_amount`   decimal(20,2) NULL DEFAULT NULL COMMENT '今日最低价(可为null)',
  `price_change`             decimal(10,4) NULL DEFAULT NULL COMMENT '涨跌幅(源站常为null)',
  `is_sold_out`              tinyint(1) NOT NULL DEFAULT 0,
  `is_pre`                   tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否处于预热期',
  `view_count`               int NOT NULL DEFAULT 0,
  `follower_count`           int NOT NULL DEFAULT 0,
  `status`                   varchar(32) NOT NULL DEFAULT 'on',
  `created_at`               datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`               tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_items_iid` (`iid`),
  KEY `idx_items_cid_status` (`cid`, `status`, `is_deleted`),
  KEY `idx_items_author` (`author_id`),
  KEY `idx_items_recommend` (`is_recommend`, `status`),
  KEY `idx_items_popular` (`is_popular`, `status`),
  KEY `idx_items_start_time` (`start_time`),
  KEY `idx_items_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品主表';

DROP TABLE IF EXISTS `item_follows`;
CREATE TABLE `item_follows` (
  `id`         bigint unsigned NOT NULL,
  `iid`        bigint unsigned NOT NULL,
  `uid`        bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_follow_iid_uid` (`iid`, `uid`),
  KEY `idx_follow_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品关注';

DROP TABLE IF EXISTS `notice_item_relations`;
CREATE TABLE `notice_item_relations` (
  `id`         bigint unsigned NOT NULL,
  `notice_id`  bigint unsigned NOT NULL COMMENT '公告ID',
  `iid`        bigint unsigned NOT NULL COMMENT '藏品ID',
  `name`       varchar(128) NOT NULL DEFAULT '' COMMENT '冗余名称',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `is_secondary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '冗余可二级交易',
  `sort`       int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nir_notice` (`notice_id`, `sort`),
  KEY `idx_nir_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告-藏品关联(源站notice.items)';

DROP TABLE IF EXISTS `item_copyrights`;
CREATE TABLE `item_copyrights` (
  `id`         bigint unsigned NOT NULL,
  `name`       varchar(128) NOT NULL DEFAULT '' COMMENT '版权名称(源站name)',
  `image`      varchar(255) NOT NULL DEFAULT '' COMMENT '版权图相对路径',
  `image_url`  varchar(512) NOT NULL DEFAULT '' COMMENT '版权图绝对地址',
  `sort`       int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_copyright_sort` (`sort`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='版权信息(源站全局,GET /item/copyright 不按 iid 过滤)';

DROP TABLE IF EXISTS `product_tp_categories`;
CREATE TABLE `product_tp_categories` (
  `id`         bigint unsigned NOT NULL,
  `name`       varchar(128) NOT NULL DEFAULT '',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `sort`       int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tp_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图谱分类';

DROP TABLE IF EXISTS `product_tp_items`;
CREATE TABLE `product_tp_items` (
  `id`         bigint unsigned NOT NULL,
  `cid`        bigint unsigned NOT NULL DEFAULT 0,
  `iid`        bigint unsigned NOT NULL DEFAULT 0,
  `name`       varchar(128) NOT NULL DEFAULT '',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `sort`       int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tp_items_cid` (`cid`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图谱项';

-- ============================================================
-- 3. 资产域
-- ============================================================

DROP TABLE IF EXISTS `nfts`;
CREATE TABLE `nfts` (
  `id`              bigint unsigned NOT NULL,
  `nft_id`          bigint unsigned NOT NULL COMMENT '资产ID',
  `uid`             bigint unsigned NOT NULL COMMENT '持有人',
  `iid`             bigint unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `name`            varchar(128) NOT NULL DEFAULT '',
  `image`           varchar(255) NOT NULL DEFAULT '',
  `image_url`       varchar(512) NOT NULL DEFAULT '',
  `token`           varchar(128) NOT NULL DEFAULT '' COMMENT '链上token',
  `status`          tinyint(2)   NOT NULL DEFAULT 0 COMMENT '源站数字枚举: 0=普通 1=在售(挂单中) 2=已售',
  `get_time`        bigint       NULL DEFAULT NULL COMMENT '获得时间(13位毫秒)',
  `price`           decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '当前挂牌价',
  `buy_price`       decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '买入价',
  `pay_lock`        tinyint(1) NOT NULL DEFAULT 0,
  `pay_way`         tinyint(1) NULL DEFAULT NULL COMMENT '支付方式(源站可为null)',
  `chain_id`        varchar(128) NOT NULL DEFAULT '',
  `chain_completed` tinyint(1) NOT NULL DEFAULT 0,
  `sell_status`     varchar(32) NOT NULL DEFAULT 'none' COMMENT 'none/on_sale/sold',
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nfts_nft_id` (`nft_id`),
  KEY `idx_nfts_uid_status` (`uid`, `sell_status`, `is_deleted`),
  KEY `idx_nfts_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='NFT资产';

DROP TABLE IF EXISTS `nft_groups`;
CREATE TABLE `nft_groups` (
  `id`          bigint unsigned NOT NULL,
  `iid`         bigint unsigned NOT NULL,
  `name`        varchar(128) NOT NULL DEFAULT '',
  `image`       varchar(255) NOT NULL DEFAULT '',
  `image_url`   varchar(512) NOT NULL DEFAULT '',
  `type`        varchar(32) NOT NULL DEFAULT '' COMMENT '仓库tab类型 0/2/4',
  `own_quantity` int NOT NULL DEFAULT 0,
  `min_price`   decimal(20,2) NULL DEFAULT NULL COMMENT '最低价(源站常为null)',
  `price_change` decimal(10,4) NULL DEFAULT NULL COMMENT '涨跌幅(源站常为null)',
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nft_groups_iid` (`iid`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='仓库分组聚合(可重建)';

DROP TABLE IF EXISTS `nft_merge_recipes`;
CREATE TABLE `nft_merge_recipes` (
  `id`              bigint unsigned NOT NULL COMMENT '源站直接返回id(不叫mid)',
  `name`            varchar(128) NOT NULL DEFAULT '',
  `cover`           varchar(255) NOT NULL DEFAULT '' COMMENT '源站字段名是cover非image',
  `cover_url`       varchar(512) NOT NULL DEFAULT '',
  `content`         text        NULL COMMENT '源站可为null',
  `type`            tinyint(2)   NOT NULL DEFAULT 1,
  `merge_quantity`  int NOT NULL DEFAULT 1 COMMENT '产出数量(源站mergeQuantity)',
  `pre_start_time`  bigint NOT NULL DEFAULT 0,
  `pre_end_time`    bigint NOT NULL DEFAULT 0,
  `start_time`      bigint NOT NULL DEFAULT 0,
  `end_time`        bigint NOT NULL DEFAULT 0,
  `merge_time`      bigint NOT NULL DEFAULT 0,
  `is_pre`          tinyint(1) NOT NULL DEFAULT 0,
  `status`          tinyint(2) NOT NULL DEFAULT 1,
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_merge_status_time` (`status`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成配方(源站: 详情含merges产出/needs消耗两个子数组,见nft_merge_needs与nft_merge_produces)';

-- 合成产出物（源站 /item/merge/{id} 详情的 merges 数组）
DROP TABLE IF EXISTS `nft_merge_produces`;
CREATE TABLE `nft_merge_produces` (
  `id`         bigint unsigned NOT NULL,
  `merge_id`   bigint unsigned NOT NULL COMMENT '配方ID',
  `iid`        bigint unsigned NOT NULL COMMENT '产出藏品ID',
  `quantity`   int NOT NULL DEFAULT 1,
  `name`       varchar(128) NOT NULL DEFAULT '',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `own_quantity` int NOT NULL DEFAULT 0 COMMENT '当前用户持有量(源站ownQuantity,动态值仅回显不落库)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merge_produce_merge` (`merge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成产出物';

-- 合成消耗物（源站 /item/merge/{id} 详情的 needs 数组）
DROP TABLE IF EXISTS `nft_merge_needs`;
CREATE TABLE `nft_merge_needs` (
  `id`         bigint unsigned NOT NULL,
  `merge_id`   bigint unsigned NOT NULL COMMENT '配方ID',
  `iid`        bigint unsigned NOT NULL COMMENT '消耗藏品ID',
  `quantity`   int NOT NULL DEFAULT 1 COMMENT '消耗数量(实测10张臻海蓝卡合成1张资格卡)',
  `name`       varchar(128) NOT NULL DEFAULT '',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `own_quantity` int NOT NULL DEFAULT 0 COMMENT '当前用户持有量(源站ownQuantity,动态值仅回显不落库)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merge_need_merge` (`merge_id`),
  KEY `idx_merge_need_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成消耗物';

DROP TABLE IF EXISTS `nft_merge_records`;
CREATE TABLE `nft_merge_records` (
  `id`               bigint unsigned NOT NULL,
  `uid`              bigint unsigned NOT NULL,
  `mid`              bigint unsigned NOT NULL,
  `consume_nft_ids`  text NULL COMMENT '消耗NFT(JSON数组)',
  `produce_nft_ids`  text NULL COMMENT '产出NFT(JSON数组)',
  `status`           varchar(32) NOT NULL DEFAULT 'success',
  `created_at`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_merge_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成记录';

DROP TABLE IF EXISTS `nft_box_open_logs`;
CREATE TABLE `nft_box_open_logs` (
  `id`          bigint unsigned NOT NULL,
  `uid`         bigint unsigned NOT NULL,
  `iid`         bigint unsigned NOT NULL,
  `nft_id`      bigint unsigned NOT NULL DEFAULT 0,
  `cost_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `pay_way`     tinyint(1) NOT NULL DEFAULT 0,
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_box_uid_time` (`uid`, `created_at`),
  KEY `idx_box_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='开箱记录';

-- ============================================================
-- 4. 交易域
-- ============================================================

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id`               bigint unsigned NOT NULL COMMENT '雪花订单号',
  `uid`              bigint unsigned NOT NULL COMMENT '买家(源站buyer)',
  `iid`              bigint unsigned NOT NULL DEFAULT 0,
  `order_type`       tinyint(2)    NOT NULL DEFAULT 0 COMMENT '源站type数字枚举(实测自order分包orderTypes): 10常规 20市场 30批量 40委托 50求购 60转售 70申购',
  `pay_way`          tinyint(1)    NOT NULL DEFAULT 1 COMMENT '支付方式: 1余额 2汇付(hfpay) 3易宝(yeepay)',
  `status`           tinyint(2)    NOT NULL DEFAULT 0 COMMENT '源站status数字枚举(实测自order分包statusValues): 0待付款 1进行中(委托中/求购中/申购中) 2已完成 3已取消 4已退款(仅市场订单)',
  `title`            varchar(128)  NOT NULL DEFAULT '' COMMENT '藏品名(冗余,源站返回)',
  `image`            varchar(255)  NOT NULL DEFAULT '' COMMENT '藏品图相对路径(冗余)',
  `image_url`        varchar(512)  NOT NULL DEFAULT '' COMMENT '藏品图绝对地址(冗余)',
  `unit_price`       decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '单价(分)',
  `quantity`         int NOT NULL DEFAULT 1,
  `item_quantity`    int NOT NULL DEFAULT 1 COMMENT '明细件数(源站itemQuantity)',
  `amount`           decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '订单总额(分,=unitPrice*quantity)',
  `subject`          varchar(255)  NULL DEFAULT NULL COMMENT '支付标题(源站可为null)',
  `out_trade_no`     varchar(64)   NULL DEFAULT NULL COMMENT '外部交易号(源站可为null)',
  `is_snap_up`       tinyint(1)    NOT NULL DEFAULT 0 COMMENT '是否秒杀',
  `seller_uid`       bigint unsigned NOT NULL DEFAULT 0 COMMENT '卖家',
  `related_order_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '关联求购/委托单',
  `end_pay_time`     bigint NOT NULL DEFAULT 0 COMMENT '支付截止(13位毫秒,源站endPayTime)',
  `pay_time`         bigint NOT NULL DEFAULT 0 COMMENT '支付时间(13位毫秒,0=未支付,源站payTime为null)',
  `cancelled_at`     bigint NOT NULL DEFAULT 0 COMMENT '取消时间(13位毫秒,0=未取消)',
  `remark`           varchar(512) NOT NULL DEFAULT '',
  `created_ts`       bigint NOT NULL DEFAULT 0 COMMENT '下单时间(13位毫秒,源站createTime)',
  `created_at`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`       tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_orders_uid_status` (`uid`, `status`, `created_ts`),
  KEY `idx_orders_iid_status` (`iid`, `status`),
  KEY `idx_orders_seller` (`seller_uid`, `status`),
  KEY `idx_orders_type_status` (`order_type`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单主表';

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id`          bigint unsigned NOT NULL,
  `order_id`    bigint unsigned NOT NULL,
  `nft_id`      bigint unsigned NOT NULL DEFAULT 0 COMMENT '成交NFT(源站nftId)',
  `token`       bigint unsigned NOT NULL DEFAULT 0 COMMENT 'NFT编号(源站token)',
  `amount`      decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '成交额(分,源站amount)',
  `seller_uid`  bigint unsigned NOT NULL DEFAULT 0 COMMENT '卖家(源站未返回,本地内部关联)',
  `created_ts`  bigint NOT NULL DEFAULT 0 COMMENT '创建时间(13位毫秒,源站createTime)',
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_nft` (`nft_id`),
  KEY `idx_order_items_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单明细(源站order.items,仅详情接口返回)';

DROP TABLE IF EXISTS `order_payments`;
CREATE TABLE `order_payments` (
  `id`            bigint unsigned NOT NULL,
  `order_id`      bigint unsigned NOT NULL,
  `uid`           bigint unsigned NOT NULL,
  `channel`       varchar(32) NOT NULL DEFAULT 'balance' COMMENT 'hfpay/yeepay/balance',
  `trade_no`      varchar(64) NOT NULL DEFAULT '',
  `cashier_url`   varchar(1024) NOT NULL DEFAULT '' COMMENT '收银台地址',
  `amount`        decimal(20,2) NOT NULL DEFAULT 0.00,
  `status`        varchar(32) NOT NULL DEFAULT 'pending',
  `paid_at`       datetime NULL DEFAULT NULL,
  `notify_payload` json NULL COMMENT '回调原始报文',
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pay_trade_no` (`trade_no`),
  KEY `idx_pay_order` (`order_id`),
  KEY `idx_pay_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付流水';

DROP TABLE IF EXISTS `nft_sell_orders`;
CREATE TABLE `nft_sell_orders` (
  `id`           bigint unsigned NOT NULL,
  `nft_id`       bigint unsigned NOT NULL COMMENT '挂单的NFT(源站secondary/sell直接返回nft对象)',
  `iid`          bigint unsigned NOT NULL,
  `uid`          bigint unsigned NOT NULL COMMENT '卖家',
  `price`        decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '挂单价(分)',
  `quantity`     int NOT NULL DEFAULT 1,
  `sold_quantity` int NOT NULL DEFAULT 0,
  `market_type`  tinyint(2) NOT NULL DEFAULT 1 COMMENT '1=寄售 2=求购 3=委托',
  `status`       tinyint(2) NOT NULL DEFAULT 1 COMMENT '1=在售 2=已售 0=已下架',
  `sold_at`      bigint NOT NULL DEFAULT 0,
  `buyer_uid`    bigint unsigned NOT NULL DEFAULT 0,
  `created_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`   tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sell_uid_status` (`uid`, `status`, `is_deleted`),
  KEY `idx_sell_iid_status` (`iid`, `status`, `price`),
  KEY `idx_sell_nft` (`nft_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='寄售挂单(源站返回形态=nft对象,status=1表示在售)';

DROP TABLE IF EXISTS `nft_market_apply_orders`;
CREATE TABLE `nft_market_apply_orders` (
  `id`                bigint unsigned NOT NULL COMMENT '源站返回字段为orderId',
  `uid`               bigint unsigned NOT NULL DEFAULT 0 COMMENT '求购人',
  `iid`               bigint unsigned NOT NULL,
  `title`             varchar(128) NOT NULL DEFAULT '' COMMENT '藏品名(冗余,源站返回)',
  `image`             varchar(255) NOT NULL DEFAULT '',
  `image_url`         varchar(512) NOT NULL DEFAULT '',
  `unit_price`        decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '求购单价(分)',
  `quantity`          int NOT NULL DEFAULT 1 COMMENT '求购数量',
  `completed_quantity` int NOT NULL DEFAULT 0 COMMENT '已成交数(源站completedQuantity)',
  `amount`            decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '总额(分,=unitPrice*quantity)',
  `created_ts`        bigint NOT NULL DEFAULT 0 COMMENT '创建时间(13位毫秒,源站createTime)',
  `pay_way`           tinyint(1) NOT NULL DEFAULT 1 COMMENT '1余额 2汇付',
  `status`            tinyint(2) NOT NULL DEFAULT 0 COMMENT '0开放 1部分成交 2已完成 3已取消(源站列表不返回)',
  `expire_at`         bigint NOT NULL DEFAULT 0,
  `created_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`        tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_apply_iid_status` (`iid`, `status`, `unit_price`),
  KEY `idx_apply_uid` (`uid`, `status`),
  KEY `idx_apply_ts` (`created_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='求购单(源站: 列表返回orderId/unitPrice/completedQuantity/amount)';

DROP TABLE IF EXISTS `nft_market_trust_orders`;
CREATE TABLE `nft_market_trust_orders` (
  `id`                bigint unsigned NOT NULL COMMENT '源站返回字段为orderId',
  `uid`               bigint unsigned NOT NULL DEFAULT 0 COMMENT '委托人',
  `iid`               bigint unsigned NOT NULL,
  `title`             varchar(128) NOT NULL DEFAULT '' COMMENT '藏品名(冗余)',
  `image`             varchar(255) NOT NULL DEFAULT '',
  `image_url`         varchar(512) NOT NULL DEFAULT '',
  `unit_price`        decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '委托单价(分)',
  `quantity`          int NOT NULL DEFAULT 1,
  `completed_quantity` int NOT NULL DEFAULT 0 COMMENT '已成交数(源站completedQuantity)',
  `amount`            decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '总额(分)',
  `created_ts`        bigint NOT NULL DEFAULT 0 COMMENT '创建时间(13位毫秒,源站createTime)',
  `pay_way`           tinyint(1) NOT NULL DEFAULT 2 COMMENT '1余额 2汇付(实测委托单多为2)',
  `status`            tinyint(2) NOT NULL DEFAULT 0 COMMENT '0开放 1部分 2完成 3取消',
  `entrust_uid`       bigint unsigned NOT NULL DEFAULT 0 COMMENT '受托人',
  `agent_rate`        decimal(5,4) NOT NULL DEFAULT 0.0000 COMMENT '佣金比例',
  `expire_at`         bigint NOT NULL DEFAULT 0,
  `created_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`        tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trust_iid_status` (`iid`, `status`, `unit_price`),
  KEY `idx_trust_uid` (`uid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='委托托卖单(源站: 与求购单同构) ';

DROP TABLE IF EXISTS `nft_market_follows`;
CREATE TABLE `nft_market_follows` (
  `id`         bigint unsigned NOT NULL,
  `iid`        bigint unsigned NOT NULL,
  `uid`        bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mf_iid_uid` (`iid`, `uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='市场关注';

DROP TABLE IF EXISTS `nft_trade_logs`;
CREATE TABLE `nft_trade_logs` (
  `id`         bigint unsigned NOT NULL,
  `nft_id`     bigint unsigned NOT NULL COMMENT 'NFT资产ID(源站nftId)',
  `iid`        bigint unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID(由nft关联,源站不返回)',
  `token`      varchar(128) NOT NULL DEFAULT '' COMMENT '链上token(源站返回)',
  `chain_id`   varchar(128) NOT NULL DEFAULT '' COMMENT '链上ID(源站返回)',
  `sold_price` decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '成交价(分,源站soldPrice)',
  `sold_time`  bigint NOT NULL DEFAULT 0 COMMENT '成交时间(13位毫秒,源站soldTime)',
  `order_id`   bigint unsigned NOT NULL DEFAULT 0 COMMENT '成交订单(源站不返回)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_trade_iid_time` (`iid`, `sold_time`),
  KEY `idx_trade_nft` (`nft_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='成交记录(源站仅返回 nftId/token/soldPrice/soldTime/chainId)';

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id`             bigint unsigned NOT NULL,
  `uid`            bigint unsigned NOT NULL,
  `order_id`       bigint unsigned NOT NULL DEFAULT 0,
  `trans_type`     varchar(32) NOT NULL DEFAULT 'buy' COMMENT 'recharge/withdraw/buy/sell/fee/reward',
  `amount`         decimal(20,2) NOT NULL DEFAULT 0.00 COMMENT '正入负出',
  `balance_after`  decimal(20,2) NOT NULL DEFAULT 0.00,
  `remark`         varchar(512) NOT NULL DEFAULT '',
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资金流水';

-- ============================================================
-- 5. 内容域
-- ============================================================

DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id`         bigint unsigned NOT NULL,
  `title`      varchar(128) NOT NULL DEFAULT '',
  `image`      varchar(255) NOT NULL DEFAULT '',
  `image_url`  varchar(512) NOT NULL DEFAULT '',
  `link_type`  varchar(32) NOT NULL DEFAULT 'none' COMMENT 'none/item/notice/url',
  `link_value` varchar(512) NOT NULL DEFAULT '',
  `position`   varchar(32) NOT NULL DEFAULT 'index',
  `sort`       int NOT NULL DEFAULT 0,
  `status`     varchar(32) NOT NULL DEFAULT 'enabled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_banner_pos` (`position`, `status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='轮播图';

DROP TABLE IF EXISTS `notice_categories`;
CREATE TABLE `notice_categories` (
  `id`         bigint unsigned NOT NULL,
  `cid`        bigint unsigned NOT NULL,
  `name`       varchar(64) NOT NULL DEFAULT '',
  `cover`      varchar(255) NOT NULL DEFAULT '',
  `cover_url`  varchar(512) NOT NULL DEFAULT '',
  `sort`       int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notice_cats_cid` (`cid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告分类';

DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id`           bigint unsigned NOT NULL,
  `cid`          bigint unsigned NOT NULL DEFAULT 0,
  `title`        varchar(255) NOT NULL DEFAULT '',
  `content`      mediumtext NULL COMMENT '正文HTML',
  `cover`        varchar(255) NOT NULL DEFAULT '',
  `cover_url`    varchar(512) NOT NULL DEFAULT '',
  `view_count`   int NOT NULL DEFAULT 0,
  `is_top`       tinyint(1) NOT NULL DEFAULT 0,
  `published_ts`  bigint NOT NULL DEFAULT 0 COMMENT '发布时间(13位毫秒,源站createTime)',
  `created_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`   tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_notice_cid_time` (`cid`, `published_ts`),
  KEY `idx_notice_top` (`is_top`, `published_ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告';

-- ============================================================
-- 6. 活动域
-- ============================================================

DROP TABLE IF EXISTS `activity_sign_records`;
CREATE TABLE `activity_sign_records` (
  `id`              bigint unsigned NOT NULL,
  `uid`             bigint unsigned NOT NULL,
  `sign_year`       int NOT NULL,
  `sign_month`      int NOT NULL COMMENT '1-12',
  `sign_day`        int NOT NULL COMMENT '1-31',
  `sign_days`       int NOT NULL DEFAULT 0 COMMENT '当月累计(冗余signDays)',
  `continuous_days` int NOT NULL DEFAULT 0,
  `reward_points`   int NOT NULL DEFAULT 0,
  `sign_time`       datetime NOT NULL,
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sign_uid_ymd` (`uid`, `sign_year`, `sign_month`, `sign_day`),
  KEY `idx_sign_uid_month` (`uid`, `sign_year`, `sign_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到记录';

DROP TABLE IF EXISTS `activity_lotteries`;
CREATE TABLE `activity_lotteries` (
  `id`         bigint unsigned NOT NULL,
  `name`       varchar(128) NOT NULL DEFAULT '',
  `cover`      varchar(255) NOT NULL DEFAULT '',
  `cover_url`  varchar(512) NOT NULL DEFAULT '',
  `points`     int NOT NULL DEFAULT 0 COMMENT '单次消耗积分',
  `start_time` datetime NULL DEFAULT NULL,
  `end_time`   datetime NULL DEFAULT NULL,
  `rule`       text NULL COMMENT '抽奖规则HTML',
  `status`     varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lottery_status` (`status`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖活动';

DROP TABLE IF EXISTS `activity_lottery_prizes`;
CREATE TABLE `activity_lottery_prizes` (
  `id`          bigint unsigned NOT NULL COMMENT '源站prize.id',
  `lottery_id`  bigint unsigned NOT NULL,
  `name`        varchar(128) NOT NULL DEFAULT '',
  `image`       varchar(255) NOT NULL DEFAULT '',
  `image_url`   varchar(512) NOT NULL DEFAULT '',
  `level`       varchar(32)  NOT NULL DEFAULT '',
  `probability` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT '中奖概率，整数百分比（如 43.00 表示 43%）',
  `quantity`    int NOT NULL DEFAULT 0,
  `sort`        int NOT NULL DEFAULT 0 COMMENT '转盘扇区顺序',
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lottery_prize_lottery` (`lottery_id`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖奖品';

DROP TABLE IF EXISTS `activity_lottery_logs`;
CREATE TABLE `activity_lottery_logs` (
  `id`              bigint unsigned NOT NULL,
  `uid`             bigint unsigned NOT NULL,
  `lottery_id`      bigint unsigned NOT NULL,
  `prize_id`        bigint unsigned NOT NULL DEFAULT 0,
  `is_win`          tinyint(1) NOT NULL DEFAULT 0,
  `consume_points`  int NOT NULL DEFAULT 0,
  `angle`           decimal(8,3) NOT NULL DEFAULT 0.000 COMMENT '转盘落点角度',
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lottery_log_uid_time` (`uid`, `created_at`),
  KEY `idx_lottery_log_lottery` (`lottery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录';

DROP TABLE IF EXISTS `activity_points_logs`;
CREATE TABLE `activity_points_logs` (
  `id`             bigint unsigned NOT NULL,
  `uid`            bigint unsigned NOT NULL,
  `change_points`  int NOT NULL DEFAULT 0,
  `balance_after`  int NOT NULL DEFAULT 0,
  `biz_type`       varchar(32) NOT NULL DEFAULT '' COMMENT 'sign/lottery/merge/invite/admin',
  `biz_id`         bigint unsigned NOT NULL DEFAULT 0,
  `remark`         varchar(512) NOT NULL DEFAULT '',
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_points_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='积分流水';

DROP TABLE IF EXISTS `activity_recast_prizes`;
CREATE TABLE `activity_recast_prizes` (
  `id`          bigint unsigned NOT NULL,
  `type_id`     bigint unsigned NOT NULL DEFAULT 0,
  `doll_type`   varchar(32) NOT NULL DEFAULT '',
  `name`        varchar(128) NOT NULL DEFAULT '',
  `image`       varchar(255) NOT NULL DEFAULT '',
  `weight`      int NOT NULL DEFAULT 0,
  `probability` decimal(6,2) NOT NULL DEFAULT 0.00 COMMENT '中奖概率，整数百分比（如 43.00 表示 43%）',
  `quantity`    int NOT NULL DEFAULT 0,
  `status`      varchar(32) NOT NULL DEFAULT 'enabled',
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recast_prize_pool` (`type_id`, `doll_type`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='重铸奖池';

DROP TABLE IF EXISTS `activity_recast_logs`;
CREATE TABLE `activity_recast_logs` (
  `id`             bigint unsigned NOT NULL,
  `uid`            bigint unsigned NOT NULL,
  `prize_id`       bigint unsigned NOT NULL DEFAULT 0,
  `is_win`         tinyint(1) NOT NULL DEFAULT 0,
  `consume_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `created_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recast_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='重铸记录';

DROP TABLE IF EXISTS `product_merge_records`;
CREATE TABLE `product_merge_records` (
  `id`         bigint unsigned NOT NULL,
  `uid`        bigint unsigned NOT NULL,
  `mid`        bigint unsigned NOT NULL,
  `status`     varchar(32) NOT NULL DEFAULT 'success',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pm_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成活动记录';

DROP TABLE IF EXISTS `ai_answers`;
CREATE TABLE `ai_answers` (
  `id`         bigint unsigned NOT NULL,
  `uid`        bigint unsigned NOT NULL,
  `question`   varchar(1024) NOT NULL DEFAULT '',
  `answer`     mediumtext NULL,
  `session_id` varchar(64) NOT NULL DEFAULT '',
  `token_used` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_uid_time` (`uid`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI问答';

-- ============================================================
-- 7. IM 域
-- ============================================================

DROP TABLE IF EXISTS `im_groups`;
CREATE TABLE `im_groups` (
  `id`              bigint unsigned NOT NULL,
  `name`            varchar(128) NOT NULL DEFAULT '',
  `avatar`          varchar(255) NOT NULL DEFAULT '',
  `avatar_url`      varchar(512) NOT NULL DEFAULT '',
  `status`          varchar(32) NOT NULL DEFAULT 'normal',
  `user_total`      int NOT NULL DEFAULT 0,
  `blacklist_total` int NOT NULL DEFAULT 0,
  `creator_uid`     bigint unsigned NOT NULL DEFAULT 0,
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_deleted`      tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_im_groups_status` (`status`, `is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群组';

DROP TABLE IF EXISTS `im_group_members`;
CREATE TABLE `im_group_members` (
  `id`            bigint unsigned NOT NULL,
  `gid`           bigint unsigned NOT NULL,
  `uid`           bigint unsigned NOT NULL,
  `nickname`      varchar(64) NOT NULL DEFAULT '',
  `is_blacklist`  tinyint(1) NOT NULL DEFAULT 0,
  `status`        varchar(32) NOT NULL DEFAULT 'joined',
  `joined_at`     datetime NULL DEFAULT NULL,
  `created_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_im_member_gid_uid` (`gid`, `uid`),
  KEY `idx_im_member_uid` (`uid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群成员';

DROP TABLE IF EXISTS `im_group_messages`;
CREATE TABLE `im_group_messages` (
  `id`          bigint unsigned NOT NULL,
  `gid`         bigint unsigned NOT NULL,
  `uid`         bigint unsigned NOT NULL,
  `type`        varchar(32) NOT NULL DEFAULT 'text' COMMENT 'text/image/card/system',
  `content`     text NULL,
  `extra`       json NULL,
  `is_recalled` tinyint(1) NOT NULL DEFAULT 0,
  `sent_at`     datetime NOT NULL,
  `created_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_im_msg_gid_time` (`gid`, `sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='群消息';

DROP TABLE IF EXISTS `im_user_sessions`;
CREATE TABLE `im_user_sessions` (
  `id`              bigint unsigned NOT NULL,
  `uid`             bigint unsigned NOT NULL,
  `peer_uid`        bigint unsigned NOT NULL,
  `last_message_id` bigint unsigned NOT NULL DEFAULT 0,
  `last_message_at` datetime NULL DEFAULT NULL,
  `unread_count`    int NOT NULL DEFAULT 0,
  `created_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_im_session` (`uid`, `peer_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='单聊会话';

-- ============================================================
-- 8. 系统域
-- ============================================================

DROP TABLE IF EXISTS `app_versions`;
CREATE TABLE `app_versions` (
  `id`           bigint unsigned NOT NULL,
  `platform`     varchar(32) NOT NULL DEFAULT 'web',
  `version`      varchar(32) NOT NULL DEFAULT '',
  `min_version`  varchar(32) NOT NULL DEFAULT '',
  `force_update` tinyint(1) NOT NULL DEFAULT 0,
  `description`  varchar(1024) NOT NULL DEFAULT '',
  `download_url` varchar(512) NOT NULL DEFAULT '',
  `status`       varchar(32) NOT NULL DEFAULT 'enabled',
  `created_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_version_platform` (`platform`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='版本检查';

DROP TABLE IF EXISTS `common_rules`;
CREATE TABLE `common_rules` (
  `id`         bigint unsigned NOT NULL,
  `rule_key`   varchar(64) NOT NULL COMMENT 'user_agreement/privacy',
  `title`      varchar(255) NOT NULL DEFAULT '',
  `content`    mediumtext NULL,
  `version`    varchar(32) NOT NULL DEFAULT '',
  `status`     varchar(32) NOT NULL DEFAULT 'enabled',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rule_key` (`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='通用规则';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 初始化基础数据
-- ============================================================

INSERT INTO `notice_categories` (`id`, `cid`, `name`, `sort`) VALUES
  (1, 1, '平台公告', 1),
  (2, 2, '市场公告', 2);

INSERT INTO `common_rules` (`id`, `rule_key`, `title`, `version`) VALUES
  (1, 'user_agreement', '用户协议', '1.0'),
  (2, 'privacy', '隐私政策', '1.0');

INSERT INTO `app_versions` (`id`, `platform`, `version`, `min_version`, `force_update`, `description`) VALUES
  (1, 'web', '1.3.2', '1.0.0', 0, '当前版本');
