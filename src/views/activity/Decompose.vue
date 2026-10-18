<template>
  <!--
    分解兑换页 —— 复刻原站 /pages/activity/decompose
    源站实测：与「合成兑换」页同构（同一套 uv-tabs 骨架），仅 banner 不同：
      banner  /assets/bg-BvPThYnE.png  高 292px（y=44 起）
      搜索框  图标 21x21 @x=30，输入框 x=61 宽 310 高 36，占位「输入活动关键词」
      uv-tabs x=52 高 42，限时活动 / 常驻活动
      card-list 位于 y=427，padding 10.4px，线上无数据
  -->
  <view class="activity-wrap">
    <UniPageHead title="分解兑换" />
    <view class="activity-container">
      <img class="banner" :src="banner" alt="" />

      <view class="panel">
        <view class="search-wrap">
          <img class="search-icon" :src="searchIcon" alt="" />
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

        <view v-if="list.length" class="card-list">
          <view v-for="(c, i) in list" :key="i" class="card">{{ c.name }}</view>
        </view>
        <view v-else class="card-list is-empty" />
      </view>
    </view>
  </view>
</template>

<script setup>
import { ref, computed } from 'vue'
import UniPageHead from '@/components/UniPageHead.vue'
import banner from '@/assets/p1/decompose/bg-BvPThYnE.png'
import searchIcon from '@/assets/p1/decompose/search2-BEEdoS7A.png'

const tabs = [{ name: '限时活动' }, { name: '常驻活动' }]
const current = ref(0)
const keyword = ref('')
/* 源站线上该活动列表接口无数据，.card-list 高度仅 21px（padding 上下 10.4） */
const list = ref([])

/** 与合成兑换页一致：指示线起点 56.6016px，每页步进 143.2px */
const lineOffset = computed(() => (56.6016 + current.value * 143.2).toFixed(4) + 'px')
</script>

<style scoped>
.activity-wrap {
  min-height: 100vh;
  background: #f5f6f8;
}

.activity-container {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.banner {
  width: 100%;
  height: 292px;
  display: block;
  object-fit: cover;
}

.panel {
  width: 100%;
  box-sizing: border-box;
}

.search-wrap {
  position: relative;
  /* 源站实测 x=30 宽 310（含 21px 图标 + 289px 输入框）。
     390 - 310 = 80 → 左右各 40，但源站输入框右边界到 371，
     即容器 x=30 宽 341，内部 input 左内边距 31px。此处按容器 x=30 宽 341 还原。 */
  width: 21.3125rem;
  margin: -0.5625rem 0 0 1.875rem;
  display: flex;
  align-items: center;
}
.search-icon {
  position: absolute;
  left: 1.4375rem;
  width: 21px;
  height: 21px;
  display: block;
  z-index: 2;
}
.search-input {
  width: 100%;
  height: 36px;
  box-sizing: border-box;
  padding: 0 0.5rem 0 2.5rem;
  background: #ffffff;
  border-radius: 18px;
  border: 0.5px solid #dcdfe6;
  font-size: 14px;
  color: rgb(96, 98, 102);
}
.search-input::placeholder {
  color: #999999;
}

.tabs-row {
  width: 100%;
  /* 源站 uv-tabs y=364，搜索框底 363 → 间距 1px */
  margin-top: 0.0625rem;
  display: flex;
  justify-content: center;
}

.uv-tabs {
  display: flex;
  align-items: stretch;
  width: 17.125rem;
}
.uv-tabs__wrapper {
  display: flex;
  align-items: center;
  width: 100%;
}
.uv-tabs__wrapper__scroll-view-wrapper {
  display: flex;
  align-items: stretch;
  flex: 1;
  min-width: 0;
}
.uv-tabs__wrapper__scroll-view {
  flex: 1;
  display: flex;
  align-items: stretch;
}
.uni-scroll-view-content {
  display: flex;
  align-items: stretch;
}
.uv-tabs__wrapper__nav {
  position: relative;
  display: flex;
  width: 100%;
}
.uv-tabs__wrapper__nav__item {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  cursor: pointer;
}
.uv-tabs__wrapper__nav__item__text {
  font-size: 0.875rem;
  line-height: 1;
  white-space: nowrap;
}
.uv-tabs__wrapper__nav__line {
  position: absolute;
  bottom: 0;
  border-radius: 2px;
  transition: left 0.25s ease;
}

.card-list {
  width: 100%;
  box-sizing: border-box;
  padding: 0.65rem;
}
.card-list.is-empty {
  height: 21px;
  padding: 10.4px 0;
}
</style>
