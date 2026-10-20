<template>
  <div class="container" :style="{ '--dt-bg': `url(${DT_BG})` }">
    <!-- 原站 navigationStyle=custom：uni-page-head-transparent，
         容器高 0，返回键浮在 3D 展示区之上，不占文档流 -->
    <UniPageHead transparent />
    <!-- ===== 3D 展示区 ===== -->
    <div class="stage">
      <!-- 原站 nb-model-viewer：iframe 加载 /static/model3d/index.html，
           纹理贴图用藏品封面图，高度 320px -->
      <iframe
        class="nbmv__frame"
        :src="modelViewerUrl"
        frameborder="0"
        scrolling="no"
        allow="xr-spatial-tracking"
      ></iframe>
    </div>

    <!-- ===== 信息区 ===== -->
    <div class="stage-info" :style="{ marginTop: stageInfoMarginTop }">
      <!-- 原站占位块 4.16px = 38/32 rem = 1.1875rem = 19.8px -->
      <div style="height: 1.1875rem"></div>

      <!-- 操作按钮组：分享 + 点赞（原站两按钮未绑定动作，保持占位） -->
      <div class="act-box">
        <div class="act-btn" @click="go2Null">
          <img :src="IMG.share" mode="" style="width: 58px; height: 58px" />
        </div>
        <div class="act-btn" @click="go2Null">
          <img :src="IMG.like" mode="" style="width: 58px; height: 58px" />
        </div>
      </div>

      <!-- 原站占位块 2.6px = 45/32 rem = 1.40625rem = 23.4px -->
      <div style="height: 1.40625rem"></div>

      <!-- 虚线分隔 + 锁文案 -->
      <div class="lock-box">
        <img class="lock-icon" :src="IMG.lock" mode="aspectFit" />
        <span class="lock-text">购买即可体验高清内容</span>
      </div>

      <!-- 标题 -->
      <div class="subject">{{ item.name }}</div>

      <!-- 发行量 + 系列标签 -->
      <div class="nums-box">
        <div class="num-tag">
          <span class="num-tag-icon">⚡</span>
          <span>发行{{ item.issueQuantity }}份</span>
        </div>
        <div class="num-tag num-tag--plain">
          <span>{{ item.category && item.category.name || '数字藏品' }}</span>
        </div>
      </div>

      <!-- ===== 免责提示（原站 .tips-box 在 .stage-info 内，是最后一个子元素） ===== -->
      <div class="tips-box">
        <span class="tips-icon">!</span>
        <span>数字文创产品属于虚拟数字商品，一经出售概不退换</span>
      </div>
    </div>

    <!-- ===== 内容区 ===== -->
    <div class="content">
      <!-- 创作者信息卡 -->
      <div class="info-card">
        <div class="user-box">
          <div class="user-head">
            <div class="user-name">
              <img class="user-avatar" :src="item.author.avatarUrl" mode="aspectFill" />
              <span class="user-name-text">{{ item.author.name }}</span>
            </div>
            <span class="user-role">设计师/创作者</span>
          </div>
          <div class="user-intro">{{ item.author.intro || item.author.introduction || '' }}</div>
        </div>
        <!-- 原站：item.content 存在才渲染富文本 -->
        <div>
          <rich-text v-if="item.content" class="detail-desc" :nodes="item.content"></rich-text>
        </div>
      </div>

      <!-- 购买须知：原站写死文案 -->
      <div class="notice-section">
        <div class="notice-title">购买须知</div>
        <div class="notice-body">
          <span class="notice-text"> 为更好服务各位用户，特此公告数字藏品的发售细则。鉴于不同属性的数字艺术的发售细则各有不同，请各位用户务必审慎阅读并同意相关规则后进行相应操作，以免造成不必要损失。</span>
          <span class="notice-red">1、本商品为在线数字化商品，一经出售，不支持七天无理由退换货。</span>
          <span class="notice-text">2、数字藏品为虚拟数字商品，购买前请完成实名认证，未满18周岁人群禁止购买。</span>
          <span class="notice-text">3、依据我国法律要求，特定条件下的数字藏品的发售不支持任何形式的变相炒作，我们坚决反对数字藏品炒作、场外交易、欺诈，或以任何其他非法、侵权方式使用的违法违规行为，请大家注意甄别上述涉嫌网络欺诈的行为及其相关风险。</span>
          <span class="notice-text"> 4、如您为参与任何活动或获得任何赋能而转让(出售赠与等)、受让、合成、兑换、使用、处置、销毁相关数字藏品，您授权同意平台向活动举办方、赋能提供方、数字藏品发售方等提供您的联系方式、藏品信息、藏品数量、收货地址 (如需)等必要信息。 </span>
        </div>
      </div>

      <!-- 备案图：原站 mode=widthFix，源图 267x66，宽 120px 时高 29.7px（实测 30px）
           故不设固定 height，由 widthFix 按比例撑开；
           原站 uni-image 为块级，此处显式 display:block 避免 img 的基线留白 -->
      <div style="display: flex; align-items: center; justify-content: center">
        <img
          :src="IMG.bsn"
          mode="widthFix"
          alt=""
          style="margin-right: 5px; width: 120px; display: block"
        />
      </div>
    </div>

    <!-- ===== 底部购买条 ===== -->
    <div class="buy-bar">
      <div class="price-block">
        <span class="price-num">{{ formattedPrice }}</span>
      </div>
      <div :class="['buy-btn', btnClass]" @click="openQuantityModal">{{ btnValue }}</div>
    </div>

    <!-- ===== 购买数量弹窗 ===== -->
    <template v-if="showBatchPopup">
      <div class="batch-overlay" @click="closeBatchPopup"></div>
      <div class="batch-popup">
        <div class="batch-inner" style="padding: 0px 0px 0px">
          <div class="batch-title">购买数量</div>
          <div class="quantity-section" style="margin-top: 1.04px">
            <span class="quantity-label">购买数量</span>
            <div class="quantity-controls">
              <div class="quantity-btn decrease" @click="decreaseQuantity"> − </div>
              <input
                v-model="quantity"
                type="number"
                inputmode="numeric"
                class="quantity-value"
                @blur="validateQuantityInput"
              />
              <div class="quantity-btn increase" @click="increaseQuantity"> + </div>
            </div>
          </div>
          <div class="quantity-section" style="background-color: #fff; justify-content: flex-end; margin-top: 2.08px">
            <span class="quantity-label total-label">订单总计：</span>
            <div class="quantity-controls total-controls">
              <span>{{ totalAmount }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
/**
 * 藏品详情（首发）—— 原站 /pages/index/info?iid=xxx
 * 严格对齐原站分包 pages-index-info.i6NsaQHp.js
 *
 * 【真实接口】
 *   GET  /item/{iid}                       取藏品详情
 *   POST order/primary  {iid, quantity}   创建一级市场订单
 *   响应 code=11111（未认证）→ 跳登录页
 *
 * 【按钮状态机】原站 updateButtonStatus，每秒轮询
 *   isSoldOut          → "已售罄"  / sold-out（灰底）
 *   buyTime < now      → "立即购买" / hot-sale（黑底）
 *   buyTime - now > 0  → "HH:mm:ss" / countdown（黑底）
 *   其余               → "立即购买" / hot-sale
 *
 * 【3D 展示】原站 nb-model-viewer 组件内部是 iframe 加载
 *   /static/model3d/index.html?modelurl=...&imgurl=<封面>&applytexture=1&rot=1&speed=200%25
 *   本项目已把原站播放器 index.html + model-viewer.min.js + model.gltf 原样落到
 *   public/static/model3d/，路径与原站保持一致（缺 static 一层会导致 iframe 404）
 */
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { itemApi, orderApi } from '@/api'
import { amountFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'
import DETAIL_BG from '@/assets/nodetail/detail-bg.png'
import SHARE from '@/assets/nodetail/share.png'
import LIKE from '@/assets/nodetail/like.png'
import LOCK from '@/assets/nodetail/lock.png'
import BSN from '@/assets/nodetail/bsn.png'

const route = useRoute()
const router = useRouter()

const IMG = { share: SHARE, like: LIKE, lock: LOCK, bsn: BSN }
/** 原站详情页背景图 detail-bg-MOCZrnGc.png（750x1300） */
const DT_BG = DETAIL_BG

/* ========== 原站 data() ========== */
const detail = { model3d: '/static/model3d/model.gltf' }
const iid = ref('')
const btnValue = ref('购买')
const btnClass = ref('')
const item = reactive({ author: {}, category: {} })
const countdownTimer = ref(null)
const quantity = ref(1)
const showQuantityModal = ref(false)
/**
 * 原站 data 初始 stageInfoMarginTop = "2.08px"
 * uni-app 编译期把内联 rpx 转成 px：-450 × (100vw/750) = -450 × 0.52 = -234px
 * 本项目无 rpx 编译期转换，按原站实测值直接写 -234px
 */
const stageInfoMarginTop = ref('-234px')
const showBatchPopup = ref(false)

/** 3D 播放器地址：参数与原站 iframe 完全一致
 *  ⚠️ 不能传 orbit 参数：值里的 %25（编码的 %）经预览网关二次解码会变成
 *  裸 %，网关无法用该 URL 构建上游请求，iframe 直接收到
 *  {"error":"failed to build request"}。index.html 内置默认值
 *  orbit='0deg 90deg 105%' 与原站参数一致，不传即等效。 */
const modelViewerUrl = computed(
  () =>
    `/static/model3d/index.html?modelurl=${encodeURIComponent(detail.model3d)}` +
    `&imgurl=${encodeURIComponent(item.imageUrl || '')}` +
    `&applytexture=1&rot=1&delay=500&speed=200%25&autofront=1&touch=1`
)

/** 底部价格：amountFormat 把「分」转元，前缀￥ */
const formattedPrice = computed(() => {
  if (item.price === undefined || item.price === null || item.price === '') return '￥--'
  return '￥' + amountFormat(item.price)
})

/** 弹窗订单总计：单价 × 数量 */
const totalAmount = computed(() => '￥' + amountFormat(item.price * (Number(quantity.value) || 0)))

/* ========== 原站 methods ========== */

function getItem() {
  itemApi.get(iid.value).then((t) => {
    if (t.status) {
      Object.assign(item, t.data)
      // 原站：content 里的 img 强制 width:100%
      if (item.content) item.content = item.content.replace(/<img/g, "<img width='100%'")
      // 原站动态改页面标题为藏品名
      if (t.data.name) document.title = t.data.name
      initializeTimeTracker()
    }
  })
}

/** 原站 initializeTimeTracker：先立即刷一次，再每秒轮询 */
function initializeTimeTracker() {
  updateButtonStatus()
  countdownTimer.value = setInterval(() => {
    updateButtonStatus()
  }, 1000)
}

/** 原站 updateButtonStatus */
function updateButtonStatus() {
  const buyTime = parseInt(item.buyTime || item.buyTime)
  const now = new Date().getTime()
  if (item.isSoldOut) {
    btnValue.value = '已售罄'
    btnClass.value = 'sold-out'
  } else if (buyTime < now) {
    btnValue.value = '立即购买'
    btnClass.value = 'hot-sale'
  } else {
    const gap = buyTime - now
    if (gap > 0) {
      const h = Math.floor(gap / 36e5)
      const m = Math.floor((gap % 36e5) / 6e4)
      const s = Math.floor((gap % 6e4) / 1e3)
      btnValue.value = `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
      btnClass.value = 'countdown'
    } else {
      btnValue.value = '立即购买'
      btnClass.value = 'hot-sale'
    }
  }
}

/** 原站 openQuantityModal：倒计时与售罄状态不可点开 */
function openQuantityModal() {
  if (btnClass.value === 'countdown' || btnClass.value === 'sold-out') return
  quantity.value = 1
  showBatchPopup.value = true
}

function closeBatchPopup() {
  showBatchPopup.value = false
}

/** 原站 decreaseQuantity：下限 1 */
function decreaseQuantity() {
  if (quantity.value > 1) quantity.value--
}

/** 原站 increaseQuantity */
function increaseQuantity() {
  quantity.value++
}

/** 原站 validateQuantityInput：非法或 <1 归一为 1 */
function validateQuantityInput() {
  const t = parseInt(quantity.value)
  if (Number.isNaN(t) || t < 1) quantity.value = 1
  else quantity.value = t
}

/** 原站 createOrder：11111 未认证跳登录 */
function createOrder() {
  orderApi
    .createPrimaryOrder({ iid: iid.value, quantity: quantity.value })
    .then((t) => {
      if (t.status) {
        router.push({ path: '/pages/my/order/info', query: { orderId: t.data } })
      } else if (t.code === 11111) {
        router.push({ path: '/pages/authorize/signin' })
      }
    })
}

/** 原站两个圆形按钮未绑定实际动作，保持占位 */
function go2Null() {}

onMounted(() => {
  const id = route.query.iid
  if (id) {
    iid.value = id
    getItem()
  } else {
    // 原站：无 iid 直接回首页
    router.replace('/pages/index/index')
  }
})

onBeforeUnmount(() => {
  countdownTimer.value && clearInterval(countdownTimer.value)
  countdownTimer.value = null
})
</script>

<style scoped>
/* ============ 页面容器 ============
   原站：background-color rgb(248,249,255) + 背景图 detail-bg-MOCZrnGc.png */
.container {
  position: relative;
  width: 100%;
  min-height: 100vh;
  background-color: #f8f9ff;
  background-image: var(--dt-bg);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top center;
  /* 原站实测 padding-bottom 104px，为 fixed 购买条预留空间 */
  padding-bottom: 104px;
  box-sizing: border-box;
  /* 原站 uni-page-body 基线 */
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
}

/* ============ 3D 展示区 ============
   原站：width 23.4375rem(满屏) / height 31.875rem / padding-top 5rem
   注意原站 .stage 未设 box-sizing，height 31.875rem 是内容高，
   加 padding-top 5rem 后实际渲染高 = 31.875 + 5 = 36.875rem = 613.6px。
   本项目全局 reset 为 * { box-sizing: border-box }，需显式改回 content-box 才与原站一致 */
.stage {
  position: relative;
  width: 100%;
  height: 31.875rem;
  padding-top: 5rem;
  box-sizing: content-box;
}
.nbmv__frame {
  display: block;
  width: 100%;
  height: 320px;
  border: none;
}

/* ============ 信息区 ============
   原站 margin-top 18.75rem，JS 里覆盖为 2.08px */
.stage-info {
  position: relative;
  margin-top: 18.75rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  z-index: 3;
}

/* ---- 操作按钮组 ---- */
.act-box {
  display: flex;
  flex-direction: row;
  align-items: center;
}
.act-btn {
  width: 3.28125rem;
  height: 3.28125rem;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 0.4375rem;
}

/* ---- 虚线分隔：左右虚线由 ::before/::after 生成 ---- */
.lock-box {
  margin-top: 0.625rem;
  width: 22.3438rem;
  display: flex;
  flex-direction: row;
  align-items: center;
}
.lock-box::before,
.lock-box::after {
  content: '';
  flex: 1 1 0%;
  border-top: 0.0625rem dashed rgba(46, 45, 51, 0.18);
}
.lock-icon {
  width: 1.1875rem;
  height: 1.1875rem;
  margin: 0 0.375rem;
}
.lock-text {
  font-size: 0.75rem;
  color: rgb(46, 45, 51);
}

/* ---- 标题 ---- */
.subject {
  margin-top: 1.25rem;
  font-size: 1.25rem;
  font-weight: 600;
  color: rgb(13, 12, 70);
  text-align: center;
  max-width: 21.5625rem;
  line-height: 1.3;
}

/* ---- 发行量 / 系列标签 ---- */
.nums-box {
  margin-top: 0.625rem;
  display: flex;
  flex-direction: row;
  align-items: center;
}
.num-tag {
  display: flex;
  flex-direction: row;
  align-items: center;
  padding: 0.25rem 0.75rem;
  margin: 0 0.25rem;
  background: rgb(243, 246, 255);
  border: 0.03125rem solid rgba(13, 12, 70, 0.4);
  border-radius: 0.75rem;
  font-size: 0.75rem;
  color: rgb(46, 45, 51);
}
.num-tag-icon {
  margin-right: 0.25rem;
}
/* 系列名标签：透明底 */
.num-tag--plain {
  background: transparent;
}

/* ============ 免责提示 ============ */
.tips-box {
  margin-top: 0.9375rem;
  width: 100%;
  height: 2.03125rem;
  background: rgba(255, 62, 62, 0.04);
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  color: rgb(255, 62, 62);
}
.tips-icon {
  display: inline-flex;
  width: 1rem;
  height: 1rem;
  border-radius: 50%;
  background: rgb(255, 62, 62);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  align-items: center;
  justify-content: center;
  margin-right: 0.3125rem;
}

/* ============ 内容区 ============ */
.content {
  margin-top: 0.625rem;
  padding: 0 0.5rem;
}
.info-card {
  background: #fff;
  box-shadow: rgb(226, 231, 250) 0 1px 4px;
  border-radius: 0.75rem;
  padding: 0.75rem;
  box-sizing: border-box;
  /* 原站实测 margin-bottom 15.6px（= 0.9375rem），由全局规则注入 */
  margin-bottom: 0.9375rem;
}

/* ---- 创作者信息 ---- */
.user-box {
  width: 100%;
}
.user-head {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}
.user-name {
  display: flex;
  flex-direction: row;
  align-items: center;
}
.user-avatar {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 50%;
  background: rgb(226, 231, 250);
  margin-right: 0.5rem;
}
.user-name-text {
  font-size: 0.875rem;
  font-weight: 700;
  color: rgb(13, 12, 70);
}
.user-role {
  font-size: 0.6875rem;
  color: rgba(13, 12, 70, 0.8);
}
.user-intro {
  margin-top: 0.75rem;
  font-size: 0.75rem;
  color: rgb(101, 107, 117);
  line-height: 1.5rem;
}
.detail-desc {
  display: block;
  margin-top: 0.75rem;
  font-size: 0.875rem;
  color: #666;
}

/* ---- 购买须知 ---- */
.notice-section {
  margin: 0.625rem;
}
.notice-title {
  font-size: 0.875rem;
  font-weight: 700;
  color: rgb(13, 12, 70);
  margin-bottom: 0.625rem;
}
.notice-body {
  display: flex;
  flex-direction: column;
}
/* 第 1 条红字强调 */
.notice-red {
  font-size: 0.875rem;
  color: rgb(255, 95, 85);
  line-height: 1.5rem;
  margin-bottom: 0.5rem;
}
.notice-text {
  font-size: 0.75rem;
  color: rgb(101, 107, 117);
  line-height: 1.5rem;
}

/* ============ 底部购买条 ============ */
.buy-bar {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 100%;
  background: #fff;
  padding: 0.625rem 1rem 1.25rem;
  box-shadow: rgba(13, 12, 70, 0.05) 0 -0.125rem 0.5rem;
  display: flex;
  flex-direction: row;
  align-items: center;
  box-sizing: border-box;
  z-index: 10;
}
.price-block {
  display: flex;
  flex-direction: row;
  align-items: baseline;
  margin-right: 0.75rem;
  white-space: nowrap;
}
.price-num {
  font-size: 1.375rem;
  font-weight: 700;
  color: rgb(13, 12, 70);
}
.buy-btn {
  flex: 1 1 0%;
  height: 2.5rem;
  line-height: 2.5rem;
  background: rgb(33, 33, 33);
  color: #fff;
  font-size: 0.96875rem;
  font-weight: 700;
  border-radius: 31.2188rem;
  border: none;
  margin: 0;
  text-align: center;
  transition: 0.3s;
  cursor: pointer;
}
.buy-btn.sold-out {
  background: rgb(227, 227, 227);
  color: rgb(153, 153, 153);
}
.buy-btn.hot-sale,
.buy-btn.countdown {
  background: rgb(33, 33, 33);
}

/* ============ 购买数量弹窗 ============
   原站 uv-popup mode="bottom" round="20" overlayStyle rgba(0,0,0,0.7) */
.batch-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.7);
  z-index: 998;
}
.batch-popup {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  background: #fff;
  border-radius: 20px 20px 0 0;
}
/* 原站内联：text-align:center; font-size:16px; font-weight:bold; color:#555; font-family:PingFang */
.batch-title {
  text-align: center;
  font-size: 16px;
  font-weight: bold;
  color: #555;
  font-family: 'PingFang';
}
.quantity-section {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0px 0;
}
.quantity-label {
  font-size: 14px;
  color: #333;
}
.quantity-controls {
  display: flex;
  align-items: center;
}
.quantity-btn {
  width: 0px;
  height: 0px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: #333;
  background: #f5f6fa;
  border-radius: 8px;
  cursor: pointer;
  user-select: none;
}
.quantity-value {
  width: 1.04px;
  height: 0px;
  text-align: center;
  font-size: 16px;
  color: #333;
  background: #f5f6fa;
  margin: 0 1.04px;
  border: none;
  border-radius: 8px;
  outline: none;
}
/* 原站内联：订单总计行 font-size:14px; color:blue */
.total-label,
.total-controls {
  font-size: 14px;
  color: #0000ff;
}
</style>