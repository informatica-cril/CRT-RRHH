import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  base: '/',
  plugins: [
    vue()
  ],
  resolve: {
    alias: {
      '@': '/src'
    }
  },
  build: {
    // We remove the problematic manualChunks for now to ensure a stable build
    chunkSizeWarningLimit: 2000
  }
})
