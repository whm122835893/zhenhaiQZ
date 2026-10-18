<!--
  修改操作密码页 —— 复刻原站 /pages/my/setting/secondPassword
  原站分包：assets/pages-my-setting-secondPassword.ClQlf0hD.js
  scopeId ：data-v-2e6f573d
  导航标题：修改操作密码（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. 顶部有个 10rpx 的空占位 div（无 class、无内容），原样保留。
  2. 获取验证码原站走阿里云验证码组件（Captcha，SceneId 1qc22sf6 / prefix 1twyi4 /
     region cn），依赖外部脚本与后端配对，H5 复刻无法原样跑通；此处降级为直接调
     passportApi.getSmsCaptcha({ type: 202 }) 再启动倒计时 —— 换的只是获取通道，
     UI 与倒计时行为完全一致。
  3. secondPassword 走 RSA 加密（复用项目 @/utils/crypto 的 encryptPassword），
     captcha 不加密。
  4. placeholder-class="ph" 是 uni-app 给 placeholder 单独挂 class 的能力，
     普通 input 做不到，改用 ::placeholder 选择器实现同样效果。
  5. 原站的 refreshImageCaptcha 是 DEAD 方法，不实现。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="修改操作密码" />

    <div class="container">
      <!-- 原站顶部 10rpx 空占位（= .3125rem），原样保留 -->
      <div style="height: 0.3125rem" />

      <div class="card">
        <div class="card-row">
          <input
            v-model="body.secondPassword"
            class="card-input"
            :type="showPwd ? 'text' : 'password'"
            inputmode="numeric"
            maxlength="6"
            placeholder="操作密码，请输入6位数字"
          />
          <img class="eye" :src="pwdEye" alt="" @click="togglePwd" />
        </div>
        <div class="card-row">
          <input
            v-model="body.captcha"
            class="card-input"
            type="number"
            maxlength="4"
            placeholder="请输入验证码"
          />
          <div class="code-btn" @click="openImageCaptcha">
            {{ countdown > 0 ? countdown + 's' : '获取验证码' }}
          </div>
        </div>
      </div>

      <div class="submit-btn" @click="updatePassword">
        <span>提交</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { passportApi } from '@/api'
import { showToast } from '@/api/request'
import { encryptPassword } from '@/utils/crypto'
import UniPageHead from '@/components/UniPageHead.vue'
import pwdEye from '@/assets/my/pwd-eye.png'

const router = useRouter()

const body = reactive({ captcha: '', secondPassword: '' })
const showPwd = ref(false)
const countdown = ref(0)
let timer = null

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
})

function togglePwd() {
  showPwd.value = !showPwd.value
}

function startCountdown() {
  countdown.value = 60
  timer = setInterval(() => {
    countdown.value--
    if (countdown.value <= 0) {
      clearInterval(timer)
      timer = null
    }
  }, 1000)
}

/** 原站走阿里云验证码组件，此处等价降级为直接取短信验证码（UI / 倒计时行为一致） */
function openImageCaptcha() {
  if (countdown.value > 0) return
  passportApi.getSmsCaptcha({ type: 202 }).then((res) => {
    if (res.status) startCountdown()
  })
}

function updatePassword() {
  if (!body.captcha) return showToast('验证码不能为空')
  if (!body.secondPassword) return showToast('密码不能为空')
  if (body.secondPassword.length !== 6) return showToast('请输入6位数字密码')
  passportApi
    .updateSecondPassword({
      secondPassword: encryptPassword(body.secondPassword),
      captcha: body.captcha,
    })
    .then((res) => {
      if (res.status) {
        showToast('修改成功')
        router.back()
      }
    })
}
</script>

<style scoped>
/* 原站页面底色（body #f6f7fb） */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f6f7fb;
}

/* 以下为 secondPassword-BnKnvtQX.css 原文，值原样抄（已是 rem，不做换算；真 px 保留） */
.container{min-height:100%;background:#f6f7fb;box-sizing:border-box}
.card{background:#fff;border-radius:.75rem;margin:.3125rem 1rem 1.375rem;padding:0 .875rem;box-sizing:border-box}
.card-row{display:flex;align-items:center;height:3.4375rem}
.card-row:first-child{border-bottom:1px solid #f3f4f8}
.card-input{flex:1;height:100%;font-size:.9375rem;color:#333}
.eye{width:1.1875rem;height:1.1875rem;margin-left:.625rem;flex-shrink:0}
.code-btn{flex-shrink:0;margin-left:.625rem;height:1.8125rem;line-height:1.8125rem;padding:0 .75rem;background:#3578fc;border-radius:.90625rem;color:#fff;font-size:.75rem;white-space:nowrap}
.submit-btn{width:17.1875rem;height:2.8125rem;margin:0 auto;background-color:#3578fc;border-radius:1.5625rem;display:flex;justify-content:center;align-items:center;color:#fff;font-size:.9375rem}

/* 原站 placeholder-class="ph" 的等价实现：uni 专有能力，Vue 普通 input 用 ::placeholder 还原 */
input.card-input::placeholder{color:#c0c4cc;font-size:.875rem}
</style>
