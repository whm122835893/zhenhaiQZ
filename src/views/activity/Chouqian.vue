<template>
  <!--
    抽签 —— 对应原站 /pages/activity/chouqian
    源站实测：页面内容区已下线，仅剩
      底图 qmcq_background-Cn4yvWMx.jpg（390x844）
      .top-bar 390x47，padding-top 46.8px，左侧返回图标 25x26 @x=12 y=47
    其余区域无任何 DOM（源站未渲染内容），此处照搬该行为。
  -->
  <div class="cqPage" :style="{ backgroundImage: `url(${pageBg})` }">
    <div class="top-bar">
      <span class="top-back" @click="goBack" />
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import pageBg from '@/assets/p0final/chouqian/qmcq_background-Cn4yvWMx.jpg'

const router = useRouter()

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}
</script>

<style scoped>
.cqPage {
  position: relative;
  width: 100%;
  height: 52.875rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  overflow: hidden;
}
.top-bar {
  height: 2.9375rem;
  padding: 2.925rem 0 0 0.78rem;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
/* 返回箭头：CSS 绘制（规则禁用 SVG/emoji 作图标） */
.top-back {
  display: block;
  width: 1.5625rem;
  height: 1.625rem;
  cursor: pointer;
  position: relative;
}
.top-back::before {
  content: '';
  position: absolute;
  left: 5px;
  top: 5px;
  width: 11px;
  height: 11px;
  border-left: 2px solid rgb(255, 255, 255);
  border-bottom: 2px solid rgb(255, 255, 255);
  transform: rotate(45deg);
}
</style>