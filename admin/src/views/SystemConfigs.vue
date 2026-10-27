<template>
  <div class="page-body">
    <!-- 平台品牌：改 C 端 + 后台所有展示位 -->
    <div class="glass card-pad rise" style="margin-bottom:14px">
      <div class="filters">
        <div>
          <div class="card-head" style="margin-bottom:2px">平台品牌<span>BRAND</span></div>
          <div class="cell-sub">平台名称、logo、首页主视觉全局生效：后台侧边栏/登录页/页面标题、C 端首页/关于页/证书/协议等所有展示位</div>
        </div>
        <div class="spacer"></div>
        <button v-if="canSuper()" class="btn btn-primary" :disabled="!brandDirty || brandSaving" @click="saveBrand">
          {{ brandSaving ? '保存中…' : brandDirty ? '保存品牌' : '暂无改动' }}
        </button>
      </div>

      <div class="cfg-grid" style="grid-template-columns:auto auto 1fr;align-items:start;gap:18px">
        <!-- logo 预览与上传 -->
        <div style="text-align:center">
          <img v-if="bf.logo" :src="bf.logo" alt="logo"
               style="width:72px;height:72px;border-radius:14px;object-fit:cover;display:block;margin:0 auto 8px" />
          <div v-else class="brand-logo" style="width:72px;height:72px;font-size:30px;margin:0 auto 8px">{{ (bf.name || 'A').slice(0, 1) }}</div>
          <input ref="logoFile" type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml" style="display:none" @change="uploadLogo" />
          <button type="button" class="btn btn-ghost btn-sm" :disabled="brandSaving" @click="$refs.logoFile.click()">上传 logo</button>
          <button v-if="bf.logo" type="button" class="btn btn-ghost btn-sm" style="margin-left:4px" @click="bf.logo = ''; brandDirty = true">清除</button>
          <div class="cell-sub" style="margin-top:6px">平台 logo</div>
        </div>

        <!-- 首页主视觉预览与上传 -->
        <div style="text-align:center">
          <img v-if="bf.banner" :src="bf.banner" alt="首页主视觉"
               style="width:128px;height:72px;border-radius:14px;object-fit:cover;display:block;margin:0 auto 8px" />
          <div v-else class="brand-logo" style="width:128px;height:72px;font-size:16px;margin:0 auto 8px">主视觉</div>
          <input ref="bannerFile" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="uploadBanner" />
          <button type="button" class="btn btn-ghost btn-sm" :disabled="brandSaving" @click="$refs.bannerFile.click()">上传主视觉</button>
          <button v-if="bf.banner" type="button" class="btn btn-ghost btn-sm" style="margin-left:4px" @click="bf.banner = ''; brandDirty = true">清除</button>
          <div class="cell-sub" style="margin-top:6px">C 端首页顶部大图</div>
        </div>

        <!-- 名称与首页文案 -->
        <div>
          <div class="form-row">
            <label>平台名称</label>
            <input v-model.trim="bf.name" class="input" maxlength="20" placeholder="例如：臻海艺术" :disabled="!canSuper()" @input="brandDirty = true" />
            <div class="cell-sub" style="margin-top:6px">用于侧边栏、登录页、浏览器标签标题、C 端首页/关于页/数字藏品证书/注册协议等全部展示位</div>
          </div>
          <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
            <div>
              <label>首页主标题·第一行</label>
              <input v-model.trim="bf.heroTitle1" class="input" maxlength="12" placeholder="让热爱" :disabled="!canSuper()" @input="brandDirty = true" />
            </div>
            <div>
              <label>首页主标题·第二行</label>
              <input v-model.trim="bf.heroTitle2" class="input" maxlength="12" placeholder="奔赴更大的世界" :disabled="!canSuper()" @input="brandDirty = true" />
            </div>
          </div>
          <div class="form-row" style="margin-top:10px">
            <label>首页副标题</label>
            <input v-model.trim="bf.heroSubtitle" class="input" maxlength="20" placeholder="数字艺术·收藏·交易·共建" :disabled="!canSuper()" @input="brandDirty = true" />
          </div>
          <div class="form-row" v-if="!canSuper()">
            <div class="cell-sub">仅超级管理员可修改品牌信息</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 运营参数 -->
    <div class="glass card-pad rise">
      <div class="filters">
        <div>
          <div class="card-head" style="margin-bottom:2px">运营参数<span>RUNTIME CONFIGS</span></div>
          <div class="cell-sub">修改后立即生效（DB 优先，文件兜底），所有变更落操作日志</div>
        </div>
        <div class="spacer"></div>
        <button v-if="canWrite()" class="btn btn-primary" :disabled="!dirty || saving" @click="save">
          {{ saving ? '保存中…' : dirty ? '保存修改' : '暂无改动' }}
        </button>
      </div>

      <div class="cfg-grid">
        <div v-for="c in rows" :key="c.config_key" class="cfg-cell">
          <div class="k">{{ c.config_key }}</div>
          <div class="rm">{{ c.remark || '—' }}</div>
          <input v-model.trim="c._value" class="input" :disabled="!canWrite()" @input="mark(c)" />
          <div class="cell-sub" style="margin-top:5px">更新于 {{ fmtTime(c.updated_at) }}</div>
        </div>
      </div>
      <div v-if="!loading && rows.length === 0" class="empty"><div class="glyph">⚙️</div>暂无运营参数</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import http, { toast, fmtTime, canWrite, canSuper, brand, loadBrand } from '../api'

/* ---------- 平台品牌 ---------- */
const bf = reactive({ name: '', logo: '', banner: '', heroTitle1: '', heroTitle2: '', heroSubtitle: '' })   // 编辑副本
const brandSaving = ref(false)
const brandDirty = ref(false)

async function loadBrandForm() {
  try {
    const d = await http.get('/basic/app')
    bf.name = String(d?.data?.app_name || '')
    bf.logo = String(d?.data?.app_logo || '')
    bf.banner = String(d?.data?.home_banner || '')
    bf.heroTitle1 = String(d?.data?.hero_title_1 || '')
    bf.heroTitle2 = String(d?.data?.hero_title_2 || '')
    bf.heroSubtitle = String(d?.data?.hero_subtitle || '')
  } catch { /* 忽略 */ }
  brandDirty.value = false
}

/** logo / 主视觉共用上传（走 /upload/image），field 区分写回哪个字段 */
async function uploadImage(e, field) {
  const f = e.target.files?.[0]
  if (!f) return
  if (f.size > 5 * 1024 * 1024) {
    toast('图片请小于 5MB', true)
    e.target.value = ''
    return
  }
  brandSaving.value = true
  try {
    const fd = new FormData()
    fd.append('file', f)
    const d = await http.post('/upload/image', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    bf[field] = d?.url || d?.data?.url || ''
    brandDirty.value = true
    toast('图片已上传，点击「保存品牌」生效')
  } catch {
    /* 拦截器已 toast */
  } finally {
    brandSaving.value = false
    e.target.value = ''
  }
}

const uploadLogo = (e) => uploadImage(e, 'logo')
const uploadBanner = (e) => uploadImage(e, 'banner')

async function saveBrand() {
  if (!bf.name) {
    toast('平台名称不能为空', true)
    return
  }
  brandSaving.value = true
  try {
    await http.put('/system/configs', {
      items: [
        { key: 'app.name', value: bf.name },
        { key: 'app.logo', value: bf.logo },
        { key: 'app.home_banner', value: bf.banner },
        { key: 'app.hero_title_1', value: bf.heroTitle1 },
        { key: 'app.hero_title_2', value: bf.heroTitle2 },
        { key: 'app.hero_subtitle', value: bf.heroSubtitle },
      ],
    })
    await loadBrand()          // 全局 brand 即时生效：侧边栏/登录页/标题
    document.title = `${brand.name}管理后台`
    toast('品牌已保存，C 端与后台展示位已更新')
    brandDirty.value = false
  } catch {
    /* 拦截器已 toast */
  } finally {
    brandSaving.value = false
  }
}

/* ---------- 运营参数 ---------- */
const rows = ref([])
const loading = ref(true)
const saving = ref(false)
const dirtyKeys = ref(new Set())

const dirty = computed(() => dirtyKeys.value.size > 0)

function mark(c) {
  if (c._value !== c.config_value) dirtyKeys.value.add(c.config_key)
  else dirtyKeys.value.delete(c.config_key)
  dirtyKeys.value = new Set(dirtyKeys.value) // 触发响应
}

async function load() {
  loading.value = true
  try {
    const data = await http.get('/system/configs')
    rows.value = (data.list || []).map(r => ({ ...r, _value: r.config_value }))
    dirtyKeys.value = new Set()
  } finally {
    loading.value = false
  }
}

async function save() {
  const items = rows.value
    .filter(r => dirtyKeys.value.has(r.config_key))
    .map(r => ({ key: r.config_key, value: r._value }))
  if (items.length === 0) return

  saving.value = true
  try {
    const res = await http.put('/system/configs', { items })
    toast(`已保存 ${res.changed?.length ?? items.length} 项参数`)
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load()
  loadBrandForm()
})
</script>
