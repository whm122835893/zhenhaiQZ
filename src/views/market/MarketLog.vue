<!--
  成交记录页 —— 复刻原站 /pages/market/log
  源文件：assets/pages-market-log.CpWDKKLr.js + assets/log-DpsWVuor.css
  scopeId: data-v-10b89a8b
  实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px

  原站逻辑（pages-market-log.CpWDKKLr.js）：
    data: { finished:false, query:{pageNo:1, iid:''}, list:[] }
    onLoad(q): 无 iid → redirectTo /pages/index/index；有则 getLogList()
    onPullDownRefresh(): finished=false, pageNo=1, getLogList(true)
    onReachBottom()   : 未 finished 则 pageNo++, getLogList(false, true) 追加
    getLogList()       : nftApi.listMarketSoldLog(query)
                        返回空数组 → finished = true
    列表项渲染：
      title = '#' + token
      time  = $fmt.timeFormat(soldTime)
      price = $fmt.amountFormat(soldPrice)   ← 自带全角￥前缀
-->
<template>
  <!-- 原站系统导航栏：标题「成交记录」，高 44px -->
  <UniPageHead title="成交记录" />

  <div class="container">
    <div v-for="(item, i) in list" :key="i" class="item">
      <div class="item-left">
        <div class="title">#{{ item.token }}</div>
        <div class="time">{{ timeFormat(item.soldTime) }}</div>
      </div>
      <div class="item-right">
        <div class="price">{{ amountFormat(item.soldPrice) }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 成交记录 —— 复刻原站 pages-market-log.CpWDKKLr.js
 * CSS 逐条对齐原站 log-DpsWVuor.css（scopeId data-v-10b89a8b）
 */
import { ref, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { nftApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import { amountFormatYen as amountFormat, timeFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

const finished = ref(false)
const query = reactive({ pageNo: 1, iid: '' })
const list = ref([])

/** 原站 getLogList：isRefresh 传 true 时不显示 loading，下拉刷新结束调用 stopPullDownRefresh */
function getLogList(isRefresh, isAppend) {
  if (!isRefresh) showLoading()
  nftApi.secondarySoldList(query).then((res) => {
    if (!isRefresh) hideLoading()
    if (res.status) {
      const data = res.data || []
      list.value = isAppend ? [...list.value, ...data] : data
      if (data.length === 0) finished.value = true
    }
  })
}

/** 原站 onLoad：无 iid 跳首页 */
if (!route.query.iid) {
  router.replace('/pages/index/index')
} else {
  query.iid = route.query.iid
  getLogList()
}

/** 原站 onPullDownRefresh */
function onPullDownRefresh() {
  finished.value = false
  query.pageNo = 1
  getLogList(true)
}

/** 原站 onReachBottom */
function onReachBottom() {
  if (finished.value) return
  query.pageNo += 1
  getLogList(false, true)
}

// 暴露给列表容器（uni-page-refresh / scroll-view）使用
defineExpose({ onPullDownRefresh, onReachBottom })
</script>

<style scoped>
/* 原站 log-DpsWVuor.css，scopeId data-v-10b89a8b，逐条 1:1 还原 */
.container {
  padding: 0 0.625rem;
}
.item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #fff;
  border-radius: 0.5rem;
  padding: 0.9375rem 1.25rem;
  margin-bottom: 0.625rem;
  border: 1px solid #eaeaea;
  box-shadow: 0 0.125rem 0.375rem rgba(0, 0, 0, 0.08);
}
.item-left {
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.title {
  font-size: 1rem;
  font-weight: 500;
  color: #333;
  margin-bottom: 0.3125rem;
}
.time {
  font-size: 0.8125rem;
  color: #999;
}
.item-right {
  display: flex;
  align-items: center;
}
.price {
  font-size: 1.125rem;
  font-weight: 500;
  color: #333;
}
</style>
