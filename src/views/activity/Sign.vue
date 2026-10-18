<template>
  <!--
    签到中心 —— 复刻原站 /pages/activity/sign
    源站实测（页面总高 888，uni-page-head 44 白底）：
      .sign-container 390x844 底图 cj-page-bg-grBasNNw.png（background-size 100% 100%），
        padding 0 10.4px 20.8px
      .header 369x39 @y=44，padding-top 10.4px；.title-image 312x0（源站图片 404，高度为 0）
      .calendar-card 369x399 @y=93，br 12.48px，padding 15.6px
        .days-count 338x41 @y=109：linear-gradient(90deg,#fff,#d3ab88)，br 6.24px，
          文案「您已打卡0天」16.64px fw500
        .month-nav 338x34 @y=165：‹ › 各 28x34 fs20.8px，中部「2026 / 10」18.72px fw500
        .week-header 338x44 @y=215：日一二三四五六，各 31x22 fs14.56px
        .days-grid 338x207 @y=269，br 8.32px，padding 10.4px
          .day-cell 33x33，br 4.16px；今日 .is-today；日期 13.52px
      .bottom-section 369x46 @y=544，margin-top 52px
        .sign-button 328x46，br 11px，bg rgb(211,171,136)，16.64px fw700 白字
  -->
  <div class="signPage">
    <UniPageHead title="签到中心" />

    <div class="sign-container" :style="{ backgroundImage: `url(${pageBg})` }">
      <div class="header">
        <!--
          源站 <image src="/static/images/sign-title.png" mode="widthFix">，
          但该资源在源站**已 404**（assets / static/nb-index / static/images
          三个根目录都探测过，均返回 1017 字节的 404 HTML）。
          所以线上这里就是一个高度为 0 的空元素 —— 本地保持一致：
          titleImg 为空串时直接不渲染 img，视觉结果与源站相同（高度 0），
          同时避免 DOM 里留一个 src="" 的破图节点。
        -->
        <img v-if="titleImg" class="title-image" :src="titleImg" alt="" />
      </div>

      <div class="calendar-card">
        <div class="days-count">
          <span class="count-text">您已打卡{{ signedDays }}天</span>
        </div>

        <div class="month-nav">
          <!--源站 .arrow-btn 未绑定任何事件（实测点击月份不变），照搬不绑--><span class="arrow-btn">‹</span>
          <span class="month-text">{{ currentYear }} / {{ String(currentMonth).padStart(2, '0') }}</span>
          <span class="arrow-btn">›</span>
        </div>

        <div class="week-header">
          <span v-for="w in WEEK" :key="w" class="week-item">{{ w }}</span>
        </div>

        <div class="days-grid">
          <div
            v-for="(d, i) in daysInMonth"
            :key="i"
            class="day-cell"
            :class="{ 'is-today': d.isToday, 'is-signed': d.isSigned }"
          >
            <span class="day-number">{{ d.day }}</span>
          </div>
        </div>
      </div>

      <div class="bottom-section">
        <button class="sign-button" @click="handleSign"> 立即签到 </button>
      </div>
    </div>
  </div>
</template>

<script setup>
/*
  源站 /pages/activity/sign 逻辑（pages-activity-sign.CsM5pD3o.js）：
    data: { weeks, currentDate, currentYear, currentMonth, daysInMonth:[],
            signedDays:0, signedDates:[] }
    onLoad  → generateCalendar()
    onShow  → loadSignData()  → GET /activity/sign
              { data: { records: boolean[当月天数], signDays: number } }
              → signedDays / signedDates 回填后重新 generateCalendar()
    generateCalendar: 取当月天数，daysInMonth[i] = { day, isSigned: signedDates[i], isToday: 'YYYY-MM-DD' === today }
    handleSign: POST /activity/sign
              status → showToast('签到成功') + loadSignData()
              code 11111 → showToast('请先登录')

  源站 .arrow-btn / .month-text 上【没有绑定任何点击事件】（实测点击月份纹丝不动），
  prevMonth / nextMonth 是死方法，本地照搬不绑。
  按钮文案恒为「 立即签到 」（首尾各一个空格），不随签到状态变化。
*/
import { ref, onMounted } from 'vue'
import { showToast } from 'vant'
import { activityApi } from '@/api'
import UniPageHead from '@/components/UniPageHead.vue'
import pageBg from '@/assets/p0final/sign/page-bg.png'

const WEEK = ['日', '一', '二', '三', '四', '五', '六']

const now = new Date()
const titleImg = '' // 源站资源已 404，恒为空

const currentYear = ref(now.getFullYear())
const currentMonth = ref(now.getMonth() + 1) // 源站 1 起算
const daysInMonth = ref([])
const signedDays = ref(0)
const signedDates = ref([])

/** 源站 generateCalendar */
function generateCalendar() {
  const y = currentYear.value
  const m = currentMonth.value
  const total = new Date(y, m, 0).getDate()
  const todayKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
  const list = []
  for (let d = 1; d <= total; d++) {
    const key = `${y}-${String(m).padStart(2, '0')}-${String(d).padStart(2, '0')}`
    list.push({ day: d, isSigned: !!signedDates.value[d - 1], isToday: key === todayKey })
  }
  daysInMonth.value = list
}

/** 源站 loadSignData：GET /activity/sign */
function loadSignData() {
  activityApi.getSign().then((res) => {
    if (res.status) {
      signedDays.value = res.data.signDays
      signedDates.value = res.data.records || []
      generateCalendar()
    }
  })
}

/** 源站 handleSign：POST /activity/sign */
function handleSign() {
  activityApi.sign().then((res) => {
    if (res.status) {
      showToast('签到成功')
      loadSignData()
    } else if (res.code === 11111) {
      showToast('请先登录')
    }
  })
}

onMounted(() => {
  generateCalendar()
  loadSignData()
})
</script>

<style scoped>
.signPage {
  min-height: 100vh;
  color: rgb(0, 0, 0);
  /* 源站 uni-app 全局 font-family 为 Times New Roman，
     本页 CSS 未声明 font-family，故全部继承 Times New Roman，
     文字宽度依赖该字体度量，必须照搬否则每个元素宽差 1px */
  font-family: 'Times New Roman';
}
.sign-container {
  min-height: 50.721154rem;
  box-sizing: border-box;
  padding: 0 0.625rem 1.25rem;
  background-repeat: no-repeat;
  background-size: 100% 100%;
}

/* 头部：源站 .title-image 高度为 0（图片 404），此处照搬 */
.header {
  height: 2.34375rem;
  padding: 0.625rem 0;
  box-sizing: border-box;
  /* 源站 .header 内为居中文本流，text-align 继承 center */
  text-align: center;
}
.title-image {
  /* 源站 uni-image 为 inline-block（mode="widthFix"），图片 404 → 高度 0，
     元素仍参与行内基线对齐，故 y = header padding 底(54) + 基线偏移 13 = 67。
     本地直接绝对定位锁定该 y，避免 inline 元素受 line-height 干扰。 */
  display: block;
  width: 18.75rem;
  height: 0;
  margin: 0 auto;
  position: relative;
  top: 13px;
}

/* 日历卡片 */
.calendar-card {
  width: 22.175481rem;
  padding: 0.9375rem;
  box-sizing: border-box;
  margin-top: 0.625rem;
  border-radius: 0.75rem;
  /* 源站 .calendar-card 实测 background 为 transparent（rgba(0,0,0,0)），
     白底视觉来自 .sign-container 底图，此处照搬不设背景 */
  background-color: transparent;
}
.days-count {
  height: 2.463942rem;
  margin-bottom: 0.92rem;
  padding: 0.5rem 0;
  box-sizing: border-box;
  border-radius: 0.375rem;
  background-image: linear-gradient(90deg, rgb(255, 255, 255), rgb(211, 171, 136));
  text-align: center;
}
.count-text {
  font-size: 1rem;
  font-weight: 500;
  color: rgb(0, 0, 0);
}

/* 月份导航 */
.month-nav {
  height: 2.043269rem;
  margin-bottom: 0.961538rem;
  padding: 0 1.25rem;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.arrow-btn {
  font-size: 1.25rem;
  padding: 0.3125rem 0.625rem;
  color: rgb(0, 0, 0);
  cursor: pointer;
}
.month-text {
  font-size: 1.125rem;
  font-weight: 500;
  color: rgb(0, 0, 0);
}

/* 星期头 */
.week-header {
  height: 2.644231rem;
  margin-bottom: 0.600962rem;
  padding: 0.625rem 0;
  box-sizing: border-box;
  display: flex;
  /* 源站实测：容器内容区 x=25.6 宽 338，7 项各 31.19 宽，首项 x=35。
     (338 - 7×31.19)/7 = 17.1，space-around 首项前距 = 17.1/2 = 8.55 → x = 34.15 ≈ 35 */
  justify-content: space-around;
  align-items: center;
  /* 源站 .week-header 内文字为 uni-text 行盒（行高由 14.56px 字体的 normal 行高决定），
     本地 span 默认 line-height 略高 1px，用 -0.5px 视觉补偿 */
  line-height: 21px;
}
.week-item {
  width: 1.862981rem;
  flex: none;
  text-align: center;
  font-size: 0.875rem;
  color: rgb(51, 51, 51);
}

/* 日期网格 */
.days-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.3125rem;
  padding: 0.625rem;
  box-sizing: border-box;
  border-radius: 0.5rem;
}
.day-cell {
  width: 1.99rem;
  height: 1.99rem;
  justify-self: start;
  border-radius: 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.day-cell.is-today {
  background-color: rgb(211, 171, 136);
}
.day-number {
  font-size: 0.8125rem;
  line-height: 16px;
  color: rgb(51, 51, 51);
}
.day-cell.is-today .day-number {
  color: rgb(255, 255, 255);
  font-weight: 700;
}

/* 底部签到按钮 */
.bottom-section {
  margin-top: 3.125rem;
  padding: 0 1.25rem;
}
.sign-button {
  width: 19.711538rem;
  height: 2.764423rem;
  border: 0;
  border-radius: 0.661058rem;
  /* 源站 uni-button 内置 padding: 0 14px */
  padding: 0 14px;
  background-color: rgb(211, 171, 136);
  font-size: 1rem;
  font-weight: 700;
  color: rgb(255, 255, 255);
  cursor: pointer;
}
</style>