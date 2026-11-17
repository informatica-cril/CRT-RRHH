<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">🛎️ Incidències</h1>
        <p class="page-subtitle">El que envien els treballadors des de l'app. Respon aquí mateix: ho veuran a la seva pantalla d'incidències.</p>
      </div>
      <div class="page-actions">
        <div class="incs-filtre">
          <button v-for="f in FILTRES" :key="f.codi" class="incs-f" :class="{ sel: filtre === f.codi }" @click="filtre = f.codi">
            {{ f.nom }}<span v-if="f.codi === 'oberta' && nObertes" class="incs-n">{{ nObertes }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Estat de gestió: filtre ràpid dins del filtre principal -->
    <div v-if="!carregant && incidencies.length" class="incs-estats">
      <button v-for="e in estatsDelFiltre" :key="e.codi" class="incs-xip" :class="[e.codi, { sel: estatTriat === e.codi }]"
        @click="estatTriat = estatTriat === e.codi ? '' : e.codi">
        {{ e.nom }} <b>{{ comptaEstat(e.codi) }}</b>
      </button>
    </div>

    <div v-if="carregant" class="card incs-buit">Carregant…</div>
    <div v-else-if="!visibles.length" class="card incs-buit">
      {{ filtre === 'oberta' ? '✓ Cap incidència oberta.' : 'No hi ha incidències.' }}
    </div>

    <!-- Llista de tiquets: una fila per incidència. El punt vermell és NOMÉS si hi ha una acció per a tu
         (nova, o el treballador ha contestat). La conversa s'obre al tauler de la dreta. -->
    <div v-else class="card incs-taula">
      <table>
        <thead><tr><th></th><th>#</th><th>Assumpte</th><th>Tipus</th><th>Per a</th><th>Estat</th><th>Últim moviment</th></tr></thead>
        <tbody>
          <tr v-for="i in visibles" :key="i.id" :class="{ atendre: calAtendre(i), tancada: i.estat === 'resolta', sel: obertaId === i.id }" @click="obertaId = i.id">
            <td class="incs-punt-col"><span v-if="calAtendre(i)" class="incs-punt" title="Espera la teva resposta"></span></td>
            <td class="text-muted">#{{ i.id }}</td>
            <td>
              <div class="incs-assumpte">{{ i.assumpte }}</div>
              <div class="text-small text-muted">{{ i.user?.name }}</div>
            </td>
            <td class="text-small">{{ tipus(i.tipus) }}</td>
            <td class="text-small">{{ i.destinatari === 'informatica' ? '💻 Informàtica' : '👥 RRHH' }}</td>
            <td><span class="incs-estat-xip" :class="i.estat_gestio">{{ nomEstat(i.estat_gestio) }}</span></td>
            <td class="text-small">
              <div>{{ dataHora(ultimMoviment(i)) }}</div>
              <div class="text-muted">{{ calAtendre(i) ? (i.missatges?.length ? 'Ha contestat el treballador' : 'Sense respondre') : (i.missatges?.length ? 'Última resposta: gestió' : '') }}</div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Tauler del tiquet (dreta) -->
    <div v-if="oberta" class="incs-fons" @click.self="obertaId = null">
      <aside class="incs-tauler" role="dialog" aria-label="Incidència">
        <header class="incs-tauler-cap">
          <div>
            <div class="text-small text-muted">#{{ oberta.id }} · {{ tipus(oberta.tipus) }} · {{ oberta.destinatari === 'informatica' ? '💻 Informàtica' : '👥 RRHH' }} · {{ dataHora(oberta.created_at) }}</div>
            <h2 class="incs-tauler-titol">{{ oberta.assumpte }}</h2>
            <div class="text-small text-muted">{{ oberta.user?.name }}</div>
          </div>
          <button class="icon-btn" @click="obertaId = null" aria-label="Tancar">✕</button>
        </header>
        <div class="incs-tauler-cos">
          <div class="incs-fila-estat">
            <label class="text-small text-muted">Estat</label>
            <select class="incs-estat-sel" :class="oberta.estat_gestio" :value="oberta.estat_gestio" :disabled="desant === oberta.id"
              title="Estat de gestió (només el veu qui les gestiona)" @change="canviaEstat(oberta, $event.target.value)">
              <option v-for="e in ESTATS_INCIDENCIA" :key="e.codi" :value="e.codi">{{ e.nom }}</option>
            </select>
          </div>
          <div class="incs-text">{{ oberta.descripcio }}</div>
          <div v-if="oberta.estat === 'resolta'" class="text-small incs-resolta">
            ✓ {{ oberta.estat_gestio === 'descartada' ? 'Descartada' : 'Resolta' }} per {{ oberta.resolutor?.name || '—' }} el {{ dataHora(oberta.resolta_at) }}<span v-if="oberta.nota_resolucio"> · «{{ oberta.nota_resolucio }}»</span>
          </div>
          <IncidenciaConversa :incidencia="oberta" :placeholder="`Respon a ${oberta.user?.name || 'la persona'}…`" @actualitzada="substitueix" />
        </div>
        <footer class="incs-accions">
          <button v-if="oberta.estat === 'oberta'" class="btn btn-primary btn-sm" :disabled="desant === oberta.id" @click="resol(oberta)">✓ Marcar com a resolta</button>
          <button v-else class="btn btn-outline btn-sm" :disabled="desant === oberta.id" @click="reobre(oberta)">↺ Tornar a obrir</button>
          <button class="btn btn-outline btn-sm" :disabled="desant === oberta.id" @click="redirigeix(oberta)"
            :title="'Passar-la a ' + (oberta.destinatari === 'informatica' ? 'RRHH' : 'Informàtica') + ' si no li tocava a qui l\'ha rebuda'">
            ↪ Redirigir a {{ oberta.destinatari === 'informatica' ? 'RRHH' : 'Informàtica' }}
          </button>
        </footer>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../services/apiClient'
import { demana, confirma } from '../utils/dialegs'
import { avisa } from '../utils/avisos'
import { TIPUS_INCIDENCIA, ESTATS_INCIDENCIA } from '../utils/etiquetes'
import { useEstatUrl } from '../composables/useEstatUrl'
import IncidenciaConversa from '../components/IncidenciaConversa.vue'

const FILTRES = [{ codi: 'oberta', nom: 'Obertes' }, { codi: 'resolta', nom: 'Resoltes' }, { codi: '', nom: 'Totes' }]
const incidencies = ref([])
const carregant = ref(true)
const desant = ref(null)
const filtre = ref('oberta')
useEstatUrl('estat', filtre)

const estatTriat = ref('')
useEstatUrl('gestio', estatTriat)
const delFiltre = computed(() => incidencies.value.filter(i => !filtre.value || i.estat === filtre.value))
const visibles = computed(() => delFiltre.value.filter(i => !estatTriat.value || i.estat_gestio === estatTriat.value))
// Els estats que tenen sentit dins del filtre principal (obertes → nova/en curs/esperant).
const estatsDelFiltre = computed(() => ESTATS_INCIDENCIA.filter(e => !filtre.value || e.oberta === (filtre.value === 'oberta')))
const comptaEstat = (codi) => delFiltre.value.filter(i => i.estat_gestio === codi).length
// Canviar entre obertes i resoltes treu el filtre d'estat (un estat obert no té res a les resoltes).
watch(filtre, () => { estatTriat.value = '' })
const obertaId = ref(null)
const oberta = computed(() => incidencies.value.find(i => i.id === obertaId.value) || null)
const nomEstat = (codi) => ESTATS_INCIDENCIA.find(e => e.codi === codi)?.nom || codi
// Espera una acció de gestió: nova sense resposta, o l'últim missatge és del propi treballador (i no s'està esperant el treballador).
function calAtendre(i) {
  if (i.estat !== 'oberta' || i.estat_gestio === 'esperant') return false
  const m = i.missatges || []
  return i.estat_gestio === 'nova' || (m.length > 0 && m[m.length - 1].user_id === i.user_id)
}
const ultimMoviment = (i) => (i.missatges?.length ? i.missatges[i.missatges.length - 1].created_at : i.created_at)
const nObertes = computed(() => incidencies.value.filter(i => i.estat === 'oberta').length)

function tipus(codi) {
  const t = TIPUS_INCIDENCIA.find(x => x.codi === codi)
  return t ? `${t.icona} ${t.nom}` : codi
}
// Dates del servidor (created_at en UTC): es mostren en hora local del navegador.
function dataHora(d) {
  return d ? new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''
}

async function carrega() {
  try { incidencies.value = await api.get('/v1/incidencies') } catch (e) { avisa("No s'han pogut carregar les incidències.", 'error') }
  carregant.value = false
}

function substitueix(nova) {
  const k = incidencies.value.findIndex(x => x.id === nova.id)
  if (k >= 0) incidencies.value[k] = nova
}

async function resol(i) {
  const nota = await demana('Nota de resolució (opcional): què s\'ha fet?', { obligatori: false, placeholder: 'Opcional' })
  if (nota === null) return
  desant.value = i.id
  try { substitueix(await api.post(`/v1/incidencies/${i.id}/resol`, { nota: nota || null })) }
  catch (e) { avisa(e?.message || "No s'ha pogut desar.", 'error') }
  desant.value = null
}

async function canviaEstat(i, estat) {
  if (estat === i.estat_gestio) return
  desant.value = i.id
  try { substitueix(await api.post(`/v1/incidencies/${i.id}/estat`, { estat })) }
  catch (e) { avisa(e?.message || "No s'ha pogut canviar l'estat.", 'error') }
  desant.value = null
}

async function redirigeix(i) {
  const a = i.destinatari === 'informatica' ? 'rrhh' : 'informatica'
  const nom = a === 'rrhh' ? 'RRHH' : 'Informàtica'
  if (!await confirma(`Passar la incidència #${i.id} a ${nom}? Hi tornarà a sortir com a nova i potser deixaràs de veure-la.`)) return
  desant.value = i.id
  try {
    await api.post(`/v1/incidencies/${i.id}/redirigeix`, { destinatari: a })
    avisa(`↪ Incidència passada a ${nom}.`)
    obertaId.value = null
    await carrega()
  } catch (e) { avisa(e?.message || "No s'ha pogut redirigir.", 'error') }
  desant.value = null
}

async function reobre(i) {
  desant.value = i.id
  try { substitueix(await api.post(`/v1/incidencies/${i.id}/reobre`)) }
  catch (e) { avisa(e?.message || "No s'ha pogut desar.", 'error') }
  desant.value = null
}

onMounted(carrega)
</script>

<style scoped>
.incs-filtre { display: inline-flex; background: var(--color-bg-card); border: 1px solid var(--color-border); border-radius: 999px; padding: 3px; }
.incs-f { border: 0; background: none; padding: 6px 14px; border-radius: 999px; cursor: pointer; font-size: 0.88rem; color: var(--color-text); }
.incs-f.sel { background: var(--color-primary); color: #fff; }
.incs-n { margin-left: 6px; background: #d97706; color: #fff; border-radius: 999px; padding: 0 7px; font-size: 0.75rem; font-weight: 700; }
.incs-buit { padding: 28px; text-align: center; color: var(--color-text-secondary); }
.incs-llista { display: flex; flex-direction: column; gap: 12px; }
.incs-taula { padding: 0; overflow: hidden; }
.incs-taula table { width: 100%; border-collapse: collapse; font-size: .9rem; }
.incs-taula th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748B; padding: 10px 12px; background: #f4f7fb; }
.incs-taula td { padding: 10px 12px; border-top: 1px solid var(--color-border-light, #eef2f7); vertical-align: middle; }
.incs-taula tbody tr { cursor: pointer; }
.incs-taula tbody tr:hover, .incs-taula tbody tr.sel { background: var(--color-bg-hover, #f1f5f9); }
.incs-taula tr.atendre .incs-assumpte { font-weight: 800; }
.incs-taula tr.tancada { opacity: .7; }
.incs-punt-col { width: 22px; text-align: center; }
.incs-punt { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--color-danger, #e5484d); }
.incs-estat-xip { font-size: .76rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; white-space: nowrap; }
.incs-fons { position: fixed; inset: 0; background: rgba(15, 35, 60, .35); z-index: 900; display: flex; justify-content: flex-end; }
.incs-tauler { width: min(620px, 100vw); height: 100%; background: var(--color-surface, #fff); box-shadow: -12px 0 32px rgba(15, 35, 60, .2); display: flex; flex-direction: column; }
.incs-tauler-cap { display: flex; justify-content: space-between; gap: 12px; padding: 18px 20px 12px; border-bottom: 1px solid var(--color-border-light, #eef2f7); }
.incs-tauler-titol { font-size: 1.15rem; margin: 4px 0 2px; }
.incs-tauler-cos { flex: 1; overflow: auto; padding: 14px 20px; }
.incs-fila-estat { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.incs-tauler .incs-accions { padding: 12px 20px; border-top: 1px solid var(--color-border-light, #eef2f7); margin: 0; }
@media (max-width: 900px) { .incs-taula th:nth-child(4), .incs-taula td:nth-child(4), .incs-taula th:nth-child(5), .incs-taula td:nth-child(5) { display: none; } }
.incs-item { padding: 16px 18px; border-left: 4px solid #d97706; }
.incs-item.resolta { border-left-color: var(--color-success, #16a34a); opacity: 0.85; background: var(--color-bg-card); color: inherit; }
.incs-cap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.incs-tipus { font-size: 0.8rem; font-weight: 600; background: var(--color-bg-hover); border-radius: 999px; padding: 3px 10px; }
.incs-estat-sel { font-size: 0.78rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; border: 1px solid transparent; cursor: pointer; }
.incs-estats { display: flex; flex-wrap: wrap; gap: 8px; margin: -4px 0 12px; }
.incs-xip { border: 1px solid transparent; border-radius: 999px; padding: 4px 12px; font-size: 0.82rem; cursor: pointer; }
.incs-xip b { margin-left: 4px; }
.incs-xip.sel { border-color: currentColor; box-shadow: 0 0 0 1px currentColor; }
.nova { background: #fdecc8; color: #92400e; }
.en_curs { background: #dbeafe; color: #1e40af; }
.esperant { background: #ede9fe; color: #5b21b6; }
.resolta { background: #dcfce7; color: #166534; }
.descartada { background: #f1f5f9; color: #475569; }
.incs-data { margin-left: auto; }
.incs-assumpte { font-weight: 700; font-size: 1rem; margin-bottom: 2px; }
.incs-text { white-space: pre-wrap; margin: 10px 0; padding: 10px 12px; background: var(--color-bg); border-radius: 8px; font-size: 0.92rem; line-height: 1.5; }
.incs-resolta { color: #15803d; margin-bottom: 8px; }
.incs-accions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; margin-top: 10px; }
</style>
