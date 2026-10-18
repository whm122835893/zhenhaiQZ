<template>
  <div class="containerStyle communityBg" :style="rootStyle">
    <!-- ============ 顶部导航 ============ -->
    <div class="topNavBox">
      <div class="navList center">
        <div
          v-for="t in navList"
          :key="t.value"
          class="navItem"
          :class="navId === t.value ? 'sel' : 'unsel'"
          @click="handleNav(t.value)"
        >
          {{ t.name }}
        </div>
      </div>
      <img class="right" :src="IMG.right" alt="" />
    </div>

    <!-- ============ 内容区 ============ -->
    <div class="content">
      <!-- 聊天室 -->
      <div v-if="navId === 0" class="chatRoom">
        <div class="chatScroll">
          <div class="group-search">
            <img class="search-icon" :src="IMG.search" alt="" />
            <input
              v-model="groupKeyword"
              class="search-input"
              type="text"
              placeholder="搜索群组名称 / 简介"
              @confirm="getGroupList"
            />
            <div v-if="groupKeyword" class="search-clear" @click="clearGroupKeyword">×</div>
          </div>

          <!-- 骨架屏 -->
          <div v-if="groupLoading" class="groupList">
            <div v-for="n in 3" :key="'sk' + n" class="groupCard">
              <div class="sk sk-avatar" />
              <div class="skRight">
                <div class="sk sk-line1" />
                <div class="sk sk-line2" />
              </div>
            </div>
          </div>

          <!-- 群组列表 -->
          <div v-else-if="filteredGroupList.length" class="groupList">
            <div
              v-for="(g, i) in filteredGroupList"
              :key="g.id || i"
              class="groupCard"
              @click="handleGroupClick(g)"
            >
              <div class="gMain">
                <div class="gAvatar">
                  <img class="gAvatar-img" :src="g.avatar || IMG.groupLogo" alt="" />
                </div>
                <div class="gInfo">
                  <div class="gName text-cut">{{ g.name }}</div>
                  <div class="gDesc text-cut">{{ g.description || '暂无简介' }}</div>
                  <div class="gMeta">
                    <span class="gCount">{{ g.userTotal || 0 }}人</span>
                    <span v-if="g.isJoin" class="gTag tag-joined">已加入</span>
                  </div>
                </div>
              </div>
              <div
                class="gAction doubleCenter"
                :class="g.joining ? 'act-loading' : g.isJoin ? 'act-enter' : 'act-join'"
              >
                <span>{{ g.joining ? '加入中' : g.isJoin ? '进入' : '加入' }}</span>
              </div>
            </div>
            <div class="load-txt">{{ groupLoadTxt }}</div>
          </div>

          <!-- 空状态 -->
          <div v-else class="columnDoubleCenter noData">
            <img class="noData-icon" :src="IMG.noData" alt="" />
            <div class="font-999-26">{{ groupKeyword ? '没有找到相关群组' : '暂无群组' }}</div>
          </div>
        </div>
      </div>

      <!-- 版权库 -->
      <div v-else-if="navId === 1" class="copyrightLibrary columnCenter">
        <div class="headActBox doubleCenter">
          <div class="crList">
            <div
              v-for="t in crList"
              :key="t.value"
              class="crItem"
              :class="crId === t.value ? 'tabed' : 'font-999-28'"
              @click="handleCrTab(t)"
            >
              {{ t.name }}
            </div>
          </div>
        </div>

        <div class="crLibraryContent">
          <!-- 版权库：瀑布流 -->
          <div class="scroll-view">
            <div v-if="leftList.length || rightList.length" class="list">
              <div class="waterfall-box h-flex-x h-flex-2">
                <div class="waterfall-col">
                  <div v-for="(it, i) in leftList" :key="'l' + i" class="waterfall-item">
                    <img class="cover" :src="it.image" alt="" />
                  </div>
                </div>
                <div class="waterfall-col">
                  <div v-for="(it, i) in rightList" :key="'r' + i" class="waterfall-item">
                    <img class="cover" :src="it.image" alt="" />
                  </div>
                </div>
              </div>
              <div class="load-txt">{{ ajax.loadTxt }}</div>
            </div>
            <div v-else class="columnDoubleCenter noData">
              <img class="noData-icon" :src="IMG.noData" alt="" />
              <div class="font-999-26"> 暂无数据 </div>
            </div>
          </div>
        </div>

        <!-- 版权存证 / 版权人 底部按钮（原站 crId>0 才显示） -->
        <div v-if="crId > 0" class="accessCard columnCenter">
          <div class="enterBtn font-fff-28 doubleCenter" @click="goJoin">
            {{ crId === 1 ? '去存证' : '新增权利人' }}
          </div>
        </div>
      </div>

      <!-- 行业资讯 -->
      <div v-else class="intrduceBlock">
        <div class="headAct bg-white">
          <div class="bg-white white-block">
            <div class="action">
              <div class="bg-white flex align-center big-tab">
                <span :class="findTab === 0 ? 'item-text' : 'item-text-a'" @click="findTabClick(0)">热门话题</span>
                <span :class="findTab === 1 ? 'item-text' : 'item-text-a'" @click="findTabClick(1)">我的关注</span>
              </div>
            </div>
            <span class="text-gray text-sm" @click="navToTopicSquare">全部 <span class="arrow-right">&gt;</span></span>
          </div>
        </div>

        <div class="scrollView">
          <div class="bg-white margin-sm radius-lg" style="margin-bottom: 0px">
            <div class="cu-list grid col-2">
              <div v-for="(t, i) in topicList" :key="i" class="cu-item">
                <img class="topic-img" :src="t.img" alt="" />
                <div class="text-cut">{{ t.name }}</div>
              </div>
            </div>
          </div>
          <div class="line" />
          <div class="search-block">
            <img :src="IMG.search" alt="" />
            <input v-model="searchTitle" type="text" placeholder="搜索下方话题文章.." @confirm="searchList" />
          </div>
          <div class="bg-white margin-sm radius-lg">
            <div class="flex align-center padding-lr-sm padding-tb">
              <span class="margin-right text-bold text-xl text-black">精选动态</span>
              <span class="margin-right text-gray">最新发布</span>
            </div>
            <div class="columnDoubleCenter noData">
              <img class="noData-icon" :src="IMG.noData" alt="" />
              <div class="font-999-26"> 暂无数据 </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="nb-tabbar-space" />
  </div>
</template>

<script setup>
/**
 * 版权页 —— 1:1 复刻原站 /pages/copyright/index
 * 样式数值全部取自原站 index-W9JHggci.css，未作改动
 * 交互逻辑取自原站 pages-copyright-index.CFIGvt3q.js
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { imApi, itemApi } from '@/api'

import bgImg from '@/assets/copyright/bg.png'
import rightImg from '@/assets/copyright/right.png'
import groupLogoImg from '@/assets/copyright/group-logo.png'
import searchImg from '@/assets/copyright/search.png'
import tabUnderlineImg from '@/assets/copyright/tab-underline.png'
import noDataImg from '@/assets/my/newNodata.png'

const IMG = {
  bg: bgImg,
  right: rightImg,
  groupLogo: groupLogoImg,
  search: searchImg,
  tabUnderline: tabUnderlineImg,
  noData: noDataImg,
}

const rootStyle = {
  '--cp-bg': `url(${bgImg})`,
  '--cp-underline': `url(${tabUnderlineImg})`,
}

const router = useRouter()

/* ---------- 原站 data ---------- */
const navList = [
  { name: '聊天室', value: 0 },
  { name: '版权库', value: 1 },
  { name: '行业资讯', value: 2 },
]
const crList = [
  { name: '版权库', api: '', value: 0 },
  { name: '版权存证', api: '', value: 1 },
  { name: '版权人', api: '', value: 2 },
]

const navId = ref(0)
const crId = ref(0)
const findTab = ref(0)
const searchTitle = ref('')

const copyrightList = ref([])
const leftList = ref([])
const rightList = ref([])
const topicList = ref([])

const groupList = ref([])
const groupKeyword = ref('')
const groupLoading = ref(false)
const groupLoadTxt = ref('')

const ajax = reactive({ loadTxt: '', curPage: 1, rows: 10, totalPages: 0 })

/* ---------- 原站 computed.filteredGroupList ---------- */
const filteredGroupList = computed(() => {
  const k = (groupKeyword.value || '').trim().toLowerCase()
  if (!k) return groupList.value
  return groupList.value.filter((g) => {
    const n = (g.name || '').toLowerCase()
    const d = (g.description || '').toLowerCase()
    return n.indexOf(k) > -1 || d.indexOf(k) > -1
  })
})

/* ---------- 原站 methods ---------- */
function handleNav(t) {
  navId.value = t
  if (t === 1) handleCrTab(crList[0])
  if (t === 0 && groupList.value.length === 0) getGroupList()
}
function handleCrTab(t) {
  crId.value = t.value
  ajax.totalPages = 0
  copyrightList.value = []
  leftList.value = []
  rightList.value = []
  ajax.curPage = 1
  ajax.loadTxt = ''
  getList()
}
function findTabClick(t) { findTab.value = t }
function navToTopicSquare() {}
function searchList() {}

function getList() {
  if (crId.value !== 0) {
    ajax.totalPages = 0
    ajax.loadTxt = ''
  } else {
    getCopyrightList()
  }
}

async function getCopyrightList() {
  try {
    const res = await itemApi.copyright({ pageNo: ajax.curPage })
    if (!res || !res.status) {
      ajax.totalPages = 0
      ajax.loadTxt = ''
      return
    }
    const d = res.data || []
    let rows, last
    if (Array.isArray(d)) {
      rows = d
      last = d.length >= ajax.rows ? ajax.curPage + 1 : ajax.curPage
    } else {
      rows = d.data || d.list || d.rows || []
      last = d.last_page || d.totalPage || 1
    }
    const mapped = rows.map((t) => ({ ...t, image: t.imageUrl || '' }))
    ajax.totalPages = last
    copyrightList.value = ajax.curPage === 1 ? mapped : copyrightList.value.concat(mapped)
    splitWaterfall(copyrightList.value)
    ajax.loadTxt = copyrightList.value.length && ajax.curPage >= last ? '没有更多了' : ''
  } catch (e) {
    ajax.totalPages = 0
    ajax.loadTxt = ''
  }
}

function splitWaterfall(list) {
  const l = []
  const r = []
  list.forEach((t, i) => ((i % 2 === 0 ? l : r).push(t)))
  leftList.value = l
  rightList.value = r
}

async function getGroupList() {
  groupLoading.value = true
  try {
    const res = await imApi.groupList()
    if (res.status) {
      groupList.value = (res.data || []).map((t) => ({ ...t, joining: false }))
      groupLoadTxt.value = groupList.value.length ? '没有更多了' : ''
    }
  } catch (e) {
    showToast('群组加载失败')
  } finally {
    groupLoading.value = false
  }
}

function clearGroupKeyword() { groupKeyword.value = '' }

function handleGroupClick(g) {
  if (g.joining) return
  if (g.isJoin) goChat(g)
  else joinGroup(g)
}

async function joinGroup(g) {
  g.joining = true
  try {
    const res = await imApi.groupJoin(g.id)
    if (res.status) {
      g.isJoin = true
      goChat(g)
    }
  } catch (e) {
    showToast('加入失败')
  } finally {
    g.joining = false
  }
}

function goChat(g) {
  router.push(`/pages/chat/web?groupId=${g.id}&name=${encodeURIComponent(g.name || '')}`)
}
function goJoin() { showToast('暂未开放') }

onMounted(() => {
  if (navId.value === 0) getGroupList()
})
</script>

<style scoped>
/* ============ 基础容器（.containerStyle / .communityBg） ============ */
.containerStyle {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
  padding-bottom: var(--window-bottom, 0px);
}
.communityBg {
  background: var(--cp-bg) top / contain no-repeat;
  background-color: #f6f7fb;
}

/* ============ 原站工具类 ============ */
.center { display: flex; align-items: center; }
.columnCenter { display: flex; flex-direction: column; align-items: center; }
.columnDoubleCenter { display: flex; flex-direction: column; align-items: center; justify-content: center; }
.centerBetween { width: 100%; display: flex; flex-direction: row; align-items: center; justify-content: space-between; }
.centerEnd { display: flex; align-items: center; justify-content: flex-end; }
.doubleCenter { display: flex; flex-direction: row; align-items: center; justify-content: center; }
.h-flex-x { display: flex; flex-direction: row; flex-wrap: nowrap; justify-content: flex-start; align-items: flex-start; align-content: flex-start; }
.h-flex-2 > div { width: 50%; }
.flex { display: flex; }
.flex1 { flex: 1; }
.align-center { align-items: center; }
.margin-right { margin-right: 0.3125rem; }
.margin-sm { margin: 0.625rem; }
.padding-lr-sm { padding-left: 1.25rem; padding-right: 1.25rem; }
.padding-tb { padding-top: 0.625rem; padding-bottom: 0.625rem; }
.bg-white { background-color: #fff; }
.radius-lg { border-radius: 0.625rem; }
.font-999-28 { color: #999; font-size: 0.875rem; }
.font-fff-28 { color: #fff; font-size: 0.875rem; }
.font-999-26 { color: #999; font-size: 0.8125rem; }
.font-999-24 { color: #999; font-size: 0.75rem; }
.text-sm { font-size: 0.8125rem; }
.text-xl { font-size: 1.25rem; }
.text-bold { font-weight: 700; }
.text-gray { color: #999; }
.text-black { color: #000; }
.text-cut { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ============ 顶部导航 ============ */
.topNavBox { margin-top: 0.625rem; position: relative; width: 23.4375rem; height: 12.4375rem; }
.topNavBox .right { position: absolute; top: 1.5625rem; right: 1rem; width: 5.3125rem; height: 8.125rem; }
.topNavBox .navList { position: absolute; top: 2.5625rem; margin-left: 0.625rem; }
.topNavBox .navList .navItem { margin: 0 0.9375rem; color: #2e2d33; }
.topNavBox .navList .unsel { font-size: 0.875rem; font-weight: 500; }
.topNavBox .navList .sel { position: relative; font-size: 1.25rem; font-weight: 700; }
.topNavBox .navList .sel::after {
  content: '';
  width: 1.125rem;
  height: 1.125rem;
  background: var(--cp-underline) no-repeat;
  background-size: 100% 100%;
  position: absolute;
  bottom: -0.75rem;
  left: 50%;
  transform: translate(-50%);
}

/* ============ 内容区 ============ */
.content {
  position: absolute; top: 5.9375rem; height: calc(100vh - 5.9375rem); width: 23.4375rem;
  background: #fff; border-radius: 1.5rem 1.5rem 0 0;
  box-shadow: 0 -0.125rem 0.25rem rgba(0, 0, 0, 0.02);
  overflow-y: scroll;
}
.content .headActBox { width: 100%; }
.content .crList { margin: 0.875rem auto 0.8125rem; display: flex; justify-content: center; }
.content .crList .crItem { margin: 0 0.9375rem; }
.content .crList .tabed { position: relative; font-size: 0.875rem; color: #1f1f1f; }
.content .crList .tabed::after {
  content: ''; width: 0.875rem; height: 0.0625rem; background: #1f1f1f; border-radius: 2.5625rem;
  position: absolute; bottom: -0.4375rem; left: 50%; transform: translate(-50%);
}
.crLibraryContent { height: calc(100vh - 13.75rem); }
.scroll-view { height: calc(100vh - 13.75rem); overflow-y: auto; }

/* ============ 聊天室 ============ */
.chatRoom { width: 100%; }
.chatScroll { width: 23.4375rem; height: calc(100vh - 12.5rem); overflow-y: auto; }
.group-search {
  display: flex; flex-direction: row; align-items: center;
  width: 21.4375rem; height: 2.25rem; margin: 0.75rem auto 0;
  padding: 0 0.75rem; box-sizing: border-box;
  background: #f6f7fb; border-radius: 0.625rem;
}
.group-search .search-icon { width: 0.8125rem; height: 0.8125rem; margin-right: 0.4375rem; flex-shrink: 0; }
.group-search .search-input {
  flex: 1; height: 2.25rem; font-size: 0.8125rem; color: #1f1f1f;
  border: none; outline: none; background: transparent; min-width: 0;
}
.group-search input::placeholder { color: #b6bac4; font-size: 0.8125rem; }
.group-search .search-clear { width: 1.25rem; text-align: center; font-size: 1.0625rem; color: #b6bac4; }

.groupList { width: 100%; padding-bottom: 1.25rem; }
.groupCard {
  display: flex; flex-direction: row; align-items: center;
  width: 21.9375rem; margin: 0.625rem auto 0; padding-bottom: 0.625rem;
  box-sizing: border-box; box-shadow: 0 0.125rem 0.5rem rgba(31, 45, 80, 0.04);
  border-bottom: 1px solid #eee;
}
.groupCard .gMain { flex: 1; display: flex; flex-direction: row; align-items: center; overflow: hidden; }
.gAvatar {
  width: 3rem; height: 3rem; border-radius: 0.75rem; overflow: hidden;
  flex-shrink: 0; margin-right: 0.625rem;
  display: flex; align-items: center; justify-content: center;
}
.gAvatar .gAvatar-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.gInfo { flex: 1; overflow: hidden; }
.gInfo .gName { font-size: 0.875rem; font-weight: 600; color: #1f1f1f; }
.gInfo .gDesc { font-size: 0.71875rem; color: #999; margin-bottom: 0.3125rem; }
.gInfo .gMeta { display: flex; flex-direction: row; align-items: center; }
.gInfo .gCount { font-size: 0.6875rem; color: #8a8f99; background: #f3f4f8; border-radius: 0.25rem; padding: 0.125rem 0.375rem; }
.gInfo .gTag { margin-left: 0.375rem; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.6875rem; }
.gInfo .tag-joined { color: #3578fc; background: #eef4ff; }
.gAction { flex-shrink: 0; margin-left: 0.625rem; width: 4rem; height: 1.75rem; border-radius: 2.0625rem; font-size: 0.75rem; }
.gAction.act-join { background: #3578fc; color: #fff; }
.gAction.act-enter { background: #f3f4f8; color: #3578fc; }
.gAction.act-loading { background: #c9d9ff; color: #fff; }

/* 骨架屏 */
.skRight { flex: 1; overflow: hidden; }
.sk {
  background: linear-gradient(90deg, #f2f4f8 25%, #e7ebf2 37%, #f2f4f8 63%);
  background-size: 400% 100%;
  animation: sk-loading 1.4s ease infinite;
  border-radius: 0.375rem;
}
.sk-avatar { width: 3rem; height: 3rem; border-radius: 0.75rem; margin-right: 0.625rem; flex-shrink: 0; }
.sk-line1 { width: 7.5rem; height: 0.875rem; margin-bottom: 0.5rem; }
.sk-line2 { width: 11.25rem; height: 0.75rem; }
@keyframes sk-loading {
  0% { background-position: 100% 50%; }
  to { background-position: 0 50%; }
}

.noData { margin-top: 20vh; padding-bottom: 1.5625rem; }
.noData .noData-icon { width: 7.8125rem; height: 7.8125rem; }
.noData .font-999-26 { margin-top: 0.75rem; }
.load-txt { text-align: center; color: #999; font-size: 0.75rem; margin: 0.9375rem 0 6.25rem; }

/* ============ 版权库瀑布流 ============ */
.list { width: calc(100% - 1.25rem); margin: 0.3125rem auto 1.5625rem; padding-bottom: 1.5625rem; }
.waterfall-col { width: 50%; }
.waterfall-item .cover { margin-bottom: 0.0625rem; width: 10.875rem; border-radius: 0.75rem; display: block; }

.accessCard { width: 100%; padding: 1.25rem 0; }
.enterBtn {
  position: fixed; bottom: 5.625rem; width: 20.5625rem; height: 3rem;
  background: #3578fc; border-radius: 0.625rem; font-weight: 500; z-index: 5;
}

/* ============ 行业资讯 ============ */
.intrduceBlock { width: 100%; overflow-x: hidden; }
.scrollView { height: calc(100vh - 13.75rem); overflow-y: auto; }
.headAct { position: sticky; top: 0; z-index: 99; width: 23.4375rem; padding: 0 0 0.625rem; }
.white-block { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.625rem 0; }
.big-tab { width: 100%; }
.item-text {
  font-family: 'PingFang SC', sans-serif; font-weight: 600; font-size: 1rem; color: #333;
  margin-right: 1.875rem; position: relative;
}
.item-text::after {
  content: ''; position: absolute; bottom: -0.3125rem; left: 50%; transform: translate(-50%);
  width: 0.9375rem; height: 0.1875rem; background: #333; border-radius: 2.875rem;
}
.item-text-a {
  font-family: 'PingFang SC', sans-serif; font-weight: 500; font-size: 0.875rem; color: #999;
  margin-right: 1.875rem; position: relative;
}
.arrow-right { margin-left: 0.1875rem; }

.cu-list.grid { text-align: center; }
.cu-list.grid > .cu-item {
  position: relative; display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 0.625rem 0.875rem;
}
.cu-list.grid.col-2 > .cu-item { width: 50%; }
.topic-img { width: 2.125rem; height: 2.125rem; border-radius: 0.375rem; }
.line { width: 23.4375rem; height: 0.3125rem; background: #f7f8fa; margin: 0 0 1.4375rem; }

.search-block {
  position: relative; width: 21.4375rem; height: 2.0625rem; background: #fff;
  border-radius: 0.5rem; border: 0.03125rem solid #333; margin: 0 auto;
  box-sizing: border-box;
}
.search-block img { width: 0.75rem; height: 0.75rem; position: absolute; left: 0.875rem; top: 50%; transform: translateY(-50%); }
.search-block input {
  padding: 0.5rem 2.25rem; box-sizing: border-box; font-family: 'PingFang SC', sans-serif;
  font-weight: 500; font-size: 0.75rem; color: #333; width: 100%; height: 100%;
  border: none; outline: none; background: transparent;
}
.search-block input::placeholder { color: #b6bac4; }

/* ============ TabBar 占位 ============ */
.nb-tabbar-space { width: 100%; height: calc(6rem + env(safe-area-inset-bottom)); }
</style>
