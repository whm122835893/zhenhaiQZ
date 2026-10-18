<template>
  <div v-if="show" class="uv-empty" :style="{ marginTop: addUnit(marginTop) }">
    <span class="uv-icon__icon" :style="{ fontSize: addUnit(iconSize), color: iconColor }">{{ glyph }}</span>
    <div class="uv-empty__text" :style="{ color: textColor, fontSize: addUnit(textSize) }">
      {{ text || icons[mode] }}
    </div>
    <slot />
  </div>
</template>

<script setup>
/**
 * uv-ui <uv-empty> 等价实现（严格对齐原站 pages-market-index 内联的 R 组件）
 * 图标取自原站 iconfont：`h["uvicon-" + name]` → unescape("%uXXXX") 的字符本体
 */
const props = defineProps({
  icon: { type: String, default: '' },
  text: { type: String, default: '' },
  textColor: { type: String, default: '#c0c4cc' },
  textSize: { type: [String, Number], default: 14 },
  iconColor: { type: String, default: '#c0c4cc' },
  iconSize: { type: [String, Number], default: 90 },
  mode: { type: String, default: 'data' },
  width: { type: [String, Number], default: 160 },
  height: { type: [String, Number], default: 160 },
  show: { type: Boolean, default: true },
  marginTop: { type: [String, Number], default: 0 },
})

const icons = {
  car: '购物车为空',
  page: '页面不存在',
  search: '没有搜索结果',
  address: '没有收货地址',
  'wifi-off': '没有WiFi',
  order: '订单为空',
  coupon: '没有优惠券',
  favor: '暂无收藏',
  permission: '无权限',
  history: '无历史记录',
  news: '无新闻列表',
  message: '消息列表为空',
  list: '列表为空',
  data: '数据为空',
  comment: '暂无评论',
}

// 原站 uv-icon 组件内联映射表（节选自 uv-icon.B8o1p19t.js）
const CODES = {
  'empty-list': 0xe672,
  'empty-data': 0xe671,
  'empty-search': 0xe677,
  'empty-favor': 0xe662,
  'empty-order': 0xe66b,
  'empty-car': 0xe657,
  'empty-address': 0xe68a,
  'empty-history': 0xe685,
  'empty-news': 0xe67e,
  'empty-permission': 0xe67d,
  'empty-page': 0xe60e,
  'empty-coupon': 0xe69b,
  'empty-wifi-off': 0xe6cc,
  chat: 0xe8b7,
}

const name = props.icon ? '' : props.mode === 'message' ? 'chat' : 'empty-' + props.mode
const glyph = props.icon ? '' : String.fromCodePoint(CODES[name] ?? CODES['empty-' + props.mode] ?? 0xe672)

// uv $uv.addUnit：数字默认按 px 处理，此处原站 iconSize 传 90（px 语义）→ 直接输出 px
function addUnit(v) {
  if (v === 0 || v == null) return String(v)
  return typeof v === 'number' ? (v / 32) + 'rem' : String(v)
}
</script>

<style scoped>
.uv-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.uv-icon__icon {
  font-family: uvicon-iconfont;
  line-height: 1;
  margin-top: 0.4375rem;
}
.uv-empty__text {
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  margin-top: 0.625rem;
}
</style>
