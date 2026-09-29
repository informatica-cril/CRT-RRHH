<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Accessos a aplicacions</h1>
        <p class="page-subtitle">Qui pot entrar a què des del portal. Treure l'accés amaga la targeta i tanca la porta a l'instant.</p>
      </div>
    </div>

    <div v-if="loading" class="card" style="text-align:center;padding:40px;color:var(--color-text-secondary);">
      Carregant accessos…
    </div>

    <div v-else class="card">
      <div class="pa-filtres">
        <input v-model="filtre" type="search" class="pa-cerca" placeholder="Filtrar per nom o email…">
        <span class="pa-resum">{{ filtrats.length }} treballadors · {{ totalGrants }} accessos actius</span>
      </div>

      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Treballador</th>
              <th v-for="a in cataleg" :key="a.app">{{ a.nom }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in filtrats" :key="u.id">
              <td>
                <strong>{{ u.name }}</strong>
                <div style="color:var(--color-text-secondary);font-size:.78rem;">{{ u.email }}</div>
              </td>
              <td v-for="a in cataleg" :key="a.app">
                <!-- Commutador tàctil gran (referència: tablet Zeno 5) -->
                <button class="pa-toggle" :class="{ actiu: te(u.id, a.app) }"
                        :disabled="canviant === u.id + a.app"
                        @click="commuta(u, a.app)">
                  {{ te(u.id, a.app) ? 'Té accés' : 'Sense accés' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p v-if="error" class="pa-error">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { apiRequest } from '../services/apiClient'

const cataleg = ref([])
const grants = ref([])
const usuaris = ref([])
const loading = ref(true)
const filtre = ref('')
const canviant = ref(null)
const error = ref('')

onMounted(carrega)

async function carrega() {
  loading.value = true
  try {
    const [g, u] = await Promise.all([
      apiRequest('/v1/portal/grants'),
      apiRequest('/v1/users/bulk-index'),
    ])
    cataleg.value = g.cataleg || []
    grants.value = g.grants || []
    usuaris.value = (u.users || [])
      .filter(t => t.role !== 'service')
      .map(t => ({ id: t.id, name: t.name, email: t.email }))
      .filter(t => t.id && t.name)
  } catch (e) {
    error.value = 'No s\'han pogut carregar els accessos.'
  } finally {
    loading.value = false
  }
}

const filtrats = computed(() => {
  const q = filtre.value.trim().toLowerCase()
  if (!q) return usuaris.value
  return usuaris.value.filter(u =>
    (u.name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q))
})

const totalGrants = computed(() => grants.value.length)

function te(userId, app) {
  return grants.value.some(g => g.user_id === userId && g.app === app)
}

async function commuta(u, app) {
  error.value = ''
  canviant.value = u.id + app
  const tenia = te(u.id, app)
  try {
    if (tenia) {
      await apiRequest('/v1/portal/grants', { method: 'DELETE', body: { user_id: u.id, app } })
      grants.value = grants.value.filter(g => !(g.user_id === u.id && g.app === app))
    } else {
      const r = await apiRequest('/v1/portal/grants', { method: 'POST', body: { user_id: u.id, app } })
      grants.value.push(r.grant || { user_id: u.id, app })
    }
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut canviar l\'accés.'
  } finally {
    canviant.value = null
  }
}
</script>

<style scoped>
.pa-filtres { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
.pa-cerca {
  border: 1px solid var(--color-border, #e2e8f0); border-radius: 999px;
  padding: 10px 18px; min-width: 260px; font: inherit;
}
.pa-resum { color: var(--color-text-secondary, #64748B); font-size: .85rem; }
.pa-toggle {
  border: 1px solid var(--color-border, #e2e8f0); border-radius: 999px;
  background: #f7fafc; color: #64748B;
  padding: 10px 18px; min-height: 44px; /* objectiu tàctil mínim */
  font: inherit; font-weight: 700; font-size: .82rem; cursor: pointer;
}
.pa-toggle.actiu { background: #E2F6EF; border-color: #0DAF83; color: #0A7C5E; }
.pa-toggle:disabled { opacity: .5; cursor: wait; }
.pa-error {
  margin-top: 14px; padding: 12px 16px; border-radius: 10px;
  background: #fdf1f0; color: #B3352F; font-weight: 600;
}
</style>
