<template>
  <div class="cfg">
    <div class="page-header">
      <div><h1 class="page-title">Configuració</h1><p class="page-subtitle">Les teves preferències<template v-if="potGestionarFestius">, el calendari de festius</template> i la privacitat.</p></div>
    </div>

    <!-- Preferències: idioma i mode fosc en una sola targeta -->
    <div class="card cfg-card">
      <h3 class="cfg-titol">Preferències</h3>
      <div class="cfg-fila">
        <div>
          <div class="cfg-etiqueta">{{ t('language') }}</div>
          <div class="text-small text-muted">L'idioma dels textos de l'aplicació.</div>
        </div>
        <div class="cfg-segments" role="group" :aria-label="t('language')">
          <button v-for="lang in locales" :key="lang" type="button" :class="{ actiu: currentLocale === lang }" @click="setLocale(lang)">
            {{ localeNames[lang] }}
          </button>
        </div>
      </div>
      <div class="cfg-fila">
        <div>
          <div class="cfg-etiqueta">{{ t('dark_mode') }}</div>
          <div class="text-small text-muted">Colors foscos, més descansats amb poca llum.</div>
        </div>
        <label class="cfg-interruptor">
          <input type="checkbox" :checked="settingsStore.darkMode" @change="settingsStore.toggleDarkMode()" />
          <span></span>
        </label>
      </div>
    </div>

    <!-- Calendari laboral: només admin i RRHH -->
    <div v-if="potGestionarFestius" class="card cfg-card">
      <div class="cfg-cap">
        <div>
          <h3 class="cfg-titol">Calendari laboral · festius</h3>
          <p class="text-small text-muted">Alimenten la bossa anual d'hores, el calendari que veu cada treballador i la programació de la Domiciliària.</p>
        </div>
        <select class="form-select cfg-any" v-model.number="anyFestius" @change="carregaFestius" title="Any">
          <option v-for="a in anysDisponibles" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>

      <div class="cfg-afegir">
        <input class="form-input" type="date" v-model="nouFestiu.date" :min="`${anyFestius}-01-01`" :max="`${anyFestius}-12-31`" />
        <input class="form-input cfg-nom" type="text" v-model="nouFestiu.name" placeholder="Nom del festiu" @keyup.enter="afegeixFestiu" />
        <select class="form-select" v-model="nouFestiu.type">
          <option v-for="(nom, codi) in TIPUS" :key="codi" :value="codi">{{ nom }}</option>
        </select>
        <button class="btn btn-primary btn-sm" @click="afegeixFestiu" :disabled="!nouFestiu.date || !nouFestiu.name">+ Afegir</button>
      </div>
      <div v-if="errFestius" class="cfg-error">{{ errFestius }}</div>

      <div v-if="!festius.length" class="cfg-buit">Cap festiu configurat per a {{ anyFestius }}: la bossa anual comptarà tots els dies laborables.</div>
      <template v-else>
        <div class="text-small text-muted cfg-compte">{{ festius.length }} festiu{{ festius.length === 1 ? '' : 's' }} el {{ anyFestius }}</div>
        <ul class="cfg-festius">
          <li v-for="f in festiusOrdenats" :key="f.id">
            <span class="cfg-data">{{ dataLlarga(f.date) }}</span>
            <span class="cfg-festiu-nom">{{ f.name }}</span>
            <span class="cfg-tipus" :class="'t-' + f.type">{{ TIPUS[f.type] || f.type }}</span>
            <button class="icon-btn cfg-esborra" @click="esborraFestiu(f)" :title="'Esborrar ' + f.name">✕</button>
          </li>
        </ul>
      </template>
    </div>

    <!-- Privacitat -->
    <div class="card cfg-card">
      <h3 class="cfg-titol">{{ t('consent_management') }}</h3>
      <div v-for="consent in consents" :key="consent.type" class="cfg-fila">
        <div>
          <div class="cfg-etiqueta">{{ consent.label }}</div>
          <div class="text-small text-muted">{{ consent.description }}</div>
        </div>
        <label class="cfg-interruptor" :class="{ bloquejat: consent.type === 'data_processing' }"
          :title="consent.type === 'data_processing' ? 'Obligatori per llei: no es pot desactivar' : ''">
          <input type="checkbox" :checked="consent.granted" @change="toggleConsent(consent.type)" :disabled="consent.type === 'data_processing'" />
          <span></span>
        </label>
      </div>
      <details class="cfg-legal">
        <summary>Qui tracta les teves dades i per què</summary>
        <p>{{ t('data_controller') }}</p>
        <p>{{ t('legal_basis') }}</p>
        <p>{{ t('data_retention') }}</p>
      </details>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
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
const TIPUS = { national: 'Nacional', regional: 'Catalunya', local: 'Local', empresa: 'Empresa' }
const festiusOrdenats = computed(() => [...festius.value].sort((a, b) => String(a.date).localeCompare(String(b.date))))
// «dc., 25 de des.»: la data es llegeix del text (AAAA-MM-DD), sense zona horària.
function dataLlarga(d) {
  const [a, m, dia] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(a, m - 1, dia).toLocaleDateString('ca-ES', { weekday: 'short', day: 'numeric', month: 'short' })
}
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
  if (!await confirma(`Esborrar el festiu ${f.date} · ${f.name}?`)) return
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

<style scoped>
.cfg { max-width: 920px; }
.cfg-card { padding: 18px 20px; margin-bottom: 16px; }
.cfg-titol { margin: 0 0 4px; font-size: 1rem; font-weight: 700; }
.cfg-cap { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
.cfg-cap p { margin: 2px 0 0; }
.cfg-any { width: auto; min-width: 100px; }
.cfg-fila { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 12px 0; border-top: 1px solid var(--color-border-light, #eef2f7); }
.cfg-titol + .cfg-fila { border-top: 0; }
.cfg-etiqueta { font-weight: 600; font-size: .9rem; }
.cfg-segments { display: inline-flex; border: 1px solid var(--color-border, #e2e8f0); border-radius: 99px; overflow: hidden; flex-shrink: 0; }
.cfg-segments button { border: 0; background: transparent; padding: 6px 14px; font-size: .82rem; font-weight: 600; cursor: pointer; color: var(--color-text-secondary, #475569); }
.cfg-segments button.actiu { background: var(--color-primary, #094E8C); color: #fff; }
.cfg-interruptor { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; cursor: pointer; }
.cfg-interruptor input { opacity: 0; width: 0; height: 0; position: absolute; }
.cfg-interruptor span { position: absolute; inset: 0; border-radius: 99px; background: #cbd5e1; transition: background .15s; }
.cfg-interruptor span::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; border-radius: 50%; background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
.cfg-interruptor input:checked + span { background: var(--color-primary, #094E8C); }
.cfg-interruptor input:checked + span::before { transform: translateX(20px); }
.cfg-interruptor input:focus-visible + span { outline: 2px solid var(--color-primary, #094E8C); outline-offset: 2px; }
.cfg-interruptor.bloquejat { cursor: not-allowed; opacity: .6; }
.cfg-afegir { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 12px; border-radius: 10px; background: var(--color-bg, #f8fafc); }
.cfg-afegir .form-input, .cfg-afegir .form-select { width: auto; }
.cfg-nom { flex: 1; min-width: 180px; }
.cfg-error { margin-top: 8px; color: var(--color-danger, #B3352F); font-size: .85rem; }
.cfg-buit { margin-top: 12px; padding: 16px; text-align: center; color: var(--color-text-muted, #64748b); font-size: .88rem; }
.cfg-compte { margin: 12px 0 4px; }
.cfg-festius { list-style: none; margin: 0; padding: 0; }
.cfg-festius li { display: grid; grid-template-columns: 130px 1fr auto auto; align-items: center; gap: 12px; padding: 8px 4px; border-top: 1px solid var(--color-border-light, #eef2f7); font-size: .88rem; }
.cfg-data { font-weight: 700; font-variant-numeric: tabular-nums; text-transform: capitalize; }
.cfg-tipus { font-size: .72rem; font-weight: 700; padding: 2px 9px; border-radius: 99px; background: #e2e8f0; color: #334155; }
.cfg-tipus.t-national { background: #FDECEA; color: #B3352F; }
.cfg-tipus.t-regional { background: #FEF3C7; color: #92400E; }
.cfg-tipus.t-local { background: #DBEAFE; color: #1E3A8A; }
.cfg-tipus.t-empresa { background: #DCFCE7; color: #166534; }
.cfg-esborra { color: var(--color-text-muted, #94a3b8); }
.cfg-esborra:hover { color: var(--color-danger, #B3352F); }
.cfg-legal { margin-top: 8px; padding-top: 10px; border-top: 1px solid var(--color-border-light, #eef2f7); font-size: .84rem; color: var(--color-text-secondary, #475569); }
.cfg-legal summary { cursor: pointer; font-weight: 600; color: var(--color-primary, #094E8C); }
.cfg-legal p { margin: 8px 0 0; line-height: 1.5; }
@media (max-width: 600px) {
  .cfg-card { padding: 14px; }
  .cfg-festius li { grid-template-columns: 1fr auto auto; }
  .cfg-data { grid-column: 1 / -1; }
  .cfg-afegir .form-input, .cfg-afegir .form-select, .cfg-afegir .btn { width: 100%; }
}
</style>
