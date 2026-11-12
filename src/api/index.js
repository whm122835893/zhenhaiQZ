/**
 * C 端 API 层 —— BuildAdmin（server/buildadmin）契约适配
 *
 * 契约主权移交：本文件是页面与后端之间的唯一翻译层。
 *   - 页面侧保持原站契约不变（{status,code,message,data}、金额分、pageNo 分页）
 *   - 后端侧为 BuildAdmin 原生形态（{code,msg,time,data}、金额元、page/limit 分页、
 *     /api/{蛇形控制器}/{action}、ba-user-token 认证头）
 *
 * 原则：白名单映射 + 未知字段透传；缺口功能统一 stub 兜底（页面空态自洽）。
 */
import { http, stub, img } from './request'

/* ==================== 通用工具 ==================== */

/** 元 → 分（C 端展示层统一按分处理，除 100 换算元） */
function yuan2fen(v) {
  const n = Number(v)
  if (isNaN(n)) return 0
  return Math.round(n * 100)
}

/** 秒 → 毫秒（原站时间字段一律毫秒时间戳） */
function ms(sec) {
  const n = Number(sec)
  if (!n || isNaN(n)) return 0
  return n * 1000
}

/** 分页参数：pageNo/pageSize → page/limit */
function pageOf(params = {}, defaultLimit = 10) {
  const q = { page: Number(params.pageNo) || 1, limit: Number(params.pageSize) || defaultLimit }
  return q
}

/** stub：业务未开放（页面自行兜底，不弹全局错误） */
function stubFail(message = '功能暂未开放') {
  return Promise.resolve({ status: false, code: -1, message, msg: message, data: null })
}

/** stub：成功空数据 */
const stubEmpty = () => stub([])
const stubNull = () => stub(null)

/* ==================== 账号 / 鉴权 ==================== */

export const passportApi = {
  /** 图形验证码：BuildAdmin 验证码组件未启用 → stub 空图（仅短信流程用到） */
  getImageCaptcha: () => stub({ key: '', url: '' }),
  /** 短信验证码：缺口功能 */
  getSmsCaptcha: () => stubFail('短信登录暂未开放'),
  /** 注册：account→username；inviteCode→invite_code */
  signup: (data) =>
    http.post('/nft_auth/register', {
      username: data.account,
      password: data.password,
      invite_code: data.inviteCode || '',
    }),
  /** 登录（明文密码，RSA 已弃用）→ {token, userInfo} */
  signin: (data) =>
    http.post('/nft_auth/login', { username: data.account, password: data.password }),
  /** 快捷登录 / 一键登录：缺口功能 */
  quickSignin: () => stubFail('请使用账号密码登录'),
  oneClickSignin: () => stubFail('请使用账号密码登录'),
  /** 退出登录：BuildAdmin 原生接口 */
  signout: () => http.post('/user/logout'),
  /** 注销账号：演示环境直接本地登出 */
  deleteAccount: () => stubNull(),
  /** 账号信息（MyPage 用 uid）：从 profile 折叠 */
  getAccount: () =>
    http.get('/nft_auth/profile').then((res) => {
      if (!res.status) return res
      const u = res.data?.userInfo || {}
      return { ...res, data: { uid: String(u.id), phone: u.username, nickname: u.nickname } }
    }),
  /** 改密 / 换绑 / 二级密码 / 实名：缺口功能 */
  updatePassword: () => stubFail('改密功能暂未开放'),
  updatePhone: () => stubFail('换绑手机暂未开放'),
  updateSecondPassword: () => stubFail('二级密码暂未开放'),
  setRealName: () => stubFail('实名认证暂未开放'),
  /** 在线设备列表：缺口功能 */
  listOnline: () => stubEmpty(),
  getOnline: () => stubNull(),
  deleteOnline: () => stubNull(),
}

/** 账号密码登录（BuildAdmin 收明文密码，不再做 RSA 加密） */
export function signinWithPassword(phone, password) {
  return passportApi.signin({ account: phone, password })
}

/* ==================== 用户 ==================== */

export const userApi = {
  /** 个人资料：userInfo → {id, uid, phone, nickname, avatar} */
  getProfile: () =>
    http.get('/nft_auth/profile').then((res) => {
      if (!res.status) return res
      const u = res.data?.userInfo || {}
      return {
        ...res,
        data: {
          ...u,
          id: String(u.id),
          uid: String(u.id),
          phone: u.username || '',
          nickname: u.nickname || '',
          avatar: img(u.avatar),
        },
      }
    }),
  /** 头像上传：BuildAdmin Ajax.upload → 回填 full_url */
  updateAvatar: (formData) =>
    http.post('/ajax/upload', formData).then((res) => {
      if (!res.status) return res
      return { ...res, data: res.data?.full_url || '' }
    }),
  /** 昵称 / 简介：BuildAdmin 无对应开放接口 → stub */
  updateNickname: () => stubFail('昵称修改暂未开放'),
  updateIntroduction: () => stubFail('简介修改暂未开放'),
  /** 邀请记录：BuildAdmin 真实接口 */
  listInvite: (params) => http.get('/nft_invite/index', pageOf(params, 10)),
  /** 权限资源：批量寄售按钮依赖该列表，stub 空数组即隐藏入口 */
  listResource: () => stubEmpty(),
}

/* ==================== 藏品 / 作者 / 分类 ==================== */

/** 藏品（collection）→ 原站 item 形状 */
function mapItem(col) {
  return {
    ...col,
    iid: String(col.id),
    name: col.name,
    imageUrl: img(col.cover),
    img: img(col.cover),
    /** 元 → 分 */
    price: yuan2fen(col.price),
    issueQuantity: col.stock,
    remaining: col.remaining,
    category: { name: '' },
  }
}

/** 藏品详情：detail 接口 → InfoPage 需要的完整 item 形状 */
function mapItemDetail(col) {
  const remaining = Number(col.remaining) || 0
  return {
    ...mapItem(col),
    content: col.detail || '',
    /** 开售倒计时（毫秒）；已结束 → 立即购买 */
    buyTime: ms(col.sale_time),
    isSoldOut: !col.is_on_sale || remaining <= 0,
    author: {
      name: col.author || col.publisher || '臻海艺术',
      nickname: col.author || col.publisher || '臻海艺术',
      avatarUrl: '',
      intro: '',
    },
    resale: col.resale,
    resalePriceMin: yuan2fen(col.resale_price_min || 0),
    resalePriceLimit: yuan2fen(col.resale_price_limit || 0),
  }
}

export const itemApi = {
  /** 发售中列表：data.list 直接返回数组（IndexPage 判 Array.isArray） */
  list: (params) =>
    http.get('/nft_collection/index', pageOf(params, 20)).then((res) => {
      if (!res.status) return res
      return { ...res, data: (res.data?.list || []).map(mapItem) }
    }),
  /** 藏品详情（InfoPage：GET /item/{iid} 形态） */
  get: (iid) =>
    http.get('/nft_collection/detail', { id: iid }).then((res) => {
      if (!res.status) return res
      return { ...res, data: mapItemDetail(res.data?.collection || {}) }
    }),
  detail: (params) => itemApi.get(params?.id ?? params?.iid),
  /** 别名：MarketBatch.vue 调 itemApi.getItem(iid) */
  getItem: (iid) => itemApi.get(iid),
  /** 一级市场下单：{iid, quantity} → {collection_id, num}；返回 orderId 字符串 */
  createPrimaryOrder: (data) =>
    http
      .post('/nft_collection/order', {
        collection_id: data.iid,
        num: Number(data.quantity) || 1,
      })
      .then((res) => {
        if (!res.status) return res
        return { ...res, data: res.data?.order_no ?? '' }
      }),
  /** 作者 / 分类 / 版权 / 关注：缺口功能 */
  author: () => stubEmpty(),
  category: () => stubEmpty(),
  copyright: () => stubEmpty(),
  follow: () => stubNull(),
  unfollow: () => stubNull(),
}

/* ==================== 基础（banner / 公告） ==================== */

export const basicApi = {
  /** 轮播：BuildAdmin NftContent.banner（server/buildadmin 唯一后端增量） */
  getBanner: () =>
    http.get('/nft_content/banner').then((res) => {
      if (!res.status) return res
      const list = (res.data || []).map((b) => ({ ...b, image: img(b.image) }))
      return { ...res, data: list }
    }),
  /** 公告分类：notice.category 枚举（1=公告,2=活动,3=系统） */
  listNoticeCategory: () => stub([{ cid: 1, name: '公告' }, { cid: 2, name: '活动' }, { cid: 3, name: '系统' }]),
  /** 公告列表：data 直接返回数组（createTime 已毫秒、category 为对象） */
  listNotice: (params = {}) => {
    const q = pageOf(params, 10)
    if (params.cid > 0) q.category = params.cid
    return http.get('/nft_content/notice', q)
  },
  /**
   * 公告详情 / 兼容列表：首页 IndexPage 调 getNotice({pageNo})，详情页调 getNotice(id)
   */
  getNotice: (idOrParams) => {
    if (idOrParams && typeof idOrParams === 'object') {
      const q = pageOf(idOrParams, 10)
      if (idOrParams.cid > 0) q.category = idOrParams.cid
      return http.get('/nft_content/notice', q)
    }
    return http.get('/nft_content/notice', { id: idOrParams })
  },
  getNoticeDetail: (params = {}) => http.get('/nft_content/notice', { id: params.id }),
}

/* ==================== 市场（marketApi：页面未直接消费，保持映射） ==================== */

export const marketApi = {
  list: (params) =>
    http.get('/nft_market/index', { ...pageOf(params, 20), collection_id: params?.iid || 0 }),
  detail: (params) => marketApi.list(params),
  need: () => stubEmpty(),
  trust: () => stubEmpty(),
  trustIndex: () => stubEmpty(),
  log: () => stubEmpty(),
}

/* ==================== 订单 ==================== */

/** houtai 订单状态 → 原站状态（0待支付 1进行中 2已取消 3已完成 4已退款） */
const ORDER_STATUS_MAP = { 0: 0, 1: 3, 2: 2, 3: 4 }

/** houtai 一级购订单 → 原站 order 形状 */
function mapOrder(row) {
  return {
    ...row,
    orderId: row.order_no,
    type: 10, // 常规订单（一级购）
    status: ORDER_STATUS_MAP[row.status] ?? row.status,
    title: row.collection_name || '',
    imageUrl: img(row.cover),
    /** 元 → 分 */
    amount: yuan2fen(row.amount),
    createTime: ms(row.createtime),
    payTime: ms(row.paytime),
    /** 待支付 15 分钟支付窗口（倒计时展示用） */
    endPayTime: ms(row.createtime) + 15 * 60 * 1000,
    itemQuantity: row.num,
    quantity: row.num,
    payTags: [],
    remark: '',
  }
}

/** 市场订单状态筛选：原站 status → houtai status（3已完成→1已支付，4已退款→3已退款） */
function orderStatusFilter(status) {
  if (status === '' || status === undefined || status === null) return ''
  return { 0: 0, 1: '', 2: 2, 3: 1, 4: 3 }[status] ?? ''
}

/** 已创建订单的内存缓存（市场购订单不在 orders 列表里，详情页兜底展示） */
const createdOrders = new Map()

async function fetchOrdersAndFind(orderNo) {
  const res = await http.get('/nft_collection/orders', { page: 1, limit: 100 })
  if (!res.status) return res
  const hit = (res.data?.list || []).find((o) => o.order_no === orderNo)
  if (!hit) return { ...res, status: false, data: null }
  return { ...res, data: mapOrder(hit) }
}

export const orderApi = {
  /**
   * 一级下单别名：InfoPage.vue 调 orderApi.createPrimaryOrder（复刻站既有断点，
   * 方法实际定义在 itemApi 上）——在适配层补别名，页面零改动。
   */
  createPrimaryOrder: (data) => itemApi.createPrimaryOrder(data),
  /** 订单列表：仅常规订单（type=10）走真实接口；其余类型 houtai 无对应数据 → 空 */
  list: (params = {}) => {
    const type = params.type ?? 10
    if (type !== 10) return stubEmpty()
    const q = pageOf(params, 20)
    const st = orderStatusFilter(params.status)
    if (st !== '') q.status = st
    return http.get('/nft_collection/orders', q).then((res) => {
      if (!res.status) return res
      return { ...res, data: (res.data?.list || []).map(mapOrder) }
    })
  },
  /** 订单详情：orders 列表按单号过滤；市场购订单走内存缓存兜底 */
  get: (orderId) =>
    fetchOrdersAndFind(orderId).then((res) => {
      if (res.status) return res
      const cached = createdOrders.get(String(orderId))
      if (cached) return { ...res, status: true, code: 0, data: cached }
      return { ...res, status: false, data: null }
    }),
  detail: (params = {}) => orderApi.get(params.orderId ?? params.id),
  /** 取消订单：houtai 无取消接口（仅模拟支付链路）→ stub 拒绝，按钮静默无副作用 */
  cancelOrder: () => stubFail('演示环境暂不支持取消订单'),
  cancel: () => stubFail('演示环境暂不支持取消订单'),
  /** 汇付支付：走 pay_mock 模拟支付；data 置 null → OrderInfo 原地刷新不跳转 */
  hfpayOrder: (data = {}) =>
    http.post('/nft_collection/pay_mock', { order_no: data.orderId }).then((res) => {
      if (!res.status) return res
      return { ...res, data: null }
    }),
  payHfpay: (data = {}) => orderApi.hfpayOrder(data),
  payYeepay: () => stubFail('演示环境仅支持模拟支付'),
  /* ---- 二级市场下单 ---- */
  /** 单条买入：{nftId} → nft_market/buy {id}；返回 orderId 字符串 */
  createSecondaryOrder: (data) =>
    http
      .post('/nft_market/buy', { id: data.nftId })
      .then((res) => {
        if (!res.status) return res
        const orderNo = res.data?.order_no ?? ''
        createdOrders.set(String(orderNo), {
          orderId: orderNo,
          type: 20,
          status: 3,
          title: '',
          imageUrl: '',
          amount: yuan2fen(res.data?.price),
          createTime: Date.now(),
          payTime: Date.now(),
        })
        return { ...res, data: orderNo }
      }),
  /** 快捷买入：取该藏品在售最低价单直接买入 */
  createSecondaryQuickOrder: (data) =>
    http.get('/nft_market/index', { collection_id: data.iid, page: 1, limit: 1 }).then((res) => {
      if (!res.status) return res
      const first = res.data?.list?.[0]
      if (!first) return { ...res, status: false, data: null }
      return orderApi.createSecondaryOrder({ nftId: first.id })
    }),
  /** 批量 / 求购 / 委托：缺口功能（对应列表均为空，入口不可达） */
  createSecondaryBatchOrder: () => stubFail('批量购买暂未开放'),
  createSecondaryNeedOrder: () => stubFail('求购暂未开放'),
  createSecondaryTrustOrder: () => stubFail('委托购买暂未开放'),
}

/* ==================== 钱包 ==================== */

export const walletApi = {
  /** 余额：balance_yuan/income_yuan/expense_yuan → 分 */
  index: () =>
    http.get('/nft_wallet/index').then((res) => {
      if (!res.status) return res
      const d = res.data || {}
      return {
        ...res,
        data: {
          ...d,
          balance: yuan2fen(d.balance_yuan),
          income: yuan2fen(d.income_yuan),
          expense: yuan2fen(d.expense_yuan),
        },
      }
    }),
  /** 充值：第三方收银台，缺口功能 */
  hfpay: () => stubFail('余额充值暂未开放'),
  yeepay: () => stubFail('余额充值暂未开放'),
}

/* ==================== NFT（持仓 / 二级市场） ==================== */

/** 持仓行 → 原站 nft 形状 */
function mapHold(row) {
  return {
    ...row,
    nftId: String(row.id),
    iid: String(row.collection_id),
    sn: row.sn,
    token: row.sn,
    name: row.name,
    imageUrl: img(row.cover),
    img: img(row.cover),
    /** 藏品原价（元 → 分） */
    price: yuan2fen(row.price),
    resale: row.resale,
    statusText: row.status_text,
    ownQuantity: 1,
    item: {
      id: String(row.collection_id),
      iid: String(row.collection_id),
      name: row.name,
      imageUrl: img(row.cover),
      issueQuantity: null,
      type: 1,
      price: yuan2fen(row.price),
      resale: row.resale,
    },
  }
}

/** 持仓详情（NftInfo / NftSale 页） */
function mapHoldDetail(hold) {
  const col = hold.collection || {}
  return {
    ...hold,
    nftId: hold.id,
    sn: hold.sn,
    token: hold.sn,
    statusText: hold.status_text,
    name: col.name || '',
    imageUrl: img(col.cover),
    item: {
      id: String(col.id),
      iid: String(col.id),
      name: col.name || '',
      imageUrl: img(col.cover),
      issueQuantity: null,
      content: '',
      type: 1,
      price: yuan2fen(col.price),
      resale: col.resale,
      resalePriceMin: yuan2fen(col.resale_price_min || 0),
      resalePriceLimit: yuan2fen(col.resale_price_limit || 0),
    },
  }
}

/** 按集合聚合持仓（MyPage group 列表） */
function aggregateHolds(rows) {
  const byCol = new Map()
  for (const r of rows) {
    const k = r.collection_id
    if (!byCol.has(k)) {
      byCol.set(k, {
        iid: String(k),
        nftId: String(k),
        name: r.name,
        imageUrl: img(r.cover),
        img: img(r.cover),
        ownQuantity: 0,
        unopenQty: 0,
        market_count: 0,
        is_merge: 0,
        minPrice: null,
        priceChange: null,
        tradeQuantity: 0,
        issueQuantity: null,
        nftQuantity: null,
      })
    }
    const g = byCol.get(k)
    if (Number(r.status) === 0) {
      g.ownQuantity += 1
      g.unopenQty += 1
    } else if (Number(r.status) === 1) {
      g.market_count += 1
    }
  }
  return [...byCol.values()]
}

export const nftApi = {
  /** 我的持仓（单件）：nft_hold/index?status=0；iid 在前端过滤 */
  list: (params = {}) =>
    http
      .get('/nft_hold/index', { ...pageOf(params, 50), status: params.status ?? 0 })
      .then((res) => {
        if (!res.status) return res
        let rows = res.data?.list || []
        if (params.iid) rows = rows.filter((r) => String(r.collection_id) === String(params.iid))
        return { ...res, data: rows.map(mapHold) }
      }),
  /** 持仓详情页 */
  getNft: (nftId) =>
    http.get('/nft_hold/detail', { id: nftId }).then((res) => {
      if (!res.status) return res
      return { ...res, data: mapHoldDetail(res.data || {}) }
    }),
  /** 按藏品聚合持仓（MyPage 主列表）：全量持仓 → 集合聚合 */
  group: (params = {}) => {
    const q = pageOf(params, 50)
    if (params.status !== undefined && params.status !== '') q.status = params.status
    return http.get('/nft_hold/index', q).then((res) => {
      if (!res.status) return res
      return { ...res, data: aggregateHolds(res.data?.list || []) }
    })
  },
  /** 开盲盒：缺口功能（盲盒藏品未接入） */
  openBox: () => stubFail('开盒暂未开放'),
  digitalAlbumCalendar: () => stubEmpty(),
  /* ---- 寄售 ---- */
  sellApply: () => stubFail('求购寄售暂未开放'),
  /** 单条寄售：⚠️ 页面发出 price 已 ×100（分），houtai 收元 → 这里 ÷100 */
  sellUp: (data) =>
    http.post('/nft_market/sell', {
      hold_id: data.nftId,
      price: Number(data.price) / 100,
    }),
  /** 批量寄售：缺口功能（资源列表为空，入口已隐藏） */
  sellUpBatch: () => stubFail('批量寄售暂未开放'),
  /** 下架：nftId → id */
  sellDown: (data) => http.post('/nft_market/cancel', { id: data.nftId }),
  sellDownPut: (data) => nftApi.sellDown(data),
  /** 已售记录：我的寄售单中 status=1（已售） */
  sellSold: (params = {}) =>
    http.get('/nft_market/mine', pageOf(params, 20)).then((res) => {
      if (!res.status) return res
      const rows = (res.data?.list || [])
        .filter((r) => Number(r.status) === 1)
        .map((r) => ({
          ...r,
          nftId: String(r.hold_id),
          iid: String(r.collection_id),
          name: r.collection_name,
          imageUrl: img(r.cover),
          img: img(r.cover),
          price: yuan2fen(r.price),
          soldTime: ms(r.soldtime),
          createTime: ms(r.createtime),
        }))
      return { ...res, data: rows }
    }),
  /* ---- 二级市场 ---- */
  /**
   * 市场聚合列表（MarketPage）：藏品发售列表 × 在售寄售单按集合聚合。
   * sellQuantity=0 的集合被页面的「已售罄过滤」兜底隐藏。
   */
  secondary: (params = {}) =>
    Promise.all([
      http.get('/nft_collection/index', { page: 1, limit: 100 }),
      http.get('/nft_market/index', {
        page: 1,
        limit: 100,
        collection_id: params.collection_id || 0,
      }),
    ]).then(([colRes, mkRes]) => {
      if (!colRes.status) return colRes
      const cols = colRes.data?.list || []
      const orders = mkRes.status ? mkRes.data?.list || [] : []
      const byCol = new Map()
      for (const o of orders) {
        const k = o.collection_id
        if (!byCol.has(k)) byCol.set(k, { count: 0, min: null, max: null })
        const g = byCol.get(k)
        g.count += 1
        const p = Number(o.price) || 0
        g.min = g.min === null ? p : Math.min(g.min, p)
        g.max = g.max === null ? p : Math.max(g.max, p)
      }
      let rows = cols.map((c) => {
        const g = byCol.get(c.id) || { count: 0, min: null, max: null }
        return {
          iid: String(c.id),
          name: c.name,
          imageUrl: img(c.cover),
          img: img(c.cover),
          issueQuantity: c.stock,
          nftQuantity: c.sold,
          tradeQuantity: 0,
          sellQuantity: g.count,
          minPrice: g.min === null ? null : yuan2fen(g.min),
          secondaryHighPrice: g.max === null ? null : yuan2fen(g.max),
          priceChange: null,
          isFollow: false,
          cid: c.category_id || 0,
          floorPriceText: g.min === null ? null : yuan2fen(g.min),
        }
      })
      if (params.cid) rows = rows.filter((r) => String(r.cid) === String(params.cid) || !r.cid)
      return { ...colRes, data: rows }
    }),
  /** 市场详情页（MarketInfo 单藏品聚合） */
  getMarket: (iid) =>
    Promise.all([
      http.get('/nft_collection/detail', { id: iid }),
      http.get('/nft_market/index', { collection_id: iid, page: 1, limit: 100 }),
    ]).then(([colRes, mkRes]) => {
      if (!colRes.status) return colRes
      const col = colRes.data?.collection || {}
      const orders = mkRes.status ? mkRes.data?.list || [] : []
      const prices = orders.map((o) => Number(o.price) || 0)
      return {
        ...colRes,
        data: {
          ...mapItem(col),
          authorName: col.author || col.publisher || '',
          issueQuantity: col.stock,
          nftQuantity: col.sold,
          sellQuantity: orders.length,
          minPrice: prices.length ? yuan2fen(Math.min(...prices)) : null,
          secondaryHighPrice: prices.length ? yuan2fen(Math.max(...prices)) : null,
          priceChange: null,
          tradeQuantity: 0,
        },
      }
    }),
  /** 在售寄售单（MarketInfo 寄售 tab）：nftId=寄售单ID（buy 用） */
  secondarySellList: (params = {}) =>
    http
      .get('/nft_market/index', { ...pageOf(params, 20), collection_id: params.iid || 0 })
      .then((res) => {
        if (!res.status) return res
        const rows = (res.data?.list || []).map((r) => ({
          ...r,
          nftId: String(r.id),
          iid: String(r.collection_id),
          name: r.collection_name,
          imageUrl: img(r.cover),
          img: img(r.cover),
          price: yuan2fen(r.price),
          token: '',
          seller: r.seller,
        }))
        return { ...res, data: rows }
      }),
  /** 求购 / 委托 / 成交记录 / 关注：缺口功能 */
  secondarySoldList: () => stubEmpty(),
  secondaryApplyList: () => stubEmpty(),
  secondaryTrustList: () => stubEmpty(),
  secondaryFollow: () => stubEmpty(),
  /** 市场成交记录（MarketLog）：缺口功能 */
  listMarketSoldLog: () => stubEmpty(),
  /** 委托列表（MarketTrustIndex）：缺口功能 */
  listMarketTrust: () => stubEmpty(),
}

/* ==================== 活动 ==================== */

export const activityApi = {
  index: () => stubNull(),
  /** 签到：BuildAdmin 真实接口 */
  getSign: () => http.get('/nft_checkin/config'),
  sign: () => http.post('/nft_checkin/sign'),
  /** 积分：缺口功能 */
  pointsBalance: () => stub({ balance: 0 }),
  /* ---- 抽奖 ---- */
  getLotteryActive: () => http.get('/nft_lottery/index'),
  lotteryList: (params) => http.get('/nft_lottery/index', pageOf(params, 10)),
  getLottery: (id) => http.get('/nft_lottery/detail', { id }),
  startLottery: (id) => http.post('/nft_lottery/draw', { id }),
  listLotteryLog: (params) => http.get('/nft_lottery/records', pageOf(params, 10)),
  /* ---- 合成 / 抽签 / AI / 数字日历：缺口功能 ---- */
  listMerge: () => stubEmpty(),
  getMerge: () => stubNull(),
  mergeV1: () => stubFail('合成暂未开放'),
  mergeRecord: () => stubEmpty(),
  recastType: () => stubEmpty(),
  recastDollType: () => stubEmpty(),
  recastDraw: () => stubFail('抽签暂未开放'),
  recastDrawNum: () => stub(0),
  recastLog: () => stubEmpty(),
  recastPrize: () => stubEmpty(),
  rule: () => stub(''),
  aiAnswer: () => stubFail('AI 助理暂未开放'),
  myZh: () => stubNull(),
  myZhList: () => stubEmpty(),
}

/* ==================== IM 聊天 ==================== */

export const imApi = {
  groupList: () => stubEmpty(),
  groupDetail: () => stubNull(),
  groupJoin: () => stubFail('社区暂未开放'),
  groupLeave: () => stubNull(),
  groupMessage: () => stubEmpty(),
  groupUser: () => stubEmpty(),
  user: () => stubNull(),
}

/* ==================== 作者 ====================
 * 与原站行为一致：detail 恒返回 {status:false, code:10203}，
 * AuthorDetail.vue 命中自带的 mock 兜底（name/avatar/intro）。
 */
export const authorApi = {
  list: () => stubEmpty(),
  detail: () => Promise.resolve({ status: false, code: 10203, message: '未找到', msg: '未找到', data: null }),
}


