<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <div>
          <div class="card-head" style="margin-bottom:2px">藏品分类<span>CATEGORIES</span></div>
          <div class="cell-sub">C 端市场页分类 tab、首页「分类」入口、藏品表单下拉共用；排序越小越靠前</div>
        </div>
        <div class="spacer"></div>
        <button v-if="canWrite()" class="btn btn-primary" @click="openAdd">＋ 新建分类</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th style="width:70px">排序</th><th>分类名称</th><th>藏品数量</th><th style="width:90px">状态</th><th style="width:170px">操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td class="serif-num">{{ r.sort }}</td>
              <td class="cell-main">{{ r.name }}</td>
              <td class="serif-num">{{ r.itemCount }}</td>
              <td><span class="tag" :class="r.status === 'enabled' ? 'tag-green' : 'tag-gray'">{{ r.status === 'enabled' ? '启用' : '停用' }}</span></td>
              <td>
                <div style="display:flex;gap:6px">
                  <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="openEdit(r)">编辑</button>
                  <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="toggleStatus(r)">{{ r.status === 'enabled' ? '停用' : '启用' }}</button>
                  <button v-if="canWrite()" class="btn btn-danger btn-sm" @click="del(r)">删除</button>
                  <span v-if="!canWrite()" class="cell-sub">只读</span>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="5"><div class="empty"><div class="glyph">🗂</div>暂无分类，点击右上角新建</div></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 新建 / 编辑 -->
    <div class="modal-mask fadein" v-if="edit" @click.self="edit = null">
      <form class="glass modal pop" style="width:440px" @submit.prevent="save">
        <h3>{{ form.id ? '编辑分类' : '新建分类' }}</h3>
        <div class="form-row">
          <label>分类名称 *</label>
          <input v-model.trim="form.name" class="input" maxlength="16" placeholder="例如：国风艺术" />
          <div class="cell-sub" style="margin-top:6px">最多 16 个字，将展示在 C 端市场页 tab 上</div>
        </div>
        <div class="form-row">
          <label>排序</label>
          <input v-model.number="form.sort" class="input" type="number" placeholder="0" />
          <div class="cell-sub" style="margin-top:6px">数字越小越靠前</div>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="edit = null">取消</button>
          <button class="btn btn-primary" :disabled="!form.name || saving">{{ saving ? '保存中…' : '保存' }}</button>
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
const form = reactive({ id: '', name: '', sort: 0 })

async function load() {
  loading.value = true
  try {
    const data = await http.get('/categories')
    list.value = data.list || []
  } catch {
    /* 拦截器已 toast */
  } finally {
    loading.value = false
  }
}

function openAdd() {
  form.id = ''
  form.name = ''
  form.sort = list.value.length + 1
  edit.value = true
}

function openEdit(r) {
  form.id = r.id
  form.name = r.name
  form.sort = r.sort
  edit.value = true
}

async function save() {
  saving.value = true
  try {
    if (form.id) {
      await http.put(`/categories/${form.id}`, { name: form.name, sort: form.sort })
      toast('分类已更新')
    } else {
      await http.post('/categories', { name: form.name, sort: form.sort })
      toast('分类已创建')
    }
    edit.value = null
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

/** 停用后 C 端市场页 tab 立即隐藏（数据保留，可随时恢复） */
async function toggleStatus(r) {
  const target = r.status === 'enabled' ? 'disabled' : 'enabled'
  try {
    await http.put(`/categories/${r.id}`, { status: target })
    toast(target === 'disabled' ? '已停用，C 端市场页 tab 已隐藏' : '已启用')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

async function del(r) {
  if (r.itemCount > 0) {
    toast(`该分类下还有 ${r.itemCount} 个藏品，无法删除`, true)
    return
  }
  if (!confirm(`确定删除分类「${r.name}」？`)) return
  try {
    await http.delete(`/categories/${r.id}`)
    toast('分类已删除')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(load)
</script>
