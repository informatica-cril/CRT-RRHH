<template>
  <div class="inc">
    <div class="page-header">
      <div>
        <h1 class="page-title">🛎️ Incidències</h1>
        <p class="page-subtitle">Explica què passa i RRHH se n'encarregarà. La resposta la veuràs aquí mateix.</p>
      </div>
    </div>

    <div v-if="enviada" class="card inc-ok">
      <div class="inc-ok-ico">✅</div>
      <h3>Incidència enviada</h3>
      <p class="text-muted">RRHH ja l'ha rebuda. Quan et responguin, ho veuràs a «Les meves incidències».</p>
      <button class="btn btn-outline" @click="novaIncidencia">Enviar-ne una altra</button>
    </div>

    <form v-else class="card inc-form" @submit.prevent="envia">
      <label class="form-label">De què es tracta?</label>
      <div class="inc-tipus">
        <button v-for="t in TIPUS" :key="t.codi" type="button" class="inc-xip" :class="{ sel: form.tipus === t.codi }"
          @click="form.tipus = t.codi">{{ t.icona }} {{ t.nom }}</button>
      </div>

      <div class="form-group">
        <label class="form-label" for="inc-assumpte">Assumpte</label>
        <input id="inc-assumpte" v-model="form.assumpte" class="form-input" maxlength="150"
          placeholder="Ex.: No em deixa fitxar la sortida" />
      </div>

      <div class="form-group">
        <label class="form-label" for="inc-text">Explica-ho</label>
        <textarea id="inc-text" v-model="form.descripcio" class="form-input inc-text" rows="6" maxlength="5000"
          placeholder="Què ha passat, quin dia i, si pots, què estaves fent quan ha passat."></textarea>
        <div class="text-small text-muted inc-comptador">{{ form.descripcio.trim().length }} / 5000 · mínim 10 caràcters</div>
      </div>

      <div v-if="error" class="inc-error">{{ error }}</div>

      <div class="inc-accions">
        <button class="btn btn-primary" type="submit" :disabled="!potEnviar || enviant">
          {{ enviant ? 'Enviant…' : 'Enviar a RRHH' }}
        </button>
      </div>
    </form>

    <h2 v-if="meves.length" class="inc-titol">Les meves incidències</h2>
    <div class="inc-meves">
      <div v-for="i in meves" :key="i.id" class="card inc-item" :class="{ resolta: i.estat === 'resolta', nova: i.resposta_nova }">
        <div class="inc-cap">
          <span class="inc-estat" :class="i.estat">{{ i.estat === 'resolta' ? 'Resolta' : 'Oberta' }}</span>
          <span v-if="i.resposta_nova" class="inc-nova">Resposta nova</span>
          <span class="text-small text-muted inc-data">{{ data(i.created_at) }}</span>
        </div>
        <div class="inc-assumpte">{{ i.assumpte }}</div>
        <div class="inc-desc">{{ i.descripcio }}</div>
        <div v-if="i.estat === 'resolta'" class="text-small inc-resolta">
          ✓ Resolta per RRHH<span v-if="i.nota_resolucio">: «{{ i.nota_resolucio }}»</span>
        </div>
        <IncidenciaConversa :incidencia="i" placeholder="Afegeix més informació o contesta…" @actualitzada="substitueix" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref, computed, onMounted } from 'vue'
import api from '../services/apiClient'
import { TIPUS_INCIDENCIA as TIPUS } from '../utils/etiquetes'
import IncidenciaConversa from '../components/IncidenciaConversa.vue'

const form = reactive({ tipus: '', assumpte: '', descripcio: '' })
const enviant = ref(false)
const enviada = ref(false)
const error = ref('')
const meves = ref([])
const potEnviar = computed(() => form.tipus && form.assumpte.trim() && form.descripcio.trim().length >= 10)

function data(d) {
  return d ? new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: '2-digit', year: 'numeric' }) : ''
}

async function carrega() {
  try {
    meves.value = await api.get('/v1/incidencies/meves')
    // Obrir la pantalla és haver-les vist: el comptador del menú es posa a zero (la marca «Resposta nova» es queda fins que surts).
    if (meves.value.some(i => i.resposta_nova)) {
      await api.post('/v1/incidencies/meves/vistes')
      window.dispatchEvent(new Event('incidencies-vistes'))
    }
  } catch (e) { /* sense llista: el formulari segueix funcionant */ }
}

function substitueix(nova) {
  const k = meves.value.findIndex(x => x.id === nova.id)
  if (k >= 0) meves.value[k] = { ...nova, resposta_nova: false }
}

async function envia() {
  if (!potEnviar.value) return
  enviant.value = true
  error.value = ''
  try {
    await api.post('/v1/incidencies', { tipus: form.tipus, assumpte: form.assumpte.trim(), descripcio: form.descripcio.trim() })
    enviada.value = true
    carrega()
  } catch (e) {
    error.value = e?.status === 429
      ? "Has enviat moltes incidències en poca estona. Torna-ho a provar d'aquí a una mica."
      : (e?.message || "No s'ha pogut enviar. Torna-ho a provar.")
  } finally {
    enviant.value = false
  }
}

function novaIncidencia() {
  Object.assign(form, { tipus: '', assumpte: '', descripcio: '' })
  enviada.value = false
}

onMounted(carrega)
</script>

<style scoped>
.inc-form { max-width: 720px; padding: 24px; }
.inc-tipus { display: flex; flex-wrap: wrap; gap: 8px; margin: 6px 0 18px; }
.inc-xip { border: 1px solid var(--color-border); background: var(--color-bg-card); color: var(--color-text); border-radius: 999px; padding: 8px 14px; font-size: 0.88rem; cursor: pointer; }
.inc-xip:hover { background: var(--color-bg-hover); }
.inc-xip.sel, .inc-xip.sel:hover { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.inc-text { width: 100%; min-height: 140px; resize: vertical; line-height: 1.5; }
.inc-comptador { text-align: right; margin-top: 4px; }
.inc-error { background: rgba(220, 38, 38, 0.08); color: #b91c1c; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; font-size: 0.9rem; }
.inc-accions { display: flex; justify-content: flex-end; }
.inc-ok { max-width: 720px; text-align: center; padding: 36px 24px; }
.inc-ok-ico { font-size: 2.4rem; margin-bottom: 8px; }
.inc-ok h3 { margin: 0 0 6px; }
.inc-ok p { margin: 0 0 18px; }
.inc-titol { font-size: 1.1rem; margin: 28px 0 12px; }
.inc-meves { display: flex; flex-direction: column; gap: 12px; max-width: 720px; }
.inc-item { padding: 16px 18px; border-left: 4px solid #d97706; }
.inc-item.resolta { border-left-color: var(--color-success, #16a34a); }
.inc-item.nova { box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.35); }
.inc-cap { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.inc-estat { font-size: 0.75rem; font-weight: 700; border-radius: 999px; padding: 3px 10px; }
.inc-estat.oberta { background: rgba(217, 119, 6, 0.12); color: #b45309; }
.inc-estat.resolta { background: rgba(22, 163, 74, 0.12); color: #15803d; }
.inc-nova { font-size: 0.75rem; font-weight: 700; background: #d97706; color: #fff; border-radius: 999px; padding: 3px 10px; }
.inc-data { margin-left: auto; }
.inc-assumpte { font-weight: 700; }
.inc-desc { white-space: pre-wrap; font-size: 0.9rem; color: var(--color-text-secondary); margin-top: 4px; }
.inc-resolta { color: #15803d; margin-top: 8px; }
</style>
