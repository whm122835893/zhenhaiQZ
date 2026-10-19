# 盛泰数字藏品平台 · 数据库设计文档

> 版本：v1.0
> 生成日期：2026-07-19
> 数据来源：源站 `https://h5.shengtai.cyou` 运行时接口抓取（`api.shengtai.cyou`）+ 6 个源站分包模块契约交叉验证（`item` / `nft` / `user` / `order` / `activity` / `basic`）
> 目标数据库：MySQL 8.0+
> 字符集：`utf8mb4` / `utf8mb4_0900_ai_ci`
> 存储引擎：InnoDB
> 框架：ThinkPHP 6（`think-orm`）

---

## 0. 设计基线（design-db 规范）

| 规则 | 说明 |
|------|------|
| 表名 | 小写 + 下划线，复数形式（`users`、`nft_orders`） |
| 主键 | 统一 `id`，来源为源站雪花 ID（19 位，字符串形态 `2109928427009183745`） |
| 审计字段 | 必含 `created_at`、`updated_at`，风格统一为 `datetime` |
| 软删除 | 业务主表含 `is_deleted tinyint(1)` + `deleted_at datetime`（保留审计的流水表不加） |
| 金额 | 一律 `decimal(20,2)`，**存「分」**（源站接口返回的就是分，本地不做换算，与源站保持 1:1） |
| 枚举 | **禁 ENUM**，用 `varchar(32)` + 应用层常量类约束 |
| 外键 | 逻辑外键 + 索引，不建物理 FK |
| 索引 | 组合索引遵循「等值列在前，范围/排序列在后」 |
| 时间 | 一律 `datetime`（源站 13 位毫秒时间戳在 DAO 层转换） |
| 冗余 | 高频读取的统计字段冗余在主表（`trade_quantity`、`today_trade_amount`），由业务层事务更新 |
| 图片 | 统一存完整 URL 字符串（源站返回既有完整 URL 又有相对路径两个字段） |

### 源站关键数据特征（决定字段类型）

| 特征 | 源站表现 | 设计对策 |
|------|---------|---------|
| 主键 | 19 位雪花 ID，如 `2109928427009183745`（超 `bigint` 安全 JS 范围） | `bigint unsigned` + API 层序列化为字符串 |
| 时间 | 13 位毫秒**字符串**，如 `"1791959018537"` | `bigint` 存原始值 + `datetime` 冗余便于查询 |
| 金额 | **源站所有金额字段单位统一为「分」，不存在混用**。实测同一响应 `iid=2109928427009183745`：`price=388`、`secondaryHighPrice=500`、`todayTradeAmount=5649000`，前端对三者统一调用 `amountFormat(v)="￥"+Number(v/100).toFixed(2)` | 一律存**分** `decimal(20,2)`，DAO 层**原样返回不换算**（换算了就与源站不一致）。逐字段对照表见 `docs/api.md` 3.1.3 |
| 图片 | `imageUrl`（CDN 绝对）+ `image`（相对路径）双字段 | 双字段并存，保持与源站响应结构 1:1 |
| 脱敏 | `175****1293`、`王*绵`、`5202****9838` | 存原文，脱敏在响应层处理 |
| 布尔 | `isPrimary`、`isSoldOut` 等 0/1 整数 | `tinyint(1)` |

---

## 1. 分域概览

```
用户域   users / user_profiles / user_wallets / user_invites / user_login_logs / user_online_sessions
藏品域   item_categories / item_authors / items / item_follows / item_copyrights / product_tp_categories
资产域   nfts / nft_groups / nft_merge_recipes / nft_merge_records
交易域   orders / order_items / order_payments / nft_sell_orders / nft_sell_apply_orders
        nft_market_sell_orders / nft_market_apply_orders / nft_market_trust_orders
        nft_market_follows / nft_trade_logs / nft_box_open_logs
内容域   banners / notice_categories / notices
活动域   activity_lotteries / activity_lottery_prizes / activity_lottery_logs
        activity_sign_records / activity_recast_prizes / activity_recast_logs
        activity_points_logs / product_merge_records / ai_answers
IM 域    im_groups / im_group_members / im_group_messages / im_blacklists
系统域   app_versions / common_rules
```

---

## 2. 用户与鉴权域

### 2.1 `users` — 用户账号

源站依据：`GET /passport/account`、`POST /passport/signup`、`POST /passport/signin`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 雪花 ID |
| phone | varchar(20) UK | 手机号（登录账号） |
| email | varchar(128) IDX | 邮箱 |
| account | varchar(64) | 登录名（源站 `account` 字段返回 `175****1293`） |
| password_hash | varchar(255) | bcrypt 密码散列 |
| salt | varchar(32) | 密码盐 |
| has_password | tinyint(1) | 是否已设密码（源站 `hasPassword`） |
| has_second_password | tinyint(1) | 是否已设二级密码（源站 `hasSecondPassword`） |
| second_password_hash | varchar(255) | 二级密码散列（寄售/求购成交校验用） |
| has_real_name | tinyint(1) | 是否已实名（源站 `hasRealName`） |
| real_name | varchar(64) | 实名姓名 |
| id_card | varchar(32) | 身份证号 |
| status | varchar(32) | `normal` / `frozen` / `banned` |
| last_login_at | datetime | 最后登录时间 |
| created_at / updated_at | datetime | 审计 |
| is_deleted | tinyint(1) | 软删除 |

索引：`uk_users_phone(phone)`、`idx_users_email(email)`、`idx_users_status(status)`

### 2.2 `user_profiles` — 用户资料

源站依据：`GET /user/profile`、`PUT /user/profile/nickname`、`PUT /user/profile/introduction`、`POST /user/profile/avatar`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 与 users.id 一致（1:1） |
| uid | bigint unsigned IDX | 用户 ID（源站响应字段名 `uid`） |
| nickname | varchar(64) | 昵称 |
| avatar | varchar(255) | 头像相对路径（源站 `avatar`） |
| avatar_url | varchar(512) | 头像 CDN 绝对地址 |
| sex | tinyint(1) | 0 未知 / 1 男 / 2 女 |
| birthday | date | 生日 |
| country | varchar(64) | 国家 |
| region_code | varchar(32) | 地区码（源站 `regionCode`） |
| introduction | varchar(512) | 个人简介 |
| chain_address | varchar(128) | 区块链地址（源站 `chainAddress`） |
| invite_code | varchar(32) UK | 我的邀请码（源站 `inviteCode`） |
| inviter | bigint unsigned IDX | 邀请人 UID（源站 `inviter`） |
| token | varchar(128) | 用户访问令牌 |
| created_at / updated_at | datetime | 审计 |

索引：`uk_profiles_uid(uid)`、`uk_profiles_invite_code(invite_code)`、`idx_profiles_inviter(inviter)`

### 2.3 `user_wallets` — 用户资源/余额

源站依据：`GET /user/resource`（实测当前返回 `[]`，但账号页有余额展示位）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned UK | 用户 ID |
| balance | decimal(20,2) | 可用余额（元） |
| frozen_balance | decimal(20,2) | 冻结余额 |
| total_income | decimal(20,2) | 累计收入 |
| total_expense | decimal(20,2) | 累计支出 |
| currency | varchar(8) | 默认 `CNY` |
| created_at / updated_at | datetime | 审计 |

### 2.4 `user_invites` — 邀请记录

源站依据：`GET /user/invite` → `{ list: [], total: 0 }`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 邀请人 |
| invitee_uid | bigint unsigned IDX | 被邀请人 |
| invite_code | varchar(32) | 使用的邀请码 |
| reward_amount | decimal(20,2) | 奖励金额 |
| reward_status | varchar(32) | `pending` / `granted` / `invalid` |
| rewarded_at | datetime | 发放时间 |
| created_at / updated_at | datetime | 审计 |

索引：`idx_invites_uid_uid(uid, invitee_uid)`、`idx_invites_code(invite_code)`

### 2.5 `user_login_logs` — 登录日志

源站依据：`POST /passport/signin` / `signin/quick` / `signin/oneClick`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 ID（失败时为 0） |
| account | varchar(64) | 登录账号 |
| login_type | varchar(32) | `password` / `quick` / `oneClick` / `sms` |
| ip | varchar(64) | |
| device_id | varchar(64) | 源站公共参数 `deviceId` |
| device_model | varchar(64) | 源站公共参数 `deviceModel`（如 `PC`） |
| platform | varchar(32) | 源站公共参数 `platform`（`web`） |
| status | varchar(32) | `success` / `failed` |
| fail_reason | varchar(255) | |
| created_at / updated_at | datetime | 审计 |

索引：`idx_login_uid_time(uid, created_at)`、`idx_login_account_time(account, created_at)`

### 2.6 `user_online_sessions` — 在线会话/设备

源站依据：`GET /passport/online`、`GET /passport/online/{id}`、`DELETE /passport/online/{id}`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 ID |
| device_id | varchar(64) | 设备唯一标识 |
| device_model | varchar(64) | |
| platform | varchar(32) | |
| ip | varchar(64) | |
| login_at | datetime | 登录时间 |
| last_active_at | datetime | 最后活跃 |
| is_online | tinyint(1) | 是否在线 |

索引：`idx_online_uid(uid, is_online)`

---

## 3. 藏品目录域

### 3.1 `item_categories` — 藏品分类

源站依据：`GET /item/category` → `[{ cid, name, sort }]`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| cid | bigint unsigned UK | 分类 ID（源站字段） |
| name | varchar(64) | 分类名 |
| cover | varchar(255) | 封面相对路径 |
| cover_url | varchar(512) | 封面绝对地址 |
| sort | int | 排序权重（源站 `sort`） |
| status | varchar(32) | `enabled` / `disabled` |
| created_at / updated_at | datetime | 审计 |

### 3.2 `item_authors` — 作者/创作者

源站依据：`GET /item/author` → `[{ id, name, avatarUrl, avatar, introduction }]`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| name | varchar(64) | 作者名 |
| avatar | varchar(255) | 头像相对路径 |
| avatar_url | varchar(512) | 头像绝对地址 |
| introduction | varchar(1024) | 简介 |
| works_count | int | 作品数 |
| follower_count | int | 粉丝数 |
| created_at / updated_at | datetime | 审计 |

索引：`idx_authors_name(name)`

### 3.3 `items` — 藏品（核心主表）

源站依据：`GET /item`、`GET /item/{iid}`。实测响应字段全集：

```
iid, cid, authorId, contract, name, imageUrl, image, content, price, type,
issueQuantity, buyQuantity, preStartTime, preEndTime, startTime, endTime,
isPrimary, isPrimaryApply, isRecommend, isPopular, isGive, isResale,
isSecondary, isSecondaryBatch, isSecondaryApply, isSecondaryTrust,
secondaryLowPrice, secondaryHighPrice, boxOpenTime, isSoldOut, nftQuantity
```

| 字段 | 类型 | 源站字段 | 说明 |
|------|------|---------|------|
| id | bigint unsigned PK | | |
| iid | bigint unsigned UK | iid | 藏品 ID，雪花 |
| cid | bigint unsigned IDX | cid | 分类 ID |
| author_id | bigint unsigned IDX | authorId | 作者 ID |
| name | varchar(128) | name | 藏品名称 |
| contract | varchar(128) | contract | 合约地址 |
| image | varchar(255) | image | 图片相对路径 |
| image_url | varchar(512) | imageUrl | 图片绝对地址 |
| content | mediumtext | content | 图文详情（HTML） |
| price | decimal(20,2) | price | 一级市场价格（**分**，源站原样，前端 ÷100） |
| type | varchar(32) | type | 藏品类型 |
| issue_quantity | int | issueQuantity | 发行总量（实测 888888） |
| buy_quantity | int | buyQuantity | 已购数量 |
| nft_quantity | int | nftQuantity | 已铸造 NFT 数（实测 2500 / 1484） |
| pre_start_time | datetime | preStartTime | 预热开始 |
| pre_end_time | datetime | preEndTime | 预热结束 |
| start_time | datetime | startTime | 发售开始 |
| end_time | datetime | endTime | 发售结束 |
| box_open_time | datetime | boxOpenTime | 开箱时间 |
| is_primary | tinyint(1) | isPrimary | 允许一级市场 |
| is_primary_apply | tinyint(1) | isPrimaryApply | 允许一级求购 |
| is_recommend | tinyint(1) | isRecommend | 首页推荐 |
| is_popular | tinyint(1) | isPopular | 热门 |
| is_give | tinyint(1) | isGive | 可赠送 |
| is_resale | tinyint(1) | isResale | 可转售 |
| is_secondary | tinyint(1) | isSecondary | 允许二级市场（一口价） |
| is_secondary_batch | tinyint(1) | isSecondaryBatch | 允许批量购买 |
| is_secondary_apply | tinyint(1) | isSecondaryApply | 允许求购 |
| is_secondary_trust | tinyint(1) | isSecondaryTrust | 允许委托托卖 |
| secondary_low_price | decimal(20,2) | secondaryLowPrice | 二级最低价 |
| secondary_high_price | decimal(20,2) | secondaryHighPrice | 二级最高价 |
| secondary_apply_low_price | decimal(20,2) | secondaryApplyLowPrice | 求购最低价 |
| secondary_apply_high_price | decimal(20,2) | secondaryApplyHighPrice | 求购最高价 |
| sell_quantity | int | sellQuantity | 在售数量 |
| trade_quantity | int | tradeQuantity | 成交数量（实测 590） |
| trade_amount | decimal(20,2) | tradeAmount | 累计成交额 |
| last_trade_amount | decimal(20,2) | lastTradeAmount | 最新成交价 |
| today_trade_quantity | int | todayTradeQuantity | 今日成交数（实测 12） |
| hour_trade_quantity | int | hourTradeQuantity | 小时成交数 |
| today_trade_amount | decimal(20,2) | todayTradeAmount | 今日成交额（实测 1257000 分） |
| today_trade_max_amount | decimal(20,2) | todayTradeMaxAmount | 今日最高价 |
| today_trade_min_amount | decimal(20,2) | todayTradeMinAmount | 今日最低价 |
| price_change | decimal(10,4) | priceChange | 涨跌幅 |
| is_sold_out | tinyint(1) | isSoldOut | 是否售罄 |
| view_count | int | — | 浏览量 |
| follower_count | int | — | 关注数 |
| status | varchar(32) | — | `on` / `off` / `sold_out` |
| created_at / updated_at | datetime | | 审计 |
| is_deleted | tinyint(1) | | 软删除 |

索引：
- `uk_items_iid(iid)`
- `idx_items_cid_status(cid, status, is_deleted)` — 分类页列表
- `idx_items_author(author_id)`
- `idx_items_recommend(is_recommend, status)` — 首页推荐
- `idx_items_popular(is_popular, status)`
- `idx_items_start_time(start_time)` — 发售中筛选
- `idx_items_created(created_at)`

### 3.4 `item_follows` — 藏品关注

源站依据：`POST /item/follow/{iid}`、`DELETE /item/unfollow/{iid}`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned IDX | 藏品 ID |
| uid | bigint unsigned IDX | 用户 ID |
| created_at / updated_at | datetime | 审计 |

索引：`uk_follow_iid_uid(iid, uid)`

### 3.5 `item_copyrights` — 版权信息

源站依据：`GET /item/copyright`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned IDX | 藏品 ID |
| title | varchar(255) | 标题 |
| content | mediumtext | 正文 |
| sort | int | 排序 |
| created_at / updated_at | datetime | 审计 |

### 3.6 `product_tp_categories` / `product_tp_items` — 图谱

源站依据：`GET /product/tp_category`、`GET /product/tp_list`

`product_tp_categories`：`id / name / image / image_url / sort / created_at / updated_at`
`product_tp_items`：`id / cid / name / image / image_url / iid / sort / created_at / updated_at`
索引：`uk_tp_cat_name(name)`、`idx_tp_items_cid(cid, sort)`

---

## 4. 资产域（NFT）

### 4.1 `nfts` — 用户持有的 NFT 资产

源站依据：`GET /nft`、`GET /nft/{nftId}`。实测响应字段：

```
nftId, uid, iid, name, imageUrl, image, token, status, getTime,
price, payLock, payWay, chainId, chainCompleted, buyPrice, item{...}
```

| 字段 | 类型 | 源站字段 | 说明 |
|------|------|---------|------|
| id | bigint unsigned PK | | |
| nft_id | bigint unsigned UK | nftId | 资产 ID（源站仓库数据用） |
| uid | bigint unsigned IDX | uid | 持有人 |
| iid | bigint unsigned IDX | iid | 藏品 ID |
| name | varchar(128) | name | 冗余藏品名 |
| image | varchar(255) | image | 冗余图片 |
| image_url | varchar(512) | imageUrl | 冗余图片绝对地址 |
| token | varchar(128) | token | 链上 token（实测 564851） |
| status | varchar(32) | status | `0` 正常 / 冻结 / 已售出（源站 status=0） |
| get_time | datetime | getTime | 获得时间 |
| price | decimal(20,2) | price | 当前挂牌价 |
| buy_price | decimal(20,2) | buyPrice | 买入价 |
| pay_lock | tinyint(1) | payLock | 支付锁 |
| pay_way | tinyint(1) | payWay | 支付方式 |
| chain_id | varchar(128) | chainId | 链上 ID |
| chain_completed | tinyint(1) | chainCompleted | 上链完成 |
| sell_status | varchar(32) | — | `none` / `on_sale` / `sold`（仓库 tab 用） |
| created_at / updated_at | datetime | | 审计 |
| is_deleted | tinyint(1) | | 软删除 |

索引：
- `uk_nfts_nft_id(nft_id)`
- `idx_nfts_uid_status(uid, sell_status, is_deleted)` — 仓库列表主查询
- `idx_nfts_iid(iid)`

### 4.2 `nft_groups` — 仓库分组统计

源站依据：`GET /nft/group` → `[{ iid, name, imageUrl, image, type, ownQuantity, minPrice, priceChange }]`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned | 藏品 ID |
| name | varchar(128) | 藏品名 |
| image / image_url | varchar | 图片 |
| type | varchar(32) | 分组类型（对应源站仓库 tab id 0/2/4） |
| own_quantity | int | 持有数量 |
| min_price | decimal(20,2) | 最低价 |
| price_change | decimal(10,4) | 涨跌幅 |

> 该表为**聚合视图表**，由 `nfts` 聚合刷新，不作为数据真源。

### 4.3 `nft_merge_recipes` — 合成配方

源站依据：`GET /item/merge`、`GET /item/merge/{id}`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| mid | bigint unsigned UK | 合成 ID（源站路径参数） |
| name | varchar(128) | 合成名 |
| image / image_url | varchar | 图 |
| target_iid | bigint unsigned IDX | 目标藏品 ID |
| target_quantity | int | 产出数量 |
| consume_points | int | 消耗积分 |
| description | varchar(1024) | 说明 |
| status | varchar(32) | |
| created_at / updated_at / is_deleted | | |

### 4.4 `nft_merge_records` — 合成记录

源站依据：`GET /product_merge/record`、`POST /nft/merge/v1`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 操作人 |
| mid | bigint unsigned IDX | 配方 ID |
| consume_nft_ids | text | 消耗的 NFT ID 列表（JSON） |
| produce_nft_ids | text | 产出的 NFT ID 列表（JSON） |
| status | varchar(32) | `success` / `failed` |
| created_at / updated_at | | |

索引：`idx_merge_uid_time(uid, created_at)`

### 4.5 `nft_box_open_logs` — 开箱记录

源站依据：`POST /nft/box/open`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 |
| iid | bigint unsigned IDX | 盲盒藏品 |
| nft_id | bigint unsigned | 开出的 NFT |
| cost_amount | decimal(20,2) | 消耗 |
| pay_way | tinyint(1) | 支付方式 |
| created_at / updated_at | | |

---

## 5. 交易域

### 5.1 `orders` — 订单主表

源站依据：`GET /order`、`GET /order/{id}`、`POST order/primary`、`POST order/secondary`、`POST order/secondary/quick`、`POST order/secondary/batch`、`POST order/secondary/apply`、`POST order/secondary/trust`、`PUT /order/cancel`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 雪花订单号 |
| order_no | varchar(32) UK | 业务单号 |
| uid | bigint unsigned IDX | 买家 UID |
| iid | bigint unsigned IDX | 藏品 ID |
| order_type | varchar(32) | `primary` / `secondary` / `secondary_quick` / `secondary_batch` / `secondary_apply` / `secondary_trust` |
| pay_way | tinyint(1) | 1 余额 / 2 汇付 / 3 易宝 |
| status | varchar(32) | `pending_pay` / `paid` / `cancelled` / `finished` / `refunded` |
| total_amount | decimal(20,2) | 订单总额 |
| pay_amount | decimal(20,2) | 实付金额 |
| fee_amount | decimal(20,2) | 手续费 |
| quantity | int | 数量（批量购买 > 1） |
| unit_price | decimal(20,2) | 单价（二级下单必传 `unitPrice`） |
| seller_uid | bigint unsigned IDX | 卖家 UID（一口价场景） |
| related_order_id | bigint unsigned | 关联的求购/委托单 ID |
| pay_expire_at | datetime | 支付过期时间 |
| paid_at | datetime | 支付时间 |
| cancelled_at | datetime | 取消时间 |
| remark | varchar(512) | 备注 |
| created_at / updated_at | datetime | 审计 |
| is_deleted | tinyint(1) | 软删除 |

索引：
- `uk_orders_order_no(order_no)`
- `idx_orders_uid_status(uid, status, created_at)` — 我的订单列表
- `idx_orders_iid_status(iid, status)` — 市场成交
- `idx_orders_seller(seller_uid, status)`

### 5.2 `order_items` — 订单明细（二级批量场景）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| order_id | bigint unsigned IDX | 订单 ID |
| nft_id | bigint unsigned IDX | 具体 NFT |
| price | decimal(20,2) | 成交价 |
| seller_uid | bigint unsigned IDX | 卖家 |
| status | varchar(32) | |
| created_at / updated_at | | |

索引：`idx_order_items_order(order_id)`、`idx_order_items_nft(nft_id)`

### 5.3 `order_payments` — 支付流水

源站依据：`POST /order/pay/hfpay`（返回第三方收银台 URL）、`POST /order/pay/yeepay`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| order_id | bigint unsigned IDX | 订单 ID |
| uid | bigint unsigned IDX | |
| channel | varchar(32) | `hfpay` / `yeepay` / `balance` |
| trade_no | varchar(64) | 第三方交易号 |
| cashier_url | varchar(1024) | 收银台地址 |
| amount | decimal(20,2) | 金额 |
| status | varchar(32) | `pending` / `success` / `failed` / `closed` |
| paid_at | datetime | |
| notify_payload | json | 回调原始报文 |
| created_at / updated_at | | |

索引：`uk_pay_trade_no(trade_no)`、`idx_pay_order(order_id)`

### 5.4 `nft_sell_orders` — 一级/个人寄售挂单

源站依据：`POST /nft/sell/up`（单条寄售）、`POST /nft/sell/up/batch`（批量寄售）、`PUT /nft/sell/down`（下架）、`GET /nft/sell/sold`（已售）、`GET /nft/secondary/sell`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| nft_id | bigint unsigned IDX | NFT |
| iid | bigint unsigned IDX | 藏品 |
| uid | bigint unsigned IDX | 卖家 |
| price | decimal(20,2) | 挂单价 |
| quantity | int | 数量（批量） |
| sold_quantity | int | 已售数量 |
| market_type | varchar(32) | `sell` 寄售 / `apply` 求购 / `trust` 委托 |
| status | varchar(32) | `on_sale` / `sold` / `off_shelf` / `cancelled` |
| sold_at | datetime | 成交时间 |
| buyer_uid | bigint unsigned IDX | 买家（二级成交回填） |
| created_at / updated_at / is_deleted | | |

索引：
- `idx_sell_uid_status(uid, status, is_deleted)` — 我的寄售列表
- `idx_sell_iid_status(iid, status, price)` — 市场在售列表
- `idx_sell_nft(nft_id)`

### 5.5 `nft_market_apply_orders` — 求购单

源站依据：`POST /nft/sell/apply`（提交求购）、`POST order/secondary/apply`、`GET /nft/secondary/apply`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned IDX | 藏品 |
| uid | bigint unsigned IDX | 求购人 |
| unit_price | decimal(20,2) | 求购单价 |
| quantity | int | 求购数量 |
| filled_quantity | int | 已成交数量 |
| status | varchar(32) | `open` / `partial` / `filled` / `cancelled` |
| expire_at | datetime | 过期时间 |
| created_at / updated_at / is_deleted | | |

索引：`idx_apply_iid_status(iid, status, unit_price)`、`idx_apply_uid(uid, status)`

### 5.6 `nft_market_trust_orders` — 委托托卖单

源站依据：`POST order/secondary/trust`、`GET /nft/secondary/trust`

字段同 5.5，额外增加 `entrust_uid`（委托人）、`agent_rate`（佣金比例）。

### 5.7 `nft_market_follows` — 市场关注

源站依据：`GET /nft/secondary/follow`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned IDX | 藏品 |
| uid | bigint unsigned IDX | 用户 |
| created_at / updated_at | | |

索引：`uk_mf_iid_uid(iid, uid)`

### 5.8 `nft_trade_logs` — 成交/流转记录

源站依据：`GET /nft/secondary/sold`（市场成交记录）

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| iid | bigint unsigned IDX | 藏品 |
| nft_id | bigint unsigned IDX | NFT |
| order_id | bigint unsigned IDX | 订单 |
| seller_uid | bigint unsigned IDX | 卖家 |
| buyer_uid | bigint unsigned IDX | 买家 |
| price | decimal(20,2) | 成交价 |
| trade_type | varchar(32) | `sell` / `batch` / `need` / `trust` |
| traded_at | datetime | 成交时间（源站 13 位毫秒） |
| created_at / updated_at | | |

索引：`idx_trade_iid_time(iid, traded_at)`、`idx_trade_buyer(buyer_uid, traded_at)`、`idx_trade_seller(seller_uid, traded_at)`

### 5.9 `wallet_transactions` — 资金流水

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 |
| order_id | bigint unsigned IDX | 关联订单 |
| trans_type | varchar(32) | `recharge` / `withdraw` / `buy` / `sell` / `fee` / `reward` |
| amount | decimal(20,2) | 变动金额（正入负出） |
| balance_after | decimal(20,2) | 变动后余额 |
| remark | varchar(512) | |
| created_at / updated_at | | |

索引：`idx_wallet_uid_time(uid, created_at)`

---

## 6. 内容域

### 6.1 `banners` — 首页轮播

源站依据：`GET /basic/banner`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| title | varchar(128) | |
| image | varchar(255) | |
| image_url | varchar(512) | |
| link_type | varchar(32) | `none` / `item` / `notice` / `url` |
| link_value | varchar(512) | 跳转目标 |
| position | varchar(32) | `index` / `market` / `mine` |
| sort | int | |
| status | varchar(32) | |
| created_at / updated_at / is_deleted | | |

索引：`idx_banner_pos(position, status, sort)`

### 6.2 `notice_categories` — 公告分类

源站依据：`GET /basic/notice/category` → `[{ cid, name, coverUrl, cover, sort }]`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| cid | bigint unsigned UK | 分类 ID |
| name | varchar(64) | 名称 |
| cover / cover_url | varchar | 封面 |
| sort | int | |

### 6.3 `notices` — 公告

源站依据：`GET /basic/notice`、`GET /basic/notice/{id}` → `{ id, cid, title, content, createTime, category{cid,name,coverUrl,cover} }`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| cid | bigint unsigned IDX | 分类 ID |
| title | varchar(255) | 标题 |
| content | mediumtext | 正文 HTML |
| cover / cover_url | varchar | 封面 |
| view_count | int | 阅读量 |
| is_top | tinyint(1) | 置顶 |
| published_at | datetime | 发布时间（源站 `createTime`） |
| created_at / updated_at / is_deleted | | |

索引：`idx_notice_cid_time(cid, published_at)`、`idx_notice_top(is_top, published_at)`

---

## 7. 活动域

### 7.1 `activity_sign_records` — 签到

源站依据：`GET /activity/sign` → `{ records: boolean[31], signDays: int }`、`POST /activity/sign`

> 注意 源站仅返回当月 `records` 布尔数组（按天索引，0 起），前端按 `records[day-1]` 标记。

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 |
| sign_year | int | 年 |
| sign_month | int | 月（1-12） |
| sign_day | int | 日（1-31） |
| sign_days | int | 当月累计签到天数（冗余，对应源站 `signDays`） |
| continuous_days | int | 连续签到天数 |
| reward_points | int | 奖励积分 |
| sign_time | datetime | 签到时刻 |
| created_at / updated_at | | |

索引：`uk_sign_uid_ymd(uid, sign_year, sign_month, sign_day)`

### 7.2 `activity_lotteries` — 抽奖活动

源站依据：`GET /activity/lottery/active`、`GET /activity/lottery`、`GET /activity/lottery/{id}`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 源站 `lotteryInfo.id` |
| name | varchar(128) | |
| cover / cover_url | varchar | 转盘底图 |
| points | int | 单次抽奖消耗积分（前端算 `floor(balance/points)` 得抽奖次数） |
| start_time / end_time | datetime | 活动周期 |
| rule | text | 抽奖规则（前端弹窗展示） |
| status | varchar(32) | `active` / `inactive` |
| created_at / updated_at / is_deleted | | |

索引：`idx_lottery_status(status, start_time, end_time)`

### 7.3 `activity_lottery_prizes` — 奖品

源站依据：`GET /activity/lottery/{id}` → `prizeList`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | 源站 `prize.id`（抽奖结果按此匹配） |
| lottery_id | bigint unsigned IDX | 活动 ID |
| name | varchar(128) | 奖品名 |
| image / image_url | varchar | 奖品图 |
| level | varchar(32) | 奖项等级 |
| probability | decimal(5,4) | 中奖概率 |
| quantity | int | 库存 |
| sort | int | 转盘排序（前端 `step = 360 / len`，索引即扇区） |

索引：`idx_lottery_prize_lottery(lottery_id, sort)`

### 7.4 `activity_lottery_logs` — 抽奖记录

源站依据：`POST /activity/lottery/start/{id}`、`GET /activity/lottery/log`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 用户 |
| lottery_id | bigint unsigned IDX | 活动 |
| prize_id | bigint unsigned IDX | 中奖奖品 |
| is_win | tinyint(1) | 是否中奖（前端据此弹中奖窗） |
| consume_points | int | 消耗积分 |
| angle | decimal(8,3) | 转盘落点角度（前端 `270 - step*(idx + 0.5)`） |
| created_at / updated_at | | |

索引：`idx_lottery_log_uid_time(uid, created_at)`

### 7.5 `activity_points_logs` — 积分流水

源站依据：`GET /activity/points/balance`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | |
| change_points | int | 变动（正负） |
| balance_after | int | 变动后余额 |
| biz_type | varchar(32) | `sign` / `lottery` / `merge` / `invite` / `admin` |
| biz_id | bigint unsigned | 业务 ID |
| remark | varchar(512) | |
| created_at / updated_at | | |

索引：`idx_points_uid_time(uid, created_at)`

### 7.6 `activity_recast_prizes` — 重铸奖品池

源站依据：`GET /activity/recast/prize`、`GET /activity/recast/doll-type`、`GET /activity/recast/draw-num`、`POST /activity/recast/draw`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| type_id | bigint unsigned IDX | 奖池类型（`/recast/type`） |
| doll_type | varchar(32) | 玩法类型（`/recast/doll-type`） |
| name | varchar(128) | 奖品名 |
| weight | int | 权重 |
| probability | decimal(5,4) | 概率 |
| quantity | int | 库存 |
| status | varchar(32) | |

### 7.7 `activity_recast_logs` — 重铸记录

源站依据：`GET /activity/recast/log`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | |
| prize_id | bigint unsigned IDX | 奖品 |
| is_win | tinyint(1) | |
| consume_amount | decimal(20,2) | 消耗 |
| created_at / updated_at | | |

索引：`idx_recast_uid_time(uid, created_at)`

### 7.8 `product_merge_records` — 合成活动记录

源站依据：`GET /product_merge`、`GET /product_merge/record`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | |
| mid | bigint unsigned IDX | 配方 |
| status | varchar(32) | |
| created_at / updated_at | | |

### 7.9 `ai_answers` — AI 助理问答

源站依据：`POST /ai/answer/get_answer`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| uid | bigint unsigned IDX | 提问用户 |
| question | varchar(1024) | 问题 |
| answer | mediumtext | 回答 |
| session_id | varchar(64) | 会话 |
| token_used | int | 消耗 token |
| created_at / updated_at | | |

索引：`idx_ai_uid_time(uid, created_at)`

---

## 8. IM 域

源站依据：`GET /im/group` → `[{ id, name, status, isJoin, isBlacklist, userTotal, blacklistTotal }]`

### 8.1 `im_groups`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| name | varchar(128) | 群名（源站为空串） |
| avatar / avatar_url | varchar | 群头像 |
| status | varchar(32) | `normal` / `muted` / `dissolved` |
| user_total | int | 成员数 |
| blacklist_total | int | 黑名单数 |
| creator_uid | bigint unsigned | 创建者 |
| created_at / updated_at / is_deleted | | |

### 8.2 `im_group_members`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| gid | bigint unsigned IDX | 群 ID |
| uid | bigint unsigned IDX | 用户 ID |
| nickname | varchar(64) | 群昵称 |
| is_blacklist | tinyint(1) | 是否黑名单（源站 `isBlacklist`） |
| status | varchar(32) | `joined` / `left` |
| joined_at | datetime | |
| created_at / updated_at | | |

索引：`uk_im_member_gid_uid(gid, uid)`、`idx_im_member_uid(uid)`

### 8.3 `im_group_messages`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| gid | bigint unsigned IDX | 群 ID |
| uid | bigint unsigned IDX | 发送者 |
| type | varchar(32) | `text` / `image` / `card` / `system` |
| content | text | 内容 |
| extra | json | 扩展（藏品卡片等） |
| is_recalled | tinyint(1) | 撤回 |
| sent_at | datetime | 发送时间（源站 13 位毫秒） |

索引：`idx_im_msg_gid_time(gid, sent_at)`

### 8.4 `im_user_sessions` — 单聊会话

源站依据：`GET /im/user/`
字段：`id / uid / peer_uid / last_message_id / last_message_at / unread_count / created_at / updated_at`
索引：`uk_im_session(uid, peer_uid)`

---

## 9. 系统域

### 9.1 `app_versions` — 版本检查

源站依据：`GET app/version/check`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| platform | varchar(32) | `web` / `android` / `ios` |
| version | varchar(32) | 版本号（如 `1.3.2`） |
| min_version | varchar(32) | 最低支持版本 |
| force_update | tinyint(1) | 强制更新 |
| description | varchar(1024) | 更新说明 |
| download_url | varchar(512) | 下载地址 |
| status | varchar(32) | |

索引：`idx_version_platform(platform, status)`

### 9.2 `common_rules` — 通用规则

源站依据：`GET /common/rule`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint unsigned PK | |
| key | varchar(64) UK | 规则键（如 `user_agreement` / `privacy`） |
| title | varchar(255) | 标题 |
| content | mediumtext | 内容 |
| version | varchar(32) | 版本 |
| status | varchar(32) | |

---

## 10. 表清单汇总（48 张）

| 域 | 表名 | 说明 |
|----|------|------|
| 用户 | `users` | 账号 |
| 用户 | `user_profiles` | 资料 |
| 用户 | `user_wallets` | 余额 |
| 用户 | `user_invites` | 邀请 |
| 用户 | `user_login_logs` | 登录日志 |
| 用户 | `user_online_sessions` | 在线会话 |
| 藏品 | `item_categories` | 分类 |
| 藏品 | `item_authors` | 作者 |
| 藏品 | `items` | 藏品主表 |
| 藏品 | `item_follows` | 关注 |
| 藏品 | `item_copyrights` | 版权 |
| 藏品 | `product_tp_categories` | 图谱分类 |
| 藏品 | `product_tp_items` | 图谱项 |
| 资产 | `nfts` | NFT 资产 |
| 资产 | `nft_groups` | 仓库聚合 |
| 资产 | `nft_merge_recipes` | 合成配方（主表）|
| 资产 | `nft_merge_produces` | 合成产出（源站 `merges` 子数组）|
| 资产 | `nft_merge_needs` | 合成消耗（源站 `needs` 子数组）|
| 资产 | `nft_merge_records` | 合成记录 |
| 资产 | `nft_box_open_logs` | 开箱记录 |
| 交易 | `orders` | 订单 |
| 交易 | `order_items` | 订单明细 |
| 交易 | `order_payments` | 支付流水 |
| 交易 | `nft_sell_orders` | 寄售挂单 |
| 交易 | `nft_market_apply_orders` | 求购单 |
| 交易 | `nft_market_trust_orders` | 委托单 |
| 交易 | `nft_market_follows` | 市场关注 |
| 交易 | `nft_trade_logs` | 成交记录 |
| 交易 | `wallet_transactions` | 资金流水 |
| 内容 | `banners` | 轮播 |
| 内容 | `notice_categories` | 公告分类 |
| 内容 | `notices` | 公告 |
| 内容 | `notice_item_relations` | 公告-藏品关联（源站 `notice.items` 数组）|
| 活动 | `activity_sign_records` | 签到 |
| 活动 | `activity_lotteries` | 抽奖活动 |
| 活动 | `activity_lottery_prizes` | 奖品 |
| 活动 | `activity_lottery_logs` | 抽奖记录 |
| 活动 | `activity_points_logs` | 积分流水 |
| 活动 | `activity_recast_prizes` | 重铸奖池 |
| 活动 | `activity_recast_logs` | 重铸记录 |
| 活动 | `product_merge_records` | 合成活动记录 |
| 活动 | `ai_answers` | AI 问答 |
| IM | `im_groups` | 群 |
| IM | `im_group_members` | 群成员 |
| IM | `im_group_messages` | 群消息 |
| IM | `im_user_sessions` | 单聊会话 |
| 系统 | `app_versions` | 版本 |
| 系统 | `common_rules` | 规则 |

（实际建表 **48 张**，其中 `nft_groups` 为可重建的聚合表；已在真实 MariaDB 10.11 建出并灌入源站抓取的真实数据，三段脚本全量零 ERROR）

---

## 11. ThinkPHP 6 模型映射

| 表 | 模型 | 控制器 |
|----|------|--------|
| `users` | `app\model\User` | `app\controller\Passport` |
| `user_profiles` | `app\model\UserProfile` | `app\controller\User` |
| `items` | `app\model\Item` | `app\controller\Item` |
| `nfts` | `app\model\Nft` | `app\controller\Nft` |
| `orders` | `app\model\Order` | `app\controller\Order` |
| `nft_sell_orders` | `app\model\NftSellOrder` | `app\controller\Nft` |
| `notices` | `app\model\Notice` | `app\controller\Basic` |
| `activity_lotteries` | `app\model\ActivityLottery` | `app\controller\Activity` |
| `activity_sign_records` | `app\model\ActivitySignRecord` | `app\controller\Activity` |

全局模型基类 `app\model\BaseModel` 统一：`id` 主键、`autoWriteTimestamp = 'datetime'`、`软删除 is_deleted`。

---

## 12. 关键业务逻辑落点

| 功能 | 主表 | 事务要点 |
|------|------|---------|
| 一级购买 | `orders` + `nfts` + `wallet_transactions` | 扣款 → 扣发行量 → 生成 NFT，幂等键 `order_no` |
| 二级一口价 | `orders` + `nft_sell_orders` + `nft_trade_logs` | 二级密码校验 → 改挂单状态 → 转移 NFT 归属 → 双边流水 |
| 批量购买 | `orders` + `order_items` | 按 `unit_price` 撮合 N 个挂单，**全部成功或全部回滚** |
| 求购成交 | `nft_market_apply_orders` + `orders` | 卖方提交 `POST /nft/sell/apply` 校验二级密码 |
| 委托托卖 | `nft_market_trust_orders` | 收单 → 撮合 → 结算佣金 |
| 开箱 | `nft_box_open_logs` + `nfts` | 扣 `box_open_time` 后的可开数量 → 按概率产出 |
| 签到 | `activity_sign_records` | `uk_sign_uid_ymd` 保证一天一次，`sign_days` 冗余累加 |
| 抽奖 | `activity_lottery_logs` + `activity_points_logs` | 扣积分 → 按权重抽奖 → 落日志（前端按 `prize_id` 匹配扇区算角度） |
| 合成 | `nft_merge_recipes` + `nft_merge_records` | 校验持有量 → 扣 NFT → 产出新 NFT |

---

## 12.5 落库验证记录（2026-07-19 实测）

已将 `database/create_schema.sql` 在真实数据库执行，46 张表全部建出、零报错，并灌入源站抓取的真实数据（`database/seed_source_data.sql`：3 藏品 / 1 NFT / 1 用户 / 1 钱包 / 9 公告 / 13 公告关联）。落库过程暴露了 4 个设计缺口，均已修正：

| # | 问题 | 现象 | 修正 |
|---|------|------|------|
| 1 | **缺公告-藏品关联表** | 源站 `GET /basic/notice` 每条公告带 `items` 数组（最多 3 条），原设计无处安放 | 新增 `notice_item_relations` 表 |
| 2 | ~~**金额单位在同一响应内混用**~~ **该结论已撤销（2026-10-19 OPEN-01）** | 曾误判"`price=388`是元、`secondaryHighPrice=500`是分"，据此设计了 DAO 层选择性÷100 | **实测证明源站金额字段单位全部为「分」，无混用**。DAO 层改为原样返回不换算，`Transformer::amount()` 注释同步修正 |
| 3 | **7 个统计字段实际可为空** | 源站返回 `priceChange`/`minPrice`/`tradeAmount`/`hourTradeQuantity`/`todayTradeMax(Min)Amount`/`lastTradeAmount` **恒为 null**，原定义 `NOT NULL` 导致插入失败 | 7 个字段改为 `NULL DEFAULT NULL` |
| 4 | **`nfts.pay_way` 实际可为空** | 源站 `GET /nft/{nftId}` 返回 `payWay: null`，原定义 `NOT NULL DEFAULT 0` 报错 | 改为 `tinyint(1) NULL DEFAULT NULL` |

> **教训**：字段可空性不能靠推测，必须拿真实响应灌一遍才能暴露。这 4 个问题在纯文档阶段全部是「看不出来的」。

### 12.5.1 第二轮：交易域实抓验证（2026-07-19）

第一轮只灌了基础域，交易域 5 张表是「按契约推的」。随后以只读方式实抓源站 4 个secondary 端点
（`GET /nft/secondary/sell|sold|apply|trust`，参数 `{pageNo:1,pageSize:10,iid}`），
拿到 2订单 / 3 求购 / 10 委托 / 10 成交 / 10 挂单 / 1 配方真实数据，灌库后暴露 **9 个新缺口**，全部已修正：

| # | 表 | 问题 | 源站实证 | 修正 |
|---|----|------|---------|------|
| 5 | `orders` | `type`/`status` 设计为字符串 | 实测 `type=20`、`status=2`，是数字枚举 | 改 `tinyint` 数字枚举（20=二级买入 / 10=一级 / 30=求购 / 40=委托；0待付 1已付 2已完成 3已取消）|
| 6 | `orders` | 缺 7 个真实字段 | 源站订单含 `title`/`image`/`imageUrl`/`itemQuantity`/`amount`/`subject`/`outTradeNo`/`isSnapUp`/`endPayTime` | 全部补齐；删掉源站不存在的 `order_no` 独立唯一键（`out_trade_no` 已是唯一键）|
| 7 | `orders` | `pay_time` 不可空 | 源站 `payTime: null` | 改 `bigint NULL` |
| 8 | `nft_market_apply_orders`<br>`nft_market_trust_orders` | 字段名不符 + 时间类型错 | 源站为 `completedQuantity`/`amount`/`payWay`/`createTime`(13位毫秒) | `filled_quantity`→`completed_quantity`，补 `amount`/`title`/`image`/`pay_way`，`created_at` datetime→`created_ts` bigint |
| 9 | `nft_trade_logs` | 多设计了 5 个字段 | 源站 `secondary/sold` **仅返回 5 字段**：`nftId`/`token`/`soldPrice`/`soldTime`/`chainId`，无 seller/buyer/iid | 表精简为 5 个源站字段 + `iid`/`order_id` 作内部关联 |
| 10 | `nft_sell_orders` | 误解为独立挂单对象 | 源站 `secondary/sell` 返回的是**完整 nft 对象**，`status=1` 表示在售 | 表结构改为源站形态：以 nft 为主体承载在售状态 |
| 11 | `nfts` | `status` 设计为字符串 | 实测数字枚举 | 改 `tinyint`（0普通 / 1在售 / 2已售）|
| 12 | `nft_merge_recipes` | 缺子结构，字段名不符 | 配方详情含 `merges[]`（产出）与 `needs[]`（消耗）子数组 | 拆出 `nft_merge_produces` + `nft_merge_needs` 两张子表；主表 `mid`→`id`、`image`→`cover`，补 `mergeQuantity`/时间戳/`isPre` |
| 13 | 全库时间字段 | 源站时间统一为 13 位毫秒，但审计字段外的业务时间字段原定义 datetime | 灌 `'2026-10-17 14:22:25'` 报 `Data truncated` | 全部业务时间字段统一 `bigint` 毫秒：`items.pre_*/start_*/end_*`、`nfts.get_time`、`users.last_login_at`、`activity_sign_records.rewarded_at`、`nft_box_open_logs.box_open_*`、`user_online_sessions.login_at/last_active_at`、求购/委托 `created_ts`。**`created_at`/`updated_at` 保留 datetime**（本地审计字段，由框架自动维护）|

> **关键修正（源站 bug 级）**：市场页 4 个 query 的初始值均为 `iid: ""`（空串），
> 导致首次进入市场页请求必报 `iidmust not be null`。这是**源站自身的 bug**，
> 本地复刻需在 `onLoad` 用路由参数赋值 `iid` 后再发请求，不要照抄空串默认值。

**当前落库状态**（MariaDB 10.11实测）：

| 项| 值 |
|----|-----|
| 表总数 | 48（`create_schema.sql` 零 ERROR 建出）|
| 已灌真实数据 | **25 张（52%）** |
| 未验证 | 23 张（活动域 9 张整域未验证 + 抽奖/重铸/IM 消息等源站返回 `10203` 或空数组）|
| 数据量 | 13 公告关联 / 10 挂单 / 10 委托 / 10 成交 / 9 公告 / 4 分类 / 3 藏品 / 3 求购 / 2 订单 / 2 规则 / 各1（用户、资料、钱包、NFT、作者、配方、产出、消耗、群、会话、版本）|

**兼容性修正**：MariaDB 10.11 不支持 MySQL 8 专有的 `utf8mb4_0900_ai_ci`，脚本统一用 `utf8mb4_unicode_ci`；纯 MySQL 8 环境可自行替换。

### 12.5.2 第三轮：订单链路只读实抓验证（2026-07-18）

从源站分包 `order.D6d574Cp.js` 提取完整订单 API 契约（12 个端点），实抓只读端点
`GET /order`（7 种type × 6 种 status 共 42 组组合）与 `GET /order/{orderId}` 详情，
暴露 **5 个新缺口**，全部已修正：

**源站订单 API 全清单**（`order.D6d574Cp.js` 实测提取）：

| 分类 | 端点 | 方法 |
|------|------|------|
| 创建 | `/order/primary` | POST |
| 创建 | `/order/secondary` | POST |
| 创建 | `/order/secondary/quick` | POST（一口价秒购）|
| 创建 | `/order/secondary/batch` | POST（批量购买）|
| 创建 | `/order/secondary/apply` | POST（求购）|
| 创建 | `/order/secondary/trust` | POST（委托）|
| 支付 | `/order/pay/hfpay` | POST（汇付）|
| 支付 | `/order/pay/yeepay` | POST（易宝）|
| 支付 | `https://notify.rongchang.space/notify/applepay/order` | POST（Apple Pay，**外部域名非本API**）|
| 取消 | `/order/cancel` | PUT |
| 查询 | `/order` | GET（query: `pageNo`/`pageSize`/`type`/`status`）|
| 查询 | `/order/{orderId}` | GET |

**源站订单类型枚举**（`pages-my-order-index.omZ1ybKK.js` 的 `orderTypes` 实测提取，7 种）：

| type | 名称 | 该类型可选status |
|------|------|-----------------|
| 10 | 常规订单 | 0待付款 / 2已完成 / 3已取消 |
| 20 | 市场订单 | 0待付款 / 2已完成 / 3已取消 / **4已退款** |
| 30 | 批量订单 | 0待付款 / 2已完成 / 3已取消 |
| 40 | 委托订单 | 0待付款 / **1委托中** / 2已完成 / 3已取消 |
| 50 | 求购订单 | 0待付款 / **1求购中** / 2已完成 / 3已取消 |
| 60 | 转售订单 | 0待付款 / 2已完成 / 3已取消 |
| 70 | 申购订单 | 0待付款 / **1申购中** / 2已完成 / 3已取消 |

**统一 status 语义**：`0待付款` / `1进行中`（委托中/求购中/申购中，同一值不同类型显示不同文案）/ `2已完成` / `3已取消` / `4已退款`（仅市场订单）。

**第三轮修正项**：

| # | 表 | 问题 | 源站实证 | 修正 |
|---|----|------|---------|------|
| 14 | `orders` | `status` 枚举不完整（漏 1 进行中 / 4 已退款）| `orderTypes[].statusValues` 实测含 `["",0,3,2]` 与 `["",0,1,3,2]`，市场订单另有 `4` | 枚举补全为 `0待付款 1进行中 2已完成 3已取消 4已退款` |
| 15 | `orders` | `order_type` 枚举只列 4 种 | 实测 7 种 type（10/20/30/40/50/60/70）| 注释补全 7 种类型 |
| 16 | `orders` | 缺 `createTime` | 列表与详情均返回 `createTime`（13位毫秒字符串）| 新增 `created_ts bigint`，索引 `idx_orders_uid_status` 改用 `created_ts` |
| 17 | `orders` | `pay_time` 用 NULL 表达未支付 | 实测已完成订单（status=2）`payTime` 仍为 `null`——**源站未回填支付时间** | 改 `bigint NOT NULL DEFAULT 0`（0=未支付），DAO 层转null 输出 |
| 18 | `order_items` | 结构与源站不符（设计了 `price`/`status`/`seller_uid` 必填）| 详情接口 `items[]` 真实字段为 `id`/`orderId`/`nftId`/`token`/`amount`/`createTime` | 按源站重建：新增 `token`/`amount`/`created_ts`，删 `status`（源站无状态），`price`→`amount`，补 `token` 索引 |

**关键发现（列表 vs 详情字段不对称）**：

`GET /order`（列表）返回的 `items` 数组**恒为 `[]`**，只有 `GET /order/{orderId}`（详情）
才返回真实的 `order_items` 明细（含 `nftId`/`token`）。
→ 本地实现必须复刻这个行为：**列表接口不 join 明细表，详情接口才 join**，否则会写出源站没有的行为。

**支付窗口实测**：两条订单的 `endPayTime - createTime = 180000ms`，即**下单后 3 分钟内未支付自动取消**。

**实抓结果**：42 组 `type`×`status` 组合中，仅 `type=20` 有数据（2 条，均`status=2已完成`）；`type` 10/30/40/50/60/70 全部返回空数组（账号无余额，从未下单）。
`GET /user/wallet`、`GET /wallet/transaction`、`GET /wallet/log` 三个路径源站均返回 `10203 未找到`（钱包流水模块线上未上线）。

**本轮新增验证表**：`order_items`（0 → **2 张已验证**）。

### 12.5.3 第四轮：全站57 个 GET 端点扫描（2026-07-18）

从 286 个源站分包中正则提取出**全站 57 个 GET 端点**（此前只覆盖约 40个），逐一实抓，
覆盖购买下单链路全场景（资金充值 → 选品 → 市场详情 → 关注 → 挂单 → NFT 列表 → 藏品详情 → 合成 → 签到 → 用户）。

**6 个新发现缺口**：

| # | 表 | 问题 | 源站实证 | 修正 |
|---|----|------|---------|------|
| 19 | `user_wallets` / 充值 | 不知道充值端点返回什么结构 | `GET /wallet/hfpay` 返回 **`data` 是纯字符串**（收银台 URL）：`https://hfpay.cloudpnr.com/h5/?uuid=hfpwallet6666000243789213...`，前端 `window.location.href = data` 直接跳转 | 充值端点定义为**返回 URL 字符串**（非对象）；`GET /wallet/yeepay` 线上恒返回 `code:20000 系统错误`（易宝通道未上线）|
| 20 | `item_copyrights` | 表结构与源站严重不符：原设计有 `iid`/`title`/`content`/`sort` 且「按 iid 索引」| `GET /item/copyright?iid=xxx` 实测返回**全局 6 条**，字段仅 `id`/`name`/`image`/`imageUrl`，**不挂 iid**，含多个不同藏品名 | 表按源站重建：删 `iid`/`title`/`content`，新增 `name`/`image`/`image_url`，索引改 `sort,id`；**版权是全局的，不按藏品过滤** |
| 21 | `notice_categories` | 分类数据是猜的（3 条：寄售/合成活动/发放通知）| `GET /basic/notice/category` 实测 **5 条**：限价公告(sort=0) / 寄售公告(1) / 运营公告(1) / 活动公告(1) / 空投公告(1)，**每条都有封面图** | seed 替换为源站真实 5 条含cover；**注意 sort 多条同为 1，不是递增序号** |
| 22 | `user_online_sessions` | 缺 3 个源站返回字段 | `GET /passport/online` 实测返回 `token`/`signinWay`(1密码2短信3第三方)/`isSelf` | 新增 `token`/`signin_way`/`is_self` 三字段 |
| 23 | `nft_merge_produces` / `nft_merge_needs` | 缺 `ownQuantity` | `GET /item/merge/{id}` 的 `merges[]`/`needs[]` 每项都带 `ownQuantity`（实测产出=0，消耗=1）| 两张子表均新增 `own_quantity`（动态值，DAO 层实时算，不落库仅回显）|
| 24 | `notices` | `published_at` 是 datetime | 源站 `notices[].createTime` 是 13 位毫秒字符串 | 改`published_ts bigint`，两个索引同步改用该字段 |

**第四轮验证表**：`item_copyrights`（0 → **6 条已验证**）。

**列表/详情不对称模式（第2 例）**：

| 接口 | 列表版 | 详情版 |
|------|--------|--------|
| `GET /order` → `items[]` | **恒为 `[]`** | `GET /order/{id}` 才返回真实明细 |
| `GET /item/merge` → `merges`/`needs` | **恒为 `null`** | `GET /item/merge/{id}` 才返回真实产出/消耗 |

> 这不是偶发，是源站的固定写法：**列表接口不加载关联明细，详情接口才加载**。
> 本地实现必须复刻，否则会写出源站没有的行为，也会让前端在列表页拿不到数据。

**关键业务数据（实抓）**：

| 项 | 实测值 | 业务含义 |
|----|--------|---------|
| 合成配方消耗 | 10 张臻海蓝卡 → 1 张批量资格卡（7天）| 账号只有 1 个 → **无法合成，这就是设计 `ownQuantity` 的用途** |
| 臻海蓝卡二级市场 | `nftQuantity=171819` `sellQuantity=14782` `todayTradeQuantity=2711` `todayTradeAmount=1210600`（分=12106元）| `minPrice=maxPrice=500`（分=5元），当前挂单价单一 |
| 藏品状态 | `isSoldOut=true`（已售罄）`isSecondary=true` `isSecondaryBatch=true` `isSecondaryTrust=true` `isSecondaryApply=false` | 一级已售罄，仅二级可交易，支持批量/委托但不支持求购 |
| 签到 | `records` 为 31 个布尔数组（本月），第 18 位= true，`signDays=1` | 源站 `records[day-1]` 标记签到 |
| 邀请 | `{list: [], total: 0}` | 无邀请记录 |
| 积分余额 | `{balance: 0}` | 无积分 |

**本轮确认线上未上线的功能**（源站返回 `10203` 或错误码）：

| 端点 | 返回 | 说明 |
|------|------|------|
| `GET /wallet/yeepay` | `code:20000 系统错误` | 易宝支付通道未上线 |
| `GET /user/wallet` | `code:10203` | 钱包详情端点不存在 |
| `GET /wallet/transaction` | `code:10203` | 资金流水端点不存在 |
| `GET /common/rule` | `code:10203` | 规则端点不存在 |
| `GET /item/{iid}/author` | `code:10203` | 作者详情走的是 `/item/{iid}` 内的嵌套 `author` 对象，无独立端点 |
| `GET /app/version/check` | `code:10101` | 传 `versionCode` 仍报参数类型错误，参数规则未明 |

**已知源站 bug 补充**：`GET /item/detail`（带 `detail` 后缀）恒报 `10101 参数类型错误`，
正确用法是 `GET /item/{iid}`（路径参数直接拼iid），本轮已验证成功。

## 13. 待确认项（OPEN-DECISIONS）

| 编号 | 待确认 | 现状 | 待定条件 |
|------|--------|------|---------|
| OD-01 | 源站 `GET /api/author/detail` 线上恒返回 `10203 未找到` | 前端走 mock 兜底 | 是否按 mock 数据建模，还是等接口上线 |
| OD-02 | `GET /nft/digitalAlbum/calendar` 返回 `10203` | 未抓到有效数据 | 数字藏品日历的数据结构待补 |
| OD-03 | `GET /user/resource` 返回 `[]` | 余额页无真实数据 | 钱包表字段以页面展示位反推 |
| OD-04 | `GET /activity/lottery/active` 返回 `data: null` | 当前无进行中活动 | 奖品扇区顺序按 `prizeList` 数组序建模 |
| OD-05 | 源站 `item` 分包存在 `item/detail`、`market/*` 等本地未使用端点 | 已保留在 API 文档但未建模 | 是否纳入 v1 |

---

## 14. 变更记录

| 日期 | 变更 | 原因 |
|------|------|------|
| 2026-07-19 | 首版创建，45 张表 | 基于源站 6 个分包契约 + 30 页运行时抓取 |
| 2026-07-19 | 46 张表，新增 `notice_item_relations` | 基础域落库发现公告 `items` 数组无处安放 |
| 2026-07-19 | 48 张表，新增 `nft_merge_produces` / `nft_merge_needs` | 交易域实抓发现合成配方含 `merges`/`needs` 子数组 |
| 2026-07-19 | 交易域 9 个字段设计修正 + 业务时间字段统一 bigint 毫秒 | `secondary/sell|sold|apply|trust` + `item/merge` 实抓灌库验证 |
| 2026-07-18 | 订单域 5 个字段设计修正，`order_items` 按源站重建，新增 `orders.created_ts` | `GET /order` 42 组 type×status + `GET /order/{id}` 详情实抓灌库验证 |
| 2026-07-18 | 第四轮 6 个缺口修正：`item_copyrights` 按源站重建、`notice_categories` 换真实 5 条、会话表补 3 字段、合成子表补 `own_quantity`、`notices.published_at`→`published_ts` | 全站 57 个 GET 端点扫描实抓 |
