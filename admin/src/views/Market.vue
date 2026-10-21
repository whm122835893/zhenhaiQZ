<template>
  <div class="page-body">
    <!-- ============ 一级：藏品单品聚合 ============ -->
    <div class="glass card-pad rise" v-if="view === 'groups'">
      <div class="filters">
        <input v-model.trim="gq.keyword" class="input" placeholder="藏品名称" @keyup.enter="loadGroups(1)" />
        <button class="btn btn-primary" @click="loadGroups(1)">查询</button>
        <button class="btn btn-ghost" @click="gq.keyword = ''; loadGroups(1)">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>藏品单品</th><th>在售挂单</th><th>价格区间</th><th>累计成交</th><th>最近挂单</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="g in groups" :key="g.iid" class="row-click" @click="openOrders(g)">
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img v-if="g.itemImage" :src="g.itemImage" class="thumb" loading="lazy" @error="e => e.target.style.visibility = 'hidden'" />
                  <div v-else class="thumb" style="display:flex;align-items:center;justify-content:center;color:#9fb4cf">无图</div>
                  <div>
                    <div class="cell-main">{{ g.itemName }}</div>
                    <div class="cell-sub">iid {{ g.iid }}</div>
                  </div>
                </div>
              </td>
              <td><span class="tag" :class="g.onSale > 0 ? 'tag-green' : 'tag-gray'">{{ g.onSale }} 单在售</span></td>
              <td class="serif-num">
                <template v-if="g.onSale > 0">¥{{ money(g.minPrice) }} ~ ¥{{ money(g.maxPrice) }}</template>
                <span v-else class="cell-sub">—</span>
              </td>
              <td class="serif-num">{{ g.sold }}</td>
              <td class="cell-sub">{{ fmtTime(g.lastTime) }}</td>
              <td>
                <button class="btn btn-ghost btn-sm" @click.stop="openOrders(g)">查看挂单 →</button>
              </td>
            </tr>
            <tr v-if="!loading && groups.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">🫧</div>没有符合条件的藏品</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="gTotal" :page="gq.pageNo" :size="gq.pageSize" @change="p => loadGroups(p)" />
    </div>

    <!-- ============ 二级：该藏品的挂单明细 ============ -->
    <div class="glass card-pad rise" v-else>
      <div class="filters">
        <button class="btn btn-ghost btn-sm" @click="backToGroups">← 返回藏品列表</button>
        <span class="cell-main" style="margin-left:4px">{{ current.itemName }}</span>
        <span class="cell-sub">iid {{ current.iid }}</span>
        <span style="flex:1"></span>
        <select v-model="dq.status" class="select" @change="loadOrders(1)">
          <option value="">全部状态</option>
          <option value="1">在售</option>
          <option value="2">已成交</option>
        </select>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>NFT</th><th>卖家</th><th>挂单价</th><th>手续费</th><th>实际到手</th><th>类型</th><th>状态</th><th>挂单时间</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="s in list" :key="s.id">
              <td class="cell-sub serif-num">{{ s.nftId }}</td>
              <td>{{ s.nickname }}</td>
              <td class="serif-num">¥{{ money(s.price) }}</td>
              <td class="cell-sub">{{ (Number(s.feeRate) * 100).toFixed(1) }}% · ¥{{ money(s.feeAmount) }}</td>
              <td class="serif-num">¥{{ money(s.actualAmount) }}</td>
              <td><span class="tag tag-gray">{{ s.market_type === 2 ? '批量' : '一口价' }}</span></td>
              <td><span class="tag" :class="Number(s.status) === 1 ? 'tag-green' : 'tag-blue'">{{ SELL_STATUS[s.status] || s.status }}</span></td>
              <td class="cell-sub">{{ fmtTime(s.created_at) }}</td>
              <td>
                <button v-if="canWrite() && Number(s.status) === 1" class="btn btn-danger btn-sm" :disabled="busy === s.id" @click="offShelf(s)">
                  下架
                </button>
                <span v-else class="cell-sub">—</span>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="9"><div class="empty"><div class="glyph">🫧</div>该藏品暂无挂单</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="dq.pageNo" :size="dq.pageSize" @change="p => loadOrders(p)" />
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, fmtTime, money, canWrite, SELL_STATUS } from '../api'
import Pager from '../components/Pager.vue'

/* ---------- 一级：藏品聚合 ---------- */
const view = ref('groups')
const current = reactive({ iid: '', itemName: '' })
const gq = reactive({ keyword: '', pageNo: 1, pageSize: 20 })
const groups = ref([])
const gTotal = ref(0)

/* ---------- 二级：挂单明细 ---------- */
const dq = reactive({ status: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)

const loading = ref(true)
const busy = ref('')

async function loadGroups(page = gq.pageNo) {
  gq.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/market/item-groups', { params: { keyword: gq.keyword, pageNo: gq.pageNo, pageSize: gq.pageSize } })
    groups.value = data.list || []
    gTotal.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

function openOrders(g) {
  current.iid = g.iid
  current.itemName = g.itemName
  view.value = 'orders'
  loadOrders(1)
}

function backToGroups() {
  view.value = 'groups'
  loadGroups()
}

async function loadOrders(page = dq.pageNo) {
  dq.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/market/sell-orders', {
      params: { iid: current.iid, status: dq.status, pageNo: dq.pageNo, pageSize: dq.pageSize },
    })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

/**
 * 强制下架（司南 v2.2 #15 联动已由后端完成）：
 * 挂单删除 + NFT 恢复未寄售 + 卖家进入冷却（sell.cooldown_minutes）
 */
async function offShelf(s) {
  if (!confirm(`确认强制下架「${current.itemName}」的挂单 #${s.id}？\n卖家将进入寄售冷却期。`)) return
  busy.value = s.id
  try {
    await http.post(`/market/sell-orders/${s.id}/off`)
    toast('已下架，NFT 恢复未寄售，卖家进入冷却')
    loadOrders()
  } catch {
    /* 拦截器已 toast */
  } finally {
    busy.value = ''
  }
}

onMounted(() => loadGroups(1))
</script>

<style scoped>
.row-click { cursor: pointer; }
.row-click:hover td { background: rgba(255, 255, 255, 0.5); }
</style>
