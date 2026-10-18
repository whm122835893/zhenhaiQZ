import { h } from 'vue'

/**
 * 原站 uni-app 内置标签的轻量等价实现。
 * 通过 vite 的 @vitejs/plugin-vue 模板编译配置，这些标签会被解析为下列组件，
 * 从而让页面代码保持与原站一致的 <view>/<text>/<image>/<scroll-view> 写法。
 */

const common = {
  inheritAttrs: false,
}

function el(tag, htmlTag) {
  return {
    ...common,
    name: tag,
    setup(_props, { slots, attrs }) {
      return () => {
        const a = { ...attrs }
        a.onClick = (e) => attrs.onClick && attrs.onClick(e)
        if (attrs.onTap) a.onClick = (e) => attrs.onTap(e)
        if (attrs.modelValue !== undefined) {
          a.value = attrs.modelValue
          a.onInput = (e) => {
            if (attrs['onUpdate:modelValue']) attrs['onUpdate:modelValue'](e.target.value)
            if (attrs.onInput) attrs.onInput(e)
          }
        }
        return h(htmlTag, a, slots.default ? slots.default() : null)
      }
    },
  }
}

/** uni-app <image> → <img>，并保留 mode（aspectFill / aspectFit）语义 */
export const UniImage = {
  ...common,
  name: 'image',
  props: {
    src: String,
    mode: String,
    width: [String, Number],
    height: [String, Number],
    round: Boolean,
  },
  setup(props, { slots, attrs }) {
    return () => {
      const a = { ...attrs, src: props.src, alt: attrs.alt || '' }
      a.onClick = (e) => attrs.onClick && attrs.onClick(e)
      if (attrs.onTap) a.onClick = (e) => attrs.onTap(e)
      if (props.width) a.style = { ...(attrs.style || {}), width: addUnit(props.width) }
      if (props.height) a.style = { ...(attrs.style || {}), height: addUnit(props.height) }
      if (props.mode) a['data-mode'] = props.mode
      return h('img', a, slots.default ? slots.default() : null)
    }
  },
}

function addUnit(v) {
  return typeof v === 'number' ? `${v}px` : v
}

export const UniView = el('view', 'div')
export const UniText = el('text', 'span')
export const UniLabel = el('label', 'label')
export const UniNavigator = el('navigator', 'div')
export const UniCoverView = el('cover-view', 'div')
export const UniForm = el('form', 'form')
export const UniInput = el('input', 'input')
export const UniTextarea = el('textarea', 'textarea')
export const UniButton = el('button', 'button')
export const UniRichText = el('rich-text', 'div')

/** <scroll-view>：支持 scroll-x / scroll-y / scroll-into-view */
export const UniScrollView = {
  ...common,
  name: 'scroll-view',
  props: {
    scrollX: { type: [Boolean, String], default: false },
    scrollY: { type: [Boolean, String], default: false },
    scrollTop: [Number, String],
    scrollIntoView: String,
    scrollWithAnimation: { type: [Boolean, String], default: false },
    showScrollbar: { type: [Boolean, String], default: false },
    upperThreshold: [Number, String],
    lowerThreshold: [Number, String],
  },
  emits: ['scroll', 'scrolltolower', 'scrolltoupper'],
  setup(props, { slots, attrs }) {
    return () => {
      const isX = props.scrollX === true || props.scrollX === 'true'
      const a = { ...attrs }
      a.style = {
        ...(attrs.style || {}),
        overflowX: isX ? 'auto' : 'hidden',
        overflowY: isX ? 'hidden' : 'auto',
        WebkitOverflowScrolling: 'touch',
      }
      a.onScroll = (e) => attrs.onScroll && attrs.onScroll(e)
      if (attrs.class) a.class = attrs.class
      return h('div', a, slots.default ? slots.default() : null)
    }
  },
}

/** <swiper> / <swiper-item> */
export const UniSwiper = {
  ...common,
  name: 'swiper',
  props: {
    indicatorDots: { type: [Boolean, String], default: false },
    autoplay: { type: [Boolean, String], default: false },
    circular: { type: [Boolean, String], default: false },
    vertical: { type: [Boolean, String], default: false },
    interval: { type: [Number, String], default: 5000 },
    duration: { type: [Number, String], default: 500 },
    current: [Number, String],
  },
  setup(props, { slots, attrs }) {
    return () => {
      const a = { ...attrs }
      a.dataset = {
        autoplay: props.autoplay ? '1' : '',
        vertical: props.vertical ? '1' : '',
        duration: String(props.duration),
      }
      a.onClick = (e) => attrs.onClick && attrs.onClick(e)
      return h('div', a, slots.default ? slots.default() : null)
    }
  },
}
export const UniSwiperItem = el('swiper-item', 'div')