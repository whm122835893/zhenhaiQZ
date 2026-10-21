<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="账号 / 手机号 / 昵称" @keyup.enter="load(1)" />
        <select v-model="q.status" class="select" @change="load(1)">
          <option value="">全部状态</option>
          <option value="normal">正常</option>
          <option value="disabled">已禁用</option>
        </select>
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="reset">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>ID</th><th>用户</th><th>手机号</th><th>邮箱</th><th>实名</th><th>邀请码</th><th>状态</th><th>最近登录</th><th>注册时间</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="u in list" :key="u.id">
              <td class="cell-sub serif-num">{{ u.id }}</td>
              <td>
                <div class="cell-main">{{ u.nickname }}</div>
                <div class="cell-sub">{{ u.account }}</div>
              </td>
              <td>{{ u.phone || '—' }}</td>
              <td class="cell-sub">{{ u.email || '—' }}</td>
              <td><span class="tag" :class="u.hasReal ? 'tag-green' : 'tag-gray'">{{ u.hasReal ? '已实名' : '未实名' }}</span></td>
              <td class="cell-sub">{{ u.invite_code || '—' }}</td>
              <td><span class="tag" :class="u.status === 'normal' ? 'tag-green' : 'tag-red'">{{ u.status === 'normal' ? '正常' : '已禁用' }}</span></td>
              <td class="cell-sub">{{ fmtTime(u.last_login_at) }}</td>
              <td class="cell-sub">{{ fmtTime(u.created_at) }}</td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="btn btn-ghost btn-sm" @click="openDetail(u)">详情</button>
                  <button v-if="canWrite()" class="btn btn-sm" :class="u.status === 'normal' ? 'btn-danger' : 'btn-primary'"
                          :disabled="busy === u.id" @click="toggleStatus(u)">
                    {{ u.status === 'normal' ? '禁用' : '启用' }}
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="10"><div class="empty"><div class="glyph">🫧</div>没有符合条件的用户</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 用户详情 -->
    <div class="modal-mask fadein" v-if="detail" @click.self="detail = null">
      <div class="glass modal pop">
        <h3>用户详情 <span class="cell-sub">#{{ detail.id }}</span></h3>
        <div class="kv" style="margin-bottom:16px">
          <div class="item"><div class="k">账号</div><div class="v">{{ detail.account }}</div></div>
          <div class="item"><div class="k">昵称</div><div class="v">{{ detail.nickname || '—' }}</div></div>
          <div class="item"><div class="k">手机号</div><div class="v">{{ detail.phone || '—' }}</div></div>
          <div class="item"><div class="k">邮箱</div><div class="v">{{ detail.email || '—' }}</div></div>
          <div class="item"><div class="k">实名</div><div class="v">{{ detail.realName || '未实名' }}</div></div>
          <div class="item"><div class="k">邀请码</div><div class="v">{{ detail.inviteCode || '—' }}</div></div>
          <div class="item"><div class="k">注册时间</div><div class="v">{{ fmtTime(detail.createdAt) }}</div></div>
          <div class="item"><div class="k">最近登录</div><div class="v">{{ fmtTime(detail.lastLoginAt) }}</div></div>
          <div class="item"><div class="k">持仓数</div><div class="v serif-num">{{ detail.nftCount }}</div></div>
          <div class="item"><div class="k">订单数</div><div class="v serif-num">{{ detail.orderCount }}</div></div>
        </div>
        <div class="card-head" style="margin-top:6px">钱包<span>WALLET</span></div>
        <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);gap:10px">
          <div class="cfg-cell"><div class="k">余额</div><div class="v serif-num" style="font-size:16px">¥{{ money(detail.wallet.balance) }}</div></div>
          <div class="cfg-cell"><div class="k">冻结</div><div class="v serif-num" style="font-size:16px">¥{{ money(detail.wallet.frozen) }}</div></div>
          <div class="cfg-cell"><div class="k">累计收入</div><div class="v serif-num" style="font-size:16px">¥{{ money(detail.wallet.income) }}</div></div>
          <div class="cfg-cell"><div class="k">累计支出</div><div class="v serif-num" style="font-size:16px">¥{{ money(detail.wallet.expense) }}</div></div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-ghost" @click="detail = null">关闭</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, fmtTime, money, canWrite } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ keyword: '', status: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const busy = ref('')
const detail = ref(null)

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/users', { params: { ...q } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

function reset() {
  q.keyword = ''
  q.status = ''
  load(1)
}

async function toggleStatus(u) {
  const next = u.status === 'normal' ? 'disabled' : 'normal'
  const tip = `确认${next === 'disabled' ? '禁用' : '启用'}用户「${u.nickname}」（${u.account}）？`
  if (!confirm(tip)) return
  busy.value = u.id
  try {
    await http.post(`/users/${u.id}/status`, { status: next })
    u.status = next
    toast(next === 'disabled' ? '已禁用，该用户无法登录' : '已恢复正常')
  } catch {
    /* 拦截器已 toast */
  } finally {
    busy.value = ''
  }
}

async function openDetail(u) {
  detail.value = null
  try {
    detail.value = await http.get(`/users/${u.id}`)
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(() => load(1))
</script>
