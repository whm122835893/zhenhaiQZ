<template>
  <div class="pageBg">
    <div class="status-bar" />

    <!-- ============ 顶部工具栏 ============ -->
    <div class="toolbar flex-bettwen">
      <div class="flex-start">
        <img class="tool-icon" :src="ICON.menu" alt="" @click="openMenu" />
      </div>
      <div class="flex-start">
        <img class="tool-icon scan" :src="ICON.scan" alt="" @click="scanCode" />
        <img class="tool-icon" :src="ICON.bell" alt="" @click="goMessage" />
      </div>
    </div>

    <!-- ============ 四宫格入口 ============ -->
    <div class="grid flex-bettwen">
      <div v-for="t in tabList" :key="t.id" class="grid-item" @click="navTo(t.url)">
        <img class="grid-icon" :src="t.icon" alt="" />
        <div class="grid-text">{{ t.name }}</div>
      </div>
    </div>

    <!-- ============ 藏品 / 盲盒 / 已售 ============ -->
    <div class="my-clloect-content">
      <div class="fixedTop">
        <div class="tab-view">
          <div class="tabs-scroll">
            <div class="tabs-row">
              <div
                v-for="(t, i) in typeList"
                :id="'tab-' + t.id"
                :key="t.id"
                class="tab-item"
                :class="{ active: tabKey === t.id }"
                @click="onTab(t.id)"
              >
                <span :class="tabKey === t.id ? 'tab-text-active' : 'tab-text'">{{ t.name }}</span>
                <div v-if="tabKey === t.id" class="tab-line" />
              </div>
            </div>
          </div>
        </div>

        <div class="search-view flex-bettwen">
          <div class="search-block flex-start">
            <img class="search-icon" :src="ICON.search" alt="" />
            <input
              v-model="keyword"
              class="search-input"
              type="search"
              maxlength="140"
              placeholder="请输入内容"
              placeholder-class="pla"
              @input="init"
              @confirm="init"
            />
          </div>
          <img class="fold-icon" :src="ICON.fold" alt="" @click="goFold" />
        </div>
      </div>

      <!-- 列表 -->
      <div v-if="dataList.length > 0" class="content-view">
        <div class="status-view" v-if="tabKey === 2">
          <div
            v-for="s in statusList"
            :key="s.value"
            class="s-item"
            :class="{ 's-item-active': status === s.value }"
            @click="onChangeStatus(s.value)"
          >
            {{ s.name }}
          </div>
          <div class="s-item" :class="{ 's-item-active': choseZu }" @click="changeType">
            合成
          </div>
        </div>

        <!--原站：横向行式（good-view 369x45），非网格卡片-->
        <div class="good-view">
          <div
            v-for="g in dataList"
            :key="g.iid"
            class="good-row"
            @click="openGood(g)"
          >
            <div class="gr-left">
              <img class="gr-img" :src="formatImgUrl(g.image || g.imageUrl)" alt="" />
              <div class="gr-info">
                <div class="gr-title oneRow">{{ g.name }}</div>
                <div class="gr-sub">持有数量：{{ g.ownQuantity }}</div>
              </div>
            </div>
            <div class="gr-right">
              <div class="gr-cell">
                <div class="gr-price">{{ floorPriceText(g) }}</div>
                <div class="gr-label">市场地板价</div>
              </div>
              <div class="gr-cell gr-cell-rate">
                <div class="gr-rate">
                  <img class="rateArrow" :src="rateIcon(g)" alt="" /> {{ rateText(g) }}
                </div>
                <div class="gr-label">市场涨跌幅</div>
              </div>
            </div>
          </div>
        </div>
        <div v-if="dataList.length > 0" class="load-txt">{{ loadTxt }}</div>
      </div>

      <!-- 空状态 -->
      <div v-else class="noData columnDoubleCenter">
        <img class="noData-icon" :src="ICON.noData" alt="" />
        <div class="font-999-26"> 暂无藏品</div>
      </div>
    </div>

    <div class="nb-tabbar-space" />

  <!-- ============ NFT 列表弹窗（原站 uv-popup mode=bottom, round=18, min-height:77vh） ============ -->
  <van-popup
    v-model:show="nftPopup"
    position="bottom"
    round="18"
    :style="{ zIndex: 99999 }"
    custom-style="width: 100%; min-height: 77vh;"
    closeable
  >
    <div class="nfp-wrap">
      <div class="nfp-header">
        <div class="nfp-title">{{ selectedItem.name }}（共{{ selectedItem.ownQuantity }}个）</div>
      </div>
      <div class="nfp-scroll" @scroll="onNftScroll">
        <div class="nfp-grid">
          <div
            v-for="(n, ni) in nftList"
            :key="ni"
            class="nfp-card"
            @click="goInfo(n)"
          >
            <div class="nfp-token"> #{{ n.token }}</div>
            <div class="status-tag" :class="{ 'status-selling': n.status === 1 || n.status === 3 }">
              {{ n.status === 0 ? '未寄售' : n.status === 1 ? '寄售中' : '转售中' }}
            </div>
            <div class="nfp-row">
              <span> 持有天数 </span>
              <span>{{ calculateHoldingDays(n.getTime) }}天 </span>
            </div>
            <div class="nfp-row">
              <span> 购入价 </span>
              <span>{{ amountFormat(n.buyPrice) }}</span>
            </div>
            <div class="nfp-row">
              <span> 寄售价 </span>
              <span>{{ amountFormat(n.price) }}</span>
            </div>
          </div>
        </div>
        <div v-if="!nftList.length" class="nfp-empty"> 暂无藏品 </div>
      </div>
      <!-- 原站：resourceList 含 nft:sell:up:batch 权限才显示 -->
      <div
        v-if="canBatchSale"
        class="nfp-batch-btn"
        @click="openBatchSale"
      >
        批量寄售
      </div>
    </div>
  </van-popup>

  <!-- ============ 批量寄售弹窗（原站 bs-card, round=18, zIndex=100100） ============ -->
  <van-popup
    v-model:show="batchSalePopup"
    position="center"
    round="18"
    :style="{ zIndex: 100100 }"
    :overlay-opacity="0.7"
    close-on-click-overlay
  >
    <div class="bs-card">
      <div class="bs-title">请选择批量寄售藏品</div>
      <div class="bs-row">
        <div class="bs-row-label">当前持有数量(可寄售)：</div>
        <div class="bs-row-value">{{ batchMaxQty }}</div>
      </div>
      <div class="bs-row">
        <div class="bs-row-label">最低价格：</div>
        <div class="bs-price-wrap">
          <input
            v-model="batchForm.minPrice"
            class="bs-price-input"
            type="number"
            placeholder="¥ 请输入价格"
          />
        </div>
      </div>
      <div class="bs-row">
        <div class="bs-row-label">最高价格：</div>
        <div class="bs-price-wrap">
          <input
            v-model="batchForm.maxPrice"
            class="bs-price-input"
            type="number"
            placeholder="¥ 请输入价格"
          />
        </div>
      </div>
      <div class="bs-qty-label">请选择批量份数：</div>
      <div class="bs-stepper">
        <div class="bs-step-btn" @click="batchMinus">−</div>
        <input
          :value="batchForm.quantity"
          class="bs-step-input"
          type="number"
          @input="onBatchQtyInput"
        />
        <div class="bs-step-btn" @click="batchPlus">+</div>
      </div>
      <div class="bs-tip">
        最大可批量寄售数量 {{ batchMaxQty }}，
        <span class="bs-tip-link" @click="selectAllBatch">全部选择</span>
      </div>
      <div class="bs-btns">
        <div class="bs-btn-cancel" @click="batchSalePopup = false">取消</div>
        <div class="bs-btn-ok" @click="batchConfirm">确定</div>
      </div>
    </div>
  </van-popup>

  <!-- ============ 批量寄售操作密码弹窗（原站 bsp-card, zIndex=100200） ============ -->
  <van-popup
    v-model:show="batchPwdPopup"
    position="center"
    round="18"
    :style="{ zIndex: 100200 }"
    :overlay-opacity="0.7"
    :close-on-click-overlay="false"
  >
    <div class="bsp-card">
      <div class="bsp-title">请输入操作密码</div>
      <div class="bsp-info">
        寄售 {{ batchForm.quantity || 0 }} 份 · ¥{{ batchForm.minPrice || 0 }} ~ ¥{{
          batchForm.maxPrice || 0
        }}
      </div>
      <div class="bsp-code">
        <div class="bsp-cells">
          <div
            v-for="i in 6"
            :key="i"
            class="bsp-cell"
            :class="{ 'bsp-cell--active': batchPwdFocus === i }"
          >
            {{ batchPwdChars[i - 1] ? '●' : '' }}
          </div>
        </div>
        <input
          class="bsp-hidden-input"
          type="number"
          maxlength="6"
          inputmode="numeric"
          :value="batchPwdChars.join('')"
          @focus="batchPwdFocus = batchPwdChars.filter(Boolean).length + 1"
          @input="onBatchPwdInput"
        />
      </div>
      <div class="bsp-tips">请输入6位数字交易密码</div>
    </div>
  </van-popup>


    <!-- ============ 左侧菜单抽屉（原站 uv-popup 菜单） ============ -->
    <div v-if="menuOpen" class="drawer-mask" @click="closeMenu" />
    <div class="drawer" :class="{ open: menuOpen }" :style="{ '--menu-bg': `url(${menuBg})` }">
      <div class="drawer-scroll">
        <div style="height: 3.125rem" />
        <div style="padding: 0 0.75rem; box-sizing: border-box">
          <div class="m-u-info flex-bettwen">
            <div class="flex-start">
              <img class="logo" :src="logoImg" alt="" @click="menuLogin" />
              <div class="i-data">
                <div class="font-333-28-bold">{{ account.uid ? (profile.nickname || '登录/注册') : '登录/注册' }}</div>
                <div v-if="account.uid" class="flex-center uid-pill" @click="copy(String(account.uid))">
                  <span class="uid-text"> 用户UID: {{ chainFormat(account.uid) }}</span>
                </div>
              </div>
            </div>
            <div class="flex-start">
              <img class="u-icon" :src="ICON_UICON" alt="" />
            </div>
          </div>

          <div class="menu-card">
            <div class="card-view">
              <div class="c-theme"> 基础信息</div>
              <div v-for="b in baseList" :key="b.id" class="c-item centerBetween" @click="menuGo(b.url)">
                <div class="left center">
                  <img class="c-icon" :src="b.icon" alt="" />
                  <div class="name font-333-28">{{ b.name }}</div>
                </div>
                <img class="arrow" :src="ICON.arrow" alt="" />
              </div>
            </div>
            <div class="card-view" style="margin-top: 1.4375rem">
              <div class="c-theme"> 其他</div>
              <div v-for="o in otherList" :key="o.id" class="c-item centerBetween" @click="menuGo(o.url)">
                <div class="left center">
                  <img class="c-icon" :src="o.icon" alt="" />
                  <div class="name font-333-28">{{ o.name }}</div>
                </div>
                <img class="arrow" :src="ICON.arrow" alt="" />
              </div>
            </div>
          </div>

          <div class="logoutBtn flex-center" @click="logout">
            <img class="logout-icon" :src="ICON.logout" alt="" />
            <span>退出登录</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { userApi, passportApi, nftApi } from '@/api'
import { baseUrl } from '@/utils/path'
import { storage, TOKEN_KEY } from '@/utils/storage'
import { encryptPassword } from '@/utils/crypto'
import { showLoading, hideLoading } from '@/api/request'

import menuIcon from '@/assets/my/tool-menu.png'
import scanIcon from '@/assets/my/tool-scan.png'
import bellIcon from '@/assets/my/tool-bell.png'
import nb6752 from '@/assets/my/nb-6752.png'
import nb73b2 from '@/assets/my/nb-73b2.png'
import nb3636 from '@/assets/my/nb-3636.png'
import nb63be from '@/assets/my/nb-63be.png'
import noDataImg from '@/assets/my/newNodata.png'
import searchIcon from '@/assets/my/searchicon.png'
import foldIcon from '@/assets/my/foldicon.png'
import menuBg from '@/assets/my/menu-bg.png'
import menuArrow from '@/assets/my/menu-arrow.png'
import menuUicon from '@/assets/my/menu-uicon.png'
import logoutIcon from '@/assets/my/logout-icon.png'
import logoImg from '@/assets/my/logo.jpg'
import mComm from '@/assets/my/menu/comm.png'
import mRight from '@/assets/my/menu/right.png'
import mTransfer from '@/assets/my/menu/transfer.png'
import mExchange from '@/assets/my/menu/exchange.png'
import mMerchant from '@/assets/my/menu/merchant.png'
import mChain from '@/assets/my/menu/chain.png'
import mProfile from '@/assets/my/menu/profile.png'
import mAddress from '@/assets/my/menu/address.png'
import mRealname from '@/assets/my/menu/realname.png'
import mAccount from '@/assets/my/menu/account.png'
import mSecurity from '@/assets/my/menu/security.png'
import mNotice from '@/assets/my/menu/notice.png'
import mReport from '@/assets/my/menu/report.png'
import mAbout from '@/assets/my/menu/about.png'
import mAgreement from '@/assets/my/menu/agreement.png'
import rateRed from '@/assets/warehouse/red.png'
import rateGreen from '@/assets/warehouse/green.png'

defineOptions({ name: 'MyPage' })

const router = useRouter()

const ICON = {
  menu: menuIcon,
  scan: scanIcon,
  bell: bellIcon,
  noData: noDataImg,
  search: searchIcon,
  fold: foldIcon,
  arrow: menuArrow,
  logout: logoutIcon,
}

/* ---------- 状态（严格对齐原站 data） ---------- */
const tabList = ref([
  { id: 1, name: '我的订单', icon: nb6752, url: '/pages/my/order/index' },
  { id: 2, name: '我的钱包', icon: nb73b2, url: '/pages/my/wallet/index' },
  { id: 3, name: '邀请好友', icon: nb3636, url: '/pages/my/invite/index' },
  { id: 4, name: '在线客服', icon: nb63be, url: '' },
])
const typeList = [
  { name: '藏品', id: 0 },
  { name: '盲盒', id: 2 },
  { name: '已售', id: 4 },
]
const statusList = [
  { name: '收藏中', value: 0 },
  { name: '寄售中', value: 1 },
  { name: '已寄售', value: 2 },
]
const moreList = [
  { id: 1, name: '社区共创', icon: mComm, url: '' },
  { id: 2, name: '版权权益', icon: mRight, url: '/pages/copyright/index' },
  { id: 3, name: '转赠记录', icon: mTransfer, url: '' },
  { id: 4, name: '礼品兑换', icon: mExchange, url: '' },
  { id: 5, name: '商家入驻', icon: mMerchant, url: '' },
  { id: 6, name: '链上查询', icon: mChain, url: '' },
]
const baseList = [
  { id: 1, name: '个人资料', icon: mProfile, url: '/pages/my/info/userInfo' },
  { id: 2, name: '收货地址', icon: mAddress, url: '/pages/my/info/adress' },
  { id: 3, name: '实名认证', icon: mRealname, url: '/pages/my/setting/realname' },
  { id: 4, name: '账号信息', icon: mAccount, url: '/pages/my/info/account' },
  { id: 5, name: '账号安全', icon: mSecurity, url: '/pages/my/info/pwd' },
]
const otherList = [
  { id: 1, name: '通知管理', icon: mNotice, url: '' },
  { id: 3, name: '投诉举报', icon: mReport, url: '' },
  { id: 4, name: '关于我们', icon: mAbout, url: '/pages/my/setting/about' },
  { id: 5, name: '协议管理', icon: mAgreement, url: '/pages/my/info/xieyi' },
]
const account = ref({})
const profile = ref({})
const tabKey = ref(0)
const status = ref(0)
const keyword = ref('')
const choseZu = ref(false)
const dataList = ref([])
const finished = ref(false)
const loadTxt = ref('')
const query = reactive({ pageNo: 1 })
const resourceList = ref([])
const menuOpen = ref(false)

/* ---------- NFT 列表弹窗（原站 openGood → $refs.popup） ---------- */
const nftPopup = ref(false)
const nftList = ref([])
const nftFinished = ref(false)
const nftQuery = reactive({ pageNo: 1, iid: '' })
const selectedItem = ref({})
/** 原站：resourceList.includes('nft:sell:up:batch') 才显示「批量寄售」按钮 */
const canBatchSale = computed(() => (resourceList.value || []).includes('nft:sell:up:batch'))

/* ---------- 批量寄售弹窗（原站 bs-card / bsp-card） ---------- */
const batchSalePopup = ref(false)
const batchPwdPopup = ref(false)
const batchForm = reactive({ iid: '', minPrice: '', maxPrice: '', quantity: '' })
const batchPwdChars = ref(['', '', '', '', '', ''])
const batchPwdFocus = ref(0)

/** 原站 computed batchMaxQty：max(0, ownQuantity||unopenQty - market_count) */
const batchMaxQty = computed(() => {
  const own = Number(selectedItem.value.ownQuantity || selectedItem.value.unopenQty || 0)
  const market = Number(selectedItem.value.market_count || 0)
  return Math.max(0, own - market)
})

/** 原站 getNftList：GET /nft?iid&pageNo */
function getNftList(append) {
  nftApi.list(nftQuery).then((res) => {
    if (res.status) {
      const arr = res.data || []
      nftList.value = append ? [...nftList.value, ...arr] : arr
      if (!arr.length) nftFinished.value = true
    } else if (res.code === 11111) {
      nftFinished.value = true
    }
  })
}
/** 原站 nftOnReachBottom：滚动到底部加载下一页 */
function onNftScroll(e) {
  const el = e.target
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
    if (nftFinished.value) return
    nftQuery.pageNo += 1
    getNftList(true)
  }
}
/** 原站 goInfo：关闭弹窗 → 跳转 NFT 详情 */
function goInfo(n) {
  nftPopup.value = false
  router.push('/pages/my/nft/info?nftId=' + n.nftId)
}

/* ---------- 批量寄售（原站 openBatchSale / batchConfirm / submitBatchSale） ---------- */
function openBatchSale() {
  if (!account.value.uid) return showToast('请先登录')
  if (batchMaxQty.value <= 0) return showToast('该藏品暂无可寄售数量')
  batchForm.iid = selectedItem.value.iid
  batchForm.minPrice = ''
  batchForm.maxPrice = ''
  batchForm.quantity = ''
  batchPwdChars.value = ['', '', '', '', '', '']
  batchSalePopup.value = true
}
function batchMinus() {
  const n = parseInt(batchForm.quantity) || 0
  if (n <= 0) return
  batchForm.quantity = String(n - 1)
}
function batchPlus() {
  const n = parseInt(batchForm.quantity) || 0
  if (n >= batchMaxQty.value) return showToast('已达最大可批量寄售数量')
  batchForm.quantity = String(n + 1)
}
function selectAllBatch() {
  batchForm.quantity = String(batchMaxQty.value)
}
/** 原站 onBatchQtyInput：仅保留数字，去除前导 0 */
function onBatchQtyInput(e) {
  let v = String(e.target.value || '').replace(/[^0-9]/g, '')
  v = v.replace(/^0+(?=\d)/, '')
  e.target.value = v
  batchForm.quantity = v
}
function batchConfirm() {
  if (!batchForm.minPrice || Number(batchForm.minPrice) <= 0 || !batchForm.maxPrice || Number(batchForm.maxPrice) <= 0) {
    return showToast('请输入有效价格')
  }
  if (!batchForm.quantity || Number(batchForm.quantity) <= 0) return showToast('请输入有效数量')
  if (Number(batchForm.quantity) > batchMaxQty.value) return showToast('超出最大可批量寄售数量')
  batchPwdChars.value = ['', '', '', '', '', '']
  batchPwdPopup.value = true
}
/** 原站 onBatchPwdFinish：满 6 位自动提交 */
function onBatchPwdInput(e) {
  const v = String(e.target.value || '').replace(/\D/g, '').slice(0, 6)
  e.target.value = v
  const arr = v.split('')
  while (arr.length < 6) arr.push('')
  batchPwdChars.value = arr
  batchPwdFocus.value = v.length + 1
  if (v.length === 6) submitBatchSale()
}
/** 原站 submitBatchSale：POST /nft/sell/up/batch，成功后刷新列表 */
function submitBatchSale() {
  const data = {
    payWay: 1,
    iid: batchForm.iid,
    minPrice: 100 * batchForm.minPrice,
    maxPrice: 100 * batchForm.maxPrice,
    quantity: Number(batchForm.quantity),
    secondPassword: encryptPassword(batchPwdChars.value.join('')),
  }
  showLoading()
  nftApi.sellUpBatch(data)
    .then((res) => {
      hideLoading()
      batchPwdPopup.value = false
      if (res.status) {
        batchSalePopup.value = false
        showToast('寄售成功')
        nftPopup.value = false
        init()
        nftFinished.value = false
        nftQuery.pageNo = 1
        getNftList()
      }
    })
    .catch(() => {
      hideLoading()
      batchPwdPopup.value = false
    })
}

/** 原站 $fmt.amountFormat：分 → ￥xx.xx */
function amountFormat(e) {
  return e ? '￥' + (Number(e) / 100).toFixed(2) : '￥--'
}
/** 原站行式列表：市场地板价（minPrice 为 null 时显示 ￥--） */
function floorPriceText(g) {
  return g.minPrice ? amountFormat(g.minPrice) : '￥--'
}
/** 原站行式列表：市场涨跌幅，带 +/- 号，保留两位小数 */
function rateText(g) {
  const v = g.priceChange
  if (v === null || v === undefined || v === '') return '+0.00%'
  const n = Number(v)
  const sign = n > 0 ? '+' : n < 0 ? '-' : '+'
  return sign + Math.abs(n).toFixed(2) + '%'
}
/** 原站 rateArrow：涨用 red.png，跌用 green.png */
function rateIcon(g) {
  const v = Number(g.priceChange || 0)
  return v < 0 ? rateGreen : rateRed
}
/** 原站 $fmt.calculateHoldingDays */
function calculateHoldingDays(e) {
  return Math.floor((Date.now() - e) / 864e5)
}

/* ---------- 方法（严格对齐原站 methods） ---------- */
function getList(t, e) {
  const a = { pageNo: query.pageNo }
  let req
  if (keyword.value) a.keyword = keyword.value
  if (tabKey.value === 2) {
    a.itemType = 2
    a.status = status.value
    req = nftApi.group(a)
  } else if (tabKey.value === 4) {
    req = nftApi.sellSold(a)
  } else {
    a.itemType = 1
    if (tabKey.value === 0 || tabKey.value === 6) a.is_merge = choseZu.value ? 1 : ''
    req = nftApi.group(a)
  }
  return req.then((res) => {
    if (res.status) {
      const d = (res.data || []).map((t) => ({
        ...t,
        image: t.imageUrl,
        subject: t.name,
        copies: t.ownQuantity || t.unopenQty || 0,
        is_merge: t.is_merge || 0,
        market_count: t.market_count || 0,
      }))
      dataList.value = e ? [...dataList.value, ...d] : d
      if (d.length < 10) {
        finished.value = true
        loadTxt.value = '没有更多了'
      } else loadTxt.value = ''
    } else if (res.code === 11111) {
      finished.value = true
      dataList.value = []
      loadTxt.value = ''
    }
  })
}
function init() {
  finished.value = false
  query.pageNo = 1
  getList()
}
function onTab(t) {
  if (tabKey.value !== t) {
    tabKey.value = t
    keyword.value = ''
    finished.value = false
    query.pageNo = 1
    getList()
  }
}
function onChangeStatus(t) {
  if (status.value !== t) {
    status.value = t
    finished.value = false
    query.pageNo = 1
    getList()
  }
}
function changeType() {
  choseZu.value = !choseZu.value
  finished.value = false
  query.pageNo = 1
  getList()
}
function getProfile() {
  userApi.getProfile().then((t) => {
    if (t.status) profile.value = t.data
    else if (t.code === 11111) profile.value = {}
  })
}
function getAccount() {
  passportApi.getAccount().then((t) => {
    if (t.status) account.value = t.data
    else if (t.code === 11111) account.value = {}
  })
}
function getNftPerm() {
  userApi.listResource().then((t) => {
    if (t.status && t.data) resourceList.value = t.data
  })
}
function navTo(t) {
  if (!account.value.uid) {
    showToast('请先登录')
    return
  }
  if (!t) {
    showToast('开发中')
    return
  }
  router.push(t)
}
function scanCode() {
  showToast('敬请期待！')
}
function goMessage() {
  showToast('敬请期待！')
}
function goFold() {
  showToast('敬请期待！')
}
function copy(t) {
  navigator.clipboard && navigator.clipboard.writeText(t)
  showToast('已复制')
}
function openMenu() {
  menuOpen.value = true
}
function closeMenu() {
  menuOpen.value = false
}
function menuLogin() {
  closeMenu()
  if (!account.value.uid) router.push('/pages/authorize/signin')
}
function menuGo(t) {
  if (!t) {
    showToast('敬请期待！')
    return
  }
  closeMenu()
  router.push(t)
}
function logout() {
  passportApi.signout().then(() => {
    closeMenu()
    showToast('已退出登录')
    account.value = {}
    dataList.value = []
    storage.remove(TOKEN_KEY)
  })
}
/** 原站 openGood：未登录提示；tabKey===4（已售）不弹；否则打开 NFT 列表弹窗 */
function openGood(t) {
  if (!account.value.uid) {
    showToast('请先登录')
    return
  }
  if (tabKey.value === 4) return
  selectedItem.value = t
  nftPopup.value = true
  nftFinished.value = false
  nftQuery.pageNo = 1
  nftQuery.iid = t.iid
  getNftList()
}
/* 原站 $fmt.chainFormat（index-HOch7q5G.js）：前 4 位 + ***** + 后 4 位 */
function chainFormat(e, t = 4) {
  return e ? e.slice(0, t) + '*****' + e.slice(-4) : ''
}
const ICON_UICON = menuUicon

function formatImgUrl(t) {
  return baseUrl(t) || baseUrl('/static/images/logo.jpg')
}

onMounted(() => {
  getProfile()
  getAccount()
  getNftPerm()
  finished.value = false
  query.pageNo = 1
  getList()
})
</script>

<style scoped>
/* ===== 类名与数值 100% 取自原站 assets/index-BFyM-Fni.css ===== */
.status-bar {
  height: var(--status-bar-height, 0px);
  width: 100%;
  flex-shrink: 0;
}
.flex-bettwen {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.centerBetween {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.rowCenter {
  display: flex;
  align-items: center;
  justify-content: center;
}
.doubleCenter {
  display: flex;
  align-items: center;
  justify-content: center;
}
.padding-top {
  padding-top: 20px;
}
.font-999-24 {
  font-family: 'PingFang SC', sans-serif;
  font-weight: 400;
  font-size: 0.75rem;
  color: #999;
}
.flex-start {
  display: flex;
  justify-content: flex-start;
  align-items: center;
}
.flex-center {
  display: flex;
  align-items: center;
  justify-content: center;
}
.center {
  display: flex;
  align-items: center;
  justify-content: center;
}
.centerWrap {
  display: flex;
  flex-wrap: wrap;
}
.columnDoubleCenter {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}
.oneRow {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.font-333-28 {
  font-family: 'PingFang SC', sans-serif;
  font-weight: 400;
  font-size: 0.875rem;
  color: #333;
}
.font-333-28-bold {
  font-family: 'PingFang SC', sans-serif;
  font-weight: 700;
  font-size: 0.875rem;
  color: #333;
}
.font-999-26 {
  font-family: 'PingFang SC', sans-serif;
  font-weight: 400;
  font-size: 0.8125rem;
  color: #999;
}
.pageBg {
  background-color: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  min-height: 100vh;
  padding-bottom: 150px;
}
.toolbar {
  width: 21.9375rem;
  margin: 0 auto;
  padding: 0.75rem 0;
  border-bottom: 0.03125rem solid #eeeeee;
}
.toolbar .flex-start {
  gap: 0.625rem;
}
.toolbar .tool-icon {
  width: 1.5rem;
  height: 1.5625rem;
  display: block;
}
.toolbar .scan {
  margin-right: 0.9375rem;
}
.grid {
  width: 100vw;
  padding: 1.5625rem 0.875rem 1rem;
  box-sizing: border-box;
}
.grid-item {
  width: 25%;
}
.grid-icon {
  width: 1.5rem;
  height: 1.5625rem;
  display: block;
  margin: auto;
}
.grid-text {
  margin-top: 0.5rem;
  text-align: center;
  font-size: 0.75rem;
  color: #333;
  font-family: 'PingFang SC', sans-serif;
}
.my-clloect-content {
  border-top: 0.0625rem solid #eee;
  background-color: #fff;
  width: 100%;
  min-height: 27.8125rem;
}
.fixedTop {
  position: sticky;
  top: 0;
  z-index: 200;
  background-color: #fff;
}
.tab-view {
  margin: 0 auto;
  padding: 0 0.6875rem;
  box-sizing: border-box;
}
.tabs-scroll {
  width: 100%;
  white-space: nowrap;
}
.tabs-row {
  display: flex;
  flex-direction: row;
}
.tab-item {
  position: relative;
  height: 2.5rem;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0 0.78125rem;
  box-sizing: border-box;
}
.tab-text {
  font-size: 0.875rem;
  color: #888;
  font-family: 'PingFang SC', sans-serif;
}
.tab-text-active {
  font-size: 0.9375rem;
  color: #333;
  font-weight: 700;
  transform: scale(1.05);
  display: inline-block;
}
.tab-line {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translate(-50%);
  width: 0.625rem;
  height: 0.09375rem;
  background: #333;
}
.search-view {
  margin: 0.625rem auto;
  width: calc(100% - 2rem);
  height: 2.25rem;
}
.search-block {
  flex: 1;
  background: #f3f4f8;
  border-radius: 3.125rem;
  padding: 0 0.75rem;
  box-sizing: border-box;
}
.search-icon {
  width: 0.71875rem;
  height: 0.71875rem;
  margin-right: 0.5rem;
}
.search-input {
  flex: 1;
  height: 2.03125rem;
  font-size: 0.875rem;
  color: #333;
  border: none;
  outline: none;
  background: transparent;
}
.pla {
  color: #9fa0b9;
  font-size: 0.875rem;
}
.fold-icon {
  width: 0.9375rem;
  height: 0.9375rem;
  margin-left: 0.8125rem;
}
.status-view {
  display: flex;
  align-items: center;
  margin: 0 auto 0.75rem;
  width: calc(100% - 2rem);
  font-size: 0.75rem;
}
.s-item {
  width: 4rem;
  height: 1.8125rem;
  border-radius: 2.8125rem;
  background: #f5f5f5;
  color: #454443;
  margin-right: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-family: 'PingFang SC', sans-serif;
}
.s-item-active {
  background: #222;
  color: #fff;
}
.content-view {
  width: 100%;
}
/* ---- 原站：横向行式仓库列表（good-view 369x45 / good-row 内联样式对齐） ---- */
.good-view {
  margin: 0 auto;
  width: calc(100% - 1.25rem);
  background-color: #fff;
  color: #000;
}
.good-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 0.78125rem 0;
}
.gr-left {
  display: flex;
  align-items: center;
  width: 50%;
}
.gr-img {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.375rem;
  display: block;
  flex-shrink: 0;
}
.gr-info {
  margin-left: 0.625rem;
  min-width: 0;
}
.gr-title {
  font-size: 0.8125rem;
}
.gr-sub {
  font-size: 0.625rem;
  color: #666;
  margin-top: 0.375rem;
}
.gr-right {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 50%;
}
.gr-cell {
  white-space: nowrap;
  flex: none;
}
.gr-price {
  font-size: 16px;
  white-space: nowrap;
}
.gr-rate {
  font-size: 16px;
  white-space: nowrap;
  line-height: 23px;
}
.rateArrow {
  width: 1rem;
  height: 18px;
  display: inline-block;
  vertical-align: -2px;
}
.gr-label {
  font-size: 0.625rem;
  color: #666;
  margin-top: 0.375rem;
}
.load-txt {
  width: 100%;
  padding: 13px 0;
  text-align: center;
  color: #999;
  font-size: 0.8125rem;
  font-family: 'PingFang SC', sans-serif;
}
.noData {
  margin-top: 2vh;
  padding-bottom: 1.5625rem;
}
.noData-icon {
  width: 10.6875rem;
  display: block;
}
.noData .font-999-26 {
  margin-top: 16px;
}
.nb-tabbar-space {
  width: 100%;
  height: calc(6rem + env(safe-area-inset-bottom));
}

/* ---- 抽屉（原站 uv-popup 菜单，从 menu-container 样式对齐） ---- */
.drawer-mask {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 998;
}
.drawer {
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  width: 18.125rem;
  background: #f3f6f8;
  background-image: var(--menu-bg, none);
  background-repeat: no-repeat;
  background-size: 100% 14.15625rem;
  z-index: 999;
  transform: translate(-100%);
  transition: transform 0.25s ease;
  overflow-y: auto;
}
.drawer.open {
  transform: translate(0);
}
.drawer-scroll {
  height: 100%;
  overflow-y: auto;
}
.m-u-info {
  position: relative;
  width: 100%;
  padding-top: 1.5rem;
}
.logo {
  width: 2.4375rem;
  height: 2.4375rem;
  border-radius: 50%;
  border: 2px solid #fff;
  margin-right: 0.625rem;
  background: #e4e7ed;
  object-fit: cover;
  flex-shrink: 0;
}
.i-data {
  flex: 1;
}
.uid-pill {
  height: 0.9375rem;
  background-color: rgba(0, 0, 0, 0.04);
  margin-top: 2px;
  border-radius: 0.4375rem;
  padding: 0 0.375rem;
  width: fit-content;
}
.uid-text {
  color: #666;
  font-size: 0.59375rem;
}
.uid-copy {
  margin-left: 0.25rem;
  color: #333;
  font-size: 0.59375rem;
}
.menu-card {
  background-color: #fff;
  border-radius: 0.9375rem 0.9375rem 0 0;
  padding: 0.75rem;
  box-sizing: border-box;
  margin-top: 0.9375rem;
}
.c-theme {
  font-size: 0.78125rem;
  font-weight: 600;
  color: #333;
  padding-bottom: 0.4375rem;
  margin-bottom: 0.71875rem;
  font-family: 'PingFang SC', sans-serif;
}
.c-item {
  margin-bottom: 1.4375rem;
}
.c-item.mb0 {
  margin-bottom: 0 !important;
}
.c-item .left {
  display: flex;
  align-items: center;
  flex: 1;
  min-width: 0;
}
.c-item .c-icon {
  width: 1.09375rem;
  height: 1.09375rem;
  margin-right: 0.59375rem;
}
.c-item .arrow {
  width: 0.71875rem;
  height: 0.71875rem;
}
.logoutBtn {
  margin: 27px auto 0;
  height: 2.96875rem;
  width: calc(100% - 1.5rem);
  background-color: #fff;
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.04);
  border-radius: 0.71875rem;
  font-weight: 500;
  color: #eb2a2a;
  font-size: 0.875rem;
  font-family: 'PingFang SC', sans-serif;
}
.logoutBtn .logout-icon {
  width: 1.21875rem;
  height: 1.21875rem;
  margin-right: 0.59375rem;
}

/* ============ NFT 列表弹窗（原站 uv-popup bottom, round:18, min-height:77vh） ============ */
.nfp-wrap {
  position: relative;
  padding: 1.625rem 0.625rem;
  padding-bottom: 1.625rem;
}
.nfp-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.nfp-title {
  display: flex;
  align-items: center;
  font-size: 17px;
  color: #333;
  font-weight: bold;
}
/* 原站 scroll-view max-height:900rpx = 468px */
.nfp-scroll {
  max-height: 468px;
  overflow-y: auto;
}
.nfp-grid {
  margin-top: 0.8125rem;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.625rem;
}
.nfp-card {
  background-color: #f9fbff;
  height: calc(30vw);
  color: #b9adad;
  border-radius: 0.5625rem;
  padding: 11px;
  box-sizing: border-box;
}
.nfp-token {
  font-weight: 700;
  font-size: 19px;
  color: rgb(51, 51, 51);
  margin-bottom: 4px;
}
.nfp-card .status-tag {
  display: inline-block;
}
.nfp-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: rgb(119, 119, 119);
  font-size: 11px;
  margin-top: 8px;
}
.nfp-empty {
  text-align: center;
  color: #999;
  font-size: 13px;
  padding: 2rem 0;
}
/* 原站 inline: margin-top:11px; position:absolute; bottom:60rpx; left/right:30rpx; height:52px */
.nfp-batch-btn {
  margin-top: 11px;
  position: absolute;
  bottom: 1.875rem;
  color: #fff;
  left: 0.9375rem;
  right: 0.9375rem;
  font-size: 15px;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  font-weight: 500;
  height: 52px;
  background: #3578fc;
  border-radius: 64px;
}

/* ============ 批量寄售弹窗（原站 bs-card, scopeId data-v-89ffbd10） ============ */
.bs-card {
  width: 19.375rem;
  background: #fff;
  border-radius: 0.75rem;
  padding: 1.375rem 1rem 1.125rem;
  box-sizing: border-box;
}
.bs-title {
  text-align: center;
  font-size: 1.0625rem;
  font-weight: 700;
  color: #1a1a1a;
  font-family: 'PingFang SC';
}
.bs-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #f3f4f8;
  border: 0.5px solid #dfe2ef;
  border-radius: 0.4375rem;
  height: 2.875rem;
  padding: 0 0.75rem;
  margin-top: 0.875rem;
}
.bs-row-label {
  font-size: 0.8125rem;
  color: #8a8a8a;
  flex-shrink: 0;
  font-family: 'PingFang SC';
}
.bs-row-value {
  font-size: 0.875rem;
  color: #333;
}
.bs-price-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  margin-left: 0.5rem;
}
.bs-price-symbol {
  font-size: 0.875rem;
  color: #333;
  margin-right: 0.25rem;
}
.bs-price-input {
  width: 7.5rem;
  height: 1.875rem;
  font-size: 0.875rem;
  color: #333;
  text-align: right;
  border: none;
  outline: none;
  background: transparent;
  padding: 0;
}
.bs-price-input::placeholder {
  color: #c0c4cc;
  font-size: 0.8125rem;
}
.bs-qty-label {
  font-size: 0.875rem;
  color: #333;
  margin-top: 1.0625rem;
  font-family: 'PingFang SC';
}
.bs-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 0.75rem;
}
.bs-step-btn {
  width: 2.0625rem;
  height: 2.0625rem;
  border-radius: 0.625rem;
  background: #3578fc;
  color: #fff;
  font-size: 1.3125rem;
  display: flex;
  align-items: center;
  justify-content: center;
}
.bs-step-input {
  width: 5.9375rem;
  height: 2.0625rem;
  margin: 0 0.8125rem;
  border: 0.03125rem solid #dcdfe6;
  border-radius: 0.3125rem;
  text-align: center;
  font-size: 0.875rem;
  color: #333;
  background: #fff;
  outline: none;
}
.bs-tip {
  text-align: center;
  font-size: 0.75rem;
  color: #999;
  margin-top: 0.9375rem;
  font-family: 'PingFang SC';
}
.bs-tip-link {
  color: #3578fc;
}
.bs-btns {
  display: flex;
  align-items: center;
  margin-top: 1.1875rem;
}
.bs-btn-cancel {
  flex: 1;
  height: 2.75rem;
  background: #f2f3f5;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  color: #333;
  margin-right: 0.8125rem;
  font-family: 'PingFang SC';
}
.bs-btn-ok {
  flex: 1;
  height: 2.75rem;
  background: #3578fc;
  border-radius: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  color: #fff;
  font-family: 'PingFang SC';
}

/* ============ 批量寄售密码弹窗（原站 bsp-card + uv-code-input size=48rpx） ============ */
.bsp-card {
  width: 17.5rem;
  background: #fff;
  border-radius: 0.75rem;
  padding: 1.5rem 1.125rem 1.375rem;
  box-sizing: border-box;
}
.bsp-title {
  text-align: center;
  font-size: 1rem;
  font-weight: 700;
  color: #333;
  font-family: 'PingFang SC';
}
.bsp-info {
  text-align: center;
  font-size: 0.75rem;
  color: #999;
  margin-top: 0.5rem;
}
.bsp-code {
  margin-top: 1.25rem;
  display: flex;
  justify-content: center;
  position: relative;
}
.bsp-cells {
  display: flex;
  align-items: center;
}
.bsp-cell {
  width: 1.5rem;
  height: 1.5rem;
  border: 0.03125rem solid #ddd;
  border-radius: 0.25rem;
  margin: 0 0.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1rem;
  color: #333;
}
.bsp-cell--active {
  border-color: #3578fc;
}
.bsp-hidden-input {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 100%;
  height: 100%;
  opacity: 0;
  z-index: 10;
  border: none;
  outline: none;
}
.bsp-tips {
  text-align: center;
  font-size: 0.6875rem;
  color: #bbb;
  margin-top: 0.9375rem;
  font-family: 'PingFang SC';
}

</style>
