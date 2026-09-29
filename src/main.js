import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { i18n } from './i18n'
import App from './App.vue'
import './assets/styles/main.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.provide('i18n', i18n)
app.mount('#app')

// ── Dispositiu corporatiu (Hexnode): la URL de quiosc porta ?disp_corp=<clau>.
// Es canvia per un token HMAC (servidor) que es guarda i s'adjunta als fichatges:
// en dispositiu d'empresa, la denegació del GPS queda bloquejada.
;(async () => {
  const m = window.location.search.match(/[?&]disp_corp=([^&]+)/)
  if (!m) return
  try {
    const { db } = await import('./services/db')
    const r = await db.deviceEnroll(decodeURIComponent(m[1]))
    if (r?.disp) localStorage.setItem('crt_disp', r.disp)
  } catch (e) { /* clau invàlida o API antiga: es queda com a personal */ }
})()
