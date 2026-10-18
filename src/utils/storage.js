/**
 * 存储封装 —— 完全对齐原站 uni.setStorageSync 的数据格式
 * 原站格式：{"type":"string","data":"xxx"}
 */
const PREFIX = 'zh:'

export const storage = {
  set(key, value) {
    const type = typeof value
    localStorage.setItem(PREFIX + key, JSON.stringify({ type, data: value }))
  },
  get(key) {
    const raw = localStorage.getItem(PREFIX + key)
    if (!raw) return ''
    try {
      const parsed = JSON.parse(raw)
      return parsed && 'data' in parsed ? parsed.data : ''
    } catch (e) {
      return ''
    }
  },
  remove(key) {
    localStorage.removeItem(PREFIX + key)
  },
  clear() {
    Object.keys(localStorage)
      .filter((k) => k.startsWith(PREFIX))
      .forEach((k) => localStorage.removeItem(k))
  },
}

export const TOKEN_KEY = 'token'
export const BG_IMG_KEY = 'bgImg'