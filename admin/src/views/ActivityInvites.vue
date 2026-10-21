<template>
  <div class="page-body">
    <!-- 统计 -->
    <div class="stat-grid" style="margin-bottom:18px">
      <div class="glass stat-card rise">
        <div class="lb">累计邀请</div>
        <div class="v serif-num">{{ stats.total }}</div>
        <div class="sub">全部邀请记录数</div>
      </div>
      <div class="glass stat-card rise" style="--i:1">
        <div class="lb">已发放奖励</div>
        <div class="v serif-num">{{ stats.granted }}<em>笔</em></div>
        <div class="sub">reward_status = granted</div>
      </div>
      <div class="glass stat-card rise" style="--i:2">
        <div class="lb">待发放</div>
        <div class="v serif-num">{{ stats.pending }}<em>笔</em></div>
        <div class="sub">满足时机后自动结算</div>
      </div>
      <div class="glass stat-card rise" style="--i:3">
        <div class="lb">当前奖励参数</div>
        <div class="v serif-num">
          <template v-if="stats.configItem">{{ stats.configItem }} ×{{ stats.configQty }}</template>
          <template v-else>未配置</template>
        </div>
        <div class="sub">
          <span class="tag" :class="stats.configEnabled ? 'tag-green' : 'tag-gray'">{{ stats.configEnabled ? '邀请奖励已开启' : '邀请奖励未开启' }}</span>
          <span class="cell-sub" style="margin-left:6px">在下方「奖励配置」调整</span>
        </div>
      </div>
    </div>

    <!-- 奖励配置（签到 + 邀请） -->
    <div class="glass card-pad rise" style="margin-bottom:18px" v-if="cfg">
      <div class="filters" style="align-items:flex-end">
        <div>
          <div class="cfg-lb">邀请奖励开关</div>
          <label class="cfg-switch"><input type="checkbox" v-model="cfg.invite.enabled" /> {{ cfg.invite.enabled ? '开启' : '关闭' }}</label>
        </div>
        <div>
          <div class="cfg-lb">结算时机</div>
          <select v-model="cfg.invite.settleTiming" class="input" style="width:150px">
            <option value="register">注册即发</option>
            <option value="first_order">首单支付后</option>
          </select>
        </div>
        <div>
          <div class="cfg-lb">奖励藏品</div>
          <select v-model="cfg.invite.rewardIid" class="input" style="width:200px">
            <option value="">选择藏品</option>
            <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
          </select>
        </div>
        <div>
          <div class="cfg-lb">数量</div>
          <input v-model.number="cfg.invite.rewardQty" type="number" min="1" max="100" class="input" style="width:90px" />
        </div>
        <div style="flex:1;min-width:260px">
          <div class="cfg-lb">签到奖励档位（连续第 1..N 天的积分，逗号分隔）</div>
          <input v-model.trim="cfg.signRewardsText" class="input" placeholder="如 1,2,3,5,7" style="width:100%" />
        </div>
        <button v-if="canWrite()" class="btn btn-primary" :disabled="savingCfg" @click="saveCfg">{{ savingCfg ? '保存中…' : '保存配置' }}</button>
      </div>

      <div style="margin-top:14px;padding-top:12px;border-top:1px dashed rgba(120,145,180,.25)">
        <div class="filters" style="align-items:flex-end">
          <div style="flex:1;min-width:420px">
            <div class="cfg-lb">签到里程碑空投（连续签到达第 N 天，直接发藏品；每档每用户仅发一次，留空不启用）</div>
            <div v-for="(m, i) in cfg.signMilestones" :key="i" class="filters" style="margin-bottom:6px;gap:8px">
              <span class="cell-sub">连续第</span>
              <input v-model.number="m.day" type="number" min="1" max="366" class="input" style="width:90px" placeholder="天数" />
              <span class="cell-sub">天发</span>
              <select v-model="m.iid" class="input" style="width:200px">
                <option value="">选择藏品</option>
                <option v-for="it in items" :key="it.id" :value="it.id">{{ it.name }}</option>
              </select>
              <span class="cell-sub">×</span>
              <input v-model.number="m.qty" type="number" min="1" max="100" class="input" style="width:80px" />
              <button class="btn btn-ghost btn-sm" type="button" @click="cfg.signMilestones.splice(i, 1)">删除</button>
            </div>
            <button class="btn btn-blue btn-sm" type="button" @click="cfg.signMilestones.push({ day: null, iid: '', qty: 1 })">+ 添加里程碑</button>
          </div>
        </div>
      </div>

      <div class="cell-sub" style="margin-top:6px">
        签到积分在第 {{ (cfg.signRewards || []).length }} 档封顶（连续超过天数按最后一档发放）；「首单支付后」时机以订单标记已支付为准；配置保存后立即生效，写操作日志。
      </div>
    </div>

    <!-- 记录 -->
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="邀请人 / 被邀请人昵称" @keyup.enter="load(1)" />
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="q.keyword = ''; load(1)">重置</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>邀请人</th><th>被邀请人</th><th>邀请码</th><th>奖励状态</th><th>发放时间</th><th>注册时间</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td class="cell-main">{{ r.inviter_name || r.uid }}</td>
              <td class="cell-main">{{ r.invitee_name || r.inviteeUid }}</td>
              <td class="cell-sub">{{ r.invite_code }}</td>
              <td>
                <span class="tag" :class="r.reward_status === 'granted' ? 'tag-green' : r.reward_status === 'invalid' ? 'tag-gray' : 'tag-orange'">
                  {{ rewardText(r.reward_status) }}
                </span>
              </td>
              <td class="cell-sub">{{ r.rewardedAt || '—' }}</td>
              <td class="cell-sub">{{ fmtTime(r.created_at) }}</td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">💌</div>暂无邀请记录</div></td>
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
import http, { fmtTime, canWrite, toast } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ keyword: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const stats = reactive({ total: 0, granted: 0, pending: 0, configEnabled: false, configItem: '', configQty: 1 })
const cfg = ref(null)
const savingCfg = ref(false)
const items = ref([])

async function loadItems() {
  try {
    const data = await http.get('/items', { params: { pageNo: 1, pageSize: 100 } })
    items.value = data.list || []
  } catch { /* 忽略 */ }
}

async function loadCfg() {
  try {
    const d = await http.get('/activity/reward-config')
    cfg.value = {
      ...d,
      signRewardsText: (d.signRewards || []).join(','),
      signMilestones: (d.signMilestones || []).map(m => ({ day: Number(m.day), iid: String(m.iid), qty: Number(m.qty) || 1 })),
      invite: {
        enabled: !!d.invite?.enabled,
        rewardIid: String(d.invite?.rewardIid || ''),
        rewardQty: Number(d.invite?.rewardQty) || 1,
        settleTiming: d.invite?.settleTiming || 'register',
      },
    }
  } catch { /* 拦截器已 toast */ }
}

async function saveCfg() {
  const tiers = (cfg.value.signRewardsText || '')
    .split(',').map(s => parseInt(s.trim(), 10)).filter(n => Number.isInteger(n) && n > 0)
  if (tiers.length === 0) {
    toast('签到奖励档位格式不正确（如 1,2,3,5,7）', true)
    return
  }
  const ms = cfg.value.signMilestones.filter(m => (m.day > 0 || m.iid))
  for (const m of ms) {
    if (!(m.day > 0) || !m.iid || !(m.qty > 0)) {
      toast('签到里程碑每行需完整填写：天数 / 藏品 / 数量', true)
      return
    }
  }
  if (cfg.value.invite.enabled && !cfg.value.invite.rewardIid) {
    toast('邀请奖励开启时必须选择奖励藏品', true)
    return
  }
  savingCfg.value = true
  try {
    const d = await http.put('/activity/reward-config', {
      signRewards: tiers,
      signMilestones: ms.map(m => ({ day: Number(m.day), iid: String(m.iid), qty: Number(m.qty) || 1 })),
      invite: {
        enabled: cfg.value.invite.enabled,
        rewardIid: String(cfg.value.invite.rewardIid || ''),
        rewardQty: Number(cfg.value.invite.rewardQty) || 1,
        settleTiming: cfg.value.invite.settleTiming,
      },
    })
    cfg.value = {
      ...d,
      signRewardsText: (d.signRewards || []).join(','),
      signMilestones: (d.signMilestones || []).map(m => ({ day: Number(m.day), iid: String(m.iid), qty: Number(m.qty) || 1 })),
      invite: {
        enabled: !!d.invite?.enabled,
        rewardIid: String(d.invite?.rewardIid || ''),
        rewardQty: Number(d.invite?.rewardQty) || 1,
        settleTiming: d.invite?.settleTiming || 'register',
      },
    }
    toast('奖励配置已保存')
    load()
  } catch { /* 拦截器已 toast */ } finally {
    savingCfg.value = false
  }
}

onMounted(() => {
  load(1)
  loadCfg()
  loadItems()
})

const rewardText = s => ({ pending: '待发放', granted: '已发放', invalid: '已失效' }[s] || s)

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/activity/invites', { params: { keyword: q.keyword, pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
    Object.assign(stats, data.stats || {})
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.cfg-lb { font-size: 12px; color: #6b7f9e; margin-bottom: 6px; }
.cfg-switch { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #1c2b45; }
</style>
