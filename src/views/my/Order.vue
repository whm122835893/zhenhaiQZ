<template>
  <!--
    订单列表页 —— 复刻原站 /pages/my/order/index
    源文件：assets/pages-my-order-index.omZ1ybKK.js + assets/index-CztJHpqz.css
    实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px
  -->
  <div class="order-list-page">
    <!-- 原站系统导航栏（uni-page-head）：返回箭头 + 标题「订单」，高 44px -->
    <UniPageHead title="订单" />

    <div class="containerStyle">
      <!-- 顶部：订单类型 Tab + 状态筛选（原站用系统导航栏，标题「订单」由导航承载） -->
      <div class="fixedHead">
        <div class="bg-fff mb20">
          <!-- 一级 Tab：7 种订单类型 -->
          <div class="u-tabs">
            <div class="u-tabs__scroll">
              <div class="u-tabs__nav">
                <div
                  v-for="(t, i) in orderTypes"
                  :key="t.name"
                  class="u-tabs__nav__item"
                  :class="{ 'is-active': currentType === i }"
                  @click="changeOrderType(i)"
                >
                  <div class="u-tabs__nav__item__text">{{ t.name }}</div>
                </div>
                <div class="u-tabs__nav__line" :style="{ transform: `translateX(${tabLineLeft}px)` }" />
              </div>
            </div>
          </div>
        </div>

        <!-- 二级筛选：胶囊 Tab -->
        <div class="tabList">
          <div
            v-for="(s, i) in currentStatusList"
            :key="s"
            class="tabItem doubleCenter"
            :class="{ tabed: currentStatus === i }"
            @click="changeOrderStatus(i)"
          >
            {{ s }}
          </div>
        </div>
      </div>

      <!-- 订单列表 -->
      <div class="listBox">
        <div
          v-for="o in orderList"
          :key="o.orderId"
          class="itemView"
          @click="goInfo(o.orderId)"
        >
          <div class="timeLookMore centerBetween">
            <div class="timeState center">
              <div class="font-999-24-500 mr15">{{ timeFormat(o.createTime) }}</div>
              <!-- 支付渠道标签：快/汇/杉/易 -->
              <span
                v-for="(tag, ti) in getPayTags(o)"
                :key="ti"
                class="payTag"
                :style="{ background: tag.color }"
              >{{ tag.text }}</span>
            </div>
            <div class="state" :class="getStateClass(o)">{{ getOrderStatusText(o) }}</div>
          </div>

          <div class="goodInfoBox">
            <div class="start">
              <img class="cover" :src="o.imageUrl" alt="" />
              <div class="flex1 nameSn column">
                <div class="namePrice centerBetween">
                  <div class="font-000-28-bold name">{{ o.title }}</div>
                  <div class="font-333-36-bold price">￥{{ amountFormat(o.amount) }}</div>
                </div>
                <div>
                  <div class="keyVal">
                    <span class="key">订单编号</span>
                    <span class="val">{{ o.orderId }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="orderFinished && !orderList.length" class="order-empty">
          <div class="order-empty-text">暂无订单</div>
        </div>
        <div v-if="orderList.length && orderFinished" class="load-txt">没有更多了</div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 原站逻辑（pages-my-order-index.omZ1ybKK.js）：
 *   data: currentType/currentStatus/timer/tabLineLeft/orderTypes/orderFinished/orderQuery/orderList
 *   orderTypes 7 类，各自带 statuses(展示) + statusValues(接口 status 参数)
 *   changeOrderType(i)：type 映射 0→10 常规, 1→20 市场, 2→21 批量, 3→23 委托, 4→22 求购, 5→40 转售, else→11 申购
 *   changeOrderStatus(i)：取 statusValues[i] 作为 query.status
 *   listOrder()：GET /order {pageNo, status, type}；返回空数组 → orderFinished=true
 *   code 11111 → orderFinished=true（不跳登录）
 *   onShow 每次进入都重置 pageNo=1 重新拉取
 *   goInfo(orderId) → /pages/my/order/info?orderId=xxx
 *
 *   状态类：{0:state0 待支付, 1:state2 进行中, 2:state3 已取消, 3:state1 已完成, 4:state4 已退款}
 *   特殊文案（type 22/23 且 status 3）：
 *     quantity===itemQuantity → 已完成；itemQuantity===0 → 已全部退款；否则 已部分退款
 *   支付渠道标签 payTags 配色：快 #B44A50 / 汇 #2AD6EB / 杉 #0F357B / 易 #24B33A / 默认 #28D8EA
 */
import { ref, computed, nextTick, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { orderApi } from '@/api'
import { amountFormat, timeFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'

const router = useRouter()
const route = useRoute()

const currentType = ref(0)
const currentStatus = ref(0)
const tabLineLeft = ref(0)
const orderFinished = ref(false)
const orderList = ref([])
const orderQuery = ref({ pageNo: 1, status: '', type: 10 })

/** 7 类订单及其状态筛选（与原站 orderTypes 完全一致） */
const orderTypes = [
  { name: '常规订单', statuses: ['全部', '待付款', '已完成', '已取消'], statusValues: ['', 0, 3, 2] },
  { name: '市场订单', statuses: ['全部', '待付款', '已完成', '已取消', '已退款'], statusValues: ['', 0, 3, 2, 4] },
  { name: '批量订单', statuses: ['全部', '待付款', '已完成', '已取消'], statusValues: ['', 0, 3, 2] },
  { name: '委托订单', statuses: ['全部', '待付款', '委托中', '已完成', '已取消'], statusValues: ['', 0, 1, 3, 2] },
  { name: '求购订单', statuses: ['全部', '待付款', '求购中', '已完成', '已取消'], statusValues: ['', 0, 1, 3, 2] },
  { name: '转售订单', statuses: ['全部', '待付款', '已完成', '已取消'], statusValues: ['', 0, 3, 2] },
  { name: '申购订单', statuses: ['全部', '待付款', '申购中', '已完成', '已取消'], statusValues: ['', 0, 1, 3, 2] },
]

const currentStatusList = computed(() => orderTypes[currentType.value].statuses)

/** 原站 updateTabLine()：累加前面 tab 宽度，再居中偏移半个差值 */
function updateTabLine() {
  const items = document.querySelectorAll('.u-tabs__nav__item')
  const line = document.querySelector('.u-tabs__nav__line')
  if (!items.length || !line) return
  const idx = currentType.value
  let left = 0
  for (let i = 0; i < idx; i++) left += items[i].getBoundingClientRect().width
  const cur = items[idx].getBoundingClientRect().width
  const lw = line.getBoundingClientRect().width
  left += (cur - lw) / 2
  tabLineLeft.value = left
}

function back() {
  window.history.length > 1 ? router.back() : router.replace('/pages/my/index')
}

/** 支付渠道标签（快/汇/杉/易），支持对象或字符串两种形态 */
function getPayTags(o) {
  if (!o || !o.payTags || !o.payTags.length) return []
  const COLORS = { 快: '#B44A50', 汇: '#2AD6EB', 杉: '#0F357B', 易: '#24B33A' }
  return o.payTags.map((t) =>
    typeof t === 'object' && t
      ? { text: t.text || '', color: t.color || '#28D8EA' }
      : { text: t, color: COLORS[t] || '#28D8EA' }
  )
}

/** 状态 → CSS 类 */
function getStateClass(o) {
  return { 0: 'state0', 1: 'state2', 2: 'state3', 3: 'state1', 4: 'state4' }[o.status] || 'state3'
}

/** 状态文案：求购(22)/委托(23) 且已完成时按 itemQuantity 细分 */
function getOrderStatusText(o) {
  const MAP = { 0: '待支付', 1: '进行中', 2: '已取消', 3: '已完成', 4: '已退款' }
  if ((o.type === 22 || o.type === 23) && o.status === 3) {
    return o.quantity === o.itemQuantity ? '已完成' : o.itemQuantity === 0 ? '已全部退款' : '已部分退款'
  }
  return MAP[o.status] || '未知状态'
}

function changeOrderType(i) {
  if (currentType.value === i) return
  currentType.value = i
  currentStatus.value = 0
  orderQuery.value.type = i === 0 ? 10 : i === 1 ? 20 : i === 2 ? 21 : i === 3 ? 23 : i === 4 ? 22 : i === 5 ? 40 : 11
  orderFinished.value = false
  orderQuery.value.pageNo = 1
  orderQuery.value.status = ''
  listOrder()
  nextTick(updateTabLine)
}

function changeOrderStatus(i) {
  if (currentStatus.value === i) return
  currentStatus.value = i
  orderQuery.value.status = orderTypes[currentType.value].statusValues[i]
  orderQuery.value.pageNo = 1
  orderFinished.value = false
  listOrder()
}

function listOrder(append = false) {
  orderApi.list(orderQuery.value).then((res) => {
    if (res.status) {
      const data = res.data || []
      orderList.value = append ? [...orderList.value, ...data] : data
      if (data.length === 0) orderFinished.value = true
    } else if (res.code === 11111) {
      orderFinished.value = true
    }
  })
}

function goInfo(orderId) {
  router.push(`/pages/my/order/info?orderId=${orderId}`)
}

function onReachBottom() {
  if (orderFinished.value) return
  orderQuery.value.pageNo += 1
  listOrder(true)
}

onMounted(() => {
  if (route.query.type) changeOrderType(Number(route.query.type))
  orderFinished.value = false
  orderQuery.value.pageNo = 1
  listOrder()
  nextTick(updateTabLine)
})

defineExpose({ onReachBottom })
</script>

<style scoped>
/* ============ 页面基线（原站 uni-page-body）============ */
.order-list-page {
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
  background-color: #f3f4f8;
  min-height: 100vh;
}

.page-head-placeholder {
  width: 100%;
  height: 44px;
  background-color: #f3f4f8;
}

.containerStyle {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
}
/* 实测 fixedHead [0,44,390,70]，sticky 吸顶；高 = 30.7 + mb20(9.9) + 29.4 */
.fixedHead {
  position: sticky;
  top: 0;
  z-index: 100;
  width: 100%;
  background-color: #f3f4f8;
}

.bg-fff {
  background-color: #fff;
}
.mb20 {
  margin-bottom: 0.59375rem;
}

/* ============ 标题栏 ============ */
.backTitle {
  width: 100%;
  text-align: center;
  padding-top: calc(var(--status-bar-height, 0px) + 1.21875rem);
  padding-bottom: 0.625rem;
  position: relative;
  z-index: 99;
}
.backTitle .back {
  position: absolute;
  left: 1.125rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.875rem;
  height: 1.875rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.backTitle .navtitle {
  width: 70%;
}
.backTitle .navtitle .tip {
  display: inline-block;
  height: 1.21875rem;
  line-height: 1.21875rem;
}
.backTitle .right-icon {
  position: absolute;
  right: 1.125rem;
}
.font-000-34-bold {
  color: #000;
  font-weight: 700;
  font-size: 1.0625rem;
}
.font-000-28-bold {
  color: #000;
  font-weight: 700;
  font-size: 0.875rem;
}
.font-333-36-bold {
  color: #333;
  font-weight: 700;
  font-size: 1.125rem;
}
.font-999-24-500 {
  color: #999;
  font-size: 0.75rem;
  font-weight: 500;
}

.doubleCenter {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
}
.center {
  display: flex;
  align-items: center;
}
.centerBetween {
  width: 100%;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}
.column {
  display: flex;
  flex-direction: column;
}
.start {
  display: flex;
  flex-direction: row;
  align-items: flex-start;
}
.flex1 {
  flex: 1;
  min-width: 0;
}
.absoluteCenter {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

/* ============ 一级 Tab ============ */
.u-tabs .u-tabs__scroll {
  white-space: nowrap;
}
.u-tabs .u-tabs__scroll .u-tabs__nav {
  display: flex;
  flex-direction: row;
  position: relative;
}
/* 实测 tab item [0,44,77.9,30.7] */
.u-tabs .u-tabs__scroll .u-tabs__nav .u-tabs__nav__item {
  flex-shrink: 0;
  padding: 0 0.59375rem;
  height: 1.84375rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.u-tabs .u-tabs__scroll .u-tabs__nav .u-tabs__nav__item .u-tabs__nav__item__text {
  font-size: 0.875rem;
  font-weight: 500;
  color: rgba(153, 153, 153, 0.6);
}
.u-tabs .u-tabs__scroll .u-tabs__nav .u-tabs__nav__item.is-active .u-tabs__nav__item__text {
  color: #333;
  font-weight: 600;
}
/* 实测 line [32.7,72.6,12.5,1] */
.u-tabs .u-tabs__scroll .u-tabs__nav .u-tabs__nav__line {
  position: absolute;
  bottom: 0.0625rem;
  left: 0;
  width: 0.75rem;
  height: 0.0625rem;
  background: #333;
  border-radius: 3.125rem;
  transition: transform 0.3s;
}

/* ============ 二级胶囊筛选 ============ */
/* 实测 tabList [15.6,84.5,358.8,29.4] */
.tabList {
  margin: 0 auto;
  width: calc(100% - 1.875rem);
  display: flex;
  box-sizing: content-box;
}
/* 实测 tabItem [15.6,84.5,54,29.4] */
.tabList .tabItem {
  flex-shrink: 0;
  margin-right: 0.5rem;
  padding: 0.34375rem 0.875rem;
  border-radius: 3.125rem;
  background: #fff;
  color: #454545;
  font-size: 0.75rem;
  cursor: pointer;
}
.tabList .tabItem:last-child {
  margin-right: 0;
}
.tabList .tabItem.tabed {
  background: #222;
  color: #fff;
}

/* ============ 列表 ============ */
.listBox {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 0.625rem 0 0.9375rem;
}
/* 实测 itemView [5.2,124.4,379.6,148.9] */
.listBox .itemView {
  width: calc(100% - 1.875rem);
  padding: 0.75rem 0.625rem 1rem;
  margin-bottom: 0.625rem;
  background: #fff;
  border-radius: 0.625rem;
  cursor: pointer;
  /* 原站未声明 box-sizing → content-box，宽不含 padding（实测 379.6 = 358.8+2*10.4） */
  box-sizing: content-box;
}
.listBox .itemView .timeLookMore {
  padding-bottom: 0.625rem;
  border-bottom: 0.0625rem solid #f8f8f8;
}
.listBox .itemView .timeLookMore .timeState .mr15 {
  padding-left: 0.375rem;
  margin-right: 0.46875rem;
}
/* 实测 payTag 小方块 */
.listBox .itemView .timeLookMore .timeState .payTag {
  margin-left: 0.25rem;
  font-size: 0.59375rem;
  font-weight: 500;
  color: #fff;
  width: 1.125rem;
  height: 1.125rem;
  text-align: center;
  line-height: 1.125rem;
  border-radius: 0.3125rem;
}
/* 实测 state [311.5,136.8,57.2,26.3] */
.listBox .itemView .timeLookMore .state {
  margin-right: 0.34375rem;
  padding: 0.25rem 0.59375rem;
  text-align: center;
  border-radius: 0.125rem;
  font-size: 0.75rem;
}
.listBox .itemView .timeLookMore .state.state0 {
  background: #fff2f2;
  color: #ff5f5f;
}
.listBox .itemView .timeLookMore .state.state1 {
  background: #f2fff4;
  color: #24b33a;
}
.listBox .itemView .timeLookMore .state.state2 {
  background: #f3f7ff;
  color: #3f77f5;
}
.listBox .itemView .timeLookMore .state.state3 {
  background: #f8f8f8;
  color: #999;
}
.listBox .itemView .timeLookMore .state.state4 {
  background: #fff0e3;
  color: #ff7800;
}
.listBox .itemView .goodInfoBox {
  padding: 0.59375rem 0.46875rem 0;
}
/* 实测 cover [23.4,184.4,72.3,72.3] */
.listBox .itemView .goodInfoBox .cover {
  flex-shrink: 0;
  margin-right: 0.53125rem;
  /* 原站实测封面 72.3x72.3（父级 uni-image 自适应），本地按此锁定 */
  width: 4.34375rem;
  height: 4.34375rem;
  border-radius: 0.3125rem;
  object-fit: cover;
}
.listBox .itemView .goodInfoBox .namePrice {
  margin-bottom: 0.59375rem;
}
.listBox .itemView .goodInfoBox .namePrice .name {
  flex: 1;
  min-width: 0;
  margin-right: 0.3125rem;
  line-height: 1.21875rem;
  word-break: break-all;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.listBox .itemView .goodInfoBox .namePrice .price {
  flex-shrink: 0;
}
/* 实测 keyVal [104.5,222.3,252.2,22.3] */
.listBox .itemView .goodInfoBox .keyVal {
  display: flex;
  align-items: center;
  height: 1.21875rem;
  line-height: 1.21875rem;
  font-size: 0.59375rem;
  font-weight: 500;
  color: #28d8ea;
  background: #f0fdff;
  border: 0.03125rem solid #2ad6eb;
  border-radius: 0.1875rem;
  overflow: hidden;
  margin-right: 0.59375rem;
  /* 原站未声明 box-sizing → content-box，1.21875rem 不含 0.5px×2 边框（实测 22.3） */
  box-sizing: content-box;
  /* 原站 keyVal 是 .nameSn(column flex) 的直接子项 → align-items:stretch 撑满
     实测 252.2 = nameSn 262.1 − margin-right 9.9 */
  flex: none;
  /* content-box：设定宽不含 0.5px×2 边框，实测外框 252.2 → 设定 250.2 */
  width: 250.2px;
}
.listBox .itemView .goodInfoBox .keyVal .key {
  height: 100%;
  padding: 0 0.1875rem;
  background: linear-gradient(180deg, #4be0f0, #2ad6eb);
  color: #fff;
}
.listBox .itemView .goodInfoBox .keyVal .val {
  padding: 0 0.25rem;
}
.listBox .load-txt {
  padding: 0.59375rem 0;
  font-size: 0.875rem;
  color: #999;
  text-align: center;
}
.listBox .order-empty {
  display: flex;
  justify-content: center;
  padding-top: 6.25rem;
}
.listBox .order-empty .order-empty-text {
  color: #999;
  font-size: 0.875rem;
}
</style>