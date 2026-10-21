<template>
  <div class="rich-editor">
    <div class="re-toolbar">
      <button type="button" title="标题" @click="fmt('formatBlock', 'h3')">H</button>
      <button type="button" title="正文" @click="fmt('formatBlock', 'p')">P</button>
      <span class="re-sep"></span>
      <button type="button" class="b" title="加粗" @click="fmt('bold')"><b>B</b></button>
      <button type="button" title="斜体" @click="fmt('italic')"><i>I</i></button>
      <button type="button" title="下划线" @click="fmt('underline')"><u>U</u></button>
      <span class="re-sep"></span>
      <button type="button" title="无序列表" @click="fmt('insertUnorderedList')">•≡</button>
      <button type="button" title="有序列表" @click="fmt('insertOrderedList')">1≡</button>
      <button type="button" title="引用" @click="fmt('formatBlock', 'blockquote')">❝</button>
      <span class="re-sep"></span>
      <button type="button" title="插入图片" :disabled="uploading" @click="pickImage">
        {{ uploading ? '…' : '🖼' }}
      </button>
      <button type="button" title="插入链接" @click="insertLink">🔗</button>
      <button type="button" title="清除格式" @click="fmt('removeFormat')">✕</button>
    </div>
    <div
      ref="box"
      class="re-body"
      contenteditable="true"
      @input="onInput"
      @blur="saveRange"
    ></div>
    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="uploadImage" />
  </div>
</template>

<script setup>
/**
 * 轻量富文本编辑器（无第三方依赖）
 * contenteditable + document.execCommand（已废弃但全浏览器可用，编辑场景稳定）
 * 产出 HTML 与 C 端公告详情页 v-html 渲染对齐；插图复用 /upload/image 上传
 */
import { onMounted, ref, watch } from 'vue'
import http, { toast } from '../api'

const props = defineProps({
  modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const box = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
let savedRange = null

onMounted(() => {
  box.value.innerHTML = props.modelValue || ''
})

// 外部值变化时同步（仅当与当前内容不同，避免打字时光标跳动）
watch(() => props.modelValue, (v) => {
  if (box.value && v !== box.value.innerHTML) box.value.innerHTML = v || ''
})

function onInput() {
  emit('update:modelValue', box.value.innerHTML)
}

function saveRange() {
  const sel = window.getSelection()
  if (sel && sel.rangeCount > 0 && box.value.contains(sel.getRangeAt(0).commonAncestorContainer)) {
    savedRange = sel.getRangeAt(0).cloneRange()
  }
}

function restoreRange() {
  box.value.focus()
  const sel = window.getSelection()
  sel.removeAllRanges()
  if (savedRange) sel.addRange(savedRange)
}

function fmt(cmd, val = null) {
  saveRange()
  restoreRange()
  document.execCommand(cmd, false, val)
  emit('update:modelValue', box.value.innerHTML)
}

function pickImage() {
  fileInput.value.click()
}

async function uploadImage(e) {
  const f = e.target.files[0]
  e.target.value = ''
  if (!f) return
  if (f.size > 5 * 1024 * 1024) {
    toast('图片不能超过 5MB', true)
    return
  }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', f)
    const d = await http.post('/upload/image', fd)
    restoreRange()
    // C 端详情页对正文 img 注入 width:100%，编辑器内同样块级铺满
    document.execCommand('insertHTML', false, `<img src="${d.url}" style="width:100%" />`)
    emit('update:modelValue', box.value.innerHTML)
    toast('图片已插入')
  } catch {
    /* 拦截器已 toast */
  } finally {
    uploading.value = false
  }
}

function insertLink() {
  const url = prompt('请输入链接地址（https://…）')
  if (!url) return
  const sel = window.getSelection()
  if (sel && String(sel).length > 0) {
    fmt('createLink', url)
  } else {
    restoreRange()
    document.execCommand('insertHTML', false, `<a href="${url}" target="_blank">${url}</a>`)
    emit('update:modelValue', box.value.innerHTML)
  }
}
</script>

<style scoped>
.rich-editor { border: 1px solid rgba(14, 24, 56, 0.12); border-radius: 12px; overflow: hidden; background: #fff; }
.re-toolbar {
  display: flex; align-items: center; gap: 2px; flex-wrap: wrap;
  padding: 6px 8px; background: rgba(240, 246, 255, 0.85);
  border-bottom: 1px solid rgba(14, 24, 56, 0.08);
}
.re-toolbar button {
  min-width: 28px; height: 26px; padding: 0 6px; border: none; border-radius: 7px;
  background: transparent; color: #27466e; font-size: 13px; cursor: pointer; line-height: 1;
}
.re-toolbar button:hover { background: rgba(96, 148, 226, 0.16); }
.re-toolbar button:disabled { opacity: 0.5; cursor: default; }
.re-sep { width: 1px; height: 16px; background: rgba(14, 24, 56, 0.14); margin: 0 4px; }
.re-body {
  min-height: 170px; max-height: 330px; overflow-y: auto;
  padding: 12px 14px; font-size: 14px; line-height: 1.75; color: #1c2b45;
  outline: none; word-break: break-word;
}
.re-body :deep(h3) { font-size: 16px; margin: 10px 0 6px; }
.re-body :deep(p) { margin: 0 0 6px; }
.re-body :deep(blockquote) {
  margin: 8px 0; padding: 6px 12px; border-left: 3px solid #8fb4e8;
  background: rgba(143, 180, 232, 0.12); color: #41547a;
}
.re-body :deep(ul), .re-body :deep(ol) { margin: 6px 0; padding-left: 22px; }
.re-body :deep(img) { max-width: 100%; border-radius: 8px; display: block; margin: 6px auto; }
.re-body:empty::before { content: attr(data-ph); color: #a8b8cf; }
</style>
