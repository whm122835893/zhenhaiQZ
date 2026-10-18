<!--
  注销账号页 —— 复刻原站 /pages/my/setting/delete
  原站分包：assets/pages-my-setting-delete.B7gbIW2E.js
  scopeId ：data-v-e1f0a6ff
  导航标题：注销账号（uni-app 内置导航栏，背景 #fff、标题色 #000）

  刻意保留的原站瑕疵 / 细节：
  1. 「注销账号」实际调用的是 passportApi.signout()（POST /passport/signout），
     不是 /passport/delete —— 这是原站的 BUG，实测抓包确认，故照抄不"修正"。
  2. 勾选框图标 icon-check 在原站全站没有字形定义，勾选后只显示蓝圆点、没有对勾。
  3. 原站模板写的是 <checkbox> 组件，但 uni-app H5 编译产物是
     <uni-view class="checkbox">，故此处用 div + 同名 class 复刻。
  4. 禁用态类名是 .delete-button.disabled（不是 setting 页那种 .checked）。
  5. 本页在原站没有任何入口可达（setting 页的 go2Delete 是 DEAD 方法）。
  6. showModal 的按钮文案是 uni 默认英文 Cancel / OK；本项目无 modal 封装，
     沿用项目内既有做法 window.confirm，正文文案（提示 / 确定要注销账号吗？）照抄。
-->
<template>
  <div class="uni-page-body">
    <UniPageHead title="注销账号" />

    <div class="delete-container">
      <div class="delete-notice">
        <div class="notice-title">在您开始注销流程时，请确保您已知下列注意事项:</div>
        <div class="notice-item">
          <span class="item-number">1、</span>
          <span class="item-content">账号注销是不可逆的操作，一旦注销成功，则个人资料、头像、购买记录等数据都会清空且不可重新获取，您将在半年内无法通过该手机号再次注册登录和使用本App的服务。如您需要通过其他方式访问数字藏品的，请联系官方客服进行验证和获取协助。</span>
        </div>
        <div class="notice-item">
          <span class="item-number">2、</span>
          <span class="item-content">请确保您账号下的藏品已全部处理完毕/钱包内无余额/无未完结的交易等，若涉及到资产问题账号将无法进入注销流程。</span>
        </div>
        <div class="notice-item">
          <span class="item-number">3、</span>
          <span class="item-content">为了保护您的权益，注销账号需是实名用户，若未实名请先实名认证，设置密码后进行注销操作。</span>
        </div>
      </div>

      <div class="agreement">
        <div class="checkbox" :class="['checkbox', { checked: agreed }]" @click="toggleAgreement">
          <!-- 原站 icon-check 无字形，勾选后只有蓝圆点 -->
          <span v-if="agreed" class="iconfont icon-check" />
        </div>
        <span class="agreement-text">我已同意【注销条款】</span>
      </div>

      <div class="delete-button" :class="['delete-button', { disabled: !agreed }]" @click="deleteAccount">
        <span>注销账号</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { passportApi } from '@/api'
import { showToast } from '@/api/request'
import UniPageHead from '@/components/UniPageHead.vue'

const router = useRouter()

const agreed = ref(false)

function toggleAgreement() {
  agreed.value = !agreed.value
}

function deleteAccount() {
  if (!agreed.value) return showToast('请先同意注销条款')
  // 原站 uni.showModal({ title:'提示', content:'确定要注销账号吗？', confirmColor:'#4478ff' })
  if (!window.confirm('确定要注销账号吗？')) return
  // 注意：原站这里发的是 POST /passport/signout（原站 BUG，实测抓包确认），照抄
  passportApi.signout().then((res) => {
    if (res.status) {
      showToast('注销成功')
      router.replace('/pages/index/index')
    }
  })
}
</script>

<style scoped>
/* 原站页面底色（body #fff） */
.uni-page-body {
  /* 原站链路：html,body,uni-page-body{height:100%}，且有 uni-page-head 时
     uni-page-wrapper 高度 = calc(100% - 44px)，故页面 body 可用高度 = 视口高 - 44px。
     本地 uni-page-body 内含 UniPageHead 的 44px 占位，等价写法如下；
     必须用 height 而非 min-height，内部容器的 min-height/height:100% 才有参照。 */
  height: calc(100vh - 44px);
  background-color: #fff;
}

/* 以下为 delete-L8oECL4F.css 原文，值原样抄（已是 rem，不做换算；真 px 保留） */
.delete-container{height:100%;background-color:#fff;padding:1.25rem .9375rem;box-sizing:border-box}
.delete-notice{background-color:#fff;border-radius:.625rem;padding:.9375rem;box-shadow:0 .125rem .5rem rgba(0,0,0,.1);margin-bottom:1.25rem}
.notice-title{font-size:.9375rem;font-weight:700;color:#333;margin-bottom:.9375rem}
.notice-item{display:flex;margin-bottom:.625rem}
.item-number{font-size:.875rem;color:#333;flex-shrink:0}
.item-content{font-size:.875rem;color:#333;line-height:1.6}
.agreement{display:flex;align-items:center;margin:1.875rem 0}
.checkbox{width:1.25rem;height:1.25rem;border-radius:50%;border:1px solid #CCCCCC;margin-right:.625rem;display:flex;justify-content:center;align-items:center;box-sizing:content-box}
.checkbox.checked{background-color:#4478ff;border-color:#4478ff}
.icon-check{color:#fff;font-size:.875rem}
.agreement-text{font-size:.875rem;color:#333}
.delete-button{height:2.8125rem;display:flex;justify-content:center;align-items:center;background-color:#4478ff;border-radius:1.40625rem;font-size:.9375rem;color:#fff;box-shadow:0 .125rem .5rem rgba(0,0,0,.1)}
.delete-button.disabled{background-color:#ccc}
</style>
