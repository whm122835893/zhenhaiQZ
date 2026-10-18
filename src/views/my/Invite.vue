<!--
  邀请好友页 —— 复刻原站 /pages/my/invite/index
  原站分包：assets/pages-my-invite-index.B0xLGhMu.js
  样式文件：assets/index-CvLrEdDn.css
  scopeId ：data-v-8364ff38
  导航标题：邀请好友（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. `.downBtns.center` 是空 div，模板里没有任何内容，只占布局位置；原样保留、不删。
  2. `.font-DINAB` 原站声明 font-family:DINAB，但该字体全站未定义，静默降级为默认字体；
     class 与声明原样保留。
  3. CSS 里原站存在两条 .appBtn 规则，但模板从未引用，本页不写（避免无用样式）。
  4. getInviteList 里 hideLoading 的写法原站是：
       isRefresh ? (isRefresh && showLoading 已被前面的 if 拦截) ... hideLoading() : hideLoading()
     即 if (isRefresh) hideLoading() else hideLoading() —— 两条分支效果相同，属原站冗余，照抄。
  5. 原站 onLoad 触发 getProfile + getInviteList，本项目对应 onMounted。
  6. 下拉刷新：原站是 uni 的 onPullDownRefresh；本项目无对应交互，按项目既有约定
     （见 MarketLog.vue / MarketTrustIndex.vue）暴露同名方法，行为一致：
     finished=false → pageNo=1 → getInviteList(true)。
  7. 内联 rpx 已按 1rpx = 0.03125rem 换算：30rpx→.9375rem。
  8. timeFormat 原站传的是大写 'YYYY-MM-DD'（dayjs 里大写 DD 为日）；本项目
     @/utils/fmt 的映射表中年份键为小写 yyyy（大写 YYYY 不会被替换），故传
     'yyyy-MM-DD' 以保证渲染结果与原站一致（2026-10-16）。
-->
<template>
  <!-- 页面底色：原站 uni-page-body 与 body 同为 #f6f7fb -->
  <div class="uni-page-body">
    <UniPageHead title="邀请好友" />

    <div class="containerStyle">
      <div class="userInfoBox">
        <div class="numCodeBox centerBetween">
          <div class="columnCenter font-DINAB font-fff-48-bold">
            <text>{{ inviteQuantity }}</text>
            <span class="font-fff-24">已邀请好友</span>
          </div>
          <div class="myCode center font-fff-24-500" @click="copyInviteCode">
            <text>我的邀请码：{{ profile.inviteCode || '---' }}</text>
            <img class="copy" src="../../assets/my/invite-copy.png" alt="" />
          </div>
        </div>

        <div class="btns centerBetween">
          <div class="btn" @click="copyInviteUrl">立即邀请</div>
          <div class="btn ml20" @click="copyInviteUrl">复制链接</div>
        </div>
      </div>

      <div class="centerBetween navName">
        <div class="font-333-32-bold">邀请记录</div>
        <!-- 原站此处是空 div，仅占位，保留结构 -->
        <div class="downBtns center"></div>
      </div>

      <div v-if="list.length > 0" class="listBox columnCenter">
        <div
          v-for="(item, index) in list"
          :key="item.id"
          class="itemView centerBetween"
        >
          <div class="userInfo center">
            <div class="icon"></div>
            <div class="nameTime column">
              <div class="name font-333-28-bold">{{ item.phone }}</div>
              <div class="timeState">
                <!-- 原站这里是 <text> 组件（编译为 inline 的 uni-text），
                     本地用 span 等价还原，避免 div 块级撑满导致宽度偏大 -->
                <span class="time font-999-24">
                  {{ timeFormat(item.createTime, 'yyyy-MM-DD') }}
                </span>
              </div>
            </div>
          </div>
          <span v-if="item.hasRealName" class="state font-000-24">已实名</span>
          <span v-else class="state font-999-24">未实名</span>
        </div>
        <div class="load-txt">
          <div class="font-999-24">{{ finished ? '没有更多了' : '' }}</div>
        </div>
      </div>

      <div v-else class="listBox columnDoubleCenter">
        <!-- 原站此处引用 newNodata 组件内的 base64 图，改为本地静态资源 -->
        <img
          src="../../assets/my/newNodata.png"
          alt=""
          style="width: 138px; height: 138px"
        />
        <div class="font-999-24" style="margin-top: .9375rem">暂无数据</div>
      </div>
    </div>
  </div>
</template>

<script setup>
/**
 * 邀请好友 —— 复刻原站 pages-my-invite-index.B0xLGhMu.js（scopeId data-v-8364ff38）
 */
import { ref, reactive, onMounted } from 'vue'
import { userApi } from '@/api'
import { showToast, showLoading, hideLoading, copy, FRONT_URL } from '@/api/request'
import { timeFormat } from '@/utils/fmt'
import UniPageHead from '@/components/UniPageHead.vue'

const profile = ref({})
const finished = ref(false)
const query = reactive({ pageNo: 1 })
const list = ref([])
const inviteQuantity = ref(0)

/** 原站 getProfile(){ ... res.status ? profile = res.data : res.code === 11111 && (profile = {}) } */
function getProfile() {
  userApi.getProfile().then((res) => {
    if (res.status) profile.value = res.data
    else if (res.code === 11111) profile.value = {}
  })
}

/** 原站 copyInviteCode()：无邀请码先提示登录 */
function copyInviteCode() {
  if (!profile.value.inviteCode) return showToast('请先登录')
  copy(profile.value.inviteCode)
}

/** 原站 copyInviteUrl()：拼接带 inviteCode 的注册链接 */
function copyInviteUrl() {
  if (!profile.value.inviteCode) return showToast('请先登录')
  copy(FRONT_URL + '/pages/authorize/signup?inviteCode=' + profile.value.inviteCode)
}

/** 原站 getInviteList(e, t)：e=isRefresh，t=isAppend；userApi.listInvite → GET /user/invite */
function getInviteList(isRefresh, isAppend) {
  if (!isRefresh) showLoading()
  userApi.listInvite(query).then((res) => {
    if (isRefresh) hideLoading()
    else hideLoading()
    if (res.status) {
      const rows = res.data.list
      inviteQuantity.value = res.data.total
      list.value = isAppend ? [...list.value, ...rows] : rows
      if (rows.length === 0) finished.value = true
    } else if (res.code === 11111) {
      finished.value = true
    }
  })
}

/** 原站 onPullDownRefresh */
function onPullDownRefresh() {
  finished.value = false
  query.pageNo = 1
  getInviteList(true)
}

/** 原站 onReachBottom */
function onReachBottom() {
  if (finished.value) return
  query.pageNo += 1
  getInviteList(false, true)
}

// 原站 onLoad
onMounted(() => {
  getProfile()
  getInviteList()
})

// 暴露给列表容器（scroll-view / 下拉刷新容器）使用
defineExpose({ onPullDownRefresh, onReachBottom })
</script>

<style scoped>
/* 页面底色：原站 uni-page-body 与 body 同为 #f6f7fb */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #f6f7fb;
}

/* 以下为 index-CvLrEdDn.css，原样抄（已是 rem 值，不再换算）。
   仅背景图的相对路径需按本项目文件位置（src/views/my/）上调一级。 */
.containerStyle{position:relative;width:100%;display:flex;flex-direction:column;align-items:center;min-height:100vh}
.centerBetween{width:100%;display:flex;flex-direction:row;align-items:center;justify-content:space-between}
.columnCenter{display:flex;flex-direction:column;align-items:center}
.center{display:flex;flex-direction:row;align-items:center}
.doubleCenter{display:flex;flex-direction:row;align-items:center;justify-content:center}
.columnDoubleCenter{display:flex;flex-direction:column;align-items:center;justify-content:center}
.column{display:flex;flex-direction:column}
.ml20{margin-left:.625rem}
.font-fff-48-bold{color:#fff;font-size:1.5rem;font-weight:700}
.font-fff-24{color:#fff;font-size:.75rem}
.font-fff-24-500{color:#fff;font-size:.75rem;font-weight:500}
.font-333-32-bold{color:#333;font-size:1rem;font-weight:700}
.font-333-28-bold{color:#333;font-size:.875rem;font-weight:700}
.font-999-24{color:#999;font-size:.75rem}
.font-000-24{color:#000;font-size:.75rem}
/* 原站声明 font-family:DINAB，该字体全站未定义，静默降级 */
.font-DINAB{font-family:DINAB}
.userInfoBox{margin:.625rem auto 1rem;border-radius:.5rem;padding:1.25rem 1.125rem .625rem;width:21.4375rem;height:8.375rem;box-sizing:border-box;background:url(../../assets/my/invite-card-bg.jpg) no-repeat;background-size:100% 100%}
.userInfoBox .copy{margin-left:.25rem;width:.875rem;height:.875rem}
.userInfoBox .btns{margin:1.3125rem auto 0}
.userInfoBox .btns .btn{width:9.25rem;height:2.0625rem;display:inline-block;line-height:2.0625rem;text-align:center;font-size:.75rem;color:#fff;border-radius:.5rem;border:.0625rem solid #fff;box-sizing:border-box}
.navName{margin:0 auto;width:calc(100% - 2rem)}
.listBox{margin:.625rem auto 14vh auto;background:#fff;border-radius:.9375rem;min-height:55vh;width:21.6875rem;padding:0 1rem;box-sizing:border-box}
.listBox .load-txt{padding-bottom:.625rem}
.listBox .itemView{width:19.125rem;padding:.875rem 0 .75rem;border-bottom:.0625rem solid #f8f8f8}
.listBox .itemView .icon{margin-right:.5625rem;width:2.3125rem;height:2.3125rem;border-radius:50%;border:.0625rem solid #e4e4e4;box-sizing:border-box;background:url(../../assets/my/logo.jpg);background-size:100% 100%;background-repeat:no-repeat}
.listBox .itemView .nameTime{flex:1}
.listBox .itemView .nameTime .name{margin-bottom:.625rem}
.listBox .itemView:last-child{border:0}
</style>