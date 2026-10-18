import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { viteSingleFile } from 'vite-plugin-singlefile'
import path from 'path'

export default defineConfig({
  base: './',
  plugins: [
    vue(),
    // 打包为单个自包含 HTML，便于手机直接打开
    viteSingleFile({
      removeViteModuleLoader: true,
      inlinePattern: /\.js$|\.css$|images\/.*\.png|jpe?g|webp/,
    }),
  ],
  resolve: {
    alias: { '@': path.resolve(__dirname, 'src') },
  },
  assetsInclude: ['**/*.png', '**/*.jpg', '**/*.jpeg', '**/*.webp'],
  build: {
    assetsInlineLimit: 1024 * 1024 * 20,
    cssCodeSplit: false,
    rollupOptions: { output: { inlineDynamicImports: true } },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      // 本地后端代理：前端 BASE_URL 走 '/api'，开发期由 vite 转发，
      // 完全规避跨域（生产环境后端与前端同域或由网关处理）。
      //
      // 改 path 时注意：后端路由表是按 api.shengtai.cyou 的真实路径定义的
      // （如 /item、/order/{id}），没有 /api 前缀，所以这里要 rewrite 掉。
      '/api': {
        target: 'http://127.0.0.1:8921',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/api/, ''),
      },
    },
  },
})