/**
 * 原站全局 $fmt（挂载在 app.vue 的 $fmt）—— 金额与时间格式化
 * 对齐原站 h5.shengtai.cyou 的 util.Duw9LX6a.js
 */

/**
 * amountFormat —— 金额格式化
 * 原站逻辑：传入的分（cent）除以 100，保留 2 位小数。
 * 例：amountFormat(6990) => "69.90"；页面拼接前缀 ￥ 后显示为 ￥69.90
 * 关键：接口返回的 price 单位是「分」，不是「元」。
 * @param {number|string|null|undefined} fen 分
 * @returns {string} 形如 "69.90"；入参为 0 / '' / null / undefined 时返回 "0.00"
 */
export function amountFormat(fen) {
  const n = Number(fen)
  if (!Number.isFinite(n)) return '0.00'
  return (n / 100).toFixed(2)
}

/**
 * 原站主包 index-4EAmbO53.js 中的 $fmt 精确实现：
 *   amountFormat(e)        => e ? "￥" + Number(e/100).toFixed(2) : "￥--"
 *   amountFormatMarket(e)  => e ? Number(e/100).toFixed(0) : "-"
 *   chainFormat(e, t=4)    => e ? e.slice(0,t) + "*****" + e.slice(-4) : ""
 *   timeFormat(e, t)       => e ? dayjs(parseInt(e)).format(t || "yyyy-MM-DD HH:mm:ss") : ""
 *   calculateHoldingDays(e)=> Math.floor((Date.now()-e)/864e5)
 *
 * 注意：
 *  1) 原站 amountFormat 自带全角「￥」前缀，且空值返回 "￥--"（不是 "0.00"）
 *  2) 原站 timeFormat 默认 pattern 是 "yyyy-MM-DD HH:mm:ss"（dayjs 大写 DD = 日）
 *  3) 原站 chainFormat 固定 5 个星号
 */
export function amountFormatYen(fen) {
  return fen ? '￥' + Number(fen / 100).toFixed(2) : '￥--'
}

/** amountFormatMarket —— 市场概览用，整数元、无前缀 */
export function amountFormatMarket(fen) {
  return fen ? Number(fen / 100).toFixed(0) : '-'
}

/** chainFormat —— 前 4 位 + ***** + 后 4 位（星号固定 5 个） */
export function chainFormat(v, len = 4) {
  const s = String(v ?? '')
  return s ? s.slice(0, len) + '*****' + s.slice(-4) : ''
}

/** calculateHoldingDays —— 持有天数 */
export function calculateHoldingDays(ts) {
  return Math.floor((Date.now() - ts) / 864e5)
}

/**
 * timeFormat —— 时间格式化（dayjs 风格）
 * 原站用 dayjs，支持 yyyy-MM-dd HH:mm:ss 与 yyyy/MM/dd 等
 * @param {string|number|Date} v
 * @param {string} pattern 默认 'yyyy-MM-DD HH:mm:ss'（dayjs：大写 DD 为日）
 */
export function timeFormat(v, pattern = 'yyyy-MM-DD HH:mm:ss') {
  if (v === null || v === undefined || v === '') return ''
  const d = v instanceof Date ? v : new Date(Number(v) < 1e11 ? Number(v) * 1000 : Number(v))
  if (Number.isNaN(d.getTime())) return ''
  const p2 = (n) => String(n).padStart(2, '0')
  const map = {
    yyyy: d.getFullYear(),
    MM: p2(d.getMonth() + 1),
    dd: p2(d.getDate()),
    DD: p2(d.getDate()),
    HH: p2(d.getHours()),
    mm: p2(d.getMinutes()),
    ss: p2(d.getSeconds()),
  }
  return pattern.replace(/yyyy|MM|dd|DD|HH|mm|ss/g, (k) => map[k])
}

export default { amountFormat, amountFormatYen, amountFormatMarket, chainFormat, timeFormat, calculateHoldingDays }