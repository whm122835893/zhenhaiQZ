<template>
  <div class="nft-info-container">
    <!-- ============ Hero 3D 区 ============ -->
    <div class="hero">
      <div class="nav-bar">
        <div class="nav-back" @click="navBack">
          <img :src="backIcon" class="back-icon" alt="" />
        </div>
      </div>

      <!-- 3D 模型查看器（原站 nb-model-viewer，模型 /static/model3d/model.gltf） -->
      <div class="art3d-wrap">
        <div class="nbmv">
          <iframe
            class="nbmv__frame"
            src="/static/model3d/index.html?modelurl=%2Fstatic%2Fmodel3d%2Fmodel.gltf&applytexture=1&rot=1&delay=500&speed=200%25&orbit=0deg%2090deg%20105%25&autofront=1&touch=1"
            frameborder="0"
            allowfullscreen
          ></iframe>
          <div class="nbmv__mask">
            <span class="nbmv__mask-text">加载中 0%</span>
          </div>
        </div>
      </div>

      <div class="stage">
        <div class="stage-inner">
          <div class="stage-name">
            <img :src="stageLaurel" class="laurel laurel--l" alt="" />
            <div class="stage-name-col">
              <!-- 原站为 uni-view（非 uni-text），绑定 nft.name 顶层字段；此处回退 item.name 兜底 -->
              <div class="name-text">{{ nft.name || nft.item.name }}</div>
              <div class="stage-badge">
                <span class="badge-tag">限量</span>
                <span class="badge-num">{{ nft.item.issueQuantity }}份</span>
              </div>
            </div>
            <img :src="stageLaurel" class="laurel laurel--r" alt="" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============ 内容区 ============ -->
    <div class="content">
      <div class="ids-cert-box">
        <div class="ids-box">
          <span class="ids-key">#{{ nft.token }}/{{ nft.item.issueQuantity }}</span>
          <span class="ids-val">产品编号</span>
        </div>
        <div class="cert-box">
          <div class="cert-star"></div>
          <span class="cert-key">臻海艺术 数字藏品</span>
          <span class="cert-val">数字资产存证证书 &gt;&gt;</span>
        </div>
      </div>

      <div class="pro-info-box">
        <div class="brand-row">
          <div class="brand-left">
            <img :src="logoImg" class="brand-logo" alt="" />
            <span class="brand-name">臻海艺术</span>
          </div>
          <span class="brand-action">品牌方 &gt;</span>
        </div>

        <div class="key-val-row">
          <span class="kv-key">藏品哈希值</span>
          <span class="kv-val-wrap">
            <span class="kv-val">{{ fmtChainFormat(nft.chainId) }}</span>
            <span class="iconfont icon-copy kv-copy" @click.stop="copy(nft.chainId)"></span>
          </span>
        </div>

        <div class="key-val-row">
          <span class="kv-key">藏品持有者</span>
        </div>

        <div class="key-val-row">
          <span class="kv-key">品牌方</span>
          <span class="kv-val-wrap">
            <img :src="logoImg" class="kv-logo" alt="" />
            <span class="kv-val">臻海艺术</span>
          </span>
        </div>

        <div class="key-val-row">
          <span class="kv-key">创作者</span>
          <span class="kv-val-wrap">
            <img :src="logoImg" class="kv-logo" alt="" />
            <span class="kv-val">臻海艺术</span>
          </span>
        </div>
      </div>

      <div class="intro-box">
        <div class="tab-list">
          <div
            class="tab-item"
            :class="activeTab === 'intro' ? 'sel' : 'unsel'"
            @click="activeTab = 'intro'"
          >
            作品介绍
          </div>
          <div
            class="tab-item"
            :class="activeTab === 'notice' ? 'sel' : 'unsel'"
            @click="activeTab = 'notice'"
          >
            相关公告
          </div>
        </div>

        <div v-if="activeTab === 'intro'" class="intro-body">
          <div class="user-row">
            <div class="user-left">
              <img :src="logoImg" class="user-logo" alt="" />
              <span class="user-name">臻海艺术</span>
            </div>
            <span class="user-role">品牌方/创作者</span>
          </div>
          <!-- 原站用 rich-text 渲染 item.content，其中 <img> 强制 width:100% -->
          <div class="intro-desc" v-html="nft.item.content"></div>
          <!-- 原站为 uni-image mode="widthFix"，src 为空时 uni-app 仍占位 240px 高，故不设 v-if -->
          <!-- 原站 uni-image mode="widthFix"：src 为空时仍占 240px 高，但不绘制任何内容。
               本地改用 div 占位，避免 <img> 空 src 渲染破图图标 -->
          <div v-if="!nft.imageUrl" class="intro-img intro-img--empty" />
          <img v-else :src="nft.imageUrl" class="intro-img" alt="" />

          <div class="kv-row">
            <span class="kv-key">藏品编号</span>
            <span class="kv-val">#{{ nft.token }}</span>
          </div>
          <div class="kv-row">
            <span class="kv-key">发行数量</span>
            <span class="kv-val">{{ nft.item.issueQuantity }}份</span>
          </div>
        </div>

        <div v-else class="intro-body notice-body">
          <div class="empty-notice">暂无相关公告</div>
        </div>
      </div>
    </div>

    <!-- ============ 底部操作栏 ============ -->
    <div class="bottom-action-bar">
      <div v-if="nft.status === 1" class="action-button cancel-button" @click="cancelSale">下架</div>
      <div
        v-else-if="nft.status === 0 && nft.item.type === 1"
        class="action-button sale-button"
        @click="openSalePopup"
      >
        立即寄售
      </div>
      <div v-else-if="nft.status === 0 && nft.item.type === 2" class="bottom-buttons">
        <div class="action-button cancel-button" style="margin-right: 0.625rem" @click="openBox">开启</div>
        <div class="action-button sale-button" @click="openSalePopup">寄售</div>
      </div>
    </div>

    <!-- ============ 设置背景图确认 ============ -->
    <div v-if="showSetBgPopup" class="set-bg-popup">
      <div class="popup-content">
        <div class="popup-title">是否设置为个人页面背景图?</div>
        <div class="popup-buttons">
          <div class="popup-button cancel-button" @click="showSetBgPopup = false">取消</div>
          <div class="popup-button confirm-button" @click="confirmSetBg">确认</div>
        </div>
      </div>
    </div>

    <!-- ============ 盲盒结果 ============ -->
    <div v-if="showBoxResult" class="box-result-popup">
      <div class="result-content">
        <div class="result-header">
          <span class="result-title">恭喜开出</span>
        </div>
        <div class="result-body">
          <img :src="boxResultData.imageUrl" class="result-image" alt="" />
          <span class="result-name">{{ boxResultData.name }}</span>
          <span class="result-info">#{{ boxResultData.token }}</span>
        </div>
        <div class="result-footer">
          <div class="result-button" @click="goBack">确定</div>
        </div>
      </div>
    </div>

  </div>

  <!-- ============ 寄售价格弹窗 ============ -->
  <van-popup
    v-model:show="salePopup"
    round="16"
    :style="{ zIndex: 999 }"
    :overlay-opacity="0.7"
    teleport="body"
    @change="onPopupChange"
  >
    <div class="sale-popup-box">
      <div class="sale-popup-title">请设置寄售价格</div>
      <div class="sale-hold-row">
        <span class="sale-hold-label">当前持有数量(可寄售):</span>
        <span class="sale-hold-val">1</span>
      </div>
      <div class="sale-price-label">请输入寄售价格:</div>
      <div class="sale-price-input-box">
        <span class="sale-price-symbol">¥</span>
        <input v-model="saleForm.price" class="sale-price-input" type="digit" placeholder="请输入价格" />
      </div>
      <div class="sale-fee-tip">
        综合服务费(5%): -¥{{ saleServiceAmount }}，
        <span>预计收益 <b class="sale-fee-income">¥{{ saleIncomeAmount }}</b></span>
      </div>
      <div class="sale-popup-btns">
        <div class="sale-btn sale-btn-cancel" @click="salePopup = false">取消</div>
        <div class="sale-btn sale-btn-confirm" @click="confirmSalePrice">确定</div>
      </div>
    </div>
  </van-popup>

  <!-- ============ 操作密码弹窗 ============ -->
  <van-popup
    v-model:show="pwdPopup"
    round="10"
    :style="{ zIndex: 999 }"
    :overlay-opacity="0.7"
    teleport="body"
    closeable
    @change="onPopupChange"
  >
    <div class="pwd-box">
      <div class="pwd-title">请输入操作密码</div>
      <div class="pwd-amount">¥{{ saleForm.price }}</div>
      <div class="pwd-inputs">
        <input
          v-for="i in 6"
          :key="i"
          class="pwd-cell"
          type="tel"
          inputmode="numeric"
          maxlength="1"
          :value="pwdChars[i - 1]"
          @input="onPwdInput($event, i - 1)"
        />
      </div>
    </div>
  </van-popup>
</template>

<script setup>
import { ref, reactive, computed, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { nftApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import { chainFormat as fmtChainFormat } from '@/utils/fmt'
import { storage, BG_IMG_KEY } from '@/utils/storage'
import { encryptPassword } from '@/utils/crypto'
import backIcon from '@/assets/my/back-icon.png'
import logoImg from '@/assets/my/logo.png'
import stageLaurel from '@/assets/my/stage-name-0.png'

defineOptions({ name: 'NftInfo' })

const route = useRoute()
const router = useRouter()

const nftId = ref(String(route.query.nftId || ''))
const nft = ref({ item: {} })
const activeTab = ref('intro')
const showSetBgPopup = ref(false)
const showBoxResult = ref(false)
const boxResultData = reactive({ imageUrl: '', name: '', token: '', total: '', nftId: '' })
const saleForm = reactive({ price: '' })
const salePopup = ref(false)
const pwdPopup = ref(false)
const pwdChars = ref(['', '', '', '', '', ''])
const popupOpen = ref(false)

/* ---------------- 数据 ---------------- */
function getNft() {
if (!nftId.value) return goBack()
nftApi.getNft(nftId.value).then((res) => {
  if (!res.status) return goBack()
  nft.value = res.data
  // 原站：item.content 中的 <img> 强制 width:100%
  if (nft.value.item.content) {
    nft.value.item.content = nft.value.item.content.replace(/<img/g, "<img width='100%'")
  }
})
}

/* ---------------- 交互 ---------------- */
function navBack() {
// 原站：getCurrentPages().length > 1 ? navigateBack() : goBack()
if (window.history.length > 1) router.back()
else goBack()
}
function goBack() {
router.replace('/pages/my/index')
}
function copy(text) {
if (navigator.clipboard) {
  navigator.clipboard.writeText(text)
  showToast('复制成功')
} else {
  showToast('长按复制')
}
}
function confirmSetBg() {
storage.set(BG_IMG_KEY, nft.value.item.imageUrl)
showToast('设置成功')
showSetBgPopup.value = false
}

/** 下架（status=1 寄售中） */
function cancelSale() {
if (!window.confirm('确定要下架吗？')) return
showLoading()
nftApi.sellDownPut({ nftId: nft.value.nftId }).then((res) => {
  hideLoading()
  if (res.status) router.back()
})
}

/** 立即寄售：先开价格弹窗 */
function openSalePopup() {
saleForm.price = ''
salePopup.value = true
}
const saleServiceAmount = computed(() => {
const p = Number(saleForm.price)
return p > 0 ? (p - Number(0.95 * p)).toFixed(2) : '0.00'
})
const saleIncomeAmount = computed(() => {
const p = Number(saleForm.price)
return p > 0 ? Number(0.95 * p).toFixed(2) : '0.00'
})
function confirmSalePrice() {
const p = Number(saleForm.price)
if (!p || p <= 0) return showToast('请输入有效价格')
salePopup.value = false
pwdChars.value = ['', '', '', '', '', '']
pwdPopup.value = true
}

/* 原站 onPopupChange：50ms 内的关闭视为「未真正打开」，用于延迟关闭价格弹窗 */
let popupTimer = null
function onPopupChange(e) {
if (e.show) {
  clearTimeout(popupTimer)
  popupOpen.value = true
  return
}
clearTimeout(popupTimer)
const now = Date.now()
popupTimer = setTimeout(() => {
  if (popupOpen.value && popupOpen.value >= now - 50) return
  popupOpen.value = false
}, 150)
}

/* 6 位密码填满自动提交（原站 uv-code-input onFinish） */
function onPwdInput(e, idx) {
const v = (e.target.value || '').replace(/\D/g, '').slice(-1)
const arr = pwdChars.value.slice()
arr[idx] = v
pwdChars.value = arr
e.target.value = v
const joined = arr.join('')
if (joined.length === 6) {
  nextTick(() => submitSaleWithPassword())
  return
}
if (v) {
  const next = e.target.nextElementSibling
  if (next) next.focus()
}
}
function submitSaleWithPassword() {
const body = {
  payWay: 1,
  nftId: nftId.value,
  price: 100 * saleForm.price,
  secondPassword: encryptPassword(pwdChars.value.join('')),
}
showLoading()
nftApi.sellUp(body).then((res) => {
  hideLoading()
  if (res.status) {
    pwdPopup.value = false
    showToast('寄售成功')
    getNft()
  } else {
    pwdChars.value = ['', '', '', '', '', '']
  }
})
}

/* 盲盒开启 */
function openBox() {
showLoading()
nftApi.openBox({ nftId: nftId.value }).then((res) => {
  hideLoading()
  if (res.status) {
    Object.assign(boxResultData, res.data)
    showBoxResult.value = true
  }
})
}

getNft()
</script>

<style scoped>
.nft-info-container {
min-height: 100vh;
background: #f5f7fc;
padding-bottom: 5.625rem;
font-size: 16px;
color: #000;
/* 原站本页继承 uni-app 全局 "Times New Roman" */
font-family: 'Times New Roman';
}

/* 原站 uni-text 行为span，行盒比本地高约 1px；此处逐类还原 */
.ids-key,
.ids-val,
.cert-key,
.cert-val,
.badge-tag,
.badge-num,
.kv-key,
.kv-val,
.brand-name,
.brand-action,
.user-name,
.user-role {
display: inline-block;
line-height: normal;
}

/* ---------- Hero ---------- */
.hero {
position: relative;
height: 33.75rem;
overflow: hidden;
background: url('@/assets/my/nftinfo-bg.png') no-repeat;
background-size: 100% 100%;
}
.nav-bar {
position: absolute;
top: 0;
left: 0;
right: 0;
z-index: 20;
display: flex;
justify-content: space-between;
align-items: center;
padding: 0.625rem 0.9375rem 0;
}
.nav-back {
width: 2rem;
height: 2rem;
display: flex;
align-items: center;
justify-content: center;
}
.nav-back .back-icon {
width: 1.5625rem;
height: 1.5625rem;
}

.art3d-wrap {
padding: 4.0625rem 5rem;
}
.nbmv {
position: relative;
width: 100%;
/* 原站 nb-model-viewer height=320px */
height: 320px;
}
.nbmv__frame {
width: 100%;
height: 100%;
border: 0;
display: block;
}
.nbmv__mask {
position: absolute;
left: 0;
top: 0;
right: 0;
bottom: 0;
display: flex;
align-items: center;
justify-content: center;
}
.nbmv__mask-text {
font-size: 0.75rem;
color: #fff;
font-family: 'PingFang SC', sans-serif;
}

.stage {
position: absolute;
bottom: 2.34375rem;
left: 0;
right: 0;
z-index: 5;
display: flex;
justify-content: center;
}
.stage-inner {
z-index: 1;
position: relative;
width: 290px;
height: 170px;
background: url('@/assets/my/nftinfo-stage.png') no-repeat;
background-size: 100% 100%;
display: flex;
flex-direction: column;
align-items: center;
justify-content: center;
}
.stage-name {
display: flex;
align-items: center;
}
.stage-name .laurel {
/* 原站 width:1.25rem，0.03125rem=1/32rem → 1.25rem=20.8px，高按 44:89 比例=42.08px */
width: 1.25rem;
height: auto;
flex-shrink: 0;
}
/* 中间竖列：藏品名 + 限量徽章，两侧留白 0.9375rem = 0.9375rem */
.stage-name-col {
display: flex;
flex-direction: column;
justify-content: center;
align-items: center;
margin: 0 0.9375rem;
}
.stage-name .name-text {
font-size: 1.25rem;
font-weight: 700;
color: #0d0c46;
letter-spacing: 0.0625rem;
}
.stage-badge {
margin-top: 0.5625rem;
display: flex;
align-items: center;
background: #ebf2ff;
border: 1px solid #b3ccfd;
border-radius: 0.3125rem;
font-size: 0.625rem;
}
.stage-badge .badge-tag {
background: #3578fc;
color: #fff;
font-weight: 500;
margin-right: 0.3125rem;
padding: 0.15625rem 0.25rem;
}
.stage-badge .badge-num {
color: #0d0c46;
padding: 0.15625rem 0.25rem;
}

/* ---------- 内容 ---------- */
.content {
margin-top: -3.75rem;
padding: 0 0.625rem;
position: relative;
z-index: 15;
}
.ids-cert-box {
background: #fff;
border-radius: 1.125rem;
padding: 0.40625rem;
display: flex;
overflow: hidden;
box-shadow: 0 2px 1px rgba(0, 0, 0, 0.04), 0 0 8px rgba(0, 0, 0, 0.08);
}
.ids-box {
/* 原站未声明 box-sizing：7.1875rem 为内容宽，加 padding 24.96 + border 2 → 146.5px */
box-sizing: content-box;
width: 7.1875rem;
background: #f8f9ff;
border: 1px solid rgba(0, 0, 0, 0.16);
border-radius: 0.8125rem;
padding: 0.875rem 0.75rem;
display: flex;
flex-direction: column;
justify-content: center;
align-items: center;
}
.ids-box .ids-key {
font-size: 0.875rem;
color: #0d0c46;
white-space: nowrap;
overflow: hidden;
text-overflow: ellipsis;
}
.ids-box .ids-val {
margin-top: 0.375rem;
font-size: 0.75rem;
color: #656b75;
}
.cert-box {
box-sizing: content-box;
flex: 1;
margin-left: 0.5625rem;
padding: 0.875rem 0.75rem;
position: relative;
display: flex;
flex-direction: column;
justify-content: center;
overflow: hidden;
background: url('@/assets/my/cert-bg.png') no-repeat;
background-size: 100% 100%;
}
.cert-box .cert-star {
position: absolute;
right: -0.625rem;
top: 50%;
transform: translateY(-50%);
width: 6.25rem;
height: 6.25rem;
background: radial-gradient(circle, rgba(120, 150, 255, 0.12), rgba(120, 150, 255, 0) 70%);
border-radius: 50%;
}
.cert-box .cert-key {
font-size: 0.875rem;
font-weight: 600;
color: #0d0c46;
}
.cert-box .cert-val {
margin-top: 0.375rem;
font-size: 0.75rem;
color: #2b52ef;
}

.pro-info-box {
margin-top: 0.625rem;
background: #fff;
border-radius: 1.125rem;
padding: 0.75rem 0.9375rem;
box-shadow: 0 0.25rem 0.75rem rgba(80, 110, 200, 0.06);
}
.brand-row {
display: flex;
justify-content: space-between;
align-items: center;
background: linear-gradient(90deg, #f3f6ff, #fdfcff 52%, #f2f3fa);
border-radius: 0.6875rem;
padding: 0.625rem 0.9375rem;
margin-bottom: 0.3125rem;
}
.brand-row .brand-left {
display: flex;
align-items: center;
}
.brand-row .brand-logo {
width: 2rem;
height: 2rem;
border-radius: 0.5rem;
margin-right: 0.625rem;
}
.brand-row .brand-name {
font-size: 0.9375rem;
font-weight: 600;
color: #0d0c46;
}
.brand-row .brand-action {
font-size: 0.8125rem;
color: #999;
}
.key-val-row {
box-sizing: content-box;
display: flex;
justify-content: space-between;
align-items: center;
padding: 1rem 0;
border-bottom: 0.03125rem solid #f2f3f7;
}
.key-val-row:last-child {
border-bottom: none;
}
.key-val-row .kv-key {
font-size: 0.875rem;
color: #2e2d33;
font-weight: 500;
}
.key-val-row .kv-val-wrap {
display: flex;
align-items: center;
max-width: 60%;
}
.key-val-row .kv-logo {
width: 1rem;
height: 1rem;
border-radius: 50%;
margin-right: 0.375rem;
}
.key-val-row .kv-val {
font-size: 0.875rem;
color: #2e2d33;
white-space: nowrap;
overflow: hidden;
text-overflow: ellipsis;
}
/* 原站为 iconfont icon-copy 字符，实测占位 14.5×14.5px，与文字间距 6.24px(12rpx)。
 本地无该字符字形，改用 CSS 双层方框绘制「复制」图标（不使用 SVG / emoji）。 */
.key-val-row .kv-copy {
position: relative;
width: 0.9063rem; /* 14.5px */
height: 0.9063rem;
margin-left: 0.3906rem; /* 6.24px = 12rpx */
flex-shrink: 0;
cursor: pointer;
}
.key-val-row .kv-copy::before,
.key-val-row .kv-copy::after {
content: '';
position: absolute;
border: 1px solid #2b52ef;
border-radius: 1px;
}
.key-val-row .kv-copy::before {
left: 4.5px;
top: 0;
width: 8.5px;
height: 8.5px;
}
.key-val-row .kv-copy::after {
left: 0;
top: 4.5px;
width: 8.5px;
height: 8.5px;
background: #fff;
}

.intro-box {
margin-top: 0.625rem;
background: #fff;
border-radius: 1.125rem;
padding: 0.9375rem;
box-shadow: 0 0.25rem 0.75rem rgba(80, 110, 200, 0.06);
}
.tab-list {
display: flex;
justify-content: center;
align-items: center;
margin-bottom: 0.9375rem;
}
.tab-list .tab-item {
position: relative;
padding: 0 0.9375rem 0.5rem;
font-size: 0.9375rem;
}
.tab-list .tab-item.sel {
font-weight: 700;
color: #2e2d33;
}
.tab-list .tab-item.sel::after {
content: '';
position: absolute;
bottom: 0;
left: 50%;
transform: translateX(-50%);
width: 1.25rem;
height: 0.1875rem;
background: #2e2d33;
border-radius: 0.09375rem;
}
.tab-list .tab-item.unsel {
color: #666;
font-weight: 400;
}
.user-row {
display: flex;
justify-content: space-between;
align-items: center;
margin-bottom: 0.9375rem;
}
.user-row .user-left {
display: flex;
align-items: center;
}
.user-row .user-logo {
width: 1.5rem;
height: 1.5rem;
border-radius: 50%;
margin-right: 0.5rem;
}
.user-row .user-name {
font-size: 0.9375rem;
font-weight: 600;
color: #2e2d33;
}
.user-row .user-role {
font-size: 0.8125rem;
color: #2e2d33;
}
.intro-desc {
font-size: 0.875rem;
color: #666;
line-height: 1.7;
text-align: justify;
margin-bottom: 0.9375rem;
}
.intro-desc :deep(img) {
width: 100%;
}
.intro-img {
width: 100%;
border-radius: 0.625rem;
/* 原站 .intro-img margin-bottom 为 10.4px；uni-image 作为 inline-block 另有约 5px
   基线间隙（实测 kv-row 起点 1315.8 = img 底 1300.4 + 10.4 + 5），故合并为 15.4px */
margin-bottom: 15.4px;
display: block;
}
/* 原站 uni-image widthFix 在 src 为空时仍占 240px 高（实测 h=240）；
 margin-bottom 与普通 .intro-img 一致（10.4px），其下另有 inline-block 基线间隙约 5px */
.intro-img--empty {
height: 240px;
}
.kv-row {
box-sizing: content-box;
display: flex;
justify-content: space-between;
align-items: center;
padding: 0.625rem 0;
}
.kv-row .kv-key {
font-size: 0.875rem;
color: #2e2d33;
font-weight: 500;
}
.kv-row .kv-val {
font-size: 0.875rem;
color: #2e2d33;
}
.empty-notice {
padding: 2.5rem 0;
text-align: center;
color: #999;
font-size: 0.875rem;
}

/* ---------- 底部操作栏 ---------- */
.bottom-action-bar {
position: fixed;
bottom: 0;
left: 0;
right: 0;
background-color: #fff;
display: flex;
justify-content: center;
padding: 0.625rem 0.9375rem;
padding-bottom: calc(0.625rem + env(safe-area-inset-bottom));
box-shadow: 0 -0.0625rem 0.3125rem rgba(0, 0, 0, 0.05);
z-index: 100;
}
.bottom-action-bar .bottom-buttons {
width: 100%;
display: flex;
justify-content: center;
}
.bottom-action-bar .action-button {
flex: 1;
height: 2.75rem;
border-radius: 1.375rem;
display: flex;
align-items: center;
justify-content: center;
font-size: 0.9375rem;
font-weight: 500;
max-width: 18.75rem;
}
.bottom-action-bar .action-button.cancel-button {
background-color: #f2f3f7;
color: #333;
}
.bottom-action-bar .action-button.sale-button {
background-color: #3578fc;
color: #fff;
}

/* ---------- 设置背景弹窗 ---------- */
.set-bg-popup {
position: fixed;
inset: 0;
background-color: rgba(0, 0, 0, 0.5);
display: flex;
align-items: center;
justify-content: center;
z-index: 999;
}
.set-bg-popup .popup-content {
width: 80%;
background-color: #fff;
border-radius: 0.75rem;
overflow: hidden;
}
.set-bg-popup .popup-title {
padding: 1.5625rem 0.9375rem;
text-align: center;
font-size: 1rem;
font-weight: 700;
}
.set-bg-popup .popup-buttons {
display: flex;
border-top: 0.03125rem solid #f0f0f0;
padding: 0.625rem;
}
.set-bg-popup .popup-button {
flex: 1;
padding: 0.75rem;
text-align: center;
font-size: 0.9375rem;
border-radius: 1.375rem;
margin: 0 0.3125rem;
}
.set-bg-popup .popup-button.cancel-button {
color: #666;
background-color: #f5f5f5;
}
.set-bg-popup .popup-button.confirm-button {
color: #fff;
background-color: #e73536;
}

/* ---------- 盲盒结果 ---------- */
.box-result-popup {
position: fixed;
inset: 0;
background-color: rgba(0, 0, 0, 0.6);
display: flex;
align-items: center;
justify-content: center;
z-index: 999;
}
.box-result-popup .result-content {
width: 80%;
background-color: #fff;
border-radius: 0.75rem;
overflow: hidden;
display: flex;
flex-direction: column;
}
.box-result-popup .result-header {
padding: 0.9375rem;
display: flex;
justify-content: space-between;
align-items: center;
border-bottom: 0.03125rem solid #f0f0f0;
}
.box-result-popup .result-title {
font-size: 1rem;
font-weight: 700;
color: #333;
}
.box-result-popup .result-body {
padding: 1.25rem;
display: flex;
flex-direction: column;
align-items: center;
}
.box-result-popup .result-image {
width: 9.375rem;
height: 9.375rem;
border-radius: 0.5rem;
margin-bottom: 0.9375rem;
}
.box-result-popup .result-name {
font-size: 0.9375rem;
color: #333;
font-weight: 600;
}
.box-result-popup .result-info {
margin-top: 0.3125rem;
font-size: 0.8125rem;
color: #999;
}
.box-result-popup .result-footer {
padding: 0.9375rem;
border-top: 0.03125rem solid #f0f0f0;
}
.box-result-popup .result-button {
height: 2.75rem;
border-radius: 1.375rem;
background: #3578fc;
color: #fff;
display: flex;
align-items: center;
justify-content: center;
font-size: 0.9375rem;
}

/* ---------- 寄售价格弹窗（逐条对齐源站 uv-popup__content 312x340 实测值） ---------- */
.sale-popup-box {
  padding: 26px 20.8px 20.8px;
  width: 18.75rem;
  height: 339.7px;
  color: #000;
  box-sizing: border-box;
}
.sale-popup-title {
  font-size: 17.68px;
  font-weight: 700;
  color: #333;
  text-align: center;
}
.sale-hold-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 16.22rem;
  color: #000;
  /* 源站实测 47.03px（13.52px padding + 19.99px 行高），非整数 rem */
  height: 47.03px;
  box-sizing: border-box;
  padding: 0.8125rem 0.75rem;
  border-radius: 0.375rem;
  background: #f5f6f8;
  font-size: 16px;
  margin-top: 1.25rem;
}
.sale-hold-label {
  font-size: 13.52px;
  color: #999;
  /* 源站该行 y=338，本地行高舍入后落到 339，用 -1px 视觉补偿 */
  margin-top: -1px;
}
.sale-hold-val {
  font-size: 14.56px;
  font-weight: 500;
  color: #333;
}
.sale-price-label {
  font-size: 14.56px;
  font-weight: 500;
  color: #333;
  margin-top: 1.125rem;
}
.sale-price-input-box {
  display: flex;
  align-items: center;
  width: 166px;
  height: 46px;
  box-sizing: border-box;
  padding: 0 0.75rem;
  /* 源站实测 margin: 12.48px 52.0156px 0px 52px */
  margin: 0.75rem 52.0156px 0 52px;
  justify-content: flex-start;
  /* 源站 border-radius:0.375rem + 1px 边框（源站为 44px 内容高 + 2px 边框 = 46px 视觉高） */
  border: 0.0625rem solid #e5e5e5;
  border-radius: 0.375rem;
  color: #000;
}
.sale-price-symbol {
  font-size: 15.6px;
  color: #333;
  margin-right: 0.375rem;
}
.sale-price-input {
  min-width: 0;
  width: 125px;
  flex: none;
  border: 0;
  outline: none;
  height: 44px;
  font-size: 15.6px;
  color: #000;
  background: transparent;
  padding: 0;
}
.sale-fee-tip {
  margin-top: 0.9375rem;
  font-size: 12.48px;
  color: #999;
}
.sale-fee-income {
  color: #2b52ef;
  font-weight: 400;
}
.sale-popup-btns {
  display: flex;
  width: 16.22rem;
  /* 源站实测 margin-top 22.88px */
  margin-top: 22.88px;
  position: relative;
  top: -0.85px;
  color: #000;
}
.sale-btn {
  flex: 1;
  height: 43.67px;
  border-radius: 0.375rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15.6px;
  font-weight: 400;
}
.sale-btn-cancel {
  background: #f4f5f7;
  color: #333;
  margin-right: 0.75rem;
}
.sale-btn-confirm {
  background: #2b52ef;
  color: #fff;
}

/* ---------- 操作密码弹窗 ---------- */
.pwd-box {
width: 15.625rem; /* 原站 15.625rem */
padding: 0.9375rem; /* 原站 0.9375rem */
text-align: center;
}
.pwd-title {
margin-top: 1.875rem; /* 原站 1.875rem */
font-size: 17px;
font-weight: 700;
color: #333;
}
.pwd-amount {
margin-top: 0.9375rem; /* 原站 0.9375rem */
font-size: 26px;
font-weight: 500;
}
.pwd-inputs {
margin-top: 2.5rem; /* 原站 2.5rem */
margin-bottom: 1.875rem; /* 原站 1.875rem */
display: flex;
justify-content: space-between;
}
.pwd-cell {
width: 1.9375rem; /* 原站 1.9375rem */
height: 1.9375rem;
border: 0.03125rem solid #e5e5e5;
border-radius: 0.5rem;
text-align: center;
font-size: 1.125rem;
color: #333;
outline: none;
}
</style>