# 盛泰数字藏品平台 · API 接口文档

> 版本：v1.0
> 生成日期：2026-07-19
> Base URL：`https://api.shengtai.cyou`（源站）→ 复刻后自建域名
> 框架：ThinkPHP 6
> 数据来源：源站 6 个分包模块契约（`item` / `nft` / `user` / `order` / `activity` / `basic`）+ 30 页运行时抓取交叉验证

---

## 0. 通用约定

### 0.1 响应包络

**所有接口**统一返回：

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

```json
{ "list": [], "total": 0 }
```

### 0.7 数据类型约定

| 源站类型 | 说明 | 约定 |
|---------|------|------|
| 主键 | 19 位雪花 ID | **字符串**返回（超 JS `Number` 安全范围） |
| 时间 | 13 位毫秒 | **字符串**返回，如 `"1791959018537"` |
| 金额 | 分 | 整数返回，`/100` 得元 |
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

```json
{
  "account": "17587881293",
  "password": "RSA(明文密码) Base64",
  "appid": "__UNI__22FD095",
  "version": "1.3.2",
  "deviceId": "xxx",
  "deviceModel": "PC",
  "platform": "web"
}
```

### 1.2 `GET /passport/account` 响应（实测抓取）

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "uid": "2109922045660860418",
    "phone": "175****1293",
    "email": null,
    "account": "175****1293",
    "createTime": "1791959018537",
    "hasPassword": true,
    "hasSecondPassword": false,
    "hasRealName": true,
    "realName": "王*绵",
    "idCard": "5202****9838"
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

### 2.1 `GET /user/profile` 响应（实测抓取）

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "uid": "2109922045660860418",
    "avatarUrl": "https://.../avatar.png",
    "avatar": "/static/images/logo.png",
    "nickname": "微信用户",
    "sex": 0,
    "birthday": null,
    "country": null,
    "regionCode": null,
    "introduction": "",
    "chainAddress": null,
    "inviteCode": "XXXXXXXX",
    "inviter": null,
    "createTime": "1791959018537",
    "token": "5f2c1a..."
  }
}
```

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

### 3.1 `GET /item/{iid}` 响应（实测抓取，全字段）

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "iid": "2109928427009183745",
    "cid": 1,
    "authorId": "2109922045660860418",
    "contract": "0x1234...abcd",
    "name": "臻海艺术藏品",
    "imageUrl": "https://cdn.../item.png",
    "image": "/static/images/item.png",
    "content": "<p>...</p>",
    "price": 38800,
    "type": "normal",
    "issueQuantity": 888888,
    "buyQuantity": 1,
    "preStartTime": "1791959018537",
    "preEndTime": "1792045418537",
    "startTime": "1792045418537",
    "endTime": "1794474218537",
    "isPrimary": 1,
    "isPrimaryApply": 1,
    "isRecommend": 1,
    "isPopular": 1,
    "isGive": 1,
    "isResale": 1,
    "isSecondary": 1,
    "isSecondaryBatch": 1,
    "isSecondaryApply": 1,
    "isSecondaryTrust": 1,
    "secondaryLowPrice": 100000,
    "secondaryHighPrice": 388000,
    "boxOpenTime": null,
    "isSoldOut": 0,
    "nftQuantity": 2500
  }
}
```

> `price: 38800` = 388.00 元（单位分）。

### 3.2 作者详情（源站未上线）

| Method | Path | 说明 | 源站行为 |
|--------|------|------|---------|
| GET | `/api/author/detail` | 作者详情 | 线上**恒返回** `{status:false, code:10203, message:"未找到"}` |

源站前端走 mock 兜底：`name="臻海艺术"`、`avatar=/static/images/logo.png`、`works` 为 mock 三图。复刻时**照搬此 mock 兜底行为**。

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

### 4.1 `GET /nft/group` 响应（实测，仓库行式列表）

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "iid": "2109928427009183745",
    "name": "臻海艺术藏品",
    "imageUrl": "https://cdn.../item.png",
    "image": "/static/images/item.png",
    "type": "normal",
    "ownQuantity": 1,
    "minPrice": 100000,
    "priceChange": "0.0123"
  }]
}
```

### 4.2 `GET /nft/{nftId}` 响应（实测）

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "nftId": "2109928427009183999",
    "uid": "2109922045660860418",
    "iid": "2109928427009183745",
    "name": "臻海艺术藏品 #001",
    "imageUrl": "https://cdn.../nft.png",
    "image": "/static/images/nft.png",
    "token": "564851",
    "status": 0,
    "getTime": "1791959018537",
    "price": 100000,
    "payLock": 0,
    "payWay": 1,
    "chainId": "0xabc...",
    "chainCompleted": 1,
    "buyPrice": 38800,
    "item": { /* 嵌套完整 item 对象，结构同 3.1 */ }
  }
}
```

### 4.3 `GET /nft/secondary` 响应（实测，二级市场核心数据）

```json
{
  "status": true, "code": 0, "message": null,
  "data": [{
    "iid": "2109928427009183745",
    "contract": "0x1234...abcd",
    "name": "臻海艺术藏品",
    "imageUrl": "https://cdn.../item.png",
    "image": "/static/images/item.png",
    "content": "...",
    "type": "normal",
    "issueQuantity": 2500,
    "isGive": 1,
    "isSecondary": 1,
    "isSecondaryBatch": 1,
    "isSecondaryApply": 1,
    "isSecondaryTrust": 1,
    "secondaryLowPrice": 100000,
    "secondaryHighPrice": 388000,
    "secondaryApplyLowPrice": 90000,
    "secondaryApplyHighPrice": 380000,
    "nftQuantity": 1484,
    "sellQuantity": 120,
    "minPrice": 100000,
    "maxPrice": 388000,
    "tradeQuantity": 590,
    "tradeAmount": 58800000,
    "lastTradeAmount": 120000,
    "isFollow": 0,
    "todayTradeQuantity": 12,
    "hourTradeQuantity": 3,
    "todayTradeAmount": 1257000,
    "todayTradeMaxAmount": 150000,
    "todayTradeMinAmount": 100000,
    "priceChange": "0.0234"
  }]
}
```

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

### 5.6 `GET /order` 实测响应（type=20 status=2，2 条真实订单）

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

### 5.7 `GET /order/{orderId}` 详情实测（**items 才有明细**）

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

```json
{
  "nftId": "2109928427009183999",
  "payWay": 1,
  "appid": "__UNI__22FD095",
  "version": "1.3.2",
  "deviceId": "xxx",
  "deviceModel": "PC",
  "platform": "web"
}
```

**响应**：

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

**`GET /activity/sign` 响应（实测）**：

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "records": [false, false, true, false, ...],  // 长度 = 当月天数，索引 = day-1
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

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "id": 2109929000000000001,
    "name": "幸运抽奖",
    "points": 10,
    "cover": "/static/images/lottery/nameBg.png",
    "rule": "<p>活动规则...</p>",
    "prizeList": [
      { "id": 1, "name": "一等奖", "image": "...", "probability": 0.01, "quantity": 1 }
    ]
  }
}
```

**`POST /activity/lottery/start/{id}` 响应**：

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

```json
{
  "status": true, "code": 0, "message": null,
  "data": {
    "id": "2109929100000000001",
    "cid": 1,
    "title": "平台公告",
    "content": "<p>...</p>",
    "createTime": "1791959018537",
    "category": {
      "cid": 1,
      "name": "平台公告",
      "coverUrl": "https://.../cover.png",
      "cover": "/static/images/cover.png"
    }
  }
}
```

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
