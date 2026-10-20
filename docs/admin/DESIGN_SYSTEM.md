# 臻海艺术 · 运营后台 设计规范（DESIGN_SYSTEM）

> 设计师：颜好看 ｜ 交付对象：前端 Agent ｜ 上游：PRD.md + ARCHITECTURE.md + ADR-006
> 寄存器判定：**Product**（设计服务产品，标杆是"赢得熟悉感"，不是独特性）
> 平台轴：**web**，形态锁定 **双端**（移动端 390px / 平板 640px / 桌面 1440px 两栏）
> 2026-10-21 变更：形态由「移动端 + PC 设备框」改为「真双端」。三断点与各断点差异见 `RESPONSIVE.md`，本文只写与形态无关的组件规范 + 形态相关的关键分档。
> 三轴刻度：`DESIGN_VARIANCE = 3`（后台要可预测，不要非对称）/ `MOTION_INTENSITY = 3`（仅状态反馈，零装饰动效）/ `VISUAL_DENSITY = 7`（驾驶舱偏紧，拒绝"一张卡包一切"）
> 图标库锁定：**lucide-vue-next 1.0.0**（精确版本，不用 `^1.0.0`），尺寸只允许 16 / 20 / 24px，`stroke-width: 1.75`
> 真源令牌：`/workspace/admin-web/src/styles/admin-tokens.css`（后台）→ `@import` → `/workspace/src/styles/tokens.css`（C 端）

---

## 0. 视觉基线与三条覆盖

后台视觉 100% 沿用 C 端令牌（主蓝 / 深蓝黑 / 圆角 / 8pt 间距 / 字号），**不新增品牌色，不新增圆角档，不新增间距档**。

C 端为了逐像素复刻源站继承了三条 uni-app 产物性声明，后台**不继承**：

| C 端声明 | 后台处理 | 依据 |
|---|---|---|
| `html { font-size: calc(100vw / 23.4375) }` | 锁死 `font-size: 16px` | 全屏等比缩放在 1920px 视口放大 4.92 倍，后台不可用 |
| `html,body,#app { font-family: initial }` | 显式声明系统 UI 字体栈 `--admin-font-sans` | `initial` 落到浏览器默认衬线体，是复刻产物不是设计决策 |
| `html,body,#app { width: 100% }` | 容器限宽居中 | 同上 |

**容器约束（必须实现）**：

```
三断点：
  <768px     移动端   满宽铺满，底部 TabBar，单列
  768~1023px 平板     居中，max-width 640px，底部 TabBar 保留
  ≥1024px    桌面     max-width 1440px，侧边 Rail 200px + 主内容区两栏
                        底部 TabBar 不渲染
根字号：锁 16px，不随视口缩放
height:auto 覆盖必须保留（否则长列表滚不动）
```

三个断点各自的值、令牌与各模块布局差异、硬约束的 PC 端逐条处置，见 `RESPONSIVE.md`。**桌面端不做「手机画中画」**——原 `--admin-frame-max / --admin-frame-pad / --admin-frame-radius` 三条设备框令牌已删除，不再有居中 430px 框体与裁切圆角。

> **移动端（<768px）渲染结果与双端改造前逐像素一致**，这是「不推翻已验收成果」的技术边界。

---

## 1. 色彩与角色

| 角色 | Token | 取自 |
|---|---|---|
| 页面底 | `--admin-bg` | C 端 `--color-bg-page` |
| 卡片 / 弹层 | `--admin-surface` | C 端 `--color-bg-card` |
| 输入框 / 按下态 | `--admin-surface-sunken` | C 端 `--color-bg-fill` |
| 选中行 / 提示条 | `--admin-surface-tint` | C 端 `--color-bg-fill-blue` |
| 一级文字 / 关键数值 | `--admin-fg-title` | C 端 `--color-text-title` |
| 正文 | `--admin-fg` | C 端 `--color-text-body` |
| 辅助 / 时间戳 | `--admin-fg-muted` | C 端 `--color-text-muted` |
| 主操作 / 选中 / 进行中 | `--admin-accent` | C 端 `--color-primary` |

**每屏强调色可见处 ≤2**：一处给当前主操作（底部操作栏主按钮 或 FAB），一处给当前选中态（TabBar 选中项 或 Tab 指示器）。列表中的链接、图标、标签一律不用主蓝。

**语义色文字档**：四档语义色在浅底上对比度均 < 3:1，直接用做文字不达 AA。`--admin-success-fg / --admin-warn-fg / --admin-danger-fg / --admin-info-fg` 由语义色与标题深蓝黑 `color-mix()` 得到同色相深色版本（对比度 4.6 ~ 7.0:1）。**组件里写文字色一律用 `-fg` 档，写填充/描边才用原档。**

**深色主题**：`[data-admin-theme="dark"]` 骨架已建，全部由品牌深蓝黑与 `#000` / `#fff` `color-mix()` 派生（bg 更暗、surface 更亮，靠亮度递进而非阴影表达层级）。MVP 只上浅色，前端只需保证 `data-admin-theme` 属性切换不被写死。

---

## 2. 排版

| 用途 | Token | 实际值 |
|---|---|---|
| 摘要数值 | `--admin-fs-metric` | 24.96px / lh 1.1 |
| 页面标题 | `--admin-fs-page` | 20.8px / lh 1.35 |
| 卡片主标题、区块标题 | `--admin-fs-title` | 16.64px / lh 1.35 |
| Tab、次级标题 | `--admin-fs-sub` | 15px |
| 正文 | `--admin-fs-body` | 16px / lh 1.5 |
| 字段标签、次要信息 | `--admin-fs-sm` | 12.48px |
| 状态标签、时间戳 | `--admin-fs-xs` | 11.44px |
| TabBar 文字（下限） | `--admin-fs-mini` | 10.92px |
| PC 密集位正文（仅 ≥1024） | `--admin-fs-dense` | 14px |

字重三级：400 正文 / 500 小标题与标签 / 700 页面标题与摘要数值。
数字、ID、金额、编号一律加 `.admin-num`（`--admin-font-mono` + `tabular-nums`），避免列表滚动时列宽跳动。

**关于「PC 正文 14px」的显式偏离登记（唯一一处）**

结论：**不采纳全局正文 14px，采纳「密集位 14px / 阅读位 16px」分档。**

| 位置 | 字号 | 理由 |
|---|---|---|
| 详情页 DL value、审计 diff、驳回理由、说明段落（阅读位） | `--admin-fs-body` 16px **不变** | 16px 中文正文一行 60~70 字符，已在 50~75 可读上限内；缩到 14px 反而把行长推到 80+ 字符，且中文小字笔画粘连。阅读位是长句，恰好最需要可读性 |
| PC 表格单元、列表行内副文本、筛选 chip、Rail 项（密集位） | `--admin-fs-dense` 14px | 密度问题在密集位解决，不在阅读位解决。14px 是 C 端体系不存在的新数值，属 C-extension（后台专属），仅 ≥1024 生效 |

**14px 是本项目唯一一处对 C 端字号体系的显式偏离**，登记在 `admin-tokens.css` §16.1。遗留风险：深色主题上线时需单独复核密集位（`--admin-fg` / `--admin-fg-2`）的对比度。

---

## 3. 信息密度约束（双端分层）

后台字段多。移动端只有 390px，规则不靠"每个页面自己看着办"，靠下面三条硬约束。**这三条在 ≥768px 后逐步放宽，放宽的逐条依据见 `RESPONSIVE.md` §4，本节只写基准形态。**

### 3.1 字段三级下沉

| 层级 | 出现位置 | 判据（必须逐字段过一遍） | 数量上限 |
|---|---|---|---|
| **L1 首屏** | 列表卡片第一行区 | 运营靠它**认出**这条记录吗 | **3 个 + 状态标签** |
| **L2 就地展开** | 卡片底部 accordion | 不跳详情页，能**判断要不要处理**吗 | ≤8 个 |
| **L3 二级详情** | 详情页 Tab 分组 | 其余全部 | 不限，但分组 ≤4 |

L1 三个字段固定语义槽位，所有列表一致（换模块只换取值，不换结构）：

```
槽位 1  主标识（记录名）       --admin-card-title-fs
槽位 2  编号 / ID（等宽）      --admin-card-id-fs
槽位 3  一个业务关键值         --admin-card-metric-fs  ← 每个列表自定，全表唯一
```

**PC 端表格列是 L1+L2 的合并投影，不引入新字段**：每张 PC 表格的每一列，必须在移动端可达（首屏 L1 或 L2 展开区），不存在「只有 PC 能看到」的字段。逐列表的 PC 列清单见 `RESPONSIVE.md` §4.2。

各列表的槽位 3 取值（写死，前端不得自行改）：

| 列表 | 槽位 1 | 槽位 2 | 槽位 3 |
|---|---|---|---|
| 藏品列表 | 藏品名（臻海蓝卡） | `#839848` | 售价 `￥3.88` |
| 版权队列 | 版权名 | 版权 ID | 关联诊断结论 |
| 挂单监控 | 藏品名 + NFT 编号 | 挂单 ID | 挂单价 `￥5.00` |
| 成交记录 | 藏品名 | 成交 ID | 成交价 `￥1,051.00` |
| 用户列表 | 手机号（掩码） | 用户 ID | 钱包余额 |
| 实名台账 | 手机号（掩码） | 用户 ID | 实名状态标签 |

### 3.2 操作按钮位置（移动端不放行尾；PC 端放开到行尾 ≤2）

PC 后台的"行尾操作列"在 390px 下必然挤成 <80px 的按钮，误触率高。替代方案按优先级：

| 优先级 | 形态 | 适用场景 | 尺寸 |
|---|---|---|---|
| 1 | **卡内「更多」→ ActionSheet** | 一条记录有 ≥2 个操作 | 热区 44×44，图标 `--admin-card-more-icon` 20px |
| 2 | **左滑露出单主操作** | 一条记录只有 1 个高频操作（如强制下架） | `--admin-card-swipe-w` 72px/个，最多 2 个 |
| 3 | **底部固定操作栏** | 提交、批量、需确认的动作 | `--admin-footer-h` 56px |
| 4 | **FAB** | 列表页「新建 / 录入」且该动作是页面唯一主入口 | `--admin-fab-size` 56px |

**禁止**：卡片内横排 ≥3 个文字按钮；操作图标小于 16px；纯图标无 `aria-label`。

**PC 端（≥1024）放开行尾操作列**，但保留三条限制：
1. **最多 2 个文字按钮 + 1 个「更多」图标**（`--admin-pc-col-actions: 160px`）。第三个及以后的操作仍收进「更多」。
2. **高危操作（强制下架 / 冻结用户 / 驳回 / 查看完整证件号）不放行尾**，只进「更多」并走二级高危确认（必填理由 + 手输确认词）。误触成本与端无关。
3. **按钮高 32px**（`--admin-pc-row-action-hit`），低于 44px 触摸目标——PC 是指针环境，44px 硬约束只对触摸端成立。图标仍 16px 档。

### 3.2b 断点分档速查（详细依据见 RESPONSIVE.md §4）

| 约束 | <768 | 768~1023 | ≥1024 |
|---|---|---|---|
| 详情页 Tab 数 | ≤4 | ≤4 | ≤4（**不放宽**，工作记忆上限与屏宽无关） |
| 一级导航项数 / 文字 | ≤4 项 / ≤2 汉字 | ≤4 项 / ≤2 汉字 | ≤6 项 / ≤4 汉字 |
| 列表可见字段 | L1 三字段 + 标签 | 同左 | 4~8 列（表格） |
| 行尾操作 | 无 | 无 | ≤2 文字 + 更多 |
| 列表分页 | 加载更多（阈值 40） | 加载更多（阈值 40） | >40 条走页码（40/页，阈值同端） |
| 筛选 | 底部弹层（AC-33） | 底部弹层 | 顶部横排条 + 更多筛选下拉 / 360px 侧面板 |
| 折线图 | 无 | 无 | 数据点 ≥30 才允许（两端同锁） |
| 成交记录列表 | 卡片流（L1 四主字段） | 卡片流 | 表格 6 列，**全端无横向滚动、无冻结首列** |

### 3.3 多字段详情组织（不准"卡片套卡片"）

详情页用 **Tab 分组 + 定义列表（DL）**：

- Tab 数 **≤4**（`--admin-tab-max`，工作记忆上限），默认顺序：概览 / 内容 / 交易 / 审计；按角色裁剪，不用的 tab 不渲染
- 每组内部是 DL：左 label `--admin-dl-label-w: 88px` 固定宽（`--admin-fs-sm` + `--admin-fg-muted`），右 value `flex:1`（`--admin-fs-body` + `--admin-fg`），行高 `min-h: 44px`
- 行长文本（`items.content`、`remark`）默认折叠到 `--admin-dl-longtext-fold-h: 96px`，「展开全文」位于文本下方右对齐
- 空值不渲染为空白，渲染为「未录入」+ `--admin-fg-disabled`，并给「去编辑」入口（PRD 模块 E）
- **禁止**用带边框的子卡片包裹每个字段组——390px 下会出现三层边框嵌套

---

## 4. 组件规范

每个组件给出：结构 / 尺寸 / Token / 断点行为。状态矩阵统一为 5 态：Loading / Empty / Error / Populated / Edge。

### 4.1 列表卡片（ListCard）

后台主信息载体，C 端无对应物。

**结构（自上而下）**

```
┌────────────────────────────────────────────────────────┐
│ [缩略图 56]   主标识 臻海蓝卡                  [状态标签] │
│  56×56        #839848                          待审     │
│  r=6px        售价 ￥3.88 · 库存 12                     │
│ ─────────────────────────────────────────────────── │
│ 展开 8 项 ▾                                    [ ⋯ ]  │
└────────────────────────────────────────────────────────┘
```

- 左：缩略图 `--admin-card-thumb: 56px`，`--admin-card-thumb-radius`
- 中：三行文字，行间距 `--admin-card-row-gap: 4px`；第 1 行标题与状态标签同基线
- 右：状态标签固定右上角对齐；「更多」按钮 44×44 热区放在卡片最右，与状态标签**不同行**（状态在标签行，更多在折叠条行）——避免两个可点目标上下相邻 4px，误触
- 底：L2 折叠条 `--admin-card-expand-toggle-h: 36px`，整条可点

**尺寸**：外边距 `--admin-card-gap: 12px`；内边距 `--admin-card-pad: 16px`；最小高 `--admin-card-min-h: 76px`；圆角 10px。

**Token**：`--admin-card-bg / --admin-card-border / --admin-card-shadow / --admin-card-thumb / --admin-card-title-* / --admin-card-id-* / --admin-card-meta-* / --admin-card-metric-* / --admin-card-expand-*`

**状态**

| 状态 | 表现 |
|---|---|
| Loading | 骨架卡：缩略图 56 灰块 + 三行文字占位，仅首屏 3 张，`--admin-motion-base` 微光 |
| Empty | 不渲染卡片，走 §4.6 空态 |
| Error | 卡片区内嵌错误条：文案「加载失败，点击重试」+ 重试按钮，不弹全屏 |
| Populated | 如上图 |
| Edge | 主标识超长：`ellipsis-2`（2 行截断）；编号超长：`ellipsis` + 等宽；数值超 6 位：千分位 + 单位后置 |
| Press | `--admin-card-bg-press`，`--admin-motion-instant` |
| Selected | `--admin-card-bg-selected` + `--admin-card-border-selected`（仅批量模式出现） |

**断点行为**：
- 移动端（<768）：卡片流，L1 三字段 + 状态标签，L2 accordion 展开 8 项。390px 结构完全一致。
- 平板（768~1023）：同移动端，容器宽上限 640px，缩略图仍 56px。
- 桌面（≥1024）：**改表格 / 行式列表**（`--admin-pc-row-*`），4~8 列，**无 L2 展开**（字段平铺）、**无左滑**、**无卡内「更多」**，操作改行尾列（≤2 文字 + 更多）。左滑在 768~1023 的鼠标环境下同时保留 hover 等价图标按钮。

---

### 4.2 搜索筛选区（SearchFilter）

**结构**：列表页顶部固定条（`--admin-filter-bar-h: 44px`），左侧搜索框（`flex:1`），右侧「筛选」chip（带已选数量角标）。

- 搜索框：高 36px，`--admin-search-radius` 全圆角，底 `--admin-search-bg`，前置 lucide `Search` 16px
- 筛选 chip：高 32px，未激活 `--admin-filter-chip-bg`；有已选条件时切 `--admin-filter-chip-active-bg` + 数量角标
- 快速筛选（状态枚举）以 chip 排列在搜索条下方。**v1.3 修正**：原「横排 + 横向滚动 + 不换行」的结论作废——**横向滚动与成交记录表是同一类反模式**（内容在视口外不可发现），且 AC-34 要求 320–428px 首屏无横向滚动条。改为：**<768 折行（最多 2 行，超出部分收进底部弹层的「快速筛选」分组）；≥1024 横排不换行**（1216px 内放得下）。

**筛选底部弹层**（AC-33，v1.3 修正）：点「筛选」胶囊，从屏幕底部升起。上圆角 `--admin-radius-sheet`（14px），宽 100%，最大高 70vh（字段多时内部滚动）。字段间距 `--admin-filter-field-gap: 20px`，底部固定「重置 / 确定」双按钮（栏高 `--admin-footer-h`，两按钮等宽，热区 ≥44px）。

**原「右侧抽屉 `calc(100% - 56px)`」的结论已作废**，理由三条：① 拇指可达区在屏幕下半部，底部弹层的「重置/确定」与 TabBar 同侧；② 右侧滑动是系统返回手势冲突区；③ 390px 视口下 56px 露边不是「暗示可 dismiss」而是**误触区**（点到即关闭，条件全丢）。

**Token**：`--admin-search-*` / `--admin-filter-chip-*` / `--admin-filter-drawer-*`（宽度令牌在底部弹层形态下不再用于筛选主入口，仅保留给桌面 360px 侧面板）

**状态**：Loading 时筛选按钮禁用（避免条件与结果不一致）；Error 时保留已填条件；Edge 时弹层内字段 >6 个改为分组折叠（组标题 `--admin-filter-group-title-fs`）。

**断点行为**：
- 移动端（<768）：**底部弹层**（AC-33），底部固定「重置 / 确定」。
- 平板（768~1023）：**底部弹层**，弹层宽度跟随内容区并居中。
- 桌面（≥1024）：**改顶部横排筛选条**（高 48px）：搜索框定宽 320px + 筛选 chip ≤4 个直接可见 + 「更多筛选」下拉面板 360px + 「重置」。**选择规则**：筛选字段 ≤6 且全为枚举 → 下拉；含日期区间 / 多选 / 需输入 → 保留 360px 侧面板。**这是字段复杂度的形态选择，与视口无关**，不是端侧特例。

---

### 4.3 详情 Tab（DetailTabs）

**结构**：Tab 条 `--admin-tab-h: 44px`，等宽平分（≤4 项），激活项文字 `--admin-tab-active-fg` + 2px 指示器（`--admin-tab-indicator-w: 24px`，居中）。

- 内容区用 DL（§3.3），不用卡片套卡片
- Tab 切换不重置滚动位置到顶，但记录各 tab 的滚动位置

**Token**：`--admin-tab-*` / `--admin-dl-*`

**状态**：Loading 用行骨架（每行 44px，3 行）；单个 tab 取数失败只在该 tab 内显示错误条，不整页失败；Empty 用区块级空态（`--admin-empty-inline-pad-y: 32px`，不带 64px 圆形底——区块级空态比页面级轻一档）。

**断点行为**：
- 移动端（<768）：Tab 数 ≤4 永不滚动；若角色权限裁剪后只剩 1 个 tab，隐藏 Tab 条直接渲染内容。
- 平板（768~1023）：同移动端。
- 桌面（≥1024）：**Tab 数 ≤4 不变**（工作记忆上限与屏宽无关）。内容区 DL 改双列并排（label 120px / value），长文本默认不折叠。

---

### 4.4 表单弹层（FormSheet）

**结构**：底部半屏 sheet（默认）或居中 dialog（高危才用）。

- 单行字段高 `--admin-field-h: 44px`，间距 `--admin-field-gap: 16px`
- label 在输入框**上方**（`-label-fs` 12.48px，`--admin-fg-2`），不用左侧 label——390px 下左侧 label 会吃掉 88px
- 必填标 `*` 用 `--admin-field-required-mark-fg`
- 多行文本 `--admin-textarea-min-h: 88px`
- 提交按钮走底部固定操作栏，不放在表单流内
- sheet 最大高 `--admin-form-max-h: 76vh`，超出内部滚动，底部操作栏始终可见

**Token**：`--admin-field-*` / `--admin-form-*` / `--admin-override-badge-*`

**状态**：失焦校验 + 提交前全量校验（ADR 沿用司南纪律）；错误文案紧贴字段下方（`-error-fs` 11.44px）；禁用态 `--admin-field-bg-disabled`；提交中按钮 loading 且整个 sheet 不可 dismiss（防重复提交）。

**覆盖写标识**：后台所有写操作都带 `is_local_override`。表单内被覆盖过的字段，label 右侧挂 `--admin-override-badge-*` 徽标「已覆盖」，并在字段下方给「还原」入口（PRD AC-04）。

**断点行为**：
- 移动端（<768）：底部半屏 sheet，键盘弹起时 sheet 上移，提交栏贴键盘顶。
- 平板（768~1023）：底部 sheet 宽度跟随内容区并居中。
- 桌面（≥1024）：**改居中 Dialog 480px**（`--admin-pc-dialog-w`），label 移到**左侧** 120px（`--admin-pc-form-label-side: 1`），字段间距 20px，保存/取消在 Dialog footer 右对齐（不铺满）。

---

### 4.5 状态标签（StatusTag）

五态固定映射，全后台一致（对齐 enterprise.md：绿=通过 / 红=驳回 / 蓝=进行中 / 灰=失效 / 橙=警示）：

| 状态 | 文案 | 底色 Token | 文字 Token | lucide 图标（16px） |
|---|---|---|---|---|
| 待审 | 待审 | `--admin-tag-pending-bg` | `--admin-tag-pending-fg` | `Clock` |
| 已通过 | 已通过 | `--admin-tag-passed-bg` | `--admin-tag-passed-fg` | `CheckCircle2` |
| 已驳回 | 已驳回 | `--admin-tag-rejected-bg` | `--admin-tag-rejected-fg` | `XCircle` |
| 已下架 | 已下架 | `--admin-tag-off-bg` | `--admin-tag-off-fg` | `Archive` |
| 异常 | 异常 | `--admin-tag-anomaly-bg` | `--admin-tag-anomaly-fg` | `AlertTriangle` |

**尺寸**：高 20px，左右内边距 8px，字号 11.44px，圆角 4px，图标 16px 与文字间距 4px。

**硬要求（WCAG 1.4.1）**：状态必须**文字 + 颜色**双重编码，图标可选但不得替代文字。禁止只用一个色点表示状态。

**脏数据态**：藏品 `status` 实测存在 `on` / `sold_out` / `1` 三种值。标签一律显示归一后的语义文案（在售 / 已售罄），并把原始值作为副标用 `--admin-fs-xs` 弱化处理（PRD §9.3 模块 E），不在标签上直接显示 `1`。

---

### 4.6 空态（EmptyState）

三种空态必须视觉可区分（PRD §9.2，禁止「暂无数据」了事）：

| 类型 | 图标（24px） | 图标色 | 文案结构 | 附加 |
|---|---|---|---|---|
| 功能未开放（依赖表 0 行） | `DatabaseZap` | `--admin-empty-icon-fg` | 「该模块依赖的数据表当前无数据，功能待数据积累后启用」 | 等宽展示依赖表名与当前行数（`--admin-empty-meta-*`），说明启用条件 |
| 筛选无结果 | `SearchX` | `--admin-empty-icon-fg` | 「当前筛选条件下无匹配结果」 | 主操作「清除筛选」 |
| 配置缺失 / 数据脏 | `AlertTriangle` | `--admin-empty-warn-icon-fg` | 「存在 N 条记录缺少必要关联信息」 | 跳转数据治理入口 |

**尺寸**：圆形底 64px + 24px 图标（图标尺寸锁 16/20/24，容器不是图标，不放放大版图标）；标题 `--admin-empty-title-fs` 15px；描述 `--admin-empty-desc-fs` 12.48px，最大宽 300px；上下留白 48px；文案与主操作间距 16px。

**区块级空态**（详情页某一组为空）：去掉圆形底，只留 24px 图标 + 单行文案，上下 32px。

---

### 4.7 操作确认（ConfirmLayer）

两级，按风险分：

**一级 · ActionSheet 确认**（常规操作，如上下架、改推荐位）
- 底部 sheet，标题 = 动作名，副说明 = 影响范围（1 行，`--admin-sheet-desc-fs`）
- 选项行高 52px，图标 20px，危险项文字用 `--admin-sheet-danger-fg`
- 取消按钮间距 8px

**二级 · 高危全屏确认**（强制下架、冻结用户、清除全部覆盖、查看完整证件号）
必须同时满足四个条件才可提交：

1. 居中 dialog（`--admin-danger-dialog-radius` 16px），标题 `--admin-danger-title-fg`
2. 展示影响面面板（`--admin-danger-panel-bg` + `--admin-danger-border`）：对象名 + 将变更字段 + 前值 → 后值
3. **必填理由**（`--admin-danger-reason-h: 88px` 多行），不填无法提交
4. 涉及资金/身份/不可逆的，追加**手输确认词**（`--admin-danger-confirm-word-fs`）：输入对象编号后 4 位

提交后写入审计日志（PRD 强约束：留痕不可延后）。

**Token**：`--admin-sheet-*` / `--admin-danger-*`

**状态**：提交中按钮 loading + 全屏不可 dismiss；409 写冲突（`code=14009`）提示刷新后重试，不静默失败；14003 权限不足 toast 不跳登录。

---

### 4.8 数据摘要卡（StatCard）

PRD §9.3：样本量不足（用户 36 / 订单 2 / 成交 10）**不画折线图**，改指标卡 + 分布列表。

**结构**：圆角 10px 白卡，内边距 16px。自上而下：数值（`--admin-stat-value-fs` 24.96px，`--admin-fg-title`，等宽 tabular）→ 单位（`--admin-stat-unit-fs`）→ 标签（`--admin-stat-label-fs` 11.44px，`--admin-fg-muted`）。

**栅格**：390px 内一行 **2 张**（`--admin-stat-cols: 2`），间距 12px。**禁止 3 张**——每张仅剩 ~118px，24.96px 数值必溢出。

**依赖表为空时**：卡片置灰 `--admin-stat-disabled-opacity`，数值位显示「依赖表 `wallet_transactions` 当前 0 行，待数据积累后启用」，**绝不显示 0**（PRD：「0 条流水」与「这个指标没做」是两回事）。

---

### 4.9 审计日志时间线（AuditTimeline）

**结构**：单列纵向。轴宽 2px 位于左侧 24px 缩进处；节点 8px（最近一条 10px + `--admin-timeline-node-ring`）。

单条自上而下：时间（等宽，`--admin-timeline-time-fs`）→ 动作（`--admin-timeline-action-fs`，主信息）→ 操作人 + 角色（`--admin-timeline-actor-fs`）→ 变更明细。

**前后值比对（关键约束）**：390px 下**禁止并排 diff**。改为纵向堆叠：

```
售价    ￥3.88  →  ￥5.00
        ↑ 旧值 --admin-timeline-old-fg + 删除线
                   新值 --admin-timeline-new-fg
```

字段名单列一列，旧值/新值右对齐成一列，中间 8px 箭头。明细区块底 `--admin-timeline-diff-bg`，圆角 6px，内边距 8px。

**Token**：`--admin-timeline-*`

**状态**：Loading 用 3 条骨架条目；Empty 文案「该对象暂无后台操作记录」（区别于取数失败）；Edge 超长值（如长文本 content）截断为前 40 字 + 「查看完整」跳转。

---

### 4.10 底部操作栏（FooterBar）

**结构**：`position: fixed` 贴底，高 56px（含安全区 `--admin-safe-bottom`），内边距 左右 12px / 上下 8px，背景 `--admin-footer-bg` + `--admin-footer-shadow`。

**按钮排布硬约束**：最多 **1 主 + 1 次**。第 3 个及以后的操作必须收进「更多」（ActionSheet）。按钮间距 12px，主按钮 `flex: 1`，次按钮固定宽（按内容 + 32px）。

**与主 TabBar 共存**：列表页同时存在底部 TabBar（50px）与操作栏时，操作栏叠在 TabBar 上方（`bottom: calc(var(--admin-tabbar-h) + var(--admin-safe-bottom))`），列表底部补 `padding-bottom` 避免遮挡最后一条。详情页无 TabBar，操作栏直接贴底。

**Token**：`--admin-footer-*`

---

## 5. 导航

| 层 | 形态 | Token | 说明 |
|---|---|---|---|
| 一级（<1024） | 底部 TabBar | `--admin-tabbar-*` | 4 项（工作记忆上限 ≤5）：工作台 / 藏品 / 市场 / 用户。图标 20px，文字 10.92px 且 **≤2 汉字**（`--admin-tabbar-label-max-chars: 2`），选中 `--admin-tabbar-fg-active` |
| 一级（≥1024） | 侧边 Rail | `--admin-rail-*` | **MVP 启用**。宽 200px，可折叠 56px（`--admin-rail-w-collapsed`）。**≤6 项、文字 ≤4 汉字**（`--admin-tabbar-label-max-chars-lg: 4`）。选中态 = 左侧 2px 指示条 + 底色双重编码 |
| 二级 | 顶部 NavBar | `--admin-navbar-*` | 高 44px，左返回、中标题、右单页操作（最多 1 个）。热区 44×44。桌面端标题升级为 20.8px（`--admin-navbar-title-fs-lg`） |

**为什么 768~1023 保留 TabBar 而不上 Rail**（v1.3 修正论证）：原论证「iPad 竖屏 820px 扣 200px Rail 后只剩 364px，比移动端 390px 还窄」**算术错误**——820 − 200 = **620px**，620 明显宽于390，「比移动端还窄」不成立。**结论不变，理由换成两条**：

1. **净收益 −20px**：平板方案内容区 640px，上Rail 后变成 620px，**内容区反而缩 20px**。Rail 省下的垂直空间（6 项 × 44px = 264px）在单列布局里本来就用不上——内容本就是纵向流式，不需要「把垂直空间还给内容」。**用 20px 宽度损失换一个在本区间无收益的收益，是净负。**
2. **旋转换形态的认知成本**：iPad 竖屏（820）拿 TabBar，横屏（1180）立刻换Rail，同一台设备旋转即换导航形态。导航形态应由「是否真的需要侧栏」决定，而非「够不够宽」。

平板区间用「单列居中 640px + 底部 TabBar」是唯一正解。

**为什么桌面 Rail 反而更好**：1440px 宽下 Rail 只占 13.9%，主内容仍有 1216px（远宽于移动端 390px），且竖排导航省下的垂直空间可以还给内容。Rail 的价值在于「省垂直空间 + 一次看到全部一级入口」，这两点只在宽屏成立。

角色裁剪：TabBar / Rail 项按 `permissions` 过滤，`reviewer` 只见「工作台（版权待办）+ 版权」，无权限项**不渲染**而非置灰（PRD §4.3 路由守卫）。Rail 项总数是**角色相关的**（4~6 项），不是全局固定 6。

---

## 6. Do's and Don'ts

**Do**

- 列表首屏只露 3 个字段 + 状态标签，其余下沉
- 金额、ID、编号一律 `.admin-num` 等宽 + tabular-nums，单位后置（`￥3.88` 不是 `3.88 元`）
- 状态标签始终带中文文案，颜色只是辅助编码
- 空态写清「为什么空」+ 依赖表名 + 行数
- 写操作一律挂覆盖徽标 + 二次确认 + 审计留痕
- 骨架屏只出首屏 3 个，不要满屏骨架
- PC 端表格列取自移动端 L1+L2 的合并投影，不引入「只有 PC 能看到」的字段
- PC 端高危操作仍走二级确认（理由 + 确认词），不因有鼠标就放松
- PC 端密集位用 `--admin-fs-dense` 14px，阅读位保持 `--admin-fs-body` 16px

**Don't**

- 不用 emoji 充当任何功能图标（含空态、状态标记、菜单项）
- 不用紫→粉渐变；全后台唯一渐变是 `--admin-accent-grad`（同色系蓝→深蓝）
- 不硬编码色值（唯一例外 `#fff` / `#000`）；淡色底一律 `color-mix()` 派生
- 不用 `initial` / 浏览器默认字体；不出现英文欢迎式占位文案、拉丁假文占位、「暂无数据」四字了事
- 不做居中大标题 + 副标题 + CTA 的 Hero；工作台首屏直接是待办与指标
- 不放弹跳缓动（带负 y 控制点的 overshoot 曲线）；动效 ≤260ms
- 不在 390px 内做 3 列摘要卡、7 列表格、行尾操作列
- **不做任何横向滚动表格，任何断点都不做**；也**不实现冻结首列**（`position: sticky` 首列）。超宽内容下沉到详情页或卡片 L2，不横向滚（AC-29 / AC-57）
- **移动端与平板筛选一律底部弹层**，不用右侧抽屉（AC-33）
- **分页阈值两端统一 40**，不为「桌面看起来整齐」降到 25
- **不在桌面端做「手机画中画」**（居中 430px 框体 + 裁切圆角已删除）
- **不因断点放宽折线图的数据门槛**：数据点 <30 一律不画折线，屏幕变宽不是理由
- **不全局把正文降到 14px**：阅读位保持 16px，只有密集位用 `--admin-fs-dense`
- 不臆造示例数据；文案用真实值（臻海蓝卡 / `#839848` / `￥3.88` / 挂单价 `￥5.00` / 成交价 `￥1,051.00`）

---

## 7. 无障碍与响应式

- 对比度：正文与底 ≥4.5:1；语义色文字一律走 `-fg` 档（4.6~7.0:1）
- 触摸目标：所有可点区域 ≥44×44px（Tag 与 chip 是**展示**组件不可点，不受此限）
- 焦点：`:focus-visible` 走 `--admin-focus-ring`，全局强制，组件不得 `outline: none` 覆盖
- 图标：装饰性 `aria-hidden="true"`；可点图标按钮必须 `aria-label`
- `prefers-reduced-motion: reduce` 全局收敛到 0.01ms（令牌文件已内置）
- 断点：三个（768 / 1024 / 1280）。`<768` 移动端满宽 + 底部 TabBar；`768~1023` 平板居中 640px + 底部 TabBar；`≥1024` 桌面 1440px 两栏 + 侧边 Rail。完整矩阵见 `RESPONSIVE.md`
- 键盘可达：桌面端表格行、Rail 项、chip 必须可用 Tab / 方向键到达；触屏目标 ≥44×44px 的要求只对 <1024 成立（PC 指针环境行内操作按钮 32px）

---

## 8. 前端实现提示（Agent Guide）

1. `admin-web/src/styles/admin-tokens.css` 已在顶部 `@import` C 端令牌（相对路径 `../../../src/styles/tokens.css`）。**`admin-web/vite.config.js` 必须加 `server.fs.allow: ['/workspace']`**，否则 dev 下 Vite 拒绝 root 之外的文件。

   > **已实测验证（2026-10-20）**：搭最小复现工程（root=`proj/`，`@import` 指向 root 外的 `../src/tokens.css`）实测，
   > Vite 6.4.4 对 root 之外路径返回 **HTTP 403**（`/@fs/...` 请求），跨 root 的 CSS 直接加载不到。
   > 这条不是"保守建议"，删掉配置后台样式会整体失效，且报错信息不会直接指向根因。
2. 组件内禁止出现 `--color-*`，只允许 `--admin-*`。C 端改色后台自动跟随。
3. 图标只允许 `import { Xxx } from 'lucide-vue-next'`；`lucide-vue-next` 锁 `1.0.0`（非 `^1.0.0`）；`:size` 只允许 16 / 20 / 24；`:stroke-width="1.75"`。
4. 数字统一走 `.admin-num`；金额统一由后端 `xxxYuan` 字段取（单位元），前端不再做分→元换算（ARCHITECTURE §3.3）。
5. 页面提示词见 `/workspace/docs/admin/PAGE_PROMPTS.md`；双端差异与断点矩阵见 `/workspace/docs/admin/RESPONSIVE.md`；令牌机器可读版本见 `/workspace/admin-web/src/styles/admin-tokens.json`。
6. **列表组件分两套**：`ListCard`（<1024）与 `DataTable`（≥1024），消费**同一份字段定义**（建议抽到 `listFieldDefs.js`），移动端投影出 L1/L2，桌面端投影出列。不各自维护映射，否则两端口径必然漂移。
7. **Rail 与 TabBar 互斥渲染**：`<1024` 不渲染 Rail 组件，`≥1024` 不渲染 TabBar 组件（不是 CSS 隐藏——隐藏的组件仍会加载图标与占位）。`admin-tokens.css` 里的 `.admin-tabbar { display:none }` 只是兜底。
8. **双端一致性自检清单**（18 条，位于 `RESPONSIVE.md` §8）是本项目的实现验收项。设计侧 review 时按该清单逐条核对；A 组（字段）5 条建议由 `listFieldDefs.js` 的构建期断言自动保证，其余 13 条需人工核对。

---

## 9. 双端一致性检查项（摘要）

完整可执行清单见 `RESPONSIVE.md` §8（共 18 条）。设计侧在此只列**最容易被实现做偏的 5 条红线**：

1. **全端无横向滚动表格、无冻结首列**——包括成交记录（<1024 走卡片流）与任何超过 8 列的桌面表格（字段下沉，不加列）。
2. **筛选形态由 AC-33 锁定**：<1024 底部弹层（含底部固定「重置/确定」），≥1024 顶部横排条 + 下拉/360px 侧面板。桌面侧面板是**字段复杂度触发**，不是端侧特例。
3. **分页阈值跨端统一 40**；PC 页码与移动端「加载更多」共用同一常量。
4. **高危操作两端都不在行尾**，走同一套二级确认（必填理由 + 确认词）。PC 放开行尾只针对低危操作。
5. **导出的 CSV 字段集 = 桌面表格可见列**，不因导出通道多出任何一端看不到的字段。

**断点的职责边界（v1.3 明确）**：断点**只改变排布**（卡片↔表格、弹层↔筛选条、Sheet↔Dialog、密度、列数）。断点**不得改变**：字段集合、数据门槛（折线 ≥30）、操作语义与总数、高危确认流程、分页阈值、空态与状态文案。**凡是「因为屏幕宽/窄所以含义变了」的设计，一律是错的。**
