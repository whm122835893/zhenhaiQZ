<template>
  <!--
    我的钱包页 —— 复刻原站 /pages/my/wallet/index
    源文件：assets/pages-my-wallet-index.CSHnvv6G.js + assets/index-CGIZ1Fcs.css
    实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px
  -->
  <div class="wallet-page">
    <!-- uv-navbar（fixed 透明，浮在 userAccount 之上） -->
    <div class="uv-navbar--fixed">
      <div class="uv-navbar__content">
        <div class="uv-navbar__content__left" @click="goBack">
          <UvIcon class="nav-back-icon" name="arrow-left" color="#fff" :size="20" />
        </div>
        <div class="uv-navbar__content__title">我的钱包</div>
        <div class="uv-navbar__content__right" />
      </div>
    </div>

    <div class="containerStyle">
      <!-- 顶部账户区（背景图取自原站 index-CGIZ1Fcs.css 内联 base64） -->
      <div class="userAccount">
        <div class="totalSumBox" />
        <div class="tips center font-fff-24-500">
          <span class="red">*</span>
          <span>所有资金均由第三方持牌支付机构进行支付、清算 </span>
        </div>
      </div>

      <!-- 钱包列表 -->
      <div class="content">
        <div class="cardItem font-000-28-500" @click="goHfpay">
          <div class="logoName center">
            <img class="logo-img" :src="hfLogo" alt="" />
            <div class="logo-text">
              <div class="name flex1 c5">汇付天下</div>
              <div class="desc flex1 c5"> 汇付天下 </div>
            </div>
          </div>
          <div class="otherBox centerBetween">
            <div class="center font-fff-28-bold">
              <span>进入钱包</span>
              <UvIcon class="arrow-icon" name="arrow-right" color="#fff" :size="14" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 原站逻辑（pages-my-wallet-index.CSHnvv6G.js）：
 *   const _ = () => a.get("/wallet/hfpay")   进入汇付钱包 → window.location.href = data
 *   const g = () => a.get("/wallet/yeepay")  进入易付钱包（模板保留方法，UI 未挂入口）
 *   顶部标题「我的钱包」，navbar 透明、返回箭头白色
 *   底部固定提示：* 所有资金均由第三方持牌支付机构进行支付、清算
 */
import { useRouter } from 'vue-router'
import { walletApi } from '@/api'
import UvIcon from '@/components/UvIcon.vue'
import hfLogo from '@/assets/wallet/hf-logo.png'

const router = useRouter()

function goBack() {
  window.history.length > 1 ? router.back() : router.replace('/pages/my/index')
}

/** 原站 goHfpay(){ _().then(t => t.status && (window.location.href = t.data)) } */
function goHfpay() {
  walletApi.hfpay().then((res) => {
    if (res && res.status && res.data) {
      window.location.href = res.data
    }
  })
}

/** 原站 goYeepay(){ g().then(t => t.status && (window.location.href = t.data)) } */
function goYeepay() {
  walletApi.yeepay().then((res) => {
    if (res && res.status && res.data) {
      window.location.href = res.data
    }
  })
}

defineExpose({ goYeepay })
</script>

<style scoped>
/* ============ 页面基线（原站 uni-page-body）============ */
.wallet-page {
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
  background-color: #f6f6f6;
  min-height: 100vh;
}

/* ============ uv-navbar（fixed 透明）============ */
.uv-navbar--fixed {
  position: fixed;
  left: 0;
  top: 0;
  right: 0;
  z-index: 200;
  height: 44px;
  background-color: transparent;
}
.uv-navbar__content {
  position: relative;
  display: flex;
  align-items: center;
  height: 44px;
}
.uv-navbar__content__left {
  width: 46px;
  height: 44px;
  display: flex;
  align-items: center;
  padding-left: 13px;
  cursor: pointer;
}
/* 实测 [13,12,20,20] */
.uv-navbar__content__left .nav-back-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  line-height: 1;
}
/* 实测标题 [91,8,208,28]，font-size 36rpx = 18.72px */
.uv-navbar__content__title {
  /* 原站实测 [91,8,208,28]：绝对定位水平居中（208 为 max-width，实际文本居中） */
  position: absolute;
  left: 91px;
  width: 208px;
  height: 28px;
  line-height: 28px;
  font-size: 1.125rem; /* 36rpx */
  font-weight: 500;
  color: #fff;
  text-align: center;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.uv-navbar__content__right {
  width: 26px;
  height: 44px;
}

/* ============ 容器 ============ */
.containerStyle {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
}

/* 实测 [0,0,390,252.7]：15.1875rem * 16.64 = 252.77 */
.userAccount {
  position: relative;
  box-sizing: border-box;
  width: 23.4375rem;
  height: 15.1875rem;
  background: url('@/assets/wallet/account-bg.png') no-repeat;
  background-size: 100% 100%;
}
.userAccount .totalSumBox {
  position: absolute;
  left: 1.875rem;
  bottom: 4.375rem;
}
/* 实测 [0,214.3,390,38.5]：2.3125rem = 38.5 */
.userAccount .tips {
  position: absolute;
  bottom: 0;
  box-sizing: border-box;
  width: 23.4375rem;
  height: 2.3125rem;
  padding: 0 1.125rem;
  background: rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: row;
  align-items: center;
  font-size: 0.75rem;
  font-weight: 500;
  color: #fff;
}
.userAccount .tips .red {
  width: 0.375rem;
  height: 0.375rem;
  margin-right: 0.125rem;
  color: #ff5f5f;
}

/* ============ 内容区 ============ */
.content {
  margin-top: 1.25rem;
}
/* 实测 [16.6,273.5,356.7,124.8] */
.content .cardItem {
  position: relative;
  box-sizing: border-box;
  border-radius: 0.625rem;
  padding: 0.9375rem;
  width: 21.4375rem;
  height: 7.5rem;
  margin-bottom: 1.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: #000;
  background: url('@/assets/wallet/huifu.png') no-repeat center top;
  background-size: 100% 124.8px; /* 原站 240rpx，1rpx=0.52px */
  cursor: pointer;
}
/* 实测 [32.2,289.1,325.5,42] */
.content .cardItem .logoName {
  display: flex;
  flex-direction: row;
  align-items: center;
  height: 42px;
}
/* 实测 [32.2,292.2,35.8,35.8] */
.content .cardItem .logo-img {
  width: 35.8px;
  height: 35.8px;
  border: 1px solid #8b99c2;
  border-radius: 6.24px; /* 原站 12rpx */
  background-color: #fff;
  flex-shrink: 0;
}
.content .cardItem .logo-text {
  display: flex;
  flex-direction: column;
  justify-content: center;
  margin-left: 10.4px; /* 原站 20rpx */
}
.content .cardItem .name {
  width: 58.2px;
  font-weight: 500;
  font-size: 0.875rem;
  color: #9949ff;
}
.content .cardItem .desc {
  width: 58.2px;
  font-weight: 400;
  font-size: 0.8125rem;
  color: #9949ff;
}
/* 实测 [32.2,355.5,325.5,22] */
.content .cardItem .otherBox {
  width: calc(100% - 1.875rem);
  position: absolute;
  bottom: 1.25rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}
.content .cardItem .otherBox .center {
  display: flex;
  flex-direction: row;
  align-items: center;
  font-size: 0.875rem;
  font-weight: 700;
  color: #fff;
}
/* 实测箭头 [90.4,359.5,14,14] */
.content .cardItem .otherBox .arrow-icon {
  width: 14px;
  height: 14px;
  margin-left: 2px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  line-height: 1;
}
</style>
