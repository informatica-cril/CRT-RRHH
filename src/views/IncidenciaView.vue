<template>
  <div class="inc">
    <div class="page-header">
      <div>
        <h1 class="page-title">🛎️ Informar d'una incidència</h1>
        <p class="page-subtitle">Explica què passa i RRHH se n'encarregarà. Si cal, et respondran per correu.</p>
      </div>
    </div>

    <div v-if="enviada" class="card inc-ok">
      <div class="inc-ok-ico">✅</div>
      <h3>Incidència enviada</h3>
      <p class="text-muted">RRHH ja l'ha rebuda. Si necessiten més informació, et contactaran.</p>
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
  </div>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import api from '../services/apiClient'
import { TIPUS_INCIDENCIA as TIPUS } from '../utils/etiquetes'

const form = reactive({ tipus: '', assumpte: '', descripcio: '' })
const enviant = ref(false)
const enviada = ref(false)
const error = ref('')
const potEnviar = computed(() => form.tipus && form.assumpte.trim() && form.descripcio.trim().length >= 10)

async function envia() {
  if (!potEnviar.value) return
  enviant.value = true
  error.value = ''
  try {
    await api.post('/v1/incidencies', { tipus: form.tipus, assumpte: form.assumpte.trim(), descripcio: form.descripcio.trim() })
    enviada.value = true
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
</style>
