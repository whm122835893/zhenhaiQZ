<!--
  收货地址页 —— 复刻原站 /pages/my/info/adress
  原站分包：assets/pages-my-info-adress.MhPnM7ap.js
  scopeId ：data-v-7a259341
  导航标题：收货地址
  注意：原站路径/分包名拼写就是 adress（少一个 d），路由沿用，不改。

  刻意保留的原站瑕疵 / 细节：
  1. 这是「即将开放」占位页，除背景外没有任何 CSS 类，外观全靠内联 style。
  2. 内联样式的声明顺序原样保留，因此最终生效值仍是
     padding-top 被后面的 padding:15px 覆盖、最后 padding-bottom:25px 生效，
     即最终盒模型为 padding:15px 15px 25px。px 值不转 rem，与原站渲染完全一致；
     仅 30rpx 按 1rpx=0.03125rem 写成 .9375rem。
  3. 卡片 class 是空字符串 class=""，照抄。
  4. 文本「 添加新的收货地址 」首尾各有一个半角空格，照抄。
  5. 本页完全不请求任何接口，不加 loading、不做鉴权；点击只弹「即将开放」。
  6. 背景 #f6f7fb 原站挂在 uni-page-body 与 body 上，这里落到外层容器。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="收货地址" />

    <div>
      <div
        class=""
        style="background-color:#fff;justify-content:center;align-items:center;color:#000;padding-top:25px;padding:15px;margin-top:15px;display:flex;border-radius:7px;padding-bottom:25px;margin-left:0.9375rem;margin-right:0.9375rem;"
        @click="tips"
      >
        添加新的收货地址
      </div>
    </div>
  </div>
</template>

<script setup>
import { showToast } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

function tips() {
  showToast({ icon: 'none', title: '即将开放' })
}
</script>

<style scoped>
/* 原站 uni-page-body + body：background-color:#f6f7fb */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f6f7fb;
}
</style>
