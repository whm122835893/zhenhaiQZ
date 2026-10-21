<template>
  <div class="page-body">
    <!-- 对账概览 -->
    <div class="stat-grid rise" v-if="sum">
      <div class="glass stat-card">
        <div class="stat-label">用户钱包余额总和</div>
        <div class="stat-value serif-num">¥{{ fmtMoney(sum.totalBalance) }}</div>
        <div class="stat-sub">共 {{ sum.userCount }} 个钱包账户</div>
      </div>
      <div class="glass stat-card">
        <div class="stat-label">流水净额（入-出）</div>
        <div class="stat-value serif-num">¥{{ fmtMoney(sum.ledgerNet) }}</div>
        <div class="stat-sub">共 {{ flowCount }} 笔流水</div>
      </div>
      <div class="glass stat-card" :class="Math.abs(sum.diff) > 0.01 ? 'warn' : ''">
        <div class="stat-label">对账差额</div>
        <div class="stat-value serif-num">¥{{ fmtMoney(sum.diff) }}</div>
        <div class="stat-sub">{{ Math.abs(sum.diff) > 0.01 ? '存在差额，多为建账前初始余额' : '账实相符' }}</div>
      </div>
      <div class="glass stat-card">
        <div class="stat-label">手续费收入</div>
        <div class="stat-value serif-num">¥{{ fmtMoney(sum.byType.fee?.amount ?? 0) }}</div>
        <div class="stat-sub">{{ sum.byType.fee?.count ?? 0 }} 笔</div>
      </div>
    </div>

    <!-- 类型汇总 -->
    <div class="glass card-pad rise" style="margin-top:14px" v-if="sum">
      <div class="type-chips">
        <span v-for="t in typeList" :key="t.key" class="tag" :class="q.type === t.key ? 'tag-blue' : 'tag-gray'" @click="setType(t.key)">
          {{ t.label }} ¥{{ fmtMoney(sum.byType[t.key]?.amount ?? 0) }}（{{ sum.byType[t.key]?.count ?? 0 }}笔）
        </span>
      </div>
    </div>

    <div class="glass card-pad rise" style="margin-top:14px">
      <div class="filters">
        <input v-model.trim="q.uid" class="input" style="max-width:210px" placeholder="用户 UID" @keyup.enter="load(1)" />
        <select v-model="q.type" class="select" style="max-width:130px">
          <option value="">全部类型</option>
          <option v-for="t in typeList" :key="t.key" :value="t.key">{{ t.label }}</option>
        </select>
        <input v-model="q.from" type="date" class="input" style="max-width:150px" />
        <span class="cell-sub">至</span>
        <input v-model="q.to" type="date" class="input" style="max-width:150px" />
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="resetQ">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>时间</th><th>用户</th><th>类型</th><th>关联订单</th><th style="text-align:right">金额</th><th style="text-align:right">变动后余额</th><th>备注</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td class="cell-sub">{{ fmtDateTime(r.created_at) }}</td>
              <td><div class="cell-main">{{ r.nickname }}</div><div class="cell-sub" style="font-size:11px">{{ r.uid }}</div></td>
              <td><span class="tag" :class="typeTag(r.trans_type)">{{ typeLabel(r.trans_type) }}</span></td>
              <td class="cell-sub" style="font-size:12px">{{ r.orderId !== '0' ? r.orderId : '—' }}</td>
              <td style="text-align:right" :class="r.amount >= 0 ? 'money-in' : 'money-out'">
                {{ r.amount >= 0 ? '+' : '' }}{{ fmtMoney(r.amount) }}
              </td>
              <td class="serif-num" style="text-align:right">{{ fmtMoney(r.balanceAfter) }}</td>
              <td class="cell-sub" style="max-width:220px;overflow:hidden;text-overflow:ellipsis">{{ r.remark || '—' }}</td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="7"><div class="empty"><div class="glyph">💰</div>暂无流水</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http, { fmtTime } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ uid: '', type: '', from: '', to: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const sum = ref(null)
const loading = ref(true)
const flowCount = computed(() =>
  sum.value ? Object.values(sum.value.byType).reduce((a, t) => a + (t.count || 0), 0) : 0
)

const typeList = [
  { key: 'recharge', label: '充值' },
  { key: 'withdraw', label: '提现' },
  { key: 'buy', label: '购买' },
  { key: 'sell', label: '出售' },
  { key: 'fee', label: '手续费' },
  { key: 'reward', label: '奖励' },
]

function typeLabel(t) { return typeList.find(x => x.key === t)?.label ?? t }
function typeTag(t) { return t === 'recharge' || t === 'sell' || t === 'reward' ? 'tag-green' : (t === 'fee' ? 'tag-orange' : 'tag-gray') }
function fmtMoney(v) { return Number(v ?? 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtDateTime(s) { return fmtTime(s) || String(s ?? '') }

function setType(k) {
  q.type = q.type === k ? '' : k
  load(1)
}

function resetQ() {
  Object.assign(q, { uid: '', type: '', from: '', to: '', pageNo: 1 })
  load(1)
}

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const [d, s] = await Promise.all([
      http.get('/wallet/transactions', { params: { uid: q.uid, type: q.type, from: q.from, to: q.to, pageNo: q.pageNo, pageSize: q.pageSize } }),
      sum.value === null || page === 1 ? http.get('/wallet/summary') : Promise.resolve(null),
    ])
    list.value = d.list || []
    total.value = Number(d.total || 0)
    if (s) sum.value = s
  } finally {
    loading.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.stat-card { padding: 16px 18px; }
.stat-card.warn .stat-value { color: #c25a3a; }
.stat-label { font-size: 12px; color: #6b7f9e; margin-bottom: 6px; }
.stat-value { font-size: 22px; font-weight: 700; color: #16294c; }
.stat-sub { font-size: 11.5px; color: #93a6c2; margin-top: 4px; }
.type-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.type-chips .tag { cursor: pointer; user-select: none; }
.money-in { color: #1c8a5a; font-weight: 600; }
.money-out { color: #c25a3a; font-weight: 600; }
@media (max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
</style>
