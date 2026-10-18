<template>
  <nav class="nb-tabbar">
    <div class="nav-inner">
      <div class="nav-bg">
        <div class="nav-rail" />
        <div class="nav-bump" />
      </div>

      <!-- 四个主 tab，按原站百分比定位 -->
      <a
        v-for="t in tabs"
        :key="t.key"
        class="nav-tab"
        :class="{ 'is-current': t.key === activeKey }"
        :style="{ left: t.center + '%' }"
        @click="switchTab(t)"
      >
        <img class="nav-tab-icon" :src="tabIcon(t)" :alt="t.name" />
        <span class="nav-tab-label">{{ t.name }}</span>
      </a>

      <!-- 选中指示条 -->
      <i v-if="indicatorVisible" class="nav-indicator" :style="{ left: indicatorCenter + '%' }" />

      <!-- 中央公告按钮（凸起圆形） -->
      <a class="nav-center" @click="goCenter">
        <img class="nav-center-img" :src="centerIcon" alt="公告" />
      </a>
    </div>
  </nav>
</template>

<script>
import { defineComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'

// 静态引入原站 tabbar 图标，确保打包为单文件时一并内联
import homeOn from '@/assets/tabbar/tab-home-on.png'
import homeOff from '@/assets/tabbar/tab-home-off.png'
import marketOn from '@/assets/tabbar/tab-market-on.png'
import marketOff from '@/assets/tabbar/tab-market-off.png'
import copyrightOn from '@/assets/tabbar/tab-copyright-on.png'
import copyrightOff from '@/assets/tabbar/tab-copyright-off.png'
import myOn from '@/assets/tabbar/tab-my-on.png'
import myOff from '@/assets/tabbar/tab-my-off.png'
import centerImg from '@/assets/tabbar/nav-center-DoLKnzNx.png'

/** 与原站 nb-tabbar 组件数据完全一致（含百分比定位） */
const TABS = [
  { key: 'index', name: '首页', url: '/pages/index/index', center: 11.885, on: homeOn, off: homeOff },
  { key: 'market', name: '市场', url: '/pages/market/index', center: 30.861, on: marketOn, off: marketOff },
  { key: 'copyright', name: '版权', url: '/pages/copyright/index', center: 69.391, on: copyrightOn, off: copyrightOff },
  { key: 'my', name: '我的', url: '/pages/my/index', center: 88.22, on: myOn, off: myOff },
]
const CENTER_TAB = { key: 'notice', name: '公告', url: '/pages/notice/index', center: 50 }

export default defineComponent({
  name: 'TabBar',
  props: { current: { type: String, default: '' } },
  data() {
    return { tabs: TABS, activeKey: '' }
  },
  computed: {
    isCenterActive() {
      return this.activeKey === CENTER_TAB.key
    },
    indicatorVisible() {
      return !!this.activeTab || this.isCenterActive
    },
    activeTab() {
      return this.tabs.find((t) => t.key === this.activeKey) || null
    },
    indicatorCenter() {
      if (this.activeTab) return this.activeTab.center
      if (this.isCenterActive) return CENTER_TAB.center
      return null
    },
    centerIcon() {
      return centerImg
    },
  },
  watch: {
    $route: { immediate: true, handler() { this.detect() } },
  },
  methods: {
    detect() {
      const key = this.current || this.$route.meta?.tabKey || ''
      this.activeKey = key
    },
    tabIcon(t) {
      return t.key === this.activeKey ? t.on : t.off
    },
    switchTab(t) {
      if (t.key === this.activeKey) return
      this.$router.replace(t.url)
    },
    goCenter() {
      if (this.isCenterActive) return
      this.$router.push(CENTER_TAB.url)
    },
  },
})
</script>

<style scoped>
/* 数值与尺寸全部取自原站 nb-tabbar-DQSAVK26.css，未作改动 */
.nb-tabbar {
  position: fixed; left: 0; right: 0; bottom: 0; z-index: 99;
  padding-bottom: calc(0 + env(safe-area-inset-bottom, 0px));
  pointer-events: none;
}
.nav-inner {
  position: relative; width: 100%; max-width: 23.4375rem; margin: 0 auto;
  height: 4.78125rem;
}
.nav-bg {
  position: absolute; left: 0; right: 0; top: 0; height: 4.78125rem;
  filter: drop-shadow(0 0 8px rgba(45, 89, 188, 0.1));
}
.nav-rail {
  position: absolute; left: 50%; transform: translate(-50%);
  top: 0.5rem; width: 96.45%; height: 3.78125rem;
  border-radius: 0.65625rem; background-color: #f3f8fe;
}
.nav-bump {
  position: absolute; left: 50%; transform: translate(-50%);
  top: 0; width: 4.78125rem; height: 4.78125rem;
  border-radius: 50%; background-color: #f3f8fe;
}
.nav-tab {
  position: absolute; top: 1.21875rem; transform: translate(-50%);
  display: flex; flex-direction: column; align-items: center;
  width: 1.4375rem; pointer-events: auto; cursor: pointer;
}
.nav-tab-icon { width: 1.4375rem; height: 1.4375rem; display: block; }
.nav-tab-label {
  margin-top: 0.0625rem;
  font-family: 'PingFang', 'PingFang SC', sans-serif;
  font-size: 0.59375rem; color: #4c5370; line-height: 1; white-space: nowrap;
}
.nav-tab.is-current .nav-tab-label {
  font-family: 'PingFangBold', 'PingFang SC', sans-serif;
  font-weight: 700; color: #0d0f38;
}
.nav-indicator {
  position: absolute; top: 4.15625rem; transform: translate(-50%);
  width: 1.125rem; height: 0.15625rem; border-radius: 0.09375rem;
  background: radial-gradient(circle, #3f8cff, rgba(63, 140, 255, 0));
}
.nav-center {
  position: absolute; left: 50%; transform: translate(-50%);
  top: 0.78125rem; width: 3.21875rem; height: 3.21875rem;
  border-radius: 50%; pointer-events: auto; cursor: pointer;
}
.nav-center-img { width: 3.21875rem; height: 3.21875rem; display: block; }
</style>