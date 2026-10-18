/** 静态资源基址：本地 public/static 已从原站下载，部署时可直接替换为 CDN 前缀 */
export const STATIC_BASE = ''

export function baseUrl(path) {
  if (!path) return ''
  if (/^https?:\/\//.test(path)) return path
  // 路径已以 /static 开头则不再重复拼接前缀
  if (path.startsWith('/static/')) return path
  return STATIC_BASE + (path.startsWith('/') ? path : '/' + path)
}