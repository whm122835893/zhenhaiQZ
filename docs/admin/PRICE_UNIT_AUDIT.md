# 金额单位混用风险核查报告（核查结论：风险不成立，No-op）

**结论：不存在 P0 契约违背。派单前提经实测被推翻，未执行任何数据修改。**

- 核查人：高见远（首席架构师）
- 日期：2026-07-18
- 源站：`https://api.shengtai.cyou`（`.cyour` 无法解析，实测 HTTP=000）
- 本地：`http://127.0.0.1:8921`
- 处置：**No-op（未改数据、未改代码、未动 `amount()`）**

---

## 1. 派单前提与实测的冲突

派单给出的证据链称：源站 `price=38800`、`secondaryHighPrice=388000`，本地为 `388` / `500`，
判定「本地 `items.price` 整体错 100 倍」，要求乘 100 修正。

**实测源站当前返回（`GET /item/2109928427009183745`，HTTP 200）：**

```json
{ "price": 388, "secondaryLowPrice": 0, "secondaryHighPrice": 500,
  "secondaryApplyLowPrice": 0, "secondaryApplyHighPrice": 0 }
```

源站返回的是 `388`，不是 `38800`。本地返回 `388`，**与源站一致**。

派单中「38800 / 388000」这两个数字的出处已定位：`docs/api.md` 第 223 / 241-242 / 250 行。
该处 JSON 快照经核对为**占位示例而非实测抓取**，同一段内还有大量不可能出现在真实响应里的值：

| 字段 | `docs/api.md` 记载 | 源站实测 |
|-------|------------------|----------|
| `contract` | `"0x1234...abcd"` | `"0xd2de6137500648aa98300ac8f947457a"` |
| `name` | `"臻海艺术藏品"` | `"臻海蓝卡"` |
| `cid` | `1` | `"2103038591555887106"` |
| `type` | `"normal"` | `1` |
| `nftQuantity` | `2500` | `95854` |
| `imageUrl` | `"https://cdn.../item.png"` | 真实 CDN 地址 |
| `image` | `"/static/images/item.png"` | `"media/2109928079460765697.png"` |

`"0x1234...abcd"` 带省略号、`cdn...` 带省略号，是文档模板占位符特征，不可能来自真实抓包。
**因此把这份快照当作源站契约，是本次误判的源头。**

---

## 2. 源站前端证明「接口值即分，除 100 交给前端」

源站主包 `https://h5.shengtai.cyou/assets/index-La17T32K.js`（1,239 字节响应，200）内实测定义：

```js
amountFormat: function(e){ return e ? "￥" + Number(e/100).toFixed(2) : "￥--" }
```

即源站自身对接口返回值一律 `/100` 后展示。本地 `src/utils/fmt.js` 的 `amountFormat`
与该实现逐字符一致（除 `0`/`null` 边界返回 `"0.00"` 而非 `"￥--"`）。

**推论：既然源站对 `price` 也执行 `/100`，那么源站 `price=388` 在页面上就显示为 `￥3.88`。**
本地显示 `￥3.88`，与源站行为一致——这不是 bug，而是复刻正确。

派单担心的「修数据后前端再除一次变 38800 元」不会发生，因为根本没有 38800 需要写入。

---

## 3. 逐列实测比对（5 个藏品 × 5 个金额字段 = 25 项）

| iid | 字段 | 源站 | 本地 | 状态 |
|----|------|------|------|------|
| 2109928427009183745 | price | 388 | 388 | MATCH |
| | secondaryLowPrice | 0 | 0 | MATCH |
| | secondaryHighPrice | 500 | 500 | MATCH |
| | secondaryApplyLowPrice | 0 | 0 | MATCH |
| | secondaryApplyHighPrice | 0 | 0 | MATCH |
| 2111310949005721602 | price | 9900 | 9900 | MATCH |
| | secondaryLowPrice | 0 | 0 | MATCH |
| | secondaryHighPrice | 100000 | 100000 | MATCH |
| | secondaryApplyLowPrice | 100000 | 100000 | MATCH |
| | secondaryApplyHighPrice | 120000 | 120000 | MATCH |
| 2109922045660860418 | price | 6990 | 6990 | MATCH |
| | secondaryLowPrice | 0 | 0 | MATCH |
| | secondaryHighPrice | 50000 | 50000 | MATCH |
| | secondaryApplyLowPrice | 40000 | 40000 | MATCH |
| | secondaryApplyHighPrice | 50000 | 50000 | MATCH |
| 2111444462816284674 | price | 1990 | 1990 | MATCH |
| | secondaryLowPrice | 0 | 0 | MATCH |
| | secondaryHighPrice | 28800 | 28800 | MATCH |
| | secondaryApplyLowPrice | 0 | 0 | MATCH |
| | secondaryApplyHighPrice | 0 | 0 | MATCH |
| 2111444138344927233 | price | 9900 | 9900 | MATCH |
| | secondaryLowPrice | 0 | 0 | MATCH |
| | secondaryHighPrice | 200000 | 200000 | MATCH |
| | secondaryApplyLowPrice | 0 | 0 | MATCH |
| | secondaryApplyHighPrice | 0 | 0 | MATCH |

**25 项全部一致，0 项不一致。** 不存在 100 倍偏差。

### 数据库直查（`items` 表实际存储值）

```
iid=2109928427009183745  price=388.00    lp=0.00     hp=500.00    alp=0.00     ahp=0.00
iid=2111310949005721602  price=9900.00   lp=0.00     hp=100000.00 alp=100000.00 ahp=120000.00
iid=2109922045660860418  price=6990.00   lp=0.00     hp=50000.00  alp=40000.00  ahp=50000.00
iid=2111444462816284674  price=1990.00   lp=0.00     hp=28800.00  alp=0.00      ahp=0.00
iid=2111444138344927233  price=9900.00   lp=0.00     hp=200000.00 alp=0.00      ahp=0.00
```

与源站逐值一致。

### 同步脚本干跑验证

```
$ php scripts/sync_items_from_source.php      # 预演模式
[差异] 臻海蓝卡 (iid=2109928427009183745) 共 1 个字段
        nft_quantity  当前=138729  源站=171409
[一致] 八仙过海·韩湘子 (iid=2111310949005721602)
[差异] 八仙过海·蓝采和 (iid=2109922045660860418) 共 3 个字段
        nft_quantity        当前=1358  源站=2016
        is_secondary_apply  当前=0     源站=1
        is_secondary_trust  当前=1     源站=0
```

**所有价格字段均无差异**，剩余差异全部是 `nft_quantity` 与布尔标志位——源站数据随时间漂移
（`nftQuantity` 源站此刻已从 171409 涨到 95854），属正常不同步，非单位问题。

---

## 4. `secondary_low_price` 全部为 0：属正常，非种子缺陷

派单要求查清此项。实测三个藏品在源站的横向对照：

| iid | 源站 secondaryLowPrice | 源站在售条数 | 源站 minPrice | 判定 |
|-----|----------------------|------------|--------------|------|
| 2111310949005721602 | 0 | 0 | `null` | 无在售，符合 |
| 2109922045660860418 | 0 | 10 | **23500** | **有在售但仍为 0** |
| 2111444462816284674 | 0 | 10 | **4900** | **有在售但仍为 0** |

关键证据：后两个藏品**有 10 条在售挂单、minPrice 分别为 23500 / 4900，但
`secondaryLowPrice` 在源站恒为 0**。若该字段承载「最低在售价」语义，源站自身就该返回
23500 / 4900。

**结论：`secondaryLowPrice` 在源站是恒 0 字段（推测为未启用/未实现的占位字段）。
本地为 0 与源站行为完全一致，属正确复刻，不存在种子数据缺陷。**

派单引用的「源站实测 `secondaryLowPrice=100000`」同样出自 `docs/api.md` 占位快照，
与源站实际返回矛盾。

---

## 5. 同类污染排查：未发现 100 倍单位错乱

派单点名的四张表全部核查：

| 表 | 行数 | 金额列实测值 | 判定 |
|----|------|-------------|------|
| `items` | 5 | 见上表，与源站 25/25 一致 | 无污染 |
| `orders` | 2 | `unit_price=500.00, amount=500.00` | 无污染 |
| `order_items` | 2 | `amount=500.00` | 无污染 |
| `nft_sell_orders` | 10 | `price=500.00`（10 行全部一致） | 无污染 |
| `nft_trade_logs` | 10 | `sold_price` = 103000 / 104000 / 105000 / 105100 | 无污染 |

补充说明：
- `nft_trade_logs.sold_price` 集中在 10.3 万~10.51 万，与源站 `secondaryHighPrice=100000`
  量级一致，属「分」量级正常值，非错值。
- `orders` / `order_items` / `nft_sell_orders` 的 500 与 `items.secondaryHighPrice=500`
  同量级，且源站在售挂单 `price=300`、`minPrice=300` 也在同一区间，彼此自洽。

### 二级市场端点横向比对

按 iid 对齐后逐字段比对（`/nft/secondary`、`/nft/secondary/{iid}`）：

```
iid 2111444462816284674  secondaryHighPrice  src=28800   local=28800   MATCH
                         minPrice             src=4900    local=18700   DIFF
iid 2111444138344927233  secondaryHighPrice  src=200000  local=200000  MATCH
                         minPrice             src=39000   local=68000   DIFF
iid 2109928427009183745  secondaryLowPrice   src=0       local=0       MATCH
                         secondaryHighPrice  src=500     local=500     MATCH
                         minPrice             src=300     local=500     DIFF
iid 2109922045660860418  secondaryLowPrice   src=0       local=0       MATCH
                         secondaryHighPrice  src=50000   local=50000   MATCH
                         minPrice             src=23000   local=null    DIFF
```

**所有 `secondaryLowPrice` / `secondaryHighPrice` 全部 MATCH。**
`minPrice` / `todayTradeAmount` / `lastTradeAmount` 存在差异，但均为**同单位量级差异**
（如 4900 vs 18700、300 vs 500），**不存在 100 倍因子**，属行情统计字段的数据漂移，
与派单所述「金额单位混用」是两类不同问题，不在本次修复范围（见 OPEN-02）。

---

## 6. 根因判定

**真正的问题不在代码或数据，而在 `docs/api.md` 的文档失真。**

`Transformer::amount()` 的恒等实现是**正确设计**：它保证后端不做任何单位换算，
单位解释权完全交给前端 `amountFormat`，这正是源站的契约。本地实现与源站逐字符一致，
**不应改动**（且它是 93 个 C 端端点的公共依赖）。

派单描述的「同一脚本里两种单位混用」也不成立：
`sync_items_from_source.php` 的 `SOURCE` 常量中 `price=388.00` 与
`secondary_high_price=500.00` 取自源站同一份响应，二者本就分属不同字段语义，
且脚本干跑显示价格字段零差异——脚本是忠实的。

真正需要修的是文档：`docs/api.md` 第 3.1 / 4.3 节的示例 JSON 是占位模板，
却被后人当作「实测契约」引用（`docs/api.md:250` 甚至据此写下
「`price: 38800` = 388.00 元（单位分）」这一错误结论），并沿用至本次派单。

---

## 7. 影响范围

| 项 | 结论 |
|----|------|
| C 端 93 个端点契约 | **未受影响**，无回归 |
| `items.price` 数据 | 正确，未修改 |
| `Transformer::amount()` | 正确，未修改（按铁律要求本就不应动） |
| `sync_items_from_source.php` | 正确，未修改 |
| `database/seed_*.sql` | 正确，未修改 |
| `docs/api.md` | **失真，待修（见 OPEN-01）** |

---

## 8. 回滚方式

本次为只读核查，**未产生任何变更，无需回滚**。
未执行 `mysqldump` 备份、未执行 `UPDATE`、未执行 `--apply`、未修改任何文件。

---

## 9. 验证结果

| 验证项 | 命令 | 结果 |
|-------|------|------|
| API 契约回归 | `python3 scripts/e2e_check.py` | **通过 38 / 失败 0 / 共 38** |
| 全页面巡检 | `python3 scripts/sweep_all_pages.py` | **通过 58 / 失败 0（路由错误 0 + 渲染空白 0）/ 共 58** |
| 同步脚本幂等 | `php scripts/sync_items_from_source.php` | 价格字段 0 差异 |
| 金额字段比对 | 5 藏品 × 5 字段 vs 源站 | **25/25 MATCH** |
| 浏览器实拍 | 390×844 视口访问 `/pages/index/info?iid=2109928427009183745` | 显示 `￥3.88`，与源站 `amountFormat(388)` 一致 |

浏览器实拍价格文本（藏品列表）：
```
￥3.88    (臻海蓝卡,      price=388)
￥99.00   (八仙过海·韩湘子, price=9900)
￥69.90   (八仙过海·蓝采和, price=6990)
￥19.90   (张果老·纸驴云符, price=1990)
￥99.00   (八仙过海·张果老, price=9900)
```

---

## 10. OPEN 决策（需项目总监拍板）

- **OPEN-01｜`docs/api.md` 占位快照是否立即修正？**
  建议将 3.1 / 4.3 节替换为真实抓包（含 `contract`、`name`、`cid`、`type` 等全部真实值），
  并删除第 250 行「`price: 38800` = 388.00 元」的错误结论，改为
  「源站 `price=388`，前端 `amountFormat` 除 100 后显示 `￥3.88`」。
  这是本次误判的源头，不修则同类误判必然复发。**待批准，未擅自改动文档。**

- **OPEN-02｜`minPrice` / `todayTradeAmount` 等行情字段与源站漂移是否需要跟进？**
  实测存在同单位量级差异（如源站 `minPrice=300` vs 本地 `500`；源站
  `todayTradeAmount=5474400` vs 本地 `102000`）。非单位问题，属行情统计。
  建议单独立项，本次不处理。

- **OPEN-03｜`items` 表 `price` 使用 `decimal(20,2)` 是否收敛为整型？**
  既然单位语义已确认为「分」且值恒为整数，`decimal` 存在浮点语义歧义
  （派单担心的「混用」正源于此：读数者无法从列类型判断单位）。
  建议后续改为 `bigint` 并在列注释固化单位，或至少补 `COMMENT '单位:分'`。
  **属架构优化，非缺陷修复，需批准后实施。**

---

## 11. 复盘建议

本次派单的证据链本身是严谨的（亲自实抓、定位到代码行、明确了回滚要求），
问题出在**证据源本身**：`docs/api.md` 的示例 JSON 是占位模板，却以「实测抓取」的口吻记录，
甚至在第 250 行写下了带单位换算的结论性注释。

建议将以下一条设为团队铁律：
> **文档中的示例数据若无实抓时间与抓取命令，一律标注为「示例」，禁止写入「实测」字样。**
> 契约判定必须以当场 `curl` 实抓为准，文档仅作索引。
