<!--
  委托购买页 —— 复刻原站 /pages/market/trust
  源文件：assets/pages-market-trust.BElo3WoV.js + assets/trust-Bm0L1JGU.css
  scopeId: data-v-36309662
  实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px

  原站逻辑（pages-market-trust.BElo3WoV.js）：
    data: { iid, item, price, quantity:10 }
    onLoad(q): 无 iid → redirectTo /pages/index/index；有则 getItem()
    computed totalPrice = price * quantity
    submitOrder():
      if (!price) return showToast('请设置价格')
      POST order/secondary/trust { iid, unitPrice:100*price, quantity }   ← 无 payWay
      status → navigateTo /pages/my/order/info?orderId=<data>
      code 11111 → navigateTo /pages/authorize/signin

  与求购页（need）的关系：CSS 完全一致（仅 scopeId 不同），
  差异仅在 section-title 文案、求购说明六条内容、提交接口。
-->
<template>
  <!-- 原站系统导航栏：标题「委托购买」，高 44px -->
  <UniPageHead title="委托购买" />

  <div class="container">
    <!-- 藏品卡 -->
    <div class="product-card">
      <div class="product-info">
        <!-- 原站 uni-image：src 为空时仍占 4.6875rem 见方 -->
        <div v-if="!item.imageUrl" class="product-image" />
        <img v-else class="product-image" :src="item.imageUrl" alt="" />
        <div class="product-details">
          <div class="product-name">{{ item.name }}</div>
        </div>
      </div>
    </div>

    <!-- 委托价格 + 购买数量 -->
    <div class="commission-section">
      <div class="section-title">
        <div>委托价格</div>
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

      <!-- 委托说明六条 -->
      <div class="notice-row">
        <div class="notice-title"> 委托说明 </div>
        <div class="notice-list">
          <h5>1.自动买入：设置委托价后，系统将自动购买低于或等于该价格的藏品。</h5>
          <h5>2.成交顺序：相同价格时，按委托时间优先成交。</h5>
          <h5>3.资金冻结：委托设置后，系统将冻结对应金额，成交或取消后自动解冻。</h5>
          <h5>4.长期有效：委托持续有效，可随时手动取消。</h5>
          <h5>5.风险提示：建议合理设置委托价，避免自动买入超出预期的藏品。</h5>
          <h5>6.自动执行提醒：委托由用户自行设置，平台将按规则执行，用户需对委托设置结果承担相应后果。</h5>
        </div>
      </div>
    </div>

    <!-- 底部固定：合计 + 预付 -->
    <div class="footer">
      <div class="total-price">
        <div>合计:</div>
        <div class="price">{{ totalPrice }}</div>
      </div>
      <button class="submit-btn" @click="submitOrder">预付</button>
    </div>
  </div>
</template>

<script setup>
/**
 * 委托购买 —— 复刻原站 pages-market-trust.BElo3WoV.js
 * CSS 逐条对齐原站 trust-Bm0L1JGU.css（scopeId data-v-36309662，与 need 完全一致）
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

/** 原站 submitOrder：POST /order/secondary/trust（无 payWay 字段） */
function submitOrder() {
  if (!body.price) return showToast('请设置价格')
  const data = {
    iid: body.iid,
    unitPrice: 100 * body.price,
    quantity: body.quantity,
  }
  showLoading()
  orderApi.createSecondaryTrustOrder(data).then((res) => {
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
/* 原站 trust-Bm0L1JGU.css，scopeId data-v-36309662，逐条 1:1 还原 */
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
.quantity-label {
  font-size: 14px;
  color: #000;
  /* 原站文本元素 line-height normal（14/15px → h 17/18px），本地 Noto 会撑到 20/21px，锁定为 1.2 */
  /* 原站文本元素 line-height 为 normal；本地已统一为浏览器默认字体，
     行为与原站一致，故不再需要 line-height 补偿 */
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
  /* 原站文本元素 line-height normal（14/15px → h 17/18px），本地 Noto 会撑到 20/21px，锁定为 1.2 */
  /* 原站文本元素 line-height 为 normal；本地已统一为浏览器默认字体，
     行为与原站一致，故不再需要 line-height 补偿 */
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
  /* 原站 h5 走全局重置：0.8rem / 700 / margin 0 / line-height normal */
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
/* 原站 uni-app <button> 默认 line-height:2.5555556 */
  line-height: 2.5555556;
}
</style>
