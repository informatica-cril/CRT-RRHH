<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Expedient del treballador</h1>
        <p class="page-subtitle">Mètriques objectives agregades des de domi · només lectura · la qualificació i la sanció les decideix una persona</p>
      </div>
    </div>

    <!-- Garanties: sempre visibles, no és un tràmit amagat -->
    <div class="card mb-lg" style="border-left:4px solid var(--color-primary);">
      <div style="display:flex;gap:10px;align-items:flex-start;">
        <span style="font-size:1.3rem;">🛡</span>
        <div class="text-small">
          <b>Garanties del procediment.</b> Cap sanció automàtica (RGPD art. 22): el motor <i>acredita i proposa</i>, una persona <i>qualifica i signa</i>.
          Només es computen fets <b>imputables</b> al treballador (amb causa); el no imputable —pacient, avaria, força major— no suma.
          Control de prescripció (ET 60.2). Vincle autònom: fora de la via disciplinària laboral.
          L'accés a aquesta pantalla queda registrat.
        </div>
      </div>
    </div>

    <!-- Controls -->
    <div class="card mb-lg">
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div>
          <label class="text-small text-muted" style="display:block;margin-bottom:4px;">Professional (identificador a domi)</label>
          <input class="form-input" v-model.trim="professional" list="profList" placeholder="p. ex. A.Martinez" style="width:240px;" @keyup.enter="consultar" />
          <datalist id="profList">
            <option v-for="w in workers" :key="w.id" :value="w.name">{{ w.name }}</option>
          </datalist>
        </div>
        <div>
          <label class="text-small text-muted" style="display:block;margin-bottom:4px;">Des de</label>
          <input class="form-input" type="date" v-model="desde" style="width:170px;" />
        </div>
        <div>
          <label class="text-small text-muted" style="display:block;margin-bottom:4px;">Fins</label>
          <input class="form-input" type="date" v-model="fins" style="width:170px;" />
        </div>
        <button class="btn btn-primary" :disabled="loading || !professional" @click="consultar">
          {{ loading ? 'Consultant…' : 'Consultar expedient' }}
        </button>
      </div>
      <p class="text-small text-muted" style="margin-top:8px;">
        L'identificador és el que fa servir domi a les sessions (columna <code>fisio</code>), no necessàriament l'ID de RRHH. La correspondència usuari RRHH ↔ professional domi és el punt d'integració pendent de tancar.
      </p>
    </div>

    <!-- Estats -->
    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);"><b>No s'ha pogut carregar l'expedient.</b> {{ error }}</div>
    </div>

    <div v-if="dorment" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="text-small">⚙ El motor de l'expedient està <b>inactiu</b> a domi (F0 pendent / kill-switch OFF). No es mostren dades.</div>
    </div>

    <!-- Resultats -->
    <template v-if="data && !dorment">
      <div v-for="(bloc, clau) in blocs" :key="clau" class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title" style="text-transform:capitalize;">{{ nomBloc(clau) }}</h3>
          <span class="text-small text-muted">
            <span v-if="bloc.tipus" :style="tipusStyle(bloc.tipus)">{{ bloc.tipus }}</span>
            <span v-if="bloc.font"> · Font: {{ bloc.font }}</span>
          </span>
        </div>

        <!-- Dades (mètriques) -->
        <div v-if="bloc.dades && Object.keys(bloc.dades).length" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:10px;">
          <div v-for="(val, k) in metriques(bloc.dades)" :key="k" style="background:var(--color-bg);padding:12px;border-radius:8px;">
            <div class="text-small text-muted">{{ etiqueta(k) }}</div>
            <div style="font-size:1.3rem;font-weight:700;">{{ formatVal(val) }}</div>
          </div>
        </div>
        <div v-else class="text-small text-muted" style="margin-bottom:8px;">Sense dades per a aquest bloc en el període.</div>

        <!-- Nota de context (objectiu vs valoratiu) -->
        <div v-if="bloc.nota" class="text-small text-muted" style="background:rgba(0,0,0,0.02);padding:10px;border-radius:8px;">
          ℹ {{ bloc.nota }}
        </div>
      </div>

      <div v-if="!Object.keys(blocs).length" class="card">
        <div class="text-small text-muted">Cap indicador per a aquest professional i període.</div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'
import api from '../services/apiClient'

const authStore = useAuthStore()

const professional = ref('')
const today = new Date()
const d90 = new Date(today.getTime() - 90 * 86400000)
const iso = (dt) => dt.toISOString().substring(0, 10)
const desde = ref(iso(d90))
const fins = ref(iso(today))

const workers = ref([])
const loading = ref(false)
const error = ref('')
const data = ref(null)

// El contracte F1 aniya els indicadors sota `blocs`, cadascun {font, tipus, dades, nota}.
const blocs = computed(() => (data.value && data.value.blocs) ? data.value.blocs : {})
const dorment = computed(() => !!(data.value && data.value.dorment))

// De `dades` només mostrem escalars/arrays com a mètriques; els objectes nidificats (p.ex. per_estat) es filtren.
function metriques(dades) {
  const out = {}
  for (const [k, v] of Object.entries(dades || {})) {
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) continue
    out[k] = v
  }
  return out
}
function tipusStyle(tipus) {
  const t = String(tipus)
  const objectiu = t.includes('objectiu')
  return {
    padding: '1px 8px', borderRadius: '10px', fontWeight: '700', fontSize: '0.72rem',
    color: objectiu ? 'var(--color-success)' : 'var(--color-warning)',
    background: objectiu ? 'rgba(76,175,80,0.10)' : 'rgba(245,158,11,0.12)'
  }
}

function nomBloc(clau) {
  const noms = {
    auditoria: 'Auditoria clínica', fitxatge: 'Puntualitat i jornada',
    desenvolupament: 'Desenvolupament de sessions', incidencies: 'Incidències',
    enquestes: 'Enquestes de satisfacció', retards: 'Retards', durades: 'Durada de sessions',
    reprogramacions: 'Reprogramacions', tecniques_no: 'Tècniques no aplicades'
  }
  return noms[clau] || String(clau).replace(/_/g, ' ')
}
function etiqueta(k) { return String(k).replace(/_/g, ' ') }
function formatVal(v) {
  if (v === null || v === undefined) return '—'
  if (Array.isArray(v)) return v.length
  if (typeof v === 'object') return JSON.stringify(v)
  return v
}

async function consultar() {
  if (!professional.value) return
  loading.value = true; error.value = ''; data.value = null
  try {
    const qs = new URLSearchParams({ desde: desde.value, fins: fins.value }).toString()
    data.value = await api.get(`/v1/expedient/${encodeURIComponent(professional.value)}?${qs}`)
  } catch (e) {
    error.value = e?.message || 'Error de connexió amb domi.'
  } finally {
    loading.value = false
  }
}

// Precarrega la llista de treballadors per a l'autocompletat (no bloqueja la vista).
db.getUsers().then(us => { workers.value = (us || []).filter(u => u.role === 'worker') }).catch(() => {})
</script>
