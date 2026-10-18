<!--
  协议管理页 —— 复刻原站 /pages/my/info/xieyi
  原站分包：assets/pages-my-info-xieyi.CUq6g50-.js
  样式文件：assets/xieyi-DNeZmVf2.css
  scopeId ：data-v-17be679e
  导航标题：协议管理（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. class 名 `flex-bettwen` 是原站拼写错误（应为 between），原样保留、不改。
  2. 两个列表项容器的样式全部走内联 style（原站就是内联），故 CSS 里只有 flex 工具类
     与 .one-line，容器样式一律写在 style 属性上。
  3. 文案「 使用条款 」「 隐私协议 」首尾各带一个半角空格，是原站编译产物里
     staticText 的真实内容，原样保留。
  4. 本页不请求任何接口。
  5. 跳转地址用 config.frontUrl 拼接，本项目等价替换为 @/api/request 导出的
     FRONT_URL（值完全一致：https://h5.shengtai.cyou）。
  6. 内联 rpx 已按 1rpx = 0.03125rem 换算：
       24rpx→.75rem  28rpx→.875rem  16rpx→.5rem  104rpx→3.25rem  702rpx→21.9375rem
-->
<template>
  <!-- 页面底色（uni-page-body #f3f6f8）；原站页面根节点自身无 class -->
  <div class="uni-page-body">
    <UniPageHead title="协议管理" />

    <div class="flex-bettwen" style="margin: .75rem auto 0; width: 21.9375rem; border-radius: .75rem; background-color: #fff; height: 3.25rem; padding: 0 .75rem; box-sizing: border-box" @click="goPage(1)">
      <span style="font-weight: 500; font-size: .875rem"> 使用条款 </span>
      <div class="flex-start" style="gap: .5rem">
        <img
          src="../../assets/my/xieyi-icon.png"
          alt=""
          style="width: .75rem"
        />
      </div>
    </div>

    <div class="flex-bettwen" style="margin: .75rem auto 0; width: 21.9375rem; border-radius: .75rem; background-color: #fff; height: 3.25rem; padding: 0 .75rem; box-sizing: border-box" @click="goPage(2)">
      <span style="font-weight: 500; font-size: .875rem"> 隐私协议 </span>
      <div class="flex-start" style="gap: .5rem">
        <img
          src="../../assets/my/xieyi-icon.png"
          alt=""
          style="width: .75rem"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 协议管理 —— 复刻原站 pages-my-info-xieyi.CUq6g50-.js（scopeId data-v-17be679e）
 * 本页无任何接口请求。
 */
import { useRouter } from 'vue-router'
// 原站此处用 config.frontUrl；本项目 @/api/request 导出的 FRONT_URL 值完全一致
// （https://h5.shengtai.cyou），属等价替换。
import { FRONT_URL } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

const router = useRouter()

/** 原站 goPage(e)：1 → 使用条款，2 → 隐私协议 */
function goPage(type) {
  if (type === 1) {
    router.push(
      '/pages/index/webView?title=使用条款&src=' +
        FRONT_URL +
        '/static/html/service.html'
    )
  }
  if (type === 2) {
    router.push(
      '/pages/index/webView?title=隐私协议&src=' +
        FRONT_URL +
        '/static/html/privacy.html'
    )
  }
}
</script>

<style scoped>
/* 页面底色：原站 uni-page-body 与 body 同为 #f3f6f8 */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f3f6f8;
}

/* 以下为 xieyi-DNeZmVf2.css 全文，原样抄（已是 rem 值，不再换算） */
.flex-bettwen{display:flex;justify-content:space-between;align-items:center}
.flex-start{display:flex;justify-content:flex-start;align-items:center}
.flex-center{display:flex;justify-content:center;align-items:center}
.flex-space-between{display:flex;justify-content:space-between;align-items:center}
.one-line{text-overflow:ellipsis;overflow:hidden;word-break:break-all;white-space:nowrap}
</style>