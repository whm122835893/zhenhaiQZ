<template>
  <div>
    <!-- 侧边栏 -->
    <aside class="glass sidebar rise" :class="{ collapsed }">
      <button class="side-toggle" type="button" @click="toggleSide" :title="collapsed ? '展开侧栏' : '收起侧栏'">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round"><path d="m14 6-6 6 6 6"/></svg>
      </button>
      <div class="brand">
        <div class="brand-logo">臻</div>
        <div class="brand-txt">
          <div class="brand-name">臻海艺术</div>
          <div class="brand-sub">ADMIN CONSOLE</div>
        </div>
      </div>

      <template v-for="g in nav" :key="g.label">
        <div class="nav-group-label">{{ g.label }}</div>
        <router-link
          v-for="it in g.items"
          :key="it.path"
          :to="it.path"
          class="nav-item"
          :class="{ active: $route.path === it.path }"
          :title="collapsed ? it.text : ''"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" v-html="icons[it.icon]"></svg>
          <span class="txt">{{ it.text }}</span>
        </router-link>
      </template>

      <div class="sidebar-foot">
        <div class="avatar-chip" style="padding-left:8px">
          <span class="ava">{{ (me.realName || me.username || 'A').slice(0, 1) }}</span>
          <span>
            <span class="nm">{{ me.realName || me.username || '—' }}</span>
            <span class="rl" style="display:block">{{ me.roleName || '' }}<template v-if="!writable"> · 只读</template></span>
          </span>
        </div>
        <div class="foot-btns" style="display:flex;gap:8px;margin-top:10px">
          <button class="btn btn-ghost btn-sm" style="flex:1" @click="pwdOpen = true">改密</button>
          <button class="btn btn-ghost btn-sm" style="flex:1" @click="logout">退出</button>
        </div>
      </div>
    </aside>

    <!-- 主区 -->
    <main class="main" :class="{ collapsed }">
      <div class="topbar">
        <div class="page-title rise" style="display:flex;align-items:center;gap:12px">
          <button class="btn btn-ghost btn-sm menu-btn" type="button" @click="toggleSide" title="收起/展开侧栏">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                 stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
          <span>{{ $route.meta.title }}
            <small>{{ ($route.meta.sub || '').toUpperCase() }}</small>
          </span>
        </div>
        <div class="topbar-right rise" style="--i:1">
          <span class="tag tag-blue" v-if="writable">写操作已开启</span>
          <span class="tag tag-gray" v-else>只读模式</span>
        </div>
      </div>
      <router-view />
    </main>

    <!-- 修改密码 -->
    <div class="modal-mask fadein" v-if="pwdOpen" @click.self="pwdOpen = false">
      <form class="glass modal pop" @submit.prevent="savePwd">
        <h3>修改登录密码</h3>
        <div class="form-row">
          <label>旧密码</label>
          <input v-model="pwd.oldPassword" class="input" type="password" autocomplete="current-password" />
        </div>
        <div class="form-row">
          <label>新密码（至少 8 位）</label>
          <input v-model="pwd.newPassword" class="input" type="password" autocomplete="new-password" />
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="pwdOpen = false">取消</button>
          <button class="btn btn-primary" :disabled="!pwd.oldPassword || !pwd.newPassword">保存</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import http, { toast, getProfile, setProfile, clearAuth, bindKick, canWrite } from '../api'

const route = useRoute()
const router = useRouter()

const me = ref(getProfile() || {})
const writable = computed(() => canWrite())
const pwdOpen = ref(false)
const pwd = reactive({ oldPassword: '', newPassword: '' })

/* ---------- 侧边栏折叠（记忆用户偏好） ---------- */
const collapsed = ref(localStorage.getItem('admin.sidebar.collapsed') === '1')
function toggleSide() {
  collapsed.value = !collapsed.value
  localStorage.setItem('admin.sidebar.collapsed', collapsed.value ? '1' : '0')
}

/* ---------- 导航 ---------- */
const nav = [
  { label: '概览', items: [{ path: '/dashboard', text: '数据仪表盘', icon: 'chart' }] },
  {
    label: '业务管理',
    items: [
      { path: '/users', text: '用户管理', icon: 'users' },
      { path: '/wallet', text: '钱包流水', icon: 'wallet' },
      { path: '/items', text: '藏品管理', icon: 'gem' },
      { path: '/orders', text: '订单管理', icon: 'receipt' },
      { path: '/market', text: '市场寄售', icon: 'store' },
      { path: '/nfts', text: '持仓查询', icon: 'cube' },
      { path: '/grant', text: '定向发放', icon: 'send' },
    ],
  },
  {
    label: '活动运营',
    items: [
      { path: '/activity/lottery', text: '抽奖活动', icon: 'gift' },
      { path: '/activity/merge', text: '合成活动', icon: 'merge' },
      { path: '/activity/invites', text: '邀请有礼', icon: 'heart' },
    ],
  },
  {
    label: '内容运营',
    items: [
      { path: '/cms/notices', text: '公告管理', icon: 'horn' },
      { path: '/cms/banners', text: '轮播管理', icon: 'image' },
    ],
  },
  {
    label: '系统',
    items: [
      { path: '/system/configs', text: '运营参数', icon: 'sliders' },
      { path: '/system/logs', text: '日志审计', icon: 'shield' },
    ],
  },
]

const icons = {
  chart: '<path d="M4 20V10M10 20V4M16 20v-6M21 20H3"/>',
  wallet: '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18a1 1 0 0 1 1 1v2"/><path d="M3 7.5V17a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H5.5A2.5 2.5 0 0 1 3 7.5Z"/><circle cx="16.5" cy="14" r="1.2" fill="currentColor" stroke="none"/>',
  users: '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.6-3.2 2.9-5 5.5-5s4.9 1.8 5.5 5"/><circle cx="17" cy="9" r="2.4"/><path d="M16 15.2c2.3.2 4 1.7 4.5 4.3"/>',
  gem: '<path d="M7 3h10l4 6-9 12L3 9l4-6z"/><path d="M3 9h18M12 21 8.5 9 12 3l3.5 6L12 21"/>',
  receipt: '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3z"/><path d="M9 8h6M9 12h6"/>',
  store: '<path d="M4 9 5.5 4h13L20 9"/><path d="M4 9a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0"/><path d="M5 11v9h14v-9M10 20v-5h4v5"/>',
  cube: '<path d="m12 2 8 4.5v9L12 22l-8-6.5v-9L12 2z"/><path d="m4 6.5 8 5 8-5M12 11.5V22"/>',
  send: '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
  horn: '<path d="M3 10v4l11 4V6L3 10z"/><path d="M14 8.5c2.5 0 4.5 1.6 4.5 3.5s-2 3.5-4.5 3.5M6 14.5V19h3v-3.5"/>',
  image: '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.8"/><path d="m5 18 5-5 3 3 3-3 3 3"/>',
  sliders: '<path d="M5 4v6M5 14v6M12 4v10M12 18v2M19 4v2M19 10v10"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="16" r="2"/><circle cx="19" cy="8" r="2"/>',
  shield: '<path d="M12 2 4.5 5v6c0 5 3.2 8.6 7.5 10 4.3-1.4 7.5-5 7.5-10V5L12 2z"/><path d="m9 11.5 2 2 4-4.5"/>',
  gift: '<rect x="3" y="9" width="18" height="4" rx="1"/><path d="M5 13v8h14v-8M12 9v12M12 9s-4.5.2-4.5-2.5C7.5 4.6 9 4 10 4.4c1.6.6 2 4.6 2 4.6s.4-4 2-4.6c1-.4 2.5.2 2.5 2.1C16.5 9.2 12 9 12 9z"/>',
  merge: '<path d="M7 4h6a4 4 0 0 1 4 4v0a4 4 0 0 1-4 4H7"/><path d="m10 9-3 3 3 3"/><path d="M17 12v8M14 17l3 3 3-3"/>',
  heart: '<path d="M12 20.5S4 15.5 4 9.9C4 7.2 6.1 5 8.8 5c1.3 0 2.5.6 3.2 1.6A4 4 0 0 1 15.2 5C17.9 5 20 7.2 20 9.9c0 5.6-8 10.6-8 10.6z"/>',
}

/* ---------- 行为 ---------- */
onMounted(async () => {
  bindKick(() => router.replace('/login'))
  try {
    // 刷新真实资料（角色可能被调整）
    const data = await http.get('/auth/me')
    me.value = data.profile
    setProfile(data.profile)
  } catch {
    /* 拦截器已处理 */
  }
})

async function logout() {
  try {
    await http.post('/auth/logout')
  } catch {
    /* 忽略 */
  }
  clearAuth()
  router.replace('/login')
}

async function savePwd() {
  try {
    await http.post('/auth/password', pwd)
    toast('密码已更新，下次登录生效')
    pwdOpen.value = false
    pwd.oldPassword = ''
    pwd.newPassword = ''
  } catch {
    /* 拦截器已 toast */
  }
}
</script>
