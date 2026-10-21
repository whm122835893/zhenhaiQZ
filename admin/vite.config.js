import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5174,
    host: true,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8921',
        changeOrigin: true,
        rewrite: p => p.replace(/^\/api/, ''),
      },
      // 藏品图等静态资源（/media/*）：dev 下转发给 API 静态根，生产由同域网关提供
      '/media': {
        target: 'http://127.0.0.1:8921',
        changeOrigin: true,
      },
    },
  },
})
