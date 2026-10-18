<template>
  <!--
    WebView 容器 —— 对应原站 /pages/index/webView

    原站实现（assets/pages-index-webView.C_v8dDkB.js）全文逻辑只有三句：
      1. onLoad 取 query.src / query.title
      2. 有 src → 设置导航标题 + 渲染 <web-view :src="src">
         无 src → reLaunch 回 /pages/index/index
      3. 监听 message 事件，收到 { data:{ arg:{ type:'goIndex' } } } 时跳 /pages/my/index

    ⚠️ 之前这里写的是**无条件** router.replace('/pages/index/index')，与原站行为不符：
       原站只有「缺 src」才回首页。带 src 访问时路由必须保持不变。
  -->
  <iframe v-if="src" class="nb-webview" :src="src" @load="onIframeLoad" />
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

/** 原站 data.src —— 由 query.src 初始化 */
const src = ref('')

/** 原站 goIndex 消息的载荷：{ data: { arg: { type: 'goIndex' } } } */
function isGoIndex(payload) {
  return payload?.data?.arg?.type === 'goIndex'
}

/**
 * 原站两个 message 监听点：
 *   window.onmessage                —— 直接挂 window
 *   <web-view @message>             —— 组件级事件
 * uni-app 的 <web-view> 组件在 H5 端编译成 iframe + postMessage 桥接，
 * 事件对象的形态是 event.detail.data[0].type（桥接转发），
 * 但页面自身也可能直接往 window.postMessage，故两处都判。
 */
function handleMessage(event) {
  const detail = event?.detail?.data
  // 桥接形态：event.detail.data[0].type === 'goIndex'
  if (Array.isArray(detail) && detail[0]?.type === 'goIndex') {
    router.replace('/pages/my/index')
    return
  }
  // window.postMessage 形态：event.data.data.arg.type === 'goIndex'
  if (isGoIndex(event?.data)) {
    router.replace('/pages/my/index')
  }
}

function onIframeLoad() {
  handleMessage({ data: { data: {} } })
}

onMounted(() => {
  const q = route.query
  if (q.src) {
    src.value = String(q.src)
    if (q.title) document.title = String(q.title)
    window.addEventListener('message', handleMessage)
  } else {
    // 原站：无 src 直接回首页
    router.replace('/pages/index/index')
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('message', handleMessage)
})
</script>

<style scoped>
/* uni-app <web-view> 在 H5 端编译为占满视口的 iframe */
.nb-webview {
  display: block;
  width: 100%;
  height: 100vh;
  border: none;
}
</style>
