<template>
  <transition name="app-loading">
    <div v-if="state.visible" class="app-loading" @touchmove.prevent.stop>
      <div class="app-loading__box">
        <span class="app-loading__spinner"></span>
        <span v-if="state.message" class="app-loading__text">{{ state.message }}</span>
      </div>
    </div>
  </transition>
</template>

<script setup>
import { state } from '@/utils/loading'
</script>

<style>
.app-loading {
  position: fixed;
  left: 0;
  top: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 99999;
}
.app-loading__box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.app-loading__spinner {
  width: 30px;
  height: 30px;
  border: 3px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: app-loading-spin 0.9s linear infinite;
}
.app-loading__text {
  font-size: 13px;
  color: #fff;
  font-family: 'PingFang SC', sans-serif;
}
@keyframes app-loading-spin {
  to {
    transform: rotate(360deg);
  }
}
.app-loading-enter-active,
.app-loading-leave-active {
  transition: opacity 0.2s;
}
.app-loading-enter-from,
.app-loading-leave-to {
  opacity: 0;
}
</style>