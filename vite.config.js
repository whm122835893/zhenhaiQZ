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
      // 后台静态资源（藏品封面 / 轮播图 /storage/...）：dev 下转发给后端，
      // 生产由 nginx 同域反代 /api、/storage 到 server/buildadmin。
      '/storage': {
        target: 'http://127.0.0.1:8787',
        changeOrigin: true,
      },
    },
  },
})