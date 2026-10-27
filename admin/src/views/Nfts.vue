<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="藏品名称" @keyup.enter="load(1)" />
        <select v-model="q.sellStatus" class="select" @change="load(1)">
          <option value="">全部持仓</option>
          <option value="0">未寄售</option>
          <option value="1">寄售中</option>
        </select>
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="q.keyword = ''; q.sellStatus = ''; load(1)">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>NFT</th><th>藏品</th><th>持有人</th><th>当前价</th><th>寄售状态</th><th>铸造时间</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td class="cell-sub serif-num">#{{ r.nftId }}</td>
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img v-if="r.itemImage" :src="r.itemImage" class="thumb" loading="lazy" @error="e => e.target.style.visibility = 'hidden'" />
                  <div v-else class="thumb" style="display:flex;align-items:center;justify-content:center;color:#9fb4cf">无图</div>
                  <div class="cell-main">{{ r.item_name }}</div>
                </div>
              </td>
              <td>{{ r.nickname }}</td>
              <td class="serif-num">¥{{ money(toYuan(r.price)) }}</td>
              <td><span class="tag" :class="Number(r.sell_status) === 1 ? 'tag-orange' : 'tag-gray'">{{ Number(r.sell_status) === 1 ? '寄售中' : '未寄售' }}</span></td>
              <td class="cell-sub">{{ fmtTime(r.created_at) }}</td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">🧊</div>没有符合条件的持仓</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { fmtTime, money, toYuan } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ keyword: '', sellStatus: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/nfts', { params: { keyword: q.keyword, sellStatus: q.sellStatus, pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

onMounted(() => load(1))
</script>
