<template>
  <!--
    成品展厅 —— 复刻原站 /pages/activity/chengjin
    源站实测（页面总高 844，composeBg0 底图 bg-Be67UB1r.jpg，bg rgb(255,233,208)）：
      .backTitle 390x69，padding 0 15.6px，白色
        返回图标 20x20 @x=16 y=24；.navtitle 208x69 居中；.right-icon 42x42 @x=333 y=14
      .composeCard 390x120 @y=84，margin-top 15.6px，padding-bottom 10.4px，居中纵向
        .record「活动记录」99x32 @x=292 y=79（绝对定位，br 22px 0 0 22px，bg rgba(255,255,255,0.22)）
          .record__icon 17x17，文字 13px fw500 白
        .logoName 82x14 @x=154 y=84：logoName__logo 78x14（logo-white-DcWh5f4W.png）
        .composeCard__title 224x38 @x=83 y=114（title5-DglILErO.png），margin-top 15.6px
        .composeCard__sub 146x28 @x=122 y=165（type5-B6f9_YRK.png），margin-top 13.52px
  -->
  <div class="chengjinPage" :style="{ backgroundImage: `url(${pageBg})` }">
    <div class="backTitle font-000-34-bold">
      <span class="back-icon" @click="goBack" />
      <div class="navtitle absoluteCenter">
        <div class="tip text-center">
          <div class="inner">
            <span class="tip-inder">成品展厅</span>
          </div>
        </div>
      </div>
      <div class="right-icon" />
    </div>

    <div class="composeCard columnCenter">
      <div class="record doubleCenter" @click="goRecord">
        <img class="record__icon" :src="recordIcon" alt="" />
        <span class="record__txt">活动记录</span>
      </div>

      <div class="logoName center">
        <img class="logoName__logo" :src="logo" alt="" />
      </div>

      <img class="composeCard__title" :src="title" alt="" />
      <img class="composeCard__sub" :src="type" alt="" />
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import pageBg from '@/assets/p0final/chengjin/bg-Be67UB1r.jpg'
import logo from '@/assets/p0final/chengjin/logo-white-DcWh5f4W.png'
import title from '@/assets/p0final/chengjin/title5-DglILErO.png'
import type from '@/assets/p0final/chengjin/type5-B6f9_YRK.png'
import recordIcon from '@/assets/p0final/chengjin/record-icon.png'

const router = useRouter()

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

function goRecord() {
  router.push('/pages/activity/record')
}
</script>

<style scoped>
.chengjinPage {
  position: relative;
  width: 100%;
  min-height: 50.721154rem;
  box-sizing: border-box;
  background-color: rgb(255, 233, 208);
  background-repeat: no-repeat;
  background-size: 100% auto;
  overflow-x: hidden;
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}

/* 顶部返回栏（源站 .backTitle 高 69） */
.backTitle {
  color: rgb(255, 255, 255);
  position: relative;
  height: 4.146635rem;
  margin-bottom: -0.060096rem;
  padding: 0 0.9375rem;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
/* 返回箭头：CSS 绘制（规则禁用 SVG/emoji 作图标） */
.back-icon {
  display: block;
  width: 1.201923rem;
  height: 1.201923rem;
  cursor: pointer;
  position: relative;
}
.back-icon::before {
  content: '';
  position: absolute;
  left: 4px;
  top: 4px;
  width: 9px;
  height: 9px;
  border-left: 2px solid rgb(255, 255, 255);
  border-bottom: 2px solid rgb(255, 255, 255);
  transform: rotate(45deg);
}
.navtitle {
  position: absolute;
  left: 50%;
  top: 0;
  margin-left: -6.25rem;
  width: 12.5rem;
  height: 4.146635rem;
  display: flex;
  justify-content: center;
  align-items: center;
}
.tip-inder {
  font-size: 1.0625rem;
  font-weight: 700;
  color: rgb(255, 255, 255);
}
.right-icon {
  width: 2.524038rem;
  height: 2.524038rem;
}

/* 展示卡片 */
.composeCard {
  position: relative;
  width: 100%;
  height: 7.211538rem;
  margin-top: 0.9375rem;
  padding-bottom: 0.625rem;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.record {
  position: absolute;
  right: -1px;
  top: -0.300481rem;
  font-size: 13px;
  color: rgb(255, 255, 255);
  width: 5.949519rem;
  height: 1.923077rem;
  padding-top: 0.625rem;
  box-sizing: border-box;
  border-radius: 22px 0 0 22px;
  background-color: rgba(255, 255, 255, 0.22);
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}
.record__icon {
  width: 1.021635rem;
  height: 1.021635rem;
  margin: 0 0.3125rem 0 0;
}
.record__txt {
  font-size: 13px;
  color: rgb(255, 255, 255);
  font-weight: 500;
  color: rgb(255, 255, 255);
}
.logoName {
  height: 0.841346rem;
  display: flex;
  align-items: center;
}
.logoName__logo {
  width: 4.6875rem;
  height: 0.841346rem;
  margin: 0 0.25rem 0 0;
}
.composeCard__title {
  width: 13.461538rem;
  height: 2.283654rem;
  margin-top: 0.997596rem;
  display: block;
}
.composeCard__sub {
  width: 8.774038rem;
  height: 1.682692rem;
  margin-top: 0.8125rem;
  display: block;
}
</style>