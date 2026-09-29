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
  // Sense cap PostCSS: el front no fa servir Tailwind i així no hereta el postcss.config
  // de l'arrel (que el necessita per a resources/js).
  css: {
    postcss: {}
  },
  build: {
    // We remove the problematic manualChunks for now to ensure a stable build
    chunkSizeWarningLimit: 2000
  }
})
