<template>
  <div class="politica">
    <div class="page-head">
      <h1>Segon factor d'autenticació</h1>
      <p class="sub">Qui està obligat a entrar amb un segon factor. Ho decideix Direcció.</p>
    </div>

    <div v-if="carregant" class="buit">Carregant…</div>
    <div v-else-if="error" class="avis">{{ error }}</div>

    <template v-else>
      <div v-if="dades.apagat_al_servidor" class="avis fort">
        ⚠️ El segon factor està <strong>apagat al servidor</strong> (kill-switch
        <code>SECOND_FACTOR_OFF</code>). Mentre hi sigui, res del que es triï aquí tindrà
        efecte. Cal que informàtica el retiri.
      </div>

      <!-- Qui es quedaria fora -->
      <div class="card" :class="{ perill: impacte.en_risc_total > 0 }">
        <h2>Abans de decidir: qui es quedaria fora</h2>

        <div class="xifres">
          <div><span class="n">{{ impacte.plantilla_activa }}</span><small>a la plantilla</small></div>
          <div><span class="n verd">{{ impacte.amb_totp_confirmat }}</span><small>ja tenen app configurada</small></div>
          <div><span class="n" :class="impacte.en_risc_total ? 'roig' : 'verd'">{{ impacte.en_risc_total }}</span><small>en risc de quedar fora</small></div>
        </div>

        <p v-if="impacte.en_risc_total" class="explica">
          Aquestes persones tenen com a segon factor la <strong>tauleta corporativa</strong>
          però no són personal domiciliari: el seu segon factor és un aparell que probablement
          no porten. Si s'activa l'obligatorietat, no podran entrar.
          <br />
          <strong>Es resol des de la fitxa de cadascú</strong>, canviant-los a
          «app d'autenticació».
        </p>
        <p v-else class="explica verd">
          Ningú es quedaria fora amb la plantilla tal com està ara.
        </p>

        <table v-if="impacte.per_rol" class="perrol">
          <thead>
            <tr><th>Rol</th><th>Total</th><th>Amb app</th><th>En risc</th></tr>
          </thead>
          <tbody>
            <tr v-for="(d, rol) in impacte.per_rol" :key="rol">
              <td>{{ nomRol(rol) }}</td>
              <td class="num">{{ d.total }}</td>
              <td class="num">{{ d.amb_totp }}</td>
              <td class="num" :class="{ roig: d.en_risc > 0 }">{{ d.en_risc }}</td>
            </tr>
          </tbody>
        </table>

        <details v-if="impacte.en_risc_noms?.length" class="noms">
          <summary>Veure qui són ({{ impacte.en_risc_mostrats }} de {{ impacte.en_risc_total }})</summary>
          <ul>
            <li v-for="p in impacte.en_risc_noms" :key="p.id">
              {{ p.nom }} <span class="meta">· {{ nomRol(p.rol) }} · {{ p.ambit || 'sense àmbit' }}</span>
            </li>
          </ul>
        </details>
      </div>

      <!-- La decisió -->
      <div class="card">
        <h2>La política</h2>

        <label class="opcio">
          <input type="radio" value="off" v-model="mode" :disabled="desant" />
          <span>
            <strong>Ningú obligat</strong>
            <small>Qui vulgui pot configurar-se'l pel seu compte. És on sou ara.</small>
          </span>
        </label>

        <label class="opcio">
          <input type="radio" value="roles" v-model="mode" :disabled="desant" />
          <span>
            <strong>Obligatori només per als rols que triï</strong>
            <small>Per començar pels comptes amb més abast sense tocar tothom el mateix dia.</small>
          </span>
        </label>

        <div v-if="mode === 'roles'" class="rols">
          <label v-for="r in ROLS" :key="r.id">
            <input type="checkbox" :value="r.id" v-model="rols" :disabled="desant" />
            {{ r.nom }}
            <span class="meta" v-if="impacte.per_rol?.[r.id]">
              ({{ impacte.per_rol[r.id].en_risc }} en risc)
            </span>
          </label>
        </div>

        <label class="opcio">
          <input type="radio" value="all" v-model="mode" :disabled="desant" />
          <span>
            <strong>Obligatori per a tota la plantilla</strong>
            <small v-if="impacte.en_risc_total" class="roig">
              Ara mateix això deixaria {{ impacte.en_risc_total }} persones fora.
            </small>
          </span>
        </label>

        <div class="accions">
          <button class="btn btn-primary" :disabled="desant || !hiHaCanvi" @click="desa">
            {{ desant ? 'Desant…' : 'Aplicar la política' }}
          </button>
          <button v-if="hiHaCanvi" class="btn btn-outline" :disabled="desant" @click="reverteix">
            Desfer
          </button>
          <span v-if="msg" class="msg" :class="{ roig: !ok }">{{ msg }}</span>
        </div>

        <p v-if="dades.changed_by" class="meta peu">
          Última decisió: {{ dades.changed_by }} · {{ formataData(dades.changed_at) }}
        </p>
      </div>
    </template>
  </div>
</template>

<script setup>
/**
 * Política de segon factor — Direcció, 01-08-2026: «vull decidir-ho jo».
 *
 * Abans vivia al .env i el comandament el tenia informàtica.
 *
 * La pantalla ensenya l'IMPACTE abans que la decisió, i no al revés, a posta: el mètode per
 * defecte de tothom és «tauleta corporativa», i per a qui treballa des d'un ordinador això
 * vol dir que el seu segon factor és un aparell que no porta. Una pantalla que només digués
 * «obligatori: sí/no» convidaria a prémer el botó i descobrir-ho l'endemà.
 */
import { ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'

const ROLS = [
  { id: 'admin', nom: 'Administració' },
  { id: 'hr', nom: 'Recursos Humans' },
  { id: 'coordinator', nom: 'Coordinació' },
  { id: 'worker', nom: 'Treballadors' }
]

const carregant = ref(true)
const desant = ref(false)
const error = ref('')
const msg = ref('')
const ok = ref(true)

const dades = ref({})
const impacte = ref({})
const mode = ref('off')
const rols = ref([])

/* El que hi havia quan es va carregar, per saber si hi ha canvi pendent i poder desfer. */
const original = ref({ mode: 'off', rols: [] })

const hiHaCanvi = computed(() =>
  mode.value !== original.value.mode ||
  JSON.stringify([...rols.value].sort()) !== JSON.stringify([...original.value.rols].sort()))

function nomRol (id) {
  return ROLS.find(r => r.id === id)?.nom || id
}

function formataData (d) {
  if (!d) return ''
  const x = new Date(d)
  return isNaN(x) ? d : x.toLocaleString('ca-ES')
}

function aplica (d) {
  dades.value = d
  impacte.value = d.impacte || {}
  mode.value = d.mode || 'off'
  rols.value = [...(d.roles || [])]
  original.value = { mode: mode.value, rols: [...rols.value] }
}

function reverteix () {
  mode.value = original.value.mode
  rols.value = [...original.value.rols]
  msg.value = ''
}

async function carrega () {
  carregant.value = true
  error.value = ''
  try {
    aplica(await api.get('/v1/seguretat/segon-factor'))
  } catch (e) {
    error.value = e?.status === 403
      ? 'Aquesta pantalla és només per a Administració.'
      : (e?.message || 'No s\'ha pogut carregar la política.')
  } finally {
    carregant.value = false
  }
}

async function desa () {
  /* Si la decisió deixa gent fora, es demana confirmació amb la xifra a la vista. No és
     paternalisme: aquí el botó no fa un canvi de configuració, impedeix entrar a persones
     concretes que estan treballant. */
  const enRisc = impacte.value.en_risc_total || 0
  if (enRisc > 0 && (mode.value === 'all' ||
      (mode.value === 'roles' && rols.value.some(r => (impacte.value.per_rol?.[r]?.en_risc || 0) > 0)))) {
    const quants = mode.value === 'all'
      ? enRisc
      : rols.value.reduce((s, r) => s + (impacte.value.per_rol?.[r]?.en_risc || 0), 0)
    if (!confirm(`Amb aquesta política, ${quants} persona(es) no podran entrar a l'aplicació ` +
                 'fins que se les canviï a «app d\'autenticació» des de la seva fitxa.\n\n' +
                 'Voleu aplicar-la igualment?')) { return }
  }

  desant.value = true
  msg.value = ''
  try {
    const r = await api.put('/v1/seguretat/segon-factor', { mode: mode.value, roles: rols.value })
    aplica({ ...dades.value, mode: r.mode, roles: r.roles, impacte: r.impacte,
             changed_by: 'ara mateix', changed_at: new Date().toISOString() })
    ok.value = true
    msg.value = 'Política aplicada.'
    await carrega()
  } catch (e) {
    ok.value = false
    msg.value = e?.data?.error || e?.message || 'No s\'ha pogut aplicar.'
  } finally {
    desant.value = false
  }
}

onMounted(carrega)
</script>

<style scoped>
.page-head h1 { margin: 0 0 .25rem; font-size: 1.35rem; }
.page-head .sub { margin: 0 0 1rem; color: #64748b; font-size: .85rem; }

.card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 1.1rem; margin-bottom: 1rem; }
.card.perill { border-left: 4px solid #f59e0b; }
.card h2 { margin: 0 0 .8rem; font-size: 1rem; color: #334155; }

.xifres { display: flex; gap: 2.5rem; margin-bottom: .8rem; }
.xifres .n { font-size: 1.8rem; font-weight: 700; display: block; line-height: 1.1; }
.xifres small { color: #94a3b8; font-size: .75rem; }
.verd { color: #047857; }
.roig { color: #be123c; }

.explica { font-size: .85rem; color: #475569; margin: 0 0 .8rem; }
.explica.verd { color: #047857; }

.perrol { width: 100%; border-collapse: collapse; font-size: .82rem; margin-bottom: .6rem; }
.perrol th { text-align: left; color: #94a3b8; font-weight: 600; font-size: .72rem; text-transform: uppercase; padding: .3rem .5rem; }
.perrol td { padding: .3rem .5rem; border-top: 1px solid #f1f5f9; }
.perrol .num { text-align: right; font-variant-numeric: tabular-nums; }

.noms summary { cursor: pointer; font-size: .82rem; color: #475569; }
.noms ul { margin: .5rem 0 0; padding-left: 1.2rem; font-size: .82rem; }
.noms li { margin-bottom: .15rem; }
.meta { color: #94a3b8; font-size: .78rem; }

.opcio { display: flex; gap: .6rem; align-items: flex-start; padding: .5rem 0; cursor: pointer; }
.opcio strong { display: block; font-size: .9rem; color: #0f172a; }
.opcio small { display: block; color: #94a3b8; font-size: .78rem; }
.rols { margin: 0 0 .4rem 1.8rem; display: flex; flex-wrap: wrap; gap: 1rem; font-size: .85rem; }
.rols label { display: flex; align-items: center; gap: .35rem; cursor: pointer; }

.accions { display: flex; align-items: center; gap: .6rem; margin-top: .8rem; }
.msg { font-size: .82rem; color: #94a3b8; }
.peu { margin: .6rem 0 0; }

.avis { background: #fffbeb; border-left: 4px solid #f59e0b; color: #92400e; padding: .8rem 1rem; border-radius: 8px; font-size: .85rem; margin-bottom: 1rem; }
.avis.fort { background: #fff1f2; border-color: #be123c; color: #9f1239; }
.buit { padding: 2rem; text-align: center; color: #94a3b8; }
</style>
