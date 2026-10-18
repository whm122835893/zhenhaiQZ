import { http } from './request'
import { encryptPassword } from '@/utils/crypto'

/* ==================== 账号 / 鉴权 ==================== */
export const passportApi = {
  getImageCaptcha: (params) => http.get('/passport/captcha/image', params),
  getSmsCaptcha: (data) => http.post('/passport/captcha/sms', data),
  signup: (data) => http.post('/passport/signup', data),
  signin: (data) => http.post('/passport/signin', data),
  quickSignin: (data) => http.post('/passport/signin/quick', data),
  oneClickSignin: (data) => http.post('/passport/signin/oneClick', data),
  signout: () => http.post('/passport/signout'),
  deleteAccount: () => http.post('/passport/delete'),
  getAccount: () => http.get('/passport/account'),
  updatePassword: (data) => http.put('/passport/password', data),
  updatePhone: (data) => http.put('/passport/phone', data),
  updateSecondPassword: (data) => http.put('/passport/secondPassword', data),
  setRealName: (data) => http.post('/passport/realName', data),
  listOnline: (params) => http.get('/passport/online', params),
  getOnline: (id) => http.get('/passport/online/' + id),
  deleteOnline: (id) => http.delete('/passport/online/' + id),
}

/** 账号密码登录（密码需 RSA 加密，与原站一致） */
export function signinWithPassword(phone, password) {
  return passportApi.signin({ account: phone, password: encryptPassword(password) })
}

/* ==================== 用户 ==================== */
export const userApi = {
  getProfile: () => http.get('user/profile'),
  updateAvatar: (formData) =>
    requestUpload('user/profile/avatar', formData),
  updateNickname: (data) => http.put('user/profile/nickname', data),
  updateIntroduction: (data) => http.put('user/profile/introduction', data),
  listInvite: (params) => http.get('/user/invite', params),
  listResource: () => http.get('/user/resource'),
}

/* ==================== 藏品 / 作者 / 分类 ==================== */
export const itemApi = {
  list: (params) => http.get('/item', params),
  /** 原站 item.Co0ZK8r3.js：GET /item/{iid} 取单个藏品（非 /item/detail） */
  get: (iid) => http.get(`/item/${iid}`),
  detail: (params) => http.get('/item/detail', params),
  /** 一级市场下单（原站 order.D6d574Cp.js：POST order/primary） */
  createPrimaryOrder: (data) => http.post('order/primary', data),
  author: (params) => http.get('/item/author', params),
  category: () => http.get('/item/category'),
  follow: (iid) => http.post(`/item/follow/${iid}`),
  unfollow: (iid) => http.delete(`/item/unfollow/${iid}`),
  copyright: (params) => http.get('/item/copyright', params),
}

/* ==================== 基础（banner / 公告） ==================== */
export const basicApi = {
  getBanner: () => http.get('/basic/banner'),
  listNoticeCategory: () => http.get('/basic/notice/category'),
  listNotice: (params) => http.get('/basic/notice', params),
  getNotice: (id) => http.get(`/basic/notice/${id}`),
  getNoticeDetail: (params) => http.get('/basic/notice/detail', params),
}

/* ==================== 市场 ==================== */
export const marketApi = {
  list: (params) => http.get('/market', params),
  detail: (params) => http.get('/market/detail', params),
  need: (params) => http.get('/market/need', params),
  trust: (params) => http.get('/market/trust', params),
  trustIndex: () => http.get('/market/trust/index'),
  log: (params) => http.get('/market/log', params),
}

/* ==================== 订单 ==================== */
export const orderApi = {
  list: (params) => http.get('/order', params),
  /** 原站 order.D6d574Cp.js：GET /order/{orderId} 取订单详情 */
  get: (orderId) => http.get(`/order/${orderId}`),
  detail: (params) => http.get('/order/detail', params),
  /** 原站：PUT /order/cancel（非 POST） */
  cancelOrder: (data) => http.put('/order/cancel', data),
  cancel: (data) => http.put('/order/cancel', data),
  /** 汇付支付：返回 data 为第三方收银台 URL */
  hfpayOrder: (data) => http.post('/order/pay/hfpay', data),
  payHfpay: (data) => http.post('/order/pay/hfpay', data),
  payYeepay: (data) => http.post('/order/pay/yeepay', data),
  /* ---- 二级市场 5 种下单形态（原站 order.D6d574Cp.js）---- */
  /** 单条买入：POST order/secondary {nftId} */
  createSecondaryOrder: (data) => http.post('/order/secondary', data),
  /** 快捷下单：POST order/secondary/quick {iid} */
  createSecondaryQuickOrder: (data) => http.post('/order/secondary/quick', data),
  /** 批量购买：POST order/secondary/batch {iid, unitPrice, quantity, payWay:1} */
  createSecondaryBatchOrder: (data) => http.post('/order/secondary/batch', data),
  /** 求购：POST order/secondary/apply {iid, unitPrice, quantity} */
  createSecondaryNeedOrder: (data) => http.post('/order/secondary/apply', data),
  /** 委托：POST order/secondary/trust {iid, unitPrice, quantity} */
  createSecondaryTrustOrder: (data) => http.post('/order/secondary/trust', data),
}

/* ==================== 钱包 ==================== */
export const walletApi = {
  index: () => http.get('/wallet'),
  hfpay: () => http.get('/wallet/hfpay'),
  yeepay: () => http.get('/wallet/yeepay'),
}

/* ==================== NFT ==================== */
export const nftApi = {
  /** 原站 nft.DGyngtlj.js listNft：GET /nft  params:{pageNo, iid} */
  list: (params) => http.get('/nft', params),
  /** 原站 getNft：GET /nft/{nftId} —— 含 item + owner 完整对象 */
  getNft: (nftId) => http.get(`/nft/${nftId}`),
  /** 原站 listGroup：GET /nft/group  params:{pageNo, itemType, is_merge} */
  group: (params) => http.get('/nft/group', params),
  /** 原站 openBox：POST /nft/box/open */
  openBox: (data) => http.post('/nft/box/open', data),
  digitalAlbumCalendar: (params) => http.get('/nft/digitalAlbum/calendar', params),
  // 一级市场挂单
  /** 原站 sellNeed：POST /nft/sell/apply */
  sellApply: (data) => http.post('/nft/sell/apply', data),
  /** 原站 sellUp：POST /nft/sell/up —— 单条寄售 */
  sellUp: (data) => http.post('/nft/sell/up', data),
  /** 原站 sellUpBatch：POST /nft/sell/up/batch */
  sellUpBatch: (data) => http.post('/nft/sell/up/batch', data),
  /** 原站 sellDown：PUT /nft/sell/down（注意是 PUT，不是 POST） */
  sellDown: (data) => http.put('/nft/sell/down', data),
  /** 原站 listSold：GET /nft/sell/sold */
  sellSold: (params) => http.get('/nft/sell/sold', params),
  // 二级市场
  secondary: (params) => http.get('/nft/secondary', params),
  /** 原站 nft.CgdmklJr.js：getMarket —— 市场详情页藏品数据 */
  getMarket: (iid) => http.get(`/nft/secondary/${iid}`),
  /** 寄售挂单列表 */
  secondarySellList: (params) => http.get('/nft/secondary/sell', params),
  /** 成交记录 */
  secondarySoldList: (params) => http.get('/nft/secondary/sold', params),
  /** 求购列表 */
  secondaryApplyList: (params) => http.get('/nft/secondary/apply', params),
  /** 委托列表 */
  secondaryTrustList: (params) => http.get('/nft/secondary/trust', params),
  secondaryFollow: (params) => http.get('/nft/secondary/follow', params),
  /** 原站 sellNeed：出售给求购单（二级密码校验） */
  sellNeed: (data) => http.post('/nft/sell/apply', data),
  /** 原站 nft.CgdmklJr.js：getNft —— 我的 NFT 详情页数据 */
  getNft: (nftId) => http.get(`/nft/${nftId}`),
  /** 原站 sellDown 为 PUT（非 POST） */
  sellDownPut: (data) => http.put('/nft/sell/down', data),
}

/* ==================== 活动 ==================== */
export const activityApi = {
  index: () => http.get('/activity/index'),
  // 签到（源站：getSign / sign，无 /sign/log）
  getSign: () => http.get('/activity/sign'),
  sign: () => http.post('/activity/sign'),
  pointsBalance: () => http.get('/activity/points/balance'),
  // 抽奖
  getLotteryActive: () => http.get('/activity/lottery/active'),
  lotteryList: (params) => http.get('/activity/lottery', params),
  getLottery: (id) => http.get(`/activity/lottery/${id}`),
  // 源站 startLottery 为 POST /activity/lottery/start/{id}，id 走路径
  startLottery: (id) => http.post(`/activity/lottery/start/${id}`),
  listLotteryLog: (params) => http.get('/activity/lottery/log', params),
  // 合成
  listMerge: (params) => http.get('/item/merge', params),
  getMerge: (id) => http.get(`/item/merge/${id}`),
  mergeV1: (data) => http.post('/nft/merge/v1', data),
  mergeRecord: (params) => http.get('/product_merge/record', params),
  // 抽签（运势）
  recastType: () => http.get('/activity/recast/type'),
  recastDollType: () => http.get('/activity/recast/doll-type'),
  recastDraw: (data) => http.post('/activity/recast/draw', data),
  recastDrawNum: () => http.get('/activity/recast/draw-num'),
  recastLog: (params) => http.get('/activity/recast/log', params),
  recastPrize: (params) => http.get('/activity/recast/prize', params),
  rule: (params) => http.get('/common/rule', params),
  // AI 助理
  aiAnswer: (data) => http.post('/ai/answer/get_answer', data),
  // 数字藏品活动
  myZh: () => http.get('/product_zh_activity/my_zh'),
  myZhList: (params) => http.get('/product_zh_activity/my_zh_list', params),
}

/* ==================== IM 聊天 ==================== */
export const imApi = {
  groupList: (params) => http.get('/im/group', params),
  groupDetail: (params) => http.get('/im/group/', params),
  groupJoin: (id) => http.post(`/im/group/join/${id}`),
  groupLeave: (id) => http.post(`/im/group/leave/${id}`),
  groupMessage: (params) => http.get('/im/group/message', params),
  groupUser: (params) => http.get('/im/group/user/', params),
  user: (params) => http.get('/im/user/', params),
}

/* ==================== 作者 ====================
 * 源站原样（勿改）：
 *   列表  pages/author/index 分包 → GET /item/author
 *   详情  pages-author-detail.Bruc8JVX.js → GET /api/author/detail?id=xxx
 *         线上该接口恒返回 {status:false,code:10203,message:"未找到"}，
 *         故源站走 mock 兜底：name="臻海艺术"、avatar=/static/images/logo.png、works=mock 三图
 */
export const authorApi = {
  /** 创作者中心列表：源站 itemApi 中 author() */
  list: (params) => http.get('/item/author', params),
  /** 作者详情：线上未上线，恒 10203 */
  detail: (params) => http.get('/api/author/detail', params),
}

/* ==================== 文件上传 ==================== */
import request from './request'
function requestUpload(url, formData) {
  return request.post(url, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}