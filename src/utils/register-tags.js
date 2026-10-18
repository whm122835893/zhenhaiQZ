/**
 * 全局 uni-app 标签别名注册
 * 让所有页面可直接使用 <view> <text> <image> <scroll-view> <swiper> 等原站标签，
 * 渲染为等价 HTML，样式类名与原站完全一致。
 */
import { h } from 'vue'

const TAGS = {
  view: 'div',
  text: 'span',
  'scroll-view': 'div',
  swiper: 'div',
  'swiper-item': 'div',
  navigator: 'div',
  'cover-view': 'div',
  'cover-image': 'img',
  label: 'label',
}

function makeTag(tag, htmlTag) {
  return {
    name: tag,
    inheritAttrs: false,
    setup(_props, { slots, attrs }) {
      return () => {
        const a = { ...attrs }
        a.onClick = (e) => attrs.onClick && attrs.onClick(e)
        if (attrs.modelValue !== undefined) {
          a.value = attrs.modelValue
          a.onInput = (e) => attrs['onUpdate:modelValue'] && attrs['onUpdate:modelValue'](e.target.value)
        }
        return h(htmlTag, a, slots.default ? slots.default() : null)
      }
    },
  }
}

function makeImage() {
  return {
    name: 'image',
    inheritAttrs: false,
    setup(_props, { slots, attrs }) {
      return () => {
        const a = { ...attrs }
        a.onClick = (e) => attrs.onClick && attrs.onClick(e)
        if (attrs.onTap) a.onClick = (e) => attrs.onTap(e)
        // aspectFill / aspectFit 等 mode 通过 data-mode 保留，交由 CSS 处理
        return h('img', { ...a, alt: attrs.alt || '' }, slots.default ? slots.default() : null)
      }
    },
  }
}

export function registerUniTags(app) {
  Object.entries(TAGS).forEach(([tag, html]) => {
    app.component(tag, makeTag(tag, html))
  })
  app.component('image', makeImage())
  // scroll-view 横向滚动
  app.directive('scroll-x', {
    mounted(el) { el.style.overflowX = 'auto'; el.style.webkitOverflowScrolling = 'touch' },
  })
}