import axios from 'axios'
import { reactive } from 'vue'
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
 * 后端地址配置（BuildAdmin 后端）
 *
 * 三种来源，优先级从低到高：
 *   1. 构建期注入：VITE_API_BASE（.env / .env.development / .env.production）
 *   2. 运行时覆盖：localStorage['zh:apiBase']（控制台一行即可切，无需重新打包）
 *   3. 默认值：同源 /api（dev 走 vite 代理，生产由 nginx 反代到 server/buildadmin）
 *
 * BuildAdmin 的 C 端接口本身就带 /api 前缀（/api/nft_collection/index），
 * 所以 dev 代理不需要 rewrite；生产 nginx 同域反代 /api、/storage 即可。
 */
const LOCAL_API = '/api'

function resolveBaseUrl() {
  // 1. 运行时覆盖
  const override = storage.get('apiBase')
  if (override) return String(override).replace(/\/+$/, '')

  // 2. 构建期注入
  const injected = import.meta.env && import.meta.env.VITE_API_BASE
  if (injected) return String(injected).replace(/\/+$/, '')

  // 3. 默认：始终同源 /api（dev 走 vite 代理，生产由 nginx 反代到后端）
  return LOCAL_API
}

export const BASE_URL = resolveBaseUrl()

/** 后端静态资源根：/storage/... 图片在 dev 下由 vite 代理，生产同域反代 */
const BACK_ORIGIN = BASE_URL === LOCAL_API ? '' : BASE_URL.replace(/\/api$/, '')

/**
 * 后端图片地址归一化：
 *   ''            → ''（页面自有空态兜底）
 *   http(s)://    → 原样（后台上传常带完整域名）
 *   /storage/...  → 补后端根
 *   相对路径      → {后端根}/storage/...
 */
export function img(url) {
  const u = String(url || '')
  if (!u) return ''
  if (/^https?:\/\//.test(u)) return u
  if (u.startsWith('/')) return BACK_ORIGIN + u
  return BACK_ORIGIN + '/storage/' + u
}

/**
 * 缺口功能兜底（IM / AI / 合成 / 实名 / 短信 / 第三方藏品等）：
 * 统一返回旧契约成功空包络，页面空态自洽，不报错。
 */
export function stub(data) {
  return Promise.resolve({
    status: true,
    code: 0,
    message: '',
    msg: '',
    data: data === undefined ? null : data,
  })
}

/** 本站根地址：邀请链接、协议页等站内绝对链接都拼在当前 origin 上 */
export const FRONT_URL = window.location.origin
export const WSS_URL = '' // IM 自建后替换（缺口功能已 stub）
export const APP_NAME = '臻海艺术'

/**
 * 平台品牌（全局响应式）：启动时从后端拉取（后台「运营参数 → 平台品牌」可配），
 * 拉取失败保持默认值。展示位：首页 logo 字、社区标题、关于页、证书、
 * 协议页、document.title 等（3D 模型背面贴图除外——烘焙在 gltf 里，静态）。
 */
export const brand = reactive({
  name: APP_NAME,
  logo: '',
  banner: '',
  heroTitle1: '让热爱',
  heroTitle2: '奔赴更大的世界',
  heroSubtitle: '数字艺术·收藏·交易·共建',
})

export async function loadBrand() {
  try {
    const res = await http.get('/nft_setting/brand')
    const d = (res && res.data) || {}
    if (d.name) brand.name = d.name
    if (d.logo) brand.logo = d.logo
  } catch (e) {
    /* 网络/后端异常：保持默认品牌，页面不报错 */
  }
}

/** 当前是否连本地后端（调试用：控制台 shengtaiApi.isLocal()） */
export function isLocalApi() {
  return BASE_URL === LOCAL_API
}

/**
 * 运行时切换后端（调试用，不刷新页面即时生效）
 * 用法：shengtaiApi.useLocal() / shengtaiApi.reset()
 */
export function switchApi(target) {
  if (target === 'local') {
    storage.set('apiBase', LOCAL_API)
  } else {
    storage.remove('apiBase')
  }

  const next = resolveBaseUrl()
  window.dispatchEvent(new CustomEvent('api:switched', { detail: next }))
  console.info(`[zhenhai] 后端已切换：${next}`)
  return next
}

/** 与原站一致的设备信息常量（新后端不校验，保留导出签名） */
export const APP_ID = '__UNI__22FD095'
export const APP_VERSION = '1.3.2'

const request = axios.create({
  // 每次请求都重新解析，配合 switchApi() 实现运行时切换（见下方拦截器）
  baseURL: '',
  timeout: 30000,
})

/* ---------------- 请求拦截：注入 ba-user-token ---------------- */
request.interceptors.request.use(
  (config) => {
    uiLoading.trackPending(1)

    // 每次请求重算 baseURL，这样控制台调 switchApi() 后无需刷新页面
    if (!config.baseURL) {
      config.baseURL = resolveBaseUrl()
    }

    const token = storage.get(TOKEN_KEY)
    if (token) config.headers['ba-user-token'] = token
    return config
  },
  (error) => Promise.reject(error)
)

/* ---------------- 响应拦截：BuildAdmin 包络 → 原站契约 ----------------
 *
 * BuildAdmin：{code, msg, time, data}，code==1 成功；未登录 code:303；
 * 业务失败 code:0（HTTP 200）；网关异常时返回 HTML（无 code 字段）。
 *
 * 归一化为原站契约：{status, code, message, data}
 *   code 0   = 成功（status: true）
 *   11111    = 未登录（清 token + 派发 auth:expired，页面各自跳登录/置空态）
 */
function normalize(body) {
  const code = body ? body.code : undefined
  const msg = (body && (body.msg ?? body.message)) || ''
  const data = body ? body.data : null

  if (code === 1) {
    return { status: true, code: 0, message: msg, msg, data }
  }
  if (code === 303 || code === 401 || code === 409) {
    storage.remove(TOKEN_KEY)
    storage.remove(BG_IMG_KEY)
    window.dispatchEvent(new CustomEvent('auth:expired'))
    return {
      status: false,
      code: 11111,
      message: '请先登录',
      msg: '请先登录',
      data: { type: 'need login' },
    }
  }
  if (code === undefined) {
    // HTML 错误页 / 非预期响应 → 当作网络异常
    return { status: false, code: -1, message: '网络异常，请稍后重试', msg: '网络异常', data: null }
  }
  return { status: false, code, message: msg, msg, data }
}

request.interceptors.response.use(
  (res) => {
    uiLoading.trackPending(-1)
    const out = normalize(res.data)
    if (!out.status && out.code !== 11111) {
      showToast({ icon: 'none', title: out.message || '操作失败' })
    }
    return out
  },
  (error) => {
    uiLoading.trackPending(-1)
    showToast({ icon: 'none', title: '网络异常，请稍后重试' })
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
