<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">El meu rendiment</h1>
        <p class="page-subtitle">
          Les mateixes xifres que la Direcció veu de tu, amb què mesura cadascuna i d'on surt la dada.
          Les calcula la Domiciliària i arriben tancades per període.
        </p>
      </div>
      <div class="page-actions">
        <select v-if="periodes.length" v-model="periodeSel" class="input" @change="load">
          <option v-for="p in periodes" :key="p.periode_desde + p.periode_fins" :value="p">
            {{ etiquetaPeriode(p) }}
          </option>
        </select>
        <button class="btn" :disabled="loading" @click="load">{{ loading ? 'Carregant…' : 'Actualitzar' }}</button>
      </div>
    </div>

    <div v-if="avis" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="text-small">{{ avis }}</div>
    </div>
    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <!--
      Sense comparatives ni rànquings: la mitjana del servei serveix a qui organitza el servei; a la
      persona no li aporta res i converteix una xifra en un judici sobre companys.
    -->
    <div v-if="fila" class="card mb-lg">
      <div class="card-header">
        <h3 class="card-title">Període {{ etiquetaPeriode(periode) }}</h3>
        <span class="text-small text-muted">actualitzat el {{ fmtData(fila.updated_at) }}</span>
      </div>
      <div class="rend-graella">
        <div v-for="i in indicadorsVisibles" :key="i.clau" class="rend-cel">
          <div class="rend-valor">{{ i.valor }}</div>
          <div class="rend-etiqueta">{{ i.etiqueta }}</div>
          <div class="text-small text-muted" style="line-height:1.5;margin-top:6px;">{{ i.mesura }}</div>
          <div class="text-small text-muted" style="margin-top:6px;"><b>Font:</b> {{ i.origen }}</div>
          <button class="btn btn-small" style="margin-top:10px;" @click="obreEscrit(i.clau)">
            No hi estic d'acord
          </button>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && !avis" class="card mb-lg">
      <div class="text-small text-muted">Cap dada per a aquest període.</div>
    </div>

    <!-- Canal per rebatre: un indicador concret d'un període concret, amb text propi. -->
    <div v-if="escrivint" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="card-header">
        <h3 class="card-title">Rebatre «{{ indicadors[escrivint]?.etiqueta }}»</h3>
        <span class="text-small text-muted">{{ etiquetaPeriode(periode) }}</span>
      </div>
      <div class="text-small" style="line-height:1.7;margin-bottom:10px;">
        Explica per què consideres que aquesta xifra no reflecteix la teva feina en aquest període.
        La data la posa el sistema. L'escrit arriba a Direcció i a Recursos Humans i ha de tenir
        resposta escrita.
      </div>
      <FrasesRapides clau="allegacio_rendiment" v-model="text" />
      <textarea v-model="text" class="form-input" rows="6" style="width:100%;"
                placeholder="Escriu aquí la teva explicació i, si escau, les dades concretes que la sostenen (mínim 20 caràcters)."></textarea>
      <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
        <button class="btn btn-primary" :disabled="enviant || text.trim().length < 20" @click="envia">
          {{ enviant ? 'Registrant…' : 'Presentar el meu escrit' }}
        </button>
        <button class="btn" :disabled="enviant" @click="escrivint = null">Cancel·lar</button>
      </div>
    </div>

    <div v-if="allegacions.length" class="card mb-lg">
      <div class="card-header"><h3 class="card-title">Els meus escrits de disconformitat</h3></div>
      <div v-for="a in allegacions" :key="a.id" class="rend-escrit">
        <div class="text-small">
          <b>{{ indicadors[a.indicador]?.etiqueta || a.indicador }}</b>
          · {{ etiquetaPeriode({ periode_desde: a.periode_desde, periode_fins: a.periode_fins }) }}
          · presentat el {{ fmtData(a.presentada_ts) }}
        </div>
        <div class="rend-text">{{ a.text }}</div>
        <div v-if="a.resposta_ts" class="rend-resposta">
          <div class="text-small" style="color:var(--color-success);font-weight:700;">
            Resposta de l'empresa · {{ fmtData(a.resposta_ts) }}
          </div>
          <div class="rend-text">{{ a.resposta }}</div>
        </div>
        <div v-else class="text-small" style="color:var(--color-warning);font-weight:700;margin-top:8px;">
          Pendent de resposta.
        </div>
      </div>
    </div>

    <div class="card">
      <div class="text-small" style="line-height:1.7;">
        <b>Com es llegeix.</b> Això són xifres, no una puntuació ni una nota. Cap d'aquestes cel·les
        dispara per si sola cap conseqüència laboral: qualsevol decisió la pren una persona, amb la
        seva pròpia motivació i pel procediment que preveu el conveni. Una adherència baixa pot ser
        la decisió clínica correcta i un retard pot ser un ascensor avariat.<br><br>
        Qui consulta el teu rendiment queda registrat a la traçabilitat, i tu pots demanar-ne còpia
        al Delegat de Protecció de Dades. Si creus que una xifra és errònia o incompleta, el botó
        «No hi estic d'acord» de cada indicador és la via per fer-ho constar.
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const fila = ref(null)
const indicadors = ref({})
const allegacions = ref([])
const periodes = ref([])
const periodeSel = ref(null)
const periode = ref({})
const loading = ref(false)
const error = ref('')
const avis = ref('')
const escrivint = ref(null)
const text = ref('')
const enviant = ref(false)

function fmtData (ts) { return ts ? new Date(ts).toLocaleString('ca-ES') : '—' }
function etiquetaPeriode (p) {
  if (!p || !p.periode_desde) return '—'
  return `${String(p.periode_desde).slice(0, 10)} → ${String(p.periode_fins).slice(0, 10)}`
}

/** Format per indicador: la unitat forma part de la lectura, no és decoració. */
function valorDe (clau, f) {
  if (clau === 'veu_pacient') {
    if (!f.aportacions) return '—'
    if (!f.prou_mostra) return 'mostra curta'
    return `${f.agraiments} agraïments / ${f.queixes} queixes`
  }
  if (clau === 'elements') return f.elements ?? 0
  const v = f[clau]
  if (v === null || v === undefined) return '—'
  if (clau.endsWith('_pct')) return v + '%'
  if (clau === 'retard_mitja_min') return v + ' min'
  return v
}

const indicadorsVisibles = computed(() => {
  if (!fila.value) return []
  return Object.entries(indicadors.value).map(([clau, i]) => ({
    clau, etiqueta: i.etiqueta, mesura: i.mesura, origen: i.origen, valor: valorDe(clau, fila.value)
  }))
})

async function load () {
  loading.value = true
  error.value = ''
  avis.value = ''
  try {
    const q = periodeSel.value
      ? `?desde=${String(periodeSel.value.periode_desde).slice(0, 10)}&fins=${String(periodeSel.value.periode_fins).slice(0, 10)}`
      : ''
    const r = await api.get(`/v1/rendiment/persona/${auth.user?.id}` + q)
    fila.value = r.fila || null
    indicadors.value = r.indicadors || {}
    allegacions.value = r.allegacions || []
    periodes.value = r.periodes || []
    periode.value = r.periode
      ? { periode_desde: r.periode.desde, periode_fins: r.periode.fins }
      : {}
    if (r.avis) avis.value = r.avis
    if (!periodeSel.value && periodes.value.length) periodeSel.value = periodes.value[0]
  } catch (e) {
    error.value = e?.message || 'No s\'han pogut carregar les dades.'
  } finally {
    loading.value = false
  }
}

function obreEscrit (clau) {
  escrivint.value = clau
  text.value = ''
}

async function envia () {
  enviant.value = true
  error.value = ''
  try {
    await api.post(`/v1/rendiment/persona/${auth.user?.id}/allegacio`, {
      periode_desde: String(periode.value.periode_desde).slice(0, 10),
      periode_fins: String(periode.value.periode_fins).slice(0, 10),
      indicador: escrivint.value,
      text: text.value
    })
    escrivint.value = null
    text.value = ''
    await load()
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut registrar l\'escrit.'
  } finally {
    enviant.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.rend-graella { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.rend-cel { border: 1px solid var(--color-border); border-radius: 10px; padding: 14px; }
.rend-valor { font-size: 1.6rem; font-weight: 700; }
.rend-etiqueta { font-weight: 600; margin-top: 2px; }
.rend-escrit { border-top: 1px solid var(--color-border); padding: 12px 0; }
.rend-text { white-space: pre-wrap; background: var(--color-bg); border-radius: 8px; padding: 10px; margin-top: 8px; }
.rend-resposta { margin-top: 10px; }
</style>
