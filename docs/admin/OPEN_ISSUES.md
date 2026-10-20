# OPEN-01 / OPEN-02 / OPEN-03 结项报告

> 日期：2026-10-19
> 作者：高见远（首席架构师）
> 触发：项目总监复验上一轮 `verdict: fail` 的三条证据后批准 OPEN-01，并追加 OPEN-02/03 决策请求

---

## OPEN-01：文档占位符污染 —— 已结案

### 根因

`docs/api.md` 3.1 节以「实测抓取，全字段」为标题，正文却是模板占位符
（`0x1234...abcd`、`cdn.../item.png`、`臻海艺术藏品`、`"cid": 1`、`price: 38800`），
并在其上建立了错误结论「`price: 38800` = 388.00 元（单位分）」。

根因不是笔误，而是**流程缺陷**：编写文档时未执行抓包动作，
却按「实测」的格式书写，使占位符获得了实测值的权威性。

### 污染范围与处理结果

| 文件 | 污染块数 | 处理 |
|------|---------|------|
| `docs/api.md` | 8 块 | 6 块替换为真实抓包，2 块降级为显式占位标注 |
| `docs/database-design.md` | 3 处结论性文字 | 撤销错误结论并标注撤销原因 |
| `docs/发售与购买链路.md` | 0 | 口径本就正确，未改动 |
| `database/create_schema.sql` | 3 处 COMMENT | 修正 |
| `database/seed_source_data.sql` | 1 处注释 | 修正 |

### 额外发现（不在原派单范围内，但同源）

排查中发现**错误结论已扩散到 DDL 与种子数据**，一并修正：

1. `database/create_schema.sql` 中 `items.price` 的 COMMENT 写的是
   `'一级价(元,源站已是元不除100)'` —— 这是把api.md 那个错误结论固化进了表结构定义，
   任何后续维护者读COMMENT 都会得到错误口径。
2. `database/seed_source_data.sql` 注释写着
   `price(388) 单位为【元】，切勿统一除 100` —— 若有开发者照此理解去「修正」数据，
   就会把 388 误当成元，进而产生 100 倍资损。
3. `docs/database-design.md` 第 35 行与第 988 行都记录了
   「金额单位在同一响应内混用」的结论，并据此设计了 DAO 层选择性 ÷100。

**运行时逻辑本身是正确的**：`Transformer::amount()` 的实现是原样返回不换算，
`items.price` 库里实际存的值是 `388.00`（分），前后端口径一致。
也就是说本地实现没有 bug，错的是**文档与 DDL 注释**——
这正是「占位符污染」的典型危害：实现对，文档错，而文档是下一次的输入。

### 防复发机制

1. `docs/api.md` 开头新增「本文档的证据分级」章节，定义
   `【实测】` / `【结构示意】` / `【占位】` 三级标记与各自的契约效力。
2. 新增 `scripts/verify_docs_against_source.py`，自动抽取文档中标记为
   `【实测】` 的 JSON 块，实时请求源站做字段级比对，输出
   `MATCH` / `DRIFT` / `MISMATCH` / `UNREACHABLE`，存在 `MISMATCH` 时以非零码退出，
   可直接接入 CI 阻断。

### 脚本设计要点（避免脚本自身产生假阴性）

| 机制 | 解决的问题 |
|------|-----------|
| 只认显式 `【实测】` 标记 | 未标记的块一律不进比对，避免把示意块误判为污染 |
| 占位符特征库反向检测 | 标了 `【实测】` 的块内若含占位符，直接判 `MISMATCH` |
| 时变字段分级 | 行情字段只校验存在性与类型，值变化记 `DRIFT` 不记 `MISMATCH` |
| 列表按业务主键对齐 | 源站列表排序随成交实时变化，按下标取首条会误报「文档缺一堆字段」 |
| `//` 注释剥离避开 URL | `https://` 中的 `//` 被当注释会破坏 JSON 结构（开发中真实踩到） |
| 端点路径整体捕获含 query | 截断 `?iid=...&pageNo=1` 会导致源站报 `iid must not be null` |

当前运行结果：**0 MISMATCH**，5MATCH，5 DRIFT（全部为正常的行情漂移）。

---

## OPEN-02：行情字段漂移 —— 结论「时变数据正常漂移」，不立项

### 事实

同一藏品 `iid=2109928427009183745`，本地库存值与源站实时值：

| 字段 | 本地 | 源站（2026-10-19 实抓） | 差异 |
|------|------|---------------------|------|
| `minPrice` | 500.00 | 300 | 行情变动 |
| `maxPrice` | 500.00 | 500 | 一致 |
| `todayTradeAmount` | 102000.00 | 5649000 | 行情变动 |
| `tradeQuantity` | 314362 | 327215 | 累计量增长 |
| `secondaryHighPrice` | 500.00 | 500 | 一致 |
| `price` | 388.00 | 388 | 一致 |

### 结论：不立项

判断依据：

1. **`price` 与 `secondaryHighPrice` 完全一致** —— 说明本地种子数据的
   **静态字段（定价）与源站是对齐的**，灌库流程本身没有失真。
2. **不一致的字段全部是纯行情字段**（`minPrice` / `todayTradeAmount` /
   `tradeQuantity`），这类字段按定义就随每一笔成交实时变化。
   本地灌库时的取值是那个时刻的真实值，源站现在的取值是此刻的真实值，
   两者都对，只是采集时刻不同。
3. 同一藏品在**几分钟内**的两次抓取，`todayTradeAmount` 就从 5649000 变成 6194800，
   `nftQuantity` 从 95824 变成 93614。这证明源站自身就在持续漂移，
   **不存在一个「正确的行情值」可供对齐**。

因此这属于**时变数据的正常漂移**，而非「本地种子数据严重失真」。
任何要求本地行情值与源站实时相等的方案都是错的——那等于要求复制源站的撮合引擎。

**不立项。** 唯一需要做的是在文档中写清「行情字段为快照值，非实时值」，
避免后续有人把漂移误判为 bug 并反复「修数据」（这正是 OPEN-01 派单差点引发的事）。

### 建议的处置（已写入文档，不需单独立项）

`docs/database-design.md` 中对这些字段标注「快照值，非实时」。

---

## OPEN-03：`items.price` 改 `bigint` —— 结论「建议做，但不在当前批次」

### 收益

`items.price` 存的量级是「分」，源站实测最大 `secondaryHighPrice` 达 150000，
`todayTradeAmount` 达 5649000。当前 `decimal(20,2)` 对一个**语义上必然是整数**的字段
使用了精确小数类型，属于类型冗余，且带来两个实际成本：

1. **精度隐患**：`decimal(20,2)` 在 PHP 端若某处被当作 float 处理，
   大额值会有浮点误差。金额字段用整数是更符合领域语义的选择。
2. **存储与索引开销**：`decimal(20,2)` 占 9 字节，`bigint` 占 8 字节，
   且 decimal 的比较运算比整数慢。`min_price` / `max_price` 上有索引时这个差异会被放大。

### 无损性验证（已完成）

```sql
SELECT COUNT(*) FROM items WHERE price <> FLOOR(price);                -- 0
SELECT COUNT(*) FROM items WHERE secondary_high_price <> FLOOR(...);   -- 0
SELECT COUNT(*) FROM items WHERE min_price <> FLOOR(min_price);         -- 0
SELECT COUNT(*) FROM items WHERE today_trade_amount <> FLOOR(...);     -- 0
SELECT COUNT(*) FROM items WHERE price_change <> FLOOR(price_change);   -- 0
```

**当前所有金额字段的实际值均为整数，无一条存在小数部分。**
且 `price_change`（涨跌幅）实测恒为 `NULL`，本地 5 条也全为 `NULL`。

因此 `decimal(20,2) → bigint` 的转换是**严格无损的**：
`CAST(price AS UNSIGNED)` 对整数值的 decimal 恒等，不存在截断风险。

### 影响面

全库共 36 处 `decimal(20,2)` / `decimal(10,4)` 金额字段，分布：

| 表 | 金额字段数 |
|----|-----------|
| `items` | 13 |
| `user_wallets` | 4 |
| `nft_market_trust_orders` / `nft_market_apply_orders` / `nft_groups` / `nfts` / `orders` / `wallet_transactions` | 各 2 |
| 其余 6 张流水/明细表 | 各 1 |

**建议分两步，不要一次动36 个字段**：

- **第一批（本次建议做）**：只改 `items` 表的 12 个纯金额字段
  （`price`、`secondary_low_price`、`secondary_high_price`、
  `secondary_apply_low_price`、`secondary_apply_high_price`、`min_price`、
  `max_price`、`trade_amount`、`last_trade_amount`、`today_trade_amount`、
  `today_trade_max_amount`、`today_trade_min_amount`），
  并同步 `COMMENT '单位:分'`。
- **第二批（暂不动）**：`price_change decimal(10,4)` —— 它**确实需要小数**
  （涨跌幅天然是小数），改bigint 会造成真实精度损失。
  源站实测该字段恒为 `null`，但字段语义上不能排除未来出现 `0.0234` 这类值，
  应保持 `decimal`。

### 是否需要停机

**不需要停机**，理由：

1. 当前 `items` 表仅 5 行数据，变更窗口内秒级完成。
2. `decimal(20,2) → bigint` 属于**同类型族内的数值类型变更**，
   MySQL 在 8.0 上支持 `ALGORITHM=INPLACE, LOCK=NONE` 的原地修改
   （仅改类型不改列位置时），期间不阻塞读写。
3. 即使退化为 `COPY` 算法，也只是复制 5 行数据，耗时可忽略。

建议的迁移 SQL（幂等、可回滚）：

```sql
-- 迁移前留底
CREATE TABLE items_bak_20261019 AS SELECT * FROM items;

-- 逐字段转换（UNSIGNED 转换对整数 decimal 恒等，无损）
ALTER TABLE `items`
  MODIFY `price`                     bigint unsigned NOT NULL DEFAULT 0   COMMENT '一级价(单位:分,源站原样不换算,前端÷100)',
  MODIFY `secondary_low_price`       bigint unsigned NOT NULL DEFAULT 0   COMMENT '二级最低价(单位:分)',
  MODIFY `secondary_high_price`      bigint unsigned NOT NULL DEFAULT 0   COMMENT '二级最高价(单位:分)',
  MODIFY `secondary_apply_low_price`  bigint unsigned NOT NULL DEFAULT 0  COMMENT '求购最低价(单位:分)',
  MODIFY `secondary_apply_high_price` bigint unsigned NOT NULL DEFAULT 0  COMMENT '求购最高价(单位:分)',
  MODIFY `min_price`                 bigint unsigned NULL DEFAULT NULL    COMMENT '在售最低价(单位:分,源站可为null)',
  MODIFY `max_price`                 bigint unsigned NULL DEFAULT NULL    COMMENT '在售最高价(单位:分,源站可为null)',
  MODIFY `trade_amount`              bigint unsigned NULL DEFAULT NULL    COMMENT '累计成交额(单位:分)',
  MODIFY `last_trade_amount`         bigint unsigned NULL DEFAULT NULL    COMMENT '最新成交价(单位:分)',
  MODIFY `today_trade_amount`        bigint          NULL DEFAULT NULL    COMMENT '今日成交额(单位:分)',
  MODIFY `today_trade_max_amount`    bigint unsigned NULL DEFAULT NULL    COMMENT '今日最高价(单位:分)',
  MODIFY `today_trade_min_amount`    bigint unsigned NULL DEFAULT NULL    COMMENT '今日最低价(单位:分)';
-- price_change decimal(10,4) 保持不变（涨跌幅需小数）

-- 校验：应返回 0
SELECT COUNT(*) FROM items WHERE price <> CAST(price AS UNSIGNED);
```

### 代码路径影响

| 路径 | 是否受影响 | 说明 |
|------|-----------|------|
| `Transformer::amount()` | **否** | 实现是 `(int)$cents`，输入 bigint 行为不变 |
| `Transformer::amountOrNull()` | **否** | 同上 |
| `Transformer::item()` / `secondary()` | **否** | 全部走上述两个函数 |
| ThinkPHP Model 读取 | **否** | PDO 对 `bigint unsigned` 返回 PHP int（64 位平台），与原 decimal 返回的字符串不同，**需验证** |
| 前端 `amountFormat()` | **否** | 接口出参仍为 JSON 数字，前端逻辑不变 |

**唯一需要实测的一点**：ThinkPHP 的 PDO 驱动对 `bigint unsigned` 列，
PDO::ATTR_STRINGIFY_FETCHES 开启时会返回字符串而非 int。
由于金额值量级（最大约 6e6）远小于 PHP int 上限，
即��返回字符串，`(int)` 转换后结果也完全一致，**行为等价**。
但这一点必须在迁移后跑一次接口回归确认，不能靠推断。

### 给总监的决策建议

**建议采纳，但排在 OPEN-01 收尾之后单独一批做，理由**：

- 无损性已用SQL 实证，不是推断；
- 不需要停机；
- 但它属于「优化」而非「修 bug」——当前实现是正确的，
  在 OPEN-01 这种正确性修复的批次里夹带类型变更，会让问题定位变得困难。

**若总监希望合并到本次**，我的意见是可以做，但必须遵守两条：
1. 只改 `items` 表 12 个字段，`price_change` 保持 `decimal(10,4)`；
2. 迁移后立即跑接口回归，重点验`GET /item/{iid}` 与
   `GET /nft/secondary/{iid}` 的出参是否与迁移前逐字节一致。

---

## 金额字段口径统一结论（供后台管理复用）

**唯一规则：源站所有金额字段单位都是「分」，后端与数据库一律原样存取不换算，前端 ÷100。**

依据：源站前端 bundle `index-La17T32K.js` 实测原文

```js
amountFormat: function(e){ return e ? "￥" + Number(e/100).toFixed(2) : "￥--" }
```

逐字段对照表见 `docs/api.md` 3.1.3节。

**不存在「某些字段源站直接返元」的情况。**
`price=388` 与 `todayTradeAmount=5649000` 的差异只反映金额量级不同，
不代表单位不同。判断单位的唯一依据是前端是否对它调用了 `amountFormat`——
实测确认源站对全部 15 个金额字段都调用了。

---

## 遗留登记

| 编号 | 事项 | 状态 |
|------|------|------|
| OPEN-04 | 需登录端点的契约（`/nft/group`、`/nft/{nftId}`、`/user/profile`、`/passport/account`、`/notice/*`、`/order/*`、`/wallet/*`）本环境无法实抓，文档中已降级为 `【占位】` 标注，字段名可信、值不可信 | 待补，需具备源站登录态的环境 |
| OPEN-05 | `product/tp_category`、`product/tp_list`、`nft/digitalAlbum/calendar` 实测需登录（`code:11111`），文档中「图谱分类/图谱列表为免登录」的说法待复核 | 待复核 |
| ~~**OPEN-06**~~ | ~~AC-28「异常低价预警上线首日必须复现命中」在当前种子数据下不成立~~ —— 根因：本地种子把挂单放在臻海蓝卡 `iid=2109928427009183745`、成交放在韩湘子 `iid=2111310949005721602`，两者分属不同藏品，按 `iid` 反查臻海蓝卡成交记录 = 0 | **已关闭（2026-10-20，总监裁决）** —— 三项处置全部完成：① 备份三表至 `.cache/db-backup/market-20261020-115741.sql`；② 按源站实抓快照重建同 `iid` 配对（臻海蓝卡挂单 10 条 price=500 + 成交 10 条 soldPrice=400），本地接口与源站**逐字段相等**，种子文件 150 项校验通过，E2E 38/38 + 巡检 58/58 通过；③ AC-28 改由**批量挂单规则**承担首日复现并已实证命中（uid 2110748099560517634 持 5 条、2110254061781852161 持 3 条，共 8 条），**异常低价规则在源站真实数据下不命中属数据事实，不造低价数据迁就验收**。同时废止 PRD 原文「0.48%」示例（跨藏品错配：500 是臻海蓝卡挂单价、105100 是韩湘子成交价）。完整证据链见 PRD §17.5.5 |
| **OPEN-07** | **成交记录买家字段无法反查** —— `nft_trade_logs.order_id` 实测**全部为 0**，`orders` 表仅 2 条且 `seller_uid` 全为 0。卖家可经 `nft_id → nfts.uid` 取「当前持有人」（注意：是当前持有人，非成交时点买家）；买家无源。**补充（2026-10-20）**：成交的 10 个 `nft_id` 在 `nfts` 表亦无对应行 —— 源站 `sold` 接口只返回 5 字段不含 `uid`，`nft/info` 需鉴权才可取 | **待裁决（阻塞在源站侧，本地不可解）**：需源站回填 `order_id` 与成交 NFT 持有人。当前实现：买卖双方均显示「—」并说明原因，不猜测、不臆造（PRD §17.5.2 已预设该行为） |