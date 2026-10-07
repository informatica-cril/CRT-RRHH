<template>
  <div v-if="jornades.length" class="card avis-ss">
    <h3 class="card-title">🚪 Jornades sense sortida fitxada</h3>
    <p class="text-small text-muted" style="margin:4px 0 10px;">
      Aquests dies no vas fitxar la sortida i la jornada compta 0 hores. El registre no es pot canviar,
      però pots declarar a quina hora vas acabar: RRHH ho revisarà.
    </p>

    <div v-for="j in jornades" :key="j.id" class="avis-ss-fila">
      <div class="avis-ss-cap">
        <strong>{{ dataLlarga(j.date) }}</strong>
        <span class="text-small text-muted">· entrada a les {{ hora(j.start_time) }}</span>
      </div>

      <div v-if="j.declaracio" class="avis-ss-feta">
        ✓ Has declarat la sortida a les <strong>{{ hora(j.declaracio.sortida) }}</strong>
        <span v-if="dia(j.declaracio.sortida) !== j.date"> del {{ dataCurta(j.declaracio.sortida) }}</span>.
        Pendent de revisió de RRHH.
      </div>

      <div v-else class="avis-ss-form">
        <label class="text-small">Hora de sortida
          <input type="datetime-local" class="form-input" v-model="form[j.id].sortida" />
        </label>
        <textarea class="form-input" rows="2" v-model="form[j.id].explicacio"
          placeholder="Explica breument què va passar (mínim 10 caràcters)"></textarea>
        <div v-if="form[j.id].error" class="avis-ss-error">{{ form[j.id].error }}</div>
        <button class="btn btn-primary btn-sm" :disabled="form[j.id].enviant || form[j.id].explicacio.trim().length < 10 || !form[j.id].sortida"
          @click="declara(j)">{{ form[j.id].enviant ? 'Enviant…' : 'Declarar la sortida' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import api from '../services/apiClient'

const emit = defineEmits(['carregat'])
const jornades = ref([])
const form = reactive({})

// Les hores arriben en hora de Madrid sense zona ('2026-10-02 08:27:00'): es llegeixen del text.
const hora = (t) => String(t || '').slice(11, 16)
const dia = (t) => String(t || '').slice(0, 10)
const dataCurta = (t) => dia(t).split('-').reverse().join('/')
function dataLlarga(d) {
  const [y, m, dd] = String(d).slice(0, 10).split('-').map(Number)
  return new Date(y, m - 1, dd).toLocaleDateString('ca-ES', { weekday: 'long', day: 'numeric', month: 'long' })
}

async function carrega() {
  try {
    const r = await api.get('/v1/work-logs/sense-sortida')
    jornades.value = Array.isArray(r) ? r : []
    for (const j of jornades.value) {
      form[j.id] ??= { sortida: `${dia(j.start_time)}T${hora(j.start_time)}`, explicacio: '', enviant: false, error: '' }
    }
  } catch (e) {
    jornades.value = []
  }
  emit('carregat', jornades.value.filter(j => !j.declaracio))
}

async function declara(j) {
  const f = form[j.id]
  f.error = ''
  f.enviant = true
  try {
    await api.post(`/v1/work-logs/${j.id}/declara-sortida`, { sortida: f.sortida.replace('T', ' '), explicacio: f.explicacio.trim() })
    await carrega()
  } catch (e) {
    f.error = e?.message || "No s'ha pogut enviar la declaració."
  } finally {
    f.enviant = false
  }
}

onMounted(carrega)
defineExpose({ carrega })
</script>

<style scoped>
.avis-ss { border-left: 4px solid var(--color-danger, #c62828); margin-bottom: 16px; }
.avis-ss-fila { padding: 10px 0; border-top: 1px solid var(--color-border-light, #eee); }
.avis-ss-cap { margin-bottom: 6px; text-transform: capitalize; }
.avis-ss-form { display: flex; flex-direction: column; gap: 8px; max-width: 460px; }
.avis-ss-form label { display: flex; flex-direction: column; gap: 4px; font-weight: 600; }
.avis-ss-feta { color: var(--color-success, #2e7d32); font-size: 0.9rem; }
.avis-ss-error { color: var(--color-danger, #c62828); font-size: 0.85rem; }
</style>
