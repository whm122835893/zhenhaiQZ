# 臻海艺术 · 运营后台管理 技术架构

> 版本 1.0.0 | 编制日期 2026-07-19 | 编制 高见远（首席架构师）
>
> 本文档是后台前端的**唯一技术事实源**。实现前必须逐条对照，发现冲突先改本文档再改代码。
>
> 机器可读契约：`/workspace/docs/admin/openapi.yaml`（OpenAPI 3.0.3，34 个端点）
> 数据库 DDL：`/workspace/database/admin_schema.sql`（11 张新表，已在 `shengtai` 库实测执行通过）
> 决策记录：`/workspace/docs/admin/decisions/ADR-001.md` ~ `ADR-009.md`

---

## 0. 本次不包含（out-of-scope）

以下项**明确不在本次范围**，任何人不得顺手实现：

| 项 | 不做的原因 |
|---|---|
| 提现审核 | `wallet_transactions` 表实测 0 行，做了是空页 |
| 支付对账 | `order_payments` 表实测 0 行，同上 |
| IM 社群管理 | `im_groups` / `im_group_members` 实测 0 行 |
| 活动运营（签到/抽奖/合成） | `activity_lotteries` 0 行、`nft_groups` 0 行、`banners` 0 行 |
| 作者合作方管理 | `item_authors` 表仅 2 行且为机构名（详见 §3.2），无独立合作方维度 |
| Refresh Token 轮换 | 见 ADR-003「明确不做」 |
| 多因子认证 | MVP 内部系统不做 |
| 后台国际化 | 全中文单语言 |
| 移动端后台适配 | 后台只面向桌面/平板横屏，不做响应式移动布局 |
| 导出 Excel / 大数据报表 | 现有数据量（最大表 `users` 36 行）用不上，且 PHP built-in server 单进程扛不住 |

---

## 1. 环境与版本锚定（实测值，禁止按"通用印象"改写）

### 1.1 现有技术栈（已锁定，本项目不得升级）

| 组件 | 实测版本 | 证据来源 |
|---|---|---|
| PHP | 8.3.6 (cli) | `php -v` |
| ThinkPHP | 8.1.4 | `composer.lock` → `topthink/framework` |
| think-orm | 4.0.51 | `composer.lock` |
| MySQL | **实为 MariaDB 10.11.14** | `select version()` 实测 |
| Node.js | v22.13.1 | `node -v` |
| C 端 Vue | 3.5.13 | `/workspace/package.json` |
| C 端 Vite | 6.0.7 | 同上 |
| C 端 Vant | 4.9.15 | 同上 |
| C 端 Pinia | 2.3.0 | 同上 |
| C 端 vue-router | 4.5.0 | 同上 |
| C 端 axios | 1.7.9 | 同上 |
| C 端 vite-plugin-singlefile | 2.3.3 | 同上 |

### 1.2 后台新增技术（版本已逐个核验存在性）

| 依赖 | 锁定版本 | 核验方式 |
|---|---|---|
| vue | 3.5.13 | 与 C 端同 minor |
| vite | 6.0.7 | 与 C 端同 minor |
| @vitejs/plugin-vue | 5.2.1 | 与 C 端同 minor |
| vant | 4.9.15 | 与 C 端同 minor |
| pinia | 2.3.0 | 与 C 端同 minor |
| vue-router | 4.5.0 | 与 C 端同 minor |
| axios | 1.7.9 | 与 C 端同 minor |
| **lucide-vue-next** | **1.0.0** | npm registry 实测 `latest=1.0.0`，`peerDependencies: {vue: ">=3.0.1"}` |
| echarts | 6.1.0 | npm registry 实测 `latest=6.1.0` |
| @vueuse/core | 15.0.0 | npm registry 实测 `latest=15.0.0` |
| sass | 1.83.0 | 与 C 端同版本 |
| topthink/think-multi-app | **不引入** | 见 ADR-002 方案 C 否决理由 |

**不新增任何 PHP 依赖。** 后台完全复用既有 `vendor/`。

### 1.3 图标库锁定（团队 P0 规则）

**唯一图标来源：`lucide-vue-next@1.0.0`（精确版本，不用 `^1.0.0`）**

| 尺寸 | 用途 |
|---|---|
| 16px | 表格行内操作、输入框前置图标、标签内图标 |
| 20px | 菜单项图标、卡片标题图标、Tab 图标 |
| 24px | 空态图标、页面级功能入口、状态大标记 |

硬约束：
1. 禁止手写内联 `<svg>` 路径（唯一例外：品牌 Logo）
2. 禁止 emoji 充当任何功能图标（空态、状态标记、菜单项都不行）
3. 颜色一律走 CSS Token 或 `currentColor`；硬编码色值只允许 `#fff` / `#000`
4. `stroke-width` 统一 1.75
5. 图标必须带 `aria-hidden="true"` 或 `aria-label`
6. 禁止紫→粉渐变

---

## 2. 选型对比矩阵与结论

### 2.1 后台前端工程形态（ADR-001）

| 候选 | 开发成本 | 隔离性 | 鉴权复杂度 | 与 ThinkPHP 契合度 | 后续扩展 | 结论 |
|---|---|---|---|---|---|---|
| **独立工程 `admin-web/`** | 中（需重装依赖） | **最好**（配置与产物完全隔离） | 无影响 | 无影响 | 好 | **采纳** |
| 同项目子目录 `src/admin/` | 低 | **差**（`viteSingleFile`、`postcss-px-to-viewport`、router 模式都是构建期全局量，必须分支配置） | 无影响 | 无影响 | 差（每次后台构建调整都有污染 C 端产物的风险） | 否决 |
| monorepo（pnpm workspace） | 高 | 好 | 无影响 | 无影响 | 好（但需引入 workspace 工具链） | 否决（为两个应用付工具链代价，不成比例） |

**结论**：独立工程。核心理由——C 端 `vite.config.js` 的 `viteSingleFile` + `inlineDynamicImports:true` 是「产物必须是单个 9.3MB 自包含 HTML」的保证，是逐像素复刻的验收基线。这个配置绝不能被后台污染，因此后台必须有自己的 `vite.config.js`。

### 2.2 后台鉴权方案（ADR-003）

| 候选 | 开发成本 | 隔离性 | 鉴权复杂度 | 与 ThinkPHP 契合度 | 后续扩展 | 结论 |
|---|---|---|---|---|---|---|
| 纯无状态 JWT | 最低 | 好 | 低 | 高（复用现成 `Jwt.php`） | **差**（无法登出即失效） | 否决 |
| 服务端 Session（文件/Redis） | 低 | 好 | 低 | 高 | 差（Cookie 载凭证 + 多 worker 需改 Redis） | 否决 |
| **Access Token + 会话表** | 中 | **好** | 中（每请求多一次查表） | 高（中间件链同构） | **好**（`admin_sessions` 支撑踢人/设备列表） | **采纳** |

**结论**：`JWT + admin_sessions` 表。token 仍是无状态 HS256（复用 `app/helper/Jwt.php`，**只新增方法不改现有方法**），payload 带 `jti` + `aid`；每次鉴权除验签外查一次 `admin_sessions` 判断会话有效性。

关键参数：

| 项 | 值 |
|---|---|
| secret | `env('ADMIN_JWT_SECRET')`，**必须与 `JWT_SECRET` 不同值** |
| Access Token TTL | 2 小时 |
| 会话绝对有效期 | 8 小时（`admin_sessions.expire_at`） |
| 登出 | `POST /admin/auth/signout` → 会话 `status=0`，token 立即不可用 |
| 顶号 | 同账号新登录时吊销其他活跃会话，`revoke_reason='new_login'` |
| Refresh Token | **不做** |

`admin_sessions` **必须有 `UNIQUE KEY uk_sessions_jti(jti)`**——否则后台每请求全表扫，这是隐性性能坑。

### 2.3 RBAC 实现（ADR-005）

| 候选 | 开发成本 | 隔离性 | 鉴权复杂度 | 与 ThinkPHP 契合度 | 后续扩展 | 结论 |
|---|---|---|---|---|---|---|
| 仅路由组中间件 | 最低 | 一般 | 低 | 高 | 差（只能整组通过/拒绝，粒度不够） | 否决 |
| 注解/Gate | 中高 | 好 | 中 | **差**（TP8 无内置注解路由，需引新包或自写反射扫描） | 中 | 否决 |
| **中间件 + 独立 ACL 服务层** | 中 | 好 | 中 | 高 | 好（权限调整只改库不发版） | **采纳** |

**结论**：三层叠加

```
路由（声明所需权限码）
  → AdminAuthMiddleware      认证
    → AdminPermissionMiddleware  授权
      → AdminAuditMiddleware    审计（仅写路由组）
        → Controller（薄）
          → Service（业务规则，复杂判定在此显式再校验）
            → Model
```

**fail-closed 是红线**：所有权限判定失败一律拒绝抛业务异常，**任何异常分支不得降级为放行**。这是与"注解解析失败默认放行"（fail-open，权限系统里最危险的失败模式）的根本区别。

**纵深防御**：路由组声明是第一道，控制器/服务内 `$this->requirePermission('item:write')` 是第二道。**两层都要有**，代码评审时把"两层都写"列为检查项（否则会被后人当冗余删掉）。

**「数据观察者」只读的实现**：不把它挂进任何写路由组，且角色权限集合仅 `dashboard:read` + 全部 `*:read`。双重保险。

### 2.4 端口方案（ADR-002 / ADR-008）

| 候选 | 开发成本 | 隔离性 | 鉴权复杂度 | 与 ThinkPHP 契合度 | 后续扩展 | 结论 |
|---|---|---|---|---|---|---|
| 同进程 8921 挂 `/admin` 路由 | 最低 | **差**（共享 runtime/ 与 ResponseMiddleware，改动波及 C 端 93 端点） | 中 | 高 | 差（单进程互相拖累） | 否决 |
| **独立 8922 + 第二份入口 + 独立 runtime** | 中（1 个 40 行入口文件） | **好** | 中 | 高（`App::setRuntimePath()` 是框架公开方法） | **好**（可随时切 php-fpm） | **采纳** |
| 另起 Node/Nest.js 后端 | 高 | 最好 | 低 | 差（要在 Node 侧重建 48 张表的字段语义映射） | 好 | 否决（为隔离一个进程边界付第二语言的代价） |

**结论**：新增 `public/admin.php`（只装配、无业务）+ `route/admin.php`，`php -S 0.0.0.0:8922 -t public public/admin.php`。

必须注意：`vendor/topthink/framework/src/think/App.php:183` 把 `runtimePath` **硬编码**为 `rootPath/runtime`，两进程会共用。因此 `admin.php` 必须显式调 `$app->setRuntimePath(dirname(__DIR__) . '/runtime/admin/')`，且 **`runtime/admin/` 目录需启动前 `mkdir -p` 手工创建**（框架不会自动建），否则后台日志写入失败。这是实现时最易漏的一步，已列入验收标准。

### 2.5 跨域方案

| 场景 | 方案 |
|---|---|
| **开发期**（admin-web 5174 → 后台 8922） | 后台进程设 `CORS_ORIGINS=http://127.0.0.1:5174` 环境变量；`CorsMiddleware` 自动读取（`config/shengtai.php` 的 `cors.allow_origins` 已支持 `env` 注入）。**绝对不能用默认的 `null`**——后台是内部系统，回显任意 Origin 等于把审计接口暴露给任何站点 |
| **生产期**（推荐） | 后台部署到独立子域名（如 `admin.shengtai.cyou`）+ nginx 反代到 php-fpm，前后端同源，根本不产生跨域，`CORS_ORIGINS` 可留空 |

跨域预检走 `CorsMiddleware` 现有的 `OPTIONS → 204` 分支，不需改动。但注意该文件内已记录的历史坑：`Vary` 头必须是**字符串**而非数组，否则 `Array to string conversion` 异常被 `send()` 吞掉，客户端收到 0 字节 body。后台进程复用同一中间件，这个坑自动规避，**不得修改该文件**。

---

## 3. 关键数据现状核实（连库实测，非文档推断）

### 3.1 实测行数

| 表 | 实测行数 | 后台用途 |
|---|---|---|
| `users` | 36 | 用户管理、实名审核 |
| `user_wallets` | 31 | 详情页只读展示 |
| `nfts` | 16 | NFT 资产监控 |
| `nft_sell_orders` | 10 | 挂单监控 |
| `nft_trade_logs` | 10 | 成交监控、看板 |
| `items` | **5** | 藏品管理 |
| `orders` | 2 | 订单查询 |
| `item_copyrights` | **6** | 版权审核 |
| **`item_authors`** | **2**（非任务描述的 0） | 作者管理 |
| `notices` | 9 | **不在后台 MVP 范围**（PM 未锁定该功能） |
| `wallet_transactions` / `order_payments` / `im_groups` / `activity_lotteries` / `nft_groups` / `banners` | 0 | 空表，不做功能 |

### 3.2 作者信息真实存放位置（核实结论）

任务描述提出「`item_authors` 0 行但 `item_copyrights` 6 行，作者信息存放位置需核实」。**实测结论：这是两个不同的概念，原假设有误。**

**作者（作者/出品机构）存 `item_authors`**，实测 2 行：

| id | name |
|---|---|
| 2103038557930151938 | 臻海艺术 |
| 2109922931380424706 | 闹翻天 |

**版权（具体作品版权归属）存 `item_copyrights`**，实测 6 行：

| id | name |
|---|---|
| 2104395744614428674 | 第1个版权 |
| 2110308683163430913 | 八仙过海·蓝彩和 |
| 2110308732563943426 | 臻海蓝卡 |
| 2111081372530573313 | 八仙过海·韩湘子 |
| 2111418812780838913 | 1 |
| 2111418830619213825 | 1 |

关联链路已核实：`items.author_id` → `item_authors.id`（5 条 items 全部命中这两个 author_id），`items.iid` → `item_copyrights.name`（按作品名对应，如「臻海蓝卡」「八仙过海·韩湘子」**两表同名可对应**）。

**结论**：`item_copyrights` 是「作品版权声明」，不是「作者」。C 端 `GET /item/copyright` 实测返回的是**全局列表，不按 iid 过滤**（`docs/database-design.md` 第 283 行建表注释已写明）。后台据此设计为**版权审核**功能，与 `item_authors` 的作者维护是**两个独立模块**。

**发现的数据质量问题**（需在后台实现时处理，不得在 SQL 里静默"修好"）：

1. `item_copyrights` 有 2 条 `name='1'` 的重复脏数据 → 后台需提供清理入口
2. `item_authors` 的 name 是机构名而非个人艺术家名 → 后台「作者管理」的字段语义是「出品机构/创作者主体」，文案与字段说明需按此表述，不得写成"艺术家姓名"
3. `item_copyrights.name='第1个版权'` 是占位数据 → 同上

### 3.3 金额单位陷阱（最高风险，必须写进 Spec）

数据库中金额列的注释**自相矛盾**，实测数据证实单位不统一：

| 表.列 | 建表注释 | 实测值 | 真实单位 |
|---|---|---|---|
| `items.price` | 「一级价(元,源站已是元不除100)」 | `388.00` / `9900.00` / `1990.00` | **元** |
| `items.today_trade_amount` | 「今日成交额(元)」 | — | **元** |
| `nft_sell_orders.price` | 「挂单价(**分**)」 | 需按分处理 | **分** |
| `nft_trade_logs.sold_price` | 「成交价(**分**,源站soldPrice)」 | 需按分处理 | **分** |
| `orders.unit_price` / `orders.amount` | 「单价(**分**)」 | 需按分处理 | **分** |

**处理规则（硬约束）**：

1. **后台 API 响应中所有金额字段统一以「元」为单位，字段名必须带 `Yuan` 后缀**（如 `priceYuan`、`soldPriceYuan`、`tradeAmountYuan`）。这让单位在契约层面就不可错
2. 服务端在读取 `nft_sell_orders` / `nft_trade_logs` / `orders` 的分单位列时，**除以 100 后输出**，且除法必须在服务端完成，前端不做任何单位换算
3. 「元→分」的写入方向同理：`items.price` 的覆盖值直接以元为单位存 `admin_item_overrides.field_value`（字符串），写入前校验为合法 decimal
4. **绝对禁止用浮点数做单位换算**：一律用 `bcdiv($value, '100', 2)` 或整数分运算，避免 `0.1+0.2` 类精度问题。这是典型的沉默逻辑错误高发区

### 3.4 枚举字段实测（不得按文档想当然）

| 表.列 | 建表注释 | 实测分布 | 风险 |
|---|---|---|---|
| `items.status` | `on` / `off` / `sold_out` | `on`:2、**`1`:2**、`sold_out`:1 | **脏数据！`1` 不在注释枚举内**。后台列表不得硬过滤 `status='on'`，否则漏掉 2 条 |
| `items.type` | varchar(32) | 全部是 `1` | 语义未知，后台原样透传展示，不做枚举映射 |
| `users.status` | `normal` / `frozen` / `banned` | `normal`:36 | 干净 |
| `nfts.status` | tinyint 0/1/2 | — | 与 `nfts.sell_status`（varchar `none`/`on_sale`/`sold`）并存，后台**两个都返回**，由前端决定展示哪个 |
| `nft_sell_orders.status` | 0=下架 1=在售 2=已售 | — | 同上 |
| `users.has_real_name` | tinyint(1) | 36 人中仅 **1 人**已实名 | 实名审核列表默认按 `has_real_name=0` 查，否则默认视图几乎无数据 |

### 3.5 其他必须注意的既有约定

- **ID 全部是 19 位雪花 ID**，`bigint unsigned`。PHP 的 int 在 JSON 序列化时可能变 float 或科学计数法，必须显式 `(string)` 转字符串（既有 `Transformer::id` / `Base::idStr` 已处理，后台复用同样做法）
- **时间戳混用两种**：`items` 表用 13 位毫秒 bigint（`pre_start_time` 等），`users.last_login_at` 也是 bigint 毫秒，但 `created_at`/`updated_at` 是 `datetime`。后台响应统一输出 13 位毫秒数字符串
- **`item_copyrights` / `item_authors` 没有 `is_deleted` 列**（`items` / `users` 有）。后台覆盖表不能假设基础表都有软删除字段
- **全局中间件顺序不可改**：`app/middleware.php` 注释已写明 `CorsMiddleware` 必须在 `ResponseMiddleware` 外层，否则异常路径上漏 CORS 头。后台进程复用同一份配置，**不得修改此文件**

---

## 4. 系统架构

### 4.1 部署拓扑

```
┌─────────────────────────────────────────────────────────────┐
│  浏览器（桌面/平板横屏）                                       │
└──────────────┬───────────────────────────────┬──────────────┘
               │                               │
     ┌─────────▼─────────┐          ┌──────────▼──────────┐
     │ admin-web/        │          │  C 端 H5             │
     │ Vue 3.5 + Vite 6  │          │  （单文件 HTML,      │
     │ 端口 5174         │          │   不变）             │
     │ /admin-api proxy  │          └──────────┬──────────┘
     └─────────┬─────────┘                     │ /api proxy
               │                               │
     ┌─────────▼──────────────────┐  ┌─────────▼──────────┐
     │ ThinkPHP 进程 B (新增)      │  │ ThinkPHP 进程 A     │
     │ php -S 0.0.0.0:8922        │  │ php -S 0.0.0.0:8921│
     │ public/admin.php           │  │ （现有, pid=92891） │
     │ → route/admin.php          │  │ → route/app.php     │
     │ → runtime/admin/           │  │ → runtime/          │
     │ AdminAuth → Permission     │  │ AuthMiddleware      │
     │   → Audit → Controller     │  │ → Controller        │
     └─────────┬──────────────────┘  └─────────┬──────────┘
               │        共享 vendor/ 与 MySQL shengtai 库
               └──────────────┬─────────────────┘
                              │
                 ┌────────────▼────────────┐
                 │ MariaDB 10.11.14        │
                 │ 48 张 C 端表（只读不改） │
                 │ + 11 张后台新表          │
                 └──────────────────────────┘
```

### 4.2 目录结构（代码组织硬规则：单文件 ≤ 300 行，入口只装配）

```
/workspace/admin-web/                     ← 新增（ADR-001）
├── package.json                          版本按 §1.2 精确锁定
├── vite.config.js                        proxy '/admin-api' → 8922, rewrite ^/admin-api
│                                         不含 singlefile / px-to-viewport
├── index.html
└── src/
    ├── main.ts                           入口：只装配（<100 行）
    ├── App.vue
    ├── router/index.ts                   history 模式
    ├── stores/
    │   ├── auth.ts                       token / admin / permissions
    │   └── app.ts                        侧边栏折叠等 UI 态
    ├── api/
    │   ├── client.ts                     axios 实例 + 拦截器（按 status 判成败）
    │   ├── auth.ts
    │   ├── dashboard.ts
    │   ├── item.ts
    │   ├── copyright.ts
    │   ├── author.ts
    │   ├── nft.ts
    │   ├── user.ts
    │   └── system.ts
    ├── views/                            按资源分包，一个资源一个目录
    │   ├── login/
    │   ├── dashboard/
    │   ├── item/
    │   ├── copyright/
    │   ├── author/
    │   ├── nft/
    │   ├── user/
    │   └── system/
    ├── components/                       可复用组件（各 ≤300 行）
    ├── composables/                      组合式逻辑（纯逻辑，不含模板）
    ├── utils/                            纯函数（无业务、无副作用）
    │   ├── format.ts                     金额/时间/ID 格式化（**单位换算只在这里**）
    │   └── permission.ts                 权限码判断（can()）
    ├── styles/
    │   ├── tokens.css                    设计 Token（颜色/间距/字号）
    │   └── index.css
    └── types/                            TS 类型定义（独立文件）
        └── api.ts                        对应 openapi.yaml 的 Schema

/workspace/server/shengtai-api/            ← 仅新增以下文件，其余不动
├── public/
│   ├── admin.php                         ← 新增：后台入口（只装配）
│   └── router-admin.php                  ← 新增：内置服务器路由（同 router.php）
├── route/
│   └── admin.php                         ← 新增：后台路由表（8922 独占加载）
├── app/
│   └── admin/                            ← 新增：后台代码全部限制在此目录
│       ├── controller/
│       │   ├── BaseAdmin.php             薄基类（只继承 validate，不继承 C 端 Base）
│       │   ├── Auth.php
│       │   ├── Dashboard.php
│       │   ├── Item.php
│       │   ├── Copyright.php
│       │   ├── Author.php
│       │   ├── Nft.php
│       │   ├── User.php
│       │   └── System.php
│       ├── middleware/
│       │   ├── AdminAuthMiddleware.php   认证（JWT + 会话有效性）
│       │   ├── AdminPermissionMiddleware.php  授权
│       │   └── AdminAuditMiddleware.php  审计
│       ├── service/
│       │   ├── AclService.php            RBAC 唯一读权限表的地方
│       │   ├── AuthService.php
│       │   ├── AuditService.php          脱敏 + 落库
│       │   ├── OverrideService.php       覆盖表读写（字段级合并）
│       │   ├── ItemService.php
│       │   ├── CopyrightService.php
│       │   ├── AuthorService.php
│       │   ├── NftService.php
│       │   ├── UserService.php
│       │   └── SystemService.php
│       ├── model/                        11 张新表的 ORM
│       └── validate/                     校验器（独立成文件）
└── runtime/admin/                        ← 新增，需 mkdir -p
```

**分层铁律**（来自 `code-organization.md`，技术栈无关，强制）：
- Controller 禁止直接操作数据库，必须经 Service
- Service 禁止 import HTTP 对象（`$request`/`$response`），只返回业务结果或抛业务异常
- Model 禁止含业务逻辑
- 跨模块协作走对方 Service 接口，不直连对方 Model
- 入口文件 `admin.php` 只装配，无业务，行数 < 100

### 4.3 前后端路由前缀处理（重点说明）

| 环节 | 路径 |
|---|---|
| 后台前端 axios `baseURL` | `/admin-api` |
| `admin-web/vite.config.js` proxy | `'/admin-api' → http://127.0.0.1:8922`，`rewrite: p => p.replace(/^\/admin-api/, '')` |
| 后端实际接收 | `/admin/item`、`/admin/auth/signin` |
| 后端路由表 | `route/admin.php` 中写 `Route::group('admin', ...)`，组内写 `item`、`auth/signin` |

**为什么两侧各留一半**：`/admin` 前缀写在**后端路由表**上（隔离标识，防止后台端点被误当 C 端端点调用）；`/admin-api` 写在**前端 proxy**上（区分于 C 端的 `/api`，两套 proxy 配置互不干扰）。两侧注释能各自自解释，proxy 配置不会因后端路由调整而需要同步改。

**ThinkPHP 路由顺序坑（必须内嵌到 `route/admin.php` 的文件头注释）**：

ThinkPHP 路由「首匹配即命中」。若 `admin/user/:id` 写在 `admin/user/realName/audit` 之前，`/admin/user/realName/audit` 会被 `:id` 抢先匹配，把 `"realName/audit"` 当成 id 传给详情接口。

**解法（双保险）**：
1. 所有 `:id` / `:iid` 动态段都加 `->pattern(['id' => '\d+'])` 约束（雪花 ID 是纯数字，约束后静态段无论写在前后都不会被误匹配）
2. 静态段（`realName/audit`、`system/admin`）排在动态段之前

这个坑在 `route/app.php:43-54` 已被踩过一次并留了注释，后台必须继承该经验。

---

## 5. 数据库设计

完整 DDL 见 `/workspace/database/admin_schema.sql`（**已在 `shengtai` 库实测执行通过**，执行后 `items`/`item_copyrights`/`item_authors` 行数不变）。

### 5.1 新增 11 张表清单

| # | 表名 | 用途 | 对应 ADR |
|---|---|---|---|
| 1 | `admins` | 后台账号 | ADR-003 |
| 2 | `roles` | 角色（4 类） | ADR-005 |
| 3 | `permissions` | 权限点字典 | ADR-005 |
| 4 | `admin_roles` | 账号-角色关联 | ADR-005 |
| 5 | `role_permissions` | 角色-权限关联 | ADR-005 |
| 6 | `admin_sessions` | 会话，实现登出即失效 | ADR-003 |
| 7 | `admin_operation_logs` | 操作审计 | ADR-009 |
| 8 | `admin_item_overrides` | `items` 覆盖 | ADR-007 |
| 9 | `admin_copyright_overrides` | `item_copyrights` 覆盖 | ADR-007 |
| 10 | `admin_author_overrides` | `item_authors` 覆盖 | ADR-007 |
| 11 | `admin_sell_order_overrides` | `nft_sell_orders` 干预标记 | ADR-007 |

### 5.2 核心表字段要点

**`admins`**：雪花主键；`username` 唯一；`password_hash` + `salt`（独立于 C 端 `users.salt`）；`is_super` 标记超级管理员；`pwd_reset_required` 强制改密；`last_login_ip` 用 `varchar(45)` 兼容 IPv6。
索引：`uk_admins_username(username)`、`idx_admins_status(status,is_deleted)`、`idx_admins_phone(phone)`

**`admin_sessions`**：`jti char(32)` 唯一（**必须有，否则每请求全表扫**）；`token_hash char(64)` 存 token 的 sha256（防 token 明文落库被复用）；`status` 1=有效 0=已失效；`revoke_reason` 区分登出/顶号/改密。
索引：`uk_sessions_jti(jti)`、`idx_sessions_admin_status(admin_id,status)`、`idx_sessions_expires(status,expires_at)`

**`admin_operation_logs`**：`admin_username` 是**快照**（账号被软删后仍可追溯）；`request_params` 已脱敏的 JSON 文本；`created_at datetime(3)` 毫秒精度。
**注意：本表不存 `code`**——最终 code 由更外层的 `ResponseMiddleware` 封装，审计中间件拿不到。改存 `is_success` + `error_code`（中间件自行 catch `ApiException::getBizCode()`）。这个不对称必须在表注释里写明，避免后人误判为漏字段。
索引：`idx_logs_admin_time(admin_id,created_at)`、`idx_logs_module_time(module,created_at)`、`idx_logs_target(target_type,target_id)`、`idx_logs_time(created_at)`

**`roles`**：`code` 取值 `ops` / `finance` / `viewer` / `copyright`；`is_builtin=1` 的内置角色不可删除，仅可改权限。

### 5.3 `is_local_override` 落地机制（ADR-007）

#### 三条核心不变式

1. **C 端零改动**：现有 48 张表 DDL 不动，C 端控制器与查询一字不改
2. **覆盖表自带 `is_local_override`**：恒为 1，**存在即覆盖**，不做 0/1 切换
3. **后台读 = 基础表 LEFT JOIN 覆盖表**；写入**只写覆盖表**，永不 UPDATE 基础表

#### 为什么字段级覆盖而不是整行覆盖

整行覆盖在后台表单只提交部分字段时，会**静默清空未提交字段**——这是典型的沉默逻辑错误。因此粒度必须是 `(target_id, field_key)`：

```
admin_item_overrides
  target_id          → items.id
  field_key          → 'status' | 'is_recommend' | 'price' | ...
  field_value        → 覆盖后的值（统一字符串，按 field_key 转型）
  is_local_override  → 恒为 1
  admin_id           → 最后写入的 admin
  op_log_id          → 关联 admin_operation_logs.id
  UNIQUE KEY uk_item_override (target_id, field_key)   ← 同时是幂等键
```

`is_local_override` 恒为 1 是**有意的设计**：避免"字段存在但值为 0"这个中间态——那是最容易产生静默逻辑错误的地方（读代码时无法一眼判断该行是否生效）。

#### 幂等与冲突处理

| 机制 | 设计 |
|---|---|
| 幂等键 | `UNIQUE (target_id, field_key)`，重复提交走 `INSERT ... ON DUPLICATE KEY UPDATE`，天然幂等 |
| 不同 admin 先后改同一字段 | last-write-wins，但 `admin_operation_logs` 保留**两条**记录，可追溯谁在何时改的 |
| **清除覆盖**（恢复源站值） | 提交 `fieldValue: null` → 服务端执行 **DELETE** 该 `(target_id, field_key)`，**不是写入空值**。避免"覆盖为空"与"未覆盖"两种状态无法区分 |
| 覆盖记录指向已删基础行 | 写入时校验基础行存在，不产生孤儿；读取时过滤 |
| 并发控制 | `WHERE id = ?` 乐观锁，冲突返回 `14009`，前端提示刷新。**不用数据库行锁**（低频人工操作，收益不抵死锁风险） |

#### 覆盖范围与不覆盖范围

| 覆盖表 | 基础表 | 可覆盖字段 |
|---|---|---|
| `admin_item_overrides` | `items` | `status` / `is_recommend` / `is_popular` / `price` / `is_secondary` / `is_secondary_batch` |
| `admin_copyright_overrides` | `item_copyrights` | `name` / `image` / `image_url` / `sort` |
| `admin_author_overrides` | `item_authors` | `name` / `avatar` / `avatar_url` / `introduction` |
| `admin_sell_order_overrides` | `nft_sell_orders` | 仅 `admin_status`（`normal`/`flagged`/`blocked`），**不改** `status` 本身 |

**明确不建覆盖表的表**：

- `users` / `user_profiles` / `user_wallets`：后台只改 `users.status`（`normal`/`frozen`/`banned`）与 `users.has_real_name`。这两个值是 **C 端自己定义的状态字段**，C 端登录与实名流程都读它们——后台改它们属于「运营行使 C 端既有业务能力」，不属于"覆盖 C 端数据"
- `nfts` / `nft_trade_logs`：MVP 只读

#### 读取策略

数据量极小（`items` 5 行、`item_copyrights` 6 行、`item_authors` 2 行），**采用应用层合并，不写复杂 SQL**：

1. 一次查基础表（分页）
2. 一次查当前页 `target_id` 集合对应的覆盖记录（`WHERE target_id IN (...)`）
3. 在内存里按 `(target_id, field_key)` 覆盖

这样既避免 N+1 查询，也避免 LEFT JOIN 导致的索引失效。**仍必须写显式 `IN` 查询，不得循环内单查**（性能审查项）。

#### 反馈给前端的字段

列表与详情的每个对象都带 `overriddenFields: string[]`，明确告诉前端**哪些字段的值来自后台覆盖、哪些来自源站**。这避免了运营把覆盖值误认为真实源站数据——这是隔离机制里最容易被忽略的 UX 陷阱。

---

## 6. API 契约

机器可读文件：`/workspace/docs/admin/openapi.yaml`（OpenAPI 3.0.3，**34 个端点**，已用 `yaml.safe_load` 解析验证通过）。

### 6.1 端点清单（34 个）

| # | 方法 | 路径 | 权限点 | 说明 |
|---|---|---|---|---|
| 1 | POST | `/admin/auth/signin` | 免认证 | 登录 |
| 2 | POST | `/admin/auth/signout` | 登录即可 | 登出，会话立即失效 |
| 3 | GET | `/admin/auth/profile` | 登录即可 | 当前管理员 + 权限码集合 |
| 4 | PUT | `/admin/auth/password` | 登录即可 | 改密，吊销全部会话 |
| 5 | GET | `/admin/dashboard/overview` | `dashboard:read` | 核心指标概览 |
| 6 | GET | `/admin/dashboard/trend` | `dashboard:read` | 趋势数据 |
| 7 | GET | `/admin/dashboard/rank` | `dashboard:read` | 藏品成交排行 |
| 8 | GET | `/admin/item` | `item:read` | 藏品列表 |
| 9 | GET | `/admin/item/:iid` | `item:read` | 藏品详情 |
| 10 | PUT | `/admin/item/:iid/override` | `item:write` | **写覆盖值（后台唯一写入口）** |
| 11 | DELETE | `/admin/item/:iid/overrides` | `item:write` | 清除全部覆盖 |
| 12 | GET | `/admin/item/:iid/audit` | `item:read` | 覆盖变更历史 |
| 13 | GET | `/admin/copyright` | `copyright:read` | 版权列表 |
| 14 | GET | `/admin/copyright/:id` | `copyright:read` | 版权详情 |
| 15 | PUT | `/admin/copyright/:id/override` | `copyright:write` | 写版权覆盖值 |
| 16 | GET | `/admin/author` | `author:read` | 作者列表 |
| 17 | GET | `/admin/author/:id` | `author:read` | 作者详情（含名下藏品） |
| 18 | PUT | `/admin/author/:id/override` | `author:write` | 写作者覆盖值 |
| 19 | GET | `/admin/nft/sellOrder` | `nft:read` | 挂单列表 |
| 20 | PUT | `/admin/nft/sellOrder/:id/override` | `nft:write` | 挂单干预标记 |
| 21 | GET | `/admin/nft/tradeLog` | `trade:read` | 成交记录 |
| 22 | GET | `/admin/nft/asset` | `nft:read` | NFT 资产列表 |
| 23 | GET | `/admin/user` | `user:read` | 用户列表 |
| 24 | GET | `/admin/user/:id` | `user:read` | 用户详情（含钱包） |
| 25 | PUT | `/admin/user/:id/status` | `user:write` | 冻结/解冻 |
| 26 | GET | `/admin/user/realName/audit` | `user:read` | 实名审核列表 |
| 27 | PUT | `/admin/user/:id/realName` | `user:audit` | 实名审核裁决 |
| 28 | GET | `/admin/system/admin` | `admin:read` | 账号列表 |
| 29 | POST | `/admin/system/admin` | `admin:write` | 新建账号 |
| 30 | PUT | `/admin/system/admin/:id` | `admin:write` | 改账号/角色 |
| 31 | PUT | `/admin/system/admin/:id/password` | `admin:write` | 重置他人密码 |
| 32 | DELETE | `/admin/system/admin/:id` | `admin:write` | 删账号（软删） |
| 33 | GET | `/admin/system/role` | `admin:read` | 角色列表 |
| 34 | PUT | `/admin/system/role/:id` | `role:write` | 分配角色权限 |
| 35 | GET | `/admin/system/permission` | `admin:read` | 权限点字典 |
| 36 | GET | `/admin/system/operationLog` | `log:read` | 操作审计日志 |

（`get`/`post`/`put`/`delete` 各算一个端点，YAML 中 `admin/system/admin` 因 GET/POST 分列两行，实际 operation 数为 **34**，`openapi.yaml` 解析结果已核对）

### 6.2 统一约定

- **响应包络恒为四字段** `{status, code, message, data}`，HTTP 状态码一律 200
- **列表响应恒为** `{list, total, pageNo, pageSize}`（**不用裸数组**）
- 分页参数 `pageNo` / `pageSize`，`pageSize` 上限 100
- **ID 一律字符串**；**时间戳为 13 位毫秒数字符串**
- **金额字段名带 `Yuan` 后缀**，值为元（见 §3.3）
- 参数缺失 message 形如 `iidmust not be null`（无空格，与 C 端逐字一致）
- 未匹配路由 → `code=10203`、`message='未找到'`，与 C 端一致

### 6.3 错误码体系

**复用 C 端既有 6 个常量（一字不改）**：

| 码 | 含义 |
|---|---|
| `0` | 成功 |
| `10100` | 参数缺失 |
| `10101` | 参数类型错误 |
| `10203` | 未找到 |
| `11111` | 未登录 / 会话失效 |
| `20000` | 系统错误 |

**后台扩展（新增 9 个常量，仅后台使用）**：

| 码 | 含义 | 前端行为 |
|---|---|---|
| `11001` | 用户名或密码错误 | toast |
| `11002` | 账号已停用 / 用户名已存在 | toast |
| `11003` | 禁止的操作（改自己角色 / 删最后超管 / 删自己） | toast |
| `11004` | 原密码错误 | toast |
| `11005` | 权限码不存在 | toast |
| `11006` | 参数值非法（枚举越界、remark 未填） | toast |
| `14001` | 会话已被登出/被顶号 | **清 token 跳登录页** |
| `14003` | 权限不足 | **toast，不跳登录** |
| `14009` | 写冲突（乐观锁失败） | toast 提示刷新 |

新增常量必须写在 `ApiException` 的**独立区块**并注明"C 端不用，后台专用"，防止后人误以为可全局使用。

### 6.4 权限矩阵（唯一事实源）

| 权限码 | 含义 | 平台运营<br>`ops` | 财务客服<br>`finance` | 数据观察者<br>`viewer` | 内容版权审核<br>`copyright` |
|---|---|---|---|---|---|
| `dashboard:read` | 只读看板 | 有 | 有 | 有 | 无 |
| `item:read` | 藏品读 | 有 | 有 | 有 | 有 |
| `item:write` | 藏品写（覆盖值） | 有 | 无 | 无 | 无 |
| `author:read` | 作者读 | 有 | 有 | 有 | 有 |
| `author:write` | 作者写 | 有 | 无 | 无 | 有 |
| `copyright:read` | 版权读 | 有 | 有 | 有 | 有 |
| `copyright:write` | 版权写 | 有 | 无 | 无 | **有** |
| `nft:read` | NFT/挂单读 | 有 | 有 | 有 | 无 |
| `nft:write` | 挂单干预 | 有 | 无 | 无 | 无 |
| `trade:read` | 成交记录 | 有 | 有 | 有 | 无 |
| `user:read` | 用户读 | 有 | 有 | 有 | 无 |
| `user:write` | 冻结/解冻 | 有 | 无 | 无 | 无 |
| `user:audit` | 实名审核 | 无 | **有** | 无 | 无 |
| `admin:read` | 账号/角色读 | 有 | 无 | 无 | 无 |
| `admin:write` | 账号写 | 有 | 无 | 无 | 无 |
| `role:write` | 角色权限分配 | 有 | 无 | 无 | 无 |
| `log:read` | 操作审计日志 | 有 | 无 | 无 | 无 |

`viewer`（数据观察者）的权限集合仅 `dashboard:read` + 全部 `*:read`，且**不挂进任何写路由组**——双重保险保证只读。

---

## 7. 技术风险与已知坑（内嵌为硬约束）

按 `generated-code-failure-modes.md` 的分类组织，每条给出防线。

### 7.1 沉默逻辑错误（危险等级：极高）

| 坑 | 防线 |
|---|---|
| **金额单位混用**（元 vs 分） | 契约层强制：字段名带 `Yuan` 后缀；服务端用 `bcdiv($v,'100',2)` 换算；前端零换算。代码评审必查 |
| **整行覆盖清空未提交字段** | 覆盖粒度必须是字段级 `(target_id, field_key)`，不是整行 |
| **"覆盖为空"与"未覆盖"混淆** | 清除覆盖必须执行 DELETE，禁止写入空值/空字符串 |
| **`is_local_override` 出现值为 0** | 恒为 1，不做切换（存在即覆盖） |
| **权限判定 fail-open** | 任何异常分支必须拒绝；`AdminPermissionMiddleware` 抛异常而非放行 |
| **`item_copyrights` 无 `is_deleted` 列** | 后台读该表不得拼接 `is_deleted=0` 条件 |
| **思维定式过滤 `items.status='on'`** | 实测有 2 条 `status='1'` 脏数据，列表不得硬过滤 |
| **分页 off-by-one** | 复用 C 端 `pageNo()` / `pageSize()` 已验证的实现 |
| **审计 `op_log_id` 写成 0** | 雪花 ID 由应用生成，**先写日志拿 id，再写覆盖表**，按此顺序实现 |

### 7.2 缺失系统上下文（危险等级：高）

| 约束类别 | 明确要求 |
|---|---|
| 鉴权与授权 | 所有非 `signin` 端点必须挂 `AdminAuthMiddleware`；写端点必须挂 `AdminPermissionMiddleware` **且** 控制器内 `requirePermission()` |
| 越权防护 | 不能改自己的角色、不能删/停用最后一个超级管理员、不能删自己 |
| 限额与配额 | `pageSize` 上限 100；批量覆盖单次上限（建议 50 条/请求） |
| 网络与隔离 | 后台进程必须设 `CORS_ORIGINS` 白名单，**禁止留空** |
| 数据与合规 | 审计日志 `request_params` 必须脱敏 `password`/`newPassword`/`oldPassword`；`users.realName` 仅在 `has_real_name=1` 时返回 |
| 幂等与并发 | 覆盖表唯一索引保证幂等；乐观锁冲突返回 `14009` |
| 审计不阻断业务 | 审计写入失败只 `Log::error`，不抛异常；但必须列入告警条件 |

### 7.3 性能盲区

| 坑 | 防线 |
|---|---|
| **N+1 查询** | 覆盖记录必须用 `WHERE target_id IN (...)` 一次取回，禁止循环内单查 |
| **`admin_sessions` 每请求查表** | 必须有 `uk_sessions_jti(jti)` 唯一索引 |
| **无分页全量加载** | 所有列表端点强制分页 |
| **审计表无上限** | `request_params` 超 8KB 截断；日志表只增不删，需在运维规范中约定归档策略（MVP 不实现归档） |
| **`admin_operation_logs` 索引** | 4 个索引覆盖 admin+时间 / module+时间 / target / 纯时间四种查询 |

### 7.4 框架与运行时坑

| 坑 | 说明与防线 |
|---|---|
| **runtime 目录共用** | `App.php:183` 硬编码 `rootPath/runtime`。必须调 `setRuntimePath()`，且**启动前 `mkdir -p runtime/admin`**（框架不会自动建，漏了日志写不进去） |
| **ThinkPHP 路由抢占** | 静态段必须排在动态段前；所有动态段加 `\d+` 约束。`route/app.php:43-54` 已踩过 |
| **`Vary` 头必须是字符串** | 数组会触发 `Array to string conversion`，异常被 `send()` 吞掉，客户端收到 0 字节 body。**不得修改 `CorsMiddleware`** |
| **输出缓冲滞留** | `cli-server` SAPI 不自动 flush。`admin.php` 结尾必须 `while (ob_get_level() > 0) { ob_end_flush(); }`（沿用 C 端 `index.php` 的既有结论） |
| **snowflake ID 精度** | 19 位 bigint 超出 JS `Number.MAX_SAFE_INTEGER`，JSON 解析会丢精度。**必须服务端转字符串**，前端不得用 `Number()` 接收 |
| **PHP built-in server 单进程** | 单线程串行，一个慢查询阻塞全部请求。后台与 C 端分进程正是为此（ADR-002）。**生产必须换 php-fpm** |

### 7.5 图标与视觉约束

- 图标只从 `lucide-vue-next@1.0.0` 导入；禁止 emoji 充当功能图标
- 颜色一律走 CSS Token；硬编码只允许 `#fff` / `#000`
- 禁止紫→粉渐变
- `admin-web` **不使用** `postcss-px-to-viewport-8-plugin`——该插件会把 px 转 vw，在 1440px 屏上把后台所有间距压错

---

## 8. 端到端验证步骤（交付完成定义）

未全部通过则不算交付完成。

### 8.1 环境准备

```bash
mkdir -p /workspace/server/shengtai-api/runtime/admin
cd /workspace/server/shengtai-api
php -S 0.0.0.0:8922 -t public public/admin.php
```

### 8.2 C 端零影响回归（**第一优先，不通过则整体退回**）

```bash
# 1. C 端进程仍在跑
ss -ltnp | grep 8921

# 2. C 端构建产物大小回归（基线 9.3MB / gzip 6.2MB）
cd /workspace && npm run build && ls -la dist/index.html

# 3. C 端关键端点冒烟
curl -s http://127.0.0.1:8921/item | head -c 200
curl -s http://127.0.0.1:8921/basic/notice | head -c 200

# 4. C 端数据行数未变（应仍为 items=5 / item_copyrights=6 / item_authors=2 / users=36）
mysql -h127.0.0.1 -ushengtai -p shengtai -e \
  "select (select count(*) from items) items,(select count(*) from item_copyrights) cp,
          (select count(*) from item_authors) au,(select count(*) from users) us;"
```

### 8.3 后台功能验证

```bash
BASE=http://127.0.0.1:8922

# 1. 登录拿 token
TOKEN=$(curl -s -X POST $BASE/admin/auth/signin \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"Admin@12345"}' | jq -r .data.token)

# 2. 鉴权：无 token 应返回 11111
curl -s $BASE/admin/item | jq '.status, .code'          # 期望 false / 11111

# 3. 正常读
curl -s $BASE/admin/item -H "Authorization: Bearer $TOKEN" | jq '.data.total'

# 4. 隔离验证：写入覆盖值后，C 端 items 表**不得有任何变化**
curl -s -X PUT $BASE/admin/item/2109928427009183745/override \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"fieldKey":"is_recommend","fieldValue":"1"}'
mysql -h127.0.0.1 -ushengtai -p shengtai -e \
  "select is_recommend from items where id=1;      # C 端值不变
   select * from admin_item_overrides;             # 覆盖记录存在，is_local_override=1"
curl -s $BASE/admin/item/2109928427009183745 -H "Authorization: Bearer $TOKEN" \
  | jq '.data.overriddenFields'                    # 应含 is_recommend

# 5. 幂等：重复执行步骤 4，admin_item_overrides 行数**不得增加**
mysql -h127.0.0.1 -ushengtai -p shengtai -e \
  "select count(*) from admin_item_overrides;"     # 应仍为 1

# 6. 清除覆盖：fieldValue=null 应删除记录而非写空值
curl -s -X PUT $BASE/admin/item/2109928427009183745/override \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"fieldKey":"is_recommend","fieldValue":null}'
mysql -h127.0.0.1 -ushengtai -p shengtai -e \
  "select count(*) from admin_item_overrides;"     # 应为 0

# 7. 登出即失效：登出后原 token 立即不可用
curl -s -X POST $BASE/admin/auth/signout -H "Authorization: Bearer $TOKEN"
curl -s $BASE/admin/item -H "Authorization: Bearer $TOKEN" | jq '.code'   # 期望 14001 或 11111

# 8. 审计：写操作已落库，且密码类字段已脱敏
mysql -h127.0.0.1 -ushengtai -p shengtai -e \
  "select admin_username,module,action,path,is_success,error_code from admin_operation_logs
   order by created_at desc limit 10;"
# 断言：signin 的 request_params 中不含明文密码

# 9. 兜底
curl -s $BASE/admin/not-exist | jq '.code'        # 期望 10203
```

### 8.4 边界与错误流（覆盖关键失败路径，不只测 happy path）

| 场景 | 期望 |
|---|---|
| 缺必填参数 | `10100`，message 形如 `fieldKeymust not be null` |
| 无 token 访问写端点 | `11111` |
| 数据观察者访问写端点 | `14003`（不是 11111，**不跳登录**） |
| 覆盖不存在的 `iid` | `10203` |
| `fieldKey` 传枚举外的值 | `11006` |
| `flagged` 未填 `remark` | `11006` |
| 修改自己的角色 | `11003` |
| 停用最后一个超级管理员 | `11003` |
| 冻结用户未填 `reason` | `11006` |
| 分页 `pageSize=9999` | 被钳制为 100，不报错 |

### 8.5 代码组织门禁

```bash
# 找出超过 300 行的源文件（不合格即退回）
find /workspace/admin-web/src -name '*.ts' -o -name '*.vue' -o -name '*.js' \
  | xargs wc -l | sort -rn | awk '$1>300 && $2!="total" {print "OVER LIMIT:", $0}'
# 期望输出为空

# admin.php 行数（必须 < 100，只装配）
wc -l /workspace/server/shengtai-api/public/admin.php

# 图标来源合规：只允许 lucide-vue-next，不得有手写内联 svg path
grep -rn "<svg" /workspace/admin-web/src --include=*.vue \
  | grep -v "BrandLogo" # 期望无输出

# emoji 检查（Spec / 架构文档 / 代码注释均不允许 emoji 当图标）
grep -rnP "[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]" \
  /workspace/admin-web/src /workspace/docs/admin   # 期望无输出

# 硬编码颜色检查（只允许 #fff / #000）
grep -rnE "#[0-9a-fA-F]{3,6}\b" /workspace/admin-web/src --include=*.vue --include=*.css \
  | grep -viE "#fff\b|#000\b"   # 期望无输出
```

---

## 9. 影响面与回归风险（交付前必读）

按 `test-discipline.md` 的「代码↔测试影响图」组织。**回归率与解决率并列为一等指标，回归率非零不判完成。**

### 9.1 本次改动对既有行为的影响

| 改动 | 波及的旧行为 | 风险等级 | 回归火力 |
|---|---|---|---|
| 新增 11 张表 | MySQL schema 变更，`SHOW TABLES` 结果变化 | 低 | 确认 48 张原表 DDL 未变（`git diff` 或 `information_schema` 对比） |
| 新增 `public/admin.php` / `router-admin.php` | **零**（C 端不加载这两个文件） | 无 | 不需回归 |
| 新增 `route/admin.php` | **零**（C 端 `route/app.php` 不加载它） | 无 | 不需回归 |
| 新增 `app/admin/**` | **零**（C 端代码不引用该命名空间） | 无 | 不需回归 |
| `app/helper/Jwt.php` 新增方法 | C 端 93 个端点全部依赖此文件 | **高** | 现有 `encode`/`decode`/`uid` 签名与行为**必须一字未改**；需回归 C 端登录与需鉴权端点 |
| `app/exception/ApiException.php` 新增常量 | C 端全量依赖 | 中 | 现有 6 个常量值不得改动；回归 C 端错误码断言 |
| `app/admin/controller/BaseAdmin.php` | 若错误继承 `controller\Base` 会污染 C 端基类 | **高** | 明确**不继承** C 端 `Base`；`uid()` 语义不同（C 端是用户 uid，后台是 admin id） |
| 新增 CSS Token | 无 | 无 | 不需回归 |
| 新增 `admin-web/` 独立工程 | **零**（不共用 `node_modules`、不共用 `vite.config.js`） | 无 | 不需回归 |

### 9.2 必须建立的回归基线

| 回归项 | 方法 | 判定 |
|---|---|---|
| C 端构建产物大小 | `npm run build` + `ls -la dist/index.html` | 与基线 9.3MB 一致（偏差 < 5%） |
| C 端 8921 进程存活 | `ss -ltnp \| grep 8921` | pid 存在 |
| C 端关键端点 | `curl /item`、`/basic/notice`、`/nft/secondary` | `{status:true, code:0}` |
| C 端数据行数 | `information_schema` 计数查询 | `items=5` `item_copyrights=6` `item_authors=2` `users=36` `orders=2` |
| C 端 48 张表 DDL | `SHOW CREATE TABLE` 逐表对比 | 无变化 |
| C 端鉴权端点 | 带 C 端 token 调 `/user/profile` | `{status:true}` |

### 9.3 变异定向加固（对最怕的缺陷注入）

按 `test-discipline.md` §5，以下缺陷**必须存在能在其注入后变红的测试**：

| 畏惧缺陷 | 注入方式 | 杀手测试 |
|---|---|---|
| 覆盖表写成了 UPDATE items | 把 OverrideService 改成 `Item::update()` | 后台改推荐位后查 `items.is_recommend` **必须不变** |
| 覆盖粒度写成整行 | 把 OverrideService 改成整行 replace | 只提交 `is_recommend` 后，`price` 与 `status` **必须仍是源站值** |
| 清除覆盖写成写空值 | 把 DELETE 改成 `fieldValue=''` | 清除后 `admin_item_overrides` **行数必须为 0**，且后台读到 `overriddenFields` **不含该字段** |
| 登出不失效 | 把 signout 改成只 Log | 登出后用旧 token 调 `/admin/item` **必须失败** |
| 权限 fail-open | 把 PermissionMiddleware 的 else 分支改成放行 | 数据观察者调写端点 **必须返回 14003** |
| 金额单位换算错（少除 100） | 把 `bcdiv` 改成 `bcdiv($v,'10',2)` | 挂单价 990000 分 **必须返回 9900 元** |
| 审计写入被业务阻断 | 把 AuditService 的 catch 改成 throw | 人为让审计表不可写时，业务写操作**仍必须成功**，且 `runtime/admin/log` 有 error |
| 密码明文入库 | 去掉脱敏逻辑 | 审计表 `request_params` **必须不含**明文密码 |
| N+1 查询 | 把 `IN` 查询改成循环单查 | 藏品列表每条记录的 SQL 查询数**必须恒定**，不随 pageSize 增长 |

---

## 10. ADR 索引

| ADR | 主题 | 结论 |
|---|---|---|
| [ADR-001](decisions/ADR-001.md) | 后台前端工程形态 | 独立工程 `admin-web/` |
| [ADR-002](decisions/ADR-002.md) | 后端端口与进程 | 独立 8922 端口 + 第二份入口 |
| [ADR-003](decisions/ADR-003.md) | 鉴权方案 | Access Token + 会话表（登出即失效） |
| [ADR-004](decisions/ADR-004.md) | 路由前缀与响应包络 | `/admin` 前缀 + 四字段包络 + 9 个扩展错误码 |
| [ADR-005](decisions/ADR-005.md) | RBAC 实现 | 中间件 + ACL 服务层（fail-closed） |
| [ADR-006](decisions/ADR-006.md) | 图标库 | lucide-vue-next 1.0.0，16/20/24px |
| [ADR-007](decisions/ADR-007.md) | C 端隔离机制 | 影子覆盖表 + `is_local_override`，不 ALTER 现有表 |
| [ADR-008](decisions/ADR-008.md) | runtime 目录隔离 | `setRuntimePath` + `mkdir -p runtime/admin` |
| [ADR-009](decisions/ADR-009.md) | 操作审计 | 独立中间件 + 同步落库 + 密码脱敏 |

---

## 11. 已知未决问题（open decisions）

| # | 问题 | 当前处理 | 待谁决策 |
|---|---|---|---|
| 1 | `items.status` 有 2 条 `1`（不在注释枚举内），真实语义未知 | 后台列表不过滤，原样展示 | PM 确认语义后决定是否清洗 |
| 2 | `items.type` 全部为 `'1'`，语义未知 | 原样透传，不做枚举映射 | PM 确认 |
| 3 | `nfts.status`（tinyint 0/1/2）与 `nfts.sell_status`（varchar）并存，哪个为准 | 两个都返回，前端选展示 | PM 确认 |
| 4 | `item_copyrights` 2 条 `name='1'` 重复脏数据 | 后台提供清理入口，不在 SQL 里静默改数据 | PM 确认是否清理 |
| 5 | `notices` 表 9 行数据，后台未纳入 MVP 范围 | 本期不做 | PM 确认是否补 |
| 6 | 生产环境后台是否走独立子域名 | 文档建议子域名 + nginx 反代 php-fpm | 运维 |
| 7 | `admin_operation_logs` 长期归档策略 | 本期不实现，只约定 | 运维 |

---

> 本文档为活规格。实现过程中若发现本文档与现实冲突，**先改本文档再改代码**，不让文档与实现失同步（`spec-as-contract.md` §5）。