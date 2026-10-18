<template>
  <view class="activity-wrap">
    <UniPageHead title="合成兑换" />
    <view class="activity-container">
    <!-- 顶部 banner：源站 /assets/bg1-BS9SelRK.png，固定 292px -->
    <img class="banner" :src="bg1" alt="" />

    <view class="panel">
      <view class="search-wrap">
        <img class="search-icon" :src="search2" alt="" />
        <input
          v-model="keyword"
          class="search-input"
          type="text"
          maxlength="140"
          placeholder="输入活动关键词"
          placeholder-class="input-placeholder"
          confirm-type="search"
        />
      </view>

      <view class="tabs-row">
        <view class="uv-tabs">
          <view class="uv-tabs__wrapper">
            <view class="uv-tabs__wrapper__scroll-view-wrapper">
              <view class="uv-tabs__wrapper__scroll-view">
                <view class="uni-scroll-view-content">
                  <view class="uv-tabs__wrapper__nav">
                    <view
                      v-for="(t, i) in tabs"
                      :key="t.name"
                      class="uv-tabs__wrapper__nav__item"
                      :style="{ height: '2.5rem', padding: '0 2.5rem' }"
                      @click="current = i"
                    >
                      <text
                        class="uv-tabs__wrapper__nav__item__text"
                        :style="
                          i === current
                            ? { color: '#303133', fontWeight: 'bold', transform: 'scale(1.05)' }
                            : { color: '#606266' }
                        "
                      >{{ t.name }}</text>
                    </view>
                    <view
                      class="uv-tabs__wrapper__nav__line"
                      :style="{
                        width: '30px',
                        height: '2px',
                        background: '#2e2d33',
                        left: lineOffset,
                      }"
                    />
                  </view>
                </view>
              </view>
            </view>
          </view>
        </view>
      </view>
    </view>

    <view class="card-list">
      <view v-for="item in list" :key="item.id" class="card-item" @click="goDetail(item)">
        <view class="card-item-top">
          <img v-if="item.coverUrl" class="card-image" :src="item.coverUrl" alt="" />
          <view class="card-content-wrap">
            <text class="card-title">{{ item.name }}</text>
            <view class="card-info">
              <text class="limit-text">限 {{ item.total }}</text>
              <text class="remain-text">剩余 {{ item.surplus }}</text>
            </view>
            <text class="card-time">{{ item.rangeTime }}</text>
          </view>
          <view class="status-tag">
            <view class="dot" />
            <text>{{ item.stateText }}</text>
          </view>
        </view>
        <view class="card-item-bottom">{{ item.remark }}</view>
      </view>
    </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { activityApi } from '@/api'
import UniPageHead from '@/components/UniPageHead.vue'
import bg1 from '@/assets/activity/bg1.png'
import search2 from '@/assets/activity/search2.png'

const router = useRouter()

const tabs = [
  { name: '限时活动', type: 1 },
  { name: '常驻活动', type: 2 },
]
const current = ref(0)
const keyword = ref('')
const all = ref([])

/** 源站 uv-tabs line 起始位置：translate(56.6016px) / 每页宽 143.2 */
const lineOffset = computed(() => (56.6016 + current.value * 143.2).toFixed(4) + 'px')

/** 源站 card-list 请求 /item/merge，按 tab type 过滤；线上恒返回空数组 */
const list = computed(() => {
  const t = tabs[current.value].type
  const k = keyword.value.trim()
  return all.value.filter((x) => {
    if (x.type != null && Number(x.type) !== t) return false
    if (k && !(x.name || '').includes(k)) return false
    return true
  })
})

function goDetail(item) {
  router.push({
    path: '/pages/activity/mergeInfo',
    query: { id: item.id },
  })
}

onMounted(async () => {
  try {
    const res = await activityApi.listMerge()
    if (res && res.status) all.value = res.data || []
  } catch (e) {
    all.value = []
  }
})
</script>

<style scoped>
.activity-wrap {
  min-height: 100vh;
  background-color: #eee;
}
.activity-container {
  color: #000;
  min-height: 100vh;
  box-sizing: border-box;
}
.banner {
  width: 100%;
  position: relative;
  height: 292px;
  display: block;
}
.panel {
  margin: -1.40625rem 0.625rem 0.625rem;
  z-index: 1;
  position: relative;
  border-radius: 28px;
  background: #fff;
  padding: 8px 8px 11px;
}
.search-wrap {
  position: relative;
  background: #eee;
  border-radius: 28px;
  border: 0.5px solid #2e2d33;
  margin-bottom: 4px;
}
.search-icon {
  width: 1.25rem;
  height: 1.25rem;
  position: absolute;
  top: 0.9375rem;
  left: 1.1875rem;
}
.search-input {
  text-align: left;
  color: #606266;
  border-radius: 100px;
  border-color: transparent;
  padding-left: 2.5rem;
  background-color: #eee;
  height: 36px;
  width: 100%;
  font-size: 14px;
  box-sizing: border-box;
}
.input-placeholder {
  font-size: 14px;
  color: #999;
}
.tabs-row {
  display: flex;
  justify-content: center;
  align-items: center;
}
.uv-tabs {
  display: flex;
  flex-direction: column;
  flex: 0 0 auto;
  align-items: stretch;
  align-content: flex-start;
}
.uv-tabs__wrapper {
  display: flex;
  flex-direction: row;
  align-items: center;
}
.uv-tabs__wrapper__scroll-view-wrapper {
  flex: 1 1 0%;
  overflow: auto hidden;
}
.uv-tabs__wrapper__scroll-view {
  display: flex;
  flex-direction: row;
  flex: 1 1 0%;
}
.uni-scroll-view-content {
  display: flex;
  flex-direction: row;
}
.uv-tabs__wrapper__nav {
  display: flex;
  flex-direction: row;
  position: relative;
}
.uv-tabs__wrapper__nav__item {
  padding: 0 11px;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
}
.uv-tabs__wrapper__nav__item__text {
  font-size: 15px;
  color: #606266;
}
.uv-tabs__wrapper__nav__line {
  left: 0;
  height: 3px;
  background: #3c9cff;
  width: 30px;
  position: absolute;
  bottom: 2px;
  border-radius: 100px;
  transition-property: transform;
  transition-duration: 0.3s;
}
.card-list {
  padding: 0.625rem;
}
.card-item {
  overflow: hidden;
  background: #fff;
  border-radius: 22px;
  margin-bottom: 13px;
  box-shadow:
    0 1px 1px rgba(0, 0, 0, 0.06),
    0 2px 3px rgba(0, 0, 0, 0.04);
}
.card-item-top {
  background-color: #fff;
  overflow: hidden;
  position: relative;
  display: flex;
  justify-content: space-between;
  box-sizing: border-box;
  padding: 0.625rem;
  border-radius: 22px 22px 0 0;
}
.card-item-bottom {
  padding: 8px 11px;
  background: #f5f5f5;
  font-size: 12px;
  color: #999;
}
.status-tag {
  position: absolute;
  bottom: 0;
  left: 0;
  background-color: #999;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9;
  color: #000;
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 0 0.9375rem 0.9375rem 0;
}
.status-tag .dot {
  width: 0.3125rem;
  height: 0.3125rem;
  border-radius: 50%;
  background-color: #fff;
  margin-right: 0.25rem;
}
.card-image {
  flex-shrink: 0;
  width: 120px;
  height: 120px;
  border-radius: 11px;
}
.card-content-wrap {
  flex: 1 1 0%;
  padding: 10px 0 10px 10px;
}
.card-title {
  display: block;
  width: 10.625rem;
  font-size: 17px;
  font-weight: 500;
  color: #333;
  margin-bottom: 10px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-sizing: border-box;
}
.card-info {
  display: flex;
  font-size: 0.875rem;
  color: #666;
  margin-bottom: 0.3125rem;
}
.card-info .limit-text,
.card-info .remain-text {
  margin-right: 0.3125rem;
}
.card-time {
  display: block;
  font-weight: 400;
  font-size: 10px;
  color: #999;
}
</style>