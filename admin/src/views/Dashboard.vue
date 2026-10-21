<template>
  <div class="page-body">
    <!-- 指标卡 -->
    <div class="stat-grid">
      <div class="glass stat-card rise" style="--i:0">
        <span class="orb"></span>
        <div class="lb">注册用户</div>
        <div class="v serif-num">{{ n(cards.users?.total) }}</div>
        <div class="sub">今日新增 <b class="serif-num">{{ n(cards.users?.today) }}</b> 人</div>
      </div>
      <div class="glass stat-card rise" style="--i:1">
        <span class="orb"></span>
        <div class="lb">藏品数</div>
        <div class="v serif-num">{{ n(cards.items?.total) }}</div>
        <div class="sub">销售中 <b class="serif-num">{{ n(cards.items?.onSale) }}</b> 件</div>
      </div>
      <div class="glass stat-card rise" style="--i:2">
        <span class="orb"></span>
        <div class="lb">NFT 持仓</div>
        <div class="v serif-num">{{ n(cards.nfts?.total) }}</div>
        <div class="sub">寄售中 <b class="serif-num">{{ n(cards.nfts?.onSell) }}</b> 枚</div>
      </div>
      <div class="glass stat-card rise" style="--i:3">
        <span class="orb"></span>
        <div class="lb">订单总数</div>
        <div class="v serif-num">{{ n(cards.orders?.total) }}</div>
        <div class="sub">今日 <b class="serif-num">{{ n(cards.orders?.today) }}</b> 单 · 累计 ¥{{ money(cards.orders?.amount) }}</div>
      </div>
    </div>

    <!-- 趋势 + 市场 -->
    <div class="dash-row">
      <div class="glass card-pad rise" style="--i:4">
        <div class="card-head">近 7 日订单趋势<span>7-DAY TREND</span></div>
        <div class="chart">
          <div class="col" v-for="t in trend" :key="t.day">
            <div class="bar" :style="{ height: barH(t.orders) }"
                 :data-tip="`${t.day} · ${t.orders} 单 / ¥${money(t.amount)}`"></div>
            <div class="x">{{ t.day }}</div>
          </div>
        </div>
      </div>
      <div class="glass stat-card rise" style="--i:5">
        <span class="orb"></span>
        <div class="lb">市场累计成交额</div>
        <div class="v serif-num" style="font-size:26px">¥{{ money(cards.market?.turnover) }}</div>
        <div class="sub" style="margin-top:8px">在售挂单 <b class="serif-num">{{ n(cards.market?.onSale) }}</b> 件</div>
        <div class="sub">成交额 = 已成交挂单金额合计</div>
      </div>
    </div>

    <!-- 最新订单 -->
    <div class="glass card-pad rise" style="--i:6">
      <div class="card-head">最新订单<span>LATEST ORDERS</span></div>
      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>订单号</th><th>买家</th><th>类型</th><th>金额</th><th>状态</th><th>下单时间</th></tr>
          </thead>
          <tbody>
            <tr v-for="o in latest" :key="o.id">
              <td>
                <div class="cell-main">{{ o.title }}</div>
                <div class="cell-sub">#{{ o.id }}</div>
              </td>
              <td>{{ o.nickname }}</td>
              <td><span class="tag tag-blue">{{ ORDER_TYPE[o.order_type] || o.order_type }}</span></td>
              <td class="serif-num">¥{{ money(o.amount) }}</td>
              <td><span class="tag" :class="statusCls(o.status)">{{ ORDER_STATUS[o.status] || o.status }}</span></td>
              <td class="cell-sub">{{ fmtTime(o.created_at) }}</td>
            </tr>
            <tr v-if="!loading && latest.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">🫧</div>暂无订单数据</div></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import http, { fmtTime, money, ORDER_TYPE, ORDER_STATUS } from '../api'

const cards = ref({})
const trend = ref([])
const latest = ref([])
const loading = ref(true)

const n = v => Number(v || 0).toLocaleString('zh-CN')
const maxOrders = computed(() => Math.max(1, ...trend.value.map(t => t.orders)))
const barH = c => `${Math.max(6, Math.round((c / maxOrders.value) * 100))}%`
const statusCls = s => ({ 0: 'tag-orange', 1: 'tag-blue', 2: 'tag-gray', 3: 'tag-green', 4: 'tag-red' }[s] || 'tag-gray')

onMounted(async () => {
  try {
    const data = await http.get('/dashboard')
    cards.value = data.cards || {}
    trend.value = data.trend || []
    latest.value = data.latestOrders || []
  } finally {
    loading.value = false
  }
})
</script>
