<template>
  <!--
    NbModelViewer —— 原站 nb-model-viewer 组件的等价实现

    【实现要点】
    原站组件内部并非直接跑 three.js，而是用 iframe 加载
    /static/model3d/index.html（内含 model-viewer.min.js），
    通过 URL query 传参、用 postMessage 接收状态。本项目严格照搬：

      modelurl   模型地址（绝对或站内绝对路径）
      imgurl     贴图/封面地址
      applytexture=1  把封面作为贴图贴到模型上
      material   可选材质名
      rot=1      自动旋转
      delay      自动旋转启动延迟 ms
      speed      旋转速度（"100%"/"200%"/"400%"）
      orbit      初始相机轨道 "方位角 仰角 距离"
      autofront=1  自动转到正面
      touch=1    允许拖动交互
      bg         背景色
    -->
    <div class="nbmv" :style="{ height: cssHeight }">
      <!-- 加载中 / 失败时的占位（与原站一致：poster 图 + 进度文案） -->
      <div v-if="status !== 'ready'" class="nbmv__mask">
        <img v-if="posterSrc" class="nbmv__poster" :src="posterSrc" alt="" />
        <div v-if="status === 'loading'" class="nbmv__tip">
          模型加载中 {{ progressText }}%
        </div>
        <div v-else class="nbmv__tip nbmv__tip--err">{{ errorMsg || '模型加载失败' }}</div>
      </div>

      <iframe
        class="nbmv__frame"
        :src="frameUrl"
        :data-nbmv="elId"
        :style="appBackground ? { background: appBackground } : null"
        frameborder="0"
        allowfullscreen
        title="3D 模型查看器"
        @load="onFrameLoad"
      ></iframe>
    </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelUrl: { type: String, required: true },
  textureUrl: { type: String, default: '' },
  applyTexture: { type: Boolean, default: false },
  material: { type: String, default: '' },
  poster: { type: String, default: '' },
  height: { type: [Number, String], default: 300 },
  autoRotate: { type: Boolean, default: true },
  rotateDelay: { type: Number, default: 500 },
  rotationPerSecond: { type: String, default: '200%' },
  autoFront: { type: Boolean, default: true },
  cameraOrbit: { type: String, default: '0deg 90deg 105%' },
  disableInteraction: { type: Boolean, default: false },
  background: { type: String, default: '' },
  appBackground: { type: String, default: '#FFFFFF' },
})

const emit = defineEmits(['loaded', 'error', 'progress'])

const status = ref('loading')
const progress = ref(0)
const errorMsg = ref('')

/** 组件实例 id：iframe 内 JS 用它 postMessage 回来定位来源（原站同款做法） */
const elId = 'nbmv_' + Math.random().toString(36).slice(2, 10)

const cssHeight = computed(() => (Number.isNaN(Number(props.height)) ? 300 : Number(props.height)) + 'px')
const progressText = computed(() => Math.min(99, Math.round(100 * (progress.value || 0))))
const posterSrc = computed(() => props.poster || props.textureUrl || '')

/** 绝对化：协议相对 // 与 data:/blob: 直接放行；站内根相对路径（/media/...）
 *  也放行 —— iframe 与主页面同源，相对路径在网关环境下反而更可靠 */
function absUrl(u) {
  if (!u) return ''
  if (/^(https?:)?\/\//i.test(u) || /^(data|blob):/i.test(u)) return u
  return u.startsWith('/') ? u : ''
}

const resolvedViewerPath = computed(() => {
  // ⚠️ 产物会直接当 iframe 的 src，必须是**站内绝对路径**（单个前导斜杠）。
  //
  // 踩过的坑：用 pathname.replace(/[^/]*$/, '') 取「目录部分」不可靠 ——
  //   hash 路由下 pathname 恒为 '/'，若结果为 ''，拼出来就是 '//static/...'。
  //   浏览器会把 '//host/path' 解析成**协议相对 URL**，把 'static' 当成域名，
  //   结果变成 http://static/model3d/... 直接 404。
  // 用 URL 构造保证单个前导斜杠，history / hash 两种路由模式都成立。
  return new URL('/static/model3d/index.html', window.location.origin).pathname
})

const frameUrl = computed(() => {
  if (!props.modelUrl || !resolvedViewerPath.value) return ''
  const q = []
  q.push('modelurl=' + encodeURIComponent(absUrl(props.modelUrl)))
  if (props.textureUrl) q.push('imgurl=' + encodeURIComponent(absUrl(props.textureUrl)))
  if (props.applyTexture) q.push('applytexture=1')
  if (props.material) q.push('material=' + encodeURIComponent(props.material))
  q.push('rot=' + (props.autoRotate ? '1' : '0'))
  q.push('delay=' + props.rotateDelay)
  q.push('speed=' + encodeURIComponent(props.rotationPerSecond))
  // ⚠️ 不传 orbit：值含编码后的 %（105%25），预览网关二次解码出现裸 %，
  // 导致网关无法构建上游请求（iframe 收到 {"error":"failed to build request"}）。
  // 组件默认 '0deg 90deg 105%' 与 index.html 内置默认一致，省略即等效。
  q.push('autofront=' + (props.autoFront ? '1' : '0'))
  q.push('touch=' + (props.disableInteraction ? '0' : '1'))
  if (props.background) q.push('bg=' + encodeURIComponent(props.background))
  return resolvedViewerPath.value + '?' + q.join('&')
})

function onMessage(e) {
  const d = e.data
  if (!d || typeof d !== 'object') return
  // 原站约定：{ nbmv: id, type: 'loaded'|'error'|'progress', ... }
  if (d.nbmv && d.nbmv !== elId) return
  if (d.type === 'progress') {
    progress.value = d.value || 0
  } else if (d.type === 'loaded') {
    status.value = 'ready'
    emit('loaded', d.payload)
  } else if (d.type === 'error') {
    status.value = 'error'
    errorMsg.value = d.message || '未知错误'
    emit('error', d.message || '未知错误')
  }
}

function onFrameLoad() {
  // iframe 加载完不代表模型加载完，等 postMessage；但先去掉纯白屏遮罩
  window.addEventListener('message', onMessage)
}

window.addEventListener('message', onMessage)
onBeforeUnmount(() => window.removeEventListener('message', onMessage))

defineExpose({
  /** 供父组件调用的三个控制方法，与原站组件 API 对齐 */
  pause() {
    const f = document.querySelector(`iframe[data-nbmv="${elId}"]`)
    if (f && f.contentWindow) f.contentWindow.postMessage({ nbmv: elId, type: 'pause' }, '*')
  },
  resume() {
    const f = document.querySelector(`iframe[data-nbmv="${elId}"]`)
    if (f && f.contentWindow) f.contentWindow.postMessage({ nbmv: elId, type: 'resume' }, '*')
  },
  setSpeed(s) {
    const f = document.querySelector(`iframe[data-nbmv="${elId}"]`)
    if (f && f.contentWindow) f.contentWindow.postMessage({ nbmv: elId, type: 'speed', value: s }, '*')
  },
  resetView() {
    const f = document.querySelector(`iframe[data-nbmv="${elId}"]`)
    if (f && f.contentWindow) f.contentWindow.postMessage({ nbmv: elId, type: 'reset' }, '*')
  },
})
</script>

<style scoped>
.nbmv {
  position: relative;
  width: 100%;
  overflow: hidden;
  background: #f3f4f6;
}
.nbmv__frame {
  width: 100%;
  height: 100%;
  display: block;
  border: 0;
}
.nbmv__mask {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f3f4f6;
}
.nbmv__poster {
  max-width: 70%;
  max-height: 70%;
  object-fit: contain;
}
.nbmv__tip {
  margin-top: 8px;
  font-size: 12px;
  color: #8a94a6;
}
.nbmv__tip--err {
  color: #d03050;
}
</style>