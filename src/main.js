import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './utils/uni'

import 'vant/lib/index.css'
import './styles/iconfont.css'
import './styles/tokens.css'
import './styles/global.css'

/* Vant 组件注册
   注意：项目原先只引入了 vant 的 CSS，却没注册组件，
   导致 <van-popup> / <van-icon> 被 Vue 当成未知自定义元素渲染
   （class 为空、无 fixed 定位、隐藏弹窗常驻可见）。
   此处按实际使用范围注册，未使用的组件不引入，避免拖大包体。 */
import { Popup as VanPopup, Icon as VanIcon } from 'vant'

import { BASE_URL, isLocalApi, switchApi, loadBrand } from './api/request'

/* 后端调试开关（仅开发环境挂到 window，生产构建被静态剔除）
 *
 * 控制台用法：
 *   shengtaiApi.base()        查看当前后端地址
 *   shengtaiApi.isLocal()     是否连本地后端
 *   shengtaiApi.useLocal()    切到本地后端（即时生效，不用刷新）
 *   shengtaiApi.useSource()   切到源站
 *   shengtaiApi.reset()       回到 .env 配置
 *
 * 用途：同一个前端随时对比「源站」与「本地后端」的返回差异，
 * 是像素级/字段级回归的最快手段。
 */
if (import.meta.env.DEV) {
  window.shengtaiApi = {
    base: BASE_URL,
    isLocal: isLocalApi,
    useLocal: () => switchApi('local'),
    useSource: () => switchApi('source'),
    reset: () => switchApi('reset'),
  }
  console.info(
    `[shengtai] 当前后端：${BASE_URL}` +
      (isLocalApi() ? '（本地）' : '（源站）') +
      ' · 控制台 shengtaiApi 可切换'
  )
}

const app = createApp(App)
app.component('van-popup', VanPopup)
app.component('van-icon', VanIcon)
app.use(createPinia())
app.use(router)
app.mount('#app')

// 平台品牌动态化：启动即拉（首页 logo 字、关于页、证书、协议、标题栏等展示位）
loadBrand()