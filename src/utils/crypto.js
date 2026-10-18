import JSEncrypt from 'jsencrypt'
import { isLocalApi } from '@/api/request'

/**
 * RSA 公钥 —— 两套，按当前后端自动选用
 *
 * 源站公钥：提取自源站 encrypt.LOUqGGe9.js。配对私钥在源站服务器，
 *           本地无法解密 —— 只能连源站时用。
 * 本地公钥：由 `php server/shengtai-api/scripts/export_public_key.php` 从
 *           .env 的 RSA_PUBLIC_KEY 导出，与后端私钥同对。
 *           连本地后端时必须用它，否则密码登录必然解密失败。
 *
 * 切换后端时 request.js 的 switchApi() 会调 resetEncryptor()，
 * 保证加密器实例与 baseURL 同步，不会出现「用本地后端 + 源站公钥」的错配。
 */

/** 源站公钥（connect 源站时使用） */
const SOURCE_PUBLIC_KEY =
  'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjFR8H0pD0tKa+LjbA57ytUjva9azNOWmukXCa+HGk5tFD6IPfsuzJ73LD562EMOMGgKLu/gLZcsgVByjPjwodZ75LWSoN/CWKEYE0YfN84P7IY0z3KoK1sorNVHL+X6U6KIfaoPNanChfpOCmKTLtPmANgetuHY9yNGwtaACDWT9VGezz3t8MOm30lsRHH0thQP/BJvwMLbkVxox6oliFzsQJCAur0uehXpNr8R0dacITDfKhYbDCmwEjc4Xca81oOJdqd+Itr3QV9ZPeHVRGxWeU+azDzOs8KrqbRQ11kBarFX5bFslVyh4v7jHuYkcUpU1g74zUyPMRFyKnsbEKQIDAQAB'

/** 本地后端公钥（配 server/shengtai-api/.env 的 RSA_PUBLIC_KEY） */
const LOCAL_PUBLIC_KEY = import.meta.env.VITE_RSA_PUBLIC_KEY || ''

let warned = false

function currentKey() {
  if (!isLocalApi()) {
    return SOURCE_PUBLIC_KEY
  }

  if (!LOCAL_PUBLIC_KEY && !warned) {
    warned = true
    console.warn(
      '[crypto] 当前连本地后端，但未配置 VITE_RSA_PUBLIC_KEY，' +
        '密码登录 / 二级密码 / 改密会失败。' +
        '请执行 php server/shengtai-api/scripts/export_public_key.php ' +
        '拿到公钥后填入 .env 的 VITE_RSA_PUBLIC_KEY。'
    )
  }

  return LOCAL_PUBLIC_KEY || SOURCE_PUBLIC_KEY
}

let encryptor = null
let cachedKey = ''

function getEncryptor() {
  const key = currentKey()
  if (!encryptor || cachedKey !== key) {
    encryptor = new JSEncrypt()
    encryptor.setPublicKey(key)
    cachedKey = key
  }
  return encryptor
}

/**
 * 切后端后丢弃缓存的加密器实例（由 request.js 的 switchApi() 调用）。
 * 不清的话会出现「刚 useSource()，加密还用的是本地公钥」的错配。
 */
export function resetEncryptor() {
  encryptor = null
  cachedKey = ''
}

/** 与源站 encrypt.encrypt() 行为一致：RSA 加密密码 */
export function encryptPassword(plain) {
  return getEncryptor().encrypt(plain)
}

/** 调试用：当前生效的公钥来源 */
export function currentKeySource() {
  return isLocalApi() ? 'local' : 'source'
}