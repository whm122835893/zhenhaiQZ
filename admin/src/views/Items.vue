<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="藏品名称" @keyup.enter="load(1)" />
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="q.keyword = ''; load(1)">重置</button>
        <span style="flex:1"></span>
        <button v-if="canWrite()" class="btn btn-primary" @click="openCreate">＋ 新建藏品</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>藏品</th><th>价格</th><th>发行 / 已购</th><th>标记</th><th>寄售</th><th>状态</th><th>浏览</th><th>创建时间</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="it in list" :key="it.id">
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img v-if="it.imageUrl" :src="it.imageUrl" class="thumb" loading="lazy" @error="e => e.target.style.visibility = 'hidden'" />
                  <div v-else class="thumb" style="display:flex;align-items:center;justify-content:center;color:#9fb4cf">无图</div>
                  <div>
                    <div class="cell-main">{{ it.name }}</div>
                    <div class="cell-sub">iid {{ it.iid }}</div>
                  </div>
                </div>
              </td>
              <td class="serif-num">¥{{ money(toYuan(it.price)) }}</td>
              <td class="serif-num">{{ it.issue_quantity }} / {{ it.buy_quantity }}</td>
              <td>
                <span v-if="Number(it.is_recommend) === 1" class="tag tag-blue">推荐</span>
                <span v-if="Number(it.is_popular) === 1" class="tag tag-orange">热门</span>
                <span v-if="Number(it.is_sold_out) === 1" class="tag tag-gray">售罄</span>
                <span v-if="!Number(it.is_recommend) && !Number(it.is_popular) && !Number(it.is_sold_out)" class="cell-sub">—</span>
              </td>
              <td>
                <span class="tag" :class="Number(it.is_secondary) === 1 ? 'tag-green' : 'tag-gray'">
                  {{ Number(it.is_secondary) === 1 ? '可寄售' : '禁止寄售' }}
                </span>
              </td>
              <td>
                <span class="tag" :class="saleState(it).cls">{{ saleState(it).text }}</span>
                <div v-if="saleState(it).sub" class="cell-sub">{{ saleState(it).sub }}</div>
              </td>
              <td class="serif-num">{{ it.view_count }}</td>
              <td class="cell-sub">{{ fmtTime(it.created_at) }}</td>
              <td>
                <template v-if="canWrite()">
                  <button class="btn btn-ghost btn-sm" @click="openEdit(it)">编辑</button>
                  <!-- 未发售 / 已排期都可「开售」：已排期可重新排期或立即开售 -->
                  <button v-if="Number(it.status) !== 1 || saleState(it).text === '已排期'" class="btn btn-primary btn-sm" style="margin-left:6px" @click="openOnsale(it)">开售</button>
                  <button v-if="Number(it.status) === 1" class="btn btn-ghost btn-sm" style="margin-left:6px" @click="doOffsale(it)">下架</button>
                </template>
                <span v-else class="cell-sub">只读</span>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="9"><div class="empty"><div class="glyph">🖼️</div>没有符合条件的藏品</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 编辑 -->
    <div class="modal-mask fadein" v-if="edit" @click.self="edit = null">
      <form class="glass modal pop" @submit.prevent="save">
        <h3>编辑藏品 <span class="cell-sub">{{ edit.name }}</span></h3>
        <div class="form-row">
          <label>藏品图片（上传或填 URL）</label>
          <div class="img-field">
            <img v-if="form.image_url" :src="form.image_url" class="img-preview" @error="e => e.target.style.visibility = 'hidden'" />
            <div style="flex:1;display:flex;flex-direction:column;gap:8px">
              <input ref="editFile" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="e => uploadFile(e, 'edit')" />
              <div>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="uploading" @click="$refs.editFile.click()">
                  {{ uploading ? '上传中…' : '上传图片' }}
                </button>
              </div>
              <input v-model.trim="form.image_url" class="input" placeholder="或直接填图片 URL" />
            </div>
          </div>
        </div>
        <div class="form-row">
          <label>名称</label>
          <input v-model.trim="form.name" class="input" />
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>价格（元）</label>
            <input v-model="form.price" class="input" type="number" step="0.01" min="0" />
          </div>
          <div>
            <label>发行量</label>
            <input v-model="form.issue_quantity" class="input" type="number" min="0" />
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>开售时间</label>
            <input v-model="form.start_time" class="input" placeholder="YYYY-MM-DD HH:MM:SS" />
          </div>
          <div>
            <label>结束时间</label>
            <input v-model="form.end_time" class="input" placeholder="YYYY-MM-DD HH:MM:SS" />
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>上架状态</label>
            <select v-model="form.status" class="select" style="width:100%">
              <option value="1">发售中</option>
              <option value="0">未发售</option>
            </select>
          </div>
          <div class="checks" style="align-items:flex-end;padding-bottom:6px">
            <label><input type="checkbox" v-model="form.is_recommend" /> 推荐</label>
            <label><input type="checkbox" v-model="form.is_popular" /> 热门</label>
            <label><input type="checkbox" v-model="form.is_sold_out" /> 售罄</label>
          </div>
        </div>

        <div class="sec-divider">二级市场（寄售）设置</div>
        <div class="checks" style="margin-bottom:12px">
          <label><input type="checkbox" v-model="form.is_secondary" /> 单品寄售开关</label>
          <label><input type="checkbox" v-model="form.is_secondary_batch" /> 批量寄售</label>
          <label><input type="checkbox" v-model="form.is_secondary_apply" /> 寄售申请</label>
          <label><input type="checkbox" v-model="form.is_secondary_trust" /> 寄售托管的</label>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>寄售最低价（元，0 不限）</label>
            <input v-model="form.secondary_low_price" class="input" type="number" step="0.01" min="0" />
          </div>
          <div>
            <label>寄售最高价（元，0 不限）</label>
            <input v-model="form.secondary_high_price" class="input" type="number" step="0.01" min="0" />
          </div>
        </div>
        <div class="cell-sub" style="margin-bottom:12px">关闭「单品寄售开关」后，该藏品持有者将无法在市场挂单寄售。</div>

        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="edit = null">取消</button>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? '保存中…' : '保存' }}</button>
        </div>
      </form>
    </div>

    <!-- 新建 -->
    <div class="modal-mask fadein" v-if="creating" @click.self="creating = false">
      <form class="glass modal pop" @submit.prevent="create">
        <h3>新建藏品</h3>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>名称 *</label>
            <input v-model.trim="nf.name" class="input" required />
          </div>
          <div>
            <label>价格（元）*</label>
            <input v-model="nf.price" class="input" type="number" step="0.01" min="0.01" required />
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>分类 *</label>
            <select v-model="nf.cid" class="select" style="width:100%" required>
              <option value="" disabled>请选择</option>
              <option v-for="c in meta.categories" :key="c.cid" :value="c.cid">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label>作者</label>
            <select v-model="nf.author_id" class="select" style="width:100%">
              <option value="">未指定</option>
              <option v-for="a in meta.authors" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>发行量</label>
            <input v-model="nf.issue_quantity" class="input" type="number" min="0" />
          </div>
          <div class="checks" style="align-items:flex-end;padding-bottom:6px">
            <label><input type="checkbox" v-model="nf.is_recommend" /> 推荐</label>
            <label><input type="checkbox" v-model="nf.is_popular" /> 热门</label>
          </div>
        </div>
        <div class="form-row">
          <div class="cell-sub" style="width:100%">创建后为「未发售」状态，不出现在 C 端首页；请在列表点「开售」并选择发售时间上架。</div>
        </div>
        <div class="form-row">
          <label>藏品图片（上传或填 URL）*</label>
          <div class="img-field">
            <img v-if="nf.image_url" :src="nf.image_url" class="img-preview" @error="e => e.target.style.visibility = 'hidden'" />
            <div style="flex:1;display:flex;flex-direction:column;gap:8px">
              <input ref="createFile" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="e => uploadFile(e, 'create')" />
              <div>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="uploading" @click="$refs.createFile.click()">
                  {{ uploading ? '上传中…' : '上传图片' }}
                </button>
              </div>
              <input v-model.trim="nf.image_url" class="input" placeholder="或直接填图片 URL" required />
            </div>
          </div>
        </div>
        <div class="form-row">
          <label>详情内容</label>
          <textarea v-model="nf.content" class="input" rows="3"></textarea>
        </div>

        <div class="sec-divider">二级市场（寄售）设置</div>
        <div class="checks" style="margin-bottom:12px">
          <label><input type="checkbox" v-model="nf.is_secondary" /> 单品寄售开关</label>
          <label><input type="checkbox" v-model="nf.is_secondary_batch" /> 批量寄售</label>
          <label><input type="checkbox" v-model="nf.is_secondary_apply" /> 寄售申请</label>
          <label><input type="checkbox" v-model="nf.is_secondary_trust" /> 寄售托管的</label>
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>寄售最低价（元，0 不限）</label>
            <input v-model="nf.secondary_low_price" class="input" type="number" step="0.01" min="0" />
          </div>
          <div>
            <label>寄售最高价（元，0 不限）</label>
            <input v-model="nf.secondary_high_price" class="input" type="number" step="0.01" min="0" />
          </div>
        </div>
        <div class="cell-sub" style="margin-top:8px">iid / 合约地址由系统自动生成。</div>

        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="creating = false">取消</button>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? '创建中…' : '创建' }}</button>
        </div>
      </form>
    </div>

    <!-- 开售：选择发售时间，到点自动上架首页发售区 -->
    <div class="modal-mask fadein" v-if="onsale" @click.self="onsale = null">
      <form class="glass modal pop" style="max-width:420px" @submit.prevent="confirmOnsale">
        <h3>开售 <span class="cell-sub">{{ onsale.name }}</span></h3>
        <div class="form-row">
          <label>发售时间</label>
          <input v-model="saleTime" class="input" type="datetime-local" step="1" />
        </div>
        <div class="cell-sub" style="margin-bottom:12px">到点自动上架至 C 端首页藏品发售区；清空 = 立即开售。</div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="onsale = null">取消</button>
          <button class="btn btn-primary" :disabled="saving">{{ saving ? '处理中…' : '确认开售' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import http, { toast, fmtTime, money, canWrite } from '../api'
import Pager from '../components/Pager.vue'

const q = reactive({ keyword: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const edit = ref(null)
const creating = ref(false)
const saving = ref(false)
const uploading = ref(false)
const form = reactive({})
const meta = reactive({ categories: [], authors: [] })

/* 开售弹窗：onsale 持有目标行，saleTime 为 datetime-local 值（空 = 立即开售） */
const onsale = ref(null)
const saleTime = ref('')

/** 状态三态：未发售 0（灰）/ 已排期 1 且未到点（蓝）/ 发售中 1 且到点（绿） */
function saleState(it) {
  if (Number(it.status) === 1) {
    if (it.start_time && Number(it.start_time) > Date.now()) {
      return { cls: 'tag-blue', text: '已排期', sub: fmtTime(it.start_time) }
    }
    return { cls: 'tag-green', text: '发售中', sub: '' }
  }
  return { cls: 'tag-gray', text: '未发售', sub: '' }
}

function openOnsale(it) {
  onsale.value = it
  // 已排期重开 = 预填当前排期时间；否则默认 1 分钟后
  const base = saleState(it).text === '已排期' && it.start_time ? Number(it.start_time) : Date.now() + 60_000
  const d = new Date(base)
  const p = n => String(n).padStart(2, '0')
  saleTime.value = `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

async function confirmOnsale() {
  saving.value = true
  try {
    await http.post(`/items/${onsale.value.id}/onsale`, { sale_time: (saleTime.value || '').replace('T', ' ') })
    toast('已开售')
    onsale.value = null
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

async function doOffsale(it) {
  if (!window.confirm(`下架后将从 C 端首页收回，确定下架「${it.name}」？`)) return
  try {
    await http.post(`/items/${it.id}/offsale`)
    toast('已下架')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

const blankNew = () => ({
  name: '', price: '', cid: '', author_id: '', issue_quantity: 0,
  image_url: '', content: '',
  is_recommend: false, is_popular: false,
  is_secondary: false, is_secondary_batch: false, is_secondary_apply: false, is_secondary_trust: false,
  secondary_low_price: 0, secondary_high_price: 0,
})
const nf = reactive(blankNew())

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/items', { params: { keyword: q.keyword, pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

async function loadMeta() {
  try {
    const d = await http.get('/items/meta')
    meta.categories = d.categories || []
    meta.authors = d.authors || []
  } catch {
    /* 拦截器已 toast */
  }
}

/** 上传藏品图：target 为 'create' | 'edit'，成功后回填 URL 字段 */
async function uploadFile(e, target) {
  const f = e.target.files[0]
  e.target.value = ''
  if (!f) return
  if (f.size > 5 * 1024 * 1024) {
    toast('图片不能超过 5MB', true)
    return
  }
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('file', f)
    const d = await http.post('/upload/image', fd)
    if (target === 'create') nf.image_url = d.url
    else form.image_url = d.url
    toast('图片已上传')
  } catch {
    /* 拦截器已 toast */
  } finally {
    uploading.value = false
  }
}

// 金额换算：库里与 API 全链路为「分」（与源站一致，C 端 /100 显示元）；
// 后台表单/列表一律以「元」呈现——toFen 元转分提交、toYuan 分转元显示
function toFen(v) {
  return Math.round(Number(v || 0) * 100)
}
function toYuan(v) {
  return Number(v || 0) / 100
}

/** 编辑：先拉详情回填（列表行字段是子集） */
async function openEdit(it) {
  try {
    const d = await http.get(`/items/${it.id}`)
    edit.value = d
    Object.assign(form, {
      name: d.name,
      image_url: d.image_url || '',
      price: toYuan(d.price),
      issue_quantity: d.issue_quantity,
      start_time: d.start_time || '',
      end_time: d.end_time || '',
      status: String(d.status),
      is_recommend: Number(d.is_recommend) === 1,
      is_popular: Number(d.is_popular) === 1,
      is_sold_out: Number(d.is_sold_out) === 1,
      is_secondary: Number(d.is_secondary) === 1,
      is_secondary_batch: Number(d.is_secondary_batch) === 1,
      is_secondary_apply: Number(d.is_secondary_apply) === 1,
      is_secondary_trust: Number(d.is_secondary_trust) === 1,
      secondary_low_price: toYuan(d.secondary_low_price),
      secondary_high_price: toYuan(d.secondary_high_price),
    })
  } catch {
    /* 拦截器已 toast */
  }
}

function openCreate() {
  Object.assign(nf, blankNew())
  creating.value = true
}

async function save() {
  saving.value = true
  try {
    const payload = {
      name: form.name,
      price: toFen(form.price),
      issue_quantity: form.issue_quantity,
      start_time: form.start_time,
      end_time: form.end_time,
      status: Number(form.status),   // 1 在售 / 0 未发售
      is_recommend: form.is_recommend ? 1 : 0,
      is_popular: form.is_popular ? 1 : 0,
      is_sold_out: form.is_sold_out ? 1 : 0,
      is_secondary: form.is_secondary ? 1 : 0,
      is_secondary_batch: form.is_secondary_batch ? 1 : 0,
      is_secondary_apply: form.is_secondary_apply ? 1 : 0,
      is_secondary_trust: form.is_secondary_trust ? 1 : 0,
      secondary_low_price: toFen(form.secondary_low_price),
      secondary_high_price: toFen(form.secondary_high_price),
    }
    // 图片留空时不提交，避免误清空
    if (form.image_url) payload.image_url = form.image_url
    await http.put(`/items/${edit.value.id}`, payload)
    toast('藏品已更新')
    edit.value = null
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

async function create() {
  saving.value = true
  try {
    const d = await http.post('/items', {
      name: nf.name,
      price: toFen(nf.price),
      cid: nf.cid,
      author_id: nf.author_id,
      issue_quantity: Number(nf.issue_quantity || 0),
      image_url: nf.image_url,
      content: nf.content,
      is_recommend: nf.is_recommend ? 1 : 0,
      is_popular: nf.is_popular ? 1 : 0,
      is_secondary: nf.is_secondary ? 1 : 0,
      is_secondary_batch: nf.is_secondary_batch ? 1 : 0,
      is_secondary_apply: nf.is_secondary_apply ? 1 : 0,
      is_secondary_trust: nf.is_secondary_trust ? 1 : 0,
      secondary_low_price: toFen(nf.secondary_low_price),
      secondary_high_price: toFen(nf.secondary_high_price),
    })
    toast(`藏品已创建，iid：${d.iid}`)
    creating.value = false
    load(1)
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  load(1)
  loadMeta()
})
</script>
