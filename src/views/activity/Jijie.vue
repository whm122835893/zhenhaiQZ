<template>
  <!--
    集结页 —— 复刻原站 /pages/activity/jijie
    源站实测：fundraisBg 底图 bg1-z4Hlnmvq.jpg (rgb(196,220,255))
              顶部无标题（仅返回键），.titleRecord 内含标题图 + 集结记录入口
              列表区 listBox 高 190px，空态用 newNodata 图
  -->
  <div class="fundraisBg" :style="{ backgroundImage: `url(${pageBg})` }">
    <P1BackBar />

    <div class="content">
      <div class="titleRecord endBetween">
        <img class="title-img" :src="titleImg" alt="" />
        <div class="recordBox columnCenter" @click="goRecord">
          <img class="record-icon" :src="recordIcon" alt="" />
          <span class="record-txt">集结记录</span>
        </div>
      </div>

      <div class="listBox">
        <img class="empty-img" :src="noData" alt="" />
        <div class="empty-txt">暂无数据</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import P1BackBar from '@/components/P1BackBar.vue'
import pageBg from '@/assets/p1/jijie/bg1-z4Hlnmvq.jpg'
import titleImg from '@/assets/p1/jijie/title.png'
import recordIcon from '@/assets/p1/jijie/record-icon.png'
import noData from '@/assets/p1/jijie/newNodata-BMz9JLqO.png'

const router = useRouter()
/* 源站「集结记录」跳转活动记录页 */
function goRecord() {
  router.push('/pages/activity/record')
}
</script>

<style scoped>
.fundraisBg {
  min-height: 100vh;
  box-sizing: border-box;
  background-color: rgb(196, 220, 255);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
}

.content {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.titleRecord {
  width: 100%;
  padding: 0 0.4375rem 0 1.75rem;
  /* 源站实测：backTitle 高 69，标题图 y=115 → margin-top 46.8px = 2.8125rem */
  margin: 2.8125rem 0 1.0625rem;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  box-sizing: border-box;
}

.title-img {
  width: 231px;
  height: 72px;
  display: block;
}

.recordBox {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}
.record-icon {
  width: 35px;
  height: 35px;
  display: block;
}
.record-txt {
  font-size: 11.44px;
  line-height: 1.2;
  color: #333333;
  margin-top: 3px;
}

.listBox {
  width: 100%;
  height: 190px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.empty-img {
  width: 138px;
  height: 138px;
  display: block;
}
.empty-txt {
  font-size: 14px;
  color: #999999;
  line-height: 21px;
}
</style>
