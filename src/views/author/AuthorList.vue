<template>
  <view class="author-page">
    <view class="header">
      <view class="back-btn" @click="goBack">
        <view class="back-icon" />
      </view>
      <img class="brand-logo" :src="logoSrc" alt="" />
      <img class="mascot" :src="mascotSrc" alt="" />
    </view>

    <view class="search-bar">
      <img class="search-icon" :src="searchIcon" alt="" />
      <input
        v-model="keyword"
        class="search-input"
        type="text"
        maxlength="140"
        placeholder="搜索创作者昵称"
        placeholder-class="search-placeholder"
        confirm-type="search"
      />
    </view>

    <view v-if="list.length > 0" class="creator-grid">
      <view
        v-for="item in list"
        :key="item.id"
        class="creator-card"
        @click="goDetail(item.id)"
      >
        <view class="card-top">
          <img class="avatar" :src="item.avatarUrl" alt="" />
          <text class="name">{{ item.name }}</text>
          <view class="arrow">
            <view class="arrow-icon" />
          </view>
        </view>
        <text class="intro">{{ item.introduction }}</text>
      </view>
    </view>

    <view v-else class="empty">
      <img class="empty-icon" :src="emptyIcon" alt="" />
      <text class="empty-text">暂无数据</text>
    </view>
  </view>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { itemApi } from '@/api'
import logoSrc from '@/assets/author/logo.png'
import mascotSrc from '@/assets/author/mascot.png'
import searchIcon from '@/assets/author/search-icon.png'
import emptyIcon from '@/assets/author/sec-title.png'

const router = useRouter()
const ALL = ref([])
const keyword = ref('')

const list = computed(() => {
  const k = keyword.value.trim()
  if (!k) return ALL.value
  return ALL.value.filter((x) => (x.name || '').includes(k))
})

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/')
}

function goDetail(id) {
  router.push(`/pages/author/detail?id=${id}`)
}

onMounted(async () => {
  try {
    const res = await itemApi.author()
    if (res && res.status) ALL.value = res.data || []
  } catch (e) {
    ALL.value = []
  }
})
</script>

<style scoped>
.author-page {
  color: #000;
  min-height: 100vh;
  background-color: #b8daf8;
  box-sizing: border-box;
}
.header {
  position: relative;
  width: 100%;
  padding-top: calc(var(--status-bar-height) + 1.5625rem);
  background: linear-gradient(180deg, #64aef9, #b8daf8);
  display: flex;
  flex-direction: column;
  align-items: center;
}
.back-btn {
  position: absolute;
  left: 0.75rem;
  top: calc(var(--status-bar-height) + 0.75rem);
  width: 1.75rem;
  height: 1.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
}
.back-icon {
  box-sizing: content-box;
  width: 0.625rem;
  height: 0.625rem;
  border-left: 0.125rem solid #ffffff;
  border-bottom: 0.125rem solid #ffffff;
  transform: rotate(45deg);
  margin-left: 0.25rem;
}
.brand-logo {
  width: 9.375rem;
  height: 5.1683rem;
  margin: 1px auto 0;
  display: block;
}
.mascot {
  width: 100%;
  height: 8.5337rem;
  margin-top: 0.8125rem;
  display: block;
}
.search-bar {
  margin: 0 0.6875rem;
  height: 2.8125rem;
  border-radius: 0.8125rem;
  background: #fff;
  display: flex;
  align-items: center;
  padding: 0 0.75rem;
}
.search-icon {
  width: 0.875rem;
  height: 0.875rem;
  flex-shrink: 0;
}
.search-input {
  flex: 1;
  margin-left: 0.5rem;
  font-size: 0.8125rem;
  color: #333;
  height: 100%;
}
.search-placeholder {
  color: #a2a2a2;
}
.creator-grid {
  padding: 0.8125rem 0.6875rem 1.25rem;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}
.creator-card {
  width: calc(50% - 0.3125rem);
  height: 10rem;
  background: #fff;
  border-radius: 0.625rem;
  padding: 0.75rem 0.75rem 0.875rem;
  margin-bottom: 0.625rem;
  box-sizing: border-box;
}
.card-top {
  display: flex;
  align-items: center;
}
.avatar {
  width: 2.125rem;
  height: 2.125rem;
  border-radius: 50%;
  flex-shrink: 0;
  background: #eef4ff;
}
.name {
  display: block;
  flex: 1;
  margin-left: 0.5rem;
  font-size: 0.9375rem;
  font-weight: 700;
  color: #000;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.arrow {
  width: 1.25rem;
  height: 1.25rem;
  border-radius: 50%;
  background: #f0f5ff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.arrow-icon {
  box-sizing: content-box;
  width: 0.375rem;
  height: 0.375rem;
  border-top: 0.09375rem solid #ababab;
  border-right: 0.09375rem solid #ababab;
  transform: rotate(45deg);
  margin-left: -0.125rem;
}
.intro {
  display: block;
  margin-top: 0.5625rem;
  font-size: 0.75rem;
  color: #767676;
  line-height: 1.6;
  word-break: break-all;
  overflow: hidden;
  text-overflow: ellipsis;
  height: 6.25rem;
}
.empty {
  padding: 3.75rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.empty-icon {
  width: 6.25rem;
  height: 6.25rem;
}
.empty-text {
  margin-top: 0.625rem;
  font-size: 0.8125rem;
  color: #999;
}
</style>