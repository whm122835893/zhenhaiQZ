<template>
  <!--
    3D 模型查看器演示页 —— 复刻原站 /pages/activity/test

    【源站实测】
    路径虽是 /pages/activity/test，但内容与「活动」无关，实际是 nb-model-viewer
    组件的演示页：上方 320px 高的 3D 播放器，下方三个控制按钮（播放/暂停、
    切换速度、复位），再下方是硬编码的藏品信息卡。

    data() 硬编码值（原样照搬，未做任何"优化"）：
      name 示例藏品 · 福仔 / sn 000018330 / author NewBee
      publishTime 2026-08-15
      model3d  file.newbee.net.cn 上的 gltf
      image    file.newbee.net.cn 上的封面

    速度档位（原样）：100% 慢·64s / 200% 标准·32s / 400% 快·16s
    默认 speedIndex=1，即「标准 · 32s」

    scopedId data-v-744e5e08，样式与 assets/test-D48SVpXb.css 逐条对齐
  -->
  <div class="page">
    <NbModelViewer
      ref="viewer"
      :model-url="detail.model3d"
      :texture-url="detail.image"
      apply-texture
      :height="320"
      :rotation-per-second="speed"
      @loaded="onModelLoaded"
      @error="onModelError"
    />

    <div class="ctrl">
      <div class="ctrl__btn" @click="toggleRotate">
        <span class="ctrl__txt">{{ rotating ? '暂停' : '播放' }}</span>
      </div>
      <div class="ctrl__btn" @click="cycleSpeed">
        <span class="ctrl__txt">{{ speedLabel }}</span>
      </div>
      <div class="ctrl__btn" @click="resetView">
        <span class="ctrl__txt">复位</span>
      </div>
    </div>

    <div class="info">
      <div class="info__row">
        <span class="info__name">{{ detail.name }}</span>
      </div>
      <div class="info__row info__row--between">
        <span class="info__label">编号</span>
        <span class="info__value">#{{ detail.sn }}</span>
      </div>
      <div class="info__row info__row--between">
        <span class="info__label">创作者</span>
        <span class="info__value">{{ detail.author }}</span>
      </div>
      <div class="info__row info__row--between">
        <span class="info__label">发行时间</span>
        <span class="info__value">{{ detail.publishTime }}</span>
      </div>
      <div v-if="modelTip" class="info__tip">
        <span class="info__tip-txt">{{ modelTip }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import NbModelViewer from '@/components/NbModelViewer.vue'

/** 原站 data() 硬编码值，逐字照搬 */
const detail = {
  name: '示例藏品 · 福仔',
  sn: '000018330',
  author: 'NewBee',
  publishTime: '2026-08-15',
  model3d: 'https://file.newbee.net.cn/uploads/20220304/9320a583c01b09fa86d76a5a67a3854d.gltf',
  image: 'https://file.newbee.net.cn/uploads/20260815/ddb85227966dc6238305e4b244a7fc81.jpg',
}

const speeds = [
  { value: '100%', label: '慢 · 64s', sec: 64 },
  { value: '200%', label: '标准 · 32s', sec: 32 },
  { value: '400%', label: '快 · 16s', sec: 16 },
]

const rotating = ref(true)
const modelTip = ref('')
const speedIndex = ref(1) // 原站默认 1 → 「标准 · 32s」

const viewer = ref(null)

const speed = computed(() => speeds[speedIndex.value].value)
const speedLabel = computed(() => speeds[speedIndex.value].label)

function onModelLoaded() {
  modelTip.value = '已加载：拖动可手动查看（若未禁用交互）'
}
function onModelError(e) {
  modelTip.value = '模型加载失败：' + (e?.message || '未知错误')
}
function toggleRotate() {
  rotating.value = !rotating.value
  const e = viewer.value
  if (!e) return
  rotating.value ? e.resume() : e.pause()
}
function cycleSpeed() {
  speedIndex.value = (speedIndex.value + 1) % speeds.length
  const e = viewer.value
  if (e) e.setSpeed(speed.value)
}
function resetView() {
  const e = viewer.value
  if (e) e.resetView()
}
</script>

<style scoped>
/* 与源站 assets/test-D48SVpXb.css 逐条对齐（rem 值按 1rem=16px 换算为 px） */
.page {
  min-height: 100vh;
  background: #f6f7fb;
  padding-bottom: 20px;
}
.ctrl {
  display: flex;
  flex-direction: row;
  justify-content: center;
  padding: 10px 0;
}
.ctrl__btn {
  padding: 6px 16px;
  margin: 0 6px;
  border-radius: 20px;
  background: #fff;
  border: 1px solid #e3e8f0;
}
.ctrl__txt {
  font-size: 13px;
  color: #2f6feb;
}
.info {
  margin: 0 12px;
  padding: 12px 14px;
  background: #fff;
  border-radius: 12px;
}
.info__row {
  padding: 8px 0;
}
.info__row--between {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f2f4f8;
}
.info__name {
  font-size: 17px;
  font-weight: 600;
  color: #1f2329;
}
.info__label {
  font-size: 13px;
  color: #8a94a6;
}
.info__value {
  font-size: 13px;
  color: #1f2329;
}
.info__tip {
  margin-top: 10px;
}
.info__tip-txt {
  font-size: 12px;
  color: #b26a00;
}
</style>