/**
 * 密码加密工具（遗留签名保留）
 *
 * 后台切换为 BuildAdmin 后收明文密码（契约主权移交，RSA 加密弃用）。
 * 保留函数签名：SignUp / MyPage 等页面仍调用 encryptPassword()，透传即可。
 */

/** 与源站签名一致：现直接透传明文 */
export function encryptPassword(plain) {
  return plain
}
