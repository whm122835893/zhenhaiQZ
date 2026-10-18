<template>
  <!--
    重铸页 —— 复刻原站 /pages/activity/chongzhu
    源站实测（页面总高 900，无系统导航栏，底图 bg-CYTqY-yh.png + rgb(18,17,13)）：
      .top-bar 高 62（padding-top 62.4px）：左侧返回 25x26 @x=12 y=49，右侧规则图 70x24 @x=320 y=50
      .main-title 317x90 @x=36 y=62
      .time-bar 301x23 @x=45 y=159，11.44px 粗体 rgb(246,227,193)
      .num-box 222x47 @x=84 y=211：标签「可用重铸资格藏品」10.4px + 箭头 17x18，数值「0」20.8px 粗体
      .lunBg 390x155 @y=303 跑马灯，item 77x69 步进 92px，共 32 项；居中框 116x182 @x=137 y=290
      .agree-row 390x16 @y=491：勾选图标 12x12 + 文案 11.44px
      .draw-btn 206x53 @x=92 y=527 / y=607
      .rule-box 365x149 @x=12 y=690，标题 15.6px 粗体 + 4 条规则 12.48px
  -->
  <div class="czPage" :style="{ backgroundImage: `url(${bodyBg})` }">
    <div class="top-bar">
      <img class="top-back" :src="topback" alt="" @click="goBack" />
      <img class="top-my" :src="ruleEntry" alt="" @click="onRuleEntry" />
    </div>

    <img class="main-title" :src="title" alt="" />

    <div class="timeBg time-bar" :style="{ backgroundImage: `url(${timeBg})` }">活动剩余时间：00:00:00</div>

    <div class="numBg num-box" :style="{ backgroundImage: `url(${numBg})` }">
      <div class="flex-center num-label">
        <span class="num-label__text">可用重铸资格藏品</span>
        <img class="num-label__arrow" :src="numArrow" alt="" />
      </div>
      <div class="num-value">{{ qualifiedCount }}</div>
    </div>

    <div class="lunBg" :style="{ backgroundImage: `url(${lunBg})` }">
      <div class="marquee-wrap">
        <div class="marquee-viewport">
          <div class="marquee-track" :style="{ animationDuration: marqueeDuration }">
            <div v-for="n in marqueeCount" :key="n" class="marquee-item">
              <div class="marquee-card">
                <div class="marquee-img-box" />
              </div>
            </div>
          </div>
        </div>
        <img class="center-frame" :src="centerFrame" alt="" />
      </div>
    </div>

    <div class="flex-center agree-row" @click="agreed = !agreed">
      <img class="agree-icon" :class="{ 'is-agreed': agreed }" :src="no" alt="" />
      <span class="agree-text">我已知晓消耗藏品不可恢复</span>
    </div>

    <div
      v-for="b in drawButtons"
      :key="b.times"
      class="btnBg draw-btn"
      :class="{ 'draw-btn--last': b.times === 10 }"
      :style="{ backgroundImage: `url(${btnBg})` }"
      @click="onDraw(b.times)"
    >
      <div class="draw-btn__main">重铸{{ b.times }}次</div>
      <div class="draw-btn__sub">消耗 x{{ b.times }}可用重铸藏品</div>
    </div>

    <div class="ruleBg rule-box" :style="{ backgroundImage: `url(${ruleBg})` }">
      <div class="rule-title">重铸规则</div>
      <div class="rule-content">
        <p v-for="(r, i) in rules" :key="i">{{ r }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import bodyBg from '@/assets/p1/chongzhu/bg-CYTqY-yh.png'
import topback from '@/assets/p1/chongzhu/topback.png'
import ruleEntry from '@/assets/p1/chongzhu/chong-UzErNZTs.png'
import title from '@/assets/p1/chongzhu/title-DkY9AB1_.png'
import numBg from '@/assets/p1/chongzhu/numBg-BZf5sTr2.png'
import timeBgImg from '@/assets/p1/chongzhu/timebg.png'
import numArrow from '@/assets/p1/chongzhu/numarrow.png'
import lunBg from '@/assets/p1/chongzhu/lunBg-wl4so_7T.png'
import centerFrame from '@/assets/p1/chongzhu/imgBg-C9s4E-fN.png'
import no from '@/assets/p1/chongzhu/no.png'
import btnBg from '@/assets/p1/chongzhu/btnBg.png'
import ruleBg from '@/assets/p1/chongzhu/ruleBg.png'

const router = useRouter()

// 源站跑马灯共渲染 32 项（数据为空，卡片内无图）
const marqueeCount = 32
const marqueeDuration = '28s'
const qualifiedCount = ref(0)
const agreed = ref(false)
const drawButtons = [{ times: 1 }, { times: 10 }]
const rules = [
  '1. 重铸需消耗对应的重铸资格藏品，重铸成功后原藏品将被销毁且不可恢复。',
  '2. 重铸获得的藏品将以实际开奖结果为准，概率公示见活动详情页。',
  '3. 活动期间请遵守平台规则，恶意刷奖将取消参与资格。',
  '4. 最终解释权归平台所有。',
]

const timeBg = timeBgImg

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/activity/index')
}

function onRuleEntry() {
  router.push('/pages/activity/index')
}

function onDraw() {
  if (!agreed.value) return
  router.push({ path: '/pages/activity/index' })
}
</script>

<style scoped>
.czPage {
  position: relative;
  min-height: 54.0625rem;
  box-sizing: border-box;
  background-color: rgb(18, 17, 13);
  background-repeat: no-repeat;
  background-size: 100% auto;
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
  overflow-x: hidden;
  color: rgb(0, 0, 0);
}

/* 顶栏：高 62，padding-top 62.4px */
.top-bar {
  height: 62px;
  box-sizing: border-box;
  padding: 3.75rem 0 0 0.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.top-back {
  width: 1.5024rem;
  height: 1.5625rem;
  flex: 0 0 auto;
}
.top-my {
  width: 4.2163rem;
  height: 1.4423rem;
  margin-right: 0;
  flex: 0 0 auto;
}

/* 主标题图 317x90 */
.main-title {
  width: 19.0505rem;
  height: 5.4087rem;
  margin: 0 2.1635rem;
  display: block;
}

/* 时间条 301x23 */
.time-bar {
  width: 18.0889rem;
  height: 1.3822rem;
  margin: 0.375rem 0 0 2.6881rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: rgb(246, 227, 193);
  display: flex;
  justify-content: center;
  align-items: center;
}

/* 数量框 222x47 */
.num-box {
  width: 13.3413rem;
  height: 2.8245rem;
  margin: 1.75rem 5.0631rem 0;
  background-repeat: no-repeat;
  background-size: 100% 100%;
}
.num-label {
  height: 1.262rem;
  padding-top: 0.1875rem;
  display: flex;
  justify-content: center;
  align-items: center;
}
.num-label__text {
  font-size: 0.625rem;
  color: rgb(255, 255, 255);
}
.num-label__arrow {
  width: 1.0216rem;
  height: 1.0817rem;
  margin: 0 0 0 0.125rem;
}
.num-value {
  font-size: 1.25rem;
  font-weight: 700;
  color: rgb(255, 255, 255);
  margin: 0.0625rem 0 0;
  text-align: center;
}

/* 跑马灯 390x155 */
.lunBg {
  position: relative;
  width: 100%;
  height: 9.3149rem;
  margin-top: 2.75rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  overflow: hidden;
}
.marquee-wrap {
  position: relative;
  height: 9.3149rem;
  margin: 2.75rem 0 0;
}
.marquee-viewport {
  position: absolute;
  left: 0;
  right: 0;
  top: 0;
  height: 9.3149rem;
  overflow: hidden;
}
.marquee-track {
  display: flex;
  align-items: center;
  width: max-content;
  animation: cz-marquee linear infinite;
}
@keyframes cz-marquee {
  from {
    transform: translateX(0);
  }
  to {
    transform: translateX(-2944px);
  }
}
.marquee-item {
  width: 4.6274rem;
  height: 4.1466rem;
  flex: 0 0 auto;
  display: flex;
  justify-content: center;
  align-items: center;
}
.marquee-card {
  width: 4.6274rem;
  height: 4.1466rem;
  display: flex;
  align-items: center;
}
.marquee-img-box {
  width: 4.125rem;
  height: 4.125rem;
  background-color: rgba(255, 255, 255, 0.06);
  border-radius: 0.25rem;
}
.center-frame {
  position: absolute;
  left: 50%;
  top: -0.7812rem;
  width: 6.9712rem;
  height: 10.9375rem;
  margin-left: -3.4856rem;
}

/* 同意行 */
.agree-row {
  margin-top: 1.9375rem;
  height: 0.9615rem;
  display: flex;
  justify-content: center;
  align-items: center;
}
.agree-icon {
  width: 0.7212rem;
  height: 0.7212rem;
  flex: 0 0 auto;
}
.agree-text {
  font-size: 0.6875rem;
  color: rgb(246, 227, 193);
  margin: 0 0 0 0.375rem;
}

/* 重铸按钮 206x53 */
.draw-btn {
  width: 12.3798rem;
  height: 3.1851rem;
  padding-top: 0.1875rem;
  margin: 1.25rem 5.5319rem 0;
  text-align: center;
  background-repeat: no-repeat;
  background-size: 100% 100%;
}
.draw-btn--last {
  margin-top: 1.5625rem;
}
.draw-btn__main {
  font-size: 1rem;
  font-weight: 600;
  color: rgb(255, 255, 255);
  text-align: center;
}
.draw-btn__sub {
  font-size: 0.6875rem;
  color: rgb(246, 227, 193);
  margin: 0.1875rem 0 0;
  text-align: center;
}

/* 规则框 365x149 */
.rule-box {
  width: 21.9351rem;
  height: 8.9543rem;
  padding: 0.1875rem 0.5rem;
  box-sizing: border-box;
  margin: 1.8125rem 0.75rem 0;
  background-repeat: no-repeat;
  background-size: 100% 100%;
}
.rule-title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: rgb(246, 227, 193);
  text-align: center;
}
.rule-content {
  margin: 0.6875rem 0 0;
  font-size: 0.75rem;
  color: rgb(246, 227, 193);
}
.rule-content p {
  margin: 0 0 0.36rem;
}
</style>
