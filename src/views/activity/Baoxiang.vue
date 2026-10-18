<template>
  <view class="containerStyle composeBg0">
    <!-- 顶部返回栏 -->
    <view class="backTitle font-000-34-bold">
      <img class="back" :src="backArrow" alt="" @click="goBack" />
      <view class="navtitle absoluteCenter">
        <view class="tip text-center">
          <view class="inner">
            <text class="tip-inder">{{ title }}</text>
          </view>
        </view>
      </view>
      <view class="right-icon" />
    </view>

    <!-- 合成卡 -->
    <view class="composeCard columnCenter">
      <view class="record doubleCenter" @click="goRecord">
        <img class="record__icon" :src="recordIcon" alt="" />
        <text class="record__txt">活动记录</text>
      </view>
      <view class="logoName center">
        <img class="logoName__logo" :src="logoWhite" alt="" />
      </view>
      <img class="composeCard__title" :src="title4" alt="" />
      <img class="composeCard__sub" :src="title5" alt="" />
    </view>

    <!-- 源站线上 /item/merge 系列接口返回空，v-for 不渲染任何卡片 -->
    <view v-if="list.length > 0" class="list">
      <view
        v-for="(row, i) in list"
        :key="row.id + i"
        class="itemView otherItem"
        @click="goMerge(row)"
      >
        <view class="infoBox">
          <img class="cover" :src="row.cover" alt="" />
          <view class="infoBox2">
            <view class="nameTime">
              <text class="name">{{ row.name }}</text>
              <text class="startTime">{{ row.startTime }}</text>
            </view>
            <view class="centerBetween">
              <view class="stateBtn">{{ row.stateText }}</view>
              <view class="goMsg">
                <text class="underline">查看详情</text>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import baoxiangBg from '@/assets/activity/baoxiang-bg.jpg'
import backArrow from '@/assets/activity/back-arrow.png'
import recordIcon from '@/assets/activity/record-icon.png'
import logoWhite from '@/assets/activity/logo-white.png'
import title4 from '@/assets/activity/title4.png'
import title5 from '@/assets/activity/title5.png'

const router = useRouter()
const list = ref([])
const title = ''

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

function goRecord() {
  router.push('/pages/activity/record?id=1')
}

function goMerge(row) {
  router.push({ path: '/pages/activity/mergeInfo', query: { id: row.id } })
}

/**
 * 源站 getList() 原文（assets/pages-activity-baoxiang.I1d4E-db.js）：
 *   getList(){ return this.list=[], this.total=0, this.lastPage=1,
 *              void(this.ajax.load=!1) }
 *
 * 即**不发任何请求，直接把列表清空** —— 该活动在源站已下线，
 * 所以线上永远不渲染任何卡片，封面图 cover 也永远不出现。
 *
 * 之前本地多调了 activityApi.listMerge({type:2})，接口有数据 → 渲染出一张
 * 封面 src 为相对路径 media/xxx.png 的卡片 → 破图 + 与源站像素不一致。
 * 现按源站行为改为恒空。
 */
onMounted(() => {
  list.value = []
})
</script>

<style scoped>
.containerStyle {
  color: #000;
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
  padding-bottom: var(--window-bottom);
}
.composeBg0 {
  background: url('@/assets/activity/baoxiang-bg.jpg') center top / cover no-repeat #ffe9d0;
}
.backTitle {
  position: relative;
  width: 100%;
  height: 4.125rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0.9375rem;
  box-sizing: border-box;
  color: #fff;
}
.back {
  display: block;
  width: 20px;
  height: 20px;
  object-fit: contain;
}
.navtitle {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 0;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 12.5rem;
}
.tip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.tip-inder {
  font-size: 1.0625rem;
  font-weight: 700;
  color: #000;
}
.right-icon {
  width: 2.5rem;
  height: 2.5rem;
}
.columnCenter {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.doubleCenter,
.center {
  display: flex;
  align-items: center;
  justify-content: center;
}
.absoluteCenter {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}
.text-center {
  text-align: center;
}
.composeCard {
  position: relative;
  width: 23.4375rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-top: 0.9375rem;
  padding-bottom: 0.625rem;
}
.record {
  position: absolute;
  top: -5px;
  right: -0.5px;
  width: 99px;
  height: 32px;
  box-sizing: border-box;
  background: rgba(255, 255, 255, 0.22);
  box-shadow: 0 0.5px 0.5px rgba(255, 255, 255, 0.2);
  font-weight: 500;
  color: #fff;
  font-size: 13px;
  border: 0.5px solid #fff;
  border-radius: 22px 0 0 22px;
}
.record__icon {
  width: 1rem;
  height: 1rem;
  margin-right: 0.3125rem;
}
.record__txt {
  font-size: 13px;
  font-weight: 500;
  color: #fff;
}
.logoName {
  display: flex;
  align-items: center;
  justify-content: center;
}
.logoName__logo {
  width: 4.6875rem;
  height: 0.875rem;
  margin-right: 0.25rem;
}
.composeCard__title {
  width: 13.4375rem;
  height: 2.2846rem;
  margin-top: 0.9375rem;
}
.composeCard__sub {
  width: 8.75rem;
  height: 1.6719rem;
  margin-top: 0.8125rem;
}
.list {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0 0.75rem;
  box-sizing: border-box;
  margin-top: 0.625rem;
}
.itemView {
  width: 21.9375rem;
  min-height: 8.375rem;
  background-color: #fff;
  border-radius: 1.21875rem;
  padding: 0.84375rem 0.75rem;
  box-sizing: border-box;
  margin-bottom: 0.625rem;
  display: flex;
  flex-direction: column;
}
.otherItem {
  position: relative;
  overflow: hidden;
}
.infoBox {
  display: flex;
  align-items: flex-start;
}
.infoBox2 {
  width: 100%;
}
.cover {
  width: 5.5625rem;
  height: 5.5625rem;
  border-radius: 0.8125rem;
  flex-shrink: 0;
  margin-right: 0.625rem;
}
.nameTime {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 5.5625rem;
}
.name {
  font-size: 1rem;
  font-weight: 700;
  color: #6d1d17;
  line-height: 1.4;
}
.startTime {
  font-size: 0.75rem;
  color: #999;
  margin-top: 0.375rem;
}
.centerBetween {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.5rem;
}
.stateBtn {
  width: 4.375rem;
  height: 1.75rem;
  border-radius: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #fff;
  background-color: #ff9a7a;
}
.goMsg {
  font-size: 0.75rem;
  color: #3578fc;
}
.underline {
  text-decoration: underline;
}
</style>