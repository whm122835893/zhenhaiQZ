<!--
  账号信息页 —— 复刻原站 /pages/my/info/account
  原站分包：assets/pages-my-info-account.DtJViVGx.js
  scopeId ：data-v-08494975
  导航标题：账号信息（navigationBar 背景 #fff、标题色 #000 —— 与 UniPageHead 默认一致）

  刻意保留的原站瑕疵 / 细节：
  1. cacheSize 是原站硬编码的假数据 '45KB'，并未真正统计缓存；清除后写成 '0KB'。
  2. 手机号与链账户地址的掩码位数和用户ID 不同：手机号前 3 后 4（共 5 星）、
     地址前 6 后 4（共 6 星）—— 原站两个掩码函数是分别手写的，位数不一致，照抄。
  3. 「注册时间」一行的 .item-value 直接挂在 .item 下，没有 .item-right 包裹、
     也没有图标 —— 原站模板如此。
  4. 三个 copy 方法都不传第 2 个参数，所以统一显示「复制成功」；值为空时静默
     无操作（原站没有 toast），不额外补提示。
  5. 本页零跳转 —— 清除缓存是假动作，只 loading + 改文案 + toast。
  6. 原站把「清除缓存」放在账号信息页（而非设置页），照抄。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="账号信息" />

    <div class="account-container">
      <div class="item" @click="copyPhone">
        <span class="item-label">手机号</span>
        <div class="item-right">
          <span class="item-value">{{ maskPhone }}</span>
          <span class="iconfont icon-copy item-icon" />
        </div>
      </div>
      <div class="item" @click="copyUid">
        <span class="item-label">用户ID</span>
        <div class="item-right">
          <span class="item-value">{{ uid || '---' }}</span>
          <span class="iconfont icon-copy item-icon" />
        </div>
      </div>
      <div class="item" @click="copyAddress">
        <span class="item-label">链账户地址</span>
        <div class="item-right">
          <span class="item-value">{{ maskAddress }}</span>
          <span class="iconfont icon-copy item-icon" />
        </div>
      </div>
      <div class="item">
        <span class="item-label">注册时间</span>
        <span class="item-value">{{ timeFormat(registerTime) || '---' }}</span>
      </div>
      <div class="item" @click="clearCache">
        <span class="item-label">清除缓存</span>
        <div class="item-right">
          <span class="item-value">{{ cacheSize }}</span>
          <span class="iconfont icon-right item-icon" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { userApi, passportApi } from '@/api'
import { showToast, showLoading, hideLoading, copy } from '@/api/request'
import { timeFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'

const profile = ref({})
const account = ref({})
/** 原站硬编码的假缓存体积，非真实统计 */
const cacheSize = ref('45KB')

function getProfile() {
  userApi.getProfile().then((res) => {
    if (res.status) profile.value = res.data
    else if (res.code === 11111) profile.value = {}
  })
}

function getAccount() {
  passportApi.getAccount().then((res) => {
    if (res.status) account.value = res.data
    else if (res.code === 11111) account.value = {}
  })
}

const phone = computed(
  () => account.value.phone || account.value.mobile || profile.value.phone || ''
)
const maskPhone = computed(() => {
  const p = phone.value
  return p ? (p.length < 7 ? p : p.slice(0, 3) + '*****' + p.slice(-4)) : '---'
})

const uid = computed(
  () => profile.value.uid || profile.value.userId || profile.value.id || ''
)

const address = computed(
  () =>
    profile.value.address ||
    profile.value.chainAddress ||
    profile.value.walletAddress ||
    profile.value.accountAddress ||
    ''
)
const maskAddress = computed(() => {
  const a = address.value
  return a ? (a.length <= 10 ? a : a.slice(0, 6) + '******' + a.slice(-4)) : '---'
})

const registerTime = computed(
  () =>
    profile.value.createTime ||
    profile.value.registerTime ||
    profile.value.createTimeStr ||
    profile.value.registerTimeStr ||
    ''
)

/** 空值时静默返回，不弹提示（原站行为） */
function copyPhone() {
  if (phone.value) copy(phone.value)
}

function copyUid() {
  if (uid.value) copy(String(uid.value))
}

function copyAddress() {
  if (address.value) copy(address.value)
}

/** 原站假清除：loading 600ms 后把文案改成 0KB */
function clearCache() {
  showLoading('清除中...')
  setTimeout(() => {
    hideLoading()
    cacheSize.value = '0KB'
    showToast({ icon: 'success', title: '清除成功' })
  }, 600)
}

// 本页不在 App.vue 的 keep-alive 白名单内，onMounted 即等价于原站 onShow
onMounted(() => {
  getProfile()
  getAccount()
})
</script>

<style scoped>
/* 原站 uni-page-body + body：background-color:#f6f7fb */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f6f7fb;
}

/* 以下为 account-BceSRO21.css 全文，值原样抄 */
.account-container{min-height:100vh;padding:.75rem .9375rem 0;box-sizing:border-box}
.item{height:3.25rem;background-color:#fff;border-radius:.75rem;padding:0 .75rem;margin-bottom:.75rem;display:flex;flex-direction:row;align-items:center;justify-content:space-between}
.item-label{font-size:.875rem;font-weight:500;color:rgba(0,0,0,.9)}
.item-right{display:flex;flex-direction:row;align-items:center}
.item-value{font-size:.875rem;font-weight:400;color:#666}
.item-icon{font-size:.75rem;color:#666;margin-left:.375rem}
</style>
