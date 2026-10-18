<template>
  <div class="app-root">
    <router-view v-slot="{ Component }">
      <transition :name="routeTransition">
        <keep-alive :include="['MarketPage', 'MyPage', 'IndexPage', 'CopyrightPage']">
          <component :is="Component" :key="routeKey" />
        </keep-alive>
      </transition>
    </router-view>
    <TabBar v-if="showTabBar" />
    <AppLoading />
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import TabBar from '@/components/TabBar.vue'
import AppLoading from '@/components/AppLoading.vue'
import { resetLoading } from '@/utils/loading'

const route = useRoute()
const showTabBar = computed(() => !!(route.meta && route.meta.tab))
const routeTransition = computed(() => 'page-fade')
const routeKey = computed(() => route.path)

// 路由切换时复位 loading，避免异常情况下遮罩残留
watch(routeKey, () => resetLoading())
</script>

<style>
.app-root { width: 100%; min-height: 100%; background: var(--color-bg-page); }
.page-fade-enter-active { transition: opacity .2s ease; }
.page-fade-leave-active { transition: opacity .15s ease; }
.page-fade-enter-from, .page-fade-leave-to { opacity: 0; }
/* 路由过渡期间隐藏，避免闪烁 */
.route-loading { display: none; }
</style>