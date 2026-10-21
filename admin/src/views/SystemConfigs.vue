<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <div>
          <div class="card-head" style="margin-bottom:2px">运营参数<span>RUNTIME CONFIGS</span></div>
          <div class="cell-sub">修改后立即生效（DB 优先，文件兜底），所有变更落操作日志</div>
        </div>
        <div class="spacer"></div>
        <button v-if="canWrite()" class="btn btn-primary" :disabled="!dirty || saving" @click="save">
          {{ saving ? '保存中…' : dirty ? '保存修改' : '暂无改动' }}
        </button>
      </div>

      <div class="cfg-grid">
        <div v-for="c in rows" :key="c.config_key" class="cfg-cell">
          <div class="k">{{ c.config_key }}</div>
          <div class="rm">{{ c.remark || '—' }}</div>
          <input v-model.trim="c._value" class="input" :disabled="!canWrite()" @input="mark(c)" />
          <div class="cell-sub" style="margin-top:5px">更新于 {{ fmtTime(c.updated_at) }}</div>
        </div>
      </div>
      <div v-if="!loading && rows.length === 0" class="empty"><div class="glyph">⚙️</div>暂无运营参数</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import http, { toast, fmtTime, canWrite } from '../api'

const rows = ref([])
const loading = ref(true)
const saving = ref(false)
const dirtyKeys = ref(new Set())

const dirty = computed(() => dirtyKeys.value.size > 0)

function mark(c) {
  if (c._value !== c.config_value) dirtyKeys.value.add(c.config_key)
  else dirtyKeys.value.delete(c.config_key)
  dirtyKeys.value = new Set(dirtyKeys.value) // 触发响应
}

async function load() {
  loading.value = true
  try {
    const data = await http.get('/system/configs')
    rows.value = (data.list || []).map(r => ({ ...r, _value: r.config_value }))
    dirtyKeys.value = new Set()
  } finally {
    loading.value = false
  }
}

async function save() {
  const items = rows.value
    .filter(r => dirtyKeys.value.has(r.config_key))
    .map(r => ({ key: r.config_key, value: r._value }))
  if (items.length === 0) return

  saving.value = true
  try {
    const res = await http.put('/system/configs', { items })
    toast(`已保存 ${res.changed?.length ?? items.length} 项参数`)
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
