<template>
  <div class="container" :style="{ '--sg-bg': `url(${IMG.bg})` }">
    <div class="status-bar" />

    <!-- 顶部返回按钮（原站 50rpx×50rpx 图片，onClick 走 goBack / navigateBack delta:1） -->
    <img class="back-btn" :src="IMG.back" alt="" @click="goBack" />

    <div style="padding: 0 26px">
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

        <!-- 密码 -->
        <div class="input-item">
          <div class="input-title">
            <img :src="IMG.iconPwd" alt="" />
          </div>
          <input
            v-model="body.password"
            class="input"
            type="password"
            maxlength="140"
            placeholder="请输入密码"
            placeholder-class="placeholder"
          />
        </div>

        <!-- 邀请码（非必填） -->
        <div class="input-item">
          <div class="input-title">
            <img :src="IMG.iconInvite" alt="" />
          </div>
          <input
            v-model="body.inviteCode"
            class="input"
            type="text"
            maxlength="140"
            placeholder="请输入邀请码（非必填）"
            placeholder-class="placeholder"
          />
        </div>

        <!-- 已有账号 → 去登录（原站为普通 view + 内联样式，非 .switch-login） -->
        <div style="margin-top: 1.09375rem; color: rgb(51, 51, 51); font-size: 13px">
          已有账号？
          <span class="switch-text" @click="switchToPasswordLogin">去登录</span>
        </div>

        <!-- 立即注册 -->
        <div class="login-btn" @click="signin">
          <div style="margin-right: 0.375rem">立即注册</div>
          <div class="btn-arrow">
            <UvIcon name="arrow-rightward" :size="10" color="#ffffff" />
          </div>
        </div>

        <!-- 协议 -->
        <div class="agreement">
          <img
            class="agree-icon"
            :src="agreeProtocol ? IMG.agree : IMG.agreeOff"
            alt=""
            @click="toggleAgreement"
          />
          <span style="font-size: 12px; color: rgb(61, 75, 102); margin-left: 0.375rem">
            我已同意{{ brand.name }}平台
          </span>
          <span class="link" @click="navigateToService">《服务协议》</span>
          <span class="link" @click="navigateToPrivacy">《隐私政策》</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 注册页 —— 1:1 复刻原站 /pages/authorize/signup
 * 样式数值全部取自原站 signup-D69IHroA.css，未作改动
 * 交互逻辑取自原站 pages-authorize-signup.C7ZO_36Z.js
 */
import { ref, reactive, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast } from 'vant'
import UvIcon from '@/components/UvIcon.vue'
import { passportApi } from '@/api'
import { encryptPassword } from '@/utils/crypto'
import { showLoading, hideLoading } from '@/api/request'
import { brand } from '@/api/request'

import bgImg from '@/assets/signup/bg.jpg'
import backImg from '@/assets/signup/back.png'
import iconPhoneImg from '@/assets/signup/icon-phone.png'
import iconPwdImg from '@/assets/signup/icon-pwd.png'
import iconInviteImg from '@/assets/signup/icon-invite.png'
import agreeImg from '@/assets/signup/agree.png'
import agreeOffImg from '@/assets/signin/agree-off.png'

const IMG = {
  bg: bgImg,
  back: backImg,
  iconPhone: iconPhoneImg,
  iconPwd: iconPwdImg,
  iconInvite: iconInviteImg,
  agree: agreeImg,
  agreeOff: agreeOffImg,
}

const router = useRouter()
const route = useRoute()

/* ---------- 原站 data ---------- */
const body = reactive({ phone: '', password: '', captcha: '', inviteCode: '' })
const showCaptchaModal = ref(false)
const smsCaptchaBody = reactive({ phone: '', type: 102, imageCaptchaKey: '', imageCaptcha: '' })
const imageCaptchaUrl = ref('')
const agreeProtocol = ref(true)
const countdown = ref(0)
let timer = null

/* ---------- 原站 methods ---------- */
function goBack() {
  router.back()
}

function openImageCaptcha() {
  if (!body.phone || body.phone.length !== 11) return showToast('请输入正确的手机号')
  if (countdown.value > 0) return
  showCaptchaModal.value = true
}

function refreshImageCaptcha() {
  passportApi.getImageCaptcha({ type: 102 }).then((res) => {
    if (res.status) {
      smsCaptchaBody.imageCaptchaKey = res.data.key
      imageCaptchaUrl.value = res.data.base64
    }
  })
}

function getSmsCaptcha(captchaVerifyParam) {
  passportApi
    .getSmsCaptcha({ type: 102, phone: body.phone, captchaVerifyParam })
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

function toggleAgreement() {
  agreeProtocol.value = !agreeProtocol.value
}

/** 原站 signin() —— 校验顺序与文案严格照搬 */
function signin() {
  if (!body.phone) return showToast('手机号不能为空')
  if (!body.password) return showToast('密码不能为空')
  if (body.password.length < 6) return showToast('密码长度不能少于6位')
  if (!body.captcha) return showToast('验证码不能为空')
  if (!agreeProtocol.value) return showToast('请同意服务协议和隐私政策')

  showLoading()
  passportApi
    .signup({
      phone: body.phone,
      password: encryptPassword(body.password),
      captcha: body.captcha,
      inviteCode: body.inviteCode,
    })
    .then((res) => {
      hideLoading()
      // 原站：注册成功 reLaunch 回登录页
      if (res.status) router.replace('/pages/authorize/signin')
    })
    .catch(() => hideLoading())
}

function navigateToService() {
  router.push('/pages/index/webView?title=服务协议')
}
function navigateToPrivacy() {
  router.push('/pages/index/webView?title=隐私政策')
}
function switchToPasswordLogin() {
  router.replace('/pages/authorize/signin')
}

/* ---------- 原站生命周期 ---------- */
// 原站 onLoad：带 inviteCode 参数时预填
if (route.query.inviteCode) {
  body.inviteCode = route.query.inviteCode
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
  background-image: var(--sg-bg);
  background-size: 100% 100%;
  background-repeat: no-repeat;
  /* 原站 uni-page-body 基线：font-size:16px / color:#000 / font-family:"Times New Roman" */
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
  padding-top: 0.9375rem;
  padding-bottom: env(safe-area-inset-bottom);
}
.status-bar { height: var(--status-bar-height, 0px); width: 100%; }
/* 原站：<image src="left" mode="widthFix" style="width:50rpx;height:50rpx;margin-left:20rpx" onClick=goBack>
   注意原站为 inline-block，其基线留白会在下方产生 5px 间隙，不可改为 block */
.back-btn {
  width: 1.5625rem;
  height: 1.5625rem;
  margin-left: 0.625rem;
  display: inline-block;
}

/* ============ 表单卡片 ============ */
.form-container {
  margin-top: 18.125rem;
  background-color: #f9fcff;
  border-radius: 1.5625rem;
  padding: 0.9375rem;
}
.input-item {
  width: 100%;
  height: 3.28125rem;
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
  cursor: pointer;
  white-space: nowrap;
}

/* ============ 注册按钮 ============ */
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

/* ============ 去登录 ============ */
.switch-text {
  margin-top: 0.46875rem;
  cursor: pointer;
  color: #3578fc;
  font-size: 13px;
}

/* ============ 协议 ============ */
.agreement { display: flex; align-items: flex-start; text-align: center; margin-top: 1.5625rem; }
.agree-icon { width: 13px; height: 14px; display: block; margin-top: 2px; cursor: pointer; }
.link { font-size: 12px; color: #3578fc; cursor: pointer; }
</style>