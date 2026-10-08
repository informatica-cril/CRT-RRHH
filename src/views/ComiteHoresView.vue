<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Hores de representació</h1>
        <p class="page-subtitle">
          Registra les hores que dediques al comitè. RRHH les valida; les reunions convocades per
          l'empresa no es descompten del teu crèdit.
        </p>
      </div>
      <div class="page-actions">
        <input v-model="mes" type="month" class="form-input" @change="load" />
      </div>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <div v-if="!loading && !membre" class="card">
      <div class="empty-state"><p>No consteu com a representant aquest mes. Si creieu que és un error, parleu amb RRHH.</p></div>
    </div>

    <template v-if="membre">
      <!-- Resum del mes -->
      <div class="comite-resum mb-lg">
        <div class="stat-card"><div class="comite-xifra">{{ hm(resum.credit) }}</div><div class="text-small text-muted">Crèdit del mes</div></div>
        <div class="stat-card"><div class="comite-xifra" style="color:var(--color-success);">{{ hm(resum.validades) }}</div><div class="text-small text-muted">Validades</div></div>
        <div class="stat-card"><div class="comite-xifra" style="color:#D9A400;">{{ hm(resum.pendents) }}</div><div class="text-small text-muted">Pendents de validar</div></div>
        <div class="stat-card"><div class="comite-xifra" :style="resum.restants < 0 ? 'color:var(--color-danger)' : ''">{{ hm(resum.restants) }}</div><div class="text-small text-muted">Restants</div></div>
      </div>
      <div v-if="resum.exces_previst > 0" class="card mb-lg" style="border-left:4px solid var(--color-warning);">
        <div class="text-small">Si es validen totes les pendents, passaràs del crèdit del mes en {{ hm(resum.exces_previst) }}. Les hores que excedeixen el crèdit no tenen la retribució garantida.</div>
      </div>

      <!-- Nou registre -->
      <div class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title">Registrar hores</h3>
        </div>
        <form class="comite-form" @submit.prevent="desa">
          <div class="form-group">
            <label class="form-label">Data</label>
            <input v-model="form.data" type="date" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">Inici</label>
            <input v-model="form.hora_inici" type="time" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">Fi</label>
            <input v-model="form.hora_fi" type="time" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">Tipus</label>
            <select v-model="form.tipus" class="form-select" required>
              <option v-for="(nom, clau) in tipus" :key="clau" :value="clau">{{ nom }}</option>
            </select>
          </div>
          <div class="form-group comite-ample">
            <label class="form-label">Motiu</label>
            <textarea v-model="form.motiu" class="form-textarea" rows="2" required
              placeholder="Ex.: reunió ordinària del comitè per preparar la negociació del calendari"></textarea>
          </div>
          <div class="form-group comite-ample">
            <label class="form-label">Justificant (opcional: convocatòria, acta…)</label>
            <input ref="fitxer" type="file" accept=".pdf,.jpg,.jpeg,.png" class="form-input" />
          </div>
          <div class="comite-ample" style="display:flex;align-items:center;gap:12px;">
            <button class="btn btn-accent" :disabled="desant">{{ desant ? 'Desant…' : 'Registrar' }}</button>
            <span v-if="minutsForm > 0" class="text-small text-muted">
              {{ hm(minutsForm / 60) }}{{ noConsumeix.includes(form.tipus) ? " · no es descompta del crèdit" : '' }}
            </span>
          </div>
        </form>
      </div>

      <!-- Registres del mes -->
      <div class="card">
        <h3 class="card-title mb-md">Registres del mes</h3>
        <div v-if="hores.length === 0" class="empty-state"><p>Encara no hi ha hores registrades aquest mes</p></div>
        <div v-else class="table-container">
          <table>
            <thead><tr><th>Data</th><th>Franja</th><th>Hores</th><th>Tipus</th><th>Motiu</th><th>Estat</th><th></th></tr></thead>
            <tbody>
              <tr v-for="h in hores" :key="h.id">
                <td>{{ dmy(h.data) }}</td>
                <td class="text-small">{{ h.hora_inici.slice(0, 5) }}–{{ h.hora_fi.slice(0, 5) }}</td>
                <td>{{ hm(h.minuts / 60) }}</td>
                <td class="text-small">{{ tipus[h.tipus] }}<span v-if="!h.consumeix_credit" class="text-muted"> (no descompta)</span></td>
                <td class="text-small">{{ h.motiu }}</td>
                <td>
                  <span class="badge" :class="badge(h.estat)">{{ etiquetaEstat(h.estat) }}</span>
                  <div v-if="h.estat === 'rebutjada' && h.motiu_rebuig" class="text-small" style="color:var(--color-danger);margin-top:4px;">{{ h.motiu_rebuig }}</div>
                </td>
                <td style="white-space:nowrap;">
                  <a v-if="h.justificant_name" href="#" class="text-small" @click.prevent="baixa(h)">📎</a>
                  <button v-if="h.estat === 'pendent'" class="btn btn-sm btn-outline" style="margin-left:6px;" @click="retira(h)">Retirar</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, onMounted } from 'vue'
import { useEstatUrl } from '../composables/useEstatUrl'
import api, { apiFetchRaw } from '../services/apiClient'

const mes = ref(new Date().toISOString().slice(0, 7))
useEstatUrl('mes', mes)
const membre = ref(null)
const resum = ref({})
const tipus = ref({})
const noConsumeix = ref([])
const hores = ref([])
const loading = ref(true)
const error = ref('')
const desant = ref(false)
const fitxer = ref(null)

const buit = () => ({ data: new Date().toISOString().slice(0, 10), hora_inici: '', hora_fi: '', tipus: 'reunio_comite', motiu: '' })
const form = ref(buit())

const minutsForm = computed(() => {
  const { hora_inici: a, hora_fi: b } = form.value
  if (!a || !b) return 0
  const m = (s) => Number(s.slice(0, 2)) * 60 + Number(s.slice(3, 5))
  return Math.max(0, m(b) - m(a))
})

function hm (h) {
  const neg = h < 0
  const total = Math.round(Math.abs(Number(h) || 0) * 60)
  return `${neg ? '−' : ''}${Math.floor(total / 60)}h ${String(total % 60).padStart(2, '0')}m`
}
function dmy (d) { return d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—' }
function badge (e) { return e === 'validada' ? 'badge-success' : e === 'rebutjada' ? 'badge-danger' : 'badge-pending' }
function etiquetaEstat (e) { return { pendent: 'Pendent', validada: 'Validada', rebutjada: 'Rebutjada' }[e] || e }

async function load () {
  loading.value = true
  error.value = ''
  try {
    const r = await api.get(`/v1/comite/me?mes=${mes.value}`)
    membre.value = r.membre
    resum.value = r.resum || {}
    tipus.value = r.tipus || {}
    noConsumeix.value = r.no_consumeixen || []
    hores.value = r.membre ? await api.get(`/v1/comite/me/hores?mes=${mes.value}`) : []
  } catch (e) {
    error.value = e?.message || "No s'han pogut carregar les hores."
  } finally {
    loading.value = false
  }
}

async function desa () {
  desant.value = true
  error.value = ''
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => fd.append(k, v))
    const f = fitxer.value?.files?.[0]
    if (f) fd.append('justificant', f)
    await api.upload('/v1/comite/hores', fd)
    form.value = { ...buit(), data: form.value.data }
    if (fitxer.value) fitxer.value.value = ''
    await load()
  } catch (e) {
    error.value = e?.message || "No s'han pogut registrar les hores."
  } finally {
    desant.value = false
  }
}

async function retira (h) {
  if (!await confirma(`Retirar el registre del ${dmy(h.data)} (${h.hora_inici.slice(0, 5)}–${h.hora_fi.slice(0, 5)})?`)) return
  try {
    await api.delete(`/v1/comite/hores/${h.id}`)
    await load()
  } catch (e) {
    error.value = e?.message || "No s'ha pogut retirar."
  }
}

async function baixa (h) {
  const resp = await apiFetchRaw(`/v1/comite/hores/${h.id}/justificant`)
  if (!resp.ok) { error.value = "No s'ha pogut baixar el justificant."; return }
  const a = document.createElement('a')
  a.href = URL.createObjectURL(await resp.blob())
  a.download = h.justificant_name || 'justificant'
  a.click()
  URL.revokeObjectURL(a.href)
}

onMounted(load)
</script>

<style scoped>
.comite-resum { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
.comite-resum .stat-card { padding: 16px; }
.comite-xifra { font-size: 1.4rem; font-weight: 700; font-variant-numeric: tabular-nums; }
.comite-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 12px; }
.comite-ample { grid-column: 1 / -1; }
</style>
