<template>
  <!--
    藏品图谱页 —— 复刻原站 /pages/activity/tupu
    源站实测：tpBg 底图 tp_bg-BYvl9vLG.png (rgb(243,244,248))，
              headBox 高 153px、底部 16.64px 圆角，内含 2 个大 tab
              空态用新图 newNodata-BMz9JLqO.png (138x138)
  -->
  <div class="tpBg" :style="{ backgroundImage: `url(${pageBg})` }">
    <div class="headBox">
      <P1BackBar title="藏品图谱" color="#333333" :size="26" />

      <div class="tabList">
        <div
          v-for="t in TABS"
          :key="t.key"
          class="tabItem"
          :class="{ 'is-active': active === t.key }"
          @click="active = t.key"
        >
          {{ t.name }}
        </div>
      </div>
    </div>

    <div class="empty-wrap">
      <img class="empty-img" :src="noData" alt="" />
      <div class="empty-txt">暂无数据</div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import P1BackBar from '@/components/P1BackBar.vue'
import pageBg from '@/assets/p1/tupu/tp_bg-BYvl9vLG.png'
import noData from '@/assets/p1/tupu/newNodata-BMz9JLqO.png'

const TABS = [
  { key: 'all', name: '全部' },
  { key: 'gift', name: '礼物区' },
]
const active = ref('all')
</script>

<style scoped>
.tpBg {
  min-height: 100vh;
  box-sizing: border-box;
  background-color: rgb(243, 244, 248);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
}

.headBox {
  width: 100%;
  border-radius: 0 0 1rem 1rem;
  overflow: hidden;
}

.tabList {
  display: flex;
  padding: 0 0 0 0.8125rem;
  margin: 0.5rem 0 0;
}

.tabItem {
  padding: 0.875rem 0.9375rem;
  border-radius: 1rem;
  font-size: 0.875rem;
  line-height: 1.5;
  margin: 0.25rem 0.125rem;
  background: #ffffff;
  color: #333333;
  cursor: pointer;
  border: 1px solid rgba(0, 0, 0, 0.04);
}
.tabItem.is-active {
  background: rgb(53, 121, 252);
  color: #ffffff;
  font-weight: 500;
}

.empty-wrap {
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
