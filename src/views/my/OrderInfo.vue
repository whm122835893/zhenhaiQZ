<template>
  <!--
    订单详情页 —— 复刻原站 /pages/my/order/info
    源文件：assets/pages-my-order-info.B90IIK2A.js + assets/info-YpH6nM6s.css
    实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px
  -->
  <div class="order-page">
    <div class="containerStyle vBg pb280">
      <div class="status-bar" />

      <!-- 顶部返回 + 标题 -->
      <div class="backTitle font-000-34-bold">
        <div class="back doubleCenter" @click="back">
          <UvIcon name="arrow-left" color="#000" :size="17" />
        </div>
        <div class="navtitle absoluteCenter">
          <div class="tip text-center">支付订单</div>
        </div>
        <div class="right-icon" />
      </div>

      <!-- 状态区：状态图 + 状态文案 -->
      <div class="columnDoubleCenter stateBox">
        <img v-if="statusImg" class="statusImg" :src="statusImg" alt="" />
        <div class="font-333-32-bold doubleCenter">{{ statusText }}</div>
      </div>

      <!-- 待支付：支付倒计时 -->
      <div v-if="order.status === 0" class="columnDoubleCenter stateBox countdown-box">
        <div class="timeBox doubleCenter">
          <div class="time doubleCenter">
            <div class="time__custom doubleCenter">
              <div class="time__custom__item">{{ payMinute }}</div>
            </div>
            <div class="time__doc">:</div>
            <div class="time__custom doubleCenter">
              <div class="time__custom__item">{{ paySecond }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 委托订单（type=23）：含限价 / 进度 / 订单类型 -->
      <div v-if="order.type === 23" class="goodInfo bg-fff">
        <div class="nameCoverIds start">
          <img v-if="order.imageUrl" class="cover" :src="order.imageUrl" alt="" />
          <div class="nameIds column">
            <div class="name">{{ order.title }}</div>
          </div>
        </div>
        <div class="orderInfo">
          <div class="keyVal kv-line">
            <div class="key">交易金额</div>
            <div class="val">
              {{ amountFormat(order.unitPrice * order.itemQuantity).slice(1) }} /{{ amountFormat(order.amount).slice(1) }}元
            </div>
          </div>
          <div class="keyVal kv-line">
            <div class="key">购买限价</div>
            <div class="val">{{ amountFormat(order.unitPrice) }}</div>
          </div>
          <div class="keyVal kv-line">
            <div class="key">购买进度/购买数量</div>
            <div class="val">{{ order.itemQuantity }}/{{ order.quantity }}</div>
          </div>
          <div class="keyVal kv-line">
            <div class="key">订单类型</div>
            <div class="val">委托订单</div>
          </div>
          <div v-if="order.createTime" class="keyVal kv-line">
            <div class="key">创建时间</div>
            <div class="val">{{ timeFormat(order.createTime) }}</div>
          </div>
          <div class="keyVal kv-line" @click="copyOrderId">
            <div class="key">订单编号</div>
            <div class="val">{{ order.orderId }}</div>
          </div>
          <div v-if="order.status !== 0 && order.payTime" class="keyVal kv-line">
            <div class="key">完成时间</div>
            <div class="val">{{ timeFormat(order.payTime) }}</div>
          </div>
        </div>
      </div>

      <!-- 常规订单 -->
      <div v-else class="goodInfo bg-fff">
        <div class="nameCoverIds start">
          <img v-if="order.imageUrl" class="cover" :src="order.imageUrl" alt="" />
          <div class="nameIds column">
            <div class="name">{{ order.title }}</div>
            <div v-if="order.itemQuantity" class="tag">数量：x{{ order.itemQuantity }}</div>
          </div>
        </div>
        <div class="orderInfo">
          <div class="keyVal" @click="copyOrderId">
            <div class="key">订单编号</div>
            <div class="val">{{ order.orderId }}</div>
          </div>
          <div class="keyVal">
            <div class="key">订单金额</div>
            <div class="val">￥{{ amountFormat(order.amount) }}</div>
          </div>
          <div v-if="order.createTime" class="keyVal">
            <div class="key">创建时间</div>
            <div class="val">{{ timeFormat(order.createTime) }}</div>
          </div>
          <div v-if="order.status !== 0 && order.payTime" class="keyVal">
            <div class="key">完成时间</div>
            <div class="val">{{ timeFormat(order.payTime) }}</div>
          </div>
        </div>
      </div>

      <!-- 备注 -->
      <div v-if="order.remark" class="goodInfo bg-fff">
        <div class="nav font-333-28-bold">备注</div>
        <div class="cont font-666-24-500">{{ order.remark }}</div>
      </div>

      <!-- 说明 -->
      <div class="explain font-666-24-500">
        <div class="nav font-000-28-bold">说明：</div>
        <div class="cont">
          <div>1.超时未支付，系统会自动取消。</div>
          <div>2.同一账号一段时间内累计取消3次订单,将会被限购一段时间。</div>
        </div>
      </div>

      <!-- 购买须知 -->
      <div class="explain font-666-24-500">
        <div class="nav font-000-28-bold">购买须知：</div>
        <div class="cont">
          <div class="txt">
            为更好服务各位用户，特此公告数字藏品的发售细则。鉴于不同属性的数字艺术的发售细则各有不同，请各位用户务必审慎阅读并同意相关规则后进行相应操作，以免造成不必要损失。
          </div>
          <div class="txt">1、本商品为在线数字化商品，一经出售，不支持七天无理由退换货。</div>
          <div class="txt">2、数字藏品为虚拟数字商品，购买前请完成实名认证，未满18周岁人群禁止购买。</div>
          <div class="txt">
            3、依据我国法律要求，特定条件下的数字藏品的发售不支持任何形式的变相炒作，我们坚决反对数字藏品炒作、场外交易、欺诈，或以任何其他非法、侵权方式使用的违法违规行为，请大家注意甄别上述涉嫌网络欺诈的行为及其相关风险。
          </div>
          <div class="txt">
            4、如您为参与任何活动或获得任何赋能而转让(出售赠与等)、受让、合成、兑换、使用、处置、销毁相关数字藏品，您授权同意平台向活动举办方、赋能提供方、数字藏品发售方等提供您的联系方式、藏品信息、藏品数量、收货地址
            (如需)等必要信息。
          </div>
        </div>
      </div>

      <!-- 底部操作条 -->
      <div class="fixed columnCenter bg-fff">
        <!-- 待支付 -->
        <div v-if="order.status === 0" class="doubleCenter">
          <div class="myBtn doubleCenter font-333-28-500 mr20" @click="cancelOrder">取消订单</div>
          <div class="buyBtn45070" @click="payOrder">去付款</div>
        </div>
        <!-- 委托/求购进行中：仅取消 -->
        <div v-else-if="order.status === 1 && (order.type === 22 || order.type === 23)" class="doubleCenter">
          <div class="myBtn doubleCenter font-333-28-500" @click="cancelOrder">取消订单</div>
        </div>
        <!-- 已取消 -->
        <div v-else-if="order.status === 2" class="doubleCenter">
          <div class="buyBtn45070" @click="toHome">继续购买</div>
        </div>
        <!-- 已完成 -->
        <div v-else-if="order.status === 3" class="doubleCenter">
          <div class="myBtn doubleCenter font-333-28-500 mr20" @click="toMy">我的藏品</div>
          <div class="buyBtn45070" @click="toHome">继续购买</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 原站逻辑（pages-my-order-info.B90IIK2A.js）：
 *   onLoad: orderId = options.orderId，否则 reLaunch 到 /pages/index/index
 *   getOrder(): getOrder(orderId).then(t => {
 *       if (t.status) {
 *         this.order = t.data
 *         // 仅 status===0 且 endPayTime > now 时启动倒计时
 *         if (this.order.status == 0 && this.order.endPayTime > Date.now()) this.timer = setInterval(...)
 *       } else if (t.code != 11111) reLaunch(/pages/index/index)
 *   })
 *   statusText: {0:'待支付',1:'进行中',2:'已取消',3:'已完成',4:'已退款'}[status]
 *   cancelOrder(): showModal 确认 → cancelOrder({orderId}) → 重新拉取 + Toast「订单已取消」
 *   payOrder():    hfpayOrder({orderId}).then(e => e.status && (location.href = e.data))
 *   toWallet():    switchTab /pages/my/wallet/index
 *
 * 注意：金额单位为分，展示走 amountFormat（除 100 保留 2 位）
 */
import { ref, computed, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'
import { orderApi } from '@/api'
import { amountFormat, timeFormat } from '@/utils/fmt'
import UvIcon from '@/components/UvIcon.vue'

import statusWait from '@/assets/order/status-wait.png'
import statusOngoing from '@/assets/order/status-ongoing.png'
import statusCancel from '@/assets/order/status-cancel.png'
import statusSuccess from '@/assets/order/status-success.png'

const route = useRoute()
const router = useRouter()

const orderId = ref(String(route.query.orderId || ''))
const order = ref({ item: {}, items: [] })
const payMinute = ref('00')
const paySecond = ref('00')
let timer = null

const STATUS_TEXT = { 0: '待支付', 1: '进行中', 2: '已取消', 3: '已完成', 4: '已退款' }
const statusText = computed(() => STATUS_TEXT[order.value.status] || '')

/** 原站仅 status 0/1/2/3 有独立状态图，status 4（已退款）无图 */
const statusImg = computed(() => {
  return (
    { 0: statusWait, 1: statusOngoing, 2: statusCancel, 3: statusSuccess }[order.value.status] || ''
  )
})

function getOrder() {
  if (!orderId.value) {
    router.replace('/pages/index/index')
    return
  }
  orderApi.get(orderId.value).then((res) => {
    if (res.status) {
      order.value = res.data
      // 原站：仅待支付且未超时才启动倒计时
      if (order.value.status === 0 && order.value.endPayTime > Date.now()) {
        clearInterval(timer)
        timer = setInterval(() => {
          const rest = order.value.endPayTime - Date.now()
          if (rest <= 0) {
            clearInterval(timer)
            timer = null
            return
          }
          payMinute.value = String(Math.floor(rest / 6e4)).padStart(2, '0')
          paySecond.value = String(Math.floor((rest % 6e4) / 1e3)).padStart(2, '0')
        }, 1000)
      }
    }
    // ⚠️ 源站 getOrder 原文只有 `t.status ? (赋值) : (无操作)`，
    //    **没有**「接口失败就跳首页」的分支。之前多写的
    //    `else if (res.code !== 11111) router.replace(...)` 会让订单不存在时
    //    路由被踢回首页 —— 与源站行为不符（源站此时保持当前路由、显示空态）。
  })
}

function back() {
  window.history.length > 1 ? router.back() : router.replace('/pages/index/index')
}

/** 原站 copyOrderId()：点击订单编号复制 */
function copyOrderId() {
  const text = order.value.orderId
  if (!text) return
  if (navigator.clipboard) navigator.clipboard.writeText(text)
  showToast({ icon: 'none', title: '订单编号已复制' })
}

/** 原站 cancelOrder()：弹窗确认 → PUT /order/cancel */
function cancelOrder() {
  showConfirmDialog({ title: '提示', message: '确定取消该订单吗？' })
    .then(() => {
      return orderApi.cancelOrder({ orderId: orderId.value })
    })
    .then((res) => {
      if (res && res.status) {
        showToast({ icon: 'none', title: '订单已取消' })
        clearInterval(timer)
        timer = null
        getOrder()
      }
    })
    .catch(() => {
      /* 用户取消 */
    })
}

/** 原站 payOrder()：POST /order/pay/hfpay → data 为收银台 URL */
function payOrder() {
  orderApi.payHfpay({ orderId: orderId.value }).then((res) => {
    if (res.status && res.data) {
      window.location.href = res.data
    }
  })
}

function toHome() {
  router.replace('/pages/index/index')
}

function toMy() {
  router.replace('/pages/my/nft/index')
}

onUnmounted(() => {
  if (timer) {
    clearInterval(timer)
    timer = null
  }
})

getOrder()
</script>

<style scoped>
/* ============ 页面基线（原站 uni-page-body）============ */
.order-page {
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
  background-color: #f6f7fb;
  min-height: 100vh;
}

.containerStyle {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
}
.vBg {
  background: url('@/assets/order/v_bg.jpg') 0% 0% / 100% 100% no-repeat;
}
.pb280 {
  padding-bottom: 8.75rem;
}

/* ============ 顶部标题 ============ */
.backTitle {
  width: 100%;
  text-align: center;
  padding-top: 1.21875rem;
  padding-bottom: 1.84375rem;
  position: relative;
  z-index: 99;
}
/* 实测 [15.6,9.9,31.2,31.2] */
.backTitle .back {
  position: absolute;
  left: 0.9375rem;
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
/* 实测 [58.5,20.3,273,20.3] */
.backTitle .navtitle {
  position: absolute;
  left: 50%;
  transform: translate(-50%);
  width: 70%;
}
.backTitle .navtitle .tip {
  height: 1.21875rem;
  line-height: 1.21875rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  text-align: center;
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

/* ============ 状态区 ============ */
.columnDoubleCenter {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.doubleCenter {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
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
.absoluteCenter {
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
}

/* 实测 stateBox [169,81.6,52,94.5] */
.stateBox {
  display: inline-block;
  margin: 1.84375rem auto 1.21875rem;
}
/* 实测 statusImg [169,81.6,52,52] */
.stateBox .statusImg {
  width: 3.125rem;
  margin-bottom: 0.8125rem;
}
/* 倒计时区原站 inline margin-top:-60rpx = -31.2px */
.countdown-box {
  margin-top: -31.2px;
}
.stateBox .timeBox .time {
  margin-top: 1.21875rem;
}
.stateBox .timeBox .time .time__custom {
  width: 1.5625rem;
  height: 1.5625rem;
  background: linear-gradient(314deg, #ff5f5f, #f9897a);
  border-radius: 0.3125rem;
  display: flex;
  justify-content: center;
  align-items: center;
}
.stateBox .timeBox .time .time__custom .time__custom__item {
  color: #fff;
  font-size: 0.375rem;
  text-align: center;
}
.stateBox .timeBox .time .time__doc {
  color: #333;
  font-weight: 700;
  font-size: 0.96875rem;
  padding: 0 0.3125rem;
}
.font-333-32-bold {
  color: #333;
  font-size: 1rem;
  font-weight: 700;
  line-height: 29.5px; /* 实测状态文案行高使 stateBox 高 94.5 */
}

/* ============ 订单信息卡 ============ */
.bg-fff {
  background-color: #fff;
}
/* 实测 goodInfo [5.7,241.6,378.5,212] */
.goodInfo {
  margin-top: 0.59375rem;
  padding: 0.9375rem 0.59375rem;
  width: calc(100% - 1.875rem);
  border-radius: 0.59375rem;
  /* 原站未声明 box-sizing，padding 不计入 width（content-box） */
  box-sizing: content-box;
}
.goodInfo .nameCoverIds {
  padding-bottom: 0.9375rem;
  border-bottom: 0.0625rem solid #f8f8f8;
}
/* 实测 cover [15.6,257.2,51.5,51.5] */
.goodInfo .nameCoverIds .cover {
  margin-right: 0.59375rem;
  width: 3.09375rem;
  height: 3.09375rem;
  border-radius: 0.59375rem;
  object-fit: cover;
  flex-shrink: 0;
}
.goodInfo .nameCoverIds .nameIds {
  flex: 1;
}
.goodInfo .nameCoverIds .nameIds .name {
  color: #000;
  font-weight: 700;
  font-size: 0.9375rem;
  width: 15.625rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.goodInfo .nameCoverIds .nameIds .tag {
  color: #999;
  font-size: 0.6875rem;
  margin-top: 0.3125rem;
}
/* 实测 keyVal [15.6,340.9,358.8,22]，行间距 37.6 */
.goodInfo .orderInfo .keyVal {
  margin-top: 0.9375rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
/* 委托订单专用分隔线（原站 inline style） */
.goodInfo .orderInfo .keyVal.kv-line {
  border-bottom: 1px solid #f3f4f8;
  padding-bottom: 15.6px;
}
.goodInfo .orderInfo .keyVal .key {
  font-size: 0.875rem;
  font-weight: 500;
  color: #7f7f7f;
}
.goodInfo .orderInfo .keyVal .val,
.goodInfo .nav {
  font-weight: 700;
  color: #333;
  font-size: 0.875rem;
}
.goodInfo .cont {
  margin-top: 0.59375rem;
}
.font-333-28-bold {
  font-weight: 700;
  color: #333;
  font-size: 0.875rem;
}
.font-666-24-500 {
  color: #666;
  font-size: 0.75rem;
  font-weight: 500;
}

/* ============ 说明 / 须知 ============ */
/* 实测 explain [5.5,463.5,379.1,108.8] */
.explain {
  margin-top: 0.59375rem;
  background: #fff;
  border-radius: 0.59375rem;
  width: calc(100% - 1.84375rem);
  padding: 0.9375rem 0.59375rem;
  box-sizing: content-box;
}
.explain .nav {
  color: #000;
  font-weight: 700;
  font-size: 0.875rem;
}
.explain .cont {
  margin-top: 0.59375rem;
  line-height: 1.375rem;
}

/* ============ 底部操作条 ============ */
/* 实测 fixed [0,780.1,390,63.9] */
.fixed {
  margin-top: 4.34375rem;
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  padding: 0.59375rem 0 0.9375rem;
  z-index: 99;
  box-shadow: 0 -0.0625rem 0.125rem rgba(0, 0, 0, 0.04);
}
.columnCenter {
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* 实测 myBtn [15.4,790,115.9,38.4] */
.fixed .myBtn {
  width: 6.84375rem;
  height: 2.1875rem;
  border-radius: 0.3125rem;
  border: 0.0625rem solid #212121;
  /* 原站有 1px border 且无 box-sizing 声明 → content-box，高 = 35+2 = 37.4≈38.4 */
  box-sizing: content-box;
  line-height: 2.1875rem;
}
.mr20 {
  margin-right: 0.59375rem;
}
.font-333-28-500 {
  font-weight: 500;
  color: #333;
  font-size: 0.875rem;
}
/* 实测 buyBtn45070 [141.1,791,233.5,36.4] */
.fixed .buyBtn45070 {
  width: 14.03125rem;
  text-align: center;
  line-height: 2.1875rem;
  font-size: 0.96875rem;
  font-weight: 700;
  color: #fff;
  height: 2.1875rem;
  background: #212121;
  border-radius: 0.3125rem;
  cursor: pointer;
}
</style>