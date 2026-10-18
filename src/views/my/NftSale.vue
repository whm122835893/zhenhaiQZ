<template>
  <div class="page-root">
    <UniPageHead title="寄售" />
    <div class="container">
      <div class="content-area">
      <!-- 藏品信息（原站 uni-image mode="aspectFill" 82×82） -->
      <div class="nft-info">
        <!-- 原站 uni-image 空 src 时仍占 82x82；本地保留元素以维持布局，
             并用 empty 态遮蔽 <img> 空 src 的破图图标 -->
        <!-- 原站 uni-image 空 src 时仍占 82x82 且不绘制内容；
             本地用 <div> 占位替代 <img>，避免空 src 破图图标 -->
        <div v-if="!nft.imageUrl" class="nft-image nft-image--empty" />
        <img v-else :src="nft.imageUrl" class="nft-image" alt="" />
        <div class="nft-title">
          {{ nft.name }}
          <span class="nft-id">#{{ nft.token }}</span>
        </div>
      </div>

      <!-- 收款账号 -->
      <div class="input-section">
        <div class="section-title">收款账号</div>
        <div class="price-input price-input--account">
          <div> 汇付钱包 </div>
          <div>
            <img :src="checkedIcon" class="checked-icon" alt="" />
          </div>
        </div>
      </div>

      <!-- 寄售价格（原站 inline: margin-bottom:0; padding-bottom:0） -->
      <div class="input-section input-section--price">
        <div class="section-title">寄售价格</div>
        <div class="price-input price-input--dashed">
          <span class="currency-symbol">¥</span>
          <input
            v-model="body.price"
            class="uni-input-input"
            type="number"
            step="0.000000000000000001"
            placeholder="请输入价格"
          />
        </div>
      </div>

      <!-- 收益明细（原站 inline: margin-top:12rpx; padding-top:0） -->
      <div class="income-section income-section--top">
        <div class="income-item income-item--fee">
          <span>综合服务费(6%)</span>
          <span>-¥{{ serviceAmountFormat }}</span>
        </div>
        <div class="income-item income-item--income">
          <span>预计收益</span>
          <span>¥{{ incomeAmountFormat }}</span>
        </div>
      </div>

      <div class="bottom-space"></div>
    </div>

    <!-- 底部提交栏 -->
    <div class="fixed-bottom">
      <div class="submit-btn" @click="confirmSaleShow">确认寄售</div>
    </div>

  </div>
  </div>

    <!-- 确认价格弹窗（原站 uv-popup round=10） -->
    <van-popup
      v-model:show="pricePopup"
      round="10"
      :style="{ zIndex: 999 }"
      :overlay-opacity="0.7"
      @change="onPopupChange"
    >
      <div class="confirm-box">
        <div class="confirm-price"> ¥ {{ body.price }}</div>
        <div class="confirm-tip"> 您确认以{{ body.price }}元出售吗？ </div>
        <div class="confirm-btns">
          <div class="confirm-btn confirm-btn--cancel" @click="pricePopup = false"> 取消 </div>
          <div class="confirm-btn confirm-btn--ok" @click="showPasswordDialog"> 确定 </div>
        </div>
      </div>
    </van-popup>

    <!-- 操作密码弹窗（原站 uv-popup round=10 + uv-code-input size=62rpx） -->
    <van-popup
      v-model:show="pwdPopup"
      round="10"
      :style="{ zIndex: 1000 }"
      :overlay-opacity="0.7"
      closeable
      @change="onPopupChange"
    >
      <div class="pwd-box">
        <div class="pwd-title"> 请输入操作密码 </div>
        <div class="pwd-amount"> ¥{{ body.price }}</div>
        <div class="pwd-inputs">
          <div
            v-for="i in 6"
            :key="i"
            class="pwd-cell"
            :class="{ 'pwd-cell--active': pwdFocus === i }"
          >
            {{ pwdChars[i - 1] ? '●' : '' }}
          </div>
          <input
            class="pwd-hidden-input"
            type="number"
            maxlength="6"
            inputmode="numeric"
            :value="pwdChars.join('')"
            @focus="pwdFocus = pwdChars.filter(Boolean).length + 1"
            @input="onPwdInput"
          />
        </div>
      </div>
    </van-popup>

</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { nftApi } from '@/api'
import { encryptPassword } from '@/utils/crypto'
import { showLoading, hideLoading } from '@/api/request'
import checkedIcon from '@/assets/my/checked-icon.png'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

const nftId = (route.query && route.query.nftId) || ''
const body = reactive({ nftId, price: '' })
const nft = ref({})
const pricePopup = ref(false)
const pwdPopup = ref(false)
const pwdChars = ref(['', '', '', '', '', ''])
const pwdFocus = ref(0)

/** 原站 incomeAmountFormat：0.94 × price（6% 综合服务费，与 NFT 详情页快捷寄售的 5% 不同） */
const incomeAmountFormat = computed(() => {
  const p = Number(body.price)
  return p ? Number(0.94 * p).toFixed(2) : '0.00'
})
/** 原站 serviceAmountFormat：price − 0.94 × price */
const serviceAmountFormat = computed(() => {
  const p = Number(body.price)
  return p ? (p - Number(0.94 * p)).toFixed(2) : '0.00'
})

function getNft() {
  if (!body.nftId) return router.replace('/pages/my/index')
  nftApi.getNft(body.nftId).then((res) => {
    if (res.status) nft.value = res.data
    else router.replace('/pages/my/index')
  })
}

/** 原站 confirmSaleShow：无任何前端价格校验（含空值/负数/超区间均直接进确认+拼图验证） */
function confirmSaleShow() {
  pricePopup.value = true
}

/** 原站 showPasswordDialog：关闭价格弹窗 → 打开密码弹窗
 *  原站两个 uv-popup 为串行切换，本地必须等价格弹窗关闭完成再开密码弹窗，
 *  否则两个弹窗会同时处于打开状态、内容叠加且无遮罩 */
function showPasswordDialog() {
  pricePopup.value = false
  pwdChars.value = ['', '', '', '', '', '']
  // 等待价格弹窗 150ms 关闭动画结束再打开密码弹窗，与原站串行行为一致
  setTimeout(() => {
    pwdPopup.value = true
  }, 150)
}

/** 原站 uv-code-input onChange + 满 6 位 onFinish 自动提交 */
function onPwdInput(e) {
  const v = String(e.target.value || '').replace(/\D/g, '').slice(0, 6)
  e.target.value = v
  const arr = v.split('')
  while (arr.length < 6) arr.push('')
  pwdChars.value = arr
  pwdFocus.value = v.length + 1
  if (v.length === 6) submitWithPassword()
}

/** 原站 submitWithPassword：POST /nft/sell/up { payWay:1, nftId, price×100, secondPassword } */
function submitWithPassword() {
  const data = {
    payWay: 1,
    nftId: body.nftId,
    price: 100 * body.price,
    secondPassword: encryptPassword(pwdChars.value.join('')),
  }
  showLoading()
  nftApi.sellUp(data).then((res) => {
    hideLoading()
    if (res.status) {
      pwdPopup.value = false
      // 原站成功回调为 navigateBack()
      router.back()
    } else {
      pwdChars.value = ['', '', '', '', '', '']
    }
  })
}

/** 原站 uv-popup change 竞态处理：延迟 150ms 关闭，避免连续开关时状态错乱 */
let popupTimer = null
function onPopupChange(show) {
  if (show) {
    if (popupTimer) clearTimeout(popupTimer)
    return
  }
  if (popupTimer) clearTimeout(popupTimer)
  popupTimer = setTimeout(() => {
    pwdFocus.value = 0
  }, 150)
}

getNft()
</script>

<style scoped>
/* 原站本页 scopeId data-v-cfcf0e7b，逐条 1:1 还原 sale-KtZ-cJcA.css
   注意：.section-title 原站带 font-family:PingFang */
/* 原站本页继承 uni-app 全局 "Times New Roman"（本页 CSS 未声明 font-family）；
   颜色同样继承 body 的初始黑（#000），未声明处不得回落成 #333。
   原站 body/uni-page 均为透明、整页纯白（uni-app 默认），本页顶层需显式给白底 */
.page-root,
.container {
  font-family: 'Times New Roman';
  color: #000;
}
.page-root {
  background-color: #fff;
  min-height: 100vh;
}
.container {
  /* 原站未声明 box-sizing（content-box）：min-height:100vh 为内容高，
     叠加 padding-bottom 62.4px 后实际总高 906.4px */
  box-sizing: content-box;
  min-height: 100vh;
  position: relative;
  padding-bottom: 3.75rem;
}
.content-area {
  padding: 0.9375rem;
}
/* 藏品信息卡 */
.nft-info {
  overflow: hidden;
  margin-bottom: 0.9375rem;
  display: flex;
  align-items: center;
  padding: 0.9375rem;
}
.nft-image--empty {
  /* 空态：仅占位，保持 82x82 盒模型（原站 uni-image 无 src 时行为） */
  flex-shrink: 0;
}
.nft-image {
  width: 82px;
  height: 82px;
  display: block;
  border-radius: 8px;
  flex-shrink: 0;
}
.nft-title {
  padding: 0.75rem;
  font-size: 15px;
  font-weight: 700;
}
.nft-id {
  color: #999;
  font-size: 0.875rem;
  margin-left: 0.3125rem;
}
/* 通用卡片 */
.input-section,
.income-section,
.notice-section {
  background-color: #fff;
  border-radius: 0.5rem;
  padding: 0.9375rem;
  margin-bottom: 0.625rem;
}
.section-title {
  font-family: PingFang, sans-serif;
  font-size: 15px;
  font-weight: 500;
  color: #333;
  margin-bottom: 0.625rem;
}
.price-input {
  /* 原站未声明 box-sizing（content-box），1px 上下边框不计入高度：content 52 + 2 = 54 */
  box-sizing: content-box;
  display: flex;
  align-items: center;
  border: 1px solid #e6e6e6;
  border-radius: 8px;
  padding: 0 0.625rem;
  height: 3.125rem;
}
/* 收款账号行：原站 inline color:#333 font-size:15px justify-content:space-between */
.price-input--account {
  color: #333;
  font-size: 15px;
  justify-content: space-between;
}
.checked-icon {
  width: 20px;
  height: 20px;
  display: block;
}
/* 原站寄售价格卡 inline margin-bottom:0; padding-bottom:0 */
.input-section--price {
  margin-bottom: 0;
  padding-bottom: 0;
}
/* 价格输入行：原站 inline border:none; border-radius:0; border-bottom:1px dashed #999999 */
.price-input--dashed {
  border: none;
  border-radius: 0;
  border-bottom: 1px dashed #999999;
}
.currency-symbol {
  color: #333;
  margin-right: 0.625rem;
  font-size: 17px;
}
.uni-input-input {
  flex: 1;
  height: 3.125rem;
  font-size: 15px;
  border: none;
  outline: none;
  background: transparent;
  padding: 0;
  min-width: 0;
}
.uni-input-input::placeholder {
  color: #707070;
  font-size: 15px;
}
/* 收益明细 */
/* 原站 inline margin-top:12rpx; padding-top:0，margin-bottom 沿用 .625rem */
.income-section--top {
  margin-top: 0.375rem;
  padding-top: 0;
  margin-bottom: 0.625rem;
}
.income-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #666;
}
.income-item--fee {
  color: #ababab;
  font-size: 13px;
}
.income-item--income {
  margin-top: 0;
  padding-top: 0;
  color: #333;
  font-size: 13px;
}
.notice-list {
  font-size: 0.8125rem;
  color: #666;
}
.notice-item {
  line-height: 1.6;
  margin-bottom: 0.5rem;
}
.bottom-space {
  height: 1.25rem;
}
/* 底部提交 */
.fixed-bottom {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: #fff;
  padding: 0.625rem 0.9375rem;
  box-shadow: 0 -0.0625rem 0.3125rem rgba(0, 0, 0, 0.05);
  z-index: 100;
}
.submit-btn {
  background-color: #2b52ef;
  color: #fff;
  text-align: center;
  height: 2.8125rem;
  line-height: 2.8125rem;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
}
/* 确认价格弹窗（原站 inline width:500rpx padding:30rpx font-family:PingFang） */
.confirm-box {
  width: 15.625rem;
  padding: 0.9375rem;
  text-align: center;
  font-family: PingFang, sans-serif;
}
.confirm-price {
  margin-top: 1.875rem;
  font-size: 22px;
  font-weight: 700;
  color: #333;
}
.confirm-tip {
  margin-top: 2.1875rem;
}
.confirm-btns {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 3.75rem;
}
.confirm-btn {
  height: 44px;
  width: 121px;
  border-radius: 22px;
  font-weight: 600;
  display: flex;
  justify-content: center;
  align-items: center;
}
.confirm-btn--cancel {
  background: #f1f0f0;
  color: #000;
}
.confirm-btn--ok {
  background: #2b52ef;
  color: #fff;
}
/* 密码弹窗 */
.pwd-box {
  width: 15.625rem;
  padding: 0.9375rem;
  text-align: center;
  font-family: PingFang, sans-serif;
}
.pwd-title {
  margin-top: 1.875rem;
  font-size: 17px;
  font-weight: 700;
  color: #333;
}
.pwd-amount {
  margin-top: 0.9375rem;
  font-size: 26px;
  font-weight: 500;
}
.pwd-inputs {
  margin-top: 2.5rem;
  margin-bottom: 1.875rem;
  display: flex;
  position: relative;
}
.pwd-cell {
  flex: 1;
  height: 1.9375rem;
  border: 0.03125rem solid #ddd;
  margin: 0 0.15625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}
.pwd-cell:first-child {
  margin-left: 0;
}
.pwd-cell:last-child {
  margin-right: 0;
}
.pwd-cell--active {
  border-color: #2b52ef;
}
.pwd-hidden-input {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  z-index: 10;
  border: none;
  outline: none;
}
</style>
