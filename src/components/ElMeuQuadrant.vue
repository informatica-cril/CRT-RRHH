<template>
  <div class="card mb-md emq">
    <div class="card-header">
      <h3 class="card-title">🗓️ El meu horari del mes</h3>
      <div class="emq-nav">
        <button type="button" class="btn btn-outline btn-sm" @click="mou(-1)" title="Mes anterior">◂</button>
        <strong>{{ nomMes }}</strong>
        <button type="button" class="btn btn-outline btn-sm" @click="mou(1)" title="Mes següent">▸</button>
      </div>
    </div>
    <div v-if="error" class="text-small text-muted">No s'ha pogut carregar el teu horari.</div>
    <template v-else-if="dades">
      <div class="text-small text-muted emq-resum">
        {{ formatHM(dades.minuts / 60) }} previstes aquest mes
        <span v-if="canvis"> · <span class="emq-marca"></span> {{ canvis }} dia{{ canvis === 1 ? '' : 's' }} amb un horari diferent del de sempre</span>
      </div>
      <div class="emq-grid">
        <span v-for="l in ['Dl','Dt','Dc','Dj','Dv','Ds','Dg']" :key="l" class="emq-cap">{{ l }}</span>
        <span v-for="n in buits" :key="'b' + n"></span>
        <div v-for="d in dies" :key="d" class="emq-dia"
          :class="{ avui: d === avui, canviat: dades.dies[d].origen === 'quadrant', lliure: !dades.dies[d].trams.length, festiu: festius[d] }"
          :title="dades.dies[d].nota || ''">
          <div class="emq-num">{{ Number(d.slice(8)) }}</div>
          <div v-if="festius[d]" class="emq-h">Festiu</div>
          <div v-else-if="dades.dies[d].absencia" class="emq-h">Permís</div>
          <template v-else-if="dades.dies[d].trams.length">
            <div v-for="t in dades.dies[d].trams" :key="t.start" class="emq-h">{{ t.start }}–{{ t.end }}</div>
          </template>
          <div v-else class="emq-h">Lliure</div>
        </div>
      </div>
      <ul v-if="notes.length" class="emq-notes">
        <li v-for="n in notes" :key="n.dia"><strong>{{ n.dia.slice(8, 10) }}/{{ n.dia.slice(5, 7) }}</strong>: {{ n.nota }}</li>
      </ul>
    </template>
    <div v-else class="text-small text-muted">Carregant…</div>
  </div>
</template>

<script setup>
// El quadrant del treballador: l'horari de cada dia del mes (el que RRHH ha canviat o, si no, el de sempre).
import { ref, computed, watch, onMounted } from 'vue'
import api from '../services/apiClient'
import { formatHM } from '../utils/formatHours'

const dd = n => String(n).padStart(2, '0')
const ara = new Date()
const avui = `${ara.getFullYear()}-${dd(ara.getMonth() + 1)}-${dd(ara.getDate())}`
const mes = ref(avui.slice(0, 7))
const dades = ref(null)
const error = ref(false)

async function carrega() {
  dades.value = null
  error.value = false
  try {
    dades.value = await api.get(`/v1/el-meu-quadrant?mes=${mes.value}`)
  } catch {
    error.value = true
  }
}
onMounted(carrega)
watch(mes, carrega)

function mou(n) {
  const [a, m] = mes.value.split('-').map(Number)
  const d = new Date(a, m - 1 + n, 1)
  mes.value = `${d.getFullYear()}-${dd(d.getMonth() + 1)}`
}
const nomMes = computed(() => {
  const [a, m] = mes.value.split('-').map(Number)
  const t = new Date(a, m - 1, 1).toLocaleDateString('ca-ES', { month: 'long', year: 'numeric' })
  return t.charAt(0).toUpperCase() + t.slice(1)
})
const dies = computed(() => Object.keys(dades.value?.dies || {}).sort())
const festius = computed(() => (Array.isArray(dades.value?.festius) ? {} : dades.value?.festius || {}))
const buits = computed(() => (new Date(`${mes.value}-01T12:00:00`).getDay() + 6) % 7)
const canvis = computed(() => dies.value.filter(d => dades.value.dies[d].origen === 'quadrant').length)
const notes = computed(() => dies.value.filter(d => dades.value.dies[d].nota).map(d => ({ dia: d, nota: dades.value.dies[d].nota })))
</script>

<style scoped>
.emq-nav { display: flex; align-items: center; gap: 8px; }
.emq-resum { margin: 4px 0 10px; }
.emq-marca { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #D9A400; }
.emq-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
.emq-cap { text-align: center; font-size: .7rem; font-weight: 700; color: var(--color-text-muted, #64748b); }
.emq-dia { min-height: 58px; border: 1px solid var(--color-border-light, #eef2f7); border-radius: 8px; padding: 4px; font-size: .7rem; background: var(--color-surface, #fff); }
.emq-num { font-weight: 700; font-size: .78rem; }
.emq-h { font-weight: 600; color: var(--color-primary, #094E8C); line-height: 1.3; word-break: break-word; }
.emq-dia.lliure .emq-h { color: var(--color-text-muted, #94a3b8); font-weight: 500; }
.emq-dia.festiu { background: #fdecea; }
.emq-dia.festiu .emq-h { color: #B3352F; }
.emq-dia.canviat { border-color: #D9A400; box-shadow: inset 0 0 0 1px #D9A400; background: #FFFBEB; }
.emq-dia.avui { outline: 2px solid var(--color-primary, #094E8C); outline-offset: -1px; }
.emq-notes { margin: 10px 0 0; padding-left: 18px; font-size: .8rem; }
@media (max-width: 480px) {
  .emq-dia { min-height: 50px; padding: 2px; font-size: .6rem; }
}
</style>
