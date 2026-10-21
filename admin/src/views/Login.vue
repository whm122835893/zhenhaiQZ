<template>
  <div class="login-stage">
    <span class="bubble" style="width:120px;height:120px;left:16%;top:18%"></span>
    <span class="bubble" style="width:64px;height:64px;left:30%;bottom:20%;animation-delay:-3s"></span>
    <span class="bubble" style="width:88px;height:88px;right:18%;top:24%;animation-delay:-5s"></span>
    <span class="bubble" style="width:150px;height:150px;right:12%;bottom:14%;animation-delay:-7s"></span>

    <form class="glass login-card pop" @submit.prevent="submit">
      <div class="login-brand">
        <div class="logo">臻</div>
        <h1>臻海艺术</h1>
        <p>ZHENHAI ART ADMIN</p>
      </div>

      <div class="login-field">
        <input v-model.trim="form.username" class="input" placeholder="管理员账号" autocomplete="username" />
      </div>
      <div class="login-field">
        <input v-model="form.password" class="input" type="password" placeholder="登录密码" autocomplete="current-password" />
      </div>

      <button class="btn btn-primary login-btn" :disabled="loading || !form.username || !form.password">
        <span v-if="loading" class="spinner" style="width:16px;height:16px;border-width:2px"></span>
        <template v-else>登 录</template>
      </button>

      <div class="login-tip">仅限授权管理人员访问 · 操作将全程留痕审计</div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import http, { toast, setToken, setProfile } from '../api'

const router = useRouter()
const form = reactive({ username: '', password: '' })
const loading = ref(false)

async function submit() {
  if (loading.value) return
  loading.value = true
  try {
    const data = await http.post('/auth/login', form)
    setToken(data.token)
    setProfile(data.profile)
    toast(`欢迎回来，${data.profile.realName || data.profile.username}`)
    router.replace('/dashboard')
  } catch {
    /* 拦截器已 toast */
  } finally {
    loading.value = false
  }
}
</script>
