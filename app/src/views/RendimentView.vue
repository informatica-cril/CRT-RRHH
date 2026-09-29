<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Rendiment assistencial</h1>
        <p class="page-subtitle">
          Compliment de les obligacions laborals contractades (art. 20.3 ET). Les xifres les calcula la
          Domiciliària i arriben tancades per període.
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

    <div v-if="files.length" class="card mb-lg" style="overflow-x:auto;">
      <table class="table">
        <thead>
          <tr>
            <th>Professional</th>
            <th class="num">Sessions</th>
            <th class="num">Compliment</th>
            <th class="num">Documental</th>
            <th class="num">Altes sense informe</th>
            <th class="num">Adherència</th>
            <th class="num">Puntualitat</th>
            <th class="num">Retard</th>
            <th class="num">Veu del pacient</th>
            <th class="num">Elements</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in files" :key="f.user_id">
            <td>
              <router-link :to="`/expedient?user=${f.user_id}`">{{ f.name }}</router-link>
            </td>
            <td class="num">{{ f.sessions_firmades }}</td>
            <td class="num" :style="colorPct(f.compliment_pct, mitjana?.compliment_pct)">{{ pct(f.compliment_pct) }}</td>
            <td class="num" :style="colorPct(f.documental_pct, mitjana?.documental_pct)">{{ pct(f.documental_pct) }}</td>
            <td class="num" :style="f.altes_sense_informe > 0 ? 'color:var(--color-danger);font-weight:700' : ''">
              {{ f.altes_sense_informe }}
            </td>
            <td class="num">{{ pct(f.adherencia_pct) }}</td>
            <td class="num">{{ pct(f.puntualitat_pct) }}</td>
            <td class="num">{{ f.retard_mitja_min === null ? '—' : f.retard_mitja_min + '′' }}</td>
            <td class="num">
              <span v-if="f.aportacions === null || !f.aportacions" class="text-muted">—</span>
              <span v-else-if="!f.prou_mostra" class="text-muted" title="Mostra insuficient">mostra curta</span>
              <span v-else>
                <span style="color:var(--color-success)">{{ f.agraiments }}</span> /
                <span style="color:var(--color-danger)">{{ f.queixes }}</span>
              </span>
            </td>
            <td class="num">
              <router-link v-if="f.elements" to="/disciplinary">
                {{ f.elements }}<span v-if="f.elements_en_cas" class="text-muted"> ({{ f.elements_en_cas }} en cas)</span>
              </router-link>
              <span v-else class="text-muted">—</span>
            </td>
          </tr>
          <tr v-if="mitjana" style="font-weight:700;background:var(--color-bg-subtle);">
            <td>Mitjana del servei ({{ mitjana.n }})</td>
            <td class="num">{{ arrodoneix(mitjana.sessions_firmades) }}</td>
            <td class="num">{{ pct(arrodoneix(mitjana.compliment_pct)) }}</td>
            <td class="num">{{ pct(arrodoneix(mitjana.documental_pct)) }}</td>
            <td colspan="6"></td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="!loading && !avis" class="card">
      <div class="text-small text-muted">Cap dada per a aquest període.</div>
    </div>

    <!--
      Escrits de disconformitat. Van AQUÍ, al costat de les xifres que discuteixen: una safata a part
      és una safata que ningú obre, i sense resposta el dret a rebatre és nominal.
    -->
    <div v-if="allegacions.length" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
      <div class="card-header">
        <h3 class="card-title">Escrits de disconformitat pendents de resposta</h3>
        <span class="text-small text-muted">{{ allegacions.length }}</span>
      </div>
      <div v-for="a in allegacions" :key="a.id" style="border-top:1px solid var(--color-border);padding:12px 0;">
        <div class="text-small">
          <b>{{ a.user?.name || ('usuari ' + a.user_id) }}</b> · indicador <b>{{ a.indicador }}</b>
          · període {{ String(a.periode_desde).slice(0,10) }} → {{ String(a.periode_fins).slice(0,10) }}
          · presentat el {{ fmtData(a.presentada_ts) }}
        </div>
        <div style="white-space:pre-wrap;background:var(--color-bg);border-radius:8px;padding:10px;margin-top:8px;">{{ a.text }}</div>
        <textarea v-model="resposta[a.id]" class="form-input" rows="4" style="width:100%;margin-top:10px;"
                  placeholder="Resposta motivada (mínim 20 caràcters). Queda desada amb data i autor, i la persona la veu a la seva pantalla."></textarea>
        <button class="btn btn-primary" style="margin-top:8px;"
                :disabled="responent === a.id || (resposta[a.id] || '').trim().length < 20"
                @click="respon(a)">
          {{ responent === a.id ? 'Desant…' : 'Respondre' }}
        </button>
      </div>
    </div>

    <div class="card">
      <div class="text-small" style="line-height:1.7;">
        <b>Com es llegeix.</b> Aquí hi ha xifres i la mitjana del servei, no puntuacions ni rànquings: els colors
        comparen amb l'equip, mai amb un objectiu imposat. Una adherència baixa pot ser la decisió clínica
        correcta i un retard pot ser un ascensor avariat, per això cap conseqüència es dedueix d'una cel·la.<br><br>
        La columna <b>Elements</b> compta els fets que algú ha elevat des de la Domiciliària a la safata de
        Procediment. Un element no és una sanció ni un expedient: és un fet documentat, amb la motivació de qui
        el va elevar, que espera que RRHH decideixi si escau instruir res. Qui consulta aquesta pantalla queda
        registrat a la traçabilitat.<br><br>
        La persona afectada veu exactament aquestes mateixes xifres seves a «El meu rendiment», amb què mesura
        cada indicador i d'on surt la dada, i des d'allà pot presentar per escrit la seva disconformitat amb un
        indicador d'un període. Aquests escrits apareixen en aquesta mateixa pantalla i s'han de respondre.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/apiClient'

const files = ref([])
const mitjana = ref(null)
const periodes = ref([])
const periodeSel = ref(null)
const loading = ref(false)
const error = ref('')
const avis = ref('')
const allegacions = ref([])
const resposta = ref({})
const responent = ref(null)

function fmtData (ts) { return ts ? new Date(ts).toLocaleString('ca-ES') : '—' }

async function respon (a) {
  responent.value = a.id
  error.value = ''
  try {
    await api.post(`/v1/rendiment/allegacions/${a.id}/resposta`, { resposta: resposta.value[a.id] })
    resposta.value = { ...resposta.value, [a.id]: '' }
    await load()
  } catch (e) {
    error.value = e?.message || 'No s\'ha pogut desar la resposta.'
  } finally {
    responent.value = null
  }
}

function pct (v) { return (v === null || v === undefined) ? '—' : v + '%' }
function arrodoneix (v) { return (v === null || v === undefined) ? null : Math.round(v) }

function colorPct (v, m) {
  if (v === null || v === undefined || !m) return ''
  if (v >= m * 1.05) return 'color:var(--color-success);font-weight:700'
  if (v < m * 0.85) return 'color:var(--color-danger);font-weight:700'
  if (v < m * 0.95) return 'color:var(--color-warning);font-weight:700'
  return ''
}

function etiquetaPeriode (p) {
  return `${p.periode_desde.slice(0, 10)} → ${p.periode_fins.slice(0, 10)}`
}

async function load () {
  loading.value = true
  error.value = ''
  avis.value = ''
  try {
    const q = periodeSel.value
      ? `?desde=${periodeSel.value.periode_desde.slice(0, 10)}&fins=${periodeSel.value.periode_fins.slice(0, 10)}`
      : ''
    const r = await api.get('/v1/rendiment' + q)
    files.value = r.files || []
    mitjana.value = r.mitjana || null
    periodes.value = r.periodes || []
    allegacions.value = r.allegacions_pendents || []
    if (r.avis) avis.value = r.avis
    if (!periodeSel.value && periodes.value.length) periodeSel.value = periodes.value[0]
  } catch (e) {
    error.value = e?.message || 'No s\'han pogut carregar les dades.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
