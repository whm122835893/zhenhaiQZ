<template>
  <div class="notice-page">
    <UniPageHead title="相关公告" />
    <div class="notice-container">
    <div class="notice-list">
      <!-- 空状态 -->
      <div v-if="noticeList.length === 0" class="empty-data">
        <div class="empty-text">暂无数据</div>
      </div>

      <template v-else>
        <div
          v-for="(item, i) in noticeList"
          :key="i"
          class="notice-item"
          @click="goToDetail(item)"
        >
          <div class="notice-content">
            <div class="notice-title">{{ item.title }}</div>
            <div class="notice-time">{{ timeFormat(item.createTime) }}</div>
          </div>
          <div class="notice-image">
            <img :src="item.category.coverUrl" alt="" />
          </div>
        </div>
      </template>
    </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 相关公告页 —— 1:1 复刻原站 /pages/notice/search
 * 样式数值全部取自原站 search-D5G5KNUt.css，未作改动
 * 交互逻辑取自原站 pages-notice-search.CQeM9DRP.js
 *
 * 注意：原站 CSS 里定义了 .search-container / .search-box / .search-btn /
 * .clear-icon 等搜索框样式，但 render 函数中并未渲染（此页无搜索 UI），
 * 故此处不实现，保持与原站一致。
 */
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showLoading, hideLoading } from '@/api/request'
import { basicApi } from '@/api'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

/* ---------- 原站 data ---------- */
const noticeFinished = ref(false)
const noticeQuery = ref({ pageNo: 1, iid: '' })
const noticeList = ref([])

/** 原站 $fmt.timeFormat（dayjs），格式 yyyy-MM-dd HH:mm:ss */
function timeFormat(v) {
  if (!v) return ''
  const d = new Date(parseInt(v, 10))
  if (Number.isNaN(d.getTime())) return ''
  const p = (n) => String(n).padStart(2, '0')
  return (
    d.getFullYear() +
    '-' +
    p(d.getMonth() + 1) +
    '-' +
    p(d.getDate()) +
    ' ' +
    p(d.getHours()) +
    ':' +
    p(d.getMinutes()) +
    ':' +
    p(d.getSeconds())
  )
}

/* ---------- 原站 methods ---------- */
async function getNoticeList(isRefresh, isLoadMore) {
  if (!isRefresh) showLoading()
  try {
    const res = await basicApi.listNotice(noticeQuery.value)
    hideLoading()
    if (res.status) {
      noticeList.value = isLoadMore ? [...noticeList.value, ...res.data] : res.data
      if (res.data.length === 0) noticeFinished.value = true
    }
  } catch (e) {
    hideLoading()
  }
}

function goToDetail(item) {
  router.push('/pages/notice/info?id=' + item.id)
}

/* ---------- 原站生命周期 ---------- */
onMounted(() => {
  const iid = route.query.iid
  // 原站：无 iid 则 reLaunch 回首页
  if (iid) {
    noticeQuery.value.iid = iid
    getNoticeList()
  } else {
    router.replace('/pages/index/index')
  }
})

/* 原站 onReachBottom：触底加载更多 */
function onReachBottom() {
  if (noticeFinished.value) return
  noticeQuery.value.pageNo++
  getNoticeList(false, true)
}

defineExpose({ onReachBottom })
</script>

<style scoped>
/* ============ 页面容器 ============ */
/* 原站此页 .notice-container / .notice-list / body 全链 background 均为 transparent，
   未设背景色，故此处也不设，继承全局底色
   原站 uni-page-body 基线为 font-size:16px / color:#000 / font-family:"Times New Roman"
   （uni-app 框架默认，中文回落到 PingFang），此处显式还原 */
.notice-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman', -apple-system, BlinkMacSystemFont, 'PingFang SC',
    'Helvetica Neue', Helvetica, 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
}
.notice-container { flex: 1; font-size: 16px; color: #000; }

/* ============ 公告列表 ============ */
.notice-list { padding: 0.625rem 0.625rem 3.125rem; }

.notice-item {
  display: flex;
  justify-content: space-between;
  background-color: #f8f8f8;
  padding: 0.9375rem;
  border-radius: 0.375rem;
  margin-bottom: 0.625rem;
  box-shadow: 0 0.0625rem 0.3125rem rgba(0, 0, 0, 0.05);
  cursor: pointer;
}
.notice-item .notice-content { flex: 1; margin-right: 0.625rem; }
.notice-item .notice-tag {
  display: inline-block;
  font-size: 0.875rem;
  font-weight: 700;
  color: #333;
}
.notice-item .notice-title {
  font-size: 0.875rem;
  color: #333;
  margin-top: 0.3125rem;
  line-height: 1.5;
}
.notice-item .notice-time { font-size: 0.75rem; color: #999; margin-top: 0.625rem; }
.notice-item .notice-image {
  width: 4.6875rem;
  height: 4.6875rem;
  border-radius: 0.25rem;
  overflow: hidden;
}
.notice-item .notice-image img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
}

/* ============ 加载 / 空状态 ============ */
.loading-more,
.no-more,
.empty-box { text-align: center; padding: 0.9375rem 0; }
.loading-text,
.no-more-text,
.empty-text { font-size: 0.75rem; color: #999; }
.empty-box { padding: 3.125rem 0; }
.empty-data {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3.125rem 0;
}
.empty-image { width: 6.25rem; height: 6.25rem; margin-bottom: 0.625rem; }
.empty-text { font-size: 0.875rem; color: #999; }
</style>