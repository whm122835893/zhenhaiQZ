import axios from 'axios'
import { reactive } from 'vue'

/**
 * 管理后台请求层
 *
 * - baseURL /api/admin → vite 代理转发到 ThinkPHP /admin/*
 * - 响应包络 {status, code, message, data}，code===0 为成功
 * - code===11111 未登录/token 失效 → 清凭证回登录页（与 C 端拦截器语义一致）
 */

const TOKEN_KEY = 'zh_admin_token'
const PROFILE_KEY = 'zh_admin_profile'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY) || ''
}
export function setToken(t) {
  localStorage.setItem(TOKEN_KEY, t)
}
export function clearAuth() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(PROFILE_KEY)
}
export function getProfile() {
  try {
    return JSON.parse(localStorage.getItem(PROFILE_KEY) || 'null')
  } catch {
    return null
  }
}
export function setProfile(p) {
  localStorage.setItem(PROFILE_KEY, JSON.stringify(p))
}

/** 写权限：仅超管(1)/运营(2)，财务(3)/客服(4) 只读 —— 与后端 AdminBase::needWriter 对齐 */
export function canWrite() {
  const p = getProfile()
  return !!p && Number(p.role) <= 2
}

/** 超管权限：仅超管(1) —— 与后端 AdminBase::needSuper 对齐 */
export function canSuper() {
  const p = getProfile()
  return !!p && Number(p.role) === 1
}

/* ---------- toast ---------- */
let toastBox = null
export function toast(msg, isErr = false) {
  if (!toastBox) {
    toastBox = document.createElement('div')
    toastBox.className = 'toast-box'
    document.body.appendChild(toastBox)
  }
  const el = document.createElement('div')
  el.className = 'toast' + (isErr ? ' err' : '')
  el.textContent = msg
  toastBox.appendChild(el)
  setTimeout(() => {
    el.style.transition = 'opacity .35s'
    el.style.opacity = '0'
  }, 2200)
  setTimeout(() => el.remove(), 2650)
}

/* ---------- axios 实例 ---------- */
const http = axios.create({ baseURL: '/api/admin', timeout: 15000 })

http.interceptors.request.use(cfg => {
  const t = getToken()
  if (t) cfg.headers.Authorization = `Bearer ${t}`
  return cfg
})

http.interceptors.response.use(
  res => {
    const body = res.data
    if (body && typeof body === 'object' && 'code' in body) {
      if (Number(body.code) === 0) return body.data
      if (Number(body.code) === 11111) {
        clearAuth()
        if (router_kick) router_kick()
      }
      toast(body.message || '请求失败', true)
      return Promise.reject(new Error(body.message || '请求失败'))
    }
    return body
  },
  err => {
    toast(err.message === 'Network Error' ? '网络异常，请检查后端服务' : (err.message || '网络异常'), true)
    return Promise.reject(err)
  },
)

let router_kick = null
export function bindKick(fn) {
  router_kick = fn
}

/* ---------- 平台品牌（全局响应式） ---------- */

/**
 * brand：平台名/logo 全局状态。
 * main.js 启动时调 loadBrand()（GET /basic/app，公开接口，登录前也能显示）；
 * 「运营参数」页保存 app.name / app.logo 后调 refreshBrand() 即时生效。
 */
export const brand = reactive({ name: '臻海艺术', logo: '' })

export async function loadBrand() {
  try {
    const d = await http.get('/basic/app')
    if (d?.data?.app_name) brand.name = String(d.data.app_name)
    brand.logo = String(d?.data?.app_logo || '')
  } catch {
    /* 拉不到就用默认值 */
  }
}

/* ---------- 通用格式化 ---------- */

/** 时间显示：兼容 datetime 字符串与 13 位毫秒时间戳 */
export function fmtTime(v) {
  if (v === null || v === undefined || v === '') return '—'
  const s = String(v)
  if (/^\d{13}$/.test(s)) {
    const d = new Date(Number(s))
    const p = n => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
  }
  if (/^\d{10}$/.test(s)) {
    const d = new Date(Number(s) * 1000)
    const p = n => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
  }
  return s.replace('T', ' ').slice(0, 16)
}

export function money(v) {
  return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
}

/** 分 → 元：库里与 API 金额全链路为「分」（源站语义，C 端 /100 显示），后台展示时转元 */
export function toYuan(v) {
  return Number(v || 0) / 100
}

/** 订单类型（后端 Order.php TYPE_* 常量） */
export const ORDER_TYPE = { 10: '常规', 20: '市场', 30: '批量', 40: '委托', 50: '求购', 60: '转售', 70: '申购' }

/** 订单状态（与 C 端 Order.vue statusText 一致） */
export const ORDER_STATUS = { 0: '待付款', 1: '进行中', 2: '已取消', 3: '已完成', 4: '已退款' }

/** 挂单状态 */
export const SELL_STATUS = { 1: '在售', 2: '已成交' }

export default http
