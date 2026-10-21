<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="card-head">首页轮播<span>BANNERS</span></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>图片</th><th>标题</th><th>跳转</th><th>位置</th><th>排序</th><th>状态</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="b in list" :key="b.id">
              <td>
                <img v-if="b.imageUrl" :src="b.imageUrl" class="thumb" style="width:64px;height:32px;border-radius:7px" loading="lazy"
                     @error="e => e.target.style.visibility = 'hidden'" />
                <div v-else class="thumb" style="width:64px;height:32px;border-radius:7px;display:flex;align-items:center;justify-content:center;color:#9fb4cf">无图</div>
              </td>
              <td class="cell-main">{{ b.title || '—' }}</td>
              <td>
                <div class="cell-sub">类型 {{ b.link_type }}</div>
                <div class="cell-sub" style="max-width:220px;overflow:hidden;text-overflow:ellipsis">{{ b.link_value || '—' }}</div>
              </td>
              <td class="serif-num">{{ b.position }}</td>
              <td class="serif-num">{{ b.sort }}</td>
              <td>
                <span class="tag" :class="Number(b.status) === 1 ? 'tag-green' : 'tag-gray'">{{ Number(b.status) === 1 ? '启用' : '停用' }}</span>
              </td>
              <td>
                <div style="display:flex;align-items:center;gap:8px">
                  <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="openEdit(b)">编辑</button>
                  <span v-if="canWrite()"
                        class="toggle" :class="{ on: Number(b.status) === 1 }"
                        :title="Number(b.status) === 1 ? '点击停用' : '点击启用'"
                        @click="toggleStatus(b)"></span>
                  <span v-if="!canWrite()" class="cell-sub">只读</span>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="7"><div class="empty"><div class="glyph">🎞️</div>暂无轮播位</div></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 编辑 -->
    <div class="modal-mask fadein" v-if="edit" @click.self="edit = null">
      <form class="glass modal pop" @submit.prevent="save">
        <h3>编辑轮播 <span class="cell-sub">#{{ edit.id }}</span></h3>
        <div class="form-row">
          <label>标题</label>
          <input v-model.trim="form.title" class="input" />
        </div>
        <div class="form-row">
          <label>跳转值（藏品 iid / 链接）</label>
          <input v-model.trim="form.link_value" class="input" />
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div>
            <label>位置</label>
            <input v-model="form.position" class="input" type="number" min="0" />
          </div>
          <div>
            <label>排序</label>
            <input v-model="form.sort" class="input" type="number" min="0" />
          </div>
          <div>
            <label>状态</label>
            <select v-model="form.status" class="select" style="width:100%">
              <option value="1">启用</option>
              <option value="0">停用</option>
            </select>
          </div>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="edit = null">取消</button>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? '保存中…' : '保存' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, canWrite } from '../api'

const list = ref([])
const loading = ref(true)
const edit = ref(null)
const saving = ref(false)
const form = reactive({})

async function load() {
  loading.value = true
  try {
    const data = await http.get('/cms/banners')
    list.value = data.list || []
  } finally {
    loading.value = false
  }
}

function openEdit(b) {
  edit.value = b
  Object.assign(form, {
    title: b.title || '',
    link_value: b.link_value || '',
    position: b.position,
    sort: b.sort,
    status: String(b.status),
  })
}

async function save() {
  saving.value = true
  try {
    await http.put(`/cms/banners/${edit.value.id}`, {
      title: form.title,
      link_value: form.link_value,
      position: Number(form.position) || 0,
      sort: Number(form.sort) || 0,
      status: Number(form.status),
    })
    toast('轮播已更新')
    edit.value = null
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

async function toggleStatus(b) {
  const next = Number(b.status) === 1 ? 0 : 1
  try {
    await http.put(`/cms/banners/${b.id}`, { status: next })
    b.status = next
    toast(next === 1 ? '已启用' : '已停用')
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(load)
</script>
