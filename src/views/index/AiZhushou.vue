<template>
  <!--
    AI 助手 —— 复刻原站 /pages/index/ai-zhushou
    源站实测（页面总高 844，navigationStyle=custom 无系统导航栏）：
      .containerStyle.aiAnswerBg 390x844，底图 ai-zhushou-bg1-CTEYgBep.jpg，bg rgb(196,220,255)
      .navBar 390x46，.back 23x24 @x=17 y=11（margin-left 16.64px）
      .backMain 390x655 @y=175：linear-gradient(#f1fffc,#fff)，br 41.6px，
        margin-top 128.96px，padding 24.96px 19.76px
        .logo 106x150 @x=259 y=60（右上角装饰，绝对定位）
        .backMain-title「推荐对话」20.8px fw600 @x=31 y=200
        .backMain-content 349x331 @x=20 y=246，br 16.64px，bg 白，padding 16.64px 20.8px
          .content-item 308x43（间距 42px）：序号 15.6px fw600 + 问题 15.6px fw600 + 右侧箭头 6x10
          .backMain-bottom 351x22 @y=594，margin-top 16.64px，居中：
            refresh 图标 21x21 + 「换一换」14.56px fw600 rgb(153,153,153)
      .Aidialog-warp 390x85 fixed @y=759，bg rgb(246,247,251)，padding 14.56px 16.64px
        .input 250x37 @x=23 y=783，br 4.16px，bg 白；输入 15.6px
        .send 58x37 @x=315 y=783，bg rgba(0,111,245,0.4)，16.64px fw500 白字
  -->
  <div class="aiPage" :style="{ backgroundImage: `url(${bg})` }">
    <div class="navBar">
      <span class="back" @click="goBack" />
    </div>

    <div class="backMain">
      <img class="logo" :src="logo" alt="" />

      <div class="backMain-title">推荐对话</div>

      <div class="backMain-content">
        <div v-for="(q, i) in questions" :key="i" class="content-item" @click="onAsk(q)">
          <div class="item-lt">
            <span class="item-num">{{ i + 1 }}</span>
            <span class="title">{{ q }}</span>
          </div>
          <div class="rt">
            <img class="rt-arrow" :src="arrow" alt="" />
          </div>
        </div>
      </div>

      <div class="backMain-bottom" @click="onRefresh">
        <img class="refresh" :src="refresh" alt="" />
        <span class="refresh-txt">换一换</span>
      </div>
    </div>

    <div class="Aidialog-warp">
      <div class="input">
        <input v-model="text" class="dialog-input" placeholder="请输入您想咨询的内容" />
      </div>
      <div class="send" @click="onSend">发送</div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

import bg from '@/assets/p0final/aizhushou/bg.jpg'
import logo from '@/assets/p0final/aizhushou/logo.png'
import arrow from '@/assets/p0final/aizhushou/arrow.png'
import refresh from '@/assets/p0final/aizhushou/refresh.png'

const router = useRouter()
const text = ref('')

/* 源站推荐对话列表（实测 7 条） */
const questions = ref([
  '数字藏品是什么？适合新手入手吗？',
  '如何查看我拥有的藏品和流转记录？',
  '平台支持哪些链上钱包的绑定？',
  '新发售的藏品一般什么时候开抢？',
  '什么是元素空间，里面的材料有什么用？',
  '合成玩法需要准备哪些素材和比例？',
  '怎么参与万象重铸活动抽稀有？',
])

function goBack() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

function onAsk(q) {
  text.value = q
}

function onRefresh() {
  questions.value = [...questions.value].reverse()
}

function onSend() {
  text.value = ''
}
</script>

<style scoped>
.aiPage {
  position: relative;
  width: 100%;
  min-height: 50.721154rem;
  box-sizing: border-box;
  background-color: rgb(196, 220, 255);
  background-repeat: no-repeat;
  background-size: 100% auto;
  overflow-x: hidden;
  color: rgb(0, 0, 0);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Helvetica,
    'PingFang SC', 'Microsoft YaHei', sans-serif;
}

/* 顶部返回栏（源站 .navBar 46px，无标题） */
.navBar {
  height: 2.764423rem;
  display: flex;
  align-items: center;
}
.back {
  display: block;
  width: 1.382212rem;
  height: 1.442308rem;
  margin-left: 1rem;
  cursor: pointer;
  background-color: rgb(255, 255, 255);
  position: relative;
}
/* 返回箭头：CSS 绘制（规则禁用 SVG/emoji 作图标） */
.back::before {
  content: '';
  position: absolute;
  left: 8px;
  top: 8px;
  width: 9px;
  height: 9px;
  border-left: 2px solid rgb(51, 51, 51);
  border-bottom: 2px solid rgb(51, 51, 51);
  transform: rotate(45deg);
}

/* 白色主面板 */
.backMain {
  position: relative;
  min-height: 39.362981rem;
  box-sizing: border-box;
  margin-top: 7.75rem;
  padding: 1.5rem 1.231971rem;
  border-radius: 2.5rem 2.5rem 0 0;
  background-image: linear-gradient(rgb(241, 255, 252), rgb(255, 255, 255));
}
.logo {
  position: absolute;
  right: 1.502404rem;
  top: -6.911058rem;
  width: 6.370192rem;
  height: 9.014423rem;
}
.backMain-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: rgb(51, 51, 51);
  margin: 0 0 1rem 0.625rem;
}
.backMain-content {
  padding: 1rem 1.231971rem;
  height: 19.891827rem;
  position: relative;
  top: -0.03rem;
  box-sizing: border-box;
  border-radius: 1rem;
  background-color: rgb(255, 255, 255);
}
.content-item {
  position: relative;
  top: 0.03rem;
  height: 2.584135rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}
.item-lt {
  display: flex;
  align-items: flex-start;
}
.item-num {
  font-size: 0.9375rem;
  font-weight: 600;
  color: rgb(51, 51, 51);
  margin: 0 0.75rem 0 0;
}
.title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: rgb(51, 51, 51);
}
.rt-arrow {
  width: 0.360577rem;
  height: 0.600962rem;
  display: block;
}

/* 换一换 */
.backMain-bottom {
  height: 22px;
  width: 21.09375rem;
  margin: 1rem -0.060096rem 0;
  display: flex;
  justify-content: center;
  align-items: center;
}
.refresh {
  width: 1.262019rem;
  height: 1.262019rem;
  margin: 0 0.5rem 0 0;
}
.refresh-txt {
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(153, 153, 153);
}

/* 底部输入条 */
.Aidialog-warp {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  height: 5.108173rem;
  padding: 0.875rem 1rem;
  box-sizing: border-box;
  background-color: rgb(246, 247, 251);
  display: flex;
  align-items: center;
}
.input {
  width: 15.024038rem;
  height: 2.223558rem;
  margin: 0 0.8125rem 0 0.375rem;
  padding: 0.4375rem 0.75rem;
  box-sizing: border-box;
  border-radius: 0.25rem;
  background-color: rgb(255, 255, 255);
}
.dialog-input {
  width: 100%;
  height: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  font-size: 0.9375rem;
  color: rgb(51, 51, 51);
}
.dialog-input::placeholder {
  color: rgb(153, 153, 153);
}
.send {
  margin-left: auto;
  width: 3.485577rem;
  height: 2.223558rem;
  border-radius: 0.25rem;
  background-color: rgba(0, 111, 245, 0.4);
  font-size: 1rem;
  font-weight: 500;
  color: rgb(255, 255, 255);
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
}
</style>