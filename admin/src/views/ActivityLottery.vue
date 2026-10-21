<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <span class="cell-sub">概率为百分比语义（43 = 43%），全部奖品概率总和不超过 100%。</span>
        <span style="flex:1"></span>
        <button v-if="canWrite()" class="btn btn-primary" @click="openCreate">＋ 新建抽奖</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>活动</th><th>消耗积分</th><th>活动时间</th><th>奖品数</th><th>概率总和</th><th>抽奖次数</th><th>状态</th><th>操作</th></tr>
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
              <td class="serif-num">{{ r.points }}</td>
              <td class="cell-sub">{{ r.start_time || '—' }} ~ {{ r.end_time || '—' }}</td>
              <td class="serif-num">{{ (r.prizes || []).length }}</td>
              <td class="serif-num">{{ probSum(r.prizes) }}%</td>
              <td class="serif-num">{{ r.drawCount }}</td>
              <td><span class="tag" :class="r.status === 'active' ? 'tag-green' : 'tag-gray'">{{ r.status === 'active' ? '进行中' : '已暂停' }}</span></td>
              <td style="white-space:nowrap">
                <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="openEdit(r)">编辑</button>
                <button v-if="canWrite()" class="btn btn-ghost btn-sm" style="color:#e02b31" @click="del(r)">删除</button>
                <span v-if="!canWrite()" class="cell-sub">只读</span>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="8"><div class="empty"><div class="glyph">🎁</div>暂无抽奖活动</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 编辑 / 新建 -->
    <div class="modal-mask fadein" v-if="open" @click.self="open = false">
      <form class="glass modal pop" style="max-width:640px" @submit.prevent="save">
        <h3>{{ form.id ? '编辑抽奖' : '新建抽奖' }}</h3>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>活动名称 *</label>
            <input v-model.trim="form.name" class="input" required />
          </div>
          <div>
            <label>单次消耗积分</label>
            <input v-model="form.points" class="input" type="number" min="0" />
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>开始时间</label>
            <input v-model="form.start_time" class="input" placeholder="YYYY-MM-DD HH:MM:SS" />
          </div>
          <div>
            <label>结束时间</label>
            <input v-model="form.end_time" class="input" placeholder="YYYY-MM-DD HH:MM:SS" />
          </div>
        </div>
        <div class="form-row">
          <label>封面图 URL</label>
          <input v-model.trim="form.cover_url" class="input" placeholder="https://…" />
        </div>
        <div class="form-row">
          <label>抽奖规则（支持 HTML）</label>
          <textarea v-model="form.rule" class="input" rows="2"></textarea>
        </div>
        <div class="form-row">
          <div class="checks">
            <label><input type="checkbox" v-model="form.status" /> 启用活动</label>
          </div>
        </div>

        <div class="sec-divider">奖品配置（转盘扇区按排序）</div>
        <div v-for="(p, i) in form.prizes" :key="i" class="prize-row">
          <input v-model.trim="p.name" class="input" placeholder="奖品名称" />
          <select v-model="p.level" class="select">
            <option value="common">普通</option>
            <option value="rare">稀有</option>
            <option value="epic">史诗</option>
            <option value="legend">传说</option>
          </select>
          <input v-model="p.probability" class="input" type="number" step="0.01" min="0" max="100" placeholder="概率%" />
          <input v-model="p.quantity" class="input" type="number" min="0" placeholder="数量" />
          <button type="button" class="btn btn-ghost btn-sm" style="color:#e02b31" @click="form.prizes.splice(i, 1)">删</button>
        </div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <button type="button" class="btn btn-ghost btn-sm" @click="addPrize">＋ 添加奖品</button>
          <span class="cell-sub">概率总和：<b class="serif-num" :style="probSum(form.prizes) > 100 ? 'color:#e02b31' : ''">{{ probSum(form.prizes) }}%</b></span>
        </div>

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

const blank = () => ({
  id: '', name: '', points: 0, start_time: '', end_time: '',
  cover_url: '', rule: '', status: true,
  prizes: [],
})
const form = reactive(blank())

const probSum = ps => Math.round((ps || []).reduce((s, p) => s + Number(p.probability || 0), 0) * 100) / 100

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/activity/lotteries', { params: { pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  Object.assign(form, blank(), { prizes: [] })
  open.value = true
}

function openEdit(r) {
  Object.assign(form, {
    id: r.id,
    name: r.name,
    points: r.points,
    start_time: r.start_time || '',
    end_time: r.end_time || '',
    cover_url: r.cover_url || '',
    rule: r.rule || '',
    status: r.status === 'active',
    prizes: (r.prizes || []).map(p => ({
      name: p.name, level: p.level || 'common', probability: Number(p.probability),
      quantity: p.quantity, image_url: p.image_url || '', sort: p.sort || 0,
    })),
  })
  open.value = true
}

function addPrize() {
  const sort = form.prizes.length + 1
  form.prizes.push({ name: '', level: 'common', probability: 0, quantity: 0, image_url: '', sort })
}

async function save() {
  if (probSum(form.prizes) > 100) {
    toast('奖品概率总和不能超过 100%', true)
    return
  }
  saving.value = true
  try {
    const payload = {
      name: form.name,
      points: Number(form.points || 0),
      start_time: form.start_time,
      end_time: form.end_time,
      cover_url: form.cover_url,
      rule: form.rule,
      status: form.status ? 1 : 0,
      prizes: form.prizes.map((p, i) => ({ ...p, probability: Number(p.probability || 0), sort: p.sort || i + 1 })),
    }
    if (form.id) {
      await http.put(`/activity/lotteries/${form.id}`, payload)
      toast('抽奖活动已更新')
    } else {
      await http.post('/activity/lotteries', payload)
      toast('抽奖活动已创建')
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
  if (!confirm(`确认删除抽奖活动「${r.name}」？`)) return
  try {
    await http.delete(`/activity/lotteries/${r.id}`)
    toast('已删除')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.prize-row { display: grid; grid-template-columns: 1.6fr 0.9fr 0.8fr 0.7fr auto; gap: 8px; margin-bottom: 8px; }
</style>
