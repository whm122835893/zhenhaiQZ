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
      // BuildAdmin 后端（server/buildadmin，php think run 8787）。
      // C 端接口本身就带 /api 前缀（/api/nft_collection/index），无需 rewrite。
      '/api': {
        target: 'http://127.0.0.1:8787',
        changeOrigin: true,
      },
      // 上传静态资源（藏品封面 / 轮播图 /storage/...，BuildAdmin 上传约定）：
      // dev 下转发给后端，生产由网关（dist-server.cjs）/storage 反代。
      // ⚠️ 不代理 /static：前后端都有 /static（C 端 model3d 等 10 个目录 vs
      // BuildAdmin 管理后台静态），vite 代理优先于 public 目录，整段代理会
      // 劫持 C 端 3D 播放器。后端 /static 只服务管理后台，管理后台直连后端
      // 源（8787），无需经过前端源。也不代理 /media：后端无此目录。
      '/storage': {
        target: 'http://127.0.0.1:8787',
        changeOrigin: true,
      },
    },
  },
})