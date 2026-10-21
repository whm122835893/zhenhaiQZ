<template>
  <div>
    <!-- 统计概览 -->
    <div class="stat-row">
      <div class="glass stat-card rise"><div class="sc-num">{{ stats.total }}</div><div class="sc-label">任务总数</div></div>
      <div class="glass stat-card rise" style="--i:1"><div class="sc-num">{{ stats.pendingAudit }}</div><div class="sc-label">待审核</div></div>
      <div class="glass stat-card rise" style="--i:2"><div class="sc-num">{{ stats.running }}</div><div class="sc-label">进行中</div></div>
      <div class="glass stat-card rise" style="--i:3"><div class="sc-num">{{ stats.pieces }}</div><div class="sc-label">累计发放份数</div></div>
    </div>

    <!-- 工具条 -->
    <div class="glass toolbar rise">
      <div class="chips">
        <span class="chip" :class="{ on: f.status === -1 }" @click="f.status = -1; load()">全部</span>
        <span v-for="s in statusOptions" :key="s.v" class="chip" :class="{ on: f.status === s.v }" @click="f.status = s.v; load()">{{ s.t }}</span>
      </div>
      <div class="chips" style="margin-left:8px">
        <span v-for="t in typeOptions" :key="t.v" class="chip chip-blue" :class="{ on: f.type === t.v }" @click="f.type = t.v; load()">{{ t.t }}</span>
      </div>
      <div style="flex:1"></div>
      <button v-if="canWrite" class="btn btn-primary" @click="openCreate">+ 创建发放任务</button>
    </div>

    <!-- 任务列表 -->
    <div class="glass table-card rise">
      <table class="tbl">
        <thead>
          <tr>
            <th>任务</th><th>藏品</th><th>目标人群</th><th>进度</th><th>状态</th><th>创建 / 审核</th><th style="width:250px">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!list.length"><td colspan="7" class="empty">暂无任务</td></tr>
          <tr v-for="t in list" :key="t.id">
            <td>
              <div class="cell-main">{{ t.name }}</div>
              <div class="cell-sub"><span class="tag" :class="typeTag(t.type)">{{ t.typeName }}</span>
                <span v-if="t.ticketNo" class="tag tag-gray">工单 {{ t.ticketNo }}</span>
              </div>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <img v-if="t.itemImage" :src="t.itemImage" class="row-img" />
                <span class="cell-main">{{ t.itemName || '—' }}</span>
              </div>
            </td>
            <td>
              <div class="cell-main">{{ t.targetTypeName }}</div>
              <div class="cell-sub">{{ ruleSummary(t) }}</div>
            </td>
            <td style="min-width:150px">
              <div class="progress-wrap">
                <div class="progress-bar"><div class="progress-in" :style="{ width: progressPct(t) }"></div></div>
                <div class="cell-sub">{{ t.granted_quantity || 0 }}{{ t.total_quantity ? ' / ' + t.total_quantity : '' }} 份</div>
              </div>
            </td>
            <td><span class="tag" :class="statusTag(t.status)">{{ t.statusName }}</span></td>
            <td>
              <div class="cell-main">{{ t.creatorName || '—' }}</div>
              <div class="cell-sub">{{ t.auditorName ? '审：' + t.auditorName : '' }}</div>
            </td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <button class="btn btn-ghost btn-sm" @click="openDetail(t)">详情</button>
                <template v-if="writable">
                  <button v-if="[0, 6].includes(t.status)" class="btn btn-ghost btn-sm" @click="openEdit(t)">编辑</button>
                  <button v-if="[0, 6].includes(t.status)" class="btn btn-blue btn-sm" @click="submit(t)">提交审核</button>
                  <button v-if="t.status === 1" class="btn btn-green btn-sm" :disabled="t.creatorId === me.id" :title="t.creatorId === me.id ? '不能审核自己创建的任务' : ''" @click="audit(t, true)">通过</button>
                  <button v-if="t.status === 1" class="btn btn-red btn-sm" :disabled="t.creatorId === me.id" @click="audit(t, false)">驳回</button>
                  <button v-if="t.status === 2" class="btn btn-primary btn-sm" @click="execute(t)">执行发放</button>
                  <button v-if="t.status === 3" class="btn btn-blue btn-sm" @click="retryFailed(t)">重发失败</button>
                  <button v-if="[0, 1, 2, 3].includes(t.status)" class="btn btn-red btn-sm" @click="cancel(t)">取消</button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      <div class="pager" v-if="total > pageSize">
        <button class="btn btn-ghost btn-sm" :disabled="pageNo <= 1" @click="pageNo--; load()">上一页</button>
        <span class="pager-txt">{{ pageNo }} / {{ Math.ceil(total / pageSize) }}</span>
        <button class="btn btn-ghost btn-sm" :disabled="pageNo >= Math.ceil(total / pageSize)" @click="pageNo++; load()">下一页</button>
      </div>
    </div>

    <!-- 创建/编辑弹窗 -->
    <div class="modal-mask fadein" v-if="formOpen" @click.self="formOpen = false">
      <form class="glass modal pop modal-wide" @submit.prevent="save">
        <h3>{{ editId ? '编辑发放任务' : '创建发放任务' }}</h3>

        <div class="form-grid">
          <div class="form-row">
            <label>任务名称 *</label>
            <input v-model="form.name" class="input" placeholder="如：新人注册送蓝卡" required />
          </div>
          <div class="form-row">
            <label>任务类型 *</label>
            <select v-model="form.type" class="input">
              <option value="1">新人发放（注册即发）</option>
              <option value="2">活动空投</option>
              <option value="3">运营补偿</option>
            </select>
          </div>
          <div class="form-row">
            <label>发放藏品 *</label>
            <select v-model="form.iid" class="input" required>
              <option value="">选择藏品</option>
              <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}（库存 {{ it.nft_quantity }}）</option>
            </select>
          </div>
          <div class="form-row">
            <label>每人数量 *</label>
            <input v-model.number="form.quantityPerUser" class="input" type="number" min="1" required />
          </div>
          <div class="form-row">
            <label>计划总量（0=不限）</label>
            <input v-model.number="form.totalQuantity" class="input" type="number" min="0" />
          </div>
        </div>

        <div class="form-row">
          <label>目标人群 *</label>
          <select v-model="form.targetType" class="input">
            <option value="1">注册时间规则</option>
            <option value="2">持仓快照（持有指定藏品）</option>
            <option value="3">指定 uid 名单</option>
          </select>
        </div>
        <div class="form-row" v-if="form.targetType === '1'">
          <div class="form-grid">
            <div class="form-row"><label>注册时间起（空=不限）</label><input v-model="form.registerAfter" class="input" placeholder="2026-10-01 00:00:00" /></div>
            <div class="form-row"><label>注册时间止（空=不限）</label><input v-model="form.registerBefore" class="input" placeholder="2026-10-31 23:59:59" /></div>
          </div>
        </div>
        <div class="form-row" v-if="form.targetType === '2'">
          <label>持仓藏品</label>
          <select v-model="form.holdIid" class="input">
            <option value="">选择用户持有的藏品</option>
            <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
          </select>
        </div>
        <div class="form-row" v-if="form.targetType === '3'">
          <label>uid 名单（逗号 / 空格 / 换行分隔，上限 5000）</label>
          <textarea v-model="form.uids" class="input" rows="4" placeholder="1234567890, 1234567891"></textarea>
        </div>

        <div class="form-grid">
          <div class="form-row">
            <label>有效期起（空=立即）</label>
            <input v-model="form.startTime" class="input" placeholder="2026-10-01 00:00:00" />
          </div>
          <div class="form-row">
            <label>有效期止（空=不限）</label>
            <input v-model="form.endTime" class="input" placeholder="2026-12-31 23:59:59" />
          </div>
          <div class="form-row" v-if="form.type === '3'">
            <label>关联工单号 *（补偿必填）</label>
            <input v-model="form.ticketNo" class="input" placeholder="TICKET-20261020-001" required />
          </div>
          <div class="form-row">
            <label>备注</label>
            <input v-model="form.remark" class="input" />
          </div>
        </div>

        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="formOpen = false">取消</button>
          <button class="btn btn-primary" :disabled="!form.name || !form.iid">保存草稿</button>
        </div>
      </form>
    </div>

    <!-- 详情弹窗 -->
    <div class="modal-mask fadein" v-if="detailOpen" @click.self="detailOpen = false">
      <div class="glass modal pop modal-wide">
        <h3>{{ detail?.name }} <span class="tag" :class="statusTag(detail?.status)" style="margin-left:8px">{{ detail?.statusName }}</span></h3>

        <div class="stat-row" style="margin:12px 0">
          <div class="glass stat-card"><div class="sc-num">{{ progress.pending }}</div><div class="sc-label">待发放</div></div>
          <div class="glass stat-card"><div class="sc-num">{{ progress.success }}</div><div class="sc-label">成功</div></div>
          <div class="glass stat-card"><div class="sc-num">{{ progress.failed }}</div><div class="sc-label">失败</div></div>
          <div class="glass stat-card"><div class="sc-num">{{ progress.pieces }}</div><div class="sc-label">已发份数</div></div>
        </div>

        <div class="detail-meta" v-if="detail">
          <div><b>审核意见</b>{{ detail.audit_remark || '—' }}</div>
          <div><b>备注</b>{{ detail.remark || '—' }}</div>
          <div><b>有效期</b>{{ (detail.start_time || '不限') + ' ~ ' + (detail.end_time || '不限') }}</div>
          <div><b>最近执行</b>{{ detail.executed_at || '未执行' }}</div>
        </div>

        <table class="tbl" style="margin-top:12px">
          <thead><tr><th>用户</th><th>NFT 编号</th><th>状态</th><th>时间</th><th>失败原因</th></tr></thead>
          <tbody>
            <tr v-if="!records.length"><td colspan="5" class="empty">暂无记录</td></tr>
            <tr v-for="r in records" :key="r.id">
              <td><div class="cell-main">{{ r.nickname }}</div><div class="cell-sub">{{ r.uid }}</div></td>
              <td class="cell-mono">{{ r.nft_ids || '—' }}</td>
              <td><span class="tag" :class="recordTag(r.status)">{{ r.statusName }}</span></td>
              <td class="cell-sub">{{ r.granted_at || r.created_at }}</td>
              <td class="cell-sub">{{ r.fail_reason || '—' }}</td>
            </tr>
          </tbody>
        </table>
        <div class="pager" v-if="recTotal > pageSize">
          <button class="btn btn-ghost btn-sm" :disabled="recPage <= 1" @click="recPage--; loadRecords()">上一页</button>
          <span class="pager-txt">{{ recPage }} / {{ Math.ceil(recTotal / pageSize) }}</span>
          <button class="btn btn-ghost btn-sm" :disabled="recPage >= Math.ceil(recTotal / pageSize)" @click="recPage++; loadRecords()">下一页</button>
        </div>

        <div class="modal-foot">
          <button class="btn btn-blue btn-sm" @click="preview(detail)">预览发放人数</button>
          <div style="flex:1"></div>
          <button class="btn btn-ghost" @click="detailOpen = false">关闭</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http, { toast, getProfile, canWrite } from '../api'

const me = ref(getProfile() || {})
const writable = computed(() => canWrite())

const list = ref([])
const total = ref(0)
const pageNo = ref(1)
const pageSize = 10
const f = reactive({ status: -1, type: 0 })
const stats = reactive({ total: 0, pendingAudit: 0, running: 0, pieces: 0 })

const statusOptions = [
  { v: 0, t: '草稿' }, { v: 1, t: '待审核' }, { v: 2, t: '已通过' }, { v: 3, t: '进行中' },
  { v: 4, t: '已完成' }, { v: 5, t: '已取消' }, { v: 6, t: '已驳回' },
]
const typeOptions = [{ v: 0, t: '全部类型' }, { v: 1, t: '新人' }, { v: 2, t: '空投' }, { v: 3, t: '补偿' }]

/* ---------- 创建/编辑 ---------- */
const formOpen = ref(false)
const editId = ref('')
const items = ref([])
const form = reactive({
  name: '', type: '2', iid: '', quantityPerUser: 1, totalQuantity: 0,
  targetType: '3', registerAfter: '', registerBefore: '', holdIid: '', uids: '',
  startTime: '', endTime: '', ticketNo: '', remark: '',
})

/* ---------- 详情 ---------- */
const detailOpen = ref(false)
const detail = ref(null)
const progress = reactive({ pending: 0, success: 0, failed: 0, pieces: 0 })
const records = ref([])
const recTotal = ref(0)
const recPage = ref(1)

function statusTag(s) {
  return { 0: 'tag-gray', 1: 'tag-blue', 2: 'tag-green', 3: 'tag-blue', 4: 'tag-gray', 5: 'tag-red', 6: 'tag-red' }[s] || 'tag-gray'
}
function typeTag(t) {
  return { 1: 'tag-green', 2: 'tag-blue', 3: 'tag-red' }[t] || 'tag-gray'
}
function recordTag(s) {
  return { 0: 'tag-gray', 1: 'tag-blue', 2: 'tag-green', 3: 'tag-red', 4: 'tag-gray' }[s] || 'tag-gray'
}
function progressPct(t) {
  const total = Number(t.total_quantity) || Math.max(Number(t.granted_quantity) || 0, 1)
  return Math.min(100, ((Number(t.granted_quantity) || 0) / total) * 100).toFixed(1) + '%'
}
function ruleSummary(t) {
  const r = t.targetRule || {}
  if (t.target_type === 1) {
    return (r.registerAfter || '不限') + ' ~ ' + (r.registerBefore || '不限')
  }
  if (t.target_type === 2) return '持有藏品 ' + (r.holdIid || '')
  return (r.uids || []).length + ' 人名单'
}

async function load() {
  try {
    const data = await http.get('/grant/tasks', { params: { pageNo: pageNo.value, pageSize, status: f.status, type: f.type } })
    list.value = data.list
    total.value = data.total
  } catch { /* 拦截器已 toast */ }
}

async function loadStats() {
  try {
    // 用一次大页拉取做轻量统计（任务量级小）
    const data = await http.get('/grant/tasks', { params: { pageNo: 1, pageSize: 100 } })
    stats.total = data.total
    stats.pendingAudit = data.list.filter(t => t.status === 1).length
    stats.running = data.list.filter(t => t.status === 3).length
    stats.pieces = data.list.reduce((s, t) => s + (Number(t.granted_quantity) || 0), 0)
  } catch { /* 忽略 */ }
}

async function loadItems() {
  try {
    const data = await http.get('/items', { params: { pageNo: 1, pageSize: 100 } })
    items.value = data.list
  } catch { /* 忽略 */ }
}

function resetForm() {
  Object.assign(form, {
    name: '', type: '2', iid: '', quantityPerUser: 1, totalQuantity: 0,
    targetType: '3', registerAfter: '', registerBefore: '', holdIid: '', uids: '',
    startTime: '', endTime: '', ticketNo: '', remark: '',
  })
}

function openCreate() {
  editId.value = ''
  resetForm()
  loadItems()
  formOpen.value = true
}

function openEdit(t) {
  editId.value = t.id
  loadItems()
  const rule = t.targetRule || {}
  Object.assign(form, {
    name: t.name, type: String(t.type), iid: t.iid, quantityPerUser: Number(t.quantity_per_user),
    totalQuantity: Number(t.total_quantity), targetType: String(t.target_type),
    registerAfter: rule.registerAfter || '', registerBefore: rule.registerBefore || '',
    holdIid: rule.holdIid || '', uids: (rule.uids || []).join(','),
    startTime: t.start_time || '', endTime: t.end_time || '', ticketNo: t.ticket_no || '', remark: t.remark || '',
  })
  formOpen.value = true
}

async function save() {
  try {
    const payload = {
      name: form.name, type: Number(form.type), iid: form.iid,
      quantityPerUser: form.quantityPerUser, totalQuantity: form.totalQuantity,
      targetType: Number(form.targetType), registerAfter: form.registerAfter, registerBefore: form.registerBefore,
      holdIid: form.holdIid, uids: form.uids, startTime: form.startTime, endTime: form.endTime,
      ticketNo: form.ticketNo, remark: form.remark,
    }
    if (editId.value) {
      await http.put(`/grant/tasks/${editId.value}`, payload)
      toast('任务已更新')
    } else {
      await http.post('/grant/task', payload)
      toast('任务已创建（草稿），请提交审核')
    }
    formOpen.value = false
    load(); loadStats()
  } catch { /* 拦截器已 toast */ }
}

async function submit(t) {
  if (!confirm(`提交「${t.name}」进入审核流程？`)) return
  try {
    await http.post(`/grant/tasks/${t.id}/submit`)
    toast('已提交审核')
    load(); loadStats()
  } catch { /* 已 toast */ }
}

async function audit(t, pass) {
  let remark = ''
  if (!pass) {
    remark = prompt('驳回必须填写审核意见：') || ''
    if (!remark.trim()) return
  } else if (!confirm(`确认通过「${t.name}」？`)) return
  try {
    await http.post(`/grant/tasks/${t.id}/audit`, { pass, remark })
    toast(pass ? '审核通过' : '已驳回')
    load(); loadStats()
  } catch { /* 已 toast */ }
}

async function cancel(t) {
  if (!confirm(`取消「${t.name}」？未发放的记录将作废，不可恢复。`)) return
  try {
    await http.post(`/grant/tasks/${t.id}/cancel`)
    toast('任务已取消')
    load(); loadStats()
  } catch { /* 已 toast */ }
}

async function execute(t) {
  if (!confirm(`执行「${t.name}」？将按规则发放给目标用户。`)) return
  try {
    const stat = await http.post(`/grant/tasks/${t.id}/execute`)
    toast(`执行完成：成功 ${stat.granted}，失败 ${stat.failed}`)
    load(); loadStats()
  } catch { /* 已 toast */ }
}

async function retryFailed(t) {
  try {
    const stat = await http.post(`/grant/tasks/${t.id}/retry`)
    toast(`重发完成：成功 ${stat.granted}，失败 ${stat.failed}`)
    loadRecords(); load(); loadStats()
  } catch { /* 已 toast */ }
}

async function preview(t) {
  try {
    const data = await http.get(`/grant/tasks/${t.id}/preview`)
    alert(`按当前规则将发放：${data.userCount} 人 · 共 ${data.pieceCount} 份`)
  } catch { /* 已 toast */ }
}

async function openDetail(t) {
  try {
    detail.value = await http.get(`/grant/tasks/${t.id}`)
    Object.assign(progress, detail.value.progress || {})
    recPage.value = 1
    detailOpen.value = true
    loadRecords()
  } catch { /* 已 toast */ }
}

async function loadRecords() {
  if (!detail.value) return
  try {
    const data = await http.get(`/grant/tasks/${detail.value.id}/records`, { params: { pageNo: recPage.value, pageSize } })
    records.value = data.list
    recTotal.value = data.total
  } catch { /* 已 toast */ }
}

onMounted(() => { load(); loadStats() })
</script>

<style scoped>
.stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 14px; }
.stat-card { padding: 16px 18px; }
.sc-num { font-size: 26px; font-weight: 700; letter-spacing: .5px; }
.sc-label { font-size: 12px; opacity: .55; margin-top: 2px; }
.toolbar { display: flex; align-items: center; padding: 12px 16px; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.table-card { padding: 6px 14px 14px; }
.tbl { width: 100%; border-collapse: collapse; }
.tbl th { text-align: left; font-size: 12px; font-weight: 600; opacity: .55; padding: 12px 10px 8px; border-bottom: 1px solid rgba(125,125,145,.14); white-space: nowrap; }
.tbl td { padding: 12px 10px; border-bottom: 1px solid rgba(125,125,145,.08); vertical-align: middle; }
.empty { text-align: center; opacity: .45; padding: 28px 0 !important; }
.cell-main { font-size: 13.5px; font-weight: 600; }
.cell-sub { font-size: 12px; opacity: .55; margin-top: 2px; }
.cell-mono { font-family: ui-monospace, monospace; font-size: 12px; max-width: 220px; word-break: break-all; }
.row-img { width: 34px; height: 34px; border-radius: 8px; object-fit: cover; background: rgba(125,125,145,.12); }
.tag { display: inline-block; font-size: 11.5px; padding: 2px 9px; border-radius: 20px; margin-right: 4px; }
.tag-gray { background: rgba(125,125,145,.14); }
.tag-blue { background: rgba(64,120,255,.15); color: #3f72ff; }
.tag-green { background: rgba(40,180,120,.15); color: #1fa87a; }
.tag-red { background: rgba(235,80,80,.14); color: #e05050; }
.chip { font-size: 12.5px; padding: 5px 13px; border-radius: 20px; background: rgba(125,125,145,.1); cursor: pointer; user-select: none; }
.chip.on { background: var(--accent, #4a6cf7); color: #fff; }
.chip-blue.on { background: #3f72ff; color: #fff; }
.progress-wrap { width: 100%; }
.progress-bar { height: 6px; border-radius: 4px; background: rgba(125,125,145,.15); overflow: hidden; }
.progress-in { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #4a6cf7, #35d0a5); transition: width .4s; }
.pager { display: flex; align-items: center; justify-content: center; gap: 12px; margin-top: 14px; }
.pager-txt { font-size: 12.5px; opacity: .6; }
.modal-mask { position: fixed; inset: 0; background: rgba(20,22,35,.45); backdrop-filter: blur(6px); z-index: 60; display: flex; align-items: center; justify-content: center; }
.modal { width: 480px; max-height: 86vh; overflow: auto; padding: 22px 24px; }
.modal-wide { width: 720px; }
.modal h3 { margin-bottom: 14px; font-size: 17px; }
.form-row { margin-bottom: 12px; }
.form-row label { display: block; font-size: 12.5px; opacity: .65; margin-bottom: 5px; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
.input { width: 100%; box-sizing: border-box; padding: 9px 12px; border-radius: 10px; border: 1px solid rgba(125,125,145,.22); background: rgba(255,255,255,.05); font-size: 13.5px; outline: none; }
.input:focus { border-color: var(--accent, #4a6cf7); }
.modal-foot { display: flex; align-items: center; gap: 10px; margin-top: 16px; }
.btn { border: none; cursor: pointer; border-radius: 10px; padding: 9px 18px; font-size: 13.5px; font-weight: 600; }
.btn:disabled { opacity: .4; cursor: not-allowed; }
.btn-sm { padding: 5px 11px; font-size: 12.5px; border-radius: 8px; }
.btn-primary { background: var(--accent, #4a6cf7); color: #fff; }
.btn-blue { background: rgba(64,120,255,.14); color: #3f72ff; }
.btn-green { background: rgba(40,180,120,.16); color: #1fa87a; }
.btn-red { background: rgba(235,80,80,.13); color: #e05050; }
.btn-ghost { background: rgba(125,125,145,.1); }
.detail-meta { font-size: 12.5px; opacity: .8; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; margin-top: 8px; }
.detail-meta b { opacity: .55; margin-right: 8px; font-weight: 600; }
</style>
