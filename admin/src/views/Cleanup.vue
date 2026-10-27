<template>
  <div class="page-body">
    <!-- 平台业务数据 -->
    <div class="glass card-pad rise">
      <div class="card-head">平台业务数据<span>BUSINESS DATA</span></div>
      <div class="cell-sub" style="margin-bottom:14px">
        按组硬删对应业务表数据（含软删记录）。分类、作者、轮播、系统配置、管理员账号不受影响。
      </div>
      <div class="scope-grid">
        <label v-for="s in scopes" :key="s.key" class="cfg-cell scope-cell">
          <input type="checkbox" v-model="s.checked" />
          <div>
            <div class="cell-main">{{ s.label }}</div>
            <div class="cell-sub serif-num">{{ s.count }} 条</div>
          </div>
        </label>
      </div>
      <div class="form-row" style="margin-top:14px">
        <label>输入「清空」确认（不可恢复）</label>
        <input v-model.trim="platformConfirm" class="input" style="max-width:240px" placeholder="清空" />
        <button class="btn btn-danger" :disabled="!canPlatformClear || busy" @click="clearPlatform">清空所选数据</button>
      </div>
    </div>

    <!-- 全体用户 -->
    <div class="glass card-pad rise" style="margin-top:16px">
      <div class="card-head">清除全体用户<span>USERS</span></div>
      <div class="cell-sub" style="margin-bottom:14px">
        硬删全部 <b class="serif-num">{{ summary.users }}</b> 个注册用户及其所有归属数据：
        资产持仓、订单、市场寄售、钱包流水、签到/抽奖/邀请记录、登录态等。被清除用户需重新注册。
        如需删除部分用户，请到「用户管理」页勾选后批量删除。
      </div>
      <div class="form-row">
        <label>输入「清空」确认（不可恢复）</label>
        <input v-model.trim="usersConfirm" class="input" style="max-width:240px" placeholder="清空" />
        <button class="btn btn-danger" :disabled="!canUsersClear || busy || !summary.users" @click="clearUsers">清除全体用户</button>
      </div>
    </div>

    <!-- 清理结果 -->
    <div class="glass card-pad rise" style="margin-top:16px" v-if="result.length">
      <div class="card-head">最近一次清理结果<span>RESULT</span></div>
      <div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:12px">
        <div v-for="r in result" :key="r.table" class="cfg-cell">
          <div class="k cell-sub">{{ r.table }}</div>
          <div class="v serif-num" style="font-size:16px">已删 {{ r.count }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http, { toast, canSuper } from '../api'

const summary = reactive({ users: 0, notices: 0, items: 0, nfts: 0, orders: 0, sellOrders: 0, grantTasks: 0, activityLogs: 0, noticesCount: 0 })
const scopes = reactive([
  { key: 'notices', label: '公告', checked: false, count: 0 },
  { key: 'items', label: '藏品（含发放任务/优先购/空投）', checked: false, count: 0 },
  { key: 'trades', label: '交易与资产（NFT/订单/市场）', checked: false, count: 0 },
  { key: 'activities', label: '活动记录（签到/抽奖/积分/合成）', checked: false, count: 0 },
])
const platformConfirm = ref('')
const usersConfirm = ref('')
const busy = ref(false)
const result = ref([])

const canPlatformClear = computed(() => platformConfirm.value === '清空' && scopes.some(s => s.checked))
const canUsersClear = computed(() => usersConfirm.value === '清空')

async function load() {
  try {
    const d = await http.get('/cleanup/summary')
    Object.assign(summary, d || {})
    scopes[0].count = d.notices || 0
    scopes[1].count = d.items || 0
    scopes[2].count = (d.nfts || 0) + (d.orders || 0) + (d.sellOrders || 0)
    scopes[3].count = d.activityLogs || 0
  } catch { /* 拦截器已 toast */ }
}

async function clearPlatform() {
  const picked = scopes.filter(s => s.checked)
  if (!confirm(`确认硬删所选 ${picked.length} 组业务数据？此操作不可恢复！`)) return
  busy.value = true
  try {
    const d = await http.post('/cleanup/platform', { scopes: picked.map(s => s.key) })
    const purged = d.purged || {}
    result.value = Object.entries(purged).map(([table, count]) => ({ table, count: Number(count) }))
    toast('平台数据已清空')
    scopes.forEach(s => (s.checked = false))
    platformConfirm.value = ''
    await load()
  } catch { /* 拦截器已 toast */ } finally { busy.value = false }
}

async function clearUsers() {
  if (!confirm(`确认清除全部 ${summary.users} 个用户？其所有数据将一并硬删，不可恢复！`)) return
  busy.value = true
  try {
    const d = await http.post('/cleanup/users', { all: true })
    toast(`已清除 ${d.deleted} 个用户`)
    usersConfirm.value = ''
    await load()
  } catch { /* 拦截器已 toast */ } finally { busy.value = false }
}

onMounted(() => {
  if (!canSuper()) {
    toast('仅超级管理员可访问数据清理', true)
    return
  }
  load()
})
</script>

<style scoped>
.scope-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 10px;
}
.scope-cell {
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  padding: 12px 14px;
}
.scope-cell input { width: 16px; height: 16px; accent-color: var(--danger, #e5484d); }
</style>
