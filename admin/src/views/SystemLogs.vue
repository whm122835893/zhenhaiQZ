<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <div class="tabs">
          <button :class="{ on: tab === 'operation' }" @click="switchTab('operation')">操作日志</button>
          <button :class="{ on: tab === 'audit' }" @click="switchTab('audit')">登录/审计日志</button>
        </div>
        <select v-model="q.module" class="select" @change="load(1)">
          <option value="">全部模块</option>
          <option value="auth">auth · 认证</option>
          <option value="users">users · 用户</option>
          <option value="items">items · 藏品</option>
          <option value="market">market · 市场</option>
          <option value="cms">cms · 内容</option>
          <option value="system">system · 系统</option>
        </select>
        <div class="spacer"></div>
        <button class="btn btn-ghost" @click="load()">刷新</button>
      </div>

      <!-- 操作日志：谁在什么时候改了什么 -->
      <div class="tbl-wrap" v-if="tab === 'operation'">
        <table class="tbl">
          <thead>
            <tr><th>#</th><th>操作人</th><th>模块</th><th>动作</th><th>变更内容</th><th>IP</th><th>时间</th></tr>
          </thead>
          <tbody>
            <tr v-for="l in list" :key="l.id">
              <td class="cell-sub serif-num">{{ l.id }}</td>
              <td class="cell-main">{{ l.username || `admin#${l.admin_id}` }}</td>
              <td><span class="tag tag-blue">{{ l.module }}</span></td>
              <td class="cell-sub">{{ l.action }}</td>
              <td>
                <button v-if="l.before_data || l.after_data" class="btn btn-ghost btn-sm" @click="viewDiff(l)">查看</button>
                <span v-else class="cell-sub">—</span>
              </td>
              <td class="cell-sub">{{ l.ip || '—' }}</td>
              <td class="cell-sub">{{ fmtTime(l.created_at) }}</td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="7"><div class="empty"><div class="glyph">🛡️</div>暂无操作日志</div></td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 审计日志：登录成败等安全事件 -->
      <div class="tbl-wrap" v-else>
        <table class="tbl">
          <thead>
            <tr><th>#</th><th>模块</th><th>动作</th><th>对象</th><th>详情</th><th>IP</th><th>时间</th></tr>
          </thead>
          <tbody>
            <tr v-for="l in list" :key="l.id">
              <td class="cell-sub serif-num">{{ l.id }}</td>
              <td><span class="tag tag-gray">{{ l.module }}</span></td>
              <td>
                <span class="tag" :class="String(l.action).includes('failed') ? 'tag-red' : 'tag-green'">{{ l.action }}</span>
              </td>
              <td class="cell-sub">{{ l.target || '—' }}</td>
              <td class="cell-sub" style="max-width:260px;overflow:hidden;text-overflow:ellipsis">{{ l.detail || '—' }}</td>
              <td class="cell-sub">{{ l.ip || '—' }}</td>
              <td class="cell-sub">{{ fmtTime(l.created_at) }}</td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="7"><div class="empty"><div class="glyph">🔍</div>暂无审计日志</div></td>
            </tr>
          </tbody>
        </table>
      </div>

      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 变更详情 -->
    <div class="modal-mask fadein" v-if="diff" @click.self="diff = null">
      <div class="glass modal pop" style="width:640px">
        <h3>变更详情 <span class="cell-sub">#{{ diff.id }} · {{ diff.module }} / {{ diff.action }}</span></h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <div class="cell-sub" style="margin-bottom:6px">变更前 BEFORE</div>
            <div class="pre">{{ pretty(diff.before_data) || '（空）' }}</div>
          </div>
          <div>
            <div class="cell-sub" style="margin-bottom:6px">变更后 AFTER</div>
            <div class="pre">{{ pretty(diff.after_data) || '（空）' }}</div>
          </div>
        </div>
        <div class="modal-foot">
          <button class="btn btn-ghost" @click="diff = null">关闭</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { fmtTime } from '../api'
import Pager from '../components/Pager.vue'

const tab = ref('operation')
const q = reactive({ module: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const diff = ref(null)

const pretty = s => {
  if (!s) return ''
  try {
    return JSON.stringify(JSON.parse(s), null, 2)
  } catch {
    return String(s)
  }
}

function switchTab(t) {
  tab.value = t
  load(1)
}

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const url = tab.value === 'operation' ? '/system/logs/operation' : '/system/logs/audit'
    const data = await http.get(url, { params: { module: q.module, pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

function viewDiff(l) {
  diff.value = l
}

onMounted(() => load(1))
</script>
