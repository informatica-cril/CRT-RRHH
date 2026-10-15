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

    <div v-else class="incs-llista">
      <div v-for="i in visibles" :key="i.id" class="card incs-item" :class="{ resolta: i.estat === 'resolta' }">
        <div class="incs-cap">
          <span class="incs-tipus">{{ tipus(i.tipus) }}</span>
          <select class="incs-estat-sel" :class="i.estat_gestio" :value="i.estat_gestio" :disabled="desant === i.id"
            title="Estat de gestió (només el veuen RRHH i Superior)" @change="canviaEstat(i, $event.target.value)">
            <option v-for="e in ESTATS_INCIDENCIA" :key="e.codi" :value="e.codi">{{ e.nom }}</option>
          </select>
          <span class="text-small text-muted incs-data">{{ dataHora(i.created_at) }}</span>
        </div>
        <div class="incs-assumpte">{{ i.assumpte }}</div>
        <div class="text-small text-muted">
          {{ i.user?.name }}
        </div>
        <div class="incs-text">{{ i.descripcio }}</div>
        <div v-if="i.estat === 'resolta'" class="text-small incs-resolta">
          ✓ {{ i.estat_gestio === 'descartada' ? 'Descartada' : 'Resolta' }} per {{ i.resolutor?.name || '—' }} el {{ dataHora(i.resolta_at) }}<span v-if="i.nota_resolucio"> · «{{ i.nota_resolucio }}»</span>
        </div>
        <IncidenciaConversa :incidencia="i" :placeholder="`Respon a ${i.user?.name || 'la persona'}…`" @actualitzada="substitueix" />
        <div class="incs-accions">
          <button v-if="i.estat === 'oberta'" class="btn btn-primary btn-sm" :disabled="desant === i.id" @click="resol(i)">✓ Marcar com a resolta</button>
          <button v-else class="btn btn-outline btn-sm" :disabled="desant === i.id" @click="reobre(i)">↺ Tornar a obrir</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../services/apiClient'
import { demana } from '../utils/dialegs'
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
