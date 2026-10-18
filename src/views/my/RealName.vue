<!--
  实名认证页 —— 复刻原站 /pages/my/setting/realname
  原站分包：assets/pages-my-setting-realname.DXdvPXmX.js
  scopeId ：data-v-f18fc361
  导航模式：navigationBar style:"custom" 且 titleText 为空字符串 —— 页面自绘导航，
           完成态用 uv-navbar（autoBack / bgColor=rgba(0,0,0,0) / leftIconColor=#fff），
           表单态没有导航栏。故本页不使用 UniPageHead。

  刻意保留的原站瑕疵 / 细节：
  1. 表单态的 step 恒为 1，原站 next() / step2 分支是 DEAD 代码，不实现。
  2. 勾选图标 check 初始为 true 且没有任何方法能改它，点击无反应 —— 保持静态图标。
  3. 完成态的徽标图 /static/my/setting/rzbz.png 在原站就是 404（资源不存在），
     此处保留原路径，坏图状态与原站一致。
  4. navigateToPrivacy 原站写的是 config.frontUrl 但源码没 import config，
     运行时必抛 ReferenceError、点了没反应；此处改用项目 FRONT_URL
     （值与原站 config.frontUrl 完全相同：https://h5.shengtai.cyou），修掉了该 bug。
  5. 原站另有 next / faceOpen / confirm / hideModal / goBack / navTo 六个 DEAD 方法，不实现。
  6. 表单态大量沿用 colorui 类名（.cu-btn / .cu-bar / .text-df 等），原样保留不替换。
-->
<template>
  <!-- ========== 分支一：已实名（完成态） ========== -->
  <div v-if="account.hasRealName" class="realname-completed">
    <!-- 自绘返回栏，对应原站 uv-navbar autoBack bgColor="rgba(0,0,0,0)" leftIconColor="#fff" -->
    <div class="completed-navbar">
      <div class="completed-back" @click="router.back()">
        <span class="iconfont icon-right completed-back__arrow" />
      </div>
    </div>

    <div class="cert-wrap">
      <div class="cert-card">
        <div class="cert-card__row">
          <div class="cert-card__col">
            <span class="cert-label">姓名</span>
            <span class="cert-value">{{ account.realName }}</span>
          </div>
          <div class="cert-card__badge">
            <!-- 原站该图 404，坏图状态照抄；用变量绑定避免被 Vite 当成模块解析 -->
            <img class="cert-card__badge-icon" :src="certBadgeIcon" alt="" />
            <span class="cert-label">信息安全保障中</span>
          </div>
        </div>
        <div class="cert-card__col">
          <span class="cert-label">身份证</span>
          <span class="cert-value">{{ account.idCard }}</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== 分支二：未实名（表单态） ========== -->
  <div v-else class="set">
    <div :class="['margin-lr', { filters5: today }]" style="padding-top: 1.875rem">
      <div class="page-tit column flex flex-direction" style="height: auto">
        <span class="f-m text-black" style="font-size: 1.5rem">认证后即可参与购买</span>
        <span class="text-df margin-top text-darkgrey" style="font-weight: normal">
          <span class="text-red">*</span>请确认注册手机号与实名信息匹配
        </span>
      </div>

      <div class="item margin-top-xl">
        <span>已注册手机号</span>
        <span class="margin-left" style="color: #ccc">{{ account.account }}</span>
      </div>

      <div class="item margin-top-xl">
        <span>真实姓名</span>
        <input
          v-model="body.realName"
          class="margin-left flex-sub"
          placeholder="请输入真实姓名"
        />
      </div>

      <div class="item margin-top-xl">
        <span>身份证号</span>
        <input
          v-model="body.idCard"
          class="margin-left flex-sub"
          placeholder="请输入身份证号码"
        />
      </div>

      <div class="text-darkgrey margin-top">
        <!-- 原站 check 恒为 true 且无方法可改，点击无反应 -->
        <span
          :class="check ? 'cuIcon-roundcheckfill' : 'cuIcon-round'"
          :style="{ color: check ? '#4177f6' : '#aaa' }"
        />
        <span class="margin-left-xs text-df" style="font-size: 16px"
          >我已同意{{ appName }} <span class="text-blue" @click="navigateToPrivacy">《使用条款》</span></span
        >
      </div>
    </div>

    <div :class="['foot cu-bar shadow-blur padding-tb-sm bg-white', { filters5: today }]">
      <div class="diamond flex-sub bg-blue cu-btn lg" @click="updateRealname">
        <span class="text">实名认证</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { passportApi } from '@/api'
import { showToast, showLoading, hideLoading, FRONT_URL } from '@/api/request'

const router = useRouter()

const body = reactive({ realName: '', idCard: '' })
const account = ref({})
const check = ref(true)
const today = ref(false)
const appName = ref('平台')
/** 原站该图 404；用变量绑定让路径原样保留（字面量 src 会被 Vite 当模块解析导致构建失败） */
const certBadgeIcon = '/static/my/setting/rzbz.png'

/** 原站 getAccount(){ ... e.status ? account = e.data : e.code === 11111 && (account = {}) } */
function getAccount() {
  passportApi.getAccount().then((res) => {
    if (res.status) account.value = res.data
    else if (res.code === 11111) account.value = {}
  })
}

function updateRealname() {
  if (!body.realName) return showToast('姓名不能为空')
  if (!body.idCard) return showToast('身份证不能为空')
  if (body.idCard.length !== 18) return showToast('身份证需要18位')
  showLoading()
  passportApi
    .setRealName({ realName: body.realName, idCard: body.idCard })
    .then((res) => {
      hideLoading()
      if (res.status) {
        showToast({ icon: 'success', title: '实名认证成功' })
        getAccount()
      }
    })
    .catch(() => hideLoading())
}

/** 原站此处用 config.frontUrl 但未 import config（运行时 ReferenceError），
 *  复刻时改用项目 FRONT_URL —— 值与原站 config.frontUrl 完全相同，此处修掉了原站 bug */
function navigateToPrivacy() {
  router.push('/pages/index/webView?title=隐私政策&src=' + FRONT_URL + '/static/html/privacy.html')
}

onMounted(() => {
  getAccount()
})
</script>

<style scoped>
/* 以下为 realname-CpaiPZfP.css 中影响布局的主干，值原样抄（已是 rem，不做换算） */
.realname-completed{background-image:url('@/assets/my/realname-page-bg.png');background-repeat:no-repeat;background-size:100% auto;background-color:#f4f7fb;min-height:100vh}
.realname-completed .cert-wrap{padding-top:14.84375rem;display:flex;flex-direction:column;align-items:center}
.realname-completed .cert-card{width:20.15625rem;height:10.84375rem;padding:1.46875rem;box-sizing:border-box;background-image:url('@/assets/my/realname-card.png');background-size:100% 100%;display:flex;flex-direction:column;justify-content:space-between;font-size:.875rem}
.realname-completed .cert-card__row{display:flex;justify-content:space-between;align-items:flex-start}
.realname-completed .cert-card__badge{display:flex;align-items:center}
.realname-completed .cert-card__badge-icon{width:.96875rem;height:.96875rem}
.cert-label{font-size:.71875rem;color:#222}
.cert-value{font-size:.96875rem;color:#222;margin-top:.5rem}
.set{padding-top:1.25rem}
.page-tit{font-weight:700;color:#333}
.f-m{font-weight:700}
.column{flex-direction:column}
.flex-direction{flex-direction:column}
.filters5{filter:progid:DXImageTransform.Microsoft.BasicImage(grayscale=1);-webkit-filter:grayscale(100%)}
.diamond{background:inherit;margin-left:-1.25rem;margin-right:-1.25rem}
.item{margin-bottom:1.25rem;height:3.4375rem;border-radius:.375rem;background:#fff;display:flex;align-items:center;padding-left:.9375rem;padding-right:.9375rem}
.flex-sub{flex:1}
.margin-lr{margin-left:.9375rem;margin-right:.9375rem}
.margin-left{margin-left:.9375rem}
.margin-left-xs{margin-left:.3125rem}
.margin-top{margin-top:.9375rem}
.margin-top-xl{margin-top:1.5625rem}
.padding-tb-sm{padding-top:.625rem;padding-bottom:.625rem}
.shadow-blur{position:relative}
.text-black{color:#333}
.text-darkgrey{color:#bdbdbd}
.text-red{color:#dd3535}
.text-blue{color:#28d8ea}
.text-df{font-size:.9375rem}
.bg-blue{background-color:#3578fc;color:#fff}
.bg-white{background-color:#fff;color:#000}
.cu-btn{position:relative;border:0;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;padding:0 .9375rem;font-size:.875rem;height:2rem;line-height:1;text-align:center;text-decoration:none;overflow:visible;margin-left:0;transform:translate(0);margin-right:0}
.cu-btn:after{display:none}
.cu-btn.lg{padding:0 1.25rem;font-size:1.0625rem;height:2.8125rem}
.cu-bar{display:flex;position:relative;align-items:center;min-height:3.125rem;justify-content:space-between}
/* 原站实测 .foot 为 content-box（uv 组件的全局样式只给 uni-* 标签设 border-box，
   普通 div 仍是 content-box），故 padding-tb-sm 的上下 padding 会叠加进高度：
   52(min-height) + 10.4×2 = 72.8px。 */
.cu-bar.foot{position:fixed;padding-left:.9375rem;padding-right:.9375rem;left:0;right:0;bottom:0;z-index:870;box-shadow:0 -.03125rem .1875rem rgba(0,0,0,.1);box-sizing:content-box}
.text{color:inherit;font-size:1.0625rem}
.cuIcon-round{display:inline-block;width:1.0625rem;height:1.0625rem;border-radius:50%;border:.0625rem solid #aaa;box-sizing:border-box;position:relative;vertical-align:middle}
.cuIcon-roundcheckfill{display:inline-block;width:.875rem;height:.875rem;border-radius:50%;background:#4177f6;position:relative;vertical-align:middle}
.cuIcon-roundcheckfill:after{content:"✓";color:#fff;font-size:.5rem;line-height:.625rem;position:absolute;left:0;right:0;top:0;text-align:center}

/* 自绘返回栏（对应原站 uv-navbar autoBack / bgColor rgba(0,0,0,0) / leftIconColor #fff）：
   透明背景、白色返回箭头、绝对定位浮在背景图之上 */
.completed-navbar{position:absolute;top:0;left:0;right:0;height:2.75rem;z-index:10}
.completed-back{position:absolute;left:1.125rem;top:50%;transform:translateY(-50%);width:1.875rem;height:1.875rem;display:flex;align-items:center;justify-content:center}
.completed-back__arrow{color:#fff;font-size:1rem;transform:rotate(-45deg);display:inline-block}
</style>
