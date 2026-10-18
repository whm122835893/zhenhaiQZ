<!--
  批量购买页 —— 复刻原站 /pages/market/batch
  源文件：assets/pages-market-batch.Hz3MJhXj.js + assets/batch-D-bIdYe3.css
  scopeId: data-v-2ee84950
  实测基准：视口 390x844，1rem = 100vw/23.4375 = 16.64px

  原站逻辑（pages-market-batch.Hz3MJhXj.js）：
    data: { iid, item, price, quantity:10 }
    onLoad(q): 无 iid → redirectTo /pages/index/index；有则 getItem()
    getItem()  : itemApi.getItem(iid) → status 时写入 this.item
    computed totalPrice = price * quantity
    decreaseQuantity(): quantity > 1 才减
    increaseQuantity(): 直接加
    validateQuantity() : blur 时 parseInt，NaN 或 <1 → 置 1
    submitOrder():
      if (!price) return showToast('请设置价格')
      POST order/secondary/batch { iid, unitPrice:100*price, quantity, payWay:1 }
      status → navigateTo /pages/my/order/info?orderId=<data>
      code 11111 → navigateTo /pages/authorize/signin
-->
<template>
  <!-- 原站系统导航栏：标题「批量购买」，高 44px -->
  <UniPageHead title="批量购买" />

  <div class="container">
    <!-- 藏品卡 -->
    <div class="product-card">
      <div class="product-info">
        <!-- 原站 uni-image：src 为空时仍占 4.6875rem 见方，此处用 div 占位避免破图 -->
        <div v-if="!item.imageUrl" class="product-image" />
        <img v-else class="product-image" :src="item.imageUrl" alt="" />
        <div class="product-details">
          <div class="product-name">{{ item.name }}</div>
        </div>
      </div>
    </div>

    <!-- 设置价格 + 购买数量 -->
    <div class="commission-section">
      <div class="section-title">
        <div>设置价格</div>
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
    </div>

    <!-- 底部固定：总计 + 立即下单 -->
    <div class="footer">
      <div class="total-price">
        <div>总计:</div>
        <div class="price">{{ totalPrice }}</div>
      </div>
      <button class="submit-btn" @click="submitOrder">立即下单</button>
    </div>
  </div>
</template>

<script setup>
/**
 * 批量购买 —— 复刻原站 pages-market-batch.Hz3MJhXj.js
 * CSS 逐条对齐原站 batch-D-bIdYe3.css（scopeId data-v-2ee84950）
 */
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { itemApi, orderApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'
import helpIcon from '@/assets/market/help-icon.png'

const route = useRoute()
const router = useRouter()

const item = ref({})
const body = reactive({ iid: '', price: '', quantity: 10 })

/** 原站 computed：totalPrice = price * quantity */
const totalPrice = computed(() => body.price * body.quantity)

/** 原站 getItem：itemApi.getItem(iid) */
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

/** 原站 validateQuantity：blur 时校正非法值 */
function validateQuantity() {
  const q = parseInt(body.quantity)
  if (isNaN(q) || q < 1) body.quantity = 1
  else body.quantity = q
}

/** 原站 submitOrder：POST /order/secondary/batch */
function submitOrder() {
  if (!body.price) return showToast('请设置价格')
  const data = {
    iid: body.iid,
    unitPrice: 100 * body.price,
    quantity: body.quantity,
    payWay: 1,
  }
  showLoading()
  orderApi.createSecondaryBatchOrder(data).then((res) => {
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
/* 原站 batch-D-bIdYe3.css，scopeId data-v-2ee84950，逐条 1:1 还原。
   注意：.container 原站未声明 box-sizing（content-box），
   padding 16px 叠加在 min-height:100vh 之外。 */
.container {
  /* 原站未声明 box-sizing（content-box）：padding 16px 叠加在 min-height:100vh 之外 */
  box-sizing: content-box;
  padding: 16px;
  background-color: #f5f5f5;
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
  width: 4.6875rem; /* 78px */
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
  font-size: 1rem;
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
  font-size: 15px;
  font-weight: 700;
  color: #000;
  gap: 5px;
}
/* 原站 .section-title uni-image{width:12px;height:12px} */
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
  font-size: 14px;
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
  background-color: #eee;
  margin: 0.3125rem 0;
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
  border: 1px solid #919191;
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
  background-color: #4478ff;
  color: #fff;
  border-radius: 1.5625rem;
  font-size: 1rem;
  border: none;
  box-sizing: border-box;
/* 原站 uni-app <button> 默认 line-height:2.5555556（实测 batch 页 16.64px → 42.5244px） */
  line-height: 2.5555556;
}
</style>
