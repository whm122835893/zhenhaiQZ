<template>
  <div class="page-container">
    <!-- ============ HERO ============ -->
    <div class="hero">
      <img class="hero-fallback" :src="heroImg" alt="" />
      <div class="hero-mask" />

      <!-- 顶部栏 -->
      <div class="topbar">
        <div class="topbar-row">
          <div class="logo-badge">
            <img class="logo-whale" :src="logoWhale" alt="臻海" />
            <span class="logo-text">臻海</span>
          </div>
          <div class="action-icons">
            <div class="act-btn" @click="goSearch">
              <img class="act-icon" :src="iconSearch" alt="搜索" />
            </div>
            <div class="act-btn" @click="go2Notice">
              <img class="act-icon" :src="iconBell" alt="消息" />
              <i v-if="hasNotice" class="act-dot" />
            </div>
          </div>
        </div>
      </div>

      <div class="hero-title">
        <span class="hero-title-line">让热爱</span>
        <span class="hero-title-line">奔赴更大的世界</span>
      </div>
      <span class="hero-sub">数字艺术·收藏·交易·共建</span>

      <div class="hero-btn" @click="go2digitalAlbum">
        <span class="hero-btn-text">查看更多</span>
        <img class="hero-btn-arrow" :src="iconArrow" alt="" />
      </div>

      <div class="hero-note">
        <span class="hero-note-line">digital</span>
        <span class="hero-note-line">collectibles</span>
        <span class="hero-note-line">a more</span>
        <span class="hero-note-line">vast ocean</span>
      </div>
    </div>

    <!-- ============ 快捷入口 ============ -->
    <div class="quick-card">
      <div
        v-for="t in quickList"
        :key="t.id"
        class="quick-item"
        @click="goQuick(t)"
      >
        <div class="quick-icon-box">
          <img class="quick-icon" :src="t.icon" :alt="t.name" />
        </div>
        <span class="quick-label">{{ t.name }}</span>
      </div>
    </div>

    <!-- ============ 热门藏品 ============ -->
    <div class="section-head">
      <span class="section-title">热门藏品</span>
      <!-- 原站 nbGoHotMore() → go2digitalAlbum() → /pages/index/digitalAlbum（发售日历），非市场页 -->
      <div class="section-more" @click="go2HotMore">
        <span>查看更多</span>
        <img class="section-more-arrow" :src="iconArrowSmall" alt="" />
      </div>
    </div>

    <div class="hot-scroll">
      <div class="hot-scroll-inner">
        <div
          v-for="(t, i) in hotList"
          :key="i"
          class="hot-card"
          @click="go2ItemInfo(t)"
        >
          <div class="hot-cover">
            <img class="hot-cover-img" :src="t.imageUrl" :alt="t.name" />
            <span v-if="t.category && t.category.name" class="hot-tag">{{ t.category.name }}</span>
            <div class="hot-heart" @click.stop>
              <img
                class="hot-heart-icon"
                :src="heartIcon"
                alt=""
                :style="{ opacity: isCollected(t) ? 1 : 0.92 }"
              />
            </div>
          </div>
          <span class="hot-name one-line">{{ t.name }}</span>
          <span class="hot-price">{{ formatPrice(t.price) }}</span>
        </div>
      </div>
    </div>

    <!-- ============ 公告栏 ============ -->
    <div class="noticeBar">
      <div class="noticeBar-left flex-start">
        <img class="noticeBar-avatar" :src="noticeAvatar" alt="" />
        <div class="noticeBar-swiper">
          <div
            v-for="(t, i) in noticeList"
            :key="i"
            class="noticeBar-text one-line"
            @click="go2NoticeInfo(t.id)"
          >
            {{ t.title }}
          </div>
        </div>
      </div>
      <div class="noticeBar-more flex-start" @click="go2Notice">
        <span>更多</span>
        <img class="noticeBar-more-icon" :src="iconArrowSmall" alt="" />
      </div>
    </div>

    <!-- ============ 社区入口 ============ -->
    <div class="community" @click="go2Group">
      <img class="community-img" :src="communityImg" alt="" />
      <span class="community-title">加入臻海社区</span>
      <span class="community-sub">与更多热爱者同行</span>
      <img class="community-arrow" :src="iconArrowCircle" alt="" />
      <div class="community-note">
        <span class="community-note-line">ART</span>
        <span class="community-note-line">PEOPLE</span>
        <span class="community-note-line">INTO</span>
        <span class="community-note-line">THE OCEAN</span>
      </div>
    </div>

    <div class="page-bottom-space" />
  </div>
</template>

<script>
import { defineComponent } from 'vue'
import { itemApi, basicApi } from '@/api'
import { baseUrl } from '@/utils/path'
import { uni } from '@/utils/uni'
import heroImg from '@/assets/inline/hero-CTQK3Xwo.jpg'
import logoWhale from '@/assets/inline/logo-whale-_BKwMUpv.png'
import communityImg from '@/assets/inline/community-ACvN0fIf.png'
import heartIcon from '@/assets/inline/icon-heart-DdqqOq92.png'
import iconArrowCircle from '@/assets/inline/icon-arrow-circle-C6BBw9_N.png'
import iconSearch from '@/assets/inline/home-icon-0.png'
import iconBell from '@/assets/inline/home-icon-1.png'
import iconArrow from '@/assets/inline/home-icon-2.png'
import iconArrowSmall from '@/assets/inline/home-icon-4.png'
import noticeAvatar from '@/assets/inline/home-icon-3.png'
import qaInvite from '@/assets/tabbar/qa-invite.png'
import qaCompose from '@/assets/tabbar/qa-compose.png'
import qaDraw from '@/assets/tabbar/qa-draw.png'
import qaSwap from '@/assets/tabbar/qa-swap.png'
import qaAirdrop from '@/assets/tabbar/qa-airdrop.png'
import qaMore from '@/assets/tabbar/qa-more.png'

export default defineComponent({
  name: 'IndexPage',
  data() {
    return {
      heroImg,
      logoWhale,
      communityImg,
      heartIcon,
      iconArrowCircle,
      iconSearch,
      iconBell,
      iconArrow,
      iconArrowSmall,
      noticeAvatar,
      quickList: [
        { id: 'invite', name: '邀请好友', icon: qaInvite, path: '/pages/my/invite/index' },
        { id: 'compose', name: '合成', icon: qaCompose, path: '/pages/activity/index' },
        { id: 'draw', name: '抽签', icon: qaDraw, path: '/pages/activity/chouqian' },
        { id: 'swap', name: '分解', icon: qaSwap, path: '/pages/activity/decompose' },
        { id: 'airdrop', name: '空投', icon: qaAirdrop, path: '/pages/activity/baozha' },
        { id: 'more', name: '更多', icon: qaMore, path: '/pages/copyright/index' },
      ],
      hotList: [],
      noticeList: [],
      hasNotice: false,
    }
  },
  async created() {
    await Promise.all([this.fetchHot(), this.fetchNotice()])
  },
  methods: {
    async fetchHot() {
      const res = await itemApi.list({ pageNo: 1 })
      if (res && res.status && Array.isArray(res.data)) this.hotList = res.data
    },
    async fetchNotice() {
      const res = await basicApi.getNotice({ pageNo: 1 })
      if (res && res.status && Array.isArray(res.data)) {
        this.noticeList = res.data
        this.hasNotice = res.data.length > 0
      }
    },
    /** 原站价格以「分」返回，展示时换算为元，保留两位小数 */
    formatPrice(p) {
      if (p === null || p === undefined || p === '') return ''
      const n = Number(p)
      return isNaN(n) ? p : `￥${(n / 100).toFixed(2)}`
    },
    isCollected() { return false },
    goQuick(t) { uni.navigateTo({ url: t.path }) },
    go2ItemInfo(t) { uni.navigateTo({ url: `/pages/index/info?iid=${t.iid}` }) },
    go2Notice() { uni.navigateTo({ url: '/pages/notice/index' }) },
    go2NoticeInfo(id) { uni.navigateTo({ url: `/pages/notice/info?id=${id}` }) },
    /**
     * 原站 nbGoHotMore(){ this.go2digitalAlbum() }
     * go2digitalAlbum(){ uni.navigateTo({ url: '/pages/index/digitalAlbum' }) }
     * 即「查看更多」进的是发售日历页，不是市场页
     */
    go2HotMore() { this.$router.push('/pages/index/digitalAlbum') },
    goSearch() { uni.navigateTo({ url: '/pages/notice/search' }) },
    go2digitalAlbum() { uni.navigateTo({ url: '/pages/index/digitalAlbum' }) },
    /**
     * 原站 go2Group(){ uni.navigateTo({ url:`/pages/index/webView?title=社群&src=${frontUrl}/static/html/shequn.html` }) }
     * 「加入臻海社区」进的是社群 webView 页，不是版权页
     */
    go2Group() {
      uni.navigateTo({
        url: `/pages/index/webView?title=${encodeURIComponent('社群')}&src=${baseUrl('/static/html/shequn.html')}`,
      })
    },
  },
})
</script>

<style scoped>
/*
  单位基准与原站完全一致：
  原站 uni-app 使用 rem，1rem = 100vw / 23.4375（390px 视口下 = 16.64px）
  以下 rem 数值直接取自原站 pages-index-index 样式表，未做换算或改动。
*/
.page-container { position: relative; min-height: 100%; background-color: #e5f2fb; }
.flex-start { display: flex; align-items: center; }
.one-line { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

/* ---------- HERO ---------- */
.hero { position: relative; z-index: 1; width: 100%; height: 20.78125rem; overflow: hidden; }
.hero-fallback { position: absolute; left: 0; top: 0; width: 100%; height: 20.78125rem; z-index: 1; }
.hero-mask {
  position: absolute; left: 0; top: 0; width: 100%; height: 20.78125rem; z-index: 3;
  background: linear-gradient(180deg, rgba(229, 242, 251, 0) 85%, #e5f2fb);
}
.topbar {
  position: absolute; left: 0; right: 0; top: 1.875rem; z-index: 20;
  padding-top: var(--status-bar-height, 0px);
}
.topbar-row {
  display: flex; align-items: center; justify-content: space-between;
  height: 1.90625rem; margin-top: -0.25rem; padding: 0 0.96875rem;
}
.logo-badge { display: flex; align-items: center; }
.logo-whale { width: 2.15625rem; height: 1.75rem; display: block; }
.logo-text {
  margin-left: 0.375rem;
  font-family: 'PingFangBold', 'PingFang SC', sans-serif;
  font-size: 1.3125rem; font-weight: 700; color: #fff; line-height: 1;
}
.action-icons { display: flex; align-items: center; }
.act-btn {
  position: relative; display: flex; align-items: center; justify-content: center;
  width: 1.90625rem; height: 1.90625rem; border-radius: 50%;
}
.act-btn + .act-btn { margin-left: 0.53125rem; }
.act-icon { width: 0.96875rem; height: 0.96875rem; display: block; }
.act-dot {
  position: absolute; top: 0.21875rem; right: 0.1875rem;
  width: 0.4375rem; height: 0.4375rem; border-radius: 50%; background-color: #ff3422;
}
.hero-title { position: absolute; left: 1.6875rem; top: 7.78125rem; z-index: 10; width: 10.75rem; }
.hero-title-line {
  display: block;
  font-family: 'STZhongsong', 'Songti SC', 'SimSun', 'Source Han Serif SC', 'Noto Serif SC', serif;
  font-size: 1.5rem; line-height: 126.27%; color: #fff;
}
.hero-sub {
  position: absolute; left: 1.6875rem; top: 11.78125rem; z-index: 10;
  font-size: 0.59375rem; color: #c5dcec;
}
.hero-btn {
  position: absolute; left: 1.5625rem; top: 13.53125rem; z-index: 10;
  display: flex; align-items: center; justify-content: center;
  width: 5.78125rem; height: 1.75rem; border-radius: 0.875rem;
  background: linear-gradient(135deg, #a4bfda, #fff);
  box-shadow: inset 0 0 0.53125rem #fff;
}
.hero-btn-text {
  font-family: 'PingFangBold', 'PingFang SC', sans-serif;
  font-size: 0.65625rem; font-weight: 700; color: #0e1838; line-height: 1;
}
.hero-btn-arrow { width: 0.71875rem; height: 0.71875rem; margin-left: 0.0625rem; display: block; }
.hero-note {
  position: absolute; left: 19.59375rem; top: 5.21875rem; z-index: 10;
  width: 2.9375rem; text-align: right;
}
.hero-note-line {
  display: block;
  font-family: 'Source Serif Pro', 'Times New Roman', serif;
  font-size: 0.3125rem; line-height: 169%; color: #c5dcec;
}

/* ---------- 快捷入口 ---------- */
.quick-card {
  position: relative; z-index: 5;
  display: flex; align-items: center; justify-content: space-between;
  box-sizing: border-box; width: 22.375rem; height: 5.1875rem;
  margin: -3.3125rem auto 0; padding: 1.0625rem 0.71875rem;
  border-radius: 0.90625rem;
  background-color: rgba(255, 255, 255, 0.78);
  border: 0.03125rem solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 0.5rem 1rem rgba(31, 59, 104, 0.04);
  backdrop-filter: blur(0.9375rem);
}
.quick-item { display: flex; flex-direction: column; align-items: center; width: 3.15625rem; }
.quick-icon-box {
  display: flex; align-items: center; justify-content: center;
  width: 2.3125rem; height: 2.3125rem; border-radius: 0.59375rem;
  background: radial-gradient(circle at 80% 90%, #fff, #d8e7f6);
  border: 0.03125rem solid #ffffff;
  box-shadow: 0 0.25rem 0.5rem rgba(63, 140, 255, 0.08);
}
.quick-icon { width: 1.78125rem; height: 1.78125rem; display: block; }
.quick-label {
  margin-top: 0.1875rem;
  font-family: 'PingFang', 'PingFang SC', sans-serif;
  font-size: 0.53125rem; color: #0e1838; line-height: 1.2;
}

/* ---------- 区块标题 ---------- */
.section-head {
  display: flex; align-items: center; justify-content: space-between;
  height: 1.4375rem; margin: 1.03125rem 0.96875rem 0;
}
.section-title {
  font-family: 'PingFangBold', 'PingFang SC', sans-serif;
  font-size: 1rem; font-weight: 700; color: #0e1838; line-height: 1;
}
.section-more { display: flex; align-items: center; font-size: 0.71875rem; color: #7e8b9b; line-height: 1; }
.section-more-arrow { width: 0.71875rem; height: 0.71875rem; margin-left: 0.0625rem; display: block; }

/* ---------- 热门藏品横滑 ---------- */
.hot-scroll { width: 100%; white-space: nowrap; padding: 0.46875rem 0 0.1875rem; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.hot-scroll::-webkit-scrollbar { display: none; height: 0; }
.hot-scroll-inner { display: inline-block; padding: 0 0.46875rem 0 1rem; }
.hot-card {
  position: relative; display: inline-block; vertical-align: top;
  width: 6.8125rem; margin-right: 0.53125rem; border-radius: 0.34375rem;
  background-color: #f5f9fc;
  box-shadow: 0 0 0.125rem rgba(45, 89, 188, 0.25);
  overflow: hidden; white-space: normal;
}
.hot-card:last-child { margin-right: 0; }
.hot-cover { position: relative; width: 6.8125rem; height: 6.90625rem; overflow: hidden; }
.hot-cover-img { width: 100%; height: 100%; display: block; }
.hot-tag {
  position: absolute; left: 0.25rem; top: 0.1875rem;
  width: 2.75rem; height: 1.03125rem; border-radius: 0.46875rem;
  background: linear-gradient(90deg, rgba(14, 24, 56, 0.42), #0e1838 65%);
  box-shadow: inset 0 -0.0625rem 0.1875rem rgba(255, 255, 255, 0.6);
  font-family: 'PingFang', 'PingFang SC', sans-serif;
  font-size: 0.59375rem; line-height: 1.03125rem; color: #e1e9fa; text-align: center;
}
.hot-heart { position: absolute; right: 0.25rem; top: 0.125rem; width: 1.1875rem; height: 1.25rem; }
.hot-heart-icon { width: 100%; height: 100%; display: block; }
.hot-name {
  display: block;
  margin: 0.25rem 0 0 0.46875rem;
  font-family: 'PingFang', 'PingFang SC', sans-serif;
  font-size: 0.71875rem; color: #0d0f38; line-height: 1.4;
}
.hot-price {
  display: block;
  margin: 0.1875rem 0 0 0.3125rem;
  font-family: 'DIN Alternate', 'DINPro', 'PingFang SC', sans-serif;
  font-size: 0.84375rem; font-weight: 700; color: #0c0c28; line-height: 1.3;
}

/* ---------- 公告栏 ---------- */
.noticeBar {
  box-sizing: border-box; display: flex; align-items: center; justify-content: space-between;
  padding: 0.625rem 0.75rem; margin: 0.75rem;
  border-radius: 0.90625rem;
  background-color: rgba(255, 255, 255, 0.78);
  border: 0.03125rem solid rgba(255, 255, 255, 0.5);
  box-shadow: 0 0.5rem 1rem rgba(31, 59, 104, 0.04);
  backdrop-filter: blur(0.9375rem);
}
.noticeBar-left { flex: 1; min-width: 0; }
.noticeBar-avatar { flex-shrink: 0; width: 1.375rem; height: 1.375rem; border-radius: 50%; }
.noticeBar-swiper { flex: 1; min-width: 0; height: 1.125rem; margin-left: 0.5rem; overflow: hidden; }
.noticeBar-text { display: block; font-size: 0.75rem; color: rgba(0, 0, 0, 0.9); line-height: 1.125rem; }
.noticeBar-more { flex-shrink: 0; margin-left: 0.5rem; font-size: 0.75rem; color: #666; }
.noticeBar-more-icon { width: 1rem; height: 1.03125rem; margin-left: 0.0625rem; }

/* ---------- 社区 ---------- */
.community {
  position: relative; width: 21.5625rem; height: 6.34375rem;
  margin: 0.90625rem auto 0; border-radius: 0.65625rem; overflow: hidden;
}
.community-img { position: absolute; left: 0.03125rem; top: 0; width: 21.5625rem; height: 7.25rem; }
.community-title {
  position: absolute; left: 1.0625rem; top: 1.65625rem;
  font-family: 'STZhongsong', 'Songti SC', 'SimSun', serif;
  font-size: 1rem; color: #fff; line-height: 1.15;
}
.community-sub {
  position: absolute; left: 0.96875rem; top: 3.34375rem;
  font-family: 'STZhongsong', 'Songti SC', 'SimSun', serif;
  font-size: 0.71875rem; color: #c5dcec; line-height: 1.2;
}
.community-arrow { position: absolute; left: 7.15625rem; top: 3.46875rem; width: 1.28125rem; height: 1.28125rem; }
.community-note { position: absolute; right: 0.96875rem; top: 1.59375rem; width: 2.6875rem; text-align: right; }
.community-note-line {
  display: block;
  font-family: 'Source Serif Pro', 'Times New Roman', serif;
  font-size: 0.5rem; line-height: 1.7; color: #c5dcec;
}

/* 底部留白：避开 tabbar */
.page-bottom-space { height: 5.5rem; }
</style>