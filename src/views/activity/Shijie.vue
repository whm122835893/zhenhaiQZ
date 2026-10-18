<template>
  <!--
    芥子世界 —— 复刻原站 /pages/activity/shijie
    源站实测（页面总高 846，无系统导航栏）：
      .topBox 高 106，底图 top-D1qSyAAk.jpg（390x106）
        .userBox 390x50 @y=51：左 logoBox 50x50 @x=17（内头像 42x42）+ 昵称「芥子旅人」12.48px；
          右 numBoxs 183x29 @x=193，两个 numBox 83x29（margin-left 16.64px）
      .centerBox 390x624 @y=106，三场景背景 bg1/bg2/bg3（background-size 100% 100%）
        tab1：record 75x75 @x=311 y=136；swiper 390x622 内含两屏（z1 首页 / z2 次屏）
             z1：next 98x75 @x=292 y=220、cover 390x266 @y=239、btn 212x75 @x=89 y=615
             z2：pre 98x75、cover 390x320、btn 212x75（横向偏移 390）
        tab2：main1 239x225 @6,121；main2 216x293 @174,240；main3 225x252 @17,430
        tab3：仅背景
      .bottomBox 390x119 @y=728：back 67x50 @x=17 y=753；tab1 99x99 @73,718（选中态）；tab2/tab3 92x92 @y=734
  -->
  <div class="sjPage">
    <div class="topBox" :style="{ backgroundImage: `url(${topBg})` }">
      <div class="userBox mb10 centerBetween">
        <div class="logoName center">
          <div class="logoBox doubleCenter" :style="{ backgroundImage: `url(${logoBox})` }">
            <img class="logo" :src="avatar" alt="" />
          </div>
          <span class="name font-fff-24-500">芥子旅人</span>
        </div>
        <div class="numBoxs center font-fff-20-500">
          <div class="numBox n1 doubleCenter" :style="{ backgroundImage: `url(${n1})` }">{{ blueCount }}</div>
          <div class="numBox n2 doubleCenter" :style="{ backgroundImage: `url(${n2})` }">{{ redCount }}</div>
        </div>
      </div>
    </div>

    <div class="centerBox" :style="{ backgroundImage: `url(${scenes[tab].bg})` }">
      <!-- 记录入口 -->
      <img v-if="tab === 0" class="record" :src="record" alt="" />

      <!-- tab1：仙府世界（左右两屏） -->
      <div v-if="tab === 0" class="sceneTrack" :style="{ transform: `translateX(${-scene * 23.4375}rem)` }">
        <div class="swiperBox">
          <img class="next preNext" :src="rightArrow" alt="" @click="scene = 1" />
          <img class="cover cover1" :src="z1Bg" alt="" />
          <img class="btn btn1" :src="z1Btn" alt="" @click="onConvert" />
        </div>
        <div class="swiperBox">
          <img class="pre preNext" :src="leftArrow" alt="" @click="scene = 0" />
          <img class="cover cover2" :src="z2Bg" alt="" />
          <img class="btn btn2" :src="z2Btn" alt="" @click="onConvert" />
        </div>
      </div>

      <!-- tab2：自造仙境（三块浮岛） -->
      <template v-else-if="tab === 1">
        <img class="main main1" :src="main1" alt="" />
        <img class="main main2" :src="main2" alt="" />
        <img class="main main3" :src="main3" alt="" />
      </template>

      <!-- tab3：修真市集（源站仅背景图） -->
    </div>

    <div class="bottomBox" :style="{ backgroundImage: `url(${bottomBg})` }">
      <img class="back" :src="back" alt="" @click="goBack" />
      <img
        v-for="(s, i) in sceneTabs"
        :key="i"
        class="tab"
        :class="[`tab${i + 1}`, { tabed: tab === i }]"
        :src="s.icon"
        alt=""
        @click="onTab(i)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import topBg from '@/assets/p1/shijie/top-D1qSyAAk.jpg'
import logoBox from '@/assets/p1/shijie/logoBox-A7fmTD1K.png'
import avatar from '@/assets/p1/shijie/a.png'
import n1 from '@/assets/p1/shijie/n1.png'
import n2 from '@/assets/p1/shijie/n2.png'
import record from '@/assets/p1/shijie/record-BmhtnhQk.png'
import bg1 from '@/assets/p1/shijie/bg1-HQm_XLKH.jpg'
import bg2 from '@/assets/p1/shijie/bg2-BtHkf9TA.jpg'
import bg3 from '@/assets/p1/shijie/bg3-CElDIx98.jpg'
import rightArrow from '@/assets/p1/shijie/right-_60g99Wy.png'
import leftArrow from '@/assets/p1/shijie/left-U4YPyAei.png'
import z1Bg from '@/assets/p1/shijie/z1_bg-Bt8lQPVc.png'
import z2Bg from '@/assets/p1/shijie/z2_bg-C5WYzU0H.png'
import z1Btn from '@/assets/p1/shijie/z1-DXFMOFnq.png'
import z2Btn from '@/assets/p1/shijie/z2-Cjmi-kPy.png'
import main1 from '@/assets/p1/shijie/main1-hJ6mbdF4.png'
import main2 from '@/assets/p1/shijie/main2-Zn-3_09w.png'
import main3 from '@/assets/p1/shijie/main3-csKPBx_g.png'
import bottomBg from '@/assets/p1/shijie/bottom-BVpMwrmA.png'
import back from '@/assets/p1/shijie/back.png'
import tab1Icon from '@/assets/p1/shijie/tab1-Ccxy2XXM.png'
import tab2Icon from '@/assets/p1/shijie/tab2-Dekwafeu.png'
import tab3Icon from '@/assets/p1/shijie/tab3-BWZG9VXM.png'

const router = useRouter()

const tab = ref(0)
const scene = ref(0)
const blueCount = ref(128)
const redCount = ref(36)

const scenes = [{ bg: bg1 }, { bg: bg2 }, { bg: bg3 }]
const sceneTabs = [{ icon: tab1Icon }, { icon: tab2Icon }, { icon: tab3Icon }]

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/activity/index')
}

function onTab(i) {
  tab.value = i
}

function onConvert() {
  router.push('/pages/activity/index')
}
</script>

<style scoped>
.sjPage {
  position: relative;
  width: 100%;
  height: 50.8413rem;
  overflow: hidden;
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}

/* ---------- 顶部 ---------- */
.topBox {
  height: 6.3702rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  display: flex;
  align-items: flex-end;
  overflow: hidden;
}
.userBox {
  width: 100%;
  height: 3.0048rem;
  padding: 0 0.8125rem 0 1rem;
  box-sizing: border-box;
  margin-bottom: 0.3125rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.logoName {
  display: flex;
  align-items: center;
}
.logoBox {
  width: 3.0048rem;
  height: 3.0048rem;
  margin-right: 0.375rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}
.logo {
  width: 2.524rem;
  height: 2.524rem;
  border-radius: 50%;
  border: 0.06rem solid rgb(255, 255, 255);
}
.name {
  font-size: 0.75rem;
  font-weight: 500;
  color: rgb(255, 255, 255);
}
.numBoxs {
  margin-right: 0.0625rem;
  font-size: 0.625rem;
  font-weight: 500;
  color: rgb(255, 255, 255);
  display: flex;
  align-items: center;
}
.numBox {
  width: 4.988rem;
  height: 1.7428rem;
  padding-top: 0.1562rem;
  box-sizing: border-box;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}
.numBox.n2 {
  margin-left: 1rem;
}

/* ---------- 中间场景 ---------- */
.centerBox {
  position: relative;
  width: 100%;
  height: 37.5rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  overflow: hidden;
}
.record {
  position: absolute;
  right: 0.25rem;
  top: 1.8029rem;
  width: 4.5072rem;
  height: 4.5072rem;
  z-index: 99;
}

/* tab1 左右两屏 */
.sceneTrack {
  position: absolute;
  left: 0;
  top: 0;
  width: 46.875rem;
  height: 37.3798rem;
  display: flex;
}
.swiperBox {
  position: relative;
  width: 23.4375rem;
  height: 37.3798rem;
  flex: 0 0 auto;
}
.preNext {
  position: absolute;
  top: 6.8606rem;
  width: 5.8894rem;
  height: 4.5072rem;
  z-index: 1;
}
.next {
  right: 0;
}
.pre {
  left: 0;
}
.cover {
  position: absolute;
  left: 0;
  width: 23.4375rem;
}
.cover1 {
  top: 8rem;
  height: 15.9856rem;
}
.cover2 {
  top: 8rem;
  height: 19.2308rem;
}
.btn {
  position: absolute;
  left: 5.3483rem;
  top: 30.649rem;
  width: 12.7404rem;
  height: 4.5072rem;
}

/* tab2 三块浮岛 */
.main {
  position: absolute;
}
.main1 {
  left: 0.3606rem;
  top: 0.9014rem;
  width: 14.3629rem;
  height: 13.5216rem;
}
.main2 {
  left: 10.4567rem;
  top: 8.0721rem;
  width: 12.9808rem;
  height: 17.6082rem;
}
.main3 {
  left: 1.0216rem;
  top: 19.4712rem;
  width: 13.5216rem;
  height: 15.1442rem;
}

/* ---------- 底部 ---------- */
.bottomBox {
  position: absolute;
  left: 0;
  top: 43.78rem;
  width: 100%;
  height: 7.1514rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
}
.back {
  position: absolute;
  left: 1.0216rem;
  top: 1.4724rem;
  width: 4.0264rem;
  height: 3.0048rem;
}
.tab {
  position: absolute;
}
.tab1 {
  left: 4.387rem;
  top: -0.631rem;
  width: 5.9495rem;
  height: 5.9495rem;
}
.tab2 {
  left: 10.8774rem;
  top: 0.3306rem;
  width: 5.5288rem;
  height: 5.5288rem;
}
.tab3 {
  left: 16.9471rem;
  top: 0.3606rem;
  width: 5.5288rem;
  height: 5.5288rem;
}
</style>
