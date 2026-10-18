<template>
  <i class="uvicon" :class="'uvicon-' + name" :style="style">{{ glyph }}</i>
</template>

<script setup>
/**
 * uv-icon 图标 —— 等价实现原站 uv-ui <uv-icon>
 * 字符码映射来自原站 uv-icon.B8o1p19t.js
 * 字体文件为原站 /assets/uvicons-DzZuDpaw.ttf（已在 src/styles/iconfont.css 注册）
 */
import { computed } from 'vue'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [String, Number], default: '' },
  color: { type: String, default: '' },
})

/** 原站图标名 → 字符码 */
const CODES = {
  'arrow-left': 0xe646,
  'arrow-right': 0xe63c,
  'arrow-leftward': 0xe63b,
  'arrow-rightward': 0xe644,
  'arrow-up': 0xe63d,
  'arrow-down': 0xe63e,
  share: 0xe629,
  'share-fill': 0xe6bb,
  'share-square': 0xe6c4,
  'empty-list': 0xe672,
  'empty-data': 0xe671,
  'empty-search': 0xe677,
  chat: 0xe656,
  'chat-fill': 0xe657,
}

const style = computed(() => {
  const s = {}
  const fs = props.size === '' ? '16px' : typeof props.size === 'number' ? `${props.size}px` : props.size
  s.fontSize = fs
  s.lineHeight = fs
  if (props.color) s.color = props.color
  return s
})

const glyph = computed(() => String.fromCodePoint(CODES[props.name] ?? 0xe672))
</script>

<style scoped>
.uvicon {
  display: inline-block;
  font-family: uvicon-iconfont;
  font-style: normal;
  font-weight: normal;
  position: relative;
  top: 0;
  vertical-align: middle;
}
</style>