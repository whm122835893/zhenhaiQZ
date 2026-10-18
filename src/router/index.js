import { createRouter, createWebHashHistory } from 'vue-router'
import { storage, TOKEN_KEY } from '@/utils/storage'

/**
 * 路由表 —— 严格对齐原站 h5.shengtai.cyou
 * 原站使用 history 模式（URL 形如 /pages/xxx/yyy）
 */
const routes = [
  { path: '/', redirect: '/pages/index/index' },

  /* ---------- 首页模块 ---------- */
  {
    path: '/pages/index/index',
    name: 'index',
    component: () => import('@/views/index/IndexPage.vue'),
    meta: { title: '首页', tabKey: 'index', tab: true },
  },
  { path: '/pages/index/info', name: 'index-info', component: () => import('@/views/index/InfoPage.vue'), meta: { title: '藏品详情' } },
  { path: '/pages/index/digitalAlbum', name: 'digital-album', component: () => import('@/views/index/DigitalAlbum.vue'), meta: { title: '发售日历' } },
  { path: '/pages/index/ai-zhushou', name: 'ai-zhushou', component: () => import('@/views/index/AiZhushou.vue'), meta: { title: 'ai助理' } },
  { path: '/pages/index/upgrade', name: 'upgrade', component: () => import('@/views/index/Upgrade.vue'), meta: { title: '版本升级' } },
  { path: '/pages/index/webView', name: 'web-view', component: () => import('@/views/index/WebView.vue'), meta: { title: '' } },

  /* ---------- 市场模块 ---------- */
  {
    path: '/pages/market/index',
    name: 'market',
    component: () => import('@/views/market/MarketPage.vue'),
    meta: { title: '市场', tabKey: 'market', tab: true },
  },
  { path: '/pages/market/info', name: 'market-info', component: () => import('@/views/market/MarketInfo.vue'), meta: { title: '市场详情' } },
  { path: '/pages/market/batch', name: 'market-batch', component: () => import('@/views/market/MarketBatch.vue'), meta: { title: '批量购买' } },
  { path: '/pages/market/need', name: 'market-need', component: () => import('@/views/market/MarketNeed.vue'), meta: { title: '求购' } },
  { path: '/pages/market/trust', name: 'market-trust', component: () => import('@/views/market/MarketTrust.vue'), meta: { title: '信任' } },
  { path: '/pages/market/trustIndex', name: 'market-trust-index', component: () => import('@/views/market/MarketTrustIndex.vue'), meta: { title: '信任列表' } },
  { path: '/pages/market/log', name: 'market-log', component: () => import('@/views/market/MarketLog.vue'), meta: { title: '交易记录' } },

  /* ---------- 版权模块 ---------- */
  {
    path: '/pages/copyright/index',
    name: 'copyright',
    component: () => import('@/views/copyright/CopyrightPage.vue'),
    meta: { title: '臻海艺术', tabKey: 'copyright', tab: true },
  },

  /* ---------- 我的模块 ---------- */
  {
    path: '/pages/my/index',
    name: 'my',
    component: () => import('@/views/my/MyPage.vue'),
    meta: { title: '我的', tabKey: 'my', tab: true },
  },
  { path: '/pages/my/info/userInfo', name: 'my-user-info', component: () => import('@/views/my/UserInfo.vue'), meta: { title: '个人资料' } },
  { path: '/pages/my/info/account', name: 'my-account', component: () => import('@/views/my/Account.vue'), meta: { title: '账号信息' } },
  { path: '/pages/my/info/adress', name: 'my-address', component: () => import('@/views/my/Address.vue'), meta: { title: '收货地址' } },
  { path: '/pages/my/info/pwd', name: 'my-pwd', component: () => import('@/views/my/Pwd.vue'), meta: { title: '账号安全' } },
  { path: '/pages/my/info/xieyi', name: 'my-agreement', component: () => import('@/views/my/Agreement.vue'), meta: { title: '协议管理' } },
  { path: '/pages/my/setting/index', name: 'my-setting', component: () => import('@/views/my/Setting.vue'), meta: { title: '设置' } },
  { path: '/pages/my/setting/about', name: 'my-about', component: () => import('@/views/my/About.vue'), meta: { title: '关于我们' } },
  { path: '/pages/my/setting/delete', name: 'my-delete', component: () => import('@/views/my/DeleteAccount.vue'), meta: { title: '注销账号' } },
  { path: '/pages/my/setting/realname', name: 'my-realname', component: () => import('@/views/my/RealName.vue'), meta: { title: '实名认证' } },
  { path: '/pages/my/setting/secondPassword', name: 'my-second-pwd', component: () => import('@/views/my/SecondPassword.vue'), meta: { title: '修改操作密码' } },
  { path: '/pages/my/wallet/index', name: 'my-wallet', component: () => import('@/views/my/Wallet.vue'), meta: { title: '我的钱包' } },
  { path: '/pages/my/order/index', name: 'my-order', component: () => import('@/views/my/Order.vue'), meta: { title: '订单' } },
  { path: '/pages/my/order/info', name: 'my-order-info', component: () => import('@/views/my/OrderInfo.vue'), meta: { title: '订单详情' } },
  { path: '/pages/my/invite/index', name: 'my-invite', component: () => import('@/views/my/Invite.vue'), meta: { title: '邀请好友' } },
  { path: '/pages/my/nft/info', name: 'my-nft-info', component: () => import('@/views/my/NftInfo.vue'), meta: { title: '我的' } },
  { path: '/pages/my/nft/sale', name: 'my-nft-sale', component: () => import('@/views/my/NftSale.vue'), meta: { title: '我的' } },
  { path: '/pages/my/nft/batchSale', name: 'my-nft-batch-sale', component: () => import('@/views/my/NftBatchSale.vue'), meta: { title: '我的' } },

  /* ---------- 授权 ---------- */
  { path: '/pages/authorize/signin', name: 'signin', component: () => import('@/views/auth/SignIn.vue'), meta: { title: '登录' } },
  { path: '/pages/authorize/signup', name: 'signup', component: () => import('@/views/auth/SignUp.vue'), meta: { title: '注册' } },
  { path: '/pages/authorize/password', name: 'forget-password', component: () => import('@/views/auth/ForgetPassword.vue'), meta: { title: '找回密码' } },

  /* ---------- 公告 ---------- */
  { path: '/pages/notice/index', name: 'notice', component: () => import('@/views/notice/NoticeList.vue'), meta: { title: '公告', tab: true, tabKey: 'notice' } },
  { path: '/pages/notice/info', name: 'notice-info', component: () => import('@/views/notice/NoticeInfo.vue'), meta: { title: '公告详情' } },
  { path: '/pages/notice/search', name: 'notice-search', component: () => import('@/views/notice/NoticeSearch.vue'), meta: { title: '相关公告' } },

  /* ---------- 作者 ---------- */
  { path: '/pages/author/index', name: 'author-index', component: () => import('@/views/author/AuthorList.vue'), meta: { title: '臻海艺术' } },
  { path: '/pages/author/detail', name: 'author-detail', component: () => import('@/views/author/AuthorDetail.vue'), meta: { title: '作者详情' } },

  /* ---------- 活动 ---------- */
  { path: '/pages/activity/index', name: 'activity-index', component: () => import('@/views/activity/Compose.vue'), meta: { title: '合成兑换' } },
  { path: '/pages/activity/mergeInfo', name: 'activity-merge', component: () => import('@/views/activity/MergeInfo.vue'), meta: { title: '合成' } },
  { path: '/pages/activity/decompose', name: 'activity-decompose', component: () => import('@/views/activity/Decompose.vue'), meta: { title: '分解兑换' } },
  { path: '/pages/activity/chengjin', name: 'activity-chengjin', component: () => import('@/views/activity/Chengjin.vue'), meta: { title: '成品展厅' } },
  { path: '/pages/activity/lottery', name: 'activity-lottery', component: () => import('@/views/activity/Lottery.vue'), meta: { title: '幸运抽奖' } },
  { path: '/pages/activity/chouqian', name: 'activity-chouqian', component: () => import('@/views/activity/Chouqian.vue'), meta: { title: '抽签' } },
  { path: '/pages/activity/baozha', name: 'activity-baozha', component: () => import('@/views/activity/Baozha.vue'), meta: { title: '爆章' } },
  { path: '/pages/activity/baoxiang', name: 'activity-baoxiang', component: () => import('@/views/activity/Baoxiang.vue'), meta: { title: '盲盒' } },
  { path: '/pages/activity/chongzhu', name: 'activity-chongzhu', component: () => import('@/views/activity/Chongzhu.vue'), meta: { title: '重铸' } },
  { path: '/pages/activity/sign', name: 'activity-sign', component: () => import('@/views/activity/Sign.vue'), meta: { title: '签到中心' } },
  { path: '/pages/activity/record', name: 'activity-record', component: () => import('@/views/activity/Record.vue'), meta: { title: '合成记录' } },
  { path: '/pages/activity/tupu', name: 'activity-tupu', component: () => import('@/views/activity/Tupu.vue'), meta: { title: '图谱' } },
  { path: '/pages/activity/jijie', name: 'activity-jijie', component: () => import('@/views/activity/Jijie.vue'), meta: { title: '跨界' } },
  { path: '/pages/activity/shiyan', name: 'activity-shiyan', component: () => import('@/views/activity/Shiyan.vue'), meta: { title: '试验' } },
  { path: '/pages/activity/shijie', name: 'activity-shijie', component: () => import('@/views/activity/Shijie.vue'), meta: { title: '世界' } },
  /**
   * 源站此路径归在 activity 下，但内容实为 nb-model-viewer 组件演示页（3D 播放器），
   * 与「活动」业务无关。标题按演示页取「3D 模型」，避免误导。
   */
  { path: '/pages/activity/test', name: 'activity-test', component: () => import('@/views/activity/Test.vue'), meta: { title: '3D 模型' } },

  /* ---------- 聊天 ---------- */
  { path: '/pages/chat/group', name: 'chat-group', component: () => import('@/views/chat/GroupChat.vue'), meta: { title: '群聊' } },
  { path: '/pages/chat/web', name: 'chat-web', component: () => import('@/views/chat/ChatWeb.vue'), meta: { title: '聊天' } },

  { path: '/:pathMatch(.*)*', redirect: '/pages/index/index' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

/** 与原站一致：设置 document.title */
router.afterEach((to) => {
  const t = to.meta && to.meta.title
  document.title = t ? (t === '臻海艺术' ? t : t) : APP_TITLE_FALLBACK
})

const APP_TITLE_FALLBACK = '臻海艺术'

export default router