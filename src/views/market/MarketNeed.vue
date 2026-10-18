<!--
  求购购买页 —— 复刻原站 /pages/market/need
  源文件：assets/pages-market-need.CD8C7P6C.js + assets/need-DQtbDiht.css
  scopeId: data-v-9340c938
  实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px

  原站逻辑（pages-market-need.CD8C7P6C.js）：
    data: { iid, item, price, quantity:10 }
    onLoad(q): 无 iid → redirectTo /pages/index/index；有则 getItem()
    computed totalPrice = price * quantity
    submitOrder():
      if (!price) return showToast('请设置价格')
      POST order/secondary/apply { iid, unitPrice:100*price, quantity }   ← 注意无 payWay
      status → navigateTo /pages/my/order/info?orderId=<data>
      code 11111 → navigateTo /pages/authorize/signin
  差异点（相对 batch）：
    · .container 背景为 #fff（batch 为 #f5f5f5）
    · 按钮 #2b52ef / 圆角 11px / 16px 粗体（batch 为 #4478ff / 26px / 1rem 常规）
    · .divider 背景 #2a2a2a、margin .375rem 0（batch 为 #eee、.3125rem 0）
    · .quantity-controls 无边框（batch 有 1px solid #919191）
    · 多出「汇付」收款方式行 + 「求购说明」六条
-->
<template>
  <!-- 原站系统导航栏：标题「求购购买」，高 44px -->
  <UniPageHead title="求购购买" />

  <div class="container">
    <!-- 藏品卡 -->
    <div class="product-card">
      <div class="product-info">
        <!-- 原站 uni-image mode="aspectFill"：src 为空时仍占 4.6875rem 见方 -->
        <div v-if="!item.imageUrl" class="product-image" />
        <img v-else class="product-image" :src="item.imageUrl" alt="" />
        <div class="product-details">
          <div class="product-name">{{ item.name }}</div>
        </div>
      </div>
    </div>

    <!-- 求购价格 + 购买数量 -->
    <div class="commission-section">
      <div class="section-title">
        <div>求购价格</div>
        <img class="help-icon" :src="helpIcon" alt="" />
      </div>
      <div class="price-input-section">
        <div class="currency-symbol">¥</div>
        <input
          class="input-price"
          type="number"
          inputmode="numeric"
          v-model="body.price"
          placeholder="请输入价格"
        />
      </div>
      <div class="divider" />
      <div class="quantity-section" style="margin-top: 10px">
        <div class="quantity-label">购买数量</div>
        <div class="quantity-controls">
          <div class="quantity-btn decrease" @click="decreaseQuantity"> − </div>
          <input
            class="quantity-value"
            type="number"
            inputmode="numeric"
            v-model="body.quantity"
            @blur="validateQuantity"
          />
          <div class="quantity-btn increase" @click="increaseQuantity"> + </div>
        </div>
      </div>
      <!-- 收款方式：汇付（行内样式，原站未抽 CSS 类） -->
      <div class="pay-row">
        <div class="pay-left">
          <img class="pay-logo" :src="hfpayIcon" alt="" />
          <div class="pay-name"> 汇付 </div>
        </div>
        <div class="pay-right">
          <img class="pay-checked" :src="checkedIcon" alt="" />
        </div>
      </div>

      <!-- 求购说明六条 -->
      <div class="notice-row">
        <div class="notice-title"> 求购说明 </div>
        <div class="notice-list">
          <h5>1.主动下单，等待出售:求购功能允许买家提前设置价格挂单，等待卖家主动点击出售，才能完成交易。</h5>
          <h5>2.非自动成交:与委托不同，求购不会自动成交，必须由持有藏品的卖家主动确认出售。</h5>
          <h5>3.成交顺序:同一藏品多个求购订单时，卖家可自由选择任一订单进行出售。</h5>
          <h5>4.资金冻结:下单后系统将冻结对应金额，成交或取消后自动解冻。</h5>
          <h5>5.随时可取消:求购订单可随时取消或修改价格，未成交前不收取任何手续费。</h5>
          <h5>6.风险提示:求购为用户自主操作，系统仅提供撮合服务，请合理设置价格并自行判断市场价值。</h5>
        </div>
      </div>
    </div>

    <!-- 底部固定：合计 + 预付 -->
    <div class="footer">
      <div class="total-price">
        <div>合计:</div>
        <!-- 原站模板逐字为：<text class="price">¥ {{ $fmt.amountFormat(totalPrice) }}</text>
             注意「¥」与金额之间有一个空格，不可省略 -->
        <div class="price">¥ {{ totalPrice }}</div>
      </div>
      <button class="submit-btn" @click="submitOrder">预付</button>
    </div>
  </div>
</template>

<script setup>
/**
 * 求购购买 —— 复刻原站 pages-market-need.CD8C7P6C.js
 * CSS 逐条对齐原站 need-DQtbDiht.css（scopeId data-v-9340c938）
 */
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { itemApi, orderApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'
import helpIcon from '@/assets/market/help-icon.png'
import hfpayIcon from '@/assets/market/hfpay-icon.png'
import checkedIcon from '@/assets/market/checked-icon.png'

const route = useRoute()
const router = useRouter()

const item = ref({})
const body = reactive({ iid: '', price: '', quantity: 10 })

/** 原站 computed：totalPrice = price * quantity */
const totalPrice = computed(() => body.price * body.quantity)

function getItem() {
  itemApi.get(body.iid).then((res) => {
    if (res.status) item.value = res.data
  })
}

/** 原站 onLoad：无 iid 跳首页 */
if (!route.query.iid) {
  router.replace('/pages/index/index')
} else {
  body.iid = route.query.iid
  getItem()
}

function decreaseQuantity() {
  if (body.quantity > 1) body.quantity--
}

function increaseQuantity() {
  body.quantity++
}

function validateQuantity() {
  const q = parseInt(body.quantity)
  if (isNaN(q) || q < 1) body.quantity = 1
  else body.quantity = q
}

/** 原站 submitOrder：POST /order/secondary/apply（无 payWay 字段） */
function submitOrder() {
  if (!body.price) return showToast('请设置价格')
  const data = {
    iid: body.iid,
    unitPrice: 100 * body.price,
    quantity: body.quantity,
  }
  showLoading()
  orderApi.createSecondaryNeedOrder(data).then((res) => {
    hideLoading()
    if (res.status) {
      router.push('/pages/my/order/info?orderId=' + res.data)
    } else if (res.code === 11111) {
      router.push('/pages/authorize/signin')
    }
  })
}
</script>

<style scoped>
/* 原站 need-DQtbDiht.css，scopeId data-v-9340c938，逐条 1:1 还原 */
.container {
  /* 原站未声明 box-sizing（content-box）：padding 16px 叠加在 min-height:100vh 之外 */
  box-sizing: content-box;
  padding: 16px;
  background-color: #fff;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  /* 原站本页分包未加载 MyFont2 的 @font-face（该 @font-face 只存在于公告详情页分包），
     故此处的 MyFont2 未定义、静默回退到浏览器默认字体；family 名照抄原站。 */
  font-family: MyFont2;
}
.product-card {
  background-color: #fff;
  border-radius: 12px;
  padding: 10px;
  margin-bottom: 0.625rem;
}
.product-info {
  display: flex;
  align-items: center;
}
.product-image {
  width: 4.6875rem;
  height: 4.6875rem;
  border-radius: 0.3125rem;
  margin-right: 0.625rem;
  flex-shrink: 0;
  object-fit: cover;
}
.product-details {
  flex: 1;
}
.product-name {
  font-size: 15px;
  color: #333;
  margin-bottom: 0.46875rem;
}
.commission-section {
  background-color: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 0.625rem;
}
.section-title {
  display: flex;
  align-items: center;
  margin-bottom: 0.9375rem;
  font-size: 16px;
  font-weight: 700;
  color: #333;
  gap: 5px;
}
.section-title .help-icon {
  width: 12px;
  height: 12px;
}
.price-input-section {
  display: flex;
  align-items: center;
  margin-bottom: 0.3125rem;
}
.currency-symbol {
  font-size: 14px;
  margin-right: 10px;
  color: #000;
  /* 原站文本元素 line-height normal（14/15px → h 17/18px），本地 Noto 会撑到 20/21px，锁定为 1.2 */
  /* 原站文本元素 line-height 为 normal；本地已统一为浏览器默认字体，
     行为与原站一致，故不再需要 line-height 补偿 */
}
.input-price {
  font-size: 15px;
  flex: 1;
  border: none;
  outline: none;
  margin: 0;
  padding: 0;
  background: transparent;
  /* 原站 uni-input 实测行高 = font-size × 1.4（14px→19.6 / 15px→21） */
  line-height: 1.4;
}
.divider {
  height: 0.0625rem;
  background-color: #2a2a2a;
  margin: 0.375rem 0;
}
.quantity-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
/* need 页 scopeId = data-v-9340c938，原站该页没有独立的 quantity CSS 分包
   （已核对 css-info-*.css 五个分包，quantity 相关规则只属于 MarketInfo 的
   data-v-fb03df53 与委托页的 data-v-094ba4bf）。
   本页 .quantity-* 无原站样式，以下按原站实测 computed 反推：
     label  color:#000 / font-size:14px / line-height:normal → h 20
     btn    font-size:14px / line-height:normal         → h 20
   高度由此行高决定，故不额外设置 line-height。 */
.quantity-label {
  font-size: 14px;
  color: #000;
}
.quantity-controls {
  display: flex;
  align-items: center;
  height: 23px;
  border-radius: 5px;
  padding-left: 10px;
  padding-right: 10px;
}
.quantity-btn {
  font-size: 14px;
  cursor: pointer;
  user-select: none;
}
.quantity-value {
  font-size: 14px;
  width: 2.5rem;
  text-align: center;
  border: none;
  background: transparent;
  outline: none;
  margin: 0;
  padding: 0;
  /* 原站 uni-input 实测行高 = font-size × 1.4（14px→19.6 / 15px→21） */
  line-height: 1.4;
}

/* ---- 以下三块原站为行内样式，此处保持一致 ---- */
.pay-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  /* 原站行内 margin-top:30rpx，1rpx = 1/32rem → 0.9375rem */
  margin-top: 0.9375rem;
}
.pay-left {
  display: flex;
  align-items: center;
}
.pay-logo {
  width: 18px;
  height: 18px;
}
.pay-name {
  /* 原站行内 margin-left:16rpx → 0.5rem */
  margin-left: 0.5rem;
}
.pay-checked {
  width: 20px;
  height: 20px;
}
.notice-row {
  margin-top: 50px;
}
.notice-title {
  font-size: 15px;
  color: #333;
  font-weight: 700;
}
.notice-list {
  margin-top: 10px;
}
.notice-list h5 {
  /* 原站 h5 无 class，走 uni-app 全局重置：0.8rem / 700 / margin 0 / line-height normal
     实测 font-size 13.28px（1rem=16.6px）、font-weight 700 */
  font-size: 0.8rem;
  font-weight: 700;
  line-height: normal;
  margin: 0;
  color: #000;
}

.footer {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: flex;
  padding: 0.625rem 0.9375rem;
}
.total-price {
  flex: 1;
  display: flex;
  align-items: center;
  font-size: 15px;
}
.price {
  margin-left: 0.3125rem;
  /* 原站文本元素 line-height normal（14/15px → h 17/18px），本地 Noto 会撑到 20/21px，锁定为 1.2 */
  /* 原站文本元素 line-height 为 normal；本地已统一为浏览器默认字体，
     行为与原站一致，故不再需要 line-height 补偿 */
}
.submit-btn {
  width: 171px;
  padding: 0.1875rem 1.875rem;
  background-color: #2b52ef;
  color: #fff;
  border-radius: 11px;
  font-size: 16px;
  font-weight: 700;
  border: none;
  box-sizing: border-box;
/* 原站 uni-app <button> 默认 line-height:2.5555556（实测 need 页 16px → 40.8889px） */
  line-height: 2.5555556;
}
</style>
