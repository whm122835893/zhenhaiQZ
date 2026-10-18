/**
 * 路由操作兼容层 —— 复刻原站 uni.navigateTo / switchTab / reLaunch 语义，
 * 内部转发到 vue-router，使页面代码与原站保持一致。
 */
import router from '@/router'

const TAB_PATHS = ['/pages/index/index', '/pages/market/index', '/pages/copyright/index', '/pages/my/index']

function push(url) {
  if (!url) return
  if (TAB_PATHS.includes(url)) router.replace(url)
  else router.push(url)
}

export const uni = {
  navigateTo: ({ url }) => push(url),
  redirectTo: ({ url }) => router.replace(url),
  switchTab: ({ url }) => router.replace(url),
  reLaunch: ({ url }) => router.replace(url),
  navigateBack: (delta = 1) => router.back(delta),
  back: (delta = 1) => router.back(delta),
}

if (typeof window !== 'undefined' && !window.uni) {
  window.uni = uni
}

export default uni