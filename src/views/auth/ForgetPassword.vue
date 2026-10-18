<template>
  <div class="container" :style="{ '--pwd-bg': `url(${IMG.bg})` }">
    <div class="status-bar" />

    <!-- 顶部返回（原站为 uni-navigator.back-nav，内部 uni-image.back-icon） -->
    <div class="back-nav" @click="goBack">
      <img class="back-icon" :src="IMG.back" alt="" />
    </div>

    <div style="padding: 0 0.9375rem">
      <div class="form-container">
        <!-- 手机号 -->
        <div class="input-item">
          <div class="input-title">
            <img :src="IMG.iconPhone" alt="" />
          </div>
          <input
            v-model="body.phone"
            class="input"
            type="number"
            maxlength="11"
            placeholder="请输入手机号"
            placeholder-class="placeholder"
          />
        </div>

        <!-- 验证码 -->
        <div class="input-item">
          <div class="input-title">
            <UvIcon name="chat" :size="23" color="rgb(111, 113, 115)" />
          </div>
          <input
            v-model="body.captcha"
            class="input code-input"
            type="number"
            maxlength="4"
            placeholder="请输入验证码"
            placeholder-class="placeholder"
          />
          <div class="get-code-btn" @click="openImageCaptcha">
            {{ countdown > 0 ? countdown + 's' : '获取验证码' }}
          </div>
        </div>

        <!-- 新密码 -->
        <div class="input-item">
          <div class="input-title">
            <img :src="IMG.iconPwd" alt="" />
          </div>
          <input
            v-model="body.password"
            class="input"
            type="password"
            maxlength="140"
            placeholder="请输入新密码"
            placeholder-class="placeholder"
          />
        </div>

        <!-- 确认新密码 -->
        <div class="input-item">
          <div class="input-title">
            <img :src="IMG.iconPwd" alt="" />
          </div>
          <input
            v-model="body.confirmPassword"
            class="input"
            type="password"
            maxlength="140"
            placeholder="请确认新密码"
            placeholder-class="placeholder"
          />
        </div>

        <!-- 确认找回 -->
        <div class="login-btn" @click="resetPassword">
          <div style="margin-right: 0.375rem">确认找回</div>
          <div class="btn-arrow">
            <UvIcon name="arrow-rightward" :size="10" color="#ffffff" />
          </div>
        </div>

        <!-- 返回登录 -->
        <div class="switch-login" style="margin-top: 0.625rem; justify-content: center">
          <div style="color: rgb(136, 144, 162); font-size: 13px">
            <span class="switch-text" @click="switchToLogin">返回登录</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 找回密码页 —— 1:1 复刻原站 /pages/authorize/password
 * 样式数值全部取自原站 password-CkS9DlBj.css，未作改动
 * 交互逻辑取自原站 pages-authorize-password.DOmHYsDo.js
 *
 * 注意：原站此页无协议勾选行、无导航标题栏，仅一个返回箭头。
 */
import { ref, reactive, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import UvIcon from '@/components/UvIcon.vue'
import { passportApi } from '@/api'
import { encryptPassword } from '@/utils/crypto'
import { showLoading, hideLoading } from '@/api/request'

import bgImg from '@/assets/password/bg.jpg'
import backImg from '@/assets/signup/back.png'
import iconPhoneImg from '@/assets/signup/icon-phone.png'
import iconPwdImg from '@/assets/signup/icon-pwd.png'

const IMG = { bg: bgImg, back: backImg, iconPhone: iconPhoneImg, iconPwd: iconPwdImg }

const router = useRouter()

/* ---------- 原站 data ---------- */
const body = reactive({ phone: '', captcha: '', password: '', confirmPassword: '' })
const showCaptchaModal = ref(false)
const smsCaptchaBody = reactive({ phone: '', type: 201, imageCaptchaKey: '', imageCaptcha: '' })
const imageCaptchaUrl = ref('')
const countdown = ref(0)
let timer = null

/* ---------- 原站 methods ---------- */
function goBack() {
  window.history.length > 1 ? router.back() : router.replace('/pages/authorize/signin')
}

function openImageCaptcha() {
  if (!body.phone || body.phone.length !== 11) return showToast('请输入正确的手机号')
  if (countdown.value > 0) return
  showCaptchaModal.value = true
}

function refreshImageCaptcha() {
  passportApi.getImageCaptcha({ type: 201 }).then((res) => {
    if (res.status) {
      smsCaptchaBody.imageCaptchaKey = res.data.key
      imageCaptchaUrl.value = res.data.base64
    }
  })
}

function getSmsCaptcha(captchaVerifyParam) {
  passportApi
    .getSmsCaptcha({ type: 201, phone: body.phone, captchaVerifyParam })
    .then((res) => {
      if (res.status) {
        showCaptchaModal.value = false
        startCountdown()
      }
    })
}

function startCountdown() {
  countdown.value = 60
  timer = setInterval(() => {
    if (countdown.value > 0) countdown.value--
    else clearInterval(timer)
  }, 1000)
}

/** 原站 resetPassword() —— 校验顺序与文案严格照搬（7 道） */
function resetPassword() {
  if (!body.phone) return showToast('手机号不能为空')
  if (!body.captcha) return showToast('验证码不能为空')
  if (!body.password) return showToast('新密码不能为空')
  if (body.password.length < 6) return showToast('密码长度不能少于6位')
  if (!body.confirmPassword) return showToast('请确认新密码')
  if (body.password !== body.confirmPassword) return showToast('两次输入的密码不一致')

  showLoading()
  passportApi
    .updatePassword({
      phone: body.phone,
      captcha: body.captcha,
      password: encryptPassword(body.password),
    })
    .then((res) => {
      hideLoading()
      if (res.status) {
        showToast('密码重置成功')
        // 原站：1.5s 后 reLaunch 回登录页
        setTimeout(() => router.replace('/pages/authorize/signin'), 1500)
      }
    })
    .catch(() => hideLoading())
}

function switchToLogin() {
  router.replace('/pages/authorize/signin')
}

onUnmounted(() => {
  timer && clearInterval(timer)
  timer = null
})

defineExpose({ showCaptchaModal, imageCaptchaUrl, refreshImageCaptcha, getSmsCaptcha })
</script>

<style scoped>
/* ============ 页面容器 ============ */
.container {
  position: relative;
  width: 100%;
  min-height: 100vh;
  box-sizing: border-box;
  background-image: var(--pwd-bg);
  background-size: 100% 100%;
  background-repeat: no-repeat;
  /* 原站 uni-page-body 基线：font-size:16px / color:#000 */
  font-size: 16px;
  color: #000;
  padding-top: 0.9375rem;
  padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));
}
.status-bar { height: var(--status-bar-height, 0px); width: 100%; }

/* 原站：<uni-navigator class="back-nav"><uni-image class="back-icon" style="height:26px"> */
.back-nav { display: inline-block; cursor: pointer; }
.back-icon { width: 1.5625rem; height: 26px; margin-left: 0.625rem; display: block; }

/* ============ 表单卡片 ============ */
.form-container {
  margin-top: 18.125rem;
  background-color: #f9fcff;
  border-radius: 1.5625rem;
  padding: 0.9375rem;
}
/* 原站此页 input-item 高度为 3.125rem（注册页为 3.28125rem），不可统一 */
.input-item {
  width: 100%;
  height: 3.125rem;
  box-sizing: border-box;
  display: flex;
  margin-bottom: 12px;
  align-items: center;
  background-color: #edf4fc;
  padding: 0.9375rem;
  border-radius: 1.5625rem;
}
.input-title {
  display: flex;
  align-items: center;
  margin-right: 0.9375rem;
  color: #666;
  font-size: 15px;
  font-weight: 500;
}
.input-title img { width: 20px; height: 20px; margin-right: 6px; display: block; }
.input {
  margin-left: 0;
  flex: 1;
  color: #000;
  font-size: 14px;
  line-height: 19.6px;
  text-align: left;
  border: none;
  outline: none;
  background: transparent;
  min-width: 0;
}
.placeholder { font-size: 14px; color: #717689; }
.input::placeholder { font-size: 14px; color: #717689; }

/* 验证码行 */
.code-input { flex: 1; }
.get-code-btn {
  box-sizing: border-box;
  font-family: 'PingFang SC';
  line-height: 22px;
  font-size: 12px;
  color: #1952f8;
  margin-left: 8px;
  white-space: nowrap;
  cursor: pointer;
}

/* ============ 确认找回按钮 ============ */
.login-btn {
  margin-top: 1.875rem;
  border-radius: 28px;
  font-weight: 600;
  font-size: 14px;
  width: 100%;
  height: 50px;
  background: linear-gradient(to right, #57abfd, #4391fc);
  color: #fff;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.btn-arrow {
  background-color: rgb(86, 163, 252);
  border-radius: 50%;
  padding: 0.375rem;
  display: flex;
  justify-content: center;
  align-items: center;
}

/* ============ 返回登录 ============ */
.switch-login { display: flex; justify-content: space-between; align-items: center; height: 40px; }
.switch-text {
  margin-top: 0.46875rem;
  cursor: pointer;
  color: #3578fc;
  font-size: 13px;
}

/* 以下样式原站有定义但当前版本未渲染，保留备用 */
.password-item { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
.password-input { flex: 1; }
.toggle-password-btn {
  color: #999;
  font-size: 0.875rem;
  padding-left: 0.625rem;
  white-space: nowrap;
  cursor: pointer;
}
.tip { color: #8890a2; font-size: 12px; line-height: 18px; }
.agreement { display: flex; align-items: flex-start; text-align: center; margin-top: 1.5625rem; }
.agreement-text { font-size: 11px; color: #4a4a4a; margin-left: 0.3125rem; }
.link { font-size: 12px; color: #3578fc; }
.title-style { color: #222; font-size: 24px; font-weight: 600; margin-bottom: 31px; }
</style>