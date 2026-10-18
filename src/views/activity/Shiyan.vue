<template>
  <!--
    投放记录页 —— 复刻原站 /pages/activity/shiyan
    源站实测：
      .containerStyle 无底色；.topBox 高 275px 背景 top-Dzfh_1aQ.jpg
      顶图内：左侧 42x36 返回图，右侧「投放记录」胶囊按钮
        （rgba(255,255,255,0.5) 底、4.16px 左圆角、[315,54 75x31]）
      .content 高 621px 背景 top-CvRb4vQp.png + rgb(5,40,100) 底色，
        顶部留白 52px（padding-top），从 y=275 开始渲染列表
      列表无数据，展示空态（内联图 140x128，容器按 110x110 居中）
  -->
  <div class="shiyanPage">
    <div class="topBox" :style="{ backgroundImage: `url(${topBox})` }">
      <img class="back" :src="backIcon" alt="" @click="goBack" />
      <div class="record doubleCenter" @click="goRecord">投放记录</div>
    </div>

    <div class="content" :style="{ backgroundImage: `url(${contentBg})` }">
      <div class="listBox">
        <img class="empty-img" :src="noData" alt="" />
        <div class="empty-txt">暂无数据</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import topBox from '@/assets/p1/shiyan/top-Dzfh_1aQ.jpg'
import contentBg from '@/assets/p1/shiyan/top-CvRb4vQp.png'
import backIcon from '@/assets/p1/shiyan/top-back.png'
import noData from '@/assets/p1/shiyan/nodata.png'

const router = useRouter()
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/pages/activity/index')
}
/* 源站「投放记录」按钮跳活动记录页 */
function goRecord() {
  router.push('/pages/activity/record')
}
</script>

<style scoped>
.shiyanPage {
  min-height: 100vh;
  box-sizing: border-box;
  background: #ffffff;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.topBox {
  position: relative;
  width: 100%;
  height: 275px;
  flex-shrink: 0;
  background-size: 100% 100%;
  background-repeat: no-repeat;
  background-position: top;
}

.back {
  position: absolute;
  left: 17px;
  top: 54px;
  width: 42px;
  height: 36px;
  display: block;
  cursor: pointer;
}

/* 「投放记录」胶囊：源站 border-radius 4.16px 0 0 4.16px（右贴边） */
.record {
  position: absolute;
  right: 0;
  top: 54px;
  width: 75px;
  height: 31px;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 0.9063rem;
  line-height: 1.2;
  color: #ffffff;
  background-color: rgba(255, 255, 255, 0.5);
  border-radius: 4.16px 0 0 4.16px;
  cursor: pointer;
}

.content {
  position: fixed;
  left: 0;
  right: 0;
  top: 223px;
  bottom: 0;
  width: 100%;
  box-sizing: border-box;
  padding-top: 52px;
  overflow-y: auto;
  background-color: rgb(5, 40, 100);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
}

.listBox {
  width: 100%;
  min-height: 144px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.empty-img {
  width: 110px;
  height: 110px;
  display: block;
  object-fit: contain;
}
.empty-txt {
  font-size: 14px;
  color: #999999;
  margin-top: 13px;
  line-height: 21px;
}
</style>
