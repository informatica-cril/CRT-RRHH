<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Comitè d'empresa</h1>
        <p class="page-subtitle">
          Representants, crèdit horari (art. 68.e ET) i validació de les hores que registren.
        </p>
      </div>
      <div class="page-actions">
        <input v-if="pestanya !== 'membres'" v-model="mes" type="month" class="form-input" @change="carrega" />
      </div>
    </div>

    <div class="comite-tabs mb-lg">
      <button v-for="p in pestanyes" :key="p.clau" class="btn btn-sm" :class="pestanya === p.clau ? 'btn-primary' : 'btn-outline'" @click="canvia(p.clau)">
        {{ p.nom }}<span v-if="p.clau === 'hores' && pendents > 0" class="comite-n">{{ pendents }}</span>
      </button>
    </div>

    <div v-if="error" class="card mb-lg" style="border-left:4px solid var(--color-danger);">
      <div class="text-small" style="color:var(--color-danger);">{{ error }}</div>
    </div>

    <!-- ═══ HORES ═══ -->
    <div v-if="pestanya === 'hores'" class="card">
      <div class="card-header">
        <h3 class="card-title">Hores registrades</h3>
        <select v-model="filtreEstat" class="form-select" style="max-width:200px;" @change="carrega">
          <option value="">Tots els estats</option>
          <option value="pendent">Pendents</option>
          <option value="validada">Validades</option>
          <option value="rebutjada">Rebutjades</option>
        </select>
      </div>
      <div v-if="hores.length === 0" class="empty-state"><p>No hi ha registres amb aquest filtre</p></div>
      <div v-else class="table-container">
        <table>
          <thead><tr><th>Representant</th><th>Data</th><th>Franja</th><th>Hores</th><th>Tipus</th><th>Motiu</th><th>Estat</th><th></th></tr></thead>
          <tbody>
            <tr v-for="h in hores" :key="h.id">
              <td><strong>{{ h.user?.name }}</strong><div class="text-small text-muted">{{ h.user?.dni }}</div></td>
              <td>{{ dmy(h.data) }}</td>
              <td class="text-small">{{ h.hora_inici.slice(0, 5) }}–{{ h.hora_fi.slice(0, 5) }}</td>
              <td>{{ hm(h.minuts / 60) }}</td>
              <td class="text-small">{{ tipusHores[h.tipus] || h.tipus }}<span v-if="!h.consumeix_credit" class="text-muted"> (no descompta)</span></td>
              <td class="text-small" style="max-width:280px;">
                {{ h.motiu }}
                <div v-if="h.justificant_name"><a href="#" @click.prevent="baixa(h)">📎 {{ h.justificant_name }}</a></div>
              </td>
              <td>
                <span class="badge" :class="badge(h.estat)">{{ etiquetaEstat(h.estat) }}</span>
                <div v-if="h.validador" class="text-small text-muted">{{ h.validador.name }}</div>
                <div v-if="h.motiu_rebuig" class="text-small" style="color:var(--color-danger);">{{ h.motiu_rebuig }}</div>
              </td>
              <td style="white-space:nowrap;">
                <template v-if="h.estat === 'pendent'">
                  <button class="btn btn-sm btn-success" @click="valida(h)">Validar</button>
                  <button class="btn btn-sm btn-danger" style="margin-left:6px;" @click="rebutja(h)">Rebutjar</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ RESUM ═══ -->
    <div v-if="pestanya === 'resum'" class="card">
      <h3 class="card-title mb-md">Crèdit horari del mes</h3>
      <div v-if="resum.length === 0" class="empty-state"><p>No hi ha representants amb mandat aquest mes</p></div>
      <div v-else class="table-container">
        <table>
          <thead><tr><th>Representant</th><th>Càrrec</th><th>Crèdit</th><th>Validades</th><th>Pendents</th><th>Convocades empresa</th><th>Restants</th></tr></thead>
          <tbody>
            <tr v-for="f in resum" :key="f.membre.id">
              <td><strong>{{ f.membre.user?.name }}</strong><div class="text-small text-muted">{{ f.membre.user?.dni }}</div></td>
              <td class="text-small">{{ carrecs[f.membre.carrec] }}<span v-if="f.membre.sindicat"> · {{ f.membre.sindicat }}</span></td>
              <td>{{ hm(f.credit) }}</td>
              <td style="color:var(--color-success);">{{ hm(f.validades) }}</td>
              <td style="color:#D9A400;">{{ hm(f.pendents) }}</td>
              <td class="text-muted">{{ hm(f.convocades) }}</td>
              <td :style="f.restants < 0 ? 'color:var(--color-danger);font-weight:700;' : 'font-weight:700;'">{{ hm(f.restants) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ MEMBRES ═══ -->
    <template v-if="pestanya === 'membres'">
      <div class="card mb-lg">
        <div class="card-header">
          <h3 class="card-title">{{ editant ? 'Editar mandat' : 'Afegir representant' }}</h3>
          <span class="text-small text-muted">Escala legal amb {{ creditLegal.plantilla }} persones en plantilla laboral: {{ creditLegal.hores }} h/mes</span>
        </div>
        <form class="comite-form" @submit.prevent="desaMembre">
          <div class="form-group comite-doble">
            <label class="form-label">Persona</label>
            <SelectorPersona v-model="form.user_id" :persones="usuaris" detall="dni" :disabled="!!editant" style="width:100%;" />
          </div>
          <div class="form-group">
            <label class="form-label">Representació</label>
            <select v-model="form.tipus_representacio" class="form-select" required>
              <option v-for="(nom, clau) in tipusMembre" :key="clau" :value="clau">{{ nom }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Càrrec</label>
            <select v-model="form.carrec" class="form-select" required>
              <option v-for="(nom, clau) in carrecs" :key="clau" :value="clau">{{ nom }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sindicat</label>
            <input v-model="form.sindicat" class="form-input" placeholder="Opcional" />
          </div>
          <div class="form-group">
            <label class="form-label">Alta</label>
            <input v-model="form.data_alta" type="date" class="form-input" required />
          </div>
          <div class="form-group">
            <label class="form-label">Baixa</label>
            <input v-model="form.data_baixa" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Crèdit (h/mes)</label>
            <input v-model="form.credit_hores_mensual" type="number" min="0" step="0.5" class="form-input" required />
          </div>
          <div class="form-group comite-ample">
            <label class="form-label">Notes</label>
            <input v-model="form.notes" class="form-input" placeholder="Ex.: elegit/da a les eleccions del 12/03/2026" />
          </div>
          <div class="comite-ample" style="display:flex;gap:8px;">
            <button class="btn btn-accent" :disabled="desant">{{ desant ? 'Desant…' : (editant ? 'Desar canvis' : 'Afegir') }}</button>
            <button v-if="editant" type="button" class="btn btn-outline" @click="nouMembre">Cancel·lar</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h3 class="card-title mb-md">Representants</h3>
        <div v-if="membres.length === 0" class="empty-state"><p>Encara no hi ha cap representant donat d'alta</p></div>
        <div v-else class="table-container">
          <table>
            <thead><tr><th>Persona</th><th>Representació</th><th>Càrrec</th><th>Sindicat</th><th>Mandat</th><th>Crèdit</th><th></th></tr></thead>
            <tbody>
              <tr v-for="m in membres" :key="m.id" :style="vigent(m) ? '' : 'opacity:.55;'">
                <td><strong>{{ m.user?.name }}</strong><div class="text-small text-muted">{{ m.user?.dni }}</div></td>
                <td class="text-small">{{ tipusMembre[m.tipus_representacio] }}</td>
                <td class="text-small">{{ carrecs[m.carrec] }}</td>
                <td class="text-small">{{ m.sindicat || '—' }}</td>
                <td class="text-small">{{ dmy(m.data_alta) }} → {{ m.data_baixa ? dmy(m.data_baixa) : 'vigent' }}</td>
                <td>{{ hm(m.credit_hores_mensual) }}</td>
                <td style="white-space:nowrap;">
                  <button class="btn btn-sm btn-outline" @click="edita(m)">Editar</button>
                  <button class="btn btn-sm btn-danger" style="margin-left:6px;" @click="esborra(m)">Eliminar</button>
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
import SelectorPersona from '../components/SelectorPersona.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, onMounted } from 'vue'
import { useEstatUrl } from '../composables/useEstatUrl'
import api, { apiFetchRaw } from '../services/apiClient'

const TIPUS_HORES = {
  reunio_comite: 'Reunió del comitè', assemblea: 'Assemblea', negociacio_empresa: "Reunió convocada per l'empresa",
  formacio: 'Formació sindical', gestio: 'Gestions de representació', altres: 'Altres',
}
const pestanyes = [
  { clau: 'hores', nom: 'Hores per validar' },
  { clau: 'resum', nom: 'Resum mensual' },
  { clau: 'membres', nom: 'Representants' },
]

const pestanya = ref('hores')
const mes = ref(new Date().toISOString().slice(0, 7))
useEstatUrl('pestanya', pestanya)
useEstatUrl('mes', mes)
const filtreEstat = ref('pendent')
const error = ref('')
const desant = ref(false)
const pendents = ref(0)

const hores = ref([])
const tipusHores = ref(TIPUS_HORES)
const resum = ref([])
const membres = ref([])
const tipusMembre = ref({})
const carrecs = ref({})
const creditLegal = ref({ plantilla: 0, hores: 15 })
const usuaris = ref([])
const editant = ref(null)

const buit = () => ({
  user_id: '', tipus_representacio: 'comite', carrec: 'vocal', sindicat: '',
  data_alta: new Date().toISOString().slice(0, 10), data_baixa: '',
  credit_hores_mensual: creditLegal.value.hores, notes: '',
})
const form = ref(buit())

function hm (h) {
  const neg = h < 0
  const total = Math.round(Math.abs(Number(h) || 0) * 60)
  return `${neg ? '−' : ''}${Math.floor(total / 60)}h ${String(total % 60).padStart(2, '0')}m`
}
function dmy (d) { return d ? String(d).slice(0, 10).split('-').reverse().join('/') : '—' }
function badge (e) { return e === 'validada' ? 'badge-success' : e === 'rebutjada' ? 'badge-danger' : 'badge-pending' }
function etiquetaEstat (e) { return { pendent: 'Pendent', validada: 'Validada', rebutjada: 'Rebutjada' }[e] || e }
function vigent (m) {
  const avui = new Date().toISOString().slice(0, 10)
  return String(m.data_alta).slice(0, 10) <= avui && (!m.data_baixa || String(m.data_baixa).slice(0, 10) >= avui)
}

async function comptaPendents () {
  try { pendents.value = (await api.get('/v1/comite/hores?estat=pendent')).length } catch { /* el comptador no és crític */ }
}

async function carrega () {
  error.value = ''
  try {
    if (pestanya.value === 'hores') {
      const q = new URLSearchParams({ mes: mes.value })
      if (filtreEstat.value) q.set('estat', filtreEstat.value)
      // Les pendents surten totes, siguin del mes que siguin: que no se n'escapi cap per filtre de data.
      if (filtreEstat.value === 'pendent') q.delete('mes')
      hores.value = await api.get(`/v1/comite/hores?${q}`)
      comptaPendents()
    } else if (pestanya.value === 'resum') {
      resum.value = (await api.get(`/v1/comite/resum?mes=${mes.value}`)).files
    } else {
      const [r, us] = await Promise.all([api.get('/v1/comite/membres'), usuaris.value.length ? usuaris.value : api.get('/v1/users')])
      membres.value = r.membres
      tipusMembre.value = r.tipus
      carrecs.value = r.carrecs
      creditLegal.value = r.credit_legal
      usuaris.value = us.filter(u => u.role !== 'service' && u.active !== false).sort((a, b) => a.name.localeCompare(b.name))
      if (!editant.value && !form.value.user_id) form.value = buit()
    }
  } catch (e) {
    error.value = e?.message || "No s'han pogut carregar les dades."
  }
}

function canvia (p) { pestanya.value = p; carrega() }

async function valida (h) {
  try {
    await api.post(`/v1/comite/hores/${h.id}/valida`)
    await carrega()
  } catch (e) { error.value = e?.message || "No s'ha pogut validar." }
}

async function rebutja (h) {
  const motiu = await demana(`Motiu del rebuig (${h.user?.name}, ${dmy(h.data)}):`, { frases: 'rebuig_comite' })
  if (!motiu) return
  try {
    await api.post(`/v1/comite/hores/${h.id}/rebutja`, { motiu_rebuig: motiu })
    await carrega()
  } catch (e) { error.value = e?.message || "No s'ha pogut rebutjar." }
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

function edita (m) {
  editant.value = m
  form.value = {
    user_id: m.user_id, tipus_representacio: m.tipus_representacio, carrec: m.carrec, sindicat: m.sindicat || '',
    data_alta: String(m.data_alta).slice(0, 10), data_baixa: m.data_baixa ? String(m.data_baixa).slice(0, 10) : '',
    credit_hores_mensual: m.credit_hores_mensual, notes: m.notes || '',
  }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function nouMembre () { editant.value = null; form.value = buit() }

async function esborra (m) {
  if (!await confirma(`Eliminar ${m.user?.name} dels representants?`)) return
  error.value = ''
  try {
    await api.delete(`/v1/comite/membres/${m.id}`)
    if (editant.value?.id === m.id) nouMembre()
    await carrega()
  } catch (e) {
    error.value = e?.message || "No s'ha pogut eliminar."
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

async function desaMembre () {
  // El selector de persona no és un <select required>: cal comprovar-ho aquí.
  if (!form.value.user_id) { error.value = 'Tria una persona.'; return }
  desant.value = true
  error.value = ''
  try {
    const dades = { ...form.value, data_baixa: form.value.data_baixa || null, sindicat: form.value.sindicat || null, notes: form.value.notes || null }
    if (editant.value) await api.put(`/v1/comite/membres/${editant.value.id}`, dades)
    else await api.post('/v1/comite/membres', dades)
    nouMembre()
    await carrega()
  } catch (e) {
    error.value = e?.message || "No s'ha pogut desar."
  } finally {
    desant.value = false
  }
}

onMounted(carrega)
</script>

<style scoped>
.comite-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
.comite-n { display: inline-block; margin-left: 6px; min-width: 20px; padding: 0 6px; border-radius: 99px; background: #B3352F; color: #fff; font-size: .75rem; font-weight: 700; }
.comite-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-top: 12px; }
.comite-doble { grid-column: span 2; }
.comite-ample { grid-column: 1 / -1; }
@media (max-width: 600px) { .comite-doble { grid-column: 1 / -1; } }
</style>
