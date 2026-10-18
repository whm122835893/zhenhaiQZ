<template>
  <div class="notice-detail-container">
    <!-- 顶部导航 -->
    <div class="back-title-wrap">
      <div class="back-title">
        <div class="back-btn" @click="goBack">
          <UvIcon name="arrow-left" :size="20" color="#000000" />
        </div>
        <div class="nav-title">详情</div>
        <div class="right-icon" @click="onShare">
          <UvIcon name="share" :size="20" color="#000000" />
        </div>
      </div>
    </div>

    <div class="content">
      <!-- 标题区 -->
      <div class="nav-box">
        <div class="name">{{ notice.title }}</div>
        <div class="center-between">
          <div class="center-row">
            <span class="nav-tag">{{ notice.category && notice.category.name }}</span>
            <span class="time">{{ timeFormat(notice.createTime, 'yyyy/MM/DD HH:mm:ss') }}</span>
          </div>
          <span v-if="readCount" class="read-text">阅读 {{ readCount }}</span>
        </div>
      </div>

      <!-- 相关藏品 -->
      <div v-if="notice.items && notice.items.length > 0" class="good-list">
        <span class="good-title">相关藏品</span>
        <div class="scroll-x">
          <div class="scroll-x-inner">
            <div
              v-for="(g, i) in notice.items"
              :key="i"
              class="good-item"
              :style="{ marginLeft: i === 0 ? '1rem' : '0' }"
              @click="goSecondary(g)"
            >
              <img class="good-img" :src="g.imageUrl" alt="" />
              <span class="good-name">{{ g.name || g.title }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- 正文 -->
      <div class="vcont">
        <!-- 原站内联样式：分类徽章（非类名选择器，故按原样内联还原） -->
        <div style="display: flex; justify-content: center; margin-bottom: 0.9375rem">
          <div
            style="background-color: rgb(230, 215, 249); box-shadow: rgb(200, 215, 255) 50px 0px 43px inset;
                   padding: 0.375rem; border-bottom: 5px solid rgb(226, 234, 255);
                   font-size: 18px; font-weight: bold; color: rgb(255, 255, 255); width: fit-content"
          >
            {{ notice.category && notice.category.name }}
          </div>
        </div>
        <!-- 原站用 uni-rich-text 渲染 HTML 字符串，此处用 v-html 等价 -->
        <div class="rich-body" v-html="notice.content" />
      </div>

      <div class="tips">
        <img :src="IMG.tips" alt="" />
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 公告详情页 —— 1:1 复刻原站 /pages/notice/info
 * 样式数值全部取自原站 info-CWmdEuW1.css，未作改动
 * 交互逻辑取自原站 pages-notice-info.js
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast } from 'vant'
import UvIcon from '@/components/UvIcon.vue'
import { basicApi } from '@/api'

import tipsImg from '@/assets/notice-info/tips.jpg'

const IMG = { tips: tipsImg }

const router = useRouter()
const route = useRoute()

/* ---------- 原站 data ---------- */
const notice = ref({ items: [], category: {} })

/* ---------- 原站 computed.readCount ---------- */
const readCount = computed(() => {
  const t = notice.value || {}
  return t.viewCount || t.readCount || t.readNum || t.views || 0
})

/** 原站 $fmt.timeFormat（dayjs），支持 yyyy/MM/DD 与 yyyy-MM-DD */
function timeFormat(v, pattern) {
  if (!v) return ''
  const d = new Date(parseInt(v, 10))
  if (Number.isNaN(d.getTime())) return ''
  const p = (n) => String(n).padStart(2, '0')
  const sep = pattern && pattern.indexOf('/') > -1 ? '/' : '-'
  return (
    d.getFullYear() + sep + p(d.getMonth() + 1) + sep + p(d.getDate()) +
    ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds())
  )
}

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/notice/index')
}
function onShare() { showToast('分享功能待接入') }

async function getNotice(id) {
  try {
    const res = await basicApi.getNotice(id)
    if (res.status) {
      notice.value = res.data
      // 原站对正文所有 img 强制 width:100%
      notice.value.content = (notice.value.content || '').replace(/<img/g, "<img width='100%'")
    } else {
      router.replace('/pages/index/index')
    }
  } catch (e) {
    router.replace('/pages/index/index')
  }
}

function goSecondary(g) {
  if (g.isSecondary) router.push(`/pages/market/info?iid=${g.iid}`)
  else showToast('暂未开放')
}

onMounted(() => {
  const id = route.query.id
  if (id) getNotice(id)
})
</script>

<style scoped>
/* 原站 @font-face{font-family:MyFont2;src:url(/assets/SourceHanSansCN-Regular-DDvDncSQ.otf)}
   只存在于公告详情页的 CSS 分包（assets/css-info-CWmdEuW1.css），不进其他页面，
   故此处的 @font-face 放在组件 scoped 内（scoped 不影响 @font-face 全局注册），
   保证只有公告详情页会注册 MyFont2，P0 五页的 MyFont2 仍保持未注册状态。
   原文件 8.4MB，本地为子集化版本（常用字 + ASCII + 标点），family 名与原站一致。 */
@font-face {
  font-family: MyFont2;
  src: url('@/assets/notice-info/MyFont2.woff2') format('woff2');
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

.notice-detail-container {
  min-height: 100vh;
  background: #f6f7fb;
  font-family: MyFont2;
  font-size: 16px;
}

/* ============ 顶部导航 ============ */
.back-title-wrap { background: #fff; padding-top: var(--status-bar-height, 0px); }
.back-title {
  position: relative; display: flex; align-items: center; justify-content: center;
  height: 3.125rem; background: #fff; box-sizing: border-box;
}
.back-title .back-btn {
  position: absolute; left: 1.125rem; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; justify-content: center;
  width: 1.25rem; height: 1.25rem;
}
.back-title .nav-title { font-size: 1.0625rem; font-weight: 700; color: #000; }
.back-title .right-icon {
  position: absolute; right: 1.125rem; top: 50%; transform: translateY(-50%);
  display: flex; align-items: center; justify-content: center;
  width: 1.375rem; height: 1.375rem;
}

/* ============ 标题区 ============ */
.content { width: 100%; box-sizing: border-box; }
.nav-box { background: #fff; padding: 1.25rem 1.125rem; box-sizing: border-box; }
.nav-box .name {
  font-size: 1.125rem; font-weight: 700; color: #000;
  margin-bottom: 0.625rem; word-break: break-all;
}
.nav-box .center-between { display: flex; align-items: center; justify-content: space-between; }
.nav-box .center-between .center-row { display: flex; align-items: center; }
.nav-box .center-between .center-row .nav-tag {
  color: #3578fc; font-size: 0.875rem; font-weight: 500; margin-right: 0.5625rem;
}
.nav-box .center-between .center-row .time { color: #999; font-size: 0.875rem; }
.nav-box .center-between .read-text { color: #999; font-size: 0.75rem; }

/* ============ 相关藏品 ============ */
.good-list { margin-top: 0.875rem; }
.good-list .good-title {
  display: block; margin-left: 1rem; font-size: 0.875rem; font-weight: 500; color: #000;
}
.good-list .scroll-x {
  margin-top: 0.5rem; width: 100%; height: 7.25rem; white-space: nowrap;
  overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
}
.good-list .scroll-x::-webkit-scrollbar { display: none; }
.good-list .scroll-x .scroll-x-inner { display: inline-flex; }
.good-list .scroll-x .good-item {
  display: inline-flex; flex-direction: column; align-items: center; justify-content: flex-start;
  width: 5.625rem; height: 7.25rem; margin-right: 0.5rem; box-sizing: border-box;
  cursor: pointer;
}
.good-list .scroll-x .good-item .good-img {
  width: 5.625rem; height: 5.625rem; border-radius: 0.5rem; background: #f4f5f9;
  object-fit: cover; display: block;
}
.good-list .scroll-x .good-item .good-name {
  margin-top: 0.25rem; width: 5.625rem; font-size: 0.875rem; color: #333;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center;
}

/* ============ 正文 ============ */
.vcont {
  margin: 0.9375rem 1rem 0; padding-bottom: 1.875rem; box-sizing: border-box;
  box-shadow: #e2eaff 0 0 45px inset;
  padding: 20px 16px;
}
.rich-body { font-family: MyFont2; }
.rich-body :deep(p) { margin: 0; }
/* 原站 uni-rich-text 内 img 为 display:inline（基线留白产生 6px 差），此处还原 */
.rich-body :deep(img) { width: 100%; display: inline; }

/* ============ 提示图 ============ */
.tips { margin: 1rem; }
.tips img { width: 100%; display: block; }
</style>