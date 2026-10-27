import { createRouter, createWebHashHistory } from 'vue-router'
import { getToken, brand } from './api'

const routes = [
  {
    path: '/login',
    component: () => import('./views/Login.vue'),
    meta: { title: '登录' },
  },
  {
    path: '/',
    component: () => import('./views/Layout.vue'),
    redirect: '/dashboard',
    children: [
      { path: 'dashboard', component: () => import('./views/Dashboard.vue'), meta: { title: '数据仪表盘', sub: 'Dashboard' } },
      { path: 'users', component: () => import('./views/Users.vue'), meta: { title: '用户管理', sub: 'Users' } },
      { path: 'wallet', component: () => import('./views/Wallets.vue'), meta: { title: '钱包流水', sub: 'Wallet' } },
      { path: 'items', component: () => import('./views/Items.vue'), meta: { title: '藏品管理', sub: 'Collections' } },
      { path: 'categories', component: () => import('./views/Categories.vue'), meta: { title: '分类管理', sub: 'Categories' } },
      { path: 'orders', component: () => import('./views/Orders.vue'), meta: { title: '订单管理', sub: 'Orders' } },
      { path: 'market', component: () => import('./views/Market.vue'), meta: { title: '市场寄售', sub: 'Market' } },
      { path: 'nfts', component: () => import('./views/Nfts.vue'), meta: { title: '持仓查询', sub: 'Holdings' } },
      { path: 'grant', component: () => import('./views/Grants.vue'), meta: { title: '定向发放', sub: 'Grants' } },
      { path: 'activity/lottery', component: () => import('./views/ActivityLottery.vue'), meta: { title: '抽奖活动', sub: 'Lottery' } },
      { path: 'activity/merge', component: () => import('./views/ActivityMerge.vue'), meta: { title: '合成活动', sub: 'Merge' } },
      { path: 'activity/invites', component: () => import('./views/ActivityInvites.vue'), meta: { title: '邀请有礼', sub: 'Invites' } },
      { path: 'cms/notices', component: () => import('./views/CmsNotices.vue'), meta: { title: '公告管理', sub: 'Notices' } },
      { path: 'cms/banners', component: () => import('./views/CmsBanners.vue'), meta: { title: '轮播管理', sub: 'Banners' } },
      { path: 'system/configs', component: () => import('./views/SystemConfigs.vue'), meta: { title: '运营参数', sub: 'Configs' } },
      { path: 'system/cleanup', component: () => import('./views/Cleanup.vue'), meta: { title: '数据清理', sub: 'Cleanup' } },
      { path: 'system/logs', component: () => import('./views/SystemLogs.vue'), meta: { title: '日志审计', sub: 'Logs' } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

router.beforeEach(to => {
  if (to.path !== '/login' && !getToken()) return '/login'
  if (to.path === '/login' && getToken()) return '/dashboard'
  // 平台名动态化：运营参数 app.name 修改后即时生效（brand 为全局响应式）
  document.title = (to.meta.title ? `${to.meta.title} · ` : '') + `${brand.name}管理后台`
  return true
})

export default router
