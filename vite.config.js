import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig(({ mode }) => ({
  base: '/',
  plugins: [
    vue()
  ],
  resolve: {
    alias: {
      '@': '/src'
    }
  },
  // Build web (`npm run build:web` → public/app): public/ és el docroot de Laravel
  // (index.php, build/...) i NO s'ha de copiar dins de public/app. app/public només té
  // els estàtics que fa servir el front (logos, icones, favicon).
  publicDir: mode === 'web' ? 'app/public' : 'public',
  build: {
    // We remove the problematic manualChunks for now to ensure a stable build
    chunkSizeWarningLimit: 2000
  }
}))
