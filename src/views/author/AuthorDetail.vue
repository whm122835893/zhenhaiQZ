<template>
  <view class="author-detail-wrap">
    <UniPageHead title="臻海艺术" />
    <view class="author-detail">
    <view class="creator-card">
      <img class="sec-title" :src="secTitle" alt="" />
      <view class="info">
        <img class="avatar" :src="avatarSrc" alt="" />
        <text v-if="detail.name" class="name">{{ detail.name }}</text>
        <text v-if="detail.intro" class="intro">{{ detail.intro }}</text>
      </view>
    </view>
    </view>
  </view>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { authorApi } from '@/api'
import UniPageHead from '@/components/UniPageHead.vue'
import secTitle from '@/assets/author/sec-title.png'
import defaultLogo from '@/assets/static/images/logo.png'

const route = useRoute()

/** 源站 pages-author-detail.Bruc8JVX.js 的 mock 兜底（/api/author/detail 线上恒 10203 时命中） */
const MOCK = { name: '臻海艺术', avatar: '', intro: '' }

const detail = ref({ ...MOCK })

const avatarSrc = computed(() => {
  const a = detail.value.avatar
  if (!a) return defaultLogo
  if (/^https?:\/\//.test(a) || a.indexOf('/static') === 0) return a
  return 'https://api.hainanchaowan.cn' + (a.charAt(0) === '/' ? a : '/' + a)
})

onMounted(async () => {
  const id = route.query.id || ''
  if (!id) return
  try {
    const res = await authorApi.detail({ id })
    const raw =
      res && res.data && (res.data.creator || res.data.detail)
        ? res.data.creator || res.data.detail
        : res && res.data && typeof res.data === 'object'
          ? res.data
          : null
    if (raw && (raw.name || raw.intro || Array.isArray(raw.works))) {
      if (raw.name) detail.value.name = raw.name
      if (raw.avatar) detail.value.avatar = raw.avatar
      if (raw.intro) detail.value.intro = raw.intro
    }
  } catch (e) {
    /* 源站同样静默 catch，保留 mock */
  }
})
</script>

<style scoped>
.author-detail {
  color: #000;
  min-height: 100vh;
  background-color: #f6f7fb;
  box-sizing: border-box;
  padding-bottom: 0.9375rem;
}
.creator-card {
  margin: 0.8125rem 0.75rem 0;
  background: #fff;
  border-radius: 0.625rem;
  padding: 1.0625rem 0.8125rem 1.125rem;
  box-sizing: border-box;
}
.sec-title {
  width: 7.5rem;
  height: 26px;
  display: block;
}
.info {
  margin-top: 1.1875rem;
  overflow: hidden;
}
.avatar {
  float: left;
  width: 5.875rem;
  height: 6.625rem;
  border-radius: 0.5rem;
  margin-right: 0.75rem;
  background: #eef4ff;
  flex-shrink: 0;
}
.name {
  margin-top: 1.625rem;
  font-size: 1.5625rem;
  font-weight: 700;
  color: #000;
  line-height: 1.15;
}
.intro {
  display: block;
  margin-top: 0.875rem;
  font-size: 0.8125rem;
  color: #333;
  line-height: 1.5rem;
  word-break: break-all;
}
</style>