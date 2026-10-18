<template>
  <!-- 原站为 uni-app 内置导航栏（uni-page-head），此处按其 computed style 1:1 还原 -->
  <div
    class="notice-page-head-wrap"
    :class="{ 'is-transparent-flat': transparent && !overlay, 'is-transparent-overlay': transparent && overlay }"
  >
    <div class="uni-page-head" :class="{ 'uni-page-head-transparent': transparent }">
      <div class="uni-page-head-hd" @click="goBack">
        <div class="uni-page-head-btn">
          <span class="head-back-arrow" />
        </div>
      </div>
      <div class="uni-page-head-bd">
        <div class="uni-page-head__title" :style="{ fontSize: '16px', opacity: 1 }">{{ title }}</div>
      </div>
      <div class="uni-page-head-ft" />
    </div>
    <div v-if="!transparent" class="uni-placeholder" />
  </div>
</template>

<script setup>
/**
 * 原生导航栏 —— 复刻原站 uni-app 内置 uni-page-head
 * 参数取自原站 computed style：
 *   .uni-page-head         fixed, 390x44, bg #fff
 *   .uni-page-head-hd      [3,7] 30x30（返回按钮容器）
 *   .uni-page-head-btn     26x26 svg
 *   .uni-page-head-bd      absolute [70,7] 250x30（标题居中）
 *   .uni-page-head-ft      [387,7] 0x30
 *   .uni-placeholder       占位 44px
 * 注意：SVG 路径直接取自原站产物，非自绘图标；此处不使用 emoji。
 */
import { useRouter } from 'vue-router'

const props = defineProps({
  title: { type: String, default: '' },
  /** 原站 navigationStyle=custom 时导航栏浮在内容之上（背景透明）。
   *  overlay=false：uni-page-head 高度 0、返回键 top:0、标题隐藏（藏品详情页实测）
   *  overlay=true ：uni-page-head 仍为 fixed 44px、背景透明、标题居中显示（市场详情页实测） */
  transparent: { type: Boolean, default: false },
  overlay: { type: Boolean, default: false },
})
const router = useRouter()

function goBack() {
  window.history.length > 1 ? router.back() : router.replace('/pages/index/index')
}
</script>

<style scoped>
/* 原站 computed:
   .uni-page-head        fixed; padding:7px 3px; bg #fff; 高44
   .uni-page-head-hd     [3,7] 30x30（static，在 padding 内）
   .uni-page-head-bd     absolute [70,7] 250x30
   .uni-page-head-ft     fs 13px
   .uni-placeholder      static 高 44（fixed 头的占位）
   注意：fixed 头 + 44px 占位块，等价于头占文档流 44px */
.uni-page-head {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 200;
  width: 100%;
  height: 44px;
  background-color: #fff;
  padding: 7px 3px;
  box-sizing: border-box;
}
.uni-page-head .uni-page-head-hd {
  position: absolute;
  left: 3px;
  top: 7px;
  width: 30px;
  height: 30px;
  cursor: pointer;
}
.uni-page-head .uni-page-head-btn {
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 2px;
}
.uni-page-head .uni-page-head-bd {
  position: absolute;
  left: 70px;
  top: 7px;
  width: 250px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.uni-page-head .uni-page-head__title {
  color: #000;
  /* 原站 computed: font-size:16px; font-weight:700; line-height:30px */
  font-weight: 700;
  font-size: 16px;
  line-height: 30px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  /* 原站实测标题盒铺满 .uni-page-head-bd（250x30），文本居中 */
  width: 100%;
}
.uni-page-head .uni-page-head-ft {
  position: absolute;
  right: 3px;
  top: 7px;
  height: 30px;
  font-size: 13px;
  color: #000;
  display: flex;
  align-items: center;
  justify-content: center;
}
.uni-placeholder { height: 44px; width: 100%; }

/* 返回箭头：用 CSS 绘制（项目规则禁用 SVG/emoji 作图标） */
.head-back-arrow {
  display: block;
  width: 11px;
  height: 11px;
  border-left: 2px solid #000;
  border-bottom: 2px solid #000;
  transform: rotate(45deg);
  margin-left: 3px;
}
.is-transparent-overlay .head-back-arrow {
  border-color: #fff;
}

/* ========== 透明模式（uni-page-head-transparent）============
   原站藏品详情页实测：容器 uni-page-head 高 0px、position static，
   内层 .uni-page-head 高 0px（absolute 定位返回键浮在 3D 区之上），
   返回键实现在 .uni-page-head-hd（absolute top:0 left:3） */
.is-transparent-flat .uni-page-head {
  position: absolute;
  height: 0;
  padding: 0;
  background: transparent;
  overflow: visible;
}
.is-transparent-flat .uni-page-head-hd {
  top: 0;
}
.is-transparent-flat .uni-page-head-bd,
.is-transparent-flat .uni-page-head-ft {
  display: none;
}

/* ========== 浮层透明模式（overlay）============
   原站市场详情页实测：.uni-page-head fixed 高 44px、padding-top 7px、
   background 透明；.uni-page-head-hd static [3,7] 30x30；
   .uni-page-head-bd absolute [70,7] 250x30（标题正常居中显示）；
   .uni-placeholder 高 0（不占文档流） */
.is-transparent-overlay .uni-page-head {
  background: transparent;
}
.is-transparent-overlay .uni-placeholder {
  height: 0;
}
</style>