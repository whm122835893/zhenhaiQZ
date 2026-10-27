<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="标题 / 订单号" @keyup.enter="load(1)" />
        <select v-model="q.status" class="select" @change="load(1)">
          <option value="">全部状态</option>
          <option v-for="(t, v) in ORDER_STATUS" :key="v" :value="v">{{ t }}</option>
        </select>
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="q.keyword = ''; q.status = ''; load(1)">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>订单</th><th>买家</th><th>类型</th><th>数量</th><th>金额</th><th>支付方式</th><th>状态</th><th>支付时间</th><th>下单时间</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="o in list" :key="o.id">
              <td>
                <div class="cell-main">{{ o.title }}</div>
                <div class="cell-sub">#{{ o.id }} · {{ o.out_trade_no }}</div>
              </td>
              <td>{{ o.nickname }}</td>
              <td><span class="tag tag-blue">{{ ORDER_TYPE[o.order_type] || o.order_type }}</span></td>
              <td class="serif-num">×{{ o.quantity }}</td>
              <td class="serif-num">¥{{ money(toYuan(o.amount)) }}</td>
              <td class="cell-sub">{{ payWayText(o.pay_way) }}</td>
              <td><span class="tag" :class="statusCls(o.status)">{{ ORDER_STATUS[o.status] || o.status }}</span></td>
              <td class="cell-sub">{{ fmtTime(o.payTime) }}</td>
              <td class="cell-sub">{{ fmtTime(o.created_at) }}</td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <button class="btn btn-ghost btn-sm" @click="openDetail(o)">详情</button>
                  <template v-if="canWrite() && (o.status === 0 || o.status === 1)">
                    <button class="btn btn-danger btn-sm" @click="forceCancel(o)">取消</button>
                    <button v-if="canSuper()" class="btn btn-ghost btn-sm" @click="markPaid(o)">标支付</button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="10"><div class="empty"><div class="glyph">🧾</div>没有符合条件的订单</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 详情弹窗 -->
    <div class="modal-mask fadein" v-if="detail" @click.self="detail = null">
      <div class="glass modal pop" style="width:640px;max-height:80vh;overflow-y:auto">
        <div class="od-head">
          <h3>订单详情</h3>
          <button type="button" class="btn btn-ghost btn-sm" @click="detail = null">关闭</button>
        </div>

        <div class="od-sec">
          <div class="od-sec-title">基本信息</div>
          <div class="od-grid">
            <div><span class="od-k">订单号</span><span class="od-v">{{ detail.id }}</span></div>
            <div><span class="od-k">外部交易号</span><span class="od-v">{{ detail.out_trade_no || '—' }}</span></div>
            <div><span class="od-k">藏品</span><span class="od-v">{{ detail.title }}（iid {{ detail.iid }}）</span></div>
            <div><span class="od-k">类型</span><span class="od-v">{{ ORDER_TYPE[detail.order_type] || detail.order_type }}</span></div>
            <div><span class="od-k">买家</span><span class="od-v">{{ detail.nickname }}（{{ detail.uid }}）</span></div>
            <div><span class="od-k">卖家</span><span class="od-v">{{ detail.sellerNickname || (detail.sellerUid !== '0' ? detail.sellerUid : '平台') }}</span></div>
            <div><span class="od-k">单价</span><span class="od-v serif-num">¥{{ money(toYuan(detail.unitPrice)) }}</span></div>
            <div><span class="od-k">总额</span><span class="od-v serif-num">¥{{ money(toYuan(detail.amount)) }} × {{ detail.quantity }}</span></div>
            <div><span class="od-k">状态</span><span class="tag" :class="statusCls(detail.status)">{{ ORDER_STATUS[detail.status] || detail.status }}</span></div>
            <div><span class="od-k">下单时间</span><span class="od-v">{{ fmtTime(detail.created_at) }}</span></div>
            <div><span class="od-k">支付时间</span><span class="od-v">{{ fmtTime(detail.pay_time) || '未支付' }}</span></div>
            <div v-if="detail.cancelled_at"><span class="od-k">取消时间</span><span class="od-v">{{ fmtTime(detail.cancelled_at) }}</span></div>
            <div v-if="detail.remark" style="grid-column:1/-1"><span class="od-k">备注</span><span class="od-v">{{ detail.remark }}</span></div>
          </div>
        </div>

        <div class="od-sec" v-if="detail.nftItems && detail.nftItems.length">
          <div class="od-sec-title">NFT 明细</div>
          <table class="tbl">
            <thead><tr><th>NFT</th><th>Token</th><th>金额</th><th>当前持有人</th><th>上架状态</th></tr></thead>
            <tbody>
              <tr v-for="(n, i) in detail.nftItems" :key="i">
                <td class="cell-sub" style="font-size:12px">{{ n.nftId }}</td>
                <td class="serif-num">#{{ n.token }}</td>
                <td class="serif-num">¥{{ money(toYuan(n.amount)) }}</td>
                <td class="cell-sub">{{ n.currentUid ?? '未分配' }}</td>
                <td><span class="tag tag-gray">{{ NFT_SELL_STATUS[n.sellStatus] ?? '未知' }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="od-sec">
          <div class="od-sec-title">支付流水</div>
          <div v-if="!detail.payments || detail.payments.length === 0" class="cell-sub" style="padding:4px 0">无支付记录</div>
          <table class="tbl" v-else>
            <thead><tr><th>渠道</th><th>交易号</th><th>金额</th><th>状态</th><th>支付时间</th></tr></thead>
            <tbody>
              <tr v-for="p in detail.payments" :key="p.id">
                <td>{{ payWayName(p.channel) }}</td>
                <td class="cell-sub" style="font-size:12px">{{ p.tradeNo || '—' }}</td>
                <td class="serif-num">¥{{ money(toYuan(p.amount)) }}</td>
                <td><span class="tag" :class="p.status === 'paid' ? 'tag-green' : 'tag-orange'">{{ p.status }}</span></td>
                <td class="cell-sub">{{ p.paidAt || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, fmtTime, money, toYuan, canWrite, canSuper, ORDER_TYPE, ORDER_STATUS } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ keyword: '', status: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const detail = ref(null)

const NFT_SELL_STATUS = { 0: '未上架', 1: '已上架', 2: '锁定中', 3: '已售出' }

const payWayText = w => payWayName(null, Number(w))
const payWayName = (c, w) => {
  if (c) return { balance: '余额', hfpay: '汇付', yeepay: '易宝' }[c] || c
  return { 1: '余额支付', 2: '汇付', 3: '易宝' }[w] || `方式 ${w}`
}
const statusCls = s => ({ 0: 'tag-orange', 1: 'tag-blue', 2: 'tag-gray', 3: 'tag-green', 4: 'tag-red' }[s] || 'tag-gray')

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/orders', { params: { ...q } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

async function openDetail(o) {
  try {
    detail.value = await http.get(`/orders/${o.id}`)
  } catch { /* 拦截器已 toast */ }
}

async function forceCancel(o) {
  const reason = prompt(`强制取消订单 ${o.id}（¥${money(toYuan(o.amount))}）\n请输入取消原因（记入操作日志）：`, '客服处理')
  if (reason === null) return
  try {
    await http.post(`/orders/${o.id}/cancel`, { reason })
    toast('订单已强制取消')
    load()
  } catch { /* 拦截器已 toast */ }
}

async function markPaid(o) {
  const reason = prompt(`将订单 ${o.id} 标记为已支付（¥${money(toYuan(o.amount))}，仅超管，线下补单）\n请输入补单原因（记入操作日志）：`, '线下已收款')
  if (reason === null) return
  try {
    await http.post(`/orders/${o.id}/mark-paid`, { reason })
    toast('订单已标记为已支付')
    load()
  } catch { /* 拦截器已 toast */ }
}

onMounted(() => load(1))
</script>

<style scoped>
.od-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.od-head h3 { margin: 0; }
.od-sec { margin-bottom: 16px; }
.od-sec-title { font-size: 13px; font-weight: 600; color: #405678; margin-bottom: 8px; }
.od-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 18px; }
.od-grid > div { display: flex; gap: 8px; align-items: baseline; min-width: 0; }
.od-k { flex-shrink: 0; font-size: 12px; color: #8296b4; min-width: 64px; }
.od-v { font-size: 13px; color: #1c2b45; word-break: break-all; }
</style>
