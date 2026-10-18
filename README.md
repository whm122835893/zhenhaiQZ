# 臻海艺术 H5 —— 一比一像素级复刻

对 `https://h5.shengtai.cyou` 的像素级复刻项目，含配套后端。

- **前端**：Vue 3 + Vue Router 4（hash 模式）+ Vite 6，58 个页面全部对齐源站
- **后端**：ThinkPHP 8.1 + MySQL/MariaDB，31 个端点对齐源站 API
- **后端零第三方依赖**：JWT、RSA、雪花 ID 均自实现

## 快速开始

### 后端

```bash
cd server/shengtai-api
cp .env.example .env          # 填 DB_PASS / JWT_SECRET / RSA_PRIVATE_KEY
composer install
php think run -p 8921         # 或用 scripts/serve_up.sh 一步拉起三件套
```

生成 RSA 密钥对：

```bash
openssl genpkey -algorithm RSA -out rsa.pem -pkeyopt rsa_keygen_bits:2048
php scripts/export_public_key.php    # 从私钥导出公钥，填回 .env 与前端 .env
```

### 前端

```bash
npm install
npm run dev     # http://127.0.0.1:5173
npm run build   # 产出 dist/index.html（singlefile 单文件模式）
```

一键恢复整套环境（MariaDB → PHP → Vite，顺序有依赖）：

```bash
bash server/shengtai-api/scripts/serve_up.sh
```

## 目录结构

```
├── src/                    前端源码（58 页 views + 逐页还原的 assets）
│   ├── views/              按源站目录结构分模块（index / market / my / activity / notice …）
│   ├── components/         nb-model-viewer、TabBar、P1BackBar 等
│   ├── api/                接口封装（request 拦截 + 各模块 api）
│   └── router/index.js     58 条路由，meta.title 对齐源站导航标题
├── public/static/model3d/  3D 播放器（model-viewer.min.js + model.gltf，与源站 md5 一致）
├── server/shengtai-api/    ThinkPHP 后端
│   ├── app/controller/     12 个控制器
│   ├── app/model/          49 个模型
│   ├── route/app.php       96 条路由
│   └── scripts/            验证与运维脚本
├── database/               建表与种子数据
├── scripts/                巡检脚本（sweep_all_pages.py / e2e_check.py 等）
└── docs/                   API 与数据库设计文档
```

## 当前验证结果

| 验证项 | 结果 |
|--------|------|
| 全站页面巡检 | **58/58 全绿**（路由落点 / 渲染内容 / JS 错误 / 破图，带登录态逐页加载） |
| 前端端到端 | **38/38 全绿** |
| 后端端点 | **31/31**（18 公开 + 4 未登录拦截 + 9 需登录） |
| 分页结构 vs 源站 | **8/8 全部一致** |
| 藏品详情逐字段 | **3 条 × 39 字段 = 117 字段零差异** |
| 破图 | **0**（逐元素查 `naturalWidth`，非「全页无报错」式判断） |
| 生产构建 | 通过（752 模块，singlefile 模式） |

验证脚本：

| 脚本 | 用途 |
|------|------|
| `scripts/sweep_all_pages.py` | 58 页全量巡检 |
| `scripts/e2e_check.py` | 38 项端到端断言 |
| `scripts/img_check.py` | 逐页破图检测 |
| `server/shengtai-api/scripts/verify_all.sh` | 31 端点全量验证 |
| `server/shengtai-api/scripts/check_pagination_shape.sh` | 本地 vs 源站分页结构比对 |
| `server/shengtai-api/scripts/sync_items_from_source.php` | 藏品数据与源站对齐（幂等） |

## 复刻要点与踩坑

完整清单见 [`server/shengtai-api/README.md`](server/shengtai-api/README.md)，几个关键的：

**源站取参字段名容易搞错** —— 各页 `onLoad` 取的 query 键不统一，
取错会被 `reLaunch` 回首页：

| 页面 | 正确键名 |
|------|---------|
| `/pages/activity/mergeInfo` | `id`（+ `type`） |
| `/pages/my/nft/info`、`/pages/my/nft/sale` | `nftId` |
| `/pages/notice/search` | `iid` |
| `/pages/index/webView` | `src` |

**源站已下线页面要照抄「不发请求」这个事实** ——
`/pages/activity/baoxiang` 的 `getList()` 原文是
`return this.list=[], this.total=0, ...`，直接清空、根本不发请求。
本地曾「顺手补全」多调一次接口，既多出源站没有的内容，封面又是相对路径导致破图。

**「假图」识别** —— 源站对不存在的路径返回 404 HTML（约 1017 字节）。
早期抓图时把这些错误页存成了 `.png`，导致 27 张假图。
判断方法：文件首字节是 `<` 就是假的。

**`//static/...` 会被当协议相对 URL** —— hash 路由下 `pathname` 恒为 `/`，
用 `pathname.replace(/[^/]*$/, '')` 可能拿到空串，拼出 `//static/...`，
浏览器会把 `static` 当域名解析成 `http://static/...`。
用 `new URL('/static/model3d/index.html', location.origin).pathname` 规避。

**接口失败时不跳首页** —— 源站 `getOrder` 只有
`t.status ? (赋值) : (无操作)`，没有「失败就跳走」的分支。

**巡检时不能用 `add_init_script` 注入 token** —— 它在 `about:blank`
（opaque origin）上执行，访问 `localStorage` 直接抛 `Access is denied`，
结果每页都报 JS 错误。改为先 `goto` 同源页面再写 token。

## 安全

`.env` 含 `RSA_PRIVATE_KEY` / `JWT_SECRET` / `DB_PASS`，已被 `.gitignore` 排除。
仓库中提供 `server/shengtai-api/.env.example` 模板供还原。

生产部署前务必重新生成密钥并确认 `APP_DEBUG=false`。

## License

MIT
