<template>
  <!--
    源站活动玩法页统一返回栏（.backTitle，高 69px）
    与系统导航栏（uni-page-head，高 44px）不同，此为活动页自绘样式：
    返回图标居左、标题绝对居中、右侧 42x42 占位

    图标按源站实际尺寸分三套（实测 20x20 / 26x26 / 42x36）：
      20  —— record 页（bg 浅橙）
      26  —— tupu / baozha（浅色底，白图标）
      42  —— shiyan（顶图深蓝区，带「投放记录」胶囊按钮）
  -->
  <div class="p1-backTitle">
    <img
      class="back"
      :class="'is-' + size"
      :src="icon"
      alt=""
      @click="goBack"
    />
    <div v-if="title" class="p1-navtitle">
      <span class="p1-navtitle__txt" :style="{ color }">{{ title }}</span>
    </div>
    <div class="p1-right-icon">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import back20 from '@/assets/p1/common/back-20.png'
import back26 from '@/assets/p1/common/back-white.png'

const props = defineProps({
  title: { type: String, default: '' },
  color: { type: String, default: '#ffffff' },
  /** 20 | 26 —— 返回图标边长（px），对应源站两套图 */
  size: { type: [Number, String], default: 26 },
})

const router = useRouter()
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/pages/activity/index')
}

const icon = computed(() => (Number(props.size) === 20 ? back20 : back26))
</script>

<style scoped>
.p1-backTitle {
  position: relative;
  width: 100%;
  height: 69px;
  padding: 0 0.9375rem;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}

.back {
  display: block;
  flex-shrink: 0;
  cursor: pointer;
}
.back.is-20 {
  width: 20px;
  height: 20px;
}
.back.is-26 {
  width: 26px;
  height: 26px;
}

.p1-navtitle {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  top: 0;
  width: 8rem;
  height: 69px;
  display: flex;
  justify-content: center;
  align-items: center;
}
.p1-navtitle__txt {
  font-size: 1.0625rem;
  font-weight: 700;
  line-height: 1.4;
  color: #ffffff;
}

.p1-right-icon {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
