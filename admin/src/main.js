import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { loadBrand } from './api'
import './style.css'

// 启动即拉平台品牌（公开接口，登录页/侧边栏动态显示）
loadBrand()

createApp(App).use(router).mount('#app')
