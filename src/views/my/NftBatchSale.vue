<template>
  <div class="page-root">
    <UniPageHead title="批量寄售" />
    <div class="container">
      <div class="content-area">
      <!-- 藏品信息（原站 uni-image mode="aspectFill" 82×82，批量页无 #编号） -->
      <div class="nft-info">
        <!-- 原站 uni-image 空 src 时仍占 82x82；本地保留元素以维持布局，
             并用 empty 态遮蔽 <img> 空 src 的破图图标 -->
        <!-- 原站 uni-image 空 src 时仍占 82x82 且不绘制内容；
             本地用 <div> 占位替代 <img>，避免空 src 破图图标 -->
        <div v-if="!item.imageUrl" class="nft-image nft-image--empty" />
        <img v-else :src="item.imageUrl" class="nft-image" alt="" />
        <div class="nft-title">{{ item.name }}</div>
      </div>

      <!-- 最低价格 -->
      <div class="input-section">
        <div class="section-title">最低价格</div>
        <div class="price-input price-input--dashed">
          <span class="currency-symbol">¥</span>
          <input
            v-model="body.minPrice"
            class="uni-input-input"
            type="number"
            step="0.000000000000000001"
            placeholder="请输入价格"
          />
        </div>
      </div>

      <!-- 最高价格 -->
      <div class="input-section">
        <div class="section-title">最高价格</div>
        <div class="price-input price-input--dashed">
          <span class="currency-symbol">¥</span>
          <input
            v-model="body.maxPrice"
            class="uni-input-input"
            type="number"
            step="0.000000000000000001"
            placeholder="请输入价格"
          />
        </div>
      </div>

      <!-- 数量（原站无 ¥ 符号） -->
      <div class="input-section">
        <div class="section-title">数量</div>
        <div class="price-input price-input--dashed">
          <input
            v-model="body.quantity"
            class="uni-input-input"
            type="number"
            step="0.000000000000000001"
            placeholder="请输入数量"
          />
        </div>
      </div>

      <!-- 寄售须知 -->
      <div class="notice-section">
        <div class="section-title">寄售须知</div>
        <div class="notice-list">
          <div class="notice-item">1. 商品未经购买前，可取消寄售。一经购买，无法取消寄售</div>
          <div class="notice-item">2. 您将获得除综合服务费6%之外的所有售卖收入。</div>
          <div class="notice-item">
            3. 商品出售成功后，售卖收入将默认进入钱包余额，钱包所有功能由第三方支付公司提供服务。
          </div>
        </div>
      </div>

      <div class="bottom-space"></div>
    </div>

    <!-- 底部提交栏 -->
    <div class="fixed-bottom">
      <div class="submit-btn" @click="showPasswordDialog">确认寄售</div>
    </div>

    <!-- 交易密码弹窗（原站自绘 password-dialog，非组件库弹窗） -->
    <div v-if="showDialog" class="password-dialog">
      <div class="dialog-mask" @click="closeDialog"></div>
      <div
        class="dialog-content"
        :style="{ transform: `translateY(-${keyboardHeight > 0 ? keyboardHeight / 3 : 0}px)` }"
      >
        <div class="dialog-title">请输入交易密码</div>
        <div class="password-input-box">
          <div v-for="i in 6" :key="i" class="password-item">
            <span v-if="payPassword.length > i - 1">*</span>
          </div>
          <input
            type="number"
            maxlength="6"
            focus
            class="password-hidden-input"
            :value="payPassword"
            @input="onPasswordInput"
          />
        </div>
        <div class="dialog-tips">请输入6位数字交易密码</div>
        <div class="dialog-close" @click="closeDialog">取消</div>
      </div>
    </div>
  </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { itemApi, nftApi } from '@/api'
import { encryptPassword } from '@/utils/crypto'
import { showLoading, hideLoading } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

const iid = (route.query && route.query.iid) || ''
const body = reactive({ iid, minPrice: '', maxPrice: '', quantity: '' })
const item = ref({})
const showDialog = ref(false)
const payPassword = ref('')
const keyboardHeight = ref(0)

function getItem() {
  if (!body.iid) return router.replace('/pages/my/index')
  itemApi.get(body.iid).then((res) => {
    if (res.status) item.value = res.data
    else router.replace('/pages/my/index')
  })
}

/** 原站 showPasswordDialog：校验价格区间与数量 → 打开自绘密码弹窗 */
function showPasswordDialog() {
  if (!body.minPrice || Number(body.minPrice) <= 0 || !body.maxPrice || Number(body.maxPrice) <= 0) {
    showToast('请输入有效价格')
    return
  }
  if (!body.quantity || Number(body.quantity) <= 0) {
    showToast('请输入有效数量')
    return
  }
  showDialog.value = true
  payPassword.value = ''
}

function closeDialog() {
  showDialog.value = false
  payPassword.value = ''
}

/** 原站 onPasswordInput：满 6 位自动提交 */
function onPasswordInput(e) {
  const v = String(e.target.value || '').replace(/\D/g, '').slice(0, 6)
  e.target.value = v
  payPassword.value = v
  if (v.length === 6) submitWithPassword()
}

/** 原站 submitWithPassword：POST /nft/sell/up/batch */
function submitWithPassword() {
  const data = {
    payWay: 1,
    iid: body.iid,
    minPrice: 100 * body.minPrice,
    maxPrice: 100 * body.maxPrice,
    quantity: body.quantity,
    secondPassword: encryptPassword(payPassword.value),
  }
  showLoading()
  nftApi.sellUpBatch(data).then((res) => {
    hideLoading()
    closeDialog()
    if (res.status) router.back()
  })
}

/** 原站 onKeyboardHeightChange：键盘弹起时弹窗上移（此处用 visualViewport 近似） */
function onViewportResize() {
  keyboardHeight.value = window.innerHeight - (window.visualViewport?.height || window.innerHeight)
}
onMounted(() => window.addEventListener('resize', onViewportResize))
onBeforeUnmount(() => window.removeEventListener('resize', onViewportResize))

getItem()
</script>

<style scoped>
/* 原站本页 scopeId data-v-d34231c0，逐条 1:1 还原 batchSale-aCagpSD4.css
   注意：本页 .section-title 无 font-family:（与单件出售页不同） */
/* 原站本页继承 uni-app 全局 "Times New Roman"（本页 CSS 未声明 font-family） */
.page-root,
.container {
  font-family: 'Times New Roman';
  /* 原站 uni-view 继承纯黑 rgb(0,0,0)，本地根回落 #333 */
  color: #000;
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
/* 通用卡片 */
.input-section,
.income-section,
.notice-section {
  background-color: #fff;
  border-radius: 0.5rem;
  padding: 0.9375rem;
  /* 原站 CSS 写 .625rem，但实测相邻卡间距为 132.1 = 116.6 + 15.5，按实测锁定 0.9375rem */
  margin-bottom: 0.9375rem;
}
.section-title {
  /* 原站 batchSale-aCagpSD4.css 未声明 font-family（与单件出售页不同） */
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
/* 原站 inline border:none; border-radius:0; border-bottom:1px dashed #999999 */
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
.income-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 0.875rem;
  color: #666;
}
/* 寄售须知 */
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
/* 自绘交易密码弹窗 */
.password-dialog {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dialog-mask {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
}
.dialog-content {
  position: relative;
  width: 80%;
  background-color: #fff;
  border-radius: 0.75rem;
  padding: 1.25rem 0.9375rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 1000;
  transition: transform 0.2s ease;
}
.dialog-title {
  font-size: 1rem;
  font-weight: 500;
  margin-bottom: 1.25rem;
}
.password-input-box {
  display: flex;
  width: 100%;
  height: 3.125rem;
  margin-bottom: 0.9375rem;
  position: relative;
}
.password-item {
  flex: 1;
  height: 3.125rem;
  border: 0.03125rem solid #ddd;
  margin: 0 0.3125rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}
.password-item:first-child {
  margin-left: 0;
}
.password-item:last-child {
  margin-right: 0;
}
.password-hidden-input {
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
.dialog-tips {
  font-size: 0.75rem;
  color: #999;
  margin-bottom: 1.25rem;
}
.dialog-close {
  width: 100%;
  height: 2.5rem;
  line-height: 2.5rem;
  text-align: center;
  font-size: 0.9375rem;
  color: #666;
  border-top: 0.03125rem solid #eee;
  margin-top: 0.625rem;
}
</style>
