<template>
  <!--
    元素大爆炸·开盲盒页 —— 复刻原站 /pages/activity/baozha
    源站实测（390x844 视口，页面总高 1015）：
      .elementalExplosionBg  底 bg2-BRiIiFjp.png + rgb(35,60,112)，纵向 flex 居中
      .navBar 高 46，左侧 23x24 返回图（y=11）
      .content  上边距 166.4px（避开顶部大标题图），columnCenter
      t1 标题图 258x34 @y=212
      .ruleBtn 高 29 @y=253：左「爆炸规则」14.56px 白；右 chooseBtn 90x29
      .proListBox 357x223 @y=293：pageBox 357x40 + noData 空态
      .gettableBox 高 57 @y=516：t3 图 187x35 + 数值「0」22.88px 粗体
      .chooseEleBox：t2Box 高 64 @y=573（t2 图 357x48 + btn 90x29）
                      chooseEle 389x219 @y=637，底图 eleBg，内含 3 个元素球（错落定位）
      .bottomBox 固定底部，高 104 @y=740：协议勾选 + 开始获取按钮 290x55
  -->
  <div class="elementalExplosionBg" :style="{ backgroundImage: `url(${pageBg})` }">
    <div class="navBar">
      <img class="back" :src="backIcon" alt="" @click="goBack" />
    </div>

    <div class="content">
      <img class="t1" :src="t1" alt="" />

      <div class="ruleBtn centerBetween">
        <span class="ruleBtn__label">爆炸规则</span>
        <div class="center">
          <img class="chooseBtn" :src="chooseBtn" alt="" />
        </div>
      </div>

      <div class="proListBox">
        <div class="pageBox doubleCenter" :style="{ backgroundImage: `url(${pageBoxImg})` }">
          <img class="icon" :src="arrowLeft" alt="" />
          <span class="page">{{ pageIndex }}/{{ pageTotal }}</span>
          <img class="icon" :src="arrowRight" alt="" />
        </div>

        <div v-if="materialList.length" class="materialList">
          <div v-for="(m, i) in materialList" :key="i" class="material">{{ m }}</div>
        </div>
        <div v-else class="noData columnDoubleCenter">
          <img class="noData__img" :src="noData" alt="" />
          <span class="noData__txt">暂无材料</span>
        </div>
      </div>

      <div class="gettableBox centerBetween" :style="{ backgroundImage: `url(${gettableImg})` }">
        <!--
          原站此处是一张 187x35 的文字图「可获取元素」（资源 t3 = 9541.png）。
          源站本页已下线，原始图片无法再取回（此前抓到的是 404 错误页），
          改为纯文字渲染 —— 语义与视觉等价，且不再依赖失效资源。
        -->
        <span class="t3">可获取元素</span>
        <span class="gettableBox__num">0</span>
      </div>

      <div class="chooseEleBox">
        <div class="t2Box centerBetween">
          <img class="t2" :src="t2" alt="" />
          <button class="btn" type="button" @click="handleSelectAll">全选</button>
        </div>
        <div class="chooseEle" :style="{ backgroundImage: `url(${eleBg})` }">
          <div
            v-for="(e, i) in ELEMENTS"
            :key="e.name"
            class="eleItem doubleCenter"
            :style="{ backgroundImage: `url(${eleBall})`, left: e.left + 'px', top: e.top + 'px' }"
          >
            {{ e.name }}
          </div>
        </div>
      </div>
    </div>

    <div class="bottomBox columnCenter" :style="{ backgroundImage: `url(${bottomBg})` }">
      <div class="agreement center" @click="check = !check">
        <img class="check" :src="check ? checkOn : checkOff" alt="" />
        <span class="agreement__txt">我已阅读并同意</span>
        <span class="agreement__link">《元素大爆炸活动协议》</span>
      </div>
      <button class="startBtn" type="button" @click="handleSubmit">开始获取</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import pageBg from '@/assets/p1/baozha/bg2-BRiIiFjp.png'
import backIcon from '@/assets/p1/baozha/back.png'
import t1 from '@/assets/p1/baozha/t1.png'
import chooseBtn from '@/assets/p1/baozha/f695.png'
import pageBoxImg from '@/assets/p1/baozha/pagebox.png'
import arrowLeft from '@/assets/p1/baozha/28f4.png'
import arrowRight from '@/assets/p1/baozha/ae7b.png'
import noData from '@/assets/p1/baozha/noData.png'
import gettableImg from '@/assets/p1/baozha/gettable.png'
import t2 from '@/assets/p1/baozha/t2.png'
import eleBg from '@/assets/p1/baozha/eleBg-BS-9ryEE.png'
import eleBall from '@/assets/p1/baozha/c0-AAzqrtx8.png'
import bottomBg from '@/assets/p1/baozha/bottom.jpg'
/**
 * 协议勾选框两个状态。
 * 原站用 6ee1.png（选中）/ 1583.png（未选中），源站本页已下线、原图取不回，
 * 改用站内同款真实素材 market/check-on.png / check-off.png。
 */
import checkOn from '@/assets/p1/baozha/6ee1.png'
import checkOff from '@/assets/p1/baozha/1583.png'

const router = useRouter()
function goBack() {
  if (window.history.length > 1) router.back()
  else router.push('/pages/activity/index')
}

/* 源站实测 1/1，即仅一页材料 */
const pageIndex = ref(1)
const pageTotal = ref(1)
/* 源站线上材料接口为空，走 noData 分支 */
const materialList = ref([])

/** 协议勾选状态 —— 源站 src 随 check 在 6ee1(选中) / 1583(未选中) 之间切换 */
const check = ref(false)

/** 「全选」：源站 handleSingleOrAllele(0)，无材料时为空操作 */
function handleSelectAll() {
  materialList.value.forEach((m) => (m.selected = true))
}

/** 提交 —— 源站校验协议后提示「提交成功（演示）」 */
function handleSubmit() {
  if (!check.value) {
    showToast('请阅读并同意协议')
    return
  }
  showToast('提交成功（演示）')
}

/* 三个元素球错落定位，坐标取自源站实测（相对 chooseEle 左上角） */
const ELEMENTS = [
  { name: '火元素', left: 4, top: 38 },
  { name: '水元素', left: 57, top: 9 },
  { name: '风元素', left: 110, top: 41 },
]
</script>

<style scoped>
.elementalExplosionBg {
  position: relative;
  min-height: 100vh;
  box-sizing: border-box;
  padding-bottom: 104px;
  background-color: rgb(35, 60, 112);
  background-size: 100% auto;
  background-repeat: no-repeat;
  background-position: top;
  background-repeat: no-repeat;
  display: flex;
  flex-direction: column;
  align-items: center;
  overflow: hidden;
}

.navBar {
  width: 100%;
  height: 46px;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.back {
  width: 23px;
  height: 24px;
  margin-left: 16.64px;
  display: block;
  cursor: pointer;
}

.content {
  width: 100%;
  margin-top: 166.4px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.t1 {
  width: 258px;
  height: 34px;
  display: block;
}

.ruleBtn {
  width: 100%;
  height: 29px;
  padding: 0 15.6px;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.ruleBtn__label {
  font-size: 0.875rem;
  line-height: 1.5;
  color: #ffffff;
  text-decoration: underline;
}
.chooseBtn {
  width: 90px;
  height: 29px;
  display: block;
}

.proListBox {
  width: 357px;
  height: 223px;
  margin-top: 10.4px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.pageBox {
  width: 357px;
  height: 40px;
  display: flex;
  justify-content: center;
  align-items: center;
  background-size: 100% 100%;
  background-repeat: no-repeat;
}
.pageBox .icon {
  width: 21px;
  height: 21px;
  display: block;
}
.page {
  font-size: 0.875rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0 10.4px;
  line-height: 1.2;
}

.noData {
  margin-top: 71.76px;
  font-size: 0.7813rem;
  color: rgba(255, 255, 255, 0.19);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.noData__img {
  width: 73px;
  height: 67px;
  display: block;
}

.gettableBox {
  width: 100%;
  height: 57px;
  padding: 0 27.04px;
  box-sizing: border-box;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-size: 100% 100%;
  background-repeat: no-repeat;
}
/* 源站实测 187x35（9541.png 文字图「可获取元素」）的占位尺寸 */
.t3 {
  width: 187px;
  height: 35px;
  display: flex;
  align-items: center;
  font-size: 17.5px;
  font-weight: 700;
  color: #ffe9a8;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}
.gettableBox__num {
  font-size: 1.3125rem;
  font-weight: 700;
  color: #ffffff;
  line-height: 1.14;
}

.chooseEleBox {
  width: 100%;
}
.t2Box {
  width: 100%;
  height: 64px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.t2 {
  width: 357px;
  height: 48px;
  display: block;
}
.t2Box .btn {
  position: absolute;
  right: 0.4375rem;
  width: 90px;
  height: 29px;
  display: block;
  padding: 0;
  border: none;
  border-radius: 14.5px;
  font-size: 13px;
  color: #ffffff;
  background: linear-gradient(180deg, #f7b955 0%, #e8873c 100%);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.25);
}

.chooseEle {
  position: relative;
  width: 389px;
  height: 219px;
  margin: 0 auto;
  background-size: 100% 100%;
  background-repeat: no-repeat;
}
.eleItem {
  position: absolute;
  width: 51px;
  height: 85px;
  font-size: 0.875rem;
  color: #ffffff;
  background-size: 100% 100%;
  background-repeat: no-repeat;
}

.bottomBox {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 100%;
  height: 104px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  background-size: 100% 100%;
  background-repeat: no-repeat;
  background-position: bottom;
}
.agreement {
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 0.6875rem;
  font-weight: 300;
  color: rgba(255, 255, 255, 0.6);
  line-height: 1.4;
}
.agreement .check {
  width: 15px;
  height: 15px;
  display: block;
  margin-right: 5px;
}
.agreement__link {
  color: #ffffff;
}
.startBtn {
  width: 290px;
  height: 55px;
  margin-top: 6.24px;
  display: block;
  padding: 0;
  border: none;
  border-radius: 27.5px;
  font-size: 18px;
  font-weight: 700;
  color: #ffffff;
  background: linear-gradient(180deg, #ffd063 0%, #f08a3c 100%);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.startBtn:active {
  opacity: 0.9;
}
</style>
