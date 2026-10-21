<template>
  <div class="page-body">
    <div class="glass card-pad rise">
      <div class="filters">
        <input v-model.trim="q.keyword" class="input" placeholder="公告标题" @keyup.enter="load(1)" />
        <button class="btn btn-primary" @click="load(1)">查询</button>
        <button class="btn btn-ghost" @click="q.keyword = ''; load(1)">重置</button>
        <div class="spacer"></div>
        <button v-if="canWrite()" class="btn btn-primary" @click="openEdit(null)">＋ 新建公告</button>
      </div>

      <div class="tbl-wrap">
        <table class="tbl">
          <thead>
            <tr><th>公告</th><th>分类</th><th>置顶</th><th>浏览量</th><th>发布时间</th><th>操作</th></tr>
          </thead>
          <tbody>
            <tr v-for="r in list" :key="r.id">
              <td>
                <div style="display:flex;align-items:center;gap:10px">
                  <img v-if="r.coverUrl" :src="r.coverUrl" class="thumb" loading="lazy" @error="e => e.target.style.visibility = 'hidden'" />
                  <div v-else class="thumb" style="display:flex;align-items:center;justify-content:center;color:#9fb4cf">📄</div>
                  <div class="cell-main" style="max-width:340px;overflow:hidden;text-overflow:ellipsis">{{ r.title }}</div>
                </div>
              </td>
              <td><span v-if="r.category" class="tag tag-blue">{{ r.category }}</span><span v-else class="cell-sub">—</span></td>
              <td><span class="tag" :class="r.isTop ? 'tag-orange' : 'tag-gray'">{{ r.isTop ? '置顶' : '普通' }}</span></td>
              <td class="serif-num">{{ r.view_count }}</td>
              <td class="cell-sub">{{ fmtTime(r.published_ts) }}</td>
              <td>
                <div style="display:flex;gap:6px">
                  <button class="btn btn-ghost btn-sm" @click="openPreview(r)">详情</button>
                  <button v-if="canWrite()" class="btn btn-ghost btn-sm" @click="openEdit(r)">编辑</button>
                  <button v-if="canWrite()" class="btn btn-danger btn-sm" @click="del(r)">删除</button>
                  <span v-if="!canWrite()" class="cell-sub">只读</span>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && list.length === 0">
              <td colspan="6"><div class="empty"><div class="glyph">📢</div>暂无公告</div></td>
            </tr>
          </tbody>
        </table>
      </div>
      <Pager :total="total" :page="q.pageNo" :size="q.pageSize" @change="p => load(p)" />
    </div>

    <!-- 新建 / 编辑 -->
    <div class="modal-mask fadein" v-if="edit" @click.self="edit = null">
      <form class="glass modal pop" style="width:680px" @submit.prevent="save">
        <h3>{{ form.id ? '编辑公告' : '新建公告' }}</h3>
        <div class="form-row">
          <label>标题 *</label>
          <input v-model.trim="form.title" class="input" />
        </div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div>
            <label>分类</label>
            <select v-model="form.cid" class="select" style="width:100%">
              <option value="0">未分类</option>
              <option v-for="c in cats" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
          </div>
          <div class="checks" style="align-items:flex-end;padding-bottom:7px">
            <label><input type="checkbox" v-model="form.isTop" /> 置顶展示</label>
          </div>
        </div>
        <div class="form-row">
          <label>封面图（上传或填 URL，可留空）</label>
          <div class="img-field">
            <img v-if="form.cover" :src="form.cover" class="img-preview" style="width:52px;height:52px" @error="e => e.target.style.visibility = 'hidden'" />
            <div style="flex:1;display:flex;flex-direction:column;gap:8px">
              <input ref="coverFile" type="file" accept="image/jpeg,image/png,image/webp" style="display:none" @change="uploadCover" />
              <div>
                <button type="button" class="btn btn-ghost btn-sm" :disabled="uploading" @click="$refs.coverFile.click()">
                  {{ uploading ? '上传中…' : '上传图片' }}
                </button>
              </div>
              <input v-model.trim="form.cover" class="input" placeholder="或直接填图片 URL" />
            </div>
          </div>
        </div>
        <div class="form-row">
          <label>正文内容（富文本）</label>
          <RichEditor v-model="form.content" />
        </div>
        <div class="form-row">
          <label>相关藏品（详情页顶部横滑展示）</label>
          <div style="display:flex;gap:8px">
            <select v-model="pickIid" class="select" style="flex:1">
              <option value="">选择藏品…</option>
              <option v-for="it in allItems" :key="it.iid" :value="it.iid">{{ it.name }}</option>
            </select>
            <button type="button" class="btn btn-ghost btn-sm" :disabled="!pickIid" @click="addItem">添加</button>
          </div>
          <div v-if="form.items.length" class="chip-list">
            <span v-for="(it, i) in form.items" :key="it.iid" class="chip">
              {{ it.name || it.iid }}
              <a @click.prevent="form.items.splice(i, 1)">✕</a>
            </span>
          </div>
        </div>
        <div class="modal-foot">
          <button type="button" class="btn btn-ghost" @click="edit = null">取消</button>
          <button class="btn btn-primary" :disabled="!form.title || saving">{{ saving ? '保存中…' : '保存' }}</button>
        </div>
      </form>
    </div>

    <!-- 详情预览：结构对齐 C 端公告详情页 -->
    <div class="modal-mask fadein" v-if="preview" @click.self="preview = null">
      <div class="glass modal pop" style="width:560px;max-height:80vh;overflow-y:auto;padding:0">
        <div class="np-head">
          <span>公告详情预览</span>
          <button type="button" class="btn btn-ghost btn-sm" @click="preview = null">关闭</button>
        </div>
        <div class="np-page" v-if="preview">
          <div class="np-nav-box">
            <div class="np-name">{{ preview.title }}</div>
            <div class="np-between">
              <div class="np-row">
                <span class="np-tag">{{ catName(preview.cid) }}</span>
                <span class="np-time">{{ fmtTime(preview.published_ts) }}</span>
              </div>
              <span class="np-read">阅读 {{ preview.view_count }}</span>
            </div>
          </div>

          <div v-if="preview.items && preview.items.length" class="np-good-list">
            <span class="np-good-title">相关藏品</span>
            <div class="np-scroll">
              <div class="np-scroll-inner">
                <div v-for="(g, i) in preview.items" :key="i" class="np-good">
                  <img class="np-good-img" :src="g.imageUrl" alt="" />
                  <span class="np-good-name">{{ g.name }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="np-vcont">
            <div class="np-badge-wrap">
              <div class="np-badge">{{ catName(preview.cid) }}</div>
            </div>
            <!-- 与 C 端一致：v-html 渲染 + img 强制 100% 宽 -->
            <div class="np-rich" v-html="previewContent"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import http, { toast, fmtTime, canWrite } from '../api'
import Pager from '../components/Pager.vue'
import RichEditor from '../components/RichEditor.vue'

const q = reactive({ keyword: '', pageNo: 1, pageSize: 20 })
const list = ref([])
const total = ref(0)
const loading = ref(true)
const cats = ref([])
const allItems = ref([])
const edit = ref(null)
const preview = ref(null)
const saving = ref(false)
const uploading = ref(false)
const pickIid = ref('')
const form = reactive({ items: [] })

async function load(page = q.pageNo) {
  q.pageNo = page
  loading.value = true
  try {
    const data = await http.get('/cms/notices', { params: { keyword: q.keyword, pageNo: q.pageNo, pageSize: q.pageSize } })
    list.value = data.list || []
    total.value = Number(data.total || 0)
  } finally {
    loading.value = false
  }
}

async function loadCats() {
  try {
    const data = await http.get('/cms/notice-categories')
    cats.value = data.list || []
  } catch {
    /* 忽略 */
  }
}

async function loadItems() {
  try {
    const data = await http.get('/items', { params: { pageNo: 1, pageSize: 100 } })
    allItems.value = data.list || []
  } catch {
    /* 忽略 */
  }
}

function catName(cid) {
  return String(cats.value.find(c => String(c.id) === String(cid))?.name || '')
}

// C 端详情页对正文所有 img 注入 width:100%，预览保持同一处理
const previewContent = computed(
  () => (preview.value?.content || '').replace(/<img/g, "<img width='100%'")
)

/** 编辑：先拉详情回填（content / 关联藏品），列表行不含这些字段 */
async function openEdit(row) {
  try {
    let d = { title: '', cid: '0', cover: '', content: '', isTop: false, items: [] }
    if (row) d = await http.get(`/cms/notices/${row.id}`)
    edit.value = true
    Object.assign(form, {
      id: row ? d.id : 0,
      title: d.title || '',
      cid: String(d.cid || '0'),
      cover: d.coverUrl || d.cover || '',
      content: d.content || '',
      isTop: !!d.isTop,
      items: (d.items || []).map(it => ({ iid: String(it.iid), name: it.name || '', imageUrl: it.imageUrl || '' })),
    })
  } catch {
    /* 拦截器已 toast */
  }
}

async function uploadCover(e) {
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
    form.cover = d.url
    toast('图片已上传')
  } catch {
    /* 拦截器已 toast */
  } finally {
    uploading.value = false
  }
}

function addItem() {
  const it = allItems.value.find(x => String(x.iid) === String(pickIid.value))
  if (!it) return
  if (form.items.some(x => String(x.iid) === String(pickIid.value))) {
    toast('该藏品已在列表中', true)
    return
  }
  form.items.push({ iid: String(it.iid), name: it.name, imageUrl: it.imageUrl })
  pickIid.value = ''
}

/** 详情预览：拉完整数据（含正文与关联藏品） */
async function openPreview(r) {
  try {
    preview.value = await http.get(`/cms/notices/${r.id}`)
  } catch {
    /* 拦截器已 toast */
  }
}

async function save() {
  saving.value = true
  try {
    const payload = {
      title: form.title,
      cid: Number(form.cid) || 0,
      cover: form.cover,
      isTop: !!form.isTop,
      content: form.content,
      items: form.items.map(x => x.iid),
    }
    if (form.id) {
      await http.put(`/cms/notices/${form.id}`, payload)
    } else {
      await http.post('/cms/notices', payload)
    }
    toast(form.id ? '公告已更新' : '公告已发布')
    edit.value = null
    load()
  } catch {
    /* 拦截器已 toast */
  } finally {
    saving.value = false
  }
}

async function del(r) {
  if (!confirm(`确认删除公告「${r.title}」？该操作进入操作日志。`)) return
  try {
    await http.delete(`/cms/notices/${r.id}`)
    toast('公告已删除')
    load()
  } catch {
    /* 拦截器已 toast */
  }
}

onMounted(() => {
  load(1)
  loadCats()
  loadItems()
})
</script>

<style scoped>
/* ---------- 编辑弹窗：已选藏品 tag ---------- */
.chip-list { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 10px; border-radius: 99px; font-size: 12px;
  background: rgba(96, 148, 226, 0.14); color: #27466e;
}
.chip a { cursor: pointer; color: #7a92b4; }
.chip a:hover { color: #d05a5a; }

/* ---------- 详情预览：数值对齐 C 端 NoticeInfo ---------- */
.np-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 18px; border-bottom: 1px solid rgba(14, 24, 56, 0.08);
}
.np-page { padding: 0 0 16px; }
.np-nav-box { background: #fff; padding: 16px 18px; }
.np-name { font-size: 17px; font-weight: 700; color: #000; margin-bottom: 8px; word-break: break-all; }
.np-between { display: flex; align-items: center; justify-content: space-between; }
.np-row { display: flex; align-items: center; }
.np-tag { color: #3578fc; font-size: 13px; font-weight: 500; margin-right: 8px; }
.np-time { color: #999; font-size: 13px; }
.np-read { color: #999; font-size: 11.5px; }

.np-good-list { margin-top: 12px; }
.np-good-title { display: block; margin-left: 14px; font-size: 13px; font-weight: 500; color: #000; }
.np-scroll {
  margin-top: 8px; width: 100%; height: 112px; white-space: nowrap;
  overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
}
.np-scroll::-webkit-scrollbar { display: none; }
.np-scroll-inner { display: inline-flex; }
.np-good {
  display: inline-flex; flex-direction: column; align-items: center;
  width: 86px; height: 112px; margin-right: 8px; box-sizing: border-box;
}
.np-good-img { width: 86px; height: 86px; border-radius: 8px; background: #f4f5f9; object-fit: cover; display: block; }
.np-good-name {
  margin-top: 4px; width: 86px; font-size: 12.5px; color: #333;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center;
}

.np-vcont {
  margin: 14px 14px 0; box-shadow: #e2eaff 0 0 45px inset;
  padding: 18px 16px;
}
.np-badge-wrap { display: flex; justify-content: center; margin-bottom: 14px; }
.np-badge {
  background-color: rgb(230, 215, 249); box-shadow: rgb(200, 215, 255) 50px 0 43px inset;
  padding: 6px; border-bottom: 5px solid rgb(226, 234, 255);
  font-size: 17px; font-weight: bold; color: #fff; width: fit-content;
}
.np-rich { font-size: 14px; line-height: 1.8; color: #1c2b45; word-break: break-word; }
.np-rich :deep(p) { margin: 0 0 8px; }
.np-rich :deep(img) { max-width: 100%; border-radius: 8px; }
.np-rich :deep(h1), .np-rich :deep(h2), .np-rich :deep(h3) { margin: 12px 0 8px; }
</style>
