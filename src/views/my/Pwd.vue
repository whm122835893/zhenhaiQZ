<!--
  账号安全页 —— 复刻原站 /pages/my/info/pwd
  原站分包：assets/pages-my-info-pwd.BNNSoxlQ.js
  scopeId ：data-v-283bc8b1
  导航标题：账号安全

  刻意保留的原站瑕疵 / 细节：
  1. 本页背景只挂在 .pwd-page 上，原站没有 uni-page-body / body 的背景规则 ——
     与个人资料 / 账号信息 / 收货地址三页不同，照抄，故外层容器不带背景色。
  2. 页面一进来确实会发 getProfile + getAccount 两个请求，但结果没有任何人消费
     （原站 data 里的 profile / account / showPopup / nicknameBody 全是死变量），
     为忠实复刻仍保留这两个请求。
  3. 「设备保护功能」一行多一个 .pwd-card__status，且位于 .pwd-card__arrow 之前；
     deviceProtectText 是常量 '未开启'，不随接口变化。
  4. 设备保护 / 隐私设置两项不跳转，只弹「功能开发中」。
  5. 方法名 go2destoryAccount 是原站拼写错误（destory，少个 r），照抄不改。
  6. 原站另有 11 个 DEAD 方法（复制自其他页残留），render 里从未引用，不实现。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="账号安全" />

    <div class="pwd-page">
      <div class="pwd-card" @click="go2LoginPassword">
        <span class="pwd-card__label">修改登录密码</span>
        <div class="pwd-card__right">
          <span class="iconfont icon-right pwd-card__arrow" />
        </div>
      </div>
      <div class="pwd-card" @click="go2SecondaryPassword">
        <span class="pwd-card__label">修改操作密码</span>
        <div class="pwd-card__right">
          <span class="iconfont icon-right pwd-card__arrow" />
        </div>
      </div>
      <div class="pwd-card" @click="go2DeviceProtect">
        <span class="pwd-card__label">设备保护功能</span>
        <div class="pwd-card__right">
          <span class="pwd-card__status">{{ deviceProtectText }}</span>
          <span class="iconfont icon-right pwd-card__arrow" />
        </div>
      </div>
      <div class="pwd-card" @click="go2Privacy">
        <span class="pwd-card__label">隐私设置</span>
        <div class="pwd-card__right">
          <span class="iconfont icon-right pwd-card__arrow" />
        </div>
      </div>
      <div class="pwd-card" @click="go2destoryAccount">
        <span class="pwd-card__label">注销账户</span>
        <div class="pwd-card__right">
          <span class="iconfont icon-right pwd-card__arrow" />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { userApi, passportApi } from '@/api'
import { showToast } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

const router = useRouter()

/** 原站常量，不随接口变化 */
const deviceProtectText = ref('未开启')

/** 原站确实发了这个请求，但结果没人消费 —— 忠实保留 */
function getProfile() {
  userApi.getProfile()
}

/** 同上 */
function getAccount() {
  passportApi.getAccount()
}

function go2LoginPassword() {
  router.push('/pages/authorize/password')
}

function go2SecondaryPassword() {
  router.push('/pages/my/setting/secondPassword')
}

/** 原站不跳转，只弹提示 */
function go2DeviceProtect() {
  showToast('功能开发中')
}

function go2Privacy() {
  showToast('功能开发中')
}

/** destory 为原站拼写，照抄 */
function go2destoryAccount() {
  router.push('/pages/my/setting/delete')
}

// 本页不在 App.vue 的 keep-alive 白名单内，onMounted 即等价于原站 onShow
onMounted(() => {
  getProfile()
  getAccount()
})
</script>

<style scoped>
/* 本页原站没有 uni-page-body 背景规则，仅撑高 */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
}

/* 以下为 pwd-Da7_fHq_.css 全文，值原样抄 */
.pwd-page{min-height:100%;background-color:#f6f7fb;box-sizing:border-box}
.pwd-card{display:flex;justify-content:space-between;align-items:center;height:3.25rem;margin:.75rem .75rem 0;padding:0 .75rem;background-color:#fff;border-radius:.75rem;box-sizing:border-box}
.pwd-card__label{font-size:.875rem;font-weight:500;color:rgba(0,0,0,.9)}
.pwd-card__right{display:flex;align-items:center}
.pwd-card__status{font-size:.875rem;color:red;margin-right:.5rem}
.pwd-card__arrow{font-size:.875rem;color:#999}
</style>
