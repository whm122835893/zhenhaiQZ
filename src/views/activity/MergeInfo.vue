<template>
  <!--
    合成详情 —— 复刻原站 /pages/activity/mergeInfo
    源码：assets/pages-activity-mergeInfo.BQYsNzH6.js + assets/mergeInfo-K-Jz9J0U.css
    scopedId：data-v-8d053b35

    ⚠️ 原实现是**无条件** router.replace('/pages/index/index')（占位空页）。
       源站真实行为：onLoad 取 query.id / query.type，
         有 id → 渲染合成详情；无 id → reLaunch 回首页。
       带 id 访问时路由必须保持不变，故此处按源站重写。
  -->
  <div class="merge-page">
    <!-- 源站 uv-navbar：透明背景 + 白色标题/返回箭头 -->
    <P1BackBar title="" transparent />

    <div class="merge-container" :class="{ 'no-scroll': showMaterialPopup }">
      <!-- 顶部合成图：345rpx 方图 + 白卡上移 45rpx 叠压 -->
      <div class="cover-wrap">
        <img class="cover-img" :src="merge.coverUrl" alt="" />
      </div>

      <div class="content-wrapper">
        <div class="merge-name">{{ merge.name }}</div>
        <div class="merge-subtitle"> ·寄售中的藏品无法参与合成 </div>

        <div class="content-wrapper-inner">
          <div class="merge-section">
            <div class="merge-tip">请选择藏品：</div>
            <div class="material-container">
              <!-- 源站遍历 merge.needs（材料），键名用索引 +1 作「条件N」 -->
              <div v-for="(need, i) in needs" :key="need.iid" class="material-row">
                <div class="condition-label"> 条件{{ i + 1 }}: </div>
                <div class="material-box">
                  <div class="material-left">
                    <img class="material-thumb" :src="need.imageUrl" alt="" />
                    <div class="material-name">{{ need.name }}</div>
                  </div>
                  <div class="material-extra">
                    <span class="need-qty">需要数量：{{ need.quantity }}</span>
                    <span> 库存：{{ need.ownQuantity }}</span>
                  </div>
                </div>
                <button class="market-btn" @click="toMarket(need)">
                  去市场
                  <img class="market-arrow" :src="MARKET_ARROW" alt="" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 底部操作条：按 activityStatus 三态 -->
      <div class="button-container">
        <div v-if="activityStatus === 0" class="action-button not-started">未开始</div>
        <div v-else-if="activityStatus === 1" class="action-button merge" @click="mergeItem">合成</div>
        <div v-else class="action-button ended">已结束</div>
      </div>
    </div>

    <!-- 材料选择弹层 -->
    <div v-if="showMaterialPopup" class="material-popup" @click="closeMaterialPopup">
      <div class="material-popup-content" @click.stop>
        <div class="popup-header">
          <div class="popup-title">{{ currentMaterial ? currentMaterial.name : '材料详情' }}</div>
          <div class="close-icon" @click="closeMaterialPopup">✕</div>
        </div>
        <div class="material-list">
          <div
            v-for="m in materialList"
            :key="m.nftId"
            class="sale-item"
            :class="{ selected: body.nftIds.includes(m.nftId) }"
            @click="selectMaterial(m)"
          >
            <div class="item-info">
              <div class="item-name">{{ m.item.name }}#{{ m.token }}</div>
              <div class="item-status" :class="{ locked: m.status === 1 }">
                {{ m.status === 1 ? '寄售中' : '持有中' }}
              </div>
            </div>
            <div class="item-price">
              {{ body.nftIds.includes(m.nftId) ? '点击取消' : '点击选择' }}
            </div>
          </div>
          <div class="list-bottom-space"></div>
        </div>
      </div>
    </div>

    <!-- 合成成功弹层 -->
    <div v-if="itemShow" class="merge-success-popup" @click="closeSuccessPopup">
      <div class="merge-success-content" @click.stop>
        <div class="popup-header">
          <div class="popup-title">合成成功</div>
          <div class="close-icon" @click="closeSuccessPopup">✕</div>
        </div>
        <div class="success-nft-info">
          <img class="nft-image" :src="mergeInfo.imageUrl" alt="" />
          <div class="nft-title">{{ mergeInfo.name }}✕{{ mergeInfo.quantity }}</div>
          <div class="nft-desc">恭喜您成功合成了新的藏品！</div>
        </div>
        <div class="success-actions">
          <div class="btn-confirm" @click="closeSuccessPopup">确定</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import P1BackBar from '@/components/P1BackBar.vue'
import { activityApi, itemApi } from '@/api'

/**
 * 源站内联 data URI（11×11 白色右箭头，"去市场"按钮尾部）
 * 编译期把 <64px 小图标转 base64，直接内联在 chunk 里。
 */
const MARKET_ARROW =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAMAAAC6V+0/AAAAQlBMVEUAAAD///////////////////////////////////////////////////////////////////////////////////8IX9KGAAAAFXRSTlMABAgMGCAkKDBARGBng4+ft9/n8/epXAZ9AAAARklEQVQY02NgoBJgY8QU4xQVYMIQZBHEJspKpigHJwxwi8BEWUVRAA9YkJGPHw6ERYXY0A3lExVip6YYFxYxBmZedjKCFgDuvAUlxUYw2gAAAABJRU5ErkJggg=='

const route = useRoute()
const router = useRouter()

/** 源站 data 字段 */
const id = ref('')
const merge = ref({})
const body = reactive({ mergeId: '', nftIds: [] })
/** 0=未开始 1=进行中 2=已结束（源站 activityStatus） */
const activityStatus = ref(1)
const needs = ref([])
const showMaterialPopup = ref(false)
const currentMaterial = ref({})
const materialList = ref([])
const materialFinished = ref(false)
const mergeInfo = ref({})
const itemShow = ref(false)
const goodsInfo = ref([])

/** 源站 toMarket：带 iid 跳市场详情 */
function toMarket(row) {
  router.push('/pages/market/info?iid=' + row.iid)
}

/** 源站 getMerge：拉配方 + 记录活动状态 */
async function getMerge() {
  const res = await activityApi.getMerge(id.value)
  if (!res.status) return
  merge.value = res.data
  // 源站同时拉产出物藏品信息（本页未渲染，仅保持数据一致）
  if (res.data.merges?.[0]?.iid) {
    const g = await itemApi.get(res.data.merges[0].iid)
    if (g.status) goodsInfo.value = g.data
  }
  for (const n of res.data.needs) needs.value.push(n)
  const now = new Date().getTime()
  const end = parseInt(res.data.endTime)
  const mergeTime = parseInt(res.data.mergeTime)
  activityStatus.value = now > end ? 2 : now > mergeTime ? 1 : 0
}

function openMaterialPopup(row) {
  currentMaterial.value = row
  materialFinished.value = false
  showMaterialPopup.value = true
}

function closeMaterialPopup() {
  showMaterialPopup.value = false
}

/** 源站 selectMaterial：已选则取消，否则受 needs 数量上限约束 */
function selectMaterial(m) {
  const idx = body.nftIds.indexOf(m.nftId)
  if (idx > -1) {
    body.nftIds.splice(idx, 1)
  } else {
    const need = needs.value.find((x) => x.iid === m.iid)
    const picked = body.nftIds.length
    if (need && picked >= need.quantity) {
      showToast('藏品选择过多')
      return
    }
    body.nftIds.push(m.nftId)
  }
}

/** 源站 mergeItem：提交合成 */
async function mergeItem() {
  const res = await activityApi.mergeV1({ ...body, mergeId: id.value })
  if (res.status) {
    await getMerge()
    mergeInfo.value = res.data
    itemShow.value = true
    body.nftIds = []
  }
}

function closeSuccessPopup() {
  itemShow.value = false
}

onMounted(async () => {
  const q = route.query
  if (q.id) {
    id.value = String(q.id)
    body.mergeId = id.value
    await getMerge()
    // 源站 onLoad：type=1 合成兑换 / 其它 分解兑换
    const title = String(q.type) === '1' ? '合成兑换' : '分解兑换'
    document.title = title
  } else {
    // 源站：无 id 直接回首页
    router.replace('/pages/index/index')
  }
})
</script>

<style scoped>
/* ============ 页面容器 ============ */
/* 源站 uni-page-body / body 均为 #f3f4f8 底 + 内联 base64 顶部纹理图。
   该纹理是 ~1000×1000 的 base64 内联图，纯装饰，复刻用等效浅色渐变表现。 */
.merge-page {
  min-height: 100vh;
  background-color: #f3f4f8;
  background-image: linear-gradient(180deg, #e9edf6 0%, #f3f4f8 260px);
  background-repeat: no-repeat;
  background-size: 100% 260px;
}

/* 源站 .merge-container { position: relative } */
.merge-container {
  position: relative;
}
/* .merge-container.no-scroll { height:100vh; overflow:hidden } */
.merge-container.no-scroll {
  height: 100vh;
  overflow: hidden;
}

/* ============ 顶部合成图 ============ */
/* 源站内联：display:flex; justify-content:center; margin-top:40rpx; position:relative */
.cover-wrap {
  display: flex;
  justify-content: center;
  margin-top: 20px;
  position: relative;
}
/* 源站内联：padding:5px; width:345rpx; height:345rpx */
.cover-img {
  padding: 5px;
  width: 172.5px;
  height: 172.5px;
  object-fit: cover;
  border-radius: 19px;
  border: 4px solid rgba(0, 0, 0, 0.14);
  background: #fff;
}

/* ============ 白卡内容区 ============ */
/* 源站内联：background-color:#fff; margin:20rpx; border-radius:40rpx;
   margin-top:-45rpx; min-height:80vh */
.content-wrapper {
  background-color: #fff;
  margin: 10px;
  border-radius: 20px;
  margin-top: -22.5px;
  min-height: 80vh;
  padding-bottom: 0.625rem;
}
/* .content-wrapper { padding-bottom:.625rem }（内联样式，已在上方合并） */

/* 源站内联：text-align:center; font-family:PingFangBold; padding-top:70rpx;
   color:#333; font-size:17px; font-weight:700 */
.merge-name {
  text-align: center;
  font-weight: 700;
  padding-top: 35px;
  color: #333;
  font-size: 17px;
}
/* 源站内联：text-align:center; color:#3578fc; font-size:13px; margin-top:12rpx */
.merge-subtitle {
  text-align: center;
  color: #3578fc;
  font-size: 13px;
  margin-top: 6px;
}

.content-wrapper-inner {
  padding-top: 0.5rem;
}

/* .merge-section { display:flex; flex-direction:column; margin:.9375rem } */
.merge-section {
  display: flex;
  flex-direction: column;
  margin: 0.9375rem;
}
/* .merge-tip { font-family:PingFangBold; margin-top:0; color:#333; font-size:17px; font-weight:700 } */
.merge-tip {
  font-weight: 700;
  margin-top: 0;
  color: #333;
  font-size: 17px;
}

.material-container {
  display: flex;
  flex-direction: column;
}

/* 源站内联条件行：color:#aaa; font-size:13px; margin:15rpx 0 */
.condition-label {
  color: #aaa;
  font-size: 13px;
  margin: 7.5px 0;
}
/* 源站内联材料卡：display:flex; justify-content:space-between; align-items:center;
   margin-top:8px; padding:4px 13px 4px 4px; height:83px;
   background:rgba(242,247,255,.6); border-radius:13px;
   border:.5px solid rgba(53,120,252,.5) */
.material-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  padding: 4px 13px 4px 4px;
  height: 83px;
  background: rgba(242, 247, 255, 0.6);
  border-radius: 13px;
  border: 0.5px solid rgba(53, 120, 252, 0.5);
}
/* 源站内联：display:flex; align-items:center */
.material-left {
  display: flex;
  align-items: center;
}
/* 源站内联：margin-right:11px; width:75px; height:75px; border-radius:11px */
.material-thumb {
  margin-right: 11px;
  width: 75px;
  height: 75px;
  border-radius: 11px;
  object-fit: cover;
}
/* 源站内联：font-weight:500; color:#333; font-size:15px */
.material-name {
  font-weight: 500;
  color: #333;
  font-size: 15px;
}
/* 源站内联：color:#ff5f5f; font-size:13px; margin-top:30rpx */
.material-extra {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  color: #ff5f5f;
  font-size: 13px;
  margin-top: 15px;
}
/* 源站内联：margin-right:12rpx（配合库存文案排成一行） */
.need-qty {
  margin-right: 6px;
}

/* 源站内联：color:#fff; background:#ff5f5f; border-radius:39px; padding:4px 15px;
   font-size:13px */
.market-btn {
  position: absolute;
  right: 20px;
  margin-top: -34px;
  color: #fff;
  background: #ff5f5f;
  border: none;
  border-radius: 19.5px;
  padding: 2px 15px;
  font-size: 13px;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
/* 源站内联：margin-left:2px; width:11px; height:11px */
.market-arrow {
  margin-left: 2px;
  width: 11px;
  height: 11px;
}

/* ============ 底部操作条 ============ */
/* .button-container { box-shadow:0 -1px 6px rgba(32,36,30,.05); background-color:#fff;
   position:fixed; left:0; bottom:0; right:0; display:flex; justify-content:center;
   padding:13px .9375rem 27px; z-index:20 } */
.button-container {
  box-shadow: 0 -1px 6px rgba(32, 36, 30, 0.05);
  background-color: #fff;
  position: fixed;
  left: 0;
  bottom: 0;
  right: 0;
  display: flex;
  justify-content: center;
  padding: 13px 0.9375rem 27px;
  z-index: 20;
}
/* .action-button { width:100%; border-radius:26px; height:52px; display:flex;
   justify-content:center; align-items:center; font-weight:700; color:#fff } */
.action-button {
  width: 100%;
  border-radius: 26px;
  height: 52px;
  display: flex;
  justify-content: center;
  align-items: center;
  font-weight: 700;
  color: #fff;
}
/* .not-started / .ended { background:#ccc; color:#fff } */
.not-started,
.ended {
  background: #ccc;
  color: #fff;
}
/* .merge { background:linear-gradient(90deg,#1e7efc,#7c46f1); color:#fff } */
.merge {
  background: linear-gradient(90deg, #1e7efc, #7c46f1);
  color: #fff;
  cursor: pointer;
}

/* ============ 材料选择弹层 ============ */
/* .material-popup { position:fixed; inset:0; background:rgba(0,0,0,.5);
   display:flex; align-items:flex-end; z-index:20; touch-action:none } */
.material-popup {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: flex-end;
  z-index: 20;
  touch-action: none;
}
/* .material-popup-content { width:100%; height:80%; background:#fff;
   border-radius:.9375rem .9375rem 0 0; display:flex; flex-direction:column;
   position:relative; overflow:hidden } */
.material-popup-content {
  width: 100%;
  height: 80%;
  background-color: #fff;
  border-top-left-radius: 0.9375rem;
  border-top-right-radius: 0.9375rem;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
}
/* .popup-header { padding:.9375rem; display:flex; justify-content:space-between;
   align-items:center; border-bottom:1px solid #f0f0f0 } */
.popup-header {
  padding: 0.9375rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f0f0f0;
}
/* .popup-title { font-size:1rem; font-weight:700 } */
.popup-title {
  font-size: 1rem;
  font-weight: 700;
}
/* .close-icon { font-size:1rem; color:#999; width:1.25rem; height:1.25rem;
   display:flex; align-items:center; justify-content:center } */
.close-icon {
  font-size: 1rem;
  color: #999;
  width: 1.25rem;
  height: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
/* .material-list { flex:1; width:100%; height:calc(100% - 2.8125rem);
   position:relative; padding:.3125rem 0 } */
.material-list {
  flex: 1;
  width: 100%;
  height: calc(100% - 2.8125rem);
  position: relative;
  padding: 0.3125rem 0;
  overflow-y: auto;
}
/* .sale-item { padding:1.25rem .9375rem; display:flex; justify-content:space-between;
   align-items:center; overflow:hidden; margin:.625rem .9375rem; border-radius:.5rem;
   background:#fff; box-shadow:0 .125rem .625rem rgba(0,0,0,.08); min-height:2.5rem } */
.sale-item {
  padding: 1.25rem 0.9375rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  overflow: hidden;
  margin: 0.625rem 0.9375rem;
  border-radius: 0.5rem;
  background-color: #fff;
  box-shadow: 0 0.125rem 0.625rem rgba(0, 0, 0, 0.08);
  min-height: 2.5rem;
}
/* .sale-item.selected { border:.09375rem solid #2a51ef; position:relative } */
.sale-item.selected {
  border: 0.09375rem solid #2a51ef;
  position: relative;
}
/* .sale-item.selected::after { content:"✓"; position:absolute; top:.3125rem;
   right:.3125rem; width:1.25rem; height:1.25rem; background:#2a51ef; color:#fff;
   border-radius:50%; display:flex; align-items:center; justify-content:center;
   font-size:.75rem } */
.sale-item.selected::after {
  content: '✓';
  position: absolute;
  top: 0.3125rem;
  right: 0.3125rem;
  width: 1.25rem;
  height: 1.25rem;
  background-color: #2a51ef;
  color: #fff;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}
/* .item-info { display:flex; flex-direction:column; flex:1 } */
.item-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}
/* .item-name { font-size:.9375rem; color:#333; white-space:nowrap; overflow:hidden;
   text-overflow:ellipsis; margin-right:.625rem; margin-bottom:.3125rem } */
.item-name {
  font-size: 0.9375rem;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-right: 0.625rem;
  margin-bottom: 0.3125rem;
}
/* .item-status { font-size:.75rem; color:#fff; background:#52c41a;
   padding:.0625rem .375rem; border-radius:.625rem; margin-top:.1875rem;
   width:2.5rem; text-align:center } */
.item-status {
  font-size: 0.75rem;
  color: #fff;
  background-color: #52c41a;
  display: inline-block;
  padding: 0.0625rem 0.375rem;
  border-radius: 0.625rem;
  margin-top: 0.1875rem;
  width: 2.5rem;
  text-align: center;
}
/* .item-status.locked { color:#fff; background:#2a51ef } */
.item-status.locked {
  color: #fff;
  background-color: #2a51ef;
}
/* .item-price { font-size:1rem; font-weight:700; color:#2a51ef; flex-shrink:0 } */
.item-price {
  font-size: 1rem;
  font-weight: 700;
  color: #2a51ef;
  flex-shrink: 0;
}
/* .list-bottom-space { height:6.25rem } */
.list-bottom-space {
  height: 6.25rem;
}

/* ============ 合成成功弹层 ============ */
/* .merge-success-popup { position:fixed; inset:0; background:rgba(0,0,0,.5);
   display:flex; align-items:center; justify-content:center; z-index:30 } */
.merge-success-popup {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 30;
  touch-action: none;
}
/* .merge-success-content { width:80%; background:#fff; border-radius:.625rem;
   display:flex; flex-direction:column; overflow:hidden; padding-bottom:1.25rem } */
.merge-success-content {
  width: 80%;
  background-color: #fff;
  border-radius: 0.625rem;
  display: flex;
  flex-direction: column;
  position: relative;
  overflow: hidden;
  padding-bottom: 1.25rem;
}
/* .success-nft-info { padding:1.25rem; display:flex; flex-direction:column;
   align-items:center } */
.success-nft-info {
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* .nft-image { width:9.375rem; height:9.375rem; border-radius:.625rem;
   margin-bottom:.9375rem; box-shadow:0 .125rem .625rem rgba(0,0,0,.1) } */
.nft-image {
  width: 9.375rem;
  height: 9.375rem;
  border-radius: 0.625rem;
  margin-bottom: 0.9375rem;
  object-fit: cover;
  box-shadow: 0 0.125rem 0.625rem rgba(0, 0, 0, 0.1);
}
/* .nft-title { font-size:1.125rem; font-weight:700; margin-bottom:.625rem } */
.nft-title {
  font-size: 1.125rem;
  font-weight: 700;
  margin-bottom: 0.625rem;
  text-align: center;
}
/* .nft-desc { font-size:.875rem; color:#666; text-align:center } */
.nft-desc {
  font-size: 0.875rem;
  color: #666;
  text-align: center;
}
/* .success-actions { padding:0 1.25rem } */
.success-actions {
  padding: 0 1.25rem;
}
/* .btn-confirm { width:100%; height:2.5rem; line-height:2.5rem;
   background:#2a51ef; color:#fff; border-radius:1.25rem; font-size:.9375rem } */
.btn-confirm {
  width: 100%;
  height: 2.5rem;
  line-height: 2.5rem;
  text-align: center;
  background-color: #2a51ef;
  color: #fff;
  border-radius: 1.25rem;
  font-size: 0.9375rem;
  cursor: pointer;
}
</style>
