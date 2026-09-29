<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Les meves aplicacions</h1>
        <p class="page-subtitle">Entra amb un sol clic a les apps que tens concedides</p>
      </div>
    </div>

    <div v-if="loading" class="card" style="text-align:center;padding:40px;color:var(--color-text-secondary);">
      Carregant les teves aplicacions…
    </div>

    <div v-else-if="apps.length === 0" class="card" style="text-align:center;padding:48px 24px;">
      <div style="font-size:2.6rem;margin-bottom:12px;">🗝</div>
      <p style="font-weight:700;margin-bottom:6px;">Encara no tens cap aplicació concedida</p>
      <p style="color:var(--color-text-secondary);">Direcció o RRHH han d'activar-te l'accés. Quan ho facin, les targetes sortiran aquí.</p>
    </div>

    <!-- Tiles grans i tàctils: la pantalla de referència és la tablet Zeno 5 (11"). -->
    <div v-else class="portal-grid">
      <button v-for="a in apps" :key="a.app" class="portal-tile" :disabled="saltant === a.app" @click="obre(a)">
        <span class="portal-tile-icon" aria-hidden="true">
          <svg v-if="a.app === 'domi'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/></svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        </span>
        <span class="portal-tile-body">
          <span class="portal-tile-nom">{{ a.nom }}</span>
          <span class="portal-tile-desc">{{ a.descripcio }}</span>
        </span>
        <span class="portal-tile-cta">{{ saltant === a.app ? 'Obrint…' : 'Entrar →' }}</span>
      </button>
    </div>

    <p v-if="error" class="portal-error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { apiRequest } from '../services/apiClient'

const apps = ref([])
const loading = ref(true)
const saltant = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    const r = await apiRequest('/v1/portal/apps')
    apps.value = r.apps || []
  } catch (e) {
    error.value = 'No s\'han pogut carregar les aplicacions. Torna-ho a provar.'
  } finally {
    loading.value = false
  }
})

async function obre(a) {
  error.value = ''
  saltant.value = a.app
  try {
    // El bitllet caduca en segons: es demana i es salta a l'instant.
    const r = await apiRequest('/v1/portal/launch', { method: 'POST', body: { app: a.app } })
    if (r.url) { window.location.href = r.url; return }
    error.value = 'No s\'ha pogut obrir l\'aplicació.'
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut obrir l\'aplicació.'
  } finally {
    saltant.value = null
  }
}
</script>

<style scoped>
.portal-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.portal-tile {
  display: flex;
  align-items: center;
  gap: 16px;
  text-align: left;
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 14px;
  padding: 20px;
  min-height: 96px; /* objectiu tàctil generós: es toca amb el dit, no amb ratolí */
  cursor: pointer;
  font: inherit;
  transition: box-shadow .15s, transform .15s;
}
.portal-tile:hover, .portal-tile:focus-visible { box-shadow: 0 8px 24px rgba(10, 42, 74, .14); transform: translateY(-2px); }
.portal-tile:disabled { opacity: .6; cursor: wait; }
.portal-tile-icon {
  width: 56px; height: 56px; flex: 0 0 56px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 14px;
  background: linear-gradient(120deg, #00806C, #2B8A8E);
  color: #fff;
}
.portal-tile-icon svg { width: 30px; height: 30px; }
.portal-tile-body { flex: 1; min-width: 0; }
.portal-tile-nom { display: block; font-weight: 700; color: var(--color-text, #0A2A4A); font-size: 1.05rem; }
.portal-tile-desc { display: block; color: var(--color-text-secondary, #64748B); font-size: .85rem; margin-top: 3px; }
.portal-tile-cta { color: #00806C; font-weight: 700; white-space: nowrap; }
.portal-error {
  margin-top: 14px; padding: 12px 16px; border-radius: 10px;
  background: #fdf1f0; color: #B3352F; font-weight: 600;
}
</style>
