<!--
  设置页 —— 复刻原站 /pages/my/setting/index
  原站分包：assets/pages-my-setting-index.DWFFR0Jq.js
  scopeId ：data-v-f354904a
  导航标题：设置（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. 「清除缓存」是纯假动画 —— showLoading('清除中...') → setTimeout 1000ms →
     hideLoading + showToast('清除成功')，没有任何实际的缓存清理逻辑。
  2. 原站定义但 render 从未引用的 go2Delete（注销账号入口）在本页不实现，
     因此全站没有任何入口能进入 /pages/my/setting/delete —— 这是原站真实状态。
  3. 「在线客服」跳转的 src 未做 encodeURIComponent，原样保留。
  4. 昵称弹窗与 userInfo 页共用同一套类名，但本页同名类的 CSS 值不同
     （本页 .setting-item 无 margin/背景色、label 非加粗），已按本页原文抄写。
  5. 手机号一行没有右箭头、无点击事件，原样保留。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="设置" />

    <div class="setting-container">
      <div class="user-info-section">
        <div class="setting-item" @click="openNickname">
          <span class="setting-item__label">昵称</span>
          <div class="setting-item__content">
            <span class="setting-item__value">{{ profile.nickname }}</span>
            <span class="iconfont icon-right" />
          </div>
        </div>
        <div class="setting-item" style="border-top: 1px solid rgb(230, 230, 230)">
          <span class="setting-item__label">手机号</span>
          <div class="setting-item__content">
            <span class="setting-item__value">{{ account.phone }}</span>
          </div>
        </div>
      </div>

      <div class="settings-section">
        <div class="setting-item" @click="go2Realname">
          <span class="setting-item__label">实名认证</span>
          <div class="setting-item__content">
            <span v-if="account.hasRealName" class="setting-name">已实名</span>
            <span class="iconfont icon-right" />
          </div>
        </div>
        <div
          class="setting-item"
          style="border-top: 1px solid rgb(230, 230, 230)"
          @click="go2SecondaryPassword"
        >
          <span class="setting-item__label">交易密码</span>
          <div class="setting-item__content">
            <span class="iconfont icon-right" />
          </div>
        </div>
      </div>

      <div class="settings-section">
        <div class="setting-item" @click="clearCache">
          <span class="setting-item__label">清除缓存</span>
        </div>
        <div
          class="setting-item"
          style="border-top: 1px solid rgb(230, 230, 230)"
          @click="go2About"
        >
          <span class="setting-item__label">关于我们</span>
        </div>
        <div
          class="setting-item"
          style="border-top: 1px solid rgb(230, 230, 230)"
          @click="go2Group"
        >
          <span class="setting-item__label">在线客服</span>
        </div>
      </div>

      <div class="logout-button" @click="signout">退出账号</div>

      <!-- 修改昵称弹窗 -->
      <div v-if="showPopup" class="nickname-modal" @touchmove.stop.prevent>
        <div class="nickname-modal__mask" @click="closeNickname" />
        <div class="nickname-modal__container">
          <div class="nickname-modal__header">修改昵称</div>
          <div class="nickname-modal__body">
            <input
              v-model="nicknameBody.nickname"
              class="nickname-input"
              type="text"
              placeholder="请输入昵称"
            />
          </div>
          <div class="nickname-modal__footer">
            <div class="modal-button modal-button--cancel" @click="closeNickname">取消</div>
            <div class="modal-button modal-button--confirm" @click="updateNickname">确认</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { userApi, passportApi } from '@/api'
import { showToast, showLoading, hideLoading } from '@/api/request'
import { storage, TOKEN_KEY } from '@/utils/storage'
import UniPageHead from '@/components/UniPageHead.vue'

const router = useRouter()

const profile = ref({})
const account = ref({})
const showPopup = ref(false)
const nicknameBody = reactive({ nickname: '' })

/** 原站 getProfile(){ ... e.status ? profile = e.data : e.code === 11111 && (profile = {}) } */
function getProfile() {
  userApi.getProfile().then((res) => {
    if (res.status) profile.value = res.data
    else if (res.code === 11111) profile.value = {}
  })
}

/** 原站 getAccount()：与 getProfile 同构，11111 置空 */
function getAccount() {
  passportApi.getAccount().then((res) => {
    if (res.status) account.value = res.data
    else if (res.code === 11111) account.value = {}
  })
}

/** 打开弹窗并回填当前昵称 */
function openNickname() {
  showPopup.value = true
  nicknameBody.nickname = (profile.value && profile.value.nickname) || ''
}

function closeNickname() {
  showPopup.value = false
}

function updateNickname() {
  if (!nicknameBody.nickname) return showToast('昵称不能为空')
  userApi.updateNickname({ nickname: nicknameBody.nickname }).then((res) => {
    if (res.status) {
      showToast('修改成功')
      profile.value.nickname = nicknameBody.nickname
      showPopup.value = false
    }
  })
}

function go2Realname() {
  router.push('/pages/my/setting/realname')
}

function go2SecondaryPassword() {
  router.push('/pages/my/setting/secondPassword')
}

/** 原站行为：假清除动画，无任何实际清理 */
function clearCache() {
  showLoading('清除中...')
  setTimeout(() => {
    hideLoading()
    showToast({ icon: 'success', title: '清除成功' })
  }, 1000)
}

function go2About() {
  router.push('/pages/my/setting/about')
}

function go2Group() {
  router.push('/pages/index/webView?title=客服&src=https://qm.qq.com/q/D7RxowRg9U')
}

function signout() {
  // 原站用 uni.showModal；本项目无 modal 封装，沿用项目内既有做法 window.confirm
  if (!window.confirm('确定退出登录')) return
  passportApi.signout().then((res) => {
    if (res.status) {
      storage.remove(TOKEN_KEY)
      showToast('退出成功')
      router.replace('/pages/index/index')
    }
  })
}

// 本页不在 keep-alive 白名单内，onMounted 等价于原站 onShow
onMounted(() => {
  getProfile()
  getAccount()
})
</script>

<style scoped>
/* 原站页面底色（body #f6f7fb）；本页 .setting-container 自身透明，故落到外层容器 */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f6f7fb;
}

/* 以下为 index-DDVOOQaQ.css 原文，值原样抄（已是 rem 不再换算；真 px 保留） */
.setting-container{min-height:100%;padding-top:1.25rem;position:relative;padding-bottom:10px;box-sizing:border-box;font-size:15px}
.user-info-section,.settings-section{background-color:#fff;margin:.625rem;overflow:hidden;box-shadow:0 2px 4px rgba(0,0,0,.1);border-radius:2px}
.setting-item{display:flex;justify-content:space-between;align-items:center;padding:.9375rem;position:relative}
.setting-item__label{font-size:.875rem;color:#4a4a4a}
.setting-item__content{display:flex;align-items:center}
.setting-item__value{font-size:.875rem;color:#222;margin-right:.3125rem}
.iconfont.icon-right{font-size:.875rem;color:#999}
.logout-button{margin:2.5rem .625rem 1.875rem;height:2.8125rem;display:flex;justify-content:center;align-items:center;border-radius:8px;font-size:16px;color:#fff;background:#2b52ef;font-weight:700}
.nickname-modal{position:fixed;top:0;left:0;right:0;bottom:0;z-index:999;display:flex;justify-content:center;align-items:center}
.nickname-modal__mask{position:absolute;top:0;left:0;right:0;bottom:0;background-color:rgba(0,0,0,.7)}
.nickname-modal__container{width:90%;background-color:#fff;border-radius:.625rem;overflow:hidden;position:relative;z-index:1000}
.nickname-modal__header{text-align:center;font-size:1rem;font-weight:700;padding:1.875rem 0}
.nickname-modal__body{padding:.9375rem}
.nickname-modal__footer{display:flex;padding:.625rem;justify-content:space-between}
.nickname-input{width:100%;height:2.8125rem;border:1px solid #f0f0f0;background-color:#f8f8f8;border-radius:1.40625rem;padding:0 .9375rem;box-sizing:border-box;font-size:.875rem}
.modal-button{flex:1;text-align:center;padding:.78125rem 0;font-size:.9375rem;border-radius:1.40625rem;margin:0 .46875rem}
.modal-button--cancel{background-color:#f8f8f8;color:#333}
.modal-button--confirm{background-color:#4478ff;color:#fff}
.setting-name{background:#4478ff;color:#fff;font-size:.5rem;padding:.125rem .5rem;border-radius:46px;margin-right:.5rem}
</style>
