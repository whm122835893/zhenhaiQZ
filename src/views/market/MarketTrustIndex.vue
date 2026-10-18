<!--
  委托列表页 —— 复刻原站 /pages/market/trustIndex
  源文件：assets/pages-market-trustIndex.BQdZlMeb.js + assets/trustIndex-97CsqxYA.css
  scopeId: data-v-645c74cd
  实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px

  原站逻辑（pages-market-trustIndex.BQdZlMeb.js）：
    data: { finished:false, query:{pageNo:1, iid:''}, list:[] }
    onLoad(q): 无 iid → redirectTo /pages/index/index；有则 getTrustList()
    onPullDownRefresh(): finished=false, pageNo=1, getTrustList(true)
    onReachBottom()   : 未 finished 则 pageNo++, getTrustList(false, true) 追加
    getTrustList()    : nftApi.listMarketTrust(query)
                        返回空数组 → finished = true
    表头两列：委托价格 / 委托数量
    列表项：price = $fmt.amountFormat(unitPrice)（自带全角￥前缀）、quantity
-->
<template>
  <!-- 原站系统导航栏：高 44px，占位块使 .trust-container 从 y=44 起 -->
  <!-- 原站 pages.json 中该页 navigationBarTitleText 为「委托订单」 -->
  <UniPageHead title="委托订单" />

  <div class="trust-container">
    <!-- 表头：两列 -->
    <div class="trust-header">
      <div class="header-title">委托价格</div>
      <div class="header-title">委托数量</div>
    </div>

    <!-- 委托列表 -->
    <div v-for="(item, i) in list" :key="i" class="trust-item">
      <div class="price-box">
        <!-- 原站模板中 price-symbol 是空的 <text class="price-symbol"></text>，
             ￥ 由 $fmt.amountFormat(unitPrice) 自带，本地曾误写 '￥' 导致重复 -->
        <div class="price-symbol"></div>
        <div class="price-value">{{ amountFormat(item.unitPrice) }}</div>
      </div>
      <div class="quantity-box">
        <div class="quantity-value">{{ item.quantity }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 委托列表 —— 复刻原站 pages-market-trustIndex.BQdZlMeb.js
 * CSS 逐条对齐原站 trustIndex-97CsqxYA.css（scopeId data-v-645c74cd）
 */
import { ref, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { nftApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import { amountFormatYen as amountFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

const finished = ref(false)
const query = reactive({ pageNo: 1, iid: '' })
const list = ref([])

/** 原站 getTrustList */
function getTrustList(isRefresh, isAppend) {
  if (!isRefresh) showLoading()
  nftApi.secondaryTrustList(query).then((res) => {
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
  getTrustList()
}

function onPullDownRefresh() {
  finished.value = false
  query.pageNo = 1
  getTrustList(true)
}

function onReachBottom() {
  if (finished.value) return
  query.pageNo += 1
  getTrustList(false, true)
}

defineExpose({ onPullDownRefresh, onReachBottom })
</script>

<style scoped>
/* 原站 trustIndex-97CsqxYA.css，scopeId data-v-645c74cd，逐条 1:1 还原 */
.trust-container {
  padding: 0.625rem;
}
.trust-header {
  display: flex;
  justify-content: space-between;
  padding: 0 0.9375rem 0.625rem;
}
.header-title {
  font-size: 0.875rem;
  color: #999;
}
.trust-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: #fff;
  padding: 0.9375rem;
  margin-bottom: 0.625rem;
  border-radius: 0.5rem;
  border: 1px solid #f0f0f0;
  box-shadow: 0 0.125rem 0.375rem rgba(0, 0, 0, 0.05);
}
.price-box {
  display: flex;
  align-items: center;
}
.price-symbol {
  font-size: 0.875rem;
  margin-right: 0.125rem;
}
.price-value {
  font-size: 1.0625rem;
  font-weight: 700;
}
.quantity-value {
  font-size: 1.0625rem;
  font-weight: 700;
}
</style>
