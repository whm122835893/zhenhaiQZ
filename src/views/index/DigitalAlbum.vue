<template>
  <div class="containerStyle">
    <!-- 原站 .backTitle：白底导航条。高度 = var(--status-bar-height) + 2.75rem -->
    <div class="backTitle">
      <div class="back" @click="goBack">
        <UvIcon name="arrow-left" color="#000000" :size="20" />
      </div>
      <div class="navtitle">发售日历</div>
      <div class="right-icon" />
    </div>

    <div class="mt100">
      <!-- ===== 有数据：日历卡片列表 ===== -->
      <div v-if="albumList.length > 0">
        <div
          v-for="(item, idx) in albumList"
          :key="idx"
          :class="['item', idx !== albumList.length - 1 ? 'margin-bottom-xs' : '']"
          @click="goDetail(item)"
        >
          <!-- 年份水印：原站用 absolute top:-2.625rem 定位，同一年份的三层叠影不重复渲染 -->
          <div
            v-if="idx === 0 || (timeFormat(item.starttime, 'Y') !== timeFormat(albumList[idx - 1].starttime, 'Y') && item.starttime)"
            class="yearWrap"
          >
            <span class="yearYear year1">{{ timeFormat(item.starttime, 'Y') }}</span>
            <span class="yearYear year2">{{ timeFormat(item.starttime, 'Y') }}</span>
            <span class="yearYear year3">{{ timeFormat(item.starttime, 'Y') }}</span>
          </div>

          <div class="dateRow">
            <img class="dateIcon" :src="IMG.dateIcon" mode="aspectFill" />
            <span class="dateText">{{ timeFormat(item.starttime, 'm') }}月{{ timeFormat(item.starttime, 'd') }}日</span>
          </div>

          <div class="cardOuter">
            <div class="card">
              <img class="cardImg" :src="formatImgUrl(item.image)" mode="aspectFill" />
              <div class="cardInfo">
                <div class="titleRow">
                  <span class="titleText">{{ item.subject }}</span>
                  <span v-if="remainText(item)" class="countDown">{{ remainText(item) }}</span>
                </div>
                <div class="tagWrap">
                  <div class="tags-xl">
                    <span class="xl">发行</span>
                    <span class="num">{{ item.quantity }}份</span>
                  </div>
                </div>
              </div>
              <div class="cardBottom">
                <div v-if="item.author && item.author.name" class="issuer">
                  <img
                    class="issuerAvatar"
                    :src="item.author.avatar ? formatImgUrl(item.author.avatar) : defaultAvatar"
                    mode="aspectFill"
                  />
                  <span class="issuerName">{{ item.author.name }}</span>
                </div>
                <div class="price">
                  <span class="priceSymbol">￥</span>
                  <span class="priceValue">{{ item.amount }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 有数据：底部加载提示 -->
      <div v-if="albumList.length > 0" class="loadTip">{{ ajax.loadTxt }}</div>

      <!-- 空态：原站在 ajax.loading 时渲染空注释节点，不显示空态图 -->
      <div v-else-if="!ajax.loading" class="emptyBox">
        <img class="noData-icon" :src="IMG.noData" mode="widthFix" />
        <div class="font-999-26"> 暂无藏品 </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 发售日历 —— 原站 /pages/index/digitalAlbum
 * 严格对齐原站分包 pages-index-digitalAlbum.SGy9YjmA.js 内的 digital-album-calendar 组件
 *
 * 【真实接口】
 *   GET /nft/digitalAlbum/calendar?pageNo=1&pageSize=10
 *   返回 { status, code, message, data: { list, lastPage }, time }
 *   当前线上未登录态返回 code=11111「未认证」，status=false → 页面呈现「暂无藏品」空态
 *
 * 【原站死代码保留】
 *   USE_MOCK / buildMock：原站常量 USE_MOCK=false，mock 数据未启用，按原站保留
 *
 * 【时间轴设计】
 *   年份水印 absolute top:-2.625rem，卡片区 border-left 虚线构成竖向时间轴
 */
import { ref, reactive, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import UvIcon from '@/components/UvIcon.vue'
import { nftApi } from '@/api'
import NODATA from '@/assets/nodetail/nodata.png'
import DATE_ICON from '@/assets/nodetail/date-icon.png'

const router = useRouter()

const IMG = { noData: NODATA, dateIcon: DATE_ICON }

/* ========== 原站 data() ========== */
const USE_MOCK = false // 原站常量，未启用
const albumList = ref([])
const ajax = reactive({ curPage: 1, lastPage: 1, pageSize: 10, loading: false, loadTxt: '' })
const serverTime = ref(0)
const nowTime = ref(0)
let timer = null
const defaultAvatar = '/static/album/avatar-newbee.jpg'

/**
 * 原站 timeFormat(v, flag)
 * 'Y'→年 / 'm'→月 / 'd'→日；值 > 1e11 视为毫秒，否则视为秒
 */
function timeFormat(v, flag) {
  if (!v || v <= 0) return ''
  const t = parseInt(v)
  const d = new Date(t > 1e11 ? t : t * 1000)
  const Y = d.getFullYear()
  const M = d.getMonth() + 1
  const D = d.getDate()
  if (flag === 'd') return D < 10 ? '0' + D : '' + D
  if (flag === 'm') return M < 10 ? '0' + M : '' + M
  if (flag === 'Y') return Y < 10 ? '0' + Y : '' + Y
  return ''
}

/** 原站 decorate：remain > 0 时以「服务器时间 + remain」推算截止时刻 */
function decorate(list) {
  ;(list || []).forEach((a) => {
    if (a && a.remain > 0) a._deadline = 1000 * (serverTime.value + parseInt(a.remain))
  })
}

/** 原站 remainText：剩余时间「X天XX时XX分XX秒」，归零或无 remain 返回空串 */
function remainText(a) {
  if (!a || !a.remain || a.remain <= 0) return ''
  const t = Math.max(Math.floor((a._deadline - nowTime.value) / 1000), 0)
  if (t <= 0) return ''
  const d = Math.floor(t / 86400)
  const h = Math.floor((t % 86400) / 3600)
  const m = Math.floor((t % 3600) / 60)
  const s = t % 60
  const l = (n) => (n < 10 ? '0' + n : '' + n)
  return `${d}天${l(h)}时${l(m)}分${l(s)}秒`
}

/** 原站 formatImgUrl：绝对地址与 / 开头原样返回，其余补前导 /；空值走兜底封面 */
function formatImgUrl(u) {
  if (!u) return '/static/album/cover-1.jpg'
  return /^https?:\/\//.test(u) || u.indexOf('/') === 0 ? u : '/' + u
}

/** 原站 goDetail：跳市场详情（非首发详情） */
function goDetail(item) {
  router.push({ path: '/pages/market/info', query: { iid: item.id } })
}

/** 原站 goBack：栈深 >1 则 back，否则 reLaunch 首页 */
function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

function getList(isRefresh) {
  ajax.loading = true
  if (!isRefresh) ajax.loadTxt = '加载中...'
  nftApi
    .digitalAlbumCalendar({ pageNo: ajax.curPage, pageSize: ajax.pageSize })
    .then((e) => {
      ajax.loading = false
      if (e.status) {
        const a = e.data || {}
        // 原站兼容三种返回形态：data.list || data.data || []
        const t = a.list || a.data || []
        serverTime.value = e.time || Math.floor(Date.now() / 1000)
        nowTime.value = Date.now()
        decorate(t)
        ajax.lastPage = a.lastPage || a.last_page || 1
        albumList.value = ajax.curPage === 1 ? t : [...albumList.value, ...t]
        ajax.loadTxt = ajax.curPage >= ajax.lastPage ? '没有更多了' : '上拉加载更多'
      } else {
        ajax.loadTxt = ''
      }
    })
    .catch(() => {
      ajax.loading = false
      ajax.loadTxt = ''
    })
}

/** 原站 refresh：下拉刷新 */
function refresh() {
  ajax.curPage = 1
  ajax.lastPage = 1
  albumList.value = []
  getList(true)
}

/** 原站 loadMore：触底加载 */
function loadMore() {
  if (ajax.loading) return
  if (ajax.curPage >= ajax.lastPage) {
    ajax.loadTxt = '没有更多了'
  } else {
    ajax.curPage++
    getList()
  }
}

onMounted(() => {
  getList()
  timer = setInterval(() => {
    nowTime.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  timer && clearInterval(timer)
  timer = null
})

// 原站父组件通过 ref 调 refresh / loadMore
defineExpose({ refresh, loadMore })
</script>

<style scoped>
/* ============ 页面容器 ============
   原站：position:relative; width:100%; display:flex; flex-direction:column;
        align-items:center; min-height:100vh; padding-bottom:var(--window-bottom);
        background-color:rgb(246,247,251) */
.containerStyle {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  min-height: 100vh;
  background-color: #f6f7fb;
  /* 原站 uni-page-body 基线 */
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
}

/* ============ 导航条 ============
   原站：height:calc(var(--status-bar-height) + 2.75rem); background:#fff; z-index:999 */
.backTitle {
  position: relative;
  width: 100%;
  height: 2.75rem;
  box-sizing: border-box;
  background-color: #fff;
  z-index: 999;
  flex-shrink: 0;
}
/* 原站：left:0.75rem; bottom:0; w/h:2.75rem; flex 居中（justify-content:flex-start） */
.back {
  position: absolute;
  left: 0.75rem;
  bottom: 0;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: flex-start;
}
/* 原站：left:50%; translate(-50%); h:2.75rem; fs:1.0625rem bold; max-width:60% 单行省略 */
.navtitle {
  position: absolute;
  left: 50%;
  bottom: 0;
  transform: translate(-50%);
  height: 2.75rem;
  line-height: 2.75rem;
  font-size: 1.0625rem;
  font-weight: 700;
  color: #000;
  max-width: 60%;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
/* 原站：right:0.75rem; bottom:0; w/h:2.75rem（占位，右侧无内容） */
.right-icon {
  position: absolute;
  right: 0.75rem;
  bottom: 0;
  width: 2.75rem;
  height: 2.75rem;
}

/* ============ 主体 ============ */
.mt100 {
  margin-top: 3.125rem;
}

/* ============ 列表项 ============ */
.item {
  position: relative;
}
.margin-bottom-xs {
  margin-bottom: 0.3125rem;
}

/* ---- 年份水印：原站 absolute top:-2.625rem，z-index:0 ---- */
.yearWrap {
  position: absolute;
  top: -2.625rem;
  left: 0;
  z-index: 0;
}
/* 原站：fs:4.9375rem bold; line-height:1; white-space:nowrap（定位基线留 left 由 year1/2/3 决定） */
.yearYear {
  position: absolute;
  left: 0;
  font-size: 4.9375rem;
  font-weight: 700;
  line-height: 1;
  white-space: nowrap;
}
.year1 { color: rgba(13, 12, 70, 0.05); }
.year2 { left: 0.6rem; color: rgba(13, 12, 70, 0.04); }
.year3 { left: 1.2rem; color: rgba(13, 12, 70, 0.03); }

/* ---- 日期行 ---- */
.dateRow {
  display: flex;
  align-items: center;
  color: rgb(34, 88, 236);
  position: relative;
  z-index: 200;
}
.dateIcon {
  width: 0.84375rem;
  height: 0.84375rem;
}
.dateText {
  margin-left: 0.3125rem;
  font-size: 1rem;
  line-height: 1;
}

/* ---- 卡片外层：左侧虚线构成时间轴 ---- */
.cardOuter {
  display: flex;
  align-items: center;
  margin-top: 0.3125rem;
  padding-top: 0.625rem;
  padding-bottom: 0.9375rem;
  margin-left: 0.3125rem;
  padding-left: 0.9375rem;
  position: relative;
  z-index: 10;
  border-left: 0.0625rem dashed rgb(195, 195, 196);
}
/* 原站：w:19.375rem 固定宽；白底圆角 0.75rem；1px 边框 rgb(201,215,253)；阴影 0 0 0.3125rem #ccc */
.card {
  margin-left: 0.9375rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  background-color: #fff;
  color: #000;
  border-radius: 0.75rem;
  margin-bottom: 0.9375rem;
  border: 0.0625rem solid rgb(201, 215, 253);
  box-shadow: rgb(204, 204, 204) 0 0 0.3125rem;
  width: 19.375rem;
  flex: 0 0 auto;
  box-sizing: border-box;
  overflow: hidden;
}
.cardImg {
  width: 100%;
  height: 19.375rem;
  border-radius: 0.75rem;
}
/* 原站：flex:1 1 0%; column; justify-between; padding:0.9375rem; margin-top:-0.625rem */
.cardInfo {
  flex: 1 1 0%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 0.9375rem;
  margin-top: -0.625rem;
  width: 100%;
  box-sizing: border-box;
}
.titleRow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  color: #333;
  font-weight: 700;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.titleText {
  font-size: 1rem;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
/* 原站：色 #999（不是红色），fs 0.8125rem 常规字重 */
.countDown {
  flex-shrink: 0;
  margin-left: 0.3125rem;
  color: #999;
  font-size: 0.8125rem;
  font-weight: 400;
}

/* ---- 发行标签：原站 xl 为青色块，num 为浅青块，靠 z-index 叠出连体效果 ---- */
.tagWrap {
  margin-top: 0.3125rem;
}
.tags-xl {
  color: #fff;
  font-size: 0.625rem;
  display: flex;
  align-items: center;
  position: relative;
  height: 1.1875rem;
}
.tags-xl .xl {
  display: inline-block;
  padding: 0 0.3125rem;
  height: 1.1875rem;
  line-height: 1.1875rem;
  background: 0 0 / 2.5rem 1.1875rem rgb(40, 216, 234);
  text-align: center;
  z-index: 10;
}
.tags-xl .num {
  display: inline-block;
  height: 1.1875rem;
  line-height: 1.25rem;
  background: rgb(212, 247, 251);
  color: rgb(40, 216, 234);
  text-align: right;
  padding: 0 0.3125rem;
  z-index: 1;
}

/* ---- 卡片底部：作者 + 价格 ---- */
.cardBottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.625rem;
  padding: 0 0.9375rem 0.9375rem;
}
.issuer {
  display: flex;
  align-items: center;
  min-width: 0;
}
.issuerAvatar {
  width: 1.875rem;
  height: 1.875rem;
  border-radius: 50%;
  background: rgb(226, 231, 250);
  border: 0.03125rem solid rgba(255, 255, 255, 0.4);
  flex-shrink: 0;
}
.issuerName {
  margin-left: 0.3125rem;
  font-size: 1rem;
  color: #333;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
/* 原站价格色 rgb(233,170,19) 金色 */
.price {
  display: flex;
  align-items: baseline;
  flex-shrink: 0;
  margin-top: 0.3125rem;
  color: rgb(233, 170, 19);
  font-weight: 700;
}
.priceSymbol {
  font-size: 1rem;
}
.priceValue {
  font-size: 1.375rem;
  line-height: 1;
}

/* ---- 底部加载提示 ---- */
.loadTip {
  width: 100%;
  text-align: center;
  color: #999;
  font-size: 0.75rem;
  padding: 0.3125rem 0 1.25rem;
}

/* ============ 空态 ============
   原站：width:100%; padding-top:6.25rem; padding-bottom:3.125rem; text-align:center */
.emptyBox {
  width: 100%;
  padding-top: 6.25rem;
  padding-bottom: 3.125rem;
  text-align: center;
}
/* 原站：uni-image 固定 138x138（源图 250x250，widthFix 会被此尺寸覆盖） */
.noData-icon {
  width: 138px;
  height: 138px;
  margin: 0 auto;
}
.font-999-26 {
  margin-top: 13px;
  color: #999;
  font-size: 14px;
  line-height: 21px;
}
</style>