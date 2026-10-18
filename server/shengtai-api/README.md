# shengtai-api

ThinkPHP 8.1.4 + PHP 8.3.6，零第三方依赖（自实现 JWT / RSA / 雪花 ID）。
路径、方法、响应包络、字段命名严格对齐源站 `api.shengtai.cyou`。

## 一、快速启动

```bash
cd server/shengtai-api

# 1. 确认 .env（DB_NAME/DB_USER/DB_PASS、JWT_SECRET、RSA 密钥对）
# 2. 建库建表
mysql -ushengtai -p < database/create_schema.sql
mysql -ushengtai -p shengtai < database/seed_source_data.sql
mysql -ushengtai -p shengtai < database/seed_market_data.sql

# 3. 起服务
php -S 127.0.0.1:8900 -t public public/router.php
```

验证：

```bash
bash scripts/verify_all.sh http://127.0.0.1:8900
```

## 二、目录结构

```
app/
  controller/    12 个控制器（Item/Passport/Order/Nft/User/Wallet/Im/Activity/Ai/Basic + Base/Index）
  model/         49 个模型（48 表 + BaseModel）
  service/       Transformer 转换层、CaptchaService、SmsService、MiddlewareService
  helper/        Jwt、Rsa、Snowflake
  middleware/    ResponseMiddleware（统一包络）、AuthMiddleware（Bearer token 鉴权）
  exception/     ApiException（源站式错误码）
config/
  shengtai.php   JWT / RSA / media_base / 订单支付窗口
  route.php      route_complete_match=true（关键）
route/app.php    96 条路由
scripts/verify_all.sh
```

## 三、必须知道的三条硬约束

### 1. 所有响应 HTTP 200，前端只看 `status`

```
{ "status": bool, "code": int, "message": string|null, "data": mixed }
```

错误码：`0` 成功 / `10100` 参数缺失 / `10101` 参数类型错 / `10203` 未找到 / `11111` 请先登录 / `20000` 系统错误。

参数缺失的 message 是源站格式，**无空格**：`iidmust not be null`。

### 2. 列表 / 详情不对称（源站固定写法，共 2 例）

| 端点 | 列表 | 详情 |
|------|------|------|
| `/order` | `items` 恒为 `[]` | `/order/{id}` 才填明细 |
| `/item/merge` | `merges`/`needs` 恒为 `null` | `/item/merge/{id}` 才填子表 |

**已实测验证通过**，改代码时不要"顺手修正"。

### 3. 路由 `:iid` 已约束 `\d+`

```php
Route::get('nft/secondary/:iid', 'Nft/secondaryDetail')->pattern(['iid' => '\d+']);
```

雪花 ID 是纯数字。加上约束后 `sell`/`sold`/`follow`/`apply`/`trust` 这些静态段
无论写在 `:iid` 前后都不会被抢匹配。同时 `config/route.php` 必须：

```php
'route_complete_match' => true,   // false 会导致前缀匹配，子端点被父规则吃掉
'request_cache_key'     => false,  // API 不可缓存
'url_html_suffix'      => '',     // 纯 API 不需要 html 后缀
```

## 四、字段与单位约定

| 约定 | 说明 |
|------|------|
| 主键 | 19 位雪花 ID，**库里 bigint，接口一律转字符串** |
| 业务时间 | bigint **毫秒**时间戳，**字符串**形式（`"1791959018537"`）。库里的时间列也是毫秒，不要按秒存 |
| 金额 | **单位混用是源站行为**：`price` 是元，`secondaryHighPrice`/`soldPrice`/`unitPrice` 是分 |
| users.status | 字符串枚举 `normal`/`forbidden`/`frozen`（不是整型） |
| 布尔 | 接口层 `bool`，库层 `tinyint(1)` |
| 空值 | 源站区分 `null` 与 `""`，**不可互相强转**（例：`items.content` 韩湘子=`""`、蓝采和=`null`） |
| 图片 | 一律**平铺双字段** `image`（相对路径）+ `imageUrl`（绝对 URL）。嵌套形态 `{image:{image,imageUrl}}` 会让前端 `:src` 拿到对象变破图 |
| `*_url` 冗余列 | 是 `image`/`avatar`/`cover` 的派生值。**必须与源列同步维护**，`Transformer` 侧对 item 做了空值兜底派生 |

## 五、鉴权

```
Authorization: Bearer {token}
```

token 用 `config('shengtai.jwt.secret')` 签发 HS256，7 天有效。
未带/无效 token → `code:11111`。

注意配置在 `shengtai` 命名空间下，写 `config('jwt.secret')` 拿不到值。

登录链路（本地无短信网关，debug 模式验证码原样返回）：

```bash
CODE=$(curl -s -X POST http://127.0.0.1:8900/passport/captcha/sms \
  -H 'Content-Type: application/json' -d '{"phone":"13800001111"}' \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["code"];')

curl -s -X POST http://127.0.0.1:8900/passport/signin/quick \
  -H 'Content-Type: application/json' -d "{\"phone\":\"13800001111\",\"code\":\"$CODE\"}"
```

生产环境必须去掉响应里的 `code`，接真实短信网关。

## 六、源站未上线端点（保持一致，不要实现）

| 端点 | 返回 |
|------|------|
| `/activity/recast/type` | `10203` |
| `/activity/product_merge` | `10203` |
| `/wallet/yeepay` | `20000` 系统错误（易宝通道未上线） |

另外源站这几个路径线上不存在：`/user/wallet`、`/wallet/transaction`、
`/wallet/log`、`/common/rule`、`/item/{iid}/author`、`/app/version/check`。

## 七、踩坑记录

| 坑 | 现象 | 解法 |
|----|------|------|
| `think-trace` 的 `TraceDebug` | HTTP 200 + 0 字节空响应 | 从 `vendor/services.php` 移除 `think\trace\Service` |
| 控制器与模型同名 | `Cannot declare class ... already in use` | 模型侧起别名 `use app\model\Item as ItemModel;` |
| 双重 `toArray()` | `Call to a member function toArray() on array` | `select()->toArray()` 已返回数组，`array_map` 里不要再调 |
| `config('app.debug')` | 恒为 NULL（真实键是 `app_debug`） | 用 `$this->app->isDebug()` |
| 输出缓冲滞留 | 响应偶发空 | `public/index.php` 末尾 `while (ob_get_level() > 0) ob_end_flush();` |
| JWT 密钥取不到 | 登录后所有接口 11111 | 配置在 `shengtai` 命名空间，用 `config('shengtai.jwt.secret')` |
| 静态路由被动态段吃掉 | `/nft/secondary/sell` 返回列表 | `:iid` 加 `pattern(['iid' => '\d+'])` |
| `Response::send()` 吞异常 | 带 `Origin` 头时响应恒 0 字节 | 头值**不能是数组**（`Array to string conversion` 被 send() 内部 catch 吞掉，`sendData()` 从不执行）|
| 中间件从未注册 | CORS 配置写了但完全无效 | `Service::register()` 阶段顺序不可控，直接在 `app/middleware.php` 显式声明 |
| `.env` 多行 PEM | `openssl_pkey_get_private` 返回 false | `parse_ini_file` 不还原字面 `\n`；`Rsa::normalizePem()` 统一处理字面转义/裸 base64/头尾补全 |
| `pageList()` 一律返回 `{list,total}` | 前端 `.map is not a function` 白屏 | 源站绝大多数端点返回**裸数组**，默认改裸数组，需 `{list,total}` 的显式传第三参 `true` |
| 列表/详情字段集不一致 | `Cannot read properties of null (reading 'name')` | 抽 `composeNotice()` 统一组装两端 |
| `*_url` 冗余列漏填 | 首页热门封面 `src=""` 破图 | 同步修数据 + `Transformer::item()` 空值兜底派生 |
| 种子数据列错位 | 蓝采和 `contract` 被写成图片路径 | `INSERT` 用显式列清单，插入前逐列核对；`scripts/sync_items_from_source.php` 可预演差异 |
| `/user/invite` 自定义返回结构 | 邀请页 `Cannot read properties of undefined (reading 'length')` | 源站读 `data.list` / `data.total`，必须走 `pageList($list, $total, true)`，且要按 `pageNo` 分页 |
| 接口失败就跳首页 | 巡检报「路由落点错误」，源站却不动 | 源站 `getOrder` 只有 `status ? 赋值 : 无操作`，删掉多余的 `else if (code !== 11111) router.replace(...)` |

## 八、当前验证结果

| 验证项 | 结果 |
|--------|------|
| 后端端点 | **31/31**（18 公开 + 4 未登录拦截 + 9 需登录） |
| 分页结构 vs 源站 | **8/8 全部一致** |
| 藏品详情逐字段 | **3 条 × 39 字段 = 117 字段零差异** |
| 作者列表 | **完全一致**（含 id 降序与字段集） |
| 前端端到端 | **38/38**（含 4 项破图检测 + 6 项 3D 演示页断言） |
| 全站页面巡检 | **58/58 全绿**（`scripts/sweep_all_pages.py`，带登录态逐页加载） |
| 破图 | **0**（逐元素查 `naturalWidth`，非「全页无报错」式判断） |
| 源站隔离 | 6 个页面 **0 次**源站请求 |

验证脚本：

| 脚本 | 用途 |
|------|------|
| `scripts/verify_all.sh` | 31 端点全量验证（含登录链路） |
| `scripts/check_pagination_shape.sh` | 本地 vs 源站分页结构自动比对 |
| `scripts/probe_source_shapes.sh` | 逐个探测源站公开端点的 data 结构 |
| `scripts/sync_items_from_source.php` | 藏品数据与源站对齐（预演 / `--apply` 写库，幂等） |
| `scripts/export_public_key.php` | 从 `.env` 导出裸 base64 公钥给前端 |
| `scripts/serve_up.sh` | 一键恢复 MariaDB → PHP → Vite（顺序有依赖，每步带健康轮询） |

前端侧巡检脚本（`/workspace/scripts/`）：

| 脚本 | 用途 |
|------|------|
| `sweep_all_pages.py` | 58 页全量巡检：路由落点 / 渲染内容 / JS 错误 / 破图 |
| `img_check.py` | 逐页破图检测 |
| `e2e_check.py` | 38 项端到端断言 |
| `fix_fake_images.py` | 「假图」清查（文件内容其实是 404 HTML）与修复 |

### 巡检脚本里的三个坑

1. **`add_init_script` 不能用来注入 token** —— 它会在 `about:blank` 上执行，
   那是 opaque origin，访问 `localStorage` 直接抛 `Access is denied`，
   结果每页都报 JS 错误。改为先 `goto` 同源页面再写 token。
2. **`Page.add_init_script()` 只接受一个参数** —— 传两个会 `TypeError`，
   token 要用字符串拼接内联。
3. **巡检 query 参数必须对齐源站 `onLoad` 的真实字段名** —— 之前
   `mergeInfo` 用 `iid`、`nft/info` 与 `nft/sale` 用 `iid`、`notice/search` 用 `cid`、
   `webView` 用 `url`，全都取不到值，源站会 `reLaunch` 回首页，
   巡检就误报「路由落点错误」。真实字段：`id` / `nftId` / `iid` / `src`。

### 源站「已下线」页面的正确复刻方式

`/pages/activity/baoxiang` 源站 `getList()` 原文是
`return this.list=[], this.total=0, this.lastPage=1, void(this.ajax.load=!1)`
—— **不发请求、直接清空**。本地曾多调一次接口拿到数据并渲染卡片，
既多出源站没有的内容，封面 `cover` 又是相对路径导致破图。
复刻这类页面要照抄「不发请求」这个事实，不要「顺手补全数据」。

代码规模：7465 行 / 12 控制器 / 49 模型 / 96 路由。
