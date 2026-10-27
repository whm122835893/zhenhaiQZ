<template>
  <div class="container" :style="{ '--sg-bg': `url(${IMG.bg})` }">
    <div class="status-bar" />

    <!-- 顶部返回按钮（原站为 50rpx×50rpx 图片，onClick 走 goBack / navigateBack delta:1） -->
    <img class="back-btn" :src="IMG.back" alt="" @click="goBack" />

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

        <!-- 密码（navCurrent === 1 时显示） -->
        <div v-if="navCurrent === 1" class="input-item">
          <div class="input-title">
            <img :src="IMG.iconPwd" alt="" />
          </div>
          <input
            v-model="body.password"
            class="input password-input"
            :type="showPassword ? 'text' : 'password'"
            maxlength="140"
            placeholder="请输入密码"
            placeholder-class="placeholder"
            @keyup.enter="signin"
          />
        </div>

        <!-- 验证码（navCurrent === 0 时显示） -->
        <div v-if="navCurrent === 0" class="input-item">
          <div class="input-title" style="font-size: 14px; font-weight: 400">验证码</div>
          <div class="code-row">
            <input
              v-model="body.captcha"
              class="input code-input"
              type="number"
              maxlength="4"
              placeholder="请输入验证码"
              placeholder-class="placeholder"
            />
            <div class="get-code-btn" @click="openImageCaptcha">{{ countdown > 0 ? countdown + 's' : '获取验证码' }}</div>
          </div>
        </div>

        <!-- 切换注册 / 忘记密码（原站为同一行 switch-login 内） -->
        <div class="switch-login">
          <div style="color: rgb(136, 144, 162); font-size: 13px">
            没有账号？<span class="switch-text" @click="switchToQuickLogin">注册账号</span>
          </div>
          <div>
            <span v-if="navCurrent === 1" class="switch-text" @click="navigateToForgetPassword">忘记密码？</span>
          </div>
        </div>

        <!-- 登录按钮 -->
        <div class="login-btn" @click="signin">
          <div style="margin-right: 0.375rem">登录</div>
          <div class="btn-arrow">
            <UvIcon name="arrow-rightward" :size="10" color="#ffffff" />
          </div>
        </div>

        <!-- 协议 -->
        <div class="agreement">
          <img class="agree-icon" :src="agreeProtocol ? IMG.agree : IMG.agreeOff" alt="" @click="toggleAgreement" />
          <span style="font-size: 12px; color: rgb(136, 144, 162); margin-left: 0.375rem">我已同意{{ brand.name }}平台</span>
          <span class="link" @click="navigateToService">《服务协议》</span>
          <span class="link" @click="navigateToPrivacy">《隐私政策》</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 登录页 —— 1:1 复刻原站 /pages/authorize/signin
 * 样式数值全部取自原站 signin-CDNvIIgR.css，未作改动
 * 交互逻辑取自原站 pages-authorize-signin.BAEz44Ei.js
 */
import { ref, reactive, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import UvIcon from '@/components/UvIcon.vue'
import { signinWithPassword } from '@/api'
import { storage, TOKEN_KEY } from '@/utils/storage'
import { showLoading, hideLoading } from '@/api/request'
import { brand } from '@/api/request'

import bgImg from '@/assets/signin/bg.jpg'
import backImg from '@/assets/signin/back.png'
import iconPhoneImg from '@/assets/signin/icon-phone.png'
import iconPwdImg from '@/assets/signin/icon-pwd.png'
import agreeImg from '@/assets/signin/agree.png'
import agreeOffImg from '@/assets/signin/agree-off.png'

const IMG = {
  bg: bgImg,
  back: backImg,
  iconPhone: iconPhoneImg,
  iconPwd: iconPwdImg,
  agree: agreeImg,
  agreeOff: agreeOffImg,
}

const router = useRouter()

/* ---------- 原站 data ---------- */
const body = reactive({ phone: '', captcha: '', password: '' })
const showPassword = ref(false)
const agreeProtocol = ref(true)
/** 原站 navCurrent：1 = 密码登录，0 = 验证码登录 */
const navCurrent = ref(1)
const countdown = ref(0)
const showCaptchaModal = ref(false)
let timer = null

/* ---------- 原站 methods ---------- */
function goBack() {
  router.back()
}
function toggleAgreement() {
  agreeProtocol.value = !agreeProtocol.value
}
function togglePassword() {
  showPassword.value = !showPassword.value
}
function openImageCaptcha() {
  if (!body.phone) return showToast('手机号不能为空')
  if (body.phone.length !== 11) return showToast('请输入正确的手机号')
  if (countdown.value > 0) return
  showCaptchaModal.value = true
}
function startCountdown() {
  countdown.value = 60
  timer && clearInterval(timer)
  timer = setInterval(() => {
    countdown.value -= 1
    if (countdown.value <= 0) {
      clearInterval(timer)
      timer = null
    }
  }, 1000)
}
onUnmounted(() => {
  timer && clearInterval(timer)
})

function switchToQuickLogin() {
  router.push('/pages/authorize/signup')
}
function navigateToForgetPassword() {
  router.push('/pages/authorize/password')
}
function navigateToService() {
  router.push('/pages/index/webView?title=服务协议')
}
function navigateToPrivacy() {
  router.push('/pages/index/webView?title=隐私政策')
}

async function signin() {
  if (!body.phone) return showToast('手机号不能为空')
  if (body.phone.length !== 11) return showToast('请输入正确的手机号')
  if (!body.password) return showToast('密码不能为空')
  if (!agreeProtocol.value) return showToast('请同意服务协议和隐私政策')
  showLoading()
  try {
    const res = await signinWithPassword(body.phone, body.password)
    hideLoading()
    if (res.status) {
      storage.set(TOKEN_KEY, res.data.token)
      router.replace('/pages/index/index')
    }
  } catch (e) {
    hideLoading()
  }
}
</script>

<style scoped>
/* ============ 页面容器 ============ */
.container {
  position: relative; width: 100%; min-height: 100vh; box-sizing: border-box;
  background-image: var(--sg-bg);
  background-size: 100% 100%;
  background-repeat: no-repeat;
  padding-top: 0.9375rem;
  padding-bottom: env(safe-area-inset-bottom);
  /* 原站 uni-page-body 基线：font-size:16px / color:#000 / font-family:"Times New Roman" */
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
}
.status-bar { height: var(--status-bar-height, 0px); width: 100%; }
/* 原站：<image src="back" mode="widthFix" style="width:50rpx;height:50rpx;margin-left:20rpx" onClick=goBack> */
.back-btn {
  width: 1.5625rem; height: 1.5625rem; margin-left: 0.625rem; display: inline-block;
}

/* ============ 表单卡片 ============ */
.form-container {
  margin-top: 18.125rem;
  background-color: #f9fcff;
  border-radius: 1.5625rem;
  padding: 0.9375rem;
}
.input-item {
  width: 100%; height: 3.28125rem; box-sizing: border-box;
  display: flex; margin-bottom: 12px; align-items: center;
  background-color: #edf4fc;
  padding: 0.9375rem;
  border-radius: 1.5625rem;
}
.input-title {
  display: flex; align-items: center; margin-right: 0.9375rem;
  color: #666; font-size: 15px; font-weight: 500;
}
.input-title img { width: 20px; height: 20px; margin-right: 6px; display: block; }
.input {
  margin-left: 0; flex: 1; color: #000; font-size: 14px; text-align: left;
  border: none; outline: none; background: transparent; min-width: 0;
  line-height: 19.6px;
}
.placeholder { font-size: 14px; color: #717689; }
.input::placeholder { font-size: 14px; color: #717689; }

/* 原站该块类名为 password-item / password-input，当前版本不渲染，样式保留备用 */
.password-item { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
.password-input { flex: 1; }
.code-row {
  flex: 1; display: flex; align-items: center; justify-content: space-between;
}
.code-input { flex: 1; }
.get-code-btn {
  box-sizing: border-box; font-family: 'PingFang SC'; line-height: 22px;
  font-size: 12px; color: #1952f8; margin-left: 8px; cursor: pointer;
}
.toggle-password-btn {
  color: #999; font-size: 0.875rem; padding-left: 0.625rem;
  white-space: nowrap; cursor: pointer;
}

/* ============ 登录按钮 ============ */
.login-btn {
  margin-top: 1.875rem;
  border-radius: 28px;
  font-weight: 600; font-size: 14px;
  width: 100%; height: 50px;
  background: linear-gradient(to right, #57abfd, #4391fc);
  color: #fff;
  display: flex; flex-direction: row; align-items: center; justify-content: center;
  cursor: pointer;
}
.btn-arrow {
  background-color: rgb(86, 163, 252);
  border-radius: 50%;
  padding: 0.375rem;
  display: flex; justify-content: center; align-items: center;
}

/* ============ 切换注册 ============ */
.switch-login { display: flex; justify-content: space-between; align-items: center; height: 40px; }
.switch-text {
  margin-top: 0.46875rem; cursor: pointer; color: #3578fc; font-size: 13px;
}

/* ============ 协议 ============ */
.agreement {
  display: flex; align-items: flex-start; text-align: center; margin-top: 1.5625rem;
}
.agree-icon {
  width: 13px; height: 14px; display: block; margin-top: 2px; cursor: pointer;
}
.link { font-size: 12px; color: #3578fc; cursor: pointer; }
</style>