import { reactive } from 'vue'

/**
 * 全局 loading 层（等价原站 uni.showLoading / uni.hideLoading）
 * 原站为自绘遮罩：rgba(0,0,0,0.7) 全屏 + 居中白色 spinner + 文案。
 * 不用 Vant Toast——多个并发 Toast 实例时 type:'reset' 会打在错误实例上，
 * 导致遮罩永久残留（表现为页面中间一块黑色方块）。
 */
export const state = reactive({
  visible: false,
  message: '加载中...',
})

let manual = 0
let pending = 0

function sync() {
  state.visible = manual > 0 || pending > 0
}

export function showLoading(title) {
  manual++
  if (title) state.message = title
  sync()
}

export function hideLoading() {
  manual = Math.max(0, manual - 1)
  sync()
}

/** 在途请求计数（由 axios 拦截器调用） */
export function trackPending(delta) {
  pending = Math.max(0, pending + delta)
  sync()
}

/** 路由切换等场景强制复位，避免残留 */
export function resetLoading() {
  manual = 0
  pending = 0
  sync()
}