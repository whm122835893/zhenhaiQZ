<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <span class="cell-sub">消耗藏品由「合成配方」自动带出，产出藏品从藏品库选择。</span>
        <span style="flex:1"></span>
        <button v-if="canWrite()" class="btn btn-primary" @click="openCreate">＋ 新建配方</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>配方</th><th>消耗 → 产出</th><th>产出数量</th><th>合成期</th><th>状态</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img v-if="r.cover_url" :src="r.cover_url" class="thumb" loading="lazy" @error="e => e.target.style.visibility = 'hidden'" />
                  <div>
                    <div class="cell-main">{{ r.name }}</div>
                    <div class="cell-sub">ID {{ r.id }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div class="cell-sub" style="max-width:380px">
                  <span v-for="(n, i) in r.needs" :key="'n' + n.id">{{ i ? ' + ' : '' }}{{ n.name }}×{{ n.quantity }}</span>
                  <span style="color:var(--blue-deep);font-weight:600"> → </span>
                  <span v-for="(p, i) in r.produces" :key="'p' + p.id">{{ i ? '、' : '' }}{{ p.name }}×{{ p.quantity }}</span>
                </div>
              </td>
              <td class="serif-num">{{ r.merge_quantity }}</td>
              <td class="cell-sub">{{ msToTime(r.start_time) }} ~ {{ msToTime(r.end_time) }}</td>
              <td><span class="tag" :class="Number(r.status) === 1 ? 'tag-green' : 'tag-gray'">{{ Number(r.status) === 1 ? '进行中' : '已暂停' }}</span></td>
              <td style="white-space:nowrap">
                <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="openEdit(r)">编辑</button>
                <button v-if="canWrite()" class="btn btn-ghost btn-sm" style="color:#e02b31" @click="del(r)">删除</button>
                <span v-if="!canWrite()" class="cell-sub">只读</span>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">⚗️</div>暂无合成配方</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 编辑 / 新建 -->
    <div class="modal-mask fadein" v-if="open" @click.self="open = false">
      <form class="glass modal pop" style="max-width:680px" @submit.prevent="save">
        <h3>{{ form.id ? '编辑配方' : '新建配方' }}</h3>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>配方名称 *</label>
            <input v-model.trim="form.name" class="input" required />
          </div>
          <div>
            <label>产出数量</label>
            <input v-model="form.merge_quantity" class="input" type="number" min="1" />
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>开始时间</label>
            <input v-model="form.start_time" class="input" type="datetime-local" />
          </div>
          <div>
            <label>结束时间</label>
            <input v-model="form.end_time" class="input" type="datetime-local" />
          </div>
        </div>
        <div class="form-row">
          <label>封面图 URL</label>
          <input v-model.trim="form.cover_url" class="input" placeholder="https://…" />
        </div>
        <div class="form-row">
          <label>规则说明</label>
          <textarea v-model="form.content" class="input" rows="2"></textarea>
        </div>
        <div class="form-row">
          <div class="checks">
            <label><input type="checkbox" v-model="form.status" /> 启用</label>
            <label><input type="checkbox" v-model="form.is_pre" /> 开启预热期</label>
          </div>
        </div>
        <div class="form-row" v-if="form.is_pre" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>预热开始</label>
            <input v-model="form.pre_start_time" class="input" type="datetime-local" />
          </div>
          <div>
            <label>预热结束</label>
            <input v-model="form.pre_end_time" class="input" type="datetime-local" />
          </div>
        </div>

        <div class="sec-divider">消耗藏品（needs）</div>
        <div v-for="(n, i) in form.needs" :key="'n' + i" class="sub-row">
          <select v-model="n.iid" class="select">
            <option value="" disabled>选择藏品</option>
            <option v-for="it in items" :key="it.iid" :value="it.iid">{{ it.name }}（{{ it.iid }}）</option>
          </select>
          <input v-model="n.quantity" class="input" type="number" min="1" placeholder="数量" />
          <button type="button" class="btn btn-ghost btn-sm" style="color:#e02b31" @click="form.needs.splice(i, 1)">删</button>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:6px" @click="form.needs.push({ iid: '', quantity: 1 })">＋ 添加消耗</button>

        <div class="sec-divider">产出藏品（produces）</div>
        <div v-for="(p, i) in form.produces" :key="'p' + i" class="sub-row">
          <select v-model="p.iid" class="select">
            <option value="" disabled>选择藏品</option>
            <option v-for="it in items" :key="it.iid" :value="it.iid">{{ it.name }}（{{ it.iid }}）</option>
          </select>
          <input v-model="p.quantity" class="input" type="number" min="1" placeholder="数量" />
          <button type="button" class="btn btn-ghost btn-sm" style="color:#e02b31" @click="form.produces.splice(i, 1)">删</button>
        </div>
        <button type="button" class="btn btn-ghost btn-sm" style="margin-bottom:6px" @click="form.produces.push({ iid: '', quantity: 1 })">＋ 添加产出</button>

        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="open = false">取消</button>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? '保存中…' : '保存' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, canWrite } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const open = ref(false)
const saving = ref(false)
const items = ref([])

const blank = () => ({
  id: '', name: '', merge_quantity: 1, cover_url: '', content: '',
  start_time: '', end_time: '', pre_start_time: '', pre_end_time: '',
  is_pre: false, status: true,
  needs: [], produces: [],
})
const form = reactive(blank())

/** 毫秒时间戳 → datetime-local 值 */
function msToLocal(ms) {
  if (!Number(ms)) return ''
  const d = new Date(Number(ms))
  const p = n => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`
}
function msToTime(ms) {
  return msToLocal(ms).replace('T', ' ') || '—'
}

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/activity/merges', { params: { pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

/** 藏品下拉源（取前 200 条，够后台配置用） */
async function loadItems() {
  try {
    const d = await http.get('/items', { params: { pageNo: 1, pageSize: 200 } })
    items.value = d.list || []
  } catch {
    /* 拦截器已 toast */
  }
}

function openCreate() {
  Object.assign(form, blank(), { needs: [], produces: [] })
  open.value = true
}

function openEdit(r) {
  Object.assign(form, {
    id: r.id,
    name: r.name,
    merge_quantity: r.merge_quantity || 1,
    cover_url: r.cover_url || '',
    content: r.content || '',
    start_time: msToLocal(r.start_time),
    end_time: msToLocal(r.end_time),
    pre_start_time: msToLocal(r.pre_start_time),
    pre_end_time: msToLocal(r.pre_end_time),
    is_pre: Number(r.is_pre) === 1,
    status: Number(r.status) === 1,
    needs: (r.needs || []).map(n => ({ iid: n.iid, quantity: n.quantity })),
    produces: (r.produces || []).map(p => ({ iid: p.iid, quantity: p.quantity })),
  })
  open.value = true
}

async function save() {
  if (!form.needs.length || !form.produces.length) {
    toast('请至少配置一个消耗与产出藏品', true)
    return
  }
  if (form.needs.some(n => !n.iid) || form.produces.some(p => !p.iid)) {
    toast('消耗/产出藏品未选完整', true)
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.name,
      merge_quantity: Number(form.merge_quantity || 1),
      cover_url: form.cover_url,
      content: form.content,
      // datetime-local → 'YYYY-MM-DD HH:MM:SS'，后端 toMs 转毫秒
      start_time: form.start_time.replace('T', ' '),
      end_time: form.end_time.replace('T', ' '),
      pre_start_time: form.pre_start_time.replace('T', ' '),
      pre_end_time: form.pre_end_time.replace('T', ' '),
      is_pre: form.is_pre ? 1 : 0,
      status: form.status ? 1 : 0,
      needs: form.needs.map(n => ({ iid: String(n.iid), quantity: Number(n.quantity || 1) })),
      produces: form.produces.map(p => ({ iid: String(p.iid), quantity: Number(p.quantity || 1) })),
    }
    if (form.id) {
      await http.put(`/activity/merges/${form.id}`, payload)
      toast('合成配方已更新')
    } else {
      await http.post('/activity/merges', payload)
      toast('合成配方已创建')
    }
    open.value = false
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

async function del(r) {
  if (!confirm(`确认删除合成配方「${r.name}」？`)) return
  try {
    await http.delete(`/activity/merges/${r.id}`)
    toast('已删除')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(() => {
  load(1)
  loadItems()
})
</script>

<style scoped>
.sub-row { display: grid; grid-template-columns: 1fr 90px auto; gap: 8px; margin-bottom: 8px; }
</style>
