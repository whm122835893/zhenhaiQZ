<template>
  <!--
    活动记录页 —— 复刻原站 /pages/activity/record
    源站实测：composeBg 全屏底图 bg-Be67UB1r.jpg (rgb(255,233,208))
              .content 白色圆角卡片 + top-ChxgdGDM.png 顶图，5 个胶囊 tab
              当前接口返回空，展示「暂无数据」空态
  -->
  <div class="composeBg" :style="{ backgroundImage: `url(${pageBg})` }">
    <P1BackBar title="活动记录" :size="20" />

    <div class="content" :style="{ backgroundImage: `url(${cardTop})` }">
      <div class="tabList centerBetween">
        <div
          v-for="t in TABS"
          :key="t.key"
          class="tabItem doubleCenter"
          :class="active === t.key ? 'selected' : 'unselect'"
          @click="active = t.key"
        >
          {{ t.name }}
        </div>
      </div>

      <div v-if="list.length" class="listBox">
        <div v-for="(row, i) in list" :key="i" class="row">{{ row }}</div>
      </div>
      <div v-else class="empty-wrap">
        <img class="empty-img" :src="noData" alt="" />
        <div class="empty-txt">暂无数据</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import P1BackBar from '@/components/P1BackBar.vue'
import pageBg from '@/assets/p1/record/bg-Be67UB1r.jpg'
import cardTop from '@/assets/p1/record/top-ChxgdGDM.png'
import noData from '@/assets/p1/record/nodata-DRgWhMdI.png'

/* 源站 tab 顺序与实测 x 坐标：全部(36) 合成(96) 熔炉(156) 点石成金(215) 宝箱(300) */
const TABS = [
  { key: 'all', name: '全部' },
  { key: 'compose', name: '合成' },
  { key: 'furnace', name: '熔炉' },
  { key: 'gold', name: '点石成金' },
  { key: 'box', name: '宝箱' },
]

const active = ref('all')
/* 源站线上该接口无数据（正文仅「活动记录 + tab + 暂无数据」），保持空态 */
const list = ref([])
</script>

<style scoped>
.composeBg {
  min-height: 100vh;
  box-sizing: border-box;
  background-color: rgb(255, 233, 208);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
}

.content {
  width: calc(100% - 1.5rem);
  margin: 10.4px auto 0;
  padding: 18.72px 12.48px;
  box-sizing: border-box;
  border-radius: 20.8px;
  background-color: #fff;
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
}

.tabList {
  margin: 0 0.625rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.tabItem {
  border-radius: 46.8px;
  padding: 0.375rem 0.875rem;
  font-size: 0.7813rem;
  line-height: 1.2;
  cursor: pointer;
  white-space: nowrap;
  /* 源站 tabItem 为 flex 居中盒，宽度由内容决定；
     曾误加 width:100% 导致横向撑满并把文案挤成两行 */
  flex: 0 0 auto;
}
.tabItem.selected {
  background-color: rgb(255, 236, 219);
  color: rgb(255, 51, 0);
}
.tabItem.unselect {
  background-color: rgba(189, 189, 189, 0.16);
  color: rgb(69, 68, 67);
}

.empty-wrap {
  margin-top: 16.88px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.empty-img {
  width: 99px;
  height: 90px;
  display: block;
}
.empty-txt {
  font-size: 14px;
  color: #999999;
  margin-top: 13px;
  line-height: 21px;
}
</style>
