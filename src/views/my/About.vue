<!--
  关于我们页 —— 复刻原站 /pages/my/setting/about
  原站分包：assets/pages-my-setting-about.JNVmn6Nj.js
  scopeId ：data-v-e6a78962
  导航模式：navigationBar style:"custom" —— 页面内自绘 navbar，不加系统导航栏。
           路由 titleText 是「关于我们」，但页面内 navbar 显示的是「联系我们」，
           两者不一致是原站真实状态，均照原样实现。

  刻意保留的原站瑕疵 / 细节：
  1. 根节点 @touchmove.prevent —— 整个页面禁止滚动，内容超出一屏时无法滚动。
  2. navbar 标题「联系我们」≠ 路由标题「关于我们」，照抄不改。
  3. 「加入社群」只弹 toast、无任何跳转（没有二维码/链接）。
  4. 「微信公众号」是空方法，点击完全无反应 —— 原站就是这样。
  5. 原站 data 里的 version / statusBarHeight 两个字段 render 从未引用，不实现。
  6. 原站另有 navigateToService / navigateToPrivacy 两个 DEAD 方法，不实现。
  7. statusBarHeight 用 CSS 变量 --status-bar-height 表达（H5 端实测 0px）。
-->
<template>
  <div class="about-container" @touchmove.prevent>
    <div class="status-bar" />
    <div class="navbar">
      <div class="navbar-inner">
        <div class="back-btn" @click="goBack">
          <div class="back-arrow" />
        </div>
        <div class="navbar-title">联系我们</div>
      </div>
    </div>

    <div class="content">
      <div class="logo-box">
        <img class="logo" :src="brand.logo || logo" alt="" />
        <span class="app-name">{{ brand.name }}</span>
      </div>

      <div class="item" @click="copyEmail">
        <span class="item-key">商务合作邮箱</span>
        <div class="item-val">
          <span class="item-mail">{{ email }}</span>
          <img class="copy-icon" :src="copyIcon" alt="" />
        </div>
      </div>

      <div class="item" @click="joinGroup">
        <span class="item-key">加入社群</span>
        <span class="iconfont icon-right item-arrow" />
      </div>

      <div class="item" @click="followOfficial">
        <span class="item-key">微信公众号</span>
        <span class="iconfont icon-right item-arrow" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, copy, brand } from '@/api/request'
import logo from '@/assets/my/logo.jpg'
import copyIcon from '@/assets/my/about-copy-icon.png'

const router = useRouter()

const email = ref('zhenhaiart@163.com')

function goBack() {
  router.back()
}

/** 原站 copy(email, '邮箱已复制') */
function copyEmail() {
  copy(email.value, '邮箱已复制')
}

/** 原站行为：只弹提示，无跳转 */
function joinGroup() {
  showToast({ icon: 'none', title: '请联系客服加入社群' })
}

/** 原站行为：空方法，点击无任何反应 —— 照抄 */
function followOfficial() {}
</script>

<style scoped>
/* 以下为 about-DLTN6Dp3.css 原文，值原样抄（已是 rem，不做换算） */
.status-bar{height:var(--status-bar-height);width:100%}
.about-container{display:flex;flex-direction:column;min-height:100vh;background:#f1f4ff url('@/assets/my/about-bg.png') no-repeat top center;background-size:contain;box-sizing:border-box}
.navbar{width:100%}
.navbar-inner{position:relative;height:2.75rem;display:flex;align-items:center;justify-content:center}
.back-btn{position:absolute;left:1.125rem;top:50%;transform:translateY(-50%);width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center}
/* 原站普通 div 是 content-box（border-box 只作用于 uni-* 标签），
   故 2px border 叠加在 .625rem 之外，rotate(45deg) 后包围盒为 17.5px。 */
.back-arrow{width:.625rem;height:.625rem;border-left:.125rem solid #000;border-bottom:.125rem solid #000;transform:rotate(45deg);margin-left:.1875rem;box-sizing:content-box}
.navbar-title{font-size:1.0625rem;font-weight:700;color:#000}
.content{width:21.5625rem;margin:2.5rem auto 0;display:flex;flex-direction:column}
.logo-box{display:flex;flex-direction:column;align-items:center;margin-bottom:2.5rem}
.logo{width:4.875rem;height:4.875rem}
.app-name{margin-top:1.5rem;font-size:1rem;font-weight:700;color:#212121}
.item{height:3.125rem;background-color:#fff;border-radius:.625rem;padding:0 .9375rem 0 .625rem;margin-bottom:.625rem;display:flex;flex-direction:row;align-items:center;justify-content:space-between;font-size:.875rem}
.item-key{color:#000;font-weight:500}
.item-val{display:flex;flex-direction:row;align-items:center}
.item-mail{color:#000;font-weight:700}
.copy-icon{width:.875rem;height:.875rem;margin-left:.375rem}
.item-arrow{color:#999;font-size:1rem}
</style>
