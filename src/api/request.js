import axios from 'axios'
import { showToast } from 'vant'
import * as uiLoading from '@/utils/loading'
import { storage, TOKEN_KEY, BG_IMG_KEY } from '@/utils/storage'

/**
 * 全局 loading：委托给自绘层 AppLoading（src/utils/loading.js）
 * 原站为 uni.showLoading 自绘遮罩，不用 Vant Toast（并发 reset 会残留黑块）
 */
export function showLoading(title) {
  uiLoading.showLoading(title)
}
export function hideLoading() {
  uiLoading.hideLoading()
}

/**
 * 原站 util.Duw9LX6a.js 的 copy：
 *   copy(s, a) => uni.setClipboardData({ data:s, showToast:false })
 *                  .success(() => uni.showToast({ icon:'none', title: a || '复制成功' }))
 * 即屏蔽系统默认「内容已复制」提示，自绘 icon:'none' 的 toast。
 */
export function copy(text, tip) {
  const data = text == null ? '' : String(text)
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(data).catch(() => {})
  }
  const ta = document.createElement('textarea')
  ta.value = data
  ta.style.position = 'fixed'
  ta.style.opacity = '0'
  document.body.appendChild(ta)
  ta.select()
  try {
    document.execCommand('copy')
  } catch (e) {
    /* 忽略：部分浏览器禁用了 execCommand */
  }
  document.body.removeChild(ta)
  showToast({ icon: 'none', title: tip || '复制成功' })
}

/**
 * 后端地址配置
 *
 * 三种来源，优先级从低到高：
 *   1. 构建期注入：VITE_API_BASE（.env / .env.development / .env.production）
 *   2. 运行时覆盖：localStorage['zh:apiBase']（控制台一行即可切，无需重新打包）
 *   3. 默认值：import.meta.env.DEV ? 本地后端 : 源站
 *
 * 源站：https://api.shengtai.cyou
 * 本地：/api 走 vite 代理（见 vite.config.js），避免开发期跨域
 */
const LOCAL_API = '/api'
const SOURCE_API = 'https://api.shengtai.cyou'

function resolveBaseUrl() {
  // 1. 运行时覆盖（方便一键对比源站 / 本地后端）
  const override = storage.get('apiBase')
  if (override) return String(override).replace(/\/+$/, '')

  // 2. 构建期注入
  const injected = import.meta.env && import.meta.env.VITE_API_BASE
  if (injected) return String(injected).replace(/\/+$/, '')

  // 3. 默认：始终同源 /api（dev 走 vite 代理，生产由 nginx 反代到后端）。
  //    不再默认直连源站 —— 源站不是本项目的一部分，静默外联属于潜在故障源；
  //    需要对比源站时用控制台 shengtaiApi.useSource() 显式切换。
  return LOCAL_API
}

export const BASE_URL = resolveBaseUrl()

/** 本站根地址：邀请链接、协议页等站内绝对链接都拼在当前 origin 上，
 *  不再指向源站（源站地址仅在调试常量里保留） */
export const FRONT_URL = window.location.origin
export const WSS_URL = 'wss://im.shengtai.cyou/ws' // 遗留常量，暂无调用方；IM 服务自建后替换
export const APP_NAME = '臻海艺术'

/** 当前是否连本地后端（调试用：控制台 shengtaiApi.isLocal()） */
export function isLocalApi() {
  return !BASE_URL.startsWith('https://api.shengtai.cyou')
}

/**
 * 运行时切换后端（调试用，不刷新页面即时生效）
 * 用法：shengtaiApi.useLocal() / shengtaiApi.useSource() / shengtaiApi.reset()
 */
export function switchApi(target) {
  if (target === 'local') {
    storage.set('apiBase', LOCAL_API)
  } else if (target === 'source') {
    storage.set('apiBase', SOURCE_API)
  } else {
    storage.remove('apiBase')
  }
  // 同步丢弃 crypto.js 里缓存的 RSA 加密器。
  // 不清会出现「刚切到源站，加密却还发本地公钥」的错配 ——
  // 源站私钥与本地私钥不同，密码一定解不开。
  notifyEncryptorReset()

  const next = resolveBaseUrl()
  window.dispatchEvent(new CustomEvent('api:switched', { detail: next }))
  console.info(`[shengtai] 后端已切换：${next}`)
  return next
}

/**
 * 通知 crypto.js 重置加密器实例。
 *
 * 用动态 import 而不是静态 import，是为了避免循环依赖：
 *   request.js → crypto.js → request.js（crypto 需要 isLocalApi）
 * 静态 import 会在模块求值阶段就触发循环，导致 isLocalApi 还未定义。
 */
function notifyEncryptorReset() {
  import('@/utils/crypto')
    .then((m) => m.resetEncryptor && m.resetEncryptor())
    .catch(() => {
      /* crypto.js 加载失败不影响后端切换本身 */
    })
}

/** 与原站一致的设备信息（appid / version / deviceId / deviceModel / platform）
 *  实测原站线上为 appVersion 1.3.2 / appVersionCode 132，1.1.9 会导致 query version 不一致 */
export const APP_ID = '__UNI__22FD095'
export const APP_VERSION = '1.3.2'

function deviceId() {
  let id = storage.get('deviceId')
  if (!id) {
    id = String(Date.now()) + Math.floor(Math.random() * 900000 + 100000)
    storage.set('deviceId', id)
  }
  return id
}

const request = axios.create({
  // 每次请求都重新解析，配合 switchApi() 实现运行时切换（见下方拦截器）
  baseURL: '',
  timeout: 30000,
})

/* ---------------- 请求拦截：注入 Bearer token + 设备参数 ---------------- */
request.interceptors.request.use(
  (config) => {
    uiLoading.trackPending(1)

    // 每次请求重算 baseURL，这样控制台调 switchApi() 后无需刷新页面
    if (!config.baseURL) {
      config.baseURL = resolveBaseUrl()
    }

    const token = storage.get(TOKEN_KEY)
    if (token) config.headers.Authorization = 'Bearer ' + token
    config.params = {
      appid: APP_ID,
      version: APP_VERSION,
      deviceId: deviceId(),
      deviceModel: 'iPhone',
      platform: 'web',
      ...(config.params || {}),
    }
    return config
  },
  (error) => Promise.reject(error)
)

/* ---------------- 响应拦截：与原站一致的 code 语义 ---------------- */
request.interceptors.response.use(
  (res) => {
    uiLoading.trackPending(-1)
    const body = res.data
    // code 11111 = 未认证 → 清 token 并销毁 socket（原站行为）
    if (body && body.code === 11111) {
      storage.remove(TOKEN_KEY)
      storage.remove(BG_IMG_KEY)
      window.dispatchEvent(new CustomEvent('auth:expired'))
      return body
    }
    if (body && body.status === false) {
      showToast({ icon: 'none', title: body.message || '' })
    }
    return body
  },
  (error) => {
    uiLoading.trackPending(-1)
    return Promise.reject(error)
  }
)

/* ---------------- 对外方法（签名对齐原站 luch-request 封装） ---------------- */
export const http = {
  get: (url, params) => request.get(url, { params }),
  post: (url, data) => request.post(url, data),
  put: (url, data) => request.put(url, data),
  delete: (url, data) => request.delete(url, { data }),
}

export { showToast }
export default request