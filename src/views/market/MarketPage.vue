<template>
  <div class="market-page" :style="{ '--market-bg': `url(${marketBg})` }">
    <!-- ============ 顶部：搜索 + 榜单 ============ -->
    <div class="topWrap">
      <div class="searchRow">
        <div class="searchBox flex-start">
          <img class="searchIcon" :src="ICON.search" alt="" />
          <input
            v-model="query.q"
            class="searchInput"
            type="text"
            placeholder="搜索关键词.."
            placeholder-class="placeholderText"
            @input="changeQ"
            @confirm="handleSearchs"
          />
        </div>
        <div class="flex-center kanbanBtn" @click="goKanBan(1)">
          <img class="kanbanIcon" :src="ICON.kanban" alt="" />
        </div>
      </div>

      <!-- 板块（分类）横滑 -->
      <div class="scrollView">
        <div class="scr-box">
          <div
            v-for="(item, e) in hotList"
            :id="'scr' + e"
            :key="'h' + e"
            class="hotItem"
            @click="goHotArea(item, e)"
          >
            <div style="height: 0.5rem" />
            <div v-if="showHotIndex === e" style="width: 9.25rem; height: 4.25rem">
              <img :src="ICON.boxBg" alt="" style="width: 100%; height: 100%" />
            </div>
            <div v-else style="width: 4.75rem; height: 4.25rem">
              <img :src="ICON.hotCover" alt="" style="width: 4.75rem; height: 4.25rem" />
            </div>
            <div class="hotMask">
              <template v-if="showHotIndex === e">
                <div style="padding-top: 0.625rem">
                  <img :src="ICON.tag" alt="" style="width: 2.6875rem" />
                  <div class="text-ellipsis" style="color: #333333; font-size: 0.9375rem; font-weight: 600; text-align: center">
                    {{ item.name }}
                  </div>
                </div>
              </template>
              <template v-else>
                <div class="text-ellipsis" style="color: #98a6bf; font-size: 0.75rem; padding: 0.625rem 0.25rem 0; box-sizing: border-box; font-weight: 600; text-align: center">
                  {{ item.name }}
                </div>
                <!-- 原站此处是一个无内容的占位容器（innerHTML 为空），必须保持为空 -->
                <div style="color: #666666; font-size: 0.9375rem; margin-top: 0.8125rem; text-align: center" />
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ============ 内容区 ============ -->
    <div class="containerStyle contentWrap">
      <div class="content">
        <div class="headBox column">
          <!-- 筛选行 -->
          <div class="searchFilterBox centerBetween">
            <div class="searchFilter center">
              <div class="flex-start" @click="handleFilter">
                <span style="color: #333333; font-size: 0.75rem; margin-right: 0.25rem">筛选</span>
                <img :src="ICON.filter" alt="" style="width: 1rem" />
              </div>

              <div class="filterItem center" style="margin-left: 2.125rem">
                <div class="center" style="font-size: 0.75rem" @click="showCollectBind = !showCollectBind">
                  {{ typeId === 0 ? '藏品' : '盲盒' }}
                </div>
                <div v-if="showCollectBind" class="sortMask" @click="showCollectBind = false" />
                <div
                  v-if="showCollectBind"
                  class="sortListModel columnCenter"
                  style="width: 4.0625rem; right: -0.625rem; top: -0.5rem"
                >
                  <div class="sortList" style="padding: 0.875rem 0.75rem 0; width: 100%">
                    <div
                      v-for="t in typeList"
                      :key="t.id"
                      class="sortItem centerBetween"
                      :class="t.id !== typeId ? 'font-333-28-500' : 'font-bule-28-bold2'"
                      @click="changeType(t.id)"
                    >
                      <span class="name">{{ t.name }}</span>
                      <img v-if="t.id === typeId" class="choose" :src="ICON.choose" alt="" style="width: 0.625rem; height: 0.625rem" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex-start" style="margin-left: 2.125rem" @click="goKanBan(2)">
                <span style="color: #333333; font-size: 0.75rem; margin-right: 0.1875rem">数据看板</span>
                <img :src="ICON.kanbanS" alt="" style="width: 1rem" />
              </div>

              <div v-if="typeId === 0" class="flex-start">
                <div class="ismarketbox filterItem center" style="font-size: 0.75rem; margin-right: 1.5rem" @click="handleIsMarkrt">
                  <img class="icon" :src="hasMarkrt ? ICON.checkOn : ICON.checkOff" alt="" style="width: 0.875rem; height: 0.875rem" />
                  <span> 未退市 </span>
                </div>
                <img
                  v-if="listType"
                  :src="ICON.viewCard"
                  alt=""
                  style="width: 1rem"
                  @click="listType = false"
                />
                <img
                  v-else
                  :src="ICON.viewList"
                  alt=""
                  style="width: 1rem"
                  @click="listType = true"
                />
              </div>
            </div>
          </div>

          <!-- 分类 Tab -->
          <div class="cateTabScroll">
            <div class="cateTabBox">
              <div class="cateTab" :class="{ active: tabKey === 'follow' }" @click="selectTab('follow')"> 关注 </div>
              <div class="cateTab" :class="{ active: tabKey === 'all' }" @click="selectTab('all')"> 全部 </div>
              <div
                v-for="(t, e) in categoryList"
                :key="'ct' + e"
                class="cateTab"
                :class="{ active: tabKey === 'cate' && showHotIndex === e }"
                @click="selectTab('cate', t, e)"
              >
                {{ t.name }}
              </div>
            </div>
          </div>
        </div>

        <!-- 列表视图 -->
        <template v-if="listType">
          <div class="listWrap">
            <div class="listRow" style="margin-top: 0.65625rem; align-items: stretch; display: flex">
              <div class="rowLeft">
                <div class="colHead">
                  <div class="colLabel"> 藏品 </div>
                </div>
                <div
                  v-for="t in markrtList"
                  :key="t.id"
                  class="flex-start lrow"
                  @click.stop="marketListClick(t)"
                >
                  <div style="width: 2.5rem; height: 2.5rem">
                    <img :src="formatImgUrl(t.image)" alt="" style="width: 2.5rem; height: 2.5rem" />
                  </div>
                  <div style="margin-left: 0.25rem; width: 5.4375rem; height: 2.5rem">
                    <div class="text-ellipsis" style="width: 5.4375rem; color: #333333; font-size: 0.75rem; font-weight: 500">
                      {{ t.subject }}
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5625rem">
                      <img v-if="t.isOut" :src="ICON.badgeA" class="badgeImg" alt="" />
                      <img v-else-if="t.isWillOut" :src="ICON.badgeB" class="badgeImg" alt="" />
                      <div v-else style="width: 2.6875rem" />
                      <img
                        :src="t.is_follow ? ICON.starOn : ICON.starOff"
                        alt=""
                        style="width: 1rem; height: 1rem"
                        @click.stop="handleLike(t)"
                      />
                    </div>
                  </div>
                </div>
              </div>

              <div class="rowRight">
                <div class="flex-start" style="width: 20rem">
                  <div
                    v-for="(s, e) in sortListNew"
                    :key="e"
                    class="flex-center sortCol"
                    @click.stop="choseSort(s, e)"
                  >
                    <div class="colLabel">{{ s.name }}</div>
                    <img v-if="s.checked && s.sortUp" :src="ICON.arrowUp" class="sortArrow" alt="" />
                    <img v-else-if="s.checked && s.sortDown" :src="ICON.arrowDown" class="sortArrow" alt="" />
                    <img v-else-if="!s.checked" :src="ICON.arrowNone" class="sortArrow" alt="" />
                  </div>
                </div>
                <div
                  v-for="t in markrtList"
                  :key="t.id"
                  class="flex-start rrow"
                  @click.stop="marketListClick(t)"
                >
                  <div class="statCol">
                    <div><span class="statLbl">发行 </span><span class="statVal">{{ t.quantity }}</span></div>
                    <div><span class="statLbl">流通</span><span class="statVal">{{ t.soldQty }}</span></div>
                    <div><span class="statLbl">成交</span><span class="statVal">{{ t.sellCount }}</span></div>
                  </div>
                  <div class="priceCol">
                    <span class="priceSign">￥</span><span class="priceVal">{{ t.amount }}</span>
                  </div>
                  <div class="rateCol flex-start">
                    <img v-if="t.fluctuation >= 0" :src="ICON.red" class="rateArrow" alt="" />
                    <img v-else-if="t.fluctuation && t.fluctuation < 0" :src="ICON.green" class="rateArrow" alt="" />
                    <span class="rateVal" :style="{ color: flucColor(t.fluctuation) }">
                      {{ t.fluctuation > 0 ? '+' + (100 * t.fluctuation).toFixed(2) + '%' : (100 * t.fluctuation).toFixed(2) + '%' }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div v-if="markrtList.length > 0" class="load-txt">{{ loadTxt }}</div>
            <div v-else class="emptyBox">
              <UvEmpty mode="list" text="暂无藏品" />
            </div>
          </div>
        </template>

        <!-- 卡片视图 -->
        <div v-else class="cardWrap">
          <div
            v-for="t in markrtList"
            :key="t.id"
            class="cardItem"
            @click="marketListClick(t)"
          >
            <img class="cardCover" :src="formatImgUrl(t.image)" alt="" />
            <div class="cardInfo">
              <div class="text-ellipsis cardName">{{ t.subject }}</div>
              <div class="cardPrice">￥{{ t.amount }}</div>
            </div>
          </div>
          <div v-if="markrtList.length === 0" class="emptyBox">
            <UvEmpty mode="list" text="暂无藏品" />
          </div>
        </div>
      </div>
    </div>

    <!-- ============ 筛选抽屉 ============ -->
    <div v-if="showDrawer" class="drawer-mask" @click="hideDrawer" />
    <div
      v-if="showDrawer"
      class="drawer-panel open"
      :style="{ top: statusBarHeight + 'px', height: 'calc(100% - ' + statusBarHeight + 'px)' }"
    >
      <div class="drawer-head centerBetween">
        <div class="drawer-back" @click="hideDrawer">‹</div>
        <div class="drawer-title">筛选</div>
        <div class="drawer-clear" @click="allcClear">清空</div>
      </div>
      <div class="drawer-body">
        <div class="menuBlock">
          <div class="titleType center">
            <img :src="ICON.typeIcon" class="typeIcon" alt="" />
            <span>板块</span>
          </div>
          <div class="grid3">
            <div
              v-for="(t, e) in categoryList"
              :key="e"
              class="allBtn"
              :class="{ on: drawerCid === t.cid }"
              @click="drawerSelectCategory(t, e)"
            >
              {{ t.name }}
            </div>
          </div>
        </div>
        <div v-for="(g, e) in drawerGroups" :key="'g' + e" class="menuBlock">
          <div class="titleType center"><span>{{ g.title }}</span></div>
          <div class="grid3">
            <div
              v-for="(t, s) in g.items"
              :key="s"
              class="allBtn"
              :class="{ on: drawerSel[e] === t }"
              @click="drawerSelectSort(e, t)"
            >
              {{ t }}
            </div>
          </div>
        </div>
        <div style="height: 1.25rem" />
      </div>
    </div>

    <div class="nb-tabbar-space" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import UvEmpty from '@/components/UvEmpty.vue'
import { itemApi, nftApi } from '@/api'
import { baseUrl } from '@/utils/path'
import { storage, TOKEN_KEY } from '@/utils/storage'

import searchIcon from '@/assets/market/search.png'
import kanbanImg from '@/assets/market/kanban.png'
import typeIcon from '@/assets/market/typeIcon.png'
import redIcon from '@/assets/market/red.png'
import greenIcon from '@/assets/market/green.png'
import tagIcon from '@/assets/market/tag.png'
import boxBg from '@/assets/market/boxBg.png'
import hotCover from '@/assets/market/hot-cover.png'
import kanbanS from '@/assets/market/kanban-s.png'
import filterIcon from '@/assets/market/filter.png'
import chooseIcon from '@/assets/market/choose.png'
import checkOn from '@/assets/market/check-on.png'
import checkOff from '@/assets/market/check-off.png'
import viewCard from '@/assets/market/view-card.png'
import viewList from '@/assets/market/view-list.png'
import badgeA from '@/assets/market/badge-a.png'
import badgeB from '@/assets/market/badge-b.png'
import starOff from '@/assets/market/star-off.png'
import starOn from '@/assets/market/star-on.png'
import arrowUp from '@/assets/market/arrow-up.png'
import arrowDown from '@/assets/market/arrow-down.png'
import arrowNone from '@/assets/market/arrow-none.png'
import marketBg from '@/assets/market/bg.png'

defineOptions({ name: 'MarketPage' })

const router = useRouter()

const ICON = {
  search: searchIcon,
  kanban: kanbanImg,
  typeIcon,
  red: redIcon,
  green: greenIcon,
  tag: tagIcon,
  boxBg,
  hotCover,
  kanbanS,
  filter: filterIcon,
  choose: chooseIcon,
  checkOn,
  checkOff,
  viewCard,
  viewList,
  badgeA,
  badgeB,
  starOff,
  starOn,
  arrowUp,
  arrowDown,
  arrowNone,
}

/* ---------- 状态（严格对齐原站 data） ---------- */
const activeTab = ref(0)
const categoryList = ref([])
const activeCategory = ref(0)
const finished = ref(false)
const list = ref([])
const query = reactive({ q: '', cid: '', pageNo: 1 })
const followFinished = ref(false)
const followList = ref([])
const followQuery = reactive({ pageNo: 1 })

const showHotIndex = ref(-1)
const typeId = ref(0)
const typeList = [
  { id: 0, name: '藏品' },
  { id: 1, name: '盲盒' },
]
const showCollectBind = ref(false)
const hasMarkrt = ref(false)
const listType = ref(true)
const guanzhuKey = ref(false)
const tabKey = ref('all')
const sortListNew = ref(
  JSON.parse(
    JSON.stringify([
      { name: '成交量', value: 'sellCount', checked: false, sortUp: false, sortDown: false },
      { name: '地板价', value: 'amount', checked: false, sortUp: false, sortDown: false },
      { name: '涨跌幅', value: 'fluctuation', checked: false, sortUp: false, sortDown: false },
    ])
  )
)
const loadTxt = ref('上拉加载更多')
const showDrawer = ref(false)
const statusBarHeight = ref(0)
const drawerGroups = [
  { title: '系列', items: ['全部', '首发', '限量', '联名'] },
  { title: '价格', items: ['全部', '0-500', '500-1000', '1000以上'] },
]
const drawerSel = reactive({})
const drawerCid = ref('')

/* ---------- 计算属性（严格对齐原站 computed） ---------- */
const hotList = computed(() =>
  (categoryList.value || []).map((t, e) => {
    const s = Math.abs(Number(t.cid) || e + 1) || e + 1
    const a = s % 2500
    const l = (s % 100) / 100
    const index = toThousands((500 + a + l).toFixed(2))
    const r = (s % 50 - 20) / 10
    const upPer = (r >= 0 ? '+' : '') + r.toFixed(2) + '%'
    const c = (r >= 0 ? '+' : '') + (20 * r).toFixed(2)
    return { _src: t, cid: t.cid, name: t.name, index, upPer, upVal: c }
  })
)

const markrtList = computed(() => {
  let t = ((activeTab.value === 0 ? list.value : followList.value) || []).map((t) => {
    const e = t.iid
    const s = Math.abs(Number(e) || 0)
    const a = Number(t.sellQuantity) === 0
    return {
      _src: t,
      id: e,
      iid: e,
      subject: t.name,
      image: t.imageUrl,
      quantity: t.issueQuantity,
      soldQty: t.nftQuantity,
      sellCount: t.tradeQuantity || 0,
      amount: a ? fmtPrice(t.secondaryHighPrice) : fmtPrice(t.minPrice),
      fluctuation: t.priceChange,
      isOut: a,
      isWillOut: !a && s % 5 === 1,
      is_follow: !!t.isFollow,
      followCount: mockFollowCount(t),
    }
  })
  if (hasMarkrt.value) t = t.filter((t) => !t.isOut)
  const e = sortListNew.value.filter((t) => t.checked).pop()
  if (e) {
    const s = e.sortUp ? 1 : -1
    t = t
      .slice()
      .sort(
        (t, a) =>
          ((parseFloat(String(t[e.value]).replace(/[^\d.-]/g, '')) || 0) -
            (parseFloat(String(a[e.value]).replace(/[^\d.-]/g, '')) || 0)) *
          s
      )
  }
  return t
})

/* ---------- 方法（严格对齐原站 methods） ---------- */
function toThousands(t) {
  const e = String(t).split('.')
  e[0] = e[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  return e.join('.')
}
function fmtPrice(t) {
  return (Number(t || 0) / 100).toFixed(2)
}
function mockFollowCount(t) {
  return Math.abs(Number(t.iid) || 0) % 5e3 + 300
}
function flucColor(t) {
  return t >= 0 ? '#EB2A3B' : t < 0 ? '#0E9976' : '#3578FC'
}
function formatImgUrl(t) {
  return baseUrl(t) || baseUrl('/static/images/logo.jpg')
}

function changeQ() {
  finished.value = false
  query.pageNo = 1
  getGroupList()
}
function handleSearchs() {
  changeQ()
}

function getCategoryList() {
  /* 注意：cid 依赖分类接口。一旦该接口慢/失败/被拦截，cid 会一直是空串，
     列表接口就会返回空数组，页面表现为「暂无藏品」——
     在 iOS WebView 打开体积较大的单文件时最容易命中（请求排队超时）。
     这里加三道兜底：失败重试、重试仍失败则用硬编码默认分类、任何情况下都拉一次列表。 */
  const FALLBACK_CID = '2103038591555887106' // 臻海板块 cid（源站线上首个分类）
  const fallback = () => {
    if (!categoryList.value.length) {
      categoryList.value = [{ cid: FALLBACK_CID, name: '臻海' }]
    }
    applyDefaultCategory()
  }
  itemApi
    .category()
    .then((t) => {
      if (t && t.status && (t.data || []).length) {
        categoryList.value = t.data || []
        applyDefaultCategory()
      } else {
        fallback()
      }
    })
    .catch(() => fallback())
}
function applyDefaultCategory() {
  const t = categoryList.value[0]
  finished.value = false
  query.pageNo = 1
  if (t) {
    tabKey.value = 'cate'
    guanzhuKey.value = false
    activeTab.value = 0
    showHotIndex.value = 0
    activeCategory.value = 0
    drawerCid.value = t.cid
    query.cid = t.cid
  } else {
    tabKey.value = 'all'
    showHotIndex.value = -1
    query.cid = ''
  }
  getGroupList(false, false)
}
function changeCategory(t, e) {
  activeCategory.value = e
  finished.value = false
  query.pageNo = 1
  query.cid = t.cid
  getGroupList()
}
function getGroupList(t, e) {
  const run = () =>
    nftApi.secondary(query).then(
      (s) => {
        if (s && s.status) {
          const d = s.data || []
          list.value = e ? [...list.value, ...d] : d
          if (d.length === 0) finished.value = true
          return true
        }
        return false
      },
      () => false
    )
  /* 首屏列表请求失败时重试一次；否则 finished 被置位后页面永久停在「暂无藏品」，
     用户只能下拉刷新才恢复。原站无此重试，但原站是同源部署不受 CORS 影响。
     注意：网络异常走 onRejected，必须显式返回 false，
     否则 ok 为 undefined，`ok === false` 判断不成立，重试永不触发。 */
  run().then((ok) => {
    if (ok === false) setTimeout(() => run(), 600)
  })
}
function getFollowList(t, e) {
  nftApi.secondaryFollow(followQuery).then((s) => {
    if (s.status) {
      const d = s.data || []
      followList.value = e ? [...followList.value, ...d] : d
      if (d.length === 0) followFinished.value = true
    }
  })
}
function showItemDetails(t) {
  router.push(`/pages/market/info?iid=${t}`)
}
function switchTab(t) {
  if (activeTab.value !== t) {
    activeTab.value = t
    t === 0 ? getGroupList(false, false) : getFollowList(false, false)
  }
}
function toggleFavorite(t) {
  if (!storage.get(TOKEN_KEY)) {
    showToast('请先登录')
    return
  }
  const req = t.isFollow ? itemApi.unfollow(t.iid) : itemApi.follow(t.iid)
  req.then((e) => {
    if (e.status) t.isFollow = !t.isFollow
    else if (e.code === 11111) showToast('请先登录')
  })
}
function goKanBan(t) {
  showToast(t === 1 ? '榜单功能即将上线' : '数据看板暂未开放')
}
function goHotArea(t, e) {
  tabKey.value = 'cate'
  guanzhuKey.value = false
  activeTab.value = 0
  showHotIndex.value = e
  if (t._src && activeCategory.value !== e) changeCategory(t._src, e)
}
function handleFilter() {
  showDrawer.value = true
}
function hideDrawer() {
  showDrawer.value = false
}
function allcClear() {
  Object.keys(drawerSel).forEach((k) => delete drawerSel[k])
  drawerCid.value = ''
  showToast('已清空筛选')
}
function drawerSelectCategory(t, e) {
  drawerCid.value = t.cid
  tabKey.value = 'cate'
  guanzhuKey.value = false
  activeTab.value = 0
  showHotIndex.value = e
  changeCategory(t, e)
}
function drawerSelectSort(t, e) {
  drawerSel[t] = e
}
function changeType(t) {
  typeId.value = t
  showCollectBind.value = false
}
function handleIsMarkrt() {
  hasMarkrt.value = !hasMarkrt.value
}
function selectTab(t, e, s) {
  if (t === 'follow') {
    if (tabKey.value === 'follow') return
    tabKey.value = 'follow'
    guanzhuKey.value = true
    switchTab(1)
    return
  }
  if (guanzhuKey.value) {
    guanzhuKey.value = false
    activeTab.value = 0
  }
  if (t === 'all') {
    if (tabKey.value === 'all') return
    tabKey.value = 'all'
    showHotIndex.value = -1
    finished.value = false
    query.pageNo = 1
    query.cid = ''
    getGroupList(false, false)
  } else {
    tabKey.value = 'cate'
    showHotIndex.value = s
    changeCategory(e, s)
  }
}
function handleLike(t) {
  toggleFavorite(t._src)
}
function choseSort(t, e) {
  const s = sortListNew.value[e]
  if (s.checked) {
    if (s.sortUp) {
      s.sortUp = false
      s.sortDown = true
    } else {
      s.checked = false
      s.sortUp = false
      s.sortDown = false
    }
  } else {
    sortListNew.value.forEach((t) => {
      t.checked = false
      t.sortUp = false
      t.sortDown = false
    })
    s.checked = true
    s.sortUp = true
  }
}
function marketListClick(t) {
  showItemDetails(t.iid || t.id)
}

onMounted(() => {
  getCategoryList()
})
</script>

<style scoped>
/* ===== 类名与数值 100% 取自原站 assets/index-CivP_c7x.css (data-v-cddde5b8) ===== */
.market-page {
  width: 23.4375rem;
  min-height: 100vh;
  padding-bottom: 1.25rem;
  background: #f3f6f8 no-repeat top;
  background-size: 100%;
  background-image: var(--market-bg);
}
.containerStyle {
  width: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.contentWrap {
  margin-top: 0.375rem;
}
.centerBetween {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.flex-start {
  display: flex;
  justify-content: flex-start;
  align-items: center;
}
.flex-center {
  display: flex;
  justify-content: center;
  align-items: center;
}
.columnCenter {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}
.column {
  display: flex;
  flex-direction: column;
}
.center {
  display: flex;
  justify-content: center;
  align-items: center;
}
.text-ellipsis {
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.placeholderText {
  font-size: 0.75rem;
  color: #9b9b9b;
}
.font-bule-28-bold2 {
  color: #3578fc;
}
.font-333-28-500 {
  color: #333;
}
.cateTabScroll {
  width: 23.4375rem;
  white-space: nowrap;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.cateTabScroll::-webkit-scrollbar {
  display: none;
  height: 0;
}
.cateTabBox {
  display: inline-flex;
  align-items: center;
  padding: 0 0.75rem;
}
.cateTab {
  display: inline-block;
  flex-shrink: 0;
  min-width: 3.4375rem;
  height: 1.5rem;
  line-height: 1.5rem;
  text-align: center;
  padding: 0 0.9375rem;
  margin-right: 0.625rem;
  box-sizing: border-box;
  border-radius: 0.375rem;
  background: #e8eef7;
  color: #333;
  font-size: 0.6875rem;
  white-space: nowrap;
  transition: all 0.2s ease;
}
.cateTab.active {
  background: rgba(53, 120, 252, 0.12);
  color: #3578fc;
  font-weight: 600;
}
.topWrap {
  padding-top: 3.125rem;
}
.searchRow {
  width: 21.9375rem;
  height: 2.25rem;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 0.5rem;
}
.searchBox {
  width: 18.125rem;
  height: 2rem;
  padding: 0.375rem 0.625rem;
  box-sizing: border-box;
  border-radius: 1rem;
  background-color: #fff;
}
.searchIcon {
  width: 1.25rem;
  height: 1.25rem;
}
.searchInput {
  width: 80%;
  margin-left: 0.375rem;
  font-size: 0.75rem;
  border: none;
  outline: none;
  background: transparent;
}
.kanbanBtn {
  width: 2.8125rem;
  margin-left: 1rem;
}
.kanbanIcon {
  width: 2.8125rem;
}
.scrollView {
  width: 21.9375rem;
  height: 4.75rem;
  margin: 0.75rem auto 0;
  white-space: nowrap;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.scrollView::-webkit-scrollbar {
  display: none;
  height: 0;
}
.scr-box {
  width: 21.9375rem;
  white-space: nowrap;
}
.hotItem {
  position: relative;
  margin-right: 0.5rem;
  display: inline-block;
  height: 4.75rem;
  vertical-align: top;
}
.hotMask {
  position: absolute;
  width: 100%;
  height: 100%;
  left: 0;
  top: 0.5rem;
  box-sizing: border-box;
}
.content {
  min-height: 100vh;
  width: 23.4375rem;
}
.headBox {
  width: 23.4375rem;
  position: sticky;
  top: 0;
  z-index: 99;
  background: #f3f6f8;
  border-radius: 0.75rem 0.75rem 0 0;
  padding: 0.25rem 0;
}
.searchFilterBox {
  padding: 0 0.75rem;
}
.searchFilter {
  height: 2.125rem;
  border-radius: 0.5625rem;
  padding: 0.5rem 0.625rem;
  box-sizing: border-box;
  background: transparent;
}
.filterItem {
  position: relative;
  font-size: 0.8125rem;
  color: #4c4d51;
}
.ismarketbox {
  margin-left: 0.25rem;
  width: 4.125rem;
  height: 1.625rem;
  border-radius: 0.5625rem;
}
.sortMask {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  z-index: 100;
}
.sortListModel {
  padding: 0.9375rem;
  position: absolute;
  z-index: 101;
}
.sortList {
  position: absolute;
  top: 0.625rem;
  padding: 0.9375rem;
  width: 7.5rem;
  background: #fff;
  border-radius: 0.625rem;
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
}
.sortItem {
  margin-bottom: 1.25rem;
}
.sortItem:last-child {
  margin-bottom: 0;
}
.sortItem .name {
  font-size: 0.8125rem;
}
.listWrap {
  padding: 0 0.75rem;
  box-sizing: border-box;
  background: linear-gradient(180deg, #f3f6f8, #fff 8%);
  min-height: 70vh;
}
.rowLeft {
  width: 8.9375rem;
  padding-right: 0.5rem;
  box-sizing: border-box;
}
.colHead {
  display: flex;
  justify-content: space-between;
}
.colLabel {
  color: rgba(128, 128, 143, 0.7);
  font-size: 0.6875rem;
}
.lrow {
  box-sizing: border-box;
  height: 4.6875rem;
  padding: 0.75rem 0;
}
.badgeImg {
  width: 2.6875rem;
}
.rowRight {
  box-sizing: border-box;
  width: 14rem;
  overflow-x: auto;
}
.sortCol {
  width: 4.0625rem;
  margin-right: 0.75rem;
}
.sortArrow {
  width: 0.4375rem;
  height: 0.5rem;
  margin-left: 0.125rem;
}
.rrow {
  box-sizing: border-box;
  height: 4.6875rem;
  width: 20rem;
}
.statCol {
  margin-left: 0.75rem;
  width: 3.75rem;
}
.statLbl {
  color: #999;
  font-size: 0.5625rem;
}
.statVal {
  color: #333;
  font-size: 0.5625rem;
  font-weight: 600;
  padding-left: 0.25rem;
}
.priceCol {
  font-size: 0.8125rem;
  color: #222;
  line-height: 1.3125rem;
  width: 3.75rem;
  height: 1.3125rem;
  margin-right: 0.75rem;
  text-align: center;
}
.priceSign {
  color: #333;
  font-size: 0.625rem;
}
.priceVal {
  font-size: 1rem;
  font-weight: 700;
}
.rateCol {
  width: 4.375rem;
  margin-left: 0.375rem;
  justify-content: center;
}
.rateArrow {
  width: 1rem;
}
.rateVal {
  font-size: 1rem;
  font-weight: 700;
}
.load-txt {
  text-align: center;
  font-size: 0.8125rem;
  color: #999;
  padding: 0.75rem 0;
}
.emptyBox {
  margin-top: 9.375rem;
}
.cardWrap {
  padding: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}
.cardItem {
  width: 10.625rem;
  background: #fff;
  border-radius: 0.5rem;
  overflow: hidden;
  margin-bottom: 0.625rem;
}
.cardCover {
  width: 10.625rem;
  height: 10.625rem;
  object-fit: cover;
}
.cardInfo {
  padding: 0.5rem;
}
.cardName {
  color: #333;
  font-size: 0.8125rem;
  font-weight: 500;
}
.cardPrice {
  margin-top: 0.25rem;
  color: #eb2a3b;
  font-size: 0.9375rem;
  font-weight: 700;
}
.drawer-mask {
  position: fixed;
  left: 0;
  right: 0;
  top: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 200;
}
.drawer-panel {
  position: fixed;
  right: 0;
  bottom: 0;
  width: 18.125rem;
  background: #fff;
  z-index: 201;
  transform: translate(100%);
  transition: transform 0.25s ease;
  display: flex;
  flex-direction: column;
}
.drawer-panel.open {
  transform: translate(0);
}
.drawer-head {
  padding: 0.75rem;
  border-bottom: 0.0625rem solid #f3f4f8;
}
.drawer-back {
  font-size: 1.25rem;
  color: #333;
  padding: 0 0.3125rem;
}
.drawer-title {
  font-size: 1rem;
  font-weight: 700;
  color: #000;
}
.drawer-clear {
  font-size: 0.8125rem;
  color: #999;
}
.drawer-body {
  flex: 1;
  background: #f3f4f8;
  overflow-y: auto;
}
.menuBlock {
  background: #fff;
  border-radius: 0.375rem;
  margin: 0.625rem auto;
  width: calc(100% - 1.25rem);
  padding: 0.625rem 0.3125rem;
}
.titleType {
  margin: 0.46875rem 0 0 0.46875rem;
  font-size: 1rem;
  font-weight: 700;
  color: #000;
}
.typeIcon {
  margin-right: 0.3125rem;
  width: 1.1875rem;
  height: 1.1875rem;
}
.grid3 {
  display: flex;
  flex-wrap: wrap;
  padding: 0.5rem 0.3125rem;
}
.allBtn {
  border-radius: 0.4375rem;
  margin: 0 0.3125rem 0.3125rem;
  padding: 0 0.3125rem;
  width: calc(33.33% - 0.625rem);
  box-sizing: border-box;
  font-size: 0.625rem;
  height: 1.625rem;
  line-height: 1.625rem;
  border: 0.03125rem solid #ddd;
  text-align: center;
  color: #454443;
  background: #edf1f3;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.allBtn.on {
  background-color: rgba(53, 120, 252, 0.1);
  color: #3578fc;
  border-color: #3578fc;
}
.nb-tabbar-space {
  width: 100%;
  height: calc(6rem + env(safe-area-inset-bottom));
}
</style>
