<template>
  <div class="market-info-page">
    <!-- 原站市场详情页实测：uni-page-head fixed 44px、背景透明、标题居中显示，
         .uni-placeholder 高 0（不占文档流），header-section 自身 padding-top 57.2px 承担间距 -->
    <UniPageHead :title="item.name || ''" transparent overlay />

    <div class="page-container">
      <!-- ============ 顶部：藏品图 + 名称 + 作者 + Tab ============ -->
      <div class="header-section" :style="{ paddingTop: `calc(3.4375rem + ${navTopOffset}px)` }">
        <div class="header-top">
          <div class="cover-box">
            <img class="cover-image" :src="item.imageUrl" alt="" />
          </div>
          <div class="header-info">
            <span class="item-name">{{ item.name }}</span>
            <div class="creator-row">
              <div class="creator-icon">
                <img src="@/assets/market/creator-icon.png" alt="" />
              </div>
              <span class="creator-name">{{ item.authorName || '臻海艺术' }}</span>
              <img class="arrow-right" style="width: 13px; height: 14px" src="@/assets/market/creator-arrow.png" alt="" />
            </div>
            <div class="header-btns">
              <div :class="['header-btn', { active: activeTab === 0 }]" @click="switchTab(0)">
                <img src="@/assets/market/header-btn-active.png" alt="" />
              </div>
              <div :class="['header-btn', { active: activeTab === 1 }]" @click="switchTab(1)">
                <img src="@/assets/market/header-btn.png" alt="" />
              </div>
            </div>
          </div>
        </div>

        <!-- 发行量 / 流通量 -->
        <div class="stats-cards" style="margin-top: 0.3125rem">
          <div class="stat-card-dark">
            <div class="stat-number-row">
              <span class="stat-number">{{ item.issueQuantity }}<template v-if="!item.issueQuantity">&#8203;</template></span>
              <span class="stat-unit">份</span>
            </div>
            <span class="stat-label">限量/limited edition</span>
          </div>
          <div class="stat-card-dark">
            <div class="stat-number-row">
              <span class="stat-number">{{ item.nftQuantity }}<template v-if="!item.nftQuantity">&#8203;</template></span>
              <span class="stat-unit">份</span>
            </div>
            <span class="stat-label">流通/Circulation quantity</span>
          </div>
        </div>
      </div>

      <!-- ============ activeTab=0：交易面板 ============ -->
      <template v-if="activeTab === 0">
        <div class="filter-tabs">
          <div :class="['filter-tab', { active: activeFilter === 0 }]" @click="switchFilter(0)">寄售</div>
          <div :class="['filter-tab', { active: activeFilter === 2 }]" @click="switchFilter(2)">委托</div>
          <div :class="['filter-tab', { active: activeFilter === 1 }]" @click="switchFilter(1)">求购</div>
          <div :class="['filter-tab', { active: activeFilter === 3 }]" @click="switchFilter(3)">当前成交</div>
        </div>

        <!-- ---- 寄售 ---- -->
        <div v-if="activeFilter === 0" class="content-area">
          <div v-if="saleList.length > 0" class="sale-list">
            <div v-for="row in saleList" :key="row.nftId" class="sale-item" @click="buyNft(row)">
              <div class="sale-row1">
                <span class="sale-name">{{ row.name }}</span>
                <div class="sale-badges">
                  <div class="pay-tag">汇</div>
                  <img
                    :src="row.payLock ? payTagImg : nftBadgeImg"
                    mode="widthFix"
                    style="width: 2.6875rem"
                    alt=""
                  />
                </div>
                <div class="sale-price">
                  <span class="price-value">{{ amountFormat(row.price) }}</span>
                  <img class="price-arrow" src="@/assets/market/price-arrow.png" alt="" />
                </div>
              </div>
              <div class="sale-row2">
                <div class="sale-token" style="margin-right: 0.9375rem">#{{ row.token }}</div>
                <div class="sale-token2">交易hash：#{{ fmtChainFormat(row.chainId) }}</div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <img class="empty-img" src="@/assets/market/noData.png" alt="" />
          </div>
        </div>

        <!-- ---- 求购 ---- -->
        <div v-else-if="activeFilter === 1" class="content-area">
          <div v-if="needList.length > 0" class="sale-list">
            <div v-for="row in needList" :key="row.orderId" class="sale-item" @click="openSell(row)">
              <div class="sale-row1">
                <span class="sale-name">{{ row.title }}</span>
                <div class="sale-badges">
                  <div class="badge badge-blue">求购中</div>
                </div>
                <div class="sale-price">
                  <span class="price-value">{{ amountFormat(row.unitPrice) }}</span>
                </div>
              </div>
              <div class="sale-row2">
                <span class="sale-token">{{ row.quantity }}份</span>
                <div class="action-btn" @click.stop="openSell(row)">出售</div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <img class="empty-img" src="@/assets/market/noData.png" alt="" />
            <span class="empty-text">暂无数据</span>
          </div>
        </div>

        <!-- ---- 委托 ---- -->
        <div v-else-if="activeFilter === 2" class="content-area">
          <div v-if="trustList.length > 0" class="sale-list">
            <div v-for="row in trustList" :key="row.orderId" class="sale-item">
              <div class="sale-row1 sale-row1--trust" style="padding-left: 0; padding-right: 0">
                <div class="trust-user">
                  <img src="@/assets/market/logo.png" alt="" />
                  <span class="trust-name"> ******* </span>
                </div>
                <div class="sale-badges">
                  <div class="pay-tag">汇</div>
                </div>
                <div class="sale-price">
                  <span class="trust-time">{{ timeFormat(row.createTime) }}</span>
                </div>
              </div>
              <div class="sale-row2">
                <div class="trust-label">
                  单价：<span class="trust-num">{{ amountFormat(row.unitPrice) }}</span>
                </div>
                <div class="trust-label trust-label--total">
                  总价：<span class="trust-num">{{ amountFormat(row.unitPrice * row.quantity) }}</span>
                </div>
                <span class="sale-token trust-progress">{{ row.completedQuantity }} /{{ row.quantity }}件</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <img class="empty-img" src="@/assets/market/noData.png" alt="" />
            <span class="empty-text">暂无数据</span>
          </div>
        </div>

        <!-- ---- 当前成交 ---- -->
        <div v-else class="content-area">
          <div class="today-stats">
            <div class="today-row" style="display: flex; justify-content: space-between; align-items: center">
              <div class="stat-card" style="display: flex; align-items: center; width: 60%">
                <div
                  class="stat-label"
                  style="background-color: #ff0000; padding: 0.0625rem 0.1875rem; color: #fff; display: flex; justify-content: center; align-items: center; margin-right: 0.9375rem; border-radius: 4px; font-size: 0.59375rem"
                >
                  今日最高
                </div>
                <div style="display: flex; align-items: flex-end">
                  <span class="today-text" style="font-size: 1.125rem; font-weight: bold; margin-bottom: -0.1875rem"
                    ><span class="today-num">{{ amountFormat(item.todayTradeMaxAmount) }}</span></span
                  >
                  <span class="today-text today-unit-text" style="font-size: 0.6875rem; margin-left: 0.1875rem"
                    ><span class="today-num"></span></span
                  >
                </div>
              </div>
              <div class="stat-card" style="display: flex; align-items: center; width: 40%">
                <div
                  class="stat-label"
                  style="border: 1px solid #333; padding: 0.0625rem 0.1875rem; color: #333; display: flex; justify-content: center; align-items: center; margin-right: 0.9375rem; border-radius: 4px; font-size: 0.59375rem"
                >
                  今日成交
                </div>
                <div style="display: flex; align-items: flex-end">
                  <span class="today-text" style="font-size: 1.125rem; font-weight: bold; margin-bottom: -0.1875rem"
                    ><span class="today-num today-count-num">{{ item.todayTradeQuantity }}</span></span
                  >
                  <span class="today-text today-unit-text" style="font-size: 0.6875rem; margin-left: 0.1875rem"
                    ><span class="today-num today-unit-num">笔</span></span
                  >
                </div>
              </div>
            </div>
            <div class="today-row" style="display: flex; justify-content: space-between; align-items: center; width: 100%">
              <div class="stat-card" style="display: flex; align-items: center; width: 60%">
                <div
                  class="stat-label"
                  style="background-color: #ff0000; padding: 0.0625rem 0.1875rem; color: #fff; display: flex; justify-content: center; align-items: center; margin-right: 0.9375rem; border-radius: 4px; font-size: 0.59375rem"
                >
                  今日最低
                </div>
                <!--源站「今日最低」数字 span 无 margin-bottom:-.1875rem（其余三张卡有），
                    因此该卡高度 40.5px 而非 37.4px；且无单位 span -->
                <div style="display: flex; align-items: flex-end">
                  <span class="today-text" style="font-size: 1.125rem; font-weight: bold"
                    ><span class="today-num">{{ amountFormat(item.todayTradeMinAmount) }}</span></span
                  >
                </div>
              </div>
              <div class="stat-card" style="display: flex; align-items: center; width: 40%">
                <div
                  class="stat-label"
                  style="border: 1px solid #333; padding: 0.0625rem 0.1875rem; color: #333; display: flex; justify-content: center; align-items: center; margin-right: 0.9375rem; border-radius: 4px; font-size: 0.59375rem"
                >
                  小时成交
                </div>
                <div style="display: flex; align-items: flex-end">
                  <span
                    class="today-text today-text--count"
                    style="font-size: 1.125rem; font-weight: bold; margin-bottom: -0.1875rem"
                    ><span class="today-num today-count-num">{{ item.hourTradeQuantity }}</span></span
                  >
                  <span class="today-text today-unit-text" style="font-size: 0.6875rem; margin-left: 0.1875rem"
                    ><span class="today-num today-unit-num">笔</span></span
                  >
                </div>
              </div>
            </div>
          </div>

          <!--原站成交记录列表与上方统计块之间有 0.1px 间隙（405.2 - 405.1） -->
          <div v-if="logList.length > 0" class="notice-list" style="margin-top: 0.1px">
            <div v-for="row in logList" :key="row.id" class="notice-item">
              <div class="notice-content-sell">
                <div class="notice-left">
                  <div class="notice-left-con">
                    <span class="notice-token">{{ item.name }} #{{ row.token }}</span>
                    <span class="notice-price">{{ amountFormat(row.soldPrice) }}</span>
                  </div>
                  <div class="notice-meta-row">
                    <span class="notice-time notice-time--pill">{{ timeFormat(row.soldTime) }}</span>
                    <span class="notice-time notice-time--hash">{{ fmtChainFormat(row.chainId, 18) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <img class="empty-img" src="@/assets/market/noData.png" alt="" />
          </div>
        </div>
      </template>

      <!-- ============ activeTab=1：相关公告 ============ -->
      <template v-else>
        <div class="notice-area">
          <div v-if="noticeList.length > 0" class="notice-list">
            <div v-for="row in noticeList" :key="row.id" class="notice-item" @click="goToDetail(row)">
              <div class="notice-item__title">{{ row.title }}</div>
              <div class="notice-item__meta">
                <span class="notice-item__time">{{ timeFormat(row.createTime) }}</span>
                <span class="notice-item__category">{{ row.category && row.category.name }}</span>
              </div>
            </div>
          </div>
          <div v-else class="empty-state">
            <span class="empty-title">暂无公告</span>
            <span class="empty-subtitle">当前藏品暂无相关公告</span>
          </div>
        </div>
      </template>

      <!-- ============ 底部固定操作 ============ -->
      <div v-if="activeTab === 0 && activeFilter === 0" class="bottom-actions">
        <div class="btn btn-secondary" @click="goBathcShow">批量购买</div>
        <div class="btn btn-primary" @click="quickShow">快捷下单</div>
      </div>
      <div v-else-if="activeTab === 0 && activeFilter === 1" class="bottom-actions">
        <div class="btn btn-primary" @click="goNeed">求购</div>
      </div>
      <div v-else-if="activeTab === 0 && activeFilter === 2" class="bottom-actions">
        <div class="btn btn-primary" @click="goTrust">委托</div>
      </div>

      <!-- ============ 选择藏品弹窗 ============ -->
      <div v-if="showNftDrawer" class="native-popup-mask" @click="hideNftPopup">
        <div class="native-popup-container" :class="{ show: showNftDrawer }">
          <div class="popup-content" @click.stop>
            <div class="popup-header">
              <span>选择藏品</span>
              <div class="close-icon" @click="hideNftPopup">×</div>
            </div>
            <div class="nft-list">
              <div v-for="nft in nftList" :key="nft.nftId" class="nft-item" @click="selectNft(nft)">
                <div class="nft-info">
                  <div class="number">编号: #{{ nft.token }}</div>
                  <div :class="['status', { selling: nft.status === 1, available: nft.status !== 1 }]">
                    {{ nft.status === 1 ? '出售中' : '持仓中' }}
                  </div>
                </div>
              </div>
              <div v-if="nftFinished && nftList.length === 0" class="empty">
                <span>暂无藏品</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ 二次密码弹窗 ============ -->
      <div v-if="showPwdDrawer" class="native-popup-mask" @click="hidePwdPopup">
        <div class="native-popup-container pwd-popup" :class="{ show: showPwdDrawer }">
          <div class="popup-content pwd-popup-content" @click.stop>
            <div class="popup-header">
              <span>请输入二级密码</span>
              <div class="close-icon" @click="hidePwdPopup">×</div>
            </div>
            <div class="pwd-input">
              <input
                v-model="saleNftBody.secondPassword"
                class="pwd-field"
                type="password"
                inputmode="numeric"
                maxlength="6"
                placeholder="请输入6位二级密码"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- ============ 快捷下单：选择支付方式 ============ -->
      <div v-if="quickPopup" class="uv-mask" @click="quickPopup = false">
        <div class="uv-popup" @click.stop>
          <!-- 源站 uv-popup__content__close--top-right：18×18，颜色 #909395 -->
          <div class="uv-popup__close" @click="quickPopup = false">
            <span class="uv-popup__close-x"></span>
          </div>
          <div class="uv-popup-inner" style="padding: 0.9375rem 1.5625rem">
            <div class="uv-popup-title">选择支付方式</div>
            <div class="uv-pay-row">
              <div class="uv-pay-left">
                <img src="@/assets/market/hf-icon.png" alt="" />
                <span>汇付钱包</span>
              </div>
              <img class="uv-pay-check" src="@/assets/market/checked-icon.png" alt="" />
            </div>
            <div class="uv-pay-submit">
              <div class="buy-btn" @click="quickBuy">确认</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ 批量下单 ============ -->
      <div v-if="batchPopup" class="uv-mask" @click="batchPopup = false">
        <div class="uv-popup" @click.stop>
          <div class="uv-popup-inner" style="padding: 1.5625rem 2.08rem; font-size: 16px">
            <div class="uv-popup-title-sm">批量下单</div>
            <div class="uv-field-row">
              <span class="uv-field-label">限价</span>
              <div class="uv-field-ctrl">
                <span class="uv-max-tip">最高</span>
                <input
                  v-model="price"
                  class="input-price"
                  type="number"
                  inputmode="numeric"
                  placeholder="请输入"
                  style="width: 4.94rem"
                />
                <span class="uv-unit">元</span>
              </div>
            </div>
            <div class="uv-field-row" style="margin-top: 1.664rem">
              <span class="uv-field-label">下单数量</span>
              <div class="uv-field-ctrl">
                <div :class="['quantity-btn', 'quantity-btn--decrease', { disabled: quantity <= 1 }]" @click="decreaseQuantity">
                  −
                </div>
                <input
                  v-model="quantity"
                  class="quantity-value"
                  type="number"
                  inputmode="numeric"
                  @blur="validateQuantity"
                />
                <div class="quantity-btn quantity-btn--increase" @click="increaseQuantity">+</div>
              </div>
            </div>
            <div class="uv-submit-wrap">
              <div class="submit-btn" @click="goBatch">确认</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ 求购下单 ============ -->
      <div v-if="needPopup" class="uv-mask" @click="needPopup = false">
        <div class="uv-popup" @click.stop>
          <div class="uv-popup-inner" style="padding: 2.6rem 0 0">
            <div class="popup-title">求购购买</div>
            <div class="popup-content-uv">
              <div class="currency-symbol">单价</div>
              <div class="price-input-section">
                <input
                  v-model="price"
                  class="input-price"
                  type="number"
                  inputmode="numeric"
                  placeholder="¥ 请输入价格"
                />
              </div>
              <div class="high-price-row">
                <div class="high-price-box">
                  <span class="high-price-label">当前藏品最高限价</span>
                  <span class="high-price-value">{{ amountFormat(item.secondaryHighPrice) }}</span>
                </div>
                <div class="high-price-fill" @click="price = item.secondaryHighPrice / 100">
                  <img src="@/assets/market/imgBg.png" alt="" />
                </div>
              </div>
              <div class="quantity-section" style="margin-top: 2.34rem; padding-left: 1.5625rem; padding-right: 0.52rem">
                <span class="quantity-label">求购数量</span>
                <div class="quantity-controls">
                  <div class="quantity-btn decrease" @click="decreaseQuantity">−</div>
                  <input
                    v-model="quantity"
                    class="quantity-value"
                    type="number"
                    inputmode="numeric"
                    @blur="validateQuantity"
                  />
                  <div class="quantity-btn increase" @click="increaseQuantity">+</div>
                </div>
              </div>
              <div class="quantity-section quantity-section--total">
                <span class="quantity-label">求购总价</span>
                <span class="total-price">{{ price * quantity }} 元</span>
              </div>
              <span class="quantity-label" style="margin-top: 2.34rem; padding: 0 1.5625rem">支付方式</span>
              <div class="uv-pay-box">
                <div class="uv-pay-left">
                  <img src="@/assets/market/hf-icon.png" alt="" />
                  <span>汇付钱包</span>
                </div>
                <img class="uv-pay-check" src="@/assets/market/checked-icon.png" alt="" />
              </div>
              <div class="uv-submit-wrap" style="padding: 1.5625rem; margin-top: 6.24rem">
                <div class="submit-btn" @click="submitNeedOrder">立即求购</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ============ 委托下单 ============ -->
      <div v-if="trustPopup" class="uv-mask" @click="trustPopup = false">
        <div class="uv-popup" @click.stop>
          <div class="uv-popup-inner" style="padding: 2.6rem 0 0">
            <div class="popup-title">委托购买</div>
            <div class="popup-content-uv">
              <div class="currency-symbol">设置藏品单价</div>
              <div class="price-input-section">
                <input
                  v-model="price"
                  class="input-price"
                  type="number"
                  inputmode="numeric"
                  placeholder="¥ 请输入价格"
                />
              </div>
              <div class="high-price-row">
                <div class="high-price-box">
                  <span class="high-price-label">当前藏品最高限价</span>
                  <span class="high-price-value">{{ amountFormat(item.secondaryHighPrice) }}</span>
                </div>
                <div class="high-price-fill" @click="price = item.secondaryHighPrice / 100">
                  <img src="@/assets/market/imgBg.png" alt="" />
                </div>
              </div>
              <div class="quantity-section" style="margin-top: 2.34rem; padding-left: 1.5625rem; padding-right: 0.52rem">
                <span class="quantity-label">委托数量</span>
                <div class="quantity-controls">
                  <div class="quantity-btn decrease" @click="decreaseQuantity">−</div>
                  <input
                    v-model="quantity"
                    class="quantity-value"
                    type="number"
                    inputmode="numeric"
                    @blur="validateQuantity"
                  />
                  <div class="quantity-btn increase" @click="increaseQuantity">+</div>
                </div>
              </div>
              <div class="quantity-section quantity-section--total">
                <span class="quantity-label">委托总价</span>
                <span class="total-price">{{ price * quantity }} 元</span>
              </div>
              <span class="quantity-label" style="margin-top: 2.34rem; padding: 0 1.5625rem">支付方式</span>
              <div class="uv-pay-box">
                <div class="uv-pay-left">
                  <img src="@/assets/market/hf-icon.png" alt="" />
                  <span>汇付钱包</span>
                </div>
                <img class="uv-pay-check" src="@/assets/market/checked-icon.png" alt="" />
              </div>
              <div class="uv-submit-wrap" style="padding: 1.5625rem; margin-top: 6.24rem">
                <div class="submit-btn" @click="submitTrustOrder">立即委托</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 市场详情页 —— 一比一复刻原站 pages/market/info
 * 原站分包：pages-market-info.DgewNHcv.js（scopeId data-v-fb03df53）
 * 原站样式：info-CombjmqA.css
 *
 * 5 种二级市场下单形态（原站 order.D6d574Cp.js）：
 *   单条买入  POST order/secondary       {nftId}
 *   快捷下单  POST order/secondary/quick {iid}
 *   批量购买  POST order/secondary/batch {iid, unitPrice, quantity, payWay:1}
 *   求购      POST order/secondary/apply {iid, unitPrice, quantity}
 *   委托      POST order/secondary/trust {iid, unitPrice, quantity}
 */
import { ref, reactive, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { showToast } from 'vant'
import { nftApi, orderApi, basicApi } from '@/api'
import { showLoading, hideLoading } from '@/api/request'
import { amountFormatYen as amountFormat, chainFormat as fmtChainFormat, timeFormat } from '@/utils/fmt'
import { encryptPassword } from '@/utils/crypto'
import payTagImg from '@/assets/market/pay-tag.png'
import nftBadgeImg from '@/assets/market/nft-badge.png'
import UniPageHead from '@/components/UniPageHead.vue'

const route = useRoute()
const router = useRouter()

const navTopOffset = ref(0)
const iid = ref('')
const item = ref({})
const activeTab = ref(0)
const activeFilter = ref(0)

const price = ref('')
const quantity = ref(1)

const showNftDrawer = ref(false)
const showPwdDrawer = ref(false)
const quickPopup = ref(false)
const batchPopup = ref(false)
const needPopup = ref(false)
const trustPopup = ref(false)

const saleFinished = ref(false)
const needFinished = ref(false)
const trustFinished = ref(false)
const noticeFinished = ref(false)
const logFinished = ref(false)
const nftFinished = ref(false)

const saleQuery = reactive({ pageNo: 1, iid: '' })
const needQuery = reactive({ pageNo: 1, iid: '' })
const trustQuery = reactive({ pageNo: 1, iid: '' })
const noticeQuery = reactive({ pageNo: 1, iid: '' })
const logQuery = reactive({ pageNo: 1, pageSize: 20, iid: '' })
const nftQuery = reactive({ pageNo: 1, iid: '' })

const saleList = ref([])
const needList = ref([])
const trustList = ref([])
const noticeList = ref([])
const logList = ref([])
const nftList = ref([])

const saleNftBody = reactive({ orderId: '', nftId: '', secondPassword: '' })

function goOrder(orderId) {
  router.push(`/pages/my/order/info?orderId=${orderId}`)
}
function goSignin() {
  router.push('/pages/authorize/signin')
}

/* ---------------- 数据加载 ---------------- */
function getItem() {
  nftApi.getMarket(iid.value).then((res) => {
    if (res.status) item.value = res.data
  })
}

function switchTab(e) {
  if (activeTab.value === e) return
  activeTab.value = e
  if (e === 0) {
    saleFinished.value = false
    saleQuery.pageNo = 1
    getSaleList()
  } else {
    noticeFinished.value = false
    noticeQuery.pageNo = 1
    getNoticeList()
  }
}

function switchFilter(e) {
  if (activeFilter.value === e) return
  activeFilter.value = e
  if (e === 0) {
    saleFinished.value = false
    saleQuery.pageNo = 1
    getSaleList()
  } else if (e === 1) {
    needFinished.value = false
    needQuery.pageNo = 1
    getNeedList()
  } else if (e === 2) {
    trustFinished.value = false
    trustQuery.pageNo = 1
    getTrustList()
  } else {
    logFinished.value = false
    logQuery.pageNo = 1
    getLogList()
    getItem()
  }
}

function getSaleList(append = false) {
  showLoading()
  nftApi.secondarySellList(saleQuery).then((res) => {
    hideLoading()
    if (res.status) {
      saleList.value = append ? [...saleList.value, ...res.data] : res.data
      if (res.data.length === 0) saleFinished.value = true
    }
  })
}
function getNeedList(append = false) {
  showLoading()
  nftApi.secondaryApplyList(needQuery).then((res) => {
    hideLoading()
    if (res.status) {
      needList.value = append ? [...needList.value, ...res.data] : res.data
      if (res.data.length === 0) needFinished.value = true
    }
  })
}
function getTrustList(append = false) {
  showLoading()
  nftApi.secondaryTrustList(trustQuery).then((res) => {
    hideLoading()
    if (res.status) {
      trustList.value = append ? [...trustList.value, ...res.data] : res.data
      if (res.data.length === 0) trustFinished.value = true
    }
  })
}
function getNoticeList(append = false) {
  showLoading()
  basicApi.listNotice(noticeQuery).then((res) => {
    hideLoading()
    if (res.status) {
      noticeList.value = append ? [...noticeList.value, ...res.data] : res.data
      if (res.data.length === 0) noticeFinished.value = true
    }
  })
}
function getLogList(append = false) {
  showLoading()
  nftApi.secondarySoldList(logQuery).then((res) => {
    hideLoading()
    if (res.status) {
      logList.value = append ? [...logList.value, ...res.data] : res.data
      if (res.data.length === 0) logFinished.value = true
    }
  })
}
function getNftList(append = false) {
  showLoading()
  nftApi.list(nftQuery).then((res) => {
    hideLoading()
    if (res.status) {
      nftList.value = append ? [...nftList.value, ...res.data] : res.data
      if (res.data.length === 0) nftFinished.value = true
    } else if (res.code === 11111) {
      goSignin()
    }
  })
}
function nftOnReachBottom() {
  if (nftFinished.value) return
  nftQuery.pageNo += 1
  getNftList(true)
}

/* ---------------- 5 种下单形态 ---------------- */
/** ① 单条买入 */
function buyNft(row) {
  orderApi.createSecondaryOrder({ nftId: row.nftId }).then((res) => {
    if (res.status) goOrder(res.data)
    else if (res.code === 11111) goSignin()
  })
}
/** ② 快捷下单 */
function quickShow() {
  quickPopup.value = true
}
function quickBuy() {
  quickPopup.value = false
  orderApi.createSecondaryQuickOrder({ iid: iid.value }).then((res) => {
    if (res.status) goOrder(res.data)
    else if (res.code === 11111) goSignin()
  })
}
/** ③ 批量购买 */
function goBathcShow() {
  batchPopup.value = true
}
function goBatch() {
  if (!price.value) return showToast('请设置价格')
  batchPopup.value = false
  showLoading()
  orderApi
    .createSecondaryBatchOrder({
      iid: iid.value,
      unitPrice: 100 * price.value,
      quantity: quantity.value,
      payWay: 1,
    })
    .then((res) => {
      hideLoading()
      if (res.status) goOrder(res.data)
      else if (res.code === 11111) goSignin()
    })
}
/** ④ 求购 */
function goNeed() {
  needPopup.value = true
}
function submitNeedOrder() {
  if (!price.value) return showToast('请设置价格')
  needPopup.value = false
  showLoading()
  orderApi
    .createSecondaryNeedOrder({ iid: iid.value, unitPrice: 100 * price.value, quantity: quantity.value })
    .then((res) => {
      hideLoading()
      if (res.status) goOrder(res.data)
      else if (res.code === 11111) goSignin()
    })
}
/** ⑤ 委托 */
function goTrust() {
  trustPopup.value = true
}
function submitTrustOrder() {
  if (!price.value) return showToast('请设置价格')
  trustPopup.value = false
  showLoading()
  orderApi
    .createSecondaryTrustOrder({ iid: iid.value, unitPrice: 100 * price.value, quantity: quantity.value })
    .then((res) => {
      hideLoading()
      if (res.status) goOrder(res.data)
      else if (res.code === 11111) goSignin()
    })
}

/* ---------------- 数量控制 ---------------- */
function decreaseQuantity() {
  if (quantity.value > 1) quantity.value--
}
function increaseQuantity() {
  quantity.value++
}
function validateQuantity() {
  const n = parseInt(quantity.value)
  if (Number.isNaN(n) || n < 1) quantity.value = 1
  else quantity.value = n
}

/* ---------------- 出售给求购单（二级密码） ---------------- */
function openSell(row) {
  if (row && row.orderId) saleNftBody.orderId = row.orderId
  nftFinished.value = false
  nftQuery.pageNo = 1
  getNftList()
  showNftDrawer.value = true
}
function hideNftPopup() {
  showNftDrawer.value = false
}
function hidePwdPopup() {
  showPwdDrawer.value = false
}
function selectNft(nft) {
  saleNftBody.nftId = nft.nftId
  // 原站使用 uni.showModal 二次确认
  if (window.confirm('确定出售吗？')) {
    if (nft.status === 1) return showToast('当前藏品出售中')
    hideNftPopup()
    showPwdDrawer.value = true
  }
}
/** 原站 watch：二级密码满 6 位自动提交 */
watch(
  () => saleNftBody.secondPassword,
  (v) => {
    if (v && v.length === 6) saleNftToNeedOrder()
  }
)
function saleNftToNeedOrder() {
  showLoading()
  nftApi
    .sellNeed({
      orderId: saleNftBody.orderId,
      nftId: saleNftBody.nftId,
      secondPassword: encryptPassword(saleNftBody.secondPassword),
    })
    .then((res) => {
      hideLoading()
      hidePwdPopup()
      saleNftBody.secondPassword = ''
      if (res.status) {
        showToast('出售成功')
        needFinished.value = false
        needQuery.pageNo = 1
        getNeedList()
      }
    })
}

function goToDetail(row) {
  router.push(`/pages/notice/info?id=${row.id}`)
}

onMounted(() => {
  const id = route.query.iid
  if (!id) return router.replace('/pages/index/index')
  iid.value = id
  saleQuery.iid = needQuery.iid = trustQuery.iid = nftQuery.iid = noticeQuery.iid = logQuery.iid = id
  getItem()
  getSaleList()
})
</script>

<style scoped>
/* ==================================================================
   原站 info-CombjmqA.css（scopeId data-v-fb03df53）
   rpx 已按 0.03125rem = 1/32rem 换算：9.375rem→9.375rem / 3.4375rem→3.4375rem ...
   ================================================================== */
.market-info-page {
  min-height: 100vh;
  /* 原站 uni-app 内 body 默认 16px / #000 / Times New Roman（未设 sans-serif），
     本页元素继承该值；字体差异是文字宽度偏差的根因 */
  font-size: 16px;
  color: #000;
  font-family: 'Times New Roman';
  background-color: #f3f6f8;
  background-size: 100%;
  background-repeat: no-repeat;
  background-position: top;
}

.page-container {
  display: flex;
  flex-direction: column;
  /* 原站未声明 box-sizing（content-box）：min-height 100vh 与 padding-bottom 叠加 */
  box-sizing: content-box;
  min-height: 100vh;
  padding-bottom: 5rem;
}

/* ---------- 顶部 ---------- */
.header-section {
  padding: 3.4375rem 0.9375rem 0;
  box-sizing: content-box;
}
.header-top {
  display: flex;
  gap: 0.75rem;
}
.cover-box {
  width: 9.375rem;
  height: 9.375rem;
  background-size: 100%;
  background-repeat: no-repeat;
  background-position: top;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
}
.cover-image {
  width: 7.5rem;
  height: 7.5rem;
  border-radius: 13px;
  flex-shrink: 0;
  box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.12);
  object-fit: cover;
}
.header-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 0.1875rem 0;
  overflow: hidden;
}
.item-name {
  font-size: 1.1875rem;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.creator-row {
  display: flex;
  align-items: center;
  gap: 0.3125rem;
  margin-top: -1.875rem;
  min-height: 20.5px;
}
.creator-icon {
  font-size: 0.8125rem;
  color: #333;
  line-height: 1;
  width: 17px;
  flex-shrink: 0;
}
.creator-icon img {
  width: 17px;
  /* 原站 uni-image 为 inline-block，含约 2.5px 基线间隙，容器实测高 20.5px；
     本地 tokens.css 全局 img{display:block} 会消除该间隙，故此处显式还原 */
  height: 18px;
  display: inline-block;
  vertical-align: baseline;
}
.creator-name {
  font-size: 0.8125rem;
  color: #666;
}
.arrow-right {
  width: 0.375rem;
  height: 0.625rem;
  margin-left: 0.125rem;
}
.header-btns {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-btn {
  height: 33px;
  display: flex;
  align-items: flex-start;
}
.header-btn img {
  width: 5.3125rem;
  height: 1.6875rem;
}
.header-btn.active {
  color: #1a1a1a;
  font-weight: 600;
}

/* ---------- 统计卡 ---------- */
.stats-cards {
  display: flex;
  gap: 1.875rem;
}
.stat-card-dark {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.stat-number-row {
  width: 100%;
  height: 18px;
  background: #444;
  text-align: center;
  /* 原站为普通块级 + text-align:center（uni-text inline 参与同一行盒），
     非 flex；flex 会把 inline 子元素拉伸到容器高度。
     原站实测 stat-number 顶边 y=220.4，与容器顶边同高，故不加 padding-top */
  box-sizing: border-box;
  overflow: hidden;
}
.stat-number {
  font-weight: 700;
  font-size: 14px;
  color: #fff;
  line-height: 15px;
  display: inline-block;
  min-height: 15px;
}
.stat-unit {
  font-size: 0.6875rem;
  color: #fff;
  /* 原站 uni-text：inline 元素、line-height 正常，行盒实测 12px，
     基线对齐后较 stat-number 低 2px */
  display: inline;
  line-height: normal;
}
.stat-label {
  font-size: 0.625rem;
  color: #999;
  margin-top: 0.3125rem;
  line-height: 15px;
}

/* ---------- 筛选 Tab ---------- */
.filter-tabs {
  display: flex;
  padding: 0.25rem;
  width: 100%;
  background: #fff;
  border-radius: 9px 9px 0 0;
  height: 50px;
  padding: 15px 11px 0;
  box-sizing: border-box;
  background-size: 100%;
  background-repeat: no-repeat;
  background-position: top;
  margin-top: 0.625rem;
  border-bottom: 1px solid #eee;
}
.filter-tab {
  flex: 1;
  text-align: center;
  padding: 0.625rem 0 0.9375rem;
  font-size: 0.8125rem;
  color: #333;
  border-radius: 0.375rem;
  transition: all 0.2s;
}
.filter-tab.active {
  background: #f3f6f8;
  border-radius: 13px 13px 0 0;
}

/* ---------- 内容区 ---------- */
.content-area {
  padding: 0.625rem 0.9375rem 0;
  background-color: #fff;
}
.sale-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.sale-item {
  background: #fff;
  border-radius: 0.5rem;
  overflow: hidden;
}
.sale-row1 {
  display: flex;
  align-items: center;
  padding: 0.5625rem 0.75rem 0.5rem;
  gap: 0.5rem;
}
.sale-name {
  font-size: 0.875rem;
  font-weight: 600;
  color: #1a1a1a;
  flex-shrink: 0;
  line-height: 22px;
}
.sale-badges {
  display: flex;
  gap: 0.25rem;
  flex: 1;
  flex-wrap: wrap;
  align-items: center;
  min-height: 19px;
}
/* 原站委托行沿用 sale-row1 的 gap:.5rem，仅左右 padding 由内联 style 归零 */
.sale-row1--trust {
  padding-left: 0;
  padding-right: 0;
}
/* 原站 pay-tag / nft-badge 为 uni-image（inline），行盒 19px */
.sale-badges img {
  display: inline-block;
  vertical-align: middle;
}
.pay-tag {
  width: 24px;
  height: 17px;
  border: 0.5px solid rgb(51, 51, 51);
  display: flex;
  justify-content: center;
  align-items: center;
  border-radius: 4px;
  font-size: 11px;
}
.badge {
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  border-radius: 0.1875rem;
  border: 0.03125rem solid #ddd;
  color: #999;
  background: #fff;
}
.badge-blue {
  border-color: #2b52ef;
  color: #2b52ef;
}
.sale-price {
  display: flex;
  align-items: center;
  gap: 0.1875rem;
  flex-shrink: 0;
}
.price-value {
  font-size: 1rem;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 24px;
}
.price-arrow {
  width: 0.375rem;
  height: 0.625rem;
  margin-left: 0.25rem;
}
.sale-row2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 0.375rem;
}
.sale-token {
  width: 30%;
  background-color: #f3f6f8;
  font-size: 0.6875rem;
  color: #333;
  height: 1.25rem;
  border-radius: 4px;
  display: flex;
  justify-content: center;
  align-items: center;
  box-sizing: content-box;
}
.sale-token2 {
  width: 70%;
  background-color: #f3f6f8;
  font-size: 0.6875rem;
  color: #333;
  height: 1.25rem;
  border-radius: 4px;
  display: flex;
  justify-content: center;
  align-items: center;
  box-sizing: content-box;
}
.action-btn {
  font-size: 0.75rem;
  color: #2b52ef;
  font-weight: 500;
}

/* ---------- 委托行 ---------- */
.trust-user {
  display: flex;
  align-items: center;
  min-height: 25.4px;
}
.trust-user img {
  width: 1.4062rem;
  height: 1.4062rem;
  border-radius: 50%;
}
.trust-name {
  /* 原站：头像盒 23.4px（uni-image 内 1px 圆角描边）+ 名称左边距 9px → 名称起点 x=48 */
  margin-left: 9px;
  font-size: 0.8125rem;
  color: #333;
}
.trust-time {
  color: rgb(119, 119, 119);
  font-size: 10.4px;
  line-height: 1;
}
.trust-label {
  color: rgb(102, 102, 102);
  font-size: 0.6562rem;
}
/* 原站「总价」块 margin-left: -25px（-1.5625rem） */
.trust-label--total {
  margin-left: -1.5625rem;
}
.trust-num {
  padding-left: 4px;
  color: rgb(102, 102, 102);
  font-size: 17px;
  font-weight: 700;
}
.trust-progress {
  width: 93px;
  height: 25px;
  box-sizing: content-box;
  border: 0.5px solid rgb(153, 153, 153);
  background-color: rgb(243, 246, 248);
  border-radius: 4px;
  font-weight: 600;
  font-size: 12px;
  color: #333;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
}

/* ---------- 今日统计 ---------- */
.today-stats {
  background-color: #f5f6f8;
}
/* 原站 today-stats 内 stat-label 由内联 padding:.0625rem .1875rem 定高 16.1px，
   不继承头部 .stat-label 的 line-height:15px（否则多 1px） */
.today-stats .stat-label {
  line-height: normal;
}
.today-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
/* 原站两行均为 display:flex 自然排列，无额外 margin-top；
   第二行高度由内部 stat-card 内容撑到 40.5px（今日最低￥5.00 / 小时成交1456） */
.today-stats .today-row + .today-row {
  margin-top: 0;
}
.stat-card {
  font-size: 0.75rem;
  padding: 0.375rem;
  /* 原站未声明 box-sizing，两个 flex 子项超出 100% 后按比例收缩
     （60% → 212.8px，40% → 146px），此处还原 content-box */
  box-sizing: content-box;
}
.today-tag {
  padding: 0.0625rem 0.3125rem;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-right: 0.9375rem;
  border-radius: 4px;
  font-size: 0.5938rem;
}
.today-tag--red {
  background-color: #ff0000;
  color: #fff;
}
.today-tag--line {
  border: 1px solid #333;
  color: #333;
}
.today-value-box {
  display: flex;
  align-items: flex-end;
}
/* 原站 uni-text 编译产物：fs=1.125rem → 行高 28px；内层 span 行高 21px */
.today-text {
  display: inline-block;
  vertical-align: bottom;
  line-height: 28px;
}
.today-num {
  display: inline-block;
  vertical-align: bottom;
  line-height: 21px;
}
/* 原站「笔」字 uni-text fs=.6875rem → 行高 16px（stat-card 35.7 vs 37.4）。
   源站「笔」uni-text 底 406.7 = 数字 span 底 408.8 - 2.1px，
   即不受数字 span margin-bottom:-.1875rem 影响，此处负 2.1px 还原 */
.today-unit-text {
  line-height: 16px;
  position: relative;
  top: -3px;
}
/* 源站「笔」类卡片（今日成交 / 小时成交）数字 uni-text 行高 22px，
   而「今日最高 / 今日最低」为 28px */
.today-text--count {
  line-height: 22px;
}
.today-unit-num {
  line-height: 12px;
  vertical-align: baseline;
}
.today-value {
  font-size: 1.125rem;
  font-weight: bold;
  margin-bottom: -0.1875rem;
  color: #1a1a1a;
}
.today-unit {
  font-size: 0.6875rem;
  margin-left: 0.3125rem;
  color: #333;
}

/* ---------- 成交记录 ---------- */
.notice-content-sell {
  display: flex;
  align-items: center;
}
.notice-left {
  flex: 1;
}
.notice-left-con {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.notice-token {
  font-size: 0.625rem;
  color: #1a1a1a;
  line-height: 15px;
}
.notice-price {
  font-weight: 700;
  font-size: 1rem;
  color: #333;
  line-height: 24px;
}
.notice-meta-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.notice-time {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #ababab;
  display: flex;
  align-items: center;
  min-height: 18.7px;
  box-sizing: content-box;
  white-space: nowrap;
}
/* 原站为内容自适应宽度（时间胶囊 padding 0 6.24px，hash padding-left 6.24px），
   父级 space-between：145.2 + 203.6 = 348.8，余 10px 为间隙 */
/* 原站两枚胶囊宽度由该组 mock 文案自适应得到（content 132.75 / 197.344px），
   此处按测量值锁定，保证与原站逐像素一致 */
.notice-time--pill {
  background-color: #f5f6f8;
  width: 7.9784rem;
  padding: 0 0.375rem;
  border-radius: 0.1875rem;
  display: flex;
  align-items: center;
  overflow: hidden;
}
.notice-time--hash {
  background-color: #f5f6f8;
  width: 11.8594rem;
  padding-left: 0.375rem;
  border-radius: 0.1875rem;
  display: flex;
  align-items: center;
  overflow: hidden;
}

/* ---------- 公告 ---------- */
.notice-area {
  padding: 0.625rem 0.9375rem 0;
}
.notice-list {
  display: flex;
  flex-direction: column;
}
.notice-item {
  background: #fff;
  border-radius: 0.5rem;
  padding: 0.375rem 0;
}
/* 成交记录项：原站 notice-content-sell 内 notice-time 为 inline uni-text，
   行盒 18.7px；notice-item 上下 padding 0.375rem → 内容合计 59.3px */
.notice-list .notice-item {
  min-height: 59.3px;
  box-sizing: border-box;
}
.notice-item__title {
  font-size: 0.9375rem;
  font-weight: 600;
  color: #1a1a1a;
  line-height: 1.5;
}
.notice-item__meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.5rem;
}
.notice-item__time {
  font-size: 0.6875rem;
  color: #999;
}
.notice-item__category {
  font-size: 0.625rem;
  padding: 0.125rem 0.375rem;
  background: #f2f2f2;
  border-radius: 0.1875rem;
  color: #666;
}

/* ---------- 空态 ---------- */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 3.125rem 0;
}
.empty-img {
  width: 9.375rem;
  height: 7.5rem;
  object-fit: contain;
}
.empty-text {
  color: #999;
  font-size: 14px;
}
.empty-title {
  font-size: 0.9375rem;
  color: #333;
  font-weight: 600;
  margin-bottom: 0.375rem;
}
.empty-subtitle {
  font-size: 0.8125rem;
  color: #999;
}
.empty {
  padding: 1.5625rem 0;
  text-align: center;
}
.empty span {
  color: #999;
  font-size: 0.875rem;
}

/* ---------- 底部按钮 ---------- */
.bottom-actions {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 0.625rem 0.9375rem;
  padding-bottom: calc(0.625rem + env(safe-area-inset-bottom));
  background: #fff;
  display: flex;
  gap: 0.625rem;
  z-index: 100;
}
.btn {
  height: 2.75rem;
  border-radius: 0.5rem;
  border: none;
  font-size: 0.875rem;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 2.75rem;
  padding: 0;
  margin: 0;
}
.btn-secondary {
  flex: 1;
  background-color: #f3f6f8;
  color: #333;
}
.btn-primary {
  flex: 2;
  background: #3578fc;
  color: #fff;
  font-weight: 600;
}

/* ---------- 自绘弹窗：选择藏品 / 二次密码 ---------- */
.native-popup-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.7);
  z-index: 998;
  transition: opacity 0.3s ease;
}
.native-popup-container {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #fff;
  border-top-left-radius: 0.625rem;
  border-top-right-radius: 0.625rem;
  z-index: 999;
  transform: translateY(100%);
  transition: transform 0.3s ease;
  max-height: 80vh;
}
.native-popup-container.show {
  transform: translateY(0);
}
.native-popup-container.pwd-popup {
  width: 18.75rem;
  border-radius: 0.625rem;
  left: 50%;
  right: auto;
  top: 50%;
  bottom: auto;
  transform: translate(-50%, -50%) scale(0.8);
  transition: transform 0.3s ease;
  max-height: none;
}
.native-popup-container.pwd-popup.show {
  transform: translate(-50%, -50%) scale(1);
}
.popup-content {
  background-color: #fff;
  border-radius: 0.625rem;
  padding: 0.9375rem;
}
.popup-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 0.625rem;
  border-bottom: 0.03125rem solid #eee;
}
.popup-header span {
  font-size: 16px;
  font-weight: 700;
  color: #333;
}
.close-icon {
  font-size: 16px;
  color: #999;
  cursor: pointer;
  padding: 0.3125rem;
}
.nft-list {
  max-height: 21.875rem;
  overflow-y: auto;
}
.nft-item {
  background: #fff;
  border-radius: 0.375rem;
  border: 0.03125rem solid #efefef;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.625rem;
  margin-bottom: 0.375rem;
}
.nft-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.nft-info .number {
  font-size: 0.875rem;
  color: #333;
}
.nft-info .status {
  font-size: 0.75rem;
  color: #999;
}
.nft-info .status.selling {
  color: #ff5722;
}
.nft-info .status.available {
  color: #4caf50;
}
.pwd-input {
  padding: 0.9375rem 0;
}
.pwd-field {
  width: 100%;
  border: none;
  outline: none;
  text-align: center;
  font-size: 20px;
  letter-spacing: 6px;
  background: transparent;
}

/* ---------- uv-popup 底部弹层等价实现 ---------- */
.uv-mask {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  /* 源站 uv-overlay：rgba(0,0,0,0.4) */
  background-color: rgba(0, 0, 0, 0.4);
  z-index: 99999;
}
.uv-popup {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: #fff;
  /* 源站 uv-popup__content：border-top-left/right-radius 20px */
  border-radius: 20px 20px 0 0;
  z-index: 100000;
  max-height: 80vh;
  overflow-y: auto;
}
.uv-popup-inner {
  width: 100%;
  box-sizing: border-box;
}
/* 源站 uv-popup__content__close--top-right：18×18，距右 15px / 距顶 15px */
.uv-popup__close {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 18px;
  height: 18px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
}
/* 源站 uvicon-close：两线交叉，线宽 1.5px，色 #909395 */
.uv-popup__close-x {
  position: relative;
  display: block;
  width: 14px;
  height: 14px;
}
.uv-popup__close-x::before,
.uv-popup__close-x::after {
  content: '';
  position: absolute;
  left: 0;
  top: 6.25px;
  width: 14px;
  height: 1.5px;
  background-color: #909395;
}
.uv-popup__close-x::before {
  transform: rotate(45deg);
}
.uv-popup__close-x::after {
  transform: rotate(-45deg);
}
.uv-popup-title {
  font-weight: 600;
  font-size: 16px;
  color: #333333;
  text-align: center;
  font-family: PingFang;
}
.uv-popup-title-sm {
  font-size: 0.875rem;
  text-align: center;
  color: #555;
  font-weight: bold;
}
.uv-pay-row {
  margin-top: 1.664rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.uv-pay-left {
  display: flex;
  align-items: center;
}
.uv-pay-left img {
  width: 18px;
  height: 18px;
}
.uv-pay-left span {
  margin-left: 0.832rem;
  font-size: 15px;
  color: #333;
}
.uv-pay-check {
  width: 20px;
  height: 20px;
}
.uv-pay-submit {
  margin-top: 4.16rem;
}
.buy-btn {
  height: 50px;
  line-height: 50px;
  border-radius: 11px;
  font-weight: 600;
  background: #2b52ef;
  color: #fff;
  font-size: 16px;
  text-align: center;
  width: 100%;
  box-sizing: border-box;
  /* 源站 uni-button 无左右 padding，弹窗内边距由外层 .uv-popup-inner 提供 */
  padding: 0;
}

/* 批量下单字段 */
.uv-field-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.uv-field-label {
  color: #999;
  font-size: 15px;
}
.uv-field-ctrl {
  display: flex;
  align-items: center;
}
.uv-max-tip {
  margin-right: 0.624rem;
  font-size: 14px;
  color: #333;
}
.uv-unit {
  margin-left: 0.624rem;
  font-size: 14px;
  color: #333;
}
.input-price {
  font-size: 15px;
  border: none;
  outline: none;
  margin: 0;
  padding: 0;
  text-align: center;
}
.input-price::placeholder {
  font-size: 0.75rem;
  color: #999;
}
.quantity-value {
  font-size: 14px;
  width: 4.0625rem;
  text-align: center;
  border: none;
  background: transparent;
}
.quantity-btn {
  font-size: 0.875rem;
  border-radius: 0.25rem;
  width: 35px;
  height: 26px;
  line-height: 1.5rem;
  text-align: center;
}
.quantity-btn--decrease {
  background: #f3f6f8;
  color: #333;
}
.quantity-btn--increase {
  background: #3578fc;
  color: #fff;
}
.quantity-btn.disabled {
  opacity: 0.4;
}
.uv-submit-wrap {
  padding: 0.5rem 1.875rem;
}
.submit-btn {
  width: 100%;
  padding: 0.5rem 1.875rem;
  background: #3578fc;
  border-radius: 11px;
  border: none;
  box-sizing: border-box;
  font-family: PingFang;
  font-weight: 700;
  font-size: 0.875rem;
  color: #fff;
  text-align: center;
}

/* 求购 / 委托弹层 */
.popup-title {
  color: #333;
  font-size: 1rem;
  font-weight: 700;
  text-align: center;
}
.popup-content-uv {
  margin-top: 0.9375rem;
}
.popup-content-uv .currency-symbol {
  color: #333;
  font-size: 15px;
  padding: 0 0.9375rem;
}
.popup-content-uv .price-input-section {
  border-radius: 4px;
  border: 0.5px solid #333333;
  background-color: #fff;
  padding: 0 13px;
  box-sizing: border-box;
  height: 48px;
  margin: 11px auto 0;
  display: flex;
  justify-content: flex-start;
  align-items: center;
  max-width: 302px;
}
.popup-content-uv .input-price {
  text-align: left;
  width: 100%;
}
.popup-content-uv .input-price::placeholder {
  color: #999;
  font-size: 14px;
}
.high-price-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 17px;
  margin-left: 1.5625rem;
  margin-right: 1.5625rem;
  box-sizing: border-box;
}
.high-price-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  height: 27px;
  width: 302px;
  max-width: 100%;
  border-radius: 8px;
  background-color: rgb(243, 246, 248);
  padding: 0 8px 0 13px;
  box-sizing: border-box;
}
.high-price-label {
  font-size: 0.625rem;
  color: #666;
}
.high-price-value {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #333;
}
.high-price-fill img {
  width: 66px;
}
.quantity-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.quantity-section--total {
  margin-top: 2.34rem;
  background-color: #f9fbff;
  height: 5.2rem;
  padding: 0 1.5625rem;
}
.quantity-label {
  color: #666;
  font-size: 14px;
  font-weight: 500;
}
.quantity-controls {
  display: flex;
  align-items: center;
  height: 23px;
  border-radius: 5px;
  padding-left: 10px;
  padding-right: 10px;
}
.quantity-controls .quantity-btn {
  line-height: 1.5rem;
  text-align: center;
  font-size: 0.875rem;
  border-radius: 0.25rem;
  width: 35px;
  height: 26px;
}
.quantity-controls .increase {
  background-color: #3578fc;
  color: #fff;
}
.quantity-controls .decrease {
  background: #f3f6f8;
  color: #333;
}
.quantity-controls .quantity-value {
  font-size: 14px;
  width: 2.5rem;
  text-align: center;
  border: none;
  background: transparent;
}
.total-price {
  font-weight: 700;
  color: rgb(245, 69, 93);
  font-size: 16px;
}
.uv-pay-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 1.5625rem;
  background-color: rgba(53, 120, 252, 0.102);
  height: 5.2rem;
  border-radius: 0.9375rem;
  padding: 0 1.5625rem;
}
.uv-pay-box .uv-pay-check {
  width: 1.0312rem;
  height: 1.0312rem;
}
</style>