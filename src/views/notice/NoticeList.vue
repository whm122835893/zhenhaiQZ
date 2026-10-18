<template>
  <div class="notice-page" :style="{ '--nt-bg': `url(${IMG.bg})` }">
    <!-- 分类横滑（topTabIndex === 0） -->
    <div class="cateBox">
      <div class="cateScroll">
        <div class="cateList">
          <div
            v-for="(t, i) in noticeCategoryList"
            :key="i"
            class="cateItem"
            :class="currentTab === i ? 'cellected' : 'uncellect'"
            @click="switchTab(i, t.cid)"
          >
            {{ t.name.slice(0, 2) }} <i v-if="currentTab === i" class="line" />
          </div>
        </div>
      </div>
    </div>

    <!-- 搜索 + 筛选 -->
    <div class="msgActBox">
      <div class="searchFilterBox">
        <div class="searchBox">
          <img class="search" :src="IMG.search" alt="" />
          <input type="text" placeholder="搜索你感兴趣的话题" @confirm="goSearch" />
        </div>
        <div class="filterBox">
          <img :src="IMG.filter" alt="" width="49" height="20" />
        </div>
      </div>
    </div>

    <!-- 公告列表 -->
    <div v-if="topTabIndex === 0" class="listBox">
      <div v-for="(item, i) in noticeList" :key="i" class="itemView" @click="go2Info(item)">
        <div class="headRow">
          <div class="userBox">
            <img class="logo" :src="IMG.logo" alt="" />
            <div class="nameTime">
              <div class="nameRow">
                <span class="name">臻海艺术先锋发布官</span>
              </div>
              <span class="time">{{ timeFormat(item.createTime) }}</span>
            </div>
          </div>
          <img v-if="isNew(item)" class="newTag" :src="IMG.newTag" alt="" />
        </div>
        <div class="nameTag">
          <div class="tagBox">
            <div class="tag">{{ item.category.name.slice(0, 2) }}</div>
          </div>
          <div class="title">{{ item.title }}</div>
        </div>
        <div v-if="item.items && item.items.length" class="imgScroll">
          <div class="imgList">
            <img v-for="(g, k) in item.items" :key="k" class="goodImg" :src="g.imageUrl" alt="" />
          </div>
        </div>
      </div>
      <div v-if="noticeFinished && noticeList.length > 0" class="list-end">— 没有更多了 —</div>
    </div>

    <!-- 新闻中心（topTabIndex === 1）：原站为空状态占位 -->
    <div v-else class="empty-state">
      <img class="empty-image" :src="IMG.searchEmpty" alt="" />
      <div class="empty-text">敬请期待</div>
    </div>

    <div class="nb-tabbar-space" />
  </div>
</template>

<script setup>
/**
 * 公告页 —— 1:1 复刻原站 /pages/notice/index
 * 样式数值全部取自原站 index-BQGl9-0w.css，未作改动
 * 交互逻辑取自原站 pages-notice-index.Cj8GX47e.js
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { basicApi } from '@/api'

import bgImg from '@/assets/notice/bg.png'
import logoImg from '@/assets/notice/logo.jpg'
import searchImg from '@/assets/notice/search.png'
import filterImg from '@/assets/notice/filter.png'
import searchEmptyImg from '@/assets/notice/search-empty.png'
import newTagImg from '@/assets/notice/new-tag.png'

const IMG = { bg: bgImg, logo: logoImg, search: searchImg, filter: filterImg, searchEmpty: searchEmptyImg, newTag: newTagImg }

const router = useRouter()

/* ---------- 原站 data ---------- */
const topTabs = [
  { name: '平台公告', value: 'platform' },
  { name: '新闻中心', value: 'news' },
]
const topTabIndex = ref(0)
const currentTab = ref(0)
const noticeCategoryList = ref([])
const scrollLeft = ref(0)
const noticeFinished = ref(false)
const noticeQuery = ref({ pageNo: 1, cid: '' })
const noticeList = ref([])

/* ---------- 原站 methods ---------- */
function switchTopTab(e) {
  if (topTabIndex.value !== e) {
    topTabIndex.value = e
    console.log('切换到顶部选项卡:', topTabs.value[e].name)
  }
}
function switchTab(e, cid) {
  if (currentTab.value === e) return
  currentTab.value = e
  calculateScroll(e)
  noticeFinished.value = false
  noticeQuery.value.pageNo = 1
  noticeQuery.value.cid = cid
  getNoticeList()
}
function calculateScroll(e) {
  scrollLeft.value = 70 * (e - 1)
  if (scrollLeft.value < 0) scrollLeft.value = 0
}
async function getNoticeCategoryList() {
  const res = await basicApi.listNoticeCategory()
  if (res.status) {
    const list = [{ cid: '', name: '全部公告' }]
    noticeCategoryList.value = [...list, ...res.data]
    noticeQuery.value.cid = noticeCategoryList.value[0].cid
    getNoticeList()
  }
}
async function getNoticeList(isRefresh, isLoadMore) {
  const res = await basicApi.listNotice(noticeQuery.value)
  if (res.status) {
    const data = res.data || []
    noticeList.value = isLoadMore ? [...noticeList.value, ...data] : data
    if (data.length === 0) noticeFinished.value = true
  }
}
function go2Info(item) {
  router.push(`/pages/notice/info?id=${item.id}`)
}
function goSearch() {
  router.push('/pages/notice/search')
}

/** 原站 $fmt.timeFormat（dayjs 格式化为 yyyy-MM-DD HH:mm:ss） */
function timeFormat(v, pattern = 'yyyy-MM-DD HH:mm:ss') {
  if (!v) return ''
  const d = new Date(parseInt(v, 10))
  if (Number.isNaN(d.getTime())) return ''
  const p = (n) => String(n).padStart(2, '0')
  return (
    d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) +
    ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds())
  )
}

/** 原站 isNew()：24 小时内发布的公告显示 newTag 角标 */
function isNew(item) {
  let t = parseInt(item.createTime, 10)
  if (!t) t = new Date(item.createTime).getTime()
  if (!t) return false
  if (t < 1e12) t *= 1000
  return Date.now() - t < 864e5
}

onMounted(() => {
  getNoticeCategoryList()
})
</script>

<style scoped>
/* ============ 页面容器（uni-page-body 背景） ============ */
.notice-page {
  min-height: 100vh;
  position: relative;
  background: var(--nt-bg) top / contain no-repeat;
  background-color: #f3f6f8;
}

/* ============ 分类横滑 ============ */
.cateBox { margin-top: 0; padding-top: 5.15625rem; }
.cateScroll { width: 100%; white-space: nowrap; overflow-x: auto; overflow-y: hidden; scrollbar-width: none; }
.cateScroll::-webkit-scrollbar { display: none; }
.cateList { display: inline-flex; align-items: center; padding: 0 0.75rem; }
.cateItem {
  position: relative;
  display: inline-block; flex-shrink: 0;
  height: 1.875rem; line-height: 1.875rem;
  padding: 0 0.875rem; margin-right: 0.5rem;
  border-radius: 0.375rem; font-size: 0.75rem; text-align: center;
}
/* 原站 .cateItem 为 static 定位，line 的绝对定位参照 cateScroll（left 由静态位置算出 = 27.0156px）
   此处用 position:relative + left:0.875rem 等价还原（14.56 + transform 8.06 = 22.62，与原站一致） */
.cateItem .line {
  width: 15px; height: 3px;
  background: #3578fb;
  position: absolute; bottom: 0; left: 0.875rem;
  border-radius: 100px;
  transform: translate(0.48438rem);
  transition-duration: 0.3s;
}
.cateItem.uncellect { color: #999; font-size: 14px; }
.cateItem.cellected { color: #3578fb; font-weight: 700; font-size: 17px; }

/* ============ 搜索 + 筛选 ============ */
.msgActBox {
  position: sticky; top: 0; z-index: 2;
  box-sizing: border-box; width: 100%; padding: 0.625rem 0;
  background-image: linear-gradient(180deg, #f3f6f8, #fff);
}
.msgActBox .searchFilterBox {
  display: flex; align-items: center; justify-content: space-between;
  width: calc(100% - 1.5rem); margin: 0.5rem auto 0;
}
.msgActBox .searchFilterBox .searchBox {
  flex: 1; min-width: 0;
  display: flex; align-items: center;
  height: 2.0625rem; padding: 0 0.875rem; box-sizing: border-box;
  background-color: #fff; border: 0.5px solid #333333; border-radius: 1.03125rem;
}
.msgActBox .searchFilterBox .searchBox .search {
  flex-shrink: 0; width: 1.125rem; height: 1.125rem; margin-right: 0.625rem;
}
.msgActBox .searchFilterBox .searchBox input {
  flex: 1; min-width: 0; height: 100%;
  font-size: 0.75rem; color: #333;
  border: none; outline: none; background: transparent;
}
.msgActBox .searchFilterBox .searchBox input::placeholder { color: #999; font-size: 0.75rem; }
.msgActBox .searchFilterBox .filterBox {
  flex-shrink: 0; margin-left: 0.75rem; font-size: 0.75rem; color: #333;
}
.msgActBox .searchFilterBox .filterBox img { width: 49px; height: 20px; display: block; }

/* ============ 公告列表 ============ */
.listBox {
  box-sizing: border-box; width: 100%;
  padding: 0.25rem 0.75rem 1.875rem;
  background-color: #fff;
}
.itemView {
  box-sizing: border-box; width: 100%;
  padding: 0.75rem 0.875rem 0.875rem;
  margin-bottom: 0.75rem;
  border-bottom: 0.03125rem solid #f3f6f8;
  border-radius: 0.75rem;
  cursor: pointer;
}
.headRow { display: flex; align-items: center; justify-content: space-between; }
.newTag { width: 35px; height: 15px; }
.userBox { display: flex; align-items: center; flex: 1; min-width: 0; }
.userBox .logo {
  flex-shrink: 0; width: 2.625rem; height: 2.625rem;
  margin-right: 0.5rem; border-radius: 50%;
  background-color: #f4f5f9; border: 1px solid #ccc;
  object-fit: cover; display: block;
}
.userBox .nameTime { display: flex; flex-direction: column; justify-content: space-between; min-width: 0; }
.userBox .nameTime .nameRow { display: flex; align-items: center; }
.userBox .nameTime .nameRow .name {
  font-size: 0.875rem; font-weight: 700; color: #333;
  overflow: hidden; white-space: nowrap; text-overflow: ellipsis;
}
.userBox .nameTime .time { margin-top: 0.1875rem; font-size: 0.75rem; font-weight: 500; color: #999; }

.nameTag { position: relative; margin-top: 0.5rem; }
.nameTag .tagBox { position: absolute; left: 0; top: 0.125rem; }
.nameTag .tagBox .tag {
  width: 2.25rem; height: 1.125rem; line-height: 1.125rem;
  text-align: center; border-radius: 0.25rem;
  background-color: #ff7770; color: #fff;
  font-size: 0.625rem; font-weight: 500;
  overflow: hidden; white-space: nowrap;
}
.nameTag .title {
  text-indent: 3.0625rem;
  font-size: 0.8125rem; font-weight: 600; color: #333; line-height: 1.375rem;
  word-break: break-all;
  display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3;
  overflow: hidden;
}

.imgScroll {
  width: 100%; margin-top: 0.5rem; padding: 0.625rem 0;
  border-radius: 0.5rem; white-space: nowrap; box-sizing: border-box;
  overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
}
.imgScroll::-webkit-scrollbar { display: none; }
.imgScroll .imgList { display: inline-flex; align-items: center; }
.imgScroll .goodImg {
  width: 5.625rem; height: 5.625rem; margin-right: 0.5rem;
  border-radius: 0.5rem; background-color: #f4f5f9;
  object-fit: cover; display: block; flex-shrink: 0;
}

.list-end { padding: 0.625rem 0 0.3125rem; text-align: center; font-size: 0.75rem; color: #999; }

/* ============ 新闻中心空状态 ============ */
.empty-state {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  margin-top: 7.5rem; padding-bottom: 8.125rem;
}
.empty-state .empty-image { width: 7.3125rem; height: 7.125rem; }
.empty-state .empty-text { margin-top: 0.625rem; font-size: 0.8125rem; color: #999; }

/* ============ TabBar 占位 ============ */
.nb-tabbar-space { width: 100%; height: calc(6rem + env(safe-area-inset-bottom)); }
</style>