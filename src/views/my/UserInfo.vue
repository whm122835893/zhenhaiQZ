<!--
  个人资料页 —— 复刻原站 /pages/my/info/userInfo
  原站分包：assets/pages-my-info-userInfo.h05lQF30.js
  scopeId ：data-v-ad968208
  导航标题：个人资料（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. 根节点外层包一个 class="uni-page-body" 的容器 —— 原站该页 CSS 同时给
     uni-page-body 与 body 设了 background-color:#f6f7fb，本项目 body 是全局
     token（#f5f7fa），故把该背景落到这个容器上，高度取 100vh 保证铺满。
  2. 昵称弹窗用 v-if 控制、遮罩与按钮都是 div（不是 button），原样保留。
  3. 手机号一行没有右箭头、没有点击事件 —— 原站就是这样。
  4. 原站还残留 7 个 DEAD 方法（go2Realname / go2SecondaryPassword / clearCache /
     go2About / go2Group / go2Delete / signout），是复制 setting 页留下的，
     render 里从未引用，故不实现。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="个人资料" />

    <div class="setting-container">
      <div class="user-info-section">
        <div class="setting-item" @click="openNickname">
          <span class="setting-item__label">昵称</span>
          <div class="setting-item__content">
            <span class="setting-item__value">{{ profile.nickname }}</span>
            <span class="iconfont icon-right" />
          </div>
        </div>
        <div class="setting-item">
          <span class="setting-item__label">手机号</span>
          <div class="setting-item__content">
            <span class="setting-item__value">{{ account.phone }}</span>
          </div>
        </div>
      </div>

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
import { userApi, passportApi } from '@/api'
import { showToast } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

const profile = ref({})
const account = ref({})
const showPopup = ref(false)
const nicknameBody = reactive({ nickname: '' })

/** 原站 getProfile(){ ... .then(e => e.status ? profile = e.data : e.code === 11111 && (profile = {})) } */
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

/* 以下为 userInfo-EXABA83e.css 原文，值原样抄（已是 rem，不再换算） */
.setting-container{min-height:100%;position:relative;padding-bottom:10px;box-sizing:border-box;font-size:15px}
.user-info-section,.settings-section{margin:.625rem;overflow:hidden;border-radius:2px}
.setting-item{display:flex;justify-content:space-between;align-items:center;padding:1.25rem .9375rem;position:relative;background-color:#fff;margin-bottom:.625rem;border-radius:.625rem}
.setting-item__label{font-weight:700;color:#333;font-size:14px}
.setting-item__content{display:flex;align-items:center}
.setting-item__value{font-size:.875rem;color:#222;margin-right:.3125rem}
.iconfont.icon-right{font-size:.875rem;color:#999}
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
</style>
