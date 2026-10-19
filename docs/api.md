# 盛泰数字藏品平台 · API 接口文档

> 版本：v1.0
> 生成日期：2026-07-19
> Base URL：`https://api.shengtai.cyou`（源站）→ 复刻后自建域名
> 框架：ThinkPHP 6
> 数据来源：源站 6 个分包模块契约（`item` / `nft` / `user` / `order` / `activity` / `basic`）+ 30 页运行时抓取交叉验证

---

## 证据分级（阅读本文档前必读）

本文档所有 JSON 示例分三级。**引用前必须先确认该块属于哪一级**：

| 标记 | 含义 | 可否作为契约依据 |
|------|------|-----------------|
| `【实测】` | 本次或既往从 `https://api.shengtai.cyou` 直接抓取的原始响应，块下方附实测时间与端点 | **可以**。字段名、类型、取值口径均可信 |
| `【结构示意】` | 仅表达 JSON 结构与字段类型，值取自文档编写时的记忆或模板，**未经抓包核验** | **不可以**。仅可用于理解结构，字段值一律不可信 |
| `【占位】` | 已知污染残留或无法抓取，块下方附 `⚠️` 说明污染原因 | **不可以**。禁止据此开发 |

历史教训（OPEN-01，2026-10-19 结案）：本文档 3.1 节曾以「实测抓取」为标题，
正文却是 `0x1234...abcd`、`"cid": 1`、`臻海艺术藏品`、`price: 38800` 这类模板占位符，
并在其上建立了一条错误结论「`price: 38800` = 388.00 元（单位分）」。
该错误结论被下游开发当权威依据，险些造成本地成为唯一偏离源站的实现。
**根因是只读文档未实抓，把占位符当成了实测值。**

### 引用本文档做开发前的强制动作

任何章节在动手写代码前，必须先自行复核，禁止直接抄文档里的示例值：

```bash
# 免登录端点可直接抓（推荐复核命令）
curl -s "https://api.shengtai.cyou/item/2109928427009183745" | python3 -m json.tool

# 批量比对本文档示例与源站实时响应，标记失配项
python3 scripts/verify_docs_against_source.py            # 全量扫描
python3 scripts/verify_docs_against_source.py --section 3 # 只扫第 3 章
```

自动化脚本 `scripts/verify_docs_against_source.py` 会抽取本文档中所有 `【实测】` 标记的
JSON 块，逐块与源站实时响应做字段级比对，输出 `MATCH` / `MISMATCH` / `UNREACHABLE`。
**CI 应将其作为阻断项**：出现 `MISMATCH` 即说明文档已与源站漂移，必须先修文档再开发。

需要登录态的端点（返回 `code:11111`）本环境无法抓取，脚本标记为 `UNREACHABLE`，
这类块在文档中一律降级为 `【占位】` 并注明原因，不允许伪装成实测。

---

## 0. 通用约定

### 0.1 响应包络

**所有接口**统一返回：

> **【结构约定】** 通用响应包络定义，非某一端点的实测响应。
```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": { }
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| status | bool | 是否成功，前端唯一判定依据 |
| code | int | 业务码，`0` 为成功 |
| message | string \| null | 错误提示，前端直接 toast |
| data | mixed | 业务数据 |

> 前端判定逻辑严格使用 `res.status`，不使用 HTTP 状态码。

### 0.2 错误码

| code | message | 前端行为 |
|------|---------|---------|
| `0` | `null` | 正常渲染 |
| `11111` | 请先登录 | toast「请先登录」→ 跳转登录页 |
| `10203` | 未找到 | 空态 / 404 页 |
| `10100` | 参数缺失 | toast 错误信息 |

### 0.3 公共请求参数

**所有请求**都需携带（源站 luch-request 拦截器注入）：

| 参数 | 类型 | 必填 | 示例 | 说明 |
|------|------|------|------|------|
| appid | string | 是 | `__UNI__22FD095` | 应用标识 |
| version | string | 是 | `1.3.2` | 前端版本 |
| deviceId | string | 是 | `uuid` | 设备唯一标识 |
| deviceModel | string | 是 | `PC` | 设备型号 |
| platform | string | 是 | `web` | 平台 |

### 0.4 鉴权

- 机制：`Authorization: Bearer {token}`
- Token 来源：`POST /passport/signin` 返回，或 `GET /user/profile` 的 `token` 字段
- 未登录访问受保护接口 → `code=11111`

### 0.5 密码加密

登录/注册/改密的 `password` 字段需 **RSA 公钥加密**（前端 `JSEncrypt`，公钥随 `GET /passport/captcha/image` 或配置下发），Base64 编码传输。服务端解密后做 bcrypt 校验。

### 0.6 分页参数

| 参数 | 类型 | 默认 | 说明 |
|------|------|------|------|
| pageNo | int | 1 | 页码（**源站用 `pageNo`，非 `page`**） |
| pageSize | int | 20 | 每页条数 |

列表响应（部分接口带 `total`）：

> **【结构示意】** 分页包络的通用形态说明，非某一端点的实抓响应。
> 各端点是否带 `total`、以及分页字段名，必须按该端点章节的实抓块单独确认。

```json
{ "list": [], "total": 0 }
```

### 0.7 数据类型约定

| 源站类型 | 说明 | 约定 |
|---------|------|------|
| 主键 | 19 位雪花 ID | **字符串**返回（超 JS `Number` 安全范围） |
| 时间 | 13 位毫秒 | **字符串**返回，如 `"1791959018537"` |
| 金额 | 分 | 整数返回，前端 `/100` 得元。**实测依据见 3.1.2 与 3.1.3**（源站 bundle 的 `amountFormat` 实现 + 逐字段实抓核对）|
| 图片 | `image` + `imageUrl` | 双字段并存 |

---

## 1. 账号与鉴权 `passport`

> 控制器：`app\controller\Passport`
> 源站契约来源：`user.B_JBCLxr.js`

| Method | Path | 功能 | 认证 | 请求参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/passport/captcha/image` | 图形验证码 | 否 | `key` | 是 |
| POST | `/passport/captcha/sms` | 短信验证码 | 否 | `phone` | 是 |
| POST | `/passport/signup` | 注册 | 否 | `phone, code, password, inviter?` | 是 |
| POST | `/passport/signin` | 账号密码登录 | 否 | `account, password(RSA)` | 是 |
| POST | `/passport/signin/quick` | 快捷登录 | 否 | `phone, code` | 是 |
| POST | `/passport/signin/oneClick` | 一键登录 | 否 | `provider, openid` | 是 |
| POST | `/passport/signout` | 退出登录 | 是 | — | 是 |
| POST | `/passport/delete` | 注销账号 | 是 | — | 是 |
| GET | `/passport/account` | 账号信息 | 是 | — | 是 |
| PUT | `/passport/password` | 修改密码 | 是 | `oldPassword, newPassword` | 是 |
| PUT | `/passport/phone` | 修改手机号 | 是 | `phone, code` | 是 |
| PUT | `/passport/secondPassword` | 设置二级密码 | 是 | `password` | 是 |
| POST | `/passport/realName` | 实名认证 | 是 | `realName, idCard` | 是 |
| GET | `/passport/online` | 在线设备列表 | 是 | `pageNo, pageSize` | 是 |
| GET | `/passport/online/{id}` | 在线设备详情 | 是 | — | 是 |
| DELETE | `/passport/online/{id}` | 下线设备 | 是 | — | 是 |

### 1.1 `POST /passport/signin` 请求

> **【结构示意】** 请求体结构取自源站 bundle 静态提取，**未实抓验证**（登录需验证码+短信）。
> `deviceId` 在源站是客户端生成的设备指纹，取值任意，示例中的占位为正常写法，非污染。

```json
{
  "account": "<手机号或账号>",
  "password": "<RSA 加密后Base64>",
  "appid": "__UNI__22FD095",
  "version": "<客户端版本号>",
  "deviceId": "<设备指纹，客户端生成>",
  "deviceModel": "<设备型号，如 PC>",
  "platform": "web"
}
```

### 1.2 `GET /passport/account` 响应

> ⚠️ 以下为**占位示意，非源站实测**。原因：该端点需登录态，本环境无法完成源站登录。
> **不可作为契约依据。** 字段名可信（源站 bundle 静态提取），
> 其中「手机号/账号/姓名/身份证在响应层脱敏」这一行为已由源站 bundle 的
> `formatSensitive` 系列函数静态确认，脱敏形态（中间位以 `*` 替代）可信。
> 具体脱敏位数与示例值不可信。

> **【占位】** 需登录态，本环境无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "uid": "<用户ID字符串>",
    "phone": "<脱敏手机号，如 175****1293>",
    "email": null,
    "account": "<脱敏账号>",
    "createTime": "<13位毫秒时间戳字符串>",
    "hasPassword": true,
    "hasSecondPassword": false,
    "hasRealName": true,
    "realName": "<脱敏姓名>",
    "idCard": "<脱敏身份证号>"
  }
}
```

> 脱敏在响应层处理，数据库存原文。

---

## 2. 用户 `user`

> 控制器：`app\controller\User`
> 源站契约：`user.B_JBCLxr.js`

| Method | Path | 功能 | 认证 | 请求参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/user/profile` | 用户资料 | 是 | — | 是 |
| POST | `user/profile/avatar` | 上传头像 | 是 | `multipart: file` | 是 |
| PUT | `user/profile/nickname` | 改昵称 | 是 | `nickname` | 是 |
| PUT | `user/profile/introduction` | 改简介 | 是 | `introduction` | 是 |
| GET | `/user/invite` | 邀请记录 | 是 | `pageNo, pageSize` | 是 |
| GET | `/user/resource` | 用户资源/余额 | 是 | — | 是 |

### 2.1 `GET /user/profile` 响应

> ⚠️ 以下为**占位示意，非源站实测**。原因：该端点需登录态，本环境无法完成源站登录
> （源站要求图形验证码 + 真实短信）。**不可作为契约依据。**
> 字段名可信（源站 bundle 静态提取），其中 `avatarUrl` / `avatar` 的形态已由免登录端点
> `GET /item/{iid}.author` 交叉确认（同为「绝对地址 + 相对路径」双字段）。
> **注意 `token` 字段仅在登录响应中出现，不应出现在 profile 响应里**，
> 旧模板把它列进来属于臆造字段，接入时需向源站复核。

> **【占位】** 需登录态，本环境无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "uid": "<用户ID字符串>",
    "avatarUrl": "<媒体绝对地址，如 https://h5.shengtai.cyou/media/xxx.png>",
    "avatar": "<媒体相对路径，如 media/xxx.png>",
    "nickname": "<昵称>",
    "sex": 0,
    "birthday": null,
    "country": null,
    "regionCode": null,
    "introduction": "",
    "chainAddress": null,
    "inviteCode": "<邀请码>",
    "inviter": null,
    "createTime": "<13位毫秒时间戳字符串>"
  }
}
```

> ⚠️ 登录响应 `POST /passport/signin` 的 `data` 中确实含 `token`（字符串），
> 格式为 `{uid, token, ...}`。该结构未能在本环境实抓验证，接入前须复核。

---

## 3. 藏品目录 `item`

> 控制器：`app\controller\Item`
> 源站契约：`item.Cvv7yXn2.js`

| Method | Path | 功能 | 认证 | 请求参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/item` | 藏品列表 | 否 | `pageNo, pageSize, cid?, name?, isRecommend?, isPopular?, type?` | 是 |
| GET | `/item/{iid}` | 藏品详情 | 否 | — | 是 |
| GET | `/item/category` | 分类列表 | 否 | — | 是 |
| GET | `/item/author` | 作者列表 | 否 | `pageNo, pageSize` | 是 |
| GET | `/item/copyright` | 版权信息 | 否 | `iid` | 是 |
| POST | `/item/follow/{iid}` | 关注藏品 | 是 | — | 是 |
| DELETE | `/item/unfollow/{iid}` | 取消关注 | 是 | — | 是 |
| GET | `/product/tp_category` | 图谱分类 | 否 | — | 是 |
| GET | `/product/tp_list` | 图谱列表 | 否 | `cid?, pageNo, pageSize` | 是 |

### 3.1 `GET /item/{iid}` 响应

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /item/2109928427009183745`（免登录）
> 原始响应存档：`.cache/source-probe/item_2109928427009183745.json`
> 本块为源站原始响应逐字段转录，可直接作为契约依据。共 39 个字段。

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": {
    "iid": "2109928427009183745",
    "cid": "2103038591555887106",
    "authorId": "2103038557930151938",
    "contract": "0xd2de6137500648aa98300ac8f947457a",
    "name": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "content": "通往财富大门的钥匙",
    "price": 388,
    "type": 1,
    "issueQuantity": 888888,
    "buyQuantity": 100,
    "preStartTime": "0",
    "preEndTime": "0",
    "startTime": "1792067400000",
    "endTime": "1792078800000",
    "isPrimary": false,
    "isPrimaryApply": false,
    "isRecommend": false,
    "isPopular": false,
    "isGive": false,
    "isResale": false,
    "isSecondary": true,
    "isSecondaryBatch": true,
    "isSecondaryApply": false,
    "isSecondaryTrust": true,
    "secondaryLowPrice": 0,
    "secondaryHighPrice": 500,
    "secondaryApplyLowPrice": 0,
    "secondaryApplyHighPrice": 0,
    "boxOpenTime": "0",
    "isSoldOut": true,
    "nftQuantity": 95824,
    "ownQuantity": 0,
    "isPre": false,
    "isFollow": false,
    "buyTime": "1792067400000",
    "category": {
      "cid": "2103038591555887106",
      "name": "臻海",
      "sort": 0
    },
    "author": {
      "id": "2103038557930151938",
      "name": "臻海艺术",
      "avatarUrl": "https://h5.shengtai.cyou/media/2103038524870647809.png",
      "avatar": "media/2103038524870647809.png",
      "introduction": "有着丰富的独立艺术品创作经验。"
    }
  }
}
```

> 实测时间：2026-10-19，抓取端点：`GET /item/{iid}`

#### 3.1.1 类型口径纠错（实测确认，勿凭直觉推断）

本块实测值与旧版文档模板差异极大，以下几点是硬结论：

| 项 | 实测值 | 说明 |
|----|--------|------|
| `price` | `388` | **单位是分**，页面显示 `￥3.88` |
| `type` | `1` | **数字枚举**，不是字符串 `"normal"` |
| `is*` 系列 | `true` / `false` | **JSON 布尔**，不是 `0` / `1` |
| `preStartTime` / `preEndTime` / `boxOpenTime` | `"0"` | **字符串 "0"**，不是 `null` |
| `startTime` / `endTime` / `buyTime` | `"1792067400000"` | **字符串承载的13 位毫秒时间戳** |
| `category` / `author` | 嵌套对象 | `/item/{iid}` 内直接返回，无独立端点 |
| 字段总数 | 39 | 旧文档写 35，且缺失 `category`/`author`/`ownQuantity`/`isPre`/`isFollow`/`buyTime` |

#### 3.1.2 金额单位：源站所有金额字段统一为「分」

**这一节是本项目最容易造成资损的地方，后台管理复用同一口径。**

源站前端 bundle（`index-La17T32K.js`）中的金额格式化实现，实测原文：

```js
amountFormat: function(e){ return e ? "￥" + Number(e/100).toFixed(2) : "￥--" },
amountFormat2: function(e){ return e ? Number(e/100).toF... }  // 无货币符号，供"今日成交额"等拼接"元"字使用
```

即：**源站后端返回的每一个金额字段，单位都是「分」，前端统一除以 100 展示为元。
后端与数据库不做任何除 100 的换算。**

因此本项目正确做法是：库里存分、接口原样返回分、前端除 100。
`Transformer::amount()` 的注释「库里存的是分，原样返回」是准确的。

> ⚠️ 已修正的错误结论：旧版本文档第 250 行写「`price: 38800` = 388.00 元（单位分）」。
> 该句建立在占位符 `38800` 之上，属双重错误（数值是编的，且把 `38800` 解读为 388.00 元）。
> 真实情况是源站返 `388`，含义 `3.88` 元。**该句已删除，禁止再引用。**

#### 3.1.3 金额字段对照表（后台管理复用此表）

全部取自 2026-10-19 实抓，**无一例外单位均为分，后端均不换算**。

| 字段名 | 出现端点 | 源站实测值 | 含义（元） | 单位 | 后端是否换算 | 前端是否换算 |
|--------|---------|-----------|-----------|------|-------------|-------------|
| `price` | `/item/{iid}`、`/nft/secondary/sell` | `388` | 3.88 | 分 | 否，原样返回 | 是，`amountFormat` 除 100 |
| `buyPrice` | `/nft/secondary/sell` | `388` | 3.88 | 分 | 否 | 是 |
| `secondaryLowPrice` | `/item/{iid}`、`/nft/secondary*` | `0` | 0.00 | 分 | 否 | 是 |
| `secondaryHighPrice` | `/item/{iid}`、`/nft/secondary*` | `500` | 5.00 | 分 | 否 | 是 |
| `secondaryApplyLowPrice` | `/item/{iid}`、`/nft/secondary*` | `0` | 0.00 | 分 | 否 | 是 |
| `secondaryApplyHighPrice` | `/item/{iid}`、`/nft/secondary*` | `0` | 0.00 | 分 | 否 | 是 |
| `minPrice` | `/nft/secondary/{iid}` | `300` | 3.00 | 分 | 否，`amountOrNull` 容忍 null | 是 |
| `maxPrice` | `/nft/secondary/{iid}` | `500` | 5.00 | 分 | 否，可为 null | 是 |
| `lastTradeAmount` | `/nft/secondary` | `10000` | 100.00 | 分 | 否，可为 null | 是 |
| `todayTradeAmount` | `/nft/secondary/{iid}` | `5649000` | 56490.00 | 分 | 否，可为 null | 是，用 `amountFormat2` 拼接"元" |
| `tradeAmount` | `/nft/secondary/{iid}` | `null` | — | 分 | 否，可为 null | — |
| `price`（挂单） | `/nft/secondary/sell` | `300` | 3.00 | 分 | 否 | 是 |
| `soldPrice` | `/nft/secondary/sold` | `300` | 3.00 | 分 | 否 | 是 |
| `unitPrice` | `/nft/secondary/trust` | `400` | 4.00 | 分 | 否 | 是 |
| `amount` | `/nft/secondary/trust` | `400000` | 4000.00 | 分 | 否 | 前端算 `unitPrice*quantity` 展示 |

**关键提醒：不存在「某些字段源站直接返元」的情况。** 数值大小差异（如 `500` vs `5649000`）
只反映金额量级不同，不代表单位不同。判断单位只有一个依据：前端是否对它调用了 `amountFormat`。

### 3.2 作者详情（源站未上线）

| Method | Path | 说明 | 源站行为 |
|--------|------|------|---------|
| GET | `/api/author/detail` | 作者详情 | 线上**恒返回** `{status:false, code:10203, message:"未找到"}` |

源站前端走 mock 兜底：`name="臻海艺术"`、`avatar=/static/images/logo.png`、`works` 为 mock 三图。复刻时**照搬此 mock 兜底行为**。

> ⚠️ 注意区分两个作者来源：`GET /item/author`（列表）是**真实可用**的，
> 而 `GET /api/author/detail`（详情）线上恒 10203。旧文档只提后者，容易误判作者域整体不可用。

### 3.3 `GET /item/category` 分类列表

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /item/category`（免登录）
> 原始响应存档：`.cache/source-probe/item_category.json`，实际返回 2 条，全量列出。
> **可作为契约依据。**

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": [
    { "cid": "2103038591555887106", "name": "臻海", "sort": 0 },
    { "cid": "2112107595570434049", "name": "其他", "sort": 3 }
  ]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /item/category`
> 注：`cid` 是字符串形态的雪花 ID，不是数字。

### 3.4 `GET /item/author` 作者列表

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /item/author`（免登录）
> 原始响应存档：`.cache/source-probe/item_author.json`，实际返回 2 条，全量列出。
> **可作为契约依据。**

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": [
    {
      "id": "2109922931380424706",
      "name": "闹翻天",
      "avatarUrl": "https://h5.shengtai.cyou/media/2109922767282475009.png",
      "avatar": "media/2109922767282475009.png",
      "introduction": "插画师、建筑师，曾任职中国国家画院-公共艺术中心研究员中国建筑设计研究院-主创设计师。"
    },
    {
      "id": "2103038557930151938",
      "name": "臻海艺术",
      "avatarUrl": "https://h5.shengtai.cyou/media/2103038524870647809.png",
      "avatar": "media/2103038524870647809.png",
      "introduction": "有着丰富的独立艺术品创作经验。"
    }
  ]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /item/author`

### 3.5 `GET /item/copyright` 版权信息

> **【实测】** 实测时间：2026-10-19，抓取端点：
> `GET /item/copyright?iid=2109928427009183745`（免登录）
> 原始响应存档：`.cache/source-probe/item_copyright.json`，实际返回 10 条，此处列首 2 条。
> **可作为契约依据。**

```json
{
  "status": true,
  "code": 0,
  "message": null,
  "data": [
    {
      "id": "2112090253767561218",
      "name": "1",
      "imageUrl": "https://h5.shengtai.cyou/media/2112074138593087490.jpg",
      "image": "media/2112074138593087490.jpg"
    },
    {
      "id": "2112074529820987393",
      "name": "1",
      "imageUrl": "https://h5.shengtai.cyou/media/2111991142808637441.jpg",
      "image": "media/2111991142808637441.jpg"
    }
  ]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /item/copyright`
> 注：该端点**不按 iid 过滤**，恒返回全局版权条目；字段仅 `id`/`name`/`image`/`imageUrl`。

---

## 4. NFT 资产与仓库 `nft`

> 控制器：`app\controller\Nft`
> 源站契约：`nft.CgdmklJr.js`

| Method | Path | 功能 | 认证 | 请求参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/nft` | 我的 NFT 列表 | 是 | `pageNo, pageSize, iid` | 是 |
| GET | `/nft/group` | 仓库分组列表 | 是 | `pageNo, pageSize, itemType, is_merge` | 是 |
| GET | `/nft/{nftId}` | NFT 详情 | 是 | — | 是 |
| POST | `/nft/box/open` | 开箱 | 是 | `iid, quantity` | 是 |
| GET | `/nft/digitalAlbum/calendar` | 数字藏品日历 | 否 | `iid` | 是（实测 10203） |
| POST | `/nft/sell/up` | 单条寄售 | 是 | `nftId, price` | 是 |
| POST | `/nft/sell/up/batch` | 批量寄售 | 是 | `iid, price, quantity` | 是 |
| PUT | `/nft/sell/down` | 下架 | 是 | `nftId` 或 `id` | 是 **PUT 非 POST** |
| GET | `/nft/sell/sold` | 已售记录 | 是 | `pageNo, pageSize` | 是 |
| POST | `/nft/sell/apply` | 出售给求购单 | 是 | `iid, nftId, applyOrderId, secondPassword` | 是 |
| GET | `/nft/secondary` | 二级市场列表 | 否 | `pageNo, pageSize, ...` | 是 |
| GET | `/nft/secondary/{iid}` | 市场藏品详情 | 否 | — | 是 |
| GET | `/nft/secondary/sell` | 在售挂单列表 | 否 | `iid, pageNo, pageSize` | 是 |
| GET | `/nft/secondary/sold` | 成交记录 | 否 | `iid, pageNo, pageSize` | 是 |
| GET | `/nft/secondary/apply` | 求购列表 | 否 | `iid, pageNo, pageSize` | 是 |
| GET | `/nft/secondary/trust` | 委托列表 | 否 | `iid, pageNo, pageSize` | 是 |
| GET | `/nft/secondary/follow` | 我关注的市场 | 是 | `pageNo, pageSize` | 是 |

### 4.2 全站 GET 端点扫描结果（2026-07-18）

从 286 个源站分包正则提取出**全站 57 个 GET 端点**，逐一实抓。关键结论：

| 端点 | 实测结果 | 说明 |
|------|---------|------|
| `GET /item/{iid}` | 成功，35 个字段 | **正确用法是路径拼 iid**；`/item/detail` 恒报 `10101` |
| `GET /item/{iid}/author` | `code:10203` | 作者走`/item/{iid}` 内的嵌套 `author` 对象，无独立端点 |
| `GET /item/copyright?iid=` | 成功，**返回全局 6 条** | **不按 iid 过滤**，字段仅 `id`/`name`/`image`/`imageUrl` |
| `GET /item/merge` | 成功，`merges`/`needs` 为 **null** | 列表接口不加载子表 |
| `GET /item/merge/{id}` | 成功，返回真实 `merges`/`needs` | 详情接口才加载 |
| `GET /nft/secondary` | 成功，3 条 | 二级市场列表 |
| `GET /nft/secondary/{iid}` | 成功，31 个字段 | 市场藏品详情（含全部统计字段）|
| `GET /nft/secondary/follow` | 成功，3 条 | 我关注的市场 |
| `GET /nft/sell/sold` | 成功，`[]` | 已售记录（本账号无）|
| `GET /item` | 成功，`[]` | 藏品列表（本账号无）|
| `GET /user/invite` | 成功，`{list:[],total:0}` | 邀请记录 |
| `GET /common/rule` | `code:10203` | 规则端点不存在 |
| `GET /app/version/check` | `code:10101` | 传 `versionCode` 仍报参数类型错误 |

### 4.1 `GET /nft/group` 响应

> ⚠️ 以下为**占位示意，非源站实测**。原因：该端点需登录态，本环境无法完成源站登录
> （源站 `passport/captcha/sms` 要求图形验证码 + 真实短信，脚本环境不具备）。
> **不可作为契约依据。** 字段名可信（来自源站 bundle 静态提取），字段值一律不可信。
> 已知的可信部分：结构为数组、金额字段单位为分（见 3.1.3 对照表）。

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "iid": "2109928427009183745",
    "name": "<藏品名，取自 /item/{iid}.name>",
    "imageUrl": "<媒体绝对地址，取自 /item/{iid}.imageUrl>",
    "image": "<媒体相对路径，取自 /item/{iid}.image>",
    "type": "<数字枚举，同 /item/{iid}.type>",
    "ownQuantity": "<int>",
    "minPrice": "<int，单位分，可为 null>",
    "priceChange": "<涨跌幅，可为 null>"
  }]
}
```

### 4.2 `GET /nft/{nftId}` 响应

> ⚠️ 以下为**占位示意，非源站实测**。原因：同4.1，需登录态。
> **不可作为契约依据。**
> 字段名可信（源站 bundle 静态提取），字段值不可信。
> 其中 `chainId` / `chainCompleted` / `payLock` 的类型已由同族端点
> `GET /nft/secondary/sell`（免登录，实测）交叉确认：`chainId` 为 `0x` 开头的
> 32 位十六进制字符串，`chainCompleted` 与 `payLock` 为 JSON 布尔。

> **【占位】** 需登录态，本环境无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "nftId": "<雪花ID字符串>",
    "uid": "<持有者uid字符串>",
    "iid": "<藏品ID字符串>",
    "name": "<藏品名>",
    "imageUrl": "<媒体绝对地址>",
    "image": "<媒体相对路径>",
    "token": "<int>",
    "status": "<int>",
    "getTime": "<13位毫秒时间戳字符串>",
    "price": "<int，单位分>",
    "payLock": "<bool>",
    "payWay": "<int>",
    "chainId": "0x<32位十六进制>",
    "chainCompleted": "<bool>",
    "buyPrice": "<int，单位分>",
    "item": { /* 嵌套完整 item 对象，结构同 3.1 */ }
  }
}
```

### 4.3 `GET /nft/secondary` 响应（二级市场核心数据）

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /nft/secondary`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary.json`，实际返回 8 条，此处列首条。
> **可作为契约依据。** 字段名与类型以源站 bundle 交叉确认：`type` 为数字枚举，
> `is*` 为 JSON 布尔，`priceChange` 实测恒为数字 `0`。

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "iid": "2112103125507723265",
    "contract": "0xeaa7302050204e65b8e55a53dc578c4e",
    "name": "臻海点券",
    "imageUrl": "https://h5.shengtai.cyou/media/2112102687215538177.png",
    "image": "media/2112102687215538177.png",
    "content": null,
    "type": 1,
    "issueQuantity": 100000,
    "isGive": false,
    "isSecondary": true,
    "isSecondaryBatch": false,
    "isSecondaryApply": false,
    "isSecondaryTrust": true,
    "secondaryLowPrice": 0,
    "secondaryHighPrice": 10000,
    "secondaryApplyLowPrice": 0,
    "secondaryApplyHighPrice": 0,
    "nftQuantity": 1356,
    "sellQuantity": 0,
    "minPrice": null,
    "maxPrice": null,
    "tradeQuantity": 365,
    "tradeAmount": null,
    "lastTradeAmount": 10000,
    "isFollow": false,
    "todayTradeQuantity": 365,
    "hourTradeQuantity": null,
    "todayTradeAmount": 3650000,
    "todayTradeMaxAmount": null,
    "todayTradeMinAmount": null,
    "priceChange": 0
  }]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /nft/secondary`

> 注：`minPrice` / `maxPrice` / `tradeAmount` / `lastTradeAmount` /
> `todayTradeMaxAmount` / `todayTradeMinAmount` / `hourTradeQuantity` /
> **`content`** 在源站返回 **`null` 而非 `0` 或空串**，本地字段必须可空。

### 4.3.1 `GET /nft/secondary/{iid}` 响应（市场藏品详情，31 字段）

> **【实测】** 实测时间：2026-10-19，抓取端点：`GET /nft/secondary/2109928427009183745`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary_iid.json`。**可作为契约依据。**

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "iid": "2109928427009183745",
    "contract": "0xd2de6137500648aa98300ac8f947457a",
    "name": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "content": "通往财富大门的钥匙",
    "type": 1,
    "issueQuantity": 888888,
    "isGive": false,
    "isSecondary": true,
    "isSecondaryBatch": true,
    "isSecondaryApply": false,
    "isSecondaryTrust": true,
    "secondaryLowPrice": 0,
    "secondaryHighPrice": 500,
    "secondaryApplyLowPrice": 0,
    "secondaryApplyHighPrice": 0,
    "nftQuantity": 95824,
    "sellQuantity": 7207,
    "minPrice": 300,
    "maxPrice": 500,
    "tradeQuantity": 327215,
    "tradeAmount": null,
    "lastTradeAmount": null,
    "isFollow": false,
    "todayTradeQuantity": 13108,
    "hourTradeQuantity": null,
    "todayTradeAmount": 5649000,
    "todayTradeMaxAmount": null,
    "todayTradeMinAmount": null,
    "priceChange": 0
  }
}
```

> 实测时间：2026-10-19，抓取端点：`GET /nft/secondary/{iid}`

### 4.3.2 `GET /nft/secondary/sell` 在售挂单列表

> **【实测】** 实测时间：2026-10-19，抓取端点：
> `GET /nft/secondary/sell?iid=2109928427009183745&pageNo=1&pageSize=3`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary_sell.json`。**可作为契约依据。**

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "nftId": "2110747115382345730",
    "uid": "2110584177333112834",
    "iid": "2109928427009183745",
    "name": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "token": 126855,
    "status": 1,
    "getTime": "1792076302750",
    "price": 300,
    "payLock": true,
    "payWay": 1,
    "chainId": "0x9d1c63bc57e94c9bafb792070501cbe9",
    "chainCompleted": true,
    "buyPrice": 388,
    "item": null,
    "owner": null,
    "isSelf": false
  }]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /nft/secondary/sell`
> 注：`price` 是**挂售价**（覆盖 NFT 自身价格），`buyPrice` 是**买入价**，
> 两者不可混用。`item` / `owner` 在列表接口返回 `null`，仅详情接口填充。

### 4.3.3 `GET /nft/secondary/sold` 成交记录

> **【实测】** 实测时间：2026-10-19，抓取端点：
> `GET /nft/secondary/sold?iid=2109928427009183745&pageNo=1&pageSize=3`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary_sold.json`。**可作为契约依据。**

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "nftId": "2110747871812489219",
    "token": 751273,
    "soldPrice": 300,
    "soldTime": "1792423281228",
    "chainId": "0x064ef5e3291b4baca78702291d731b7e"
  }]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /nft/secondary/sold`

### 4.3.4 `GET /nft/secondary/trust` 委托列表

> **【实测】** 实测时间：2026-10-19，抓取端点：
> `GET /nft/secondary/trust?iid=2109928427009183745&pageNo=1&pageSize=3`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary_trust.json`。**可作为契约依据。**

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "orderId": "2111108168080752641",
    "iid": "2109928427009183745",
    "title": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "unitPrice": 400,
    "quantity": 1000,
    "amount": 400000,
    "payWay": 2,
    "createTime": "1792162384420",
    "completedQuantity": 0
  }]
}
```

> 实测时间：2026-10-19，抓取端点：`GET /nft/secondary/trust`
> 注：`amount = unitPrice * quantity`（实测 400 * 1000 = 400000），
> 源站已算好，前端展示时算的是 `amountFormat(unitPrice * quantity)`，二者数值一致。
> **`quantity` 是委托份数不是金额**，切勿按金额处理。

### 4.3.5 `GET /nft/secondary/apply` 求购列表

> **【实测】** 实测时间：2026-10-19，抓取端点：
> `GET /nft/secondary/apply?iid=2109928427009183745&pageNo=1&pageSize=3`（免登录）
> 原始响应存档：`.cache/source-probe/nft_secondary_apply.json`
> 实测结果：`{"status":true,"code":0,"message":null,"data":[]}` —— **该藏品当前无求购单，返回空数组**。
> 本地必须能正确处理空数组形态，不得报错。

---

## 4.9 钱包与充值（2026-07-18 实测）

| Method | Path | 功能 | 认证 | `data` 返回类型 | 源站实测 |
|--------|------|------|------|----------------|---------|
| GET | `/wallet/hfpay` | 汇付充值收银台 | 是 | **纯字符串（URL）** | 成功 |
| GET | `/wallet/yeepay` | 易宝充值收银台 | 是 | — | **`code:20000 系统错误`** |

**实测响应（`GET /wallet/hfpay`）**：

```json
{
  "status": true, "code": 0, "message": null,
  "data": "https://hfpay.cloudpnr.com/h5/?uuid=hfpwallet6666000243789213index1c9391eb-957a-4863-af07-4a1b50df7422"
}
```

**前端处理方式**（源站 `pages-my-wallet-index.CSHnvv6G.js` 实测）：

```js
const hfpay = () => request.get("/wallet/hfpay");
goHfpay() {
  hfpay().then(res => {
    if (res.status) window.location.href = res.data;  // data 直接就是 URL 字符串
  });
}
```

> **注意**：充值端点的 `data` **不是对象而是 URL 字符串**，
> 直接 `window.location.href = data`跳转。实现时不要按 `{url: "..."}` 解析。

**线上未上线的钱包端点**（实测确认）：

| 端点 | 返回 | 说明 |
|------|------|------|
| `GET /user/wallet` | `code:10203 未找到` | 钱包详情端点不存在 |
| `GET /wallet/transaction` | `code:10203 未找到` | 资金流水端点不存在 |
| `GET /wallet/log` | `code:10203 未找到` | 同上 |

> 钱包域**只有充值入口，没有余额查询和流水接口**。
> 余额通过 `GET /passport/account` 间接体现，钱包流水表 `wallet_transactions` 目前无源站数据支撑。

---

## 5. 订单与支付 `order`

> 控制器：`app\controller\Order`
> 源站契约：`order.D6d574Cp.js`

### 5.1 下单（6 种形态）

| Method | Path | 功能 | 认证 | 请求体 | 源站 |
|--------|------|------|------|---------|------|
| POST | `order/primary` | 一级市场购买 | 是 | `iid, quantity, payWay` | 是 |
| POST | `order/secondary` | 二级单条买入 | 是 | `nftId, payWay` | 是 |
| POST | `order/secondary/quick` | 快捷下单 | 是 | `iid, payWay` | 是 |
| POST | `order/secondary/batch` | 批量购买 | 是 | `iid, unitPrice, quantity, payWay:1` | 是 |
| POST | `order/secondary/apply` | 求购下单 | 是 | `iid, unitPrice, quantity` | 是 |
| POST | `order/secondary/trust` | 委托下单 | 是 | `iid, unitPrice, quantity` | 是 |

### 5.2 订单查询与取消

| Method | Path | 功能 | 认证 | 请求参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/order` | 订单列表 | 是 | `pageNo, pageSize, type, status` | 是 |
| GET | `/order/{orderId}` | 订单详情 | 是 | — | 是 |
| PUT | `/order/cancel` | 取消订单 | 是 | `orderId` 或 `id` | 是 **PUT 非 POST** |

### 5.3 支付

| Method | Path | 功能 | 认证 | 请求体 | 返回 | 源站 |
|--------|------|------|------|---------|------|---------|------|
| POST | `/order/pay/hfpay` | 汇付支付 | 是 | `orderId, payWay` | 第三方收银台 URL | 是 |
| POST | `/order/pay/yeepay` | 易宝支付 | 是 | `orderId, payWay` | 第三方收银台 URL | 是 |
| POST | `https://notify.rongchang.space/notify/applepay/order` | Apple Pay 下单 | 是 | 订单 + Apple Pay token | 支付结果 | 是 **外部域名，非本 API** |

### 5.5 订单 type / status 枚举（2026-07-18 实测自 `pages-my-order-index.omZ1ybKK.js`）

**type（7 种订单类型）**：

| type | 名称 | 该类型可选 status |
|------|------|------------------|
| 10 | 常规订单 | 0 / 2 / 3 |
| 20 | 市场订单 | 0 / 2 / 3 / **4** |
| 30 | 批量订单 | 0 / 2 / 3 |
| 40 | 委托订单 | 0 / **1** / 2 / 3 |
| 50 | 求购订单 | 0 / **1** / 2 / 3 |
| 60 | 转售订单 | 0 / 2 / 3 |
| 70 | 申购订单 | 0 / **1** / 2 / 3 |

**status 统一语义**：

| 值 | 语义 | 各类型文案 |
|----|------|-----------|
| `""` | 全部（不传或空串）| 全部 |
| 0 | 待付款 | 待付款 |
| 1 | 进行中 | 委托中 / 求购中 / 申购中 |
| 2 | 已完成 | 已完成 |
| 3 | 已取消 | 已取消 |
| 4 | 已退款 | **仅市场订单（type=20）有** |

> **注意**：源站订单列表页初始 query 为 `{pageNo:1, status:"", type:10}`，
> 三个 tab 的 `statusValues` 数组长度与 `statuses` 文案数组长度严格对应，改一个必须同时改另一个。

### 5.6 `GET /order` 响应（type=20 status=2）

> **【实测】** 实测时间：2026-07-19，抓取端点：
> `GET /order?pageNo=1&pageSize=10&type=20&status=2`
> 该端点需登录态，本环境无法复抓校验，故**不纳入** `verify_docs_against_source.py`
> 的自动比对范围（脚本会将其标记为 `UNREACHABLE`）。
> 字段名可信（源站 bundle 静态提取 + 当时的实抓），订单号等具体值属历史快照。

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "orderId": "2111082090104668161",
    "buyer": "2110255189743443969",
    "type": 20,
    "iid": "2109928427009183745",
    "title": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "unitPrice": 500,
    "quantity": 1,
    "amount": 500,
    "subject": null,
    "status": 2,
    "payWay": 1,
    "outTradeNo": null,
    "payTime": null,
    "isSnapUp": false,
    "createTime": "1792156166946",
    "endPayTime": "1792156346946",
    "items": [],
    "itemQuantity": 1
  }]
}
```

### 5.7 `GET /order/{orderId}` 详情（**items 才有明细**）

> **【实测】** 实测时间：2026-07-19，抓取端点：`GET /order/2111082090104668161`
> 需登录态，本环境无法复抓校验，故不纳入自动比对范围（脚本标记 `UNREACHABLE`）。
> 字段名可信；订单号与金额为历史快照值。

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "orderId": "2111082090104668161",
    "buyer": "2110255189743443969",
    "type": 20,
    "iid": "2109928427009183745",
    "title": "臻海蓝卡",
    "imageUrl": "https://h5.shengtai.cyou/media/2109928079460765697.png",
    "image": "media/2109928079460765697.png",
    "unitPrice": 500,
    "quantity": 1,
    "amount": 500,
    "subject": null,
    "status": 2,
    "payWay": 1,
    "outTradeNo": null,
    "payTime": null,
    "isSnapUp": false,
    "createTime": "1792156166946",
    "endPayTime": "1792156346946",
    "items": [{
      "id": "2111082090184359938",
      "orderId": "2111082090104668161",
      "nftId": "2110712935341678594",
      "token": 627752,
      "amount": 500,
      "createTime": "1792156166965"
    }],
    "itemQuantity": 1
  }
}
```

**列表 vs 详情字段不对称（必须复刻）**：

| 差异点 | `GET /order`（列表）| `GET /order/{id}`（详情）|
|--------|-------------------|------------------------|
| `items` | **恒为 `[]`** | 返回真实明细（含 `nftId`/`token`）|
| 顶层字段 | 20 个 | 21 个（与列表一致 + items 填充）|

> → 本地实现：**列表接口不 join `order_items` 表**，只在详情接口 join。
> 多写这一步就复刻出了源站没有的行为。

**实测数据要点**：

| 项| 实测值 |
|----|-------|
| 支付窗口 | `endPayTime - createTime = 180000ms`（**3 分钟**，超时自动取消）|
| `payTime` | 已完成订单（status=2）仍为 `null`——**源站未回填支付时间** |
| `outTradeNo` | `null`（余额支付无外部交易号）|
| `subject` | `null` |
| `isSnapUp` | `false` |
| 时间字段 | 全部为**字符串**形式的 13 位毫秒 |
| 金额字段 | **分**（`unitPrice:500` + `amount:500` 表示 5 元）|

**42 组组合实抓结论**：7 种 `type` × 6 种 `status` 共 42 组请求，仅 `type=20` 有数据（2 条，均 status=2）；
`type` 10/30/40/50/60/70 全部返回空数组（账号无余额，从未下单）。

### 5.4 `POST order/secondary` 完整请求示例

> **【结构示意】** 请求体结构取自源站 bundle 静态提取，未实抓验证（需登录态）。
> `nftId` 与 `deviceId` 为占位写法。

```json
{
  "nftId": "<NFT实例ID字符串>",
  "payWay": 1,
  "appid": "__UNI__22FD095",
  "version": "<客户端版本号>",
  "deviceId": "<设备指纹，客户端生成>",
  "deviceModel": "<设备型号>",
  "platform": "web"
}
```

**响应**：

> **【占位】** 需登录态，本环境无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": { "orderId": "2109929000000000001", "orderNo": "ST202607190001" }
}
```

### 5.5 下单流程（复刻时的实现顺序）

```
1. 校验登录（未登录 → 11111）
2. 校验二级密码（求购成交 / 快捷下单场景）
3. 创建订单 orders（status=pending_pay）
4. 扣减余额 / 生成支付单 order_payments
5. 返回订单号 → 前端跳转收银台（hfpay/yeepay）或直接完成
6. 支付回调 → status=paid → 生成 NFT / 转移归属 → 写 nft_trade_logs
```

---

## 6. 活动 `activity`

> 控制器：`app\controller\Activity`
> 源站契约：`activity.YNl1geKp.js`

### 6.1 签到

| Method | Path | 功能 | 认证 | 参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/activity/sign` | 签到状态 | 是 | — | 是 |
| POST | `/activity/sign` | 执行签到 | 是 | — | 是 |

> 注意 源站**没有** `/activity/sign/log` 端点。签到记录通过 `GET` 返回的 `records` 数组获取。

**`GET /activity/sign` 响应**：

> ⚠️ 以下为**占位示意，非源站实测**。原因：该端点需登录态，本环境无法完成源站登录。
> **不可作为契约依据。**
> 但以下**结构结论可信**（来自源站 bundle 静态提取的渲染逻辑 `records[day - 1]`）：
> `records` 是**布尔数组**，长度 = 当月天数，索引 = day-1，即 `records[0]` 表示 1 号。

> **【占位】** 需登录态，本环境无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "records": [false, false, true, false],  // 长度 = 当月天数，索引 = day-1（此处仅示意前 4 天）
    "signDays": 12
  }
}
```

> 前端渲染逻辑：`isSigned = records[day - 1]`。月份箭头在源站**未绑定事件**（死方法），复刻时照搬不绑。

### 6.2 积分与活动首页

| Method | Path | 功能 | 认证 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/activity/index` | 活动聚合首页（`rallyList`） | 否 | 是 |
| GET | `/activity/points/balance` | 积分余额 | 是 | 是 |

### 6.3 抽奖

| Method | Path | 功能 | 认证 | 参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/activity/lottery/active` | 当前抽奖活动 | 否 | — | 是 |
| GET | `/activity/lottery` | 抽奖列表 | 否 | `pageNo, pageSize` | 是 |
| GET | `/activity/lottery/{id}` | 抽奖详情（含 `prizeList`） | 否 | — | 是 |
| POST | `/activity/lottery/start/{id}` | **发起抽奖** | 是 | — | 是 **id 走路径** |
| GET | `/activity/lottery/log` | 抽奖记录 | 是 | `pageNo, pageSize` | 是 |

**`GET /activity/lottery/{id}` 响应**：

> ⚠️ 以下为**占位示意，非源站实测**。原因：源站当前无进行中的抽奖活动
> （实测 `GET /activity/lottery` 需登录态，且无可用活动 id）。
> **不可作为契约依据。**
> 已交叉确认可信的部分：`prizeList` 中 `probability` 为**小数概率**（0.01 = 1%），
> 源站按累计概率划分扇区，`id` 用于匹配抽奖结果。

> **【占位】** 源站无进行中的抽奖活动，无法实抓，**不可作为契约依据**。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "id": "<抽奖活动ID字符串>",
    "name": "<活动名称>",
    "points": 10,
    "cover": "<奖品图背景路径>",
    "rule": "<活动规则HTML文本>",
    "prizeList": [
      { "id": 1, "name": "一等奖", "image": "<奖品图路径>", "probability": 0.01, "quantity": 1 }
    ]
  }
}
```

**`POST /activity/lottery/start/{id}` 响应**：

> **【结构示意】** 需登录态 + 需真实进行中的抽奖活动，本环境无法实抓。
> 已交叉确认可信的部分：`data` 是**单个奖品对象**（不是数组），
> 前端按 `id` 匹配扇区。

> **【结构示意】** 需登录态且需真实进行中的抽奖活动，无法实抓。
```json
{
  "status": true, "code": 0, "message": null,
  "data": { "id": 2, "name": "二等奖" }   // 奖品对象，前端按 id 匹配扇区
}
```

**前端抽奖次数计算**（复刻必须 1:1）：

```js
drawTimes = Math.floor(pointsBalance / lotteryInfo.points)
```

**前端转盘角度计算**（复刻必须 1:1）：

```js
step = 360 / prizeList.length
target = 270 - (step * winPrizeIndex + step / 2)
// 累加 1080 度 + 360 的整数倍，动画 3000ms
rotateAngle = 360 * Math.floor(rotateAngle / 360) + 1080 + target
```

### 6.4 合成

| Method | Path | 功能 | 认证 | 参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/item/merge` | 合成列表 | 否 | `pageNo, pageSize` | 是 |
| GET | `/item/merge/{id}` | 合成详情 | 否 | — | 是 |
| POST | `/nft/merge/v1` | 执行合成 | 是 | `mid, nftIds[]` | 是 |
| GET | `/product_merge` | 合成活动列表 | 否 | `pageNo, pageSize` | 是 |
| GET | `/product_merge/record` | 合成记录 | 是 | `pageNo, pageSize` | 是 |

### 6.5 抽签（运势）/ 重铸

| Method | Path | 功能 | 认证 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/activity/recast/type` | 奖池类型 | 否 | 是 |
| GET | `/activity/recast/doll-type` | 玩法类型 | 否 | 是 |
| GET | `/activity/recast/draw-num` | 剩余抽取次数 | 是 | 是 |
| GET | `/activity/recast/prize` | 奖品列表 | 否 | 是 |
| POST | `/activity/recast/draw` | 发起抽签 | 是 | 是 |
| GET | `/activity/recast/log` | 抽签记录 | 是 | 是 |

### 6.6 其他

| Method | Path | 功能 | 认证 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/common/rule` | 通用规则 | 否 | 是 |
| POST | `/ai/answer/get_answer` | AI 助理问答 | 是 | 是 |
| GET | `/product_zh_activity/my_zh_list` | 我的数字藏品 | 是 | 是 |
| POST | `/product_zh_activity/my_zh` | 提交数字藏品 | 是 | 是 |

---

## 7. 基础内容 `basic`

> 控制器：`app\controller\Basic`
> 源站契约：`basic.Cs6D0UiA.js`

| Method | Path | 功能 | 认证 | 参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `app/version/check` | 版本检查 | 否 | `version, platform` | 是 |
| GET | `basic/banner` | 轮播图 | 否 | `position?` | 是 |
| GET | `basic/notice/category` | 公告分类 | 否 | — | 是 |
| GET | `basic/notice` | 公告列表 | 否 | `cid?, pageNo, pageSize` | 是 |
| GET | `basic/notice/{id}` | 公告详情 | 否 | — | 是 |

### 7.1 `GET /basic/notice/{id}` 响应（实测）

> ⚠️ **标题「（实测）」为笔误，实为结构示意**。原因：`GET /basic/notice/{id}` 需登录态，
> 本环境无法实抓，**不可作为契约依据**。字段名可信（源站 bundle 静态提取），字段值不可信。
```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "id": "<公告ID字符串>",
    "cid": "<分类ID字符串>",
    "title": "<公告标题>",
    "content": "<公告正文HTML文本>",
    "createTime": "<13位毫秒时间戳字符串>",
    "category": {
      "cid": "<分类ID字符串>",
      "name": "<分类名称>",
      "coverUrl": "<分类封面绝对地址>",
      "cover": "<分类封面相对路径>"
    }
  }
}
```

> ⚠️ 上方公告详情示例为**结构示意，非源站实测**。原因：`GET /notice/info` 需登录态，
> 本环境无法完成源站登录。**不可作为契约依据。**
> 其中 `category` 的 `coverUrl` / `cover` 双字段形态，已由免登录端点交叉确认可信
> （见 3.4 作者对象的同款「绝对+相对」配对）。

---

## 8. IM 群聊 `im`

| Method | Path | 功能 | 认证 | 参数 | 源站 |
|--------|------|------|------|---------|------|
| GET | `/im/group` | 群列表 | 是 | `pageNo, pageSize` | 是 |
| GET | `/im/group/` | 群详情 | 是 | `id` | 是 |
| POST | `/im/group/join/{id}` | 入群 | 是 | — | 是 |
| POST | `/im/group/leave/{id}` | 退群 | 是 | — | 是 |
| GET | `/im/group/message` | 群消息 | 是 | `gid, pageNo, pageSize` | 是 |
| GET | `/im/group/user/` | 群成员 | 是 | `gid, pageNo, pageSize` | 是 |
| GET | `/im/user/` | 单聊会话 | 是 | `uid, pageNo, pageSize` | 是 |

**`GET /im/group` 响应（实测）**：

> ⚠️ **标题「（实测）」为笔误，实为结构示意**。原因：`GET /im/group` 需登录态。
> **不可作为契约依据。** 字段名可信（源站 bundle 静态提取），字段值不可信。
```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "id": 1, "name": "", "status": "normal",
    "isJoin": 1, "isBlacklist": 0,
    "userTotal": 0, "blacklistTotal": 0
  }]
}
```

---

## 9. 文件上传

| Method | Path | 功能 | 认证 | 说明 | 源站 |
|--------|------|------|------|---------|------|
| POST | `user/profile/avatar` | 头像上传 | 是 | `multipart/form-data`，字段名 `file` | 是 |

---

## 10. ThinkPHP 6 路由定义

`route/app.php`：

```php
use think\facade\Route;

// 无需登录
Route::group(function () {
    // 账号
    Route::get('passport/captcha/image', 'Passport/captchaImage');
    Route::post('passport/captcha/sms', 'Passport/captchaSms');
    Route::post('passport/signup', 'Passport/signup');
    Route::post('passport/signin', 'Passport/signin');
    Route::post('passport/signin/quick', 'Passport/signinQuick');
    Route::post('passport/signin/oneClick', 'Passport/signinOneClick');

    // 藏品
    Route::get('item', 'Item/index');
    Route::get('item/category', 'Item/category');
    Route::get('item/author', 'Item/author');
    Route::get('item/copyright', 'Item/copyright');
    Route::get('item/merge', 'Item/mergeList');
    Route::get('item/merge/:id', 'Item/mergeDetail');
    Route::get('product/tp_category', 'Item/tpCategory');
    Route::get('product/tp_list', 'Item/tpList');

    // 市场
    Route::get('nft/secondary', 'Nft/secondary');
    Route::get('nft/secondary/:iid', 'Nft/secondaryDetail');
    Route::get('nft/secondary/sell', 'Nft/secondarySell');
    Route::get('nft/secondary/sold', 'Nft/secondarySold');
    Route::get('nft/secondary/apply', 'Nft/secondaryApply');
    Route::get('nft/secondary/trust', 'Nft/secondaryTrust');
    Route::get('nft/digitalAlbum/calendar', 'Nft/digitalAlbumCalendar');

    // 内容
    Route::get('basic/banner', 'Basic/banner');
    Route::get('basic/notice', 'Basic/noticeList');
    Route::get('basic/notice/category', 'Basic/noticeCategory');
    Route::get('basic/notice/:id', 'Basic/noticeDetail');
    Route::get('common/rule', 'Basic/rule');
    Route::get('app/version/check', 'Basic/versionCheck');

    // 活动（只读）
    Route::get('activity/index', 'Activity/index');
    Route::get('activity/lottery/active', 'Activity/lotteryActive');
    Route::get('activity/lottery', 'Activity/lotteryList');
    Route::get('activity/lottery/:id', 'Activity/lotteryDetail');
    Route::get('activity/lottery/log', 'Activity/lotteryLog');
    Route::get('activity/recast/type', 'Activity/recastType');
    Route::get('activity/recast/doll-type', 'Activity/recastDollType');
    Route::get('activity/recast/draw-num', 'Activity/recastDrawNum');
    Route::get('activity/recast/prize', 'Activity/recastPrize');
    Route::get('activity/recast/log', 'Activity/recastLog');
    Route::get('product_merge', 'Activity/mergeActivityList');
    Route::get('product_merge/record', 'Activity/mergeRecord');
});

// 需登录
Route::group(function () {
    // 账号
    Route::post('passport/signout', 'Passport/signout');
    Route::post('passport/delete', 'Passport/delete');
    Route::get('passport/account', 'Passport/account');
    Route::put('passport/password', 'Passport/updatePassword');
    Route::put('passport/phone', 'Passport/updatePhone');
    Route::put('passport/secondPassword', 'Passport/updateSecondPassword');
    Route::post('passport/realName', 'Passport/realName');
    Route::get('passport/online', 'Passport/online');
    Route::get('passport/online/:id', 'Passport/onlineDetail');
    Route::delete('passport/online/:id', 'Passport/onlineDelete');

    // 用户
    Route::get('user/profile', 'User/profile');
    Route::post('user/profile/avatar', 'User/updateAvatar');
    Route::put('user/profile/nickname', 'User/updateNickname');
    Route::put('user/profile/introduction', 'User/updateIntroduction');
    Route::get('user/invite', 'User/invite');
    Route::get('user/resource', 'User/resource');

    // 关注
    Route::post('item/follow/:iid', 'Item/follow');
    Route::delete('item/unfollow/:iid', 'Item/unfollow');

    // NFT
    Route::get('nft', 'Nft/index');
    Route::get('nft/group', 'Nft/group');
    Route::get('nft/:nftId', 'Nft/detail');
    Route::post('nft/box/open', 'Nft/openBox');
    Route::post('nft/sell/up', 'Nft/sellUp');
    Route::post('nft/sell/up/batch', 'Nft/sellUpBatch');
    Route::put('nft/sell/down', 'Nft/sellDown');
    Route::get('nft/sell/sold', 'Nft/sellSold');
    Route::post('nft/sell/apply', 'Nft/sellApply');
    Route::get('nft/secondary/follow', 'Nft/secondaryFollow');

    // 订单
    Route::post('order/primary', 'Order/primary');
    Route::post('order/secondary', 'Order/secondary');
    Route::post('order/secondary/quick', 'Order/secondaryQuick');
    Route::post('order/secondary/batch', 'Order/secondaryBatch');
    Route::post('order/secondary/apply', 'Order/secondaryApply');
    Route::post('order/secondary/trust', 'Order/secondaryTrust');
    Route::get('order', 'Order/index');
    Route::get('order/:id', 'Order/detail');
    Route::put('order/cancel', 'Order/cancel');
    Route::post('order/pay/hfpay', 'Order/payHfpay');
    Route::post('order/pay/yeepay', 'Order/payYeepay');

    // 活动（写）
    Route::get('activity/sign', 'Activity/signStatus');
    Route::post('activity/sign', 'Activity/sign');
    Route::get('activity/points/balance', 'Activity/pointsBalance');
    Route::post('activity/lottery/start/:id', 'Activity/lotteryStart');
    Route::post('activity/recast/draw', 'Activity/recastDraw');
    Route::post('nft/merge/v1', 'Nft/merge');
    Route::post('ai/answer/get_answer', 'Ai/answer');
    Route::get('product_zh_activity/my_zh', 'ZhActivity/myZh');
    Route::get('product_zh_activity/my_zh_list', 'ZhActivity/myZhList');
    Route::post('product_zh_activity/my_zh', 'ZhActivity/submit');

    // IM
    Route::get('im/group', 'Im/groupList');
    Route::get('im/group/', 'Im/groupDetail');
    Route::post('im/group/join/:id', 'Im/join');
    Route::post('im/group/leave/:id', 'Im/leave');
    Route::get('im/group/message', 'Im/messages');
    Route::get('im/group/user/', 'Im/members');
    Route::get('im/user/', 'Im/sessions');
})->middleware('auth');
```

### 10.1 统一响应中间件

`app\middleware\ResponseMiddleware`：

```php
public function handle($request, \Closure $next)
{
    try {
        $data = $next($request);
        return json(['status' => true, 'code' => 0, 'message' => null, 'data' => $data]);
    } catch (ApiException $e) {
        return json(['status' => false, 'code' => $e->getCode(), 'message' => $e->getMessage(), 'data' => null]);
    }
}
```

### 10.2 登录中间件

`app\middleware\AuthMiddleware`：无 `Authorization` 头或 Token 失效 → `throw new ApiException('请先登录', 11111)`。

---

## 11. ThinkPHP 6 Controller 基类

```php
namespace app\controller;

use think\App;
use think\Response;

abstract class Base
{
    protected App $app;
    protected \think\Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $app->request;
    }

    /** 当前登录用户 ID，未登录抛 11111 */
    protected function uid(): string
    {
        $uid = $this->request->middleware('auth_uid');
        if (empty($uid)) {
            throw new ApiException('请先登录', 11111);
        }
        return $uid;
    }
}
```

---

## 12. 接口总览统计

| 模块 | 接口数 |
|------|-------|
| 账号与鉴权 `passport` | 16 |
| 用户 `user` | 6 |
| 藏品 `item` + `product` | 9 |
| NFT 资产与仓库 `nft` | 17 |
| 订单与支付 `order` | 11 |
| 活动 `activity` + 合成 + AI | 22 |
| 基础内容 `basic` + `common` | 5 |
| IM | 7 |
| **合计** | **93** |

---

## 13. 待确认接口（源站存在但行为异常）

| Path | 源站实测 | 复刻处理 |
|------|---------|---------|
| `/api/author/detail` | 恒 `10203 未找到` | 照搬 mock 兜底 |
| `/nft/digitalAlbum/calendar` | `10203` | 保留端点，返回空日历 |
| `/user/resource` | `[]` | 保留端点，返回空资源列表 |
| `/activity/lottery/active` | `data: null` | 无活动时 `data: null`，前端 toast「敬请期待」 |

---

## 14. 变更记录

| 日期 | 变更 | 原因 |
|------|------|------|
| 2026-07-19 | 首版，93 个接口 | 基于源站 6 个分包契约 + 30 页运行时抓取 |
