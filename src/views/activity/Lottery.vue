<template>
  <!--
    幸运抽奖页 —— 复刻原站 /pages/activity/lottery
    源站分包：assets/pages-activity-lottery.BYKXMw1D.js  scopeId data-v-8a2b57a9
    源站本页几乎全部布局走【内联 style】，元素无 class，本地保留结构 + 补类名便于维护。

    源站实测几何（视口 390，uni-page-body 起于 y=44）：
      .lottery-container            0,44 390x844
      ├ img turntable (nameBg)      0,44 414x253   inline: absolute z0 top0 left0 w414 h253
      ├ 右上角列 (flex column)      342,105 48x107 inline: absolute z2 top61 right0
      │  ├ img lamp                342,105 48x25
      │  └ img log-icon            343,155 47x57  margin-top:25px
      ├ 主背景 wrapper (z1 top38)    0,82 390x759   bg cj_bg.png
      │  ├ 「我的抽奖次数：N」       107,242 176x39  inline top160 z1 w176 fs13 fw500
      │  ├ 转盘装饰 img              44,261 72x126  inline top179 z1
      │  ├ 「抽奖」wrapper           28,534 335x68  inline top452 w335
      │  │  └ 抽奖文字              110,534 171x68 fs24 fw800 ls1px
      │  └ 奖品箱 wrapper            0,744 414x163  inline top662
      │     ├ img goodBox          0,744 414x158
      │     ├ 红条 wrapper          0,804 414       bg #f83 padding 0 16px
      │     │  ├ img prize-row     16,804 378x32
      │     │  └ .prize-grid       margin-top -11px min-h143 白底内阴影
      └ uv-popup（抽奖规则，居中 90% x 60vh）

    源站转盘说明：getPrizeItemStyle / getSectorLineStyle 两个方法在源码里【从未被模板调用】，
    转盘是 cj_bg.png 静态背景图整体旋转（rotateAngle），抽奖结果由接口返回的 winPrizeIndex 决定。
  -->
  <div class="lotteryPage">
    <UniPageHead title="幸运抽奖" />

    <div class="lottery-container">
      <!-- 转盘底图 -->
      <img class="turntable" :src="nameBg" alt="" />

      <!-- 右上角：抽奖规则 + 抽奖记录 -->
      <div class="corner-col">
        <img class="lamp" :src="lamp" alt="" @click="openRules" />
        <img class="log-icon" :src="logIcon" alt="" @click="goLotteryLog" />
      </div>

      <!-- 主背景 + 内容 -->
      <div class="stage" :style="{ transform: `rotate(${rotateAngle}deg)` }">
        <div class="count-box"> 我的抽奖次数：{{ drawTimes }} </div>

        <div class="deco-box">
          <img class="deco" :src="deco" alt="" />
        </div>

        <div class="draw-wrap">
          <div
            class="draw-btn"
            :style="{ backgroundImage: `url(${drawBtnBg})` }"
            @click="startLottery"
          >
            抽奖
          </div>
        </div>

        <div class="goodbox-wrap">
          <img class="goodbox" :src="goodBox" alt="" />
          <div class="prize-bar">
            <img class="prize-row" :src="prizeRow" alt="" />
            <div class="prize-grid">
              <div v-for="(p, i) in prizeList" :key="i" class="prize-card">
                <img v-if="p.imageUrl" class="card-image" :src="p.imageUrl" alt="" />
                <div class="card-info">
                  <div class="card-name">{{ p.name }}</div>
                  <div class="card-probability">中奖率: {{ p.probability }}%</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 抽奖规则（源站 uv-popup center 90% x 60vh border-radius 25rpx） -->
      <van-popup
        v-model:show="showRules"
        position="center"
        :style="{ width: '90%', height: '60vh', borderRadius: '12.48px' }"
        round
      >
        <div class="rules-section card">
          <div class="section-title">抽奖规则</div>
          <div class="rules-content" v-html="lotteryInfo.content || ''" />
        </div>
      </van-popup>

      <!-- 抽奖记录 -->
      <div v-if="showLogModal" class="lottery-log-modal" @click="closeLogModal">
        <div class="modal-content" @click.stop>
          <div class="modal-header">
            <div class="modal-title">抽奖记录</div>
            <div class="close-btn" @click="closeLogModal">×</div>
          </div>
          <div class="modal-body">
            <div v-if="lotteryLogs.length > 0" class="log-scroll" @scroll="loadMoreLotteryLogs">
              <div class="log-list">
                <div v-for="(l, i) in lotteryLogs" :key="i" class="log-item">
                  <img class="log-prize-image" :src="l.imageUrl" alt="" />
                  <div class="log-info">
                    <div class="log-prize">{{ l.name }}</div>
                    <div class="log-time">{{ timeFormat(l.createTime) }}</div>
                  </div>
                </div>
              </div>
              <div v-if="lotteryLogsFinished" class="no-more">
                <div class="no-more-text">没有更多了</div>
              </div>
            </div>
            <div v-else class="empty-state">
              <img class="empty-image" :src="emptyImg" alt="" />
              <div class="empty-text">暂无抽奖记录</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/*
  源站逻辑（pages-activity-lottery.BYKXMw1D.js）：
    data: { id, pointsBalance:0, lotteryInfo:{}, prizeList:[], isSpinning:false,
            rotateAngle:0, winPrizeIndex:-1, showLogModal:false, lotteryLogs:[],
            lotteryLogsFinished:false, lotteryLogsQuery:{pageNo:1} }
    onLoad       → getLotteryActive()
    getPointsBalance()      → GET  /activity/points/balance  → data.balance
    getLotteryActive()      → GET  /activity/lottery/active   → lotteryInfo=data, prizeList=data.prizes
    startLottery()          → isSpinning 期间直接 return
                           → 无 lotteryInfo：toast「敬请期待」
                           → 有：POST /activity/lottery/start/{id}
                             status → winPrizeIndex = prizeList.findIndex(p=>p.id===data.id) → spinWheel()
                             code 11111 → toast「请先登录」
    spinWheel()             → 圈数 = 360*floor(rotateAngle/360) + 1080 + (270 - (角度*winIdx + 角度/2))
                             3s 后 isSpinning=false → showResult()
    showResult()            → uni.showModal「恭喜您！」/「获得了：{name}」
    goLotteryLog()          → 打开记录弹窗 + GET /activity/lottery/log?pageNo=1
    loadMoreLotteryLogs()   → pageNo++ 追加；返回空 → lotteryLogsFinished=true
    「我的抽奖次数」= lotteryInfo.points ? Math.floor(pointsBalance / points) : 0
*/
import { ref, computed, onMounted } from 'vue'
import { showToast, showConfirmDialog } from 'vant'
import { activityApi } from '@/api'
import { timeFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'
import nameBg from '@/assets/p1/lottery/nameBg-XKATuG9e.png'
import lamp from '@/assets/p1/lottery/lamp-v2.png'
import logIcon from '@/assets/p1/lottery/log-icon.png'
import deco from '@/assets/p1/lottery/deco-v2.png'
import goodBox from '@/assets/p1/lottery/goodBox-CN2365XW.png'
import prizeRow from '@/assets/p1/lottery/prize-row.png'
import drawBtnBg from '@/assets/p1/lottery/draw-btn-bg.png'
import bodyBg from '@/assets/p1/lottery/cj_bg.png'
import emptyImg from '@/assets/p1/lottery/empty.png'

const pointsBalance = ref(0)
const lotteryInfo = ref({})
const prizeList = ref([])
const isSpinning = ref(false)
const rotateAngle = ref(0)
const winPrizeIndex = ref(-1)
const showRules = ref(false)
const showLogModal = ref(false)
const lotteryLogs = ref([])
const lotteryLogsFinished = ref(false)
const lotteryLogsQuery = ref({ pageNo: 1 })

/** 源站模板内联表达式：points ? floor(balance / points) : 0 */
const drawTimes = computed(() =>
  lotteryInfo.value.points
    ? Math.floor(pointsBalance.value / lotteryInfo.value.points)
    : 0
)

/** 源站 getPointsBalance */
function getPointsBalance() {
  activityApi.pointsBalance().then((res) => {
    if (res.status) pointsBalance.value = res.data.balance
  })
}

/** 源站 getLotteryActive */
function getLotteryActive() {
  activityApi.getLotteryActive().then((res) => {
    if (res.status && res.data) {
      lotteryInfo.value = res.data
      prizeList.value = res.data.prizes || []
    }
  })
}

/** 源站 startLottery */
function startLottery() {
  if (isSpinning.value) return
  if (!lotteryInfo.value || !Object.keys(lotteryInfo.value).length) {
    showToast('敬请期待')
    return
  }
  activityApi.startLottery(lotteryInfo.value.id).then((res) => {
    if (res.status) {
      winPrizeIndex.value = prizeList.value.findIndex((p) => p.id === res.data.id)
      spinWheel()
    } else if (res.code === 11111) {
      showToast('请先登录')
    }
  })
}

/** 源站 spinWheel：整圈 + 1080 度（3 圈）后停在中奖扇区 */
function spinWheel() {
  isSpinning.value = true
  const step = 360 / prizeList.value.length
  const target = 270 - (step * winPrizeIndex.value + step / 2)
  rotateAngle.value = 360 * Math.floor(rotateAngle.value / 360) + 1080 + target
  setTimeout(() => {
    isSpinning.value = false
    showResult()
  }, 3000)
}

/** 源站 showResult */
function showResult() {
  const prize = prizeList.value[winPrizeIndex.value]
  if (prize) {
    showConfirmDialog({ title: '恭喜您！', message: `获得了：${prize.name}`, showCancel: false, confirmButtonText: '确定' })
  }
}

function openRules() {
  showRules.value = true
}

/** 源站 goLotteryLog */
function goLotteryLog() {
  lotteryLogsQuery.value.pageNo = 1
  lotteryLogsFinished.value = false
  lotteryLogs.value = []
  showLogModal.value = true
  getLotteryLogs()
}

/** 源站 getLotteryLogs */
function getLotteryLogs(append = false) {
  activityApi.listLotteryLog(lotteryLogsQuery.value).then((res) => {
    if (res.status) {
      const list = res.data || []
      lotteryLogs.value = append ? [...lotteryLogs.value, ...list] : list
      if (!list.length) lotteryLogsFinished.value = true
    }
  })
}

/** 源站 loadMoreLotteryLogs */
function loadMoreLotteryLogs() {
  if (lotteryLogsFinished.value) return
  lotteryLogsQuery.value.pageNo += 1
  getLotteryLogs(true)
}

function closeLogModal() {
  showLogModal.value = false
}

onMounted(() => {
  getPointsBalance()
  getLotteryActive()
})
</script>

<style scoped>
.lotteryPage {
  min-height: 100vh;
  box-sizing: border-box;
  color: #000;
}
.lottery-container {
  position: relative;
  width: 100%;
  height: 844px;
  overflow: hidden;
  background-color: #fff5e6;
}
/* 转盘底图：源站 inline left:0（非居中），宽 414 溢出右侧 24px */
.turntable {
  position: absolute;
  z-index: 0;
  top: 0;
  left: 0;
  width: 414px;
  height: 253px;
  display: block;
}
/* 右上角竖排：源站 inline top:61px right:0 flex column center */
.corner-col {
  position: absolute;
  z-index: 2;
  top: 61px;
  right: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.lamp {
  width: 48px;
  height: 25px;
  display: block;
}
.log-icon {
  margin-top: 25px;
  width: 47px;
  height: 57px;
  display: block;
}
/* 主舞台：源站 inline top:38px，内含 cj_bg 背景，整层随中奖结果旋转 */
.stage {
  position: absolute;
  z-index: 1;
  top: 38px;
  left: 0;
  width: 100vw;
  height: 759px;
  background-image: v-bind('`url(${bodyBg})`');
  background-repeat: no-repeat;
  background-size: 100% 100%;
}
.count-box {
  position: absolute;
  z-index: 1;
  top: 160px;
  left: 50%;
  transform: translateX(-50%);
  width: 176px;
  font-size: 13px;
  font-weight: 500;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  height: 39px;
  background: #fdf6cb;
  box-shadow: inset 0 -4px 4px 0 #f9cfa2, 0 8px 8px 0 rgba(161, 145, 116, 0.6);
  border-radius: 13px;
  color: #ac4e17;
}
.deco-box {
  position: absolute;
  top: 179px;
  left: 44px;
  height: 126px;
  width: 72px;
}
.deco {
  margin-bottom: -2px;
  width: 100%;
  height: 100%;
  display: block;
}
/* 源站 inline: top452 w calc(100% - 55px) flex row center translateX(-50%) */
.draw-wrap {
  position: absolute;
  top: 452px;
  left: 50%;
  transform: translateX(-50%);
  width: calc(100% - 55px);
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
}
.draw-btn {
  padding-top: 4px;
  color: #f58600;
  letter-spacing: 1px;
  font-size: 24px;
  width: 171px;
  height: 68px;
  font-weight: 800;
  text-align: center;
  background-repeat: no-repeat;
  background-size: 100% 100%;
  cursor: pointer;
}
.goodbox-wrap {
  /* 源站 inline 未设 left（静态流在 top:662 的绝对定位容器内），
     实测源站 goodBox x=0，即靠左不居中 */
  position: absolute;
  width: 414px;
  height: 163px;
  top: 662px;
  left: 0;
}
.goodbox {
  width: 414px;
  height: 158px;
  display: block;
}
/* 源站 inline: top:60px bg #f83 padding 0 16px */
.prize-bar {
  /* 源站未声明 box-sizing（content-box）：414 内容宽 + 0 16px padding = 446 实测宽。
     本地 tokens.css 有全局 * { box-sizing:border-box }，此处需显式改回 content-box */
  width: 414px;
  box-sizing: content-box;
  position: absolute;
  top: 60px;
  /* 源站实测红条容器高 169 = 32(奖品行) + 143(白底 min-height) - 11(margin-top) + 5 底部余量 */
  height: 169px;
  background-color: #f83;
  padding: 0 16px;
}
.prize-row {
  width: 378px;
  height: 32px;
  display: block;
}
.prize-grid {
  /* 源站 prize-grid 是红条内 margin-top:-11px 的块级元素，
     红条容器实测高 169 = 32(prize-row) + 148(白底区含 -11 偏移) */
  margin-top: -11px;
  width: 378px;
  background: #fff;
  min-height: 143px;
  box-shadow: inset 0 3px 6px 1px rgba(0, 0, 0, 0.16);
  border-radius: 9px;
  padding: 8px;
  box-sizing: border-box;
}
.prize-card {
  display: flex;
  align-items: center;
  padding: 6px 0;
}
.card-image {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  flex-shrink: 0;
}
.card-info {
  margin-left: 8px;
}
.card-name {
  font-size: 13px;
  color: #333;
}
.card-probability {
  font-size: 11px;
  color: #999;
  margin-top: 2px;
}
/* 抽奖规则弹窗 */
.rules-section {
  padding: 15px;
}
.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #333;
  margin-bottom: 12px;
}
.rules-content {
  font-size: 13px;
  color: #666;
  line-height: 1.7;
}
/* 抽奖记录弹窗 */
.lottery-log-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  background: rgba(0, 0, 0, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
}
.modal-content {
  width: 312px;
  max-height: 236px;
  background: #fff;
  border-radius: 10.4px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-bottom: 1px solid #eee;
}
.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: #333;
}
.close-btn {
  font-size: 22px;
  color: #999;
  line-height: 1;
}
.modal-body {
  overflow-y: auto;
  padding: 0 16px 16px;
}
.log-scroll {
  max-height: 50vh;
  overflow-y: auto;
}
.log-item {
  display: flex;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid #f5f5f5;
}
.log-prize-image {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  flex-shrink: 0;
}
.log-info {
  margin-left: 10px;
}
.log-prize {
  font-size: 14px;
  color: #333;
}
.log-time {
  font-size: 12px;
  color: #999;
  margin-top: 2px;
}
.no-more {
  text-align: center;
  padding: 12px 0;
}
.no-more-text {
  font-size: 12px;
  color: #999;
}
.empty-state {
  padding: 40px 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.empty-image {
  /* 源站 /static/images/empty.png 实测 404（background-image:none），
     仅占 62x62 空盒，本地照搬空占位 */
  width: 62px;
  height: 62px;
}
.empty-text {
  font-size: 14.56px;
  color: #999;
  margin-top: 0;
}
</style>