<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('settings') }}</h1></div>
    </div>

    <div class="settings-grid">
      <div class="card">
        <h3 class="card-title mb-md">{{ t('language') }}</h3>
        <div class="lang-selector" style="display:inline-flex;">
          <button v-for="lang in locales" :key="lang" class="lang-btn"
            :class="{ active: currentLocale === lang }" @click="setLocale(lang)">
            {{ localeNames[lang] }}
          </button>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title mb-md">{{ t('dark_mode') }}</h3>
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
          <input type="checkbox" :checked="settingsStore.darkMode" @change="settingsStore.toggleDarkMode()" style="width:18px;height:18px;" />
          <span>{{ t('dark_mode') }}</span>
        </label>
      </div>

      <div class="card" style="grid-column:1/-1;" v-if="potGestionarFestius">
        <h3 class="card-title mb-md">Calendari laboral · festius</h3>
        <p class="text-muted mb-md">Els festius es configuren aquí cada any. Alimenten la bossa anual d'hores
          (1726 h a jornada completa), el calendari que veu cada treballador i la programació de la Domiciliària.</p>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
          <select v-model.number="anyFestius" @change="carregaFestius" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;">
            <option v-for="a in anysDisponibles" :key="a" :value="a">{{ a }}</option>
          </select>
          <input type="date" v-model="nouFestiu.date" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;" />
          <input type="text" v-model="nouFestiu.name" placeholder="Nom del festiu" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;min-width:200px;" />
          <select v-model="nouFestiu.type" style="padding:8px 12px;border:1px solid var(--color-border);border-radius:8px;">
            <option value="national">Nacional</option><option value="regional">Catalunya</option>
            <option value="local">Local</option><option value="empresa">Empresa</option>
          </select>
          <button class="btn btn-primary" @click="afegeixFestiu" :disabled="!nouFestiu.date || !nouFestiu.name">Afegir</button>
        </div>
        <div v-if="errFestius" style="color:var(--color-danger);margin-bottom:10px;">{{ errFestius }}</div>
        <div v-if="!festius.length" class="text-muted">Cap festiu configurat per a {{ anyFestius }} — la bossa anual comptarà tots els dies laborables.</div>
        <div v-for="f in festius" :key="f.id" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--color-border-light);">
          <div><strong>{{ f.date }}</strong> · {{ f.name }} <span class="text-small text-muted">({{ f.type }})</span></div>
          <button class="btn btn-sm" style="color:var(--color-danger);" @click="esborraFestiu(f)">Esborrar</button>
        </div>
      </div>

      <div class="card" style="grid-column:1/-1;">
        <h3 class="card-title mb-md">{{ t('consent_management') }}</h3>
        <p class="text-muted mb-md">{{ t('data_controller') }}</p>
        <p class="text-muted mb-md">{{ t('legal_basis') }}</p>
        <p class="text-muted mb-md">{{ t('data_retention') }}</p>
        
        <div style="border-top:1px solid var(--color-border);padding-top:16px;margin-top:16px;">
          <h4 style="margin-bottom:12px;">Consentiments</h4>
          <div v-for="consent in consents" :key="consent.type" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--color-border-light);">
            <div>
              <strong>{{ consent.label }}</strong>
              <p class="text-small text-muted">{{ consent.description }}</p>
            </div>
            <label :style="consent.type === 'data_processing' ? 'cursor:not-allowed;opacity:0.5;' : 'cursor:pointer;'">
              <input type="checkbox" :checked="consent.granted" @change="toggleConsent(consent.type)" :disabled="consent.type === 'data_processing'" style="width:16px;height:16px;" />
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSettingsStore } from '../stores/settings'
import { useAuthStore } from '../stores/auth'
import { api } from '../services/apiClient'
import { i18n } from '../i18n'

const settingsStore = useSettingsStore()
const authStore = useAuthStore()

// ── Calendari laboral (festius per any) — només admin i RRHH ──
const potGestionarFestius = computed(() => ['admin', 'hr'].includes(authStore.user?.role))
const anyFestius = ref(new Date().getFullYear())
const anysDisponibles = computed(() => {
  const a = new Date().getFullYear()
  return [a - 1, a, a + 1]
})
const festius = ref([])
const nouFestiu = ref({ date: '', name: '', type: 'empresa' })
const errFestius = ref('')

async function carregaFestius() {
  if (!potGestionarFestius.value) return
  festius.value = await api.get(`/v1/holidays?year=${anyFestius.value}`).catch(() => [])
}

async function afegeixFestiu() {
  errFestius.value = ''
  try {
    await api.post('/v1/holidays', { ...nouFestiu.value })
    nouFestiu.value = { date: '', name: '', type: 'empresa' }
    await carregaFestius()
  } catch (e) {
    errFestius.value = e?.message || "No s'ha pogut afegir el festiu."
  }
}

async function esborraFestiu(f) {
  if (!confirm(`Esborrar el festiu ${f.date} · ${f.name}?`)) return
  try {
    await api.delete(`/v1/holidays/${f.id}`)
    await carregaFestius()
  } catch (e) {
    errFestius.value = e?.message || "No s'ha pogut esborrar."
  }
}

onMounted(carregaFestius)
const t = (key) => i18n.t(key)
const locales = i18n.availableLocales
const localeNames = i18n.localeNames
const currentLocale = computed(() => i18n.locale)

function setLocale(lang) { i18n.locale = lang }

const consents = ref([
  { type: 'data_processing', label: 'Tractament de dades laborals', description: 'Necessari per al registre de jornada (obligatori per RDL 8/2019)', granted: true },
  { type: 'geolocation', label: 'Geolocalització', description: 'Per verificar la ubicació durant la jornada laboral', granted: true },
  { type: 'analytics', label: 'Analítica', description: 'Per millorar l\'experiència d\'ús de l\'aplicació', granted: false }
])

function toggleConsent(type) {
  const c = consents.value.find(c => c.type === type)
  if (c && type !== 'data_processing') c.granted = !c.granted
}
</script>
