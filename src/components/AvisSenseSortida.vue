<template>
  <div v-if="jornades.length" class="avis-ss">
    <div class="avis-ss-resum">
      <span>🚪 <strong>{{ pendents.length ? `${pendents.length} jornada${pendents.length === 1 ? '' : 'es'} sense sortida` : 'Sortides declarades' }}</strong>
        <span class="text-muted"> · {{ jornades.map(j => dataCurta(j.date)).join(', ') }}</span></span>
      <button class="btn btn-outline btn-sm" @click="obert = !obert">{{ obert ? 'Amagar' : (pendents.length ? 'Declarar' : 'Veure') }}</button>
    </div>

    <div v-if="obert" class="avis-ss-cos">
      <p class="avis-ss-nota">No vas fitxar la sortida i compta 0 h. El registre no es canvia: declara a quina hora vas acabar i RRHH ho revisarà.</p>
      <div v-for="j in jornades" :key="j.id" class="avis-ss-fila">
        <span class="avis-ss-dia">{{ dataCurta(j.date) }} <span class="text-muted">· entrada {{ hora(j.start_time) }}</span></span>

        <span v-if="j.declaracio" class="avis-ss-feta">✓ Sortida declarada a les {{ hora(j.declaracio.sortida) }} · pendent de RRHH</span>

        <template v-else>
          <input type="time" class="form-input avis-ss-hora" v-model="form[j.id].hora" title="Hora de sortida" />
          <input type="text" class="form-input avis-ss-text" v-model="form[j.id].explicacio" placeholder="Què va passar? (mín. 10 caràcters)" />
          <button class="btn btn-primary btn-sm" :disabled="form[j.id].enviant || !form[j.id].hora || form[j.id].explicacio.trim().length < 10"
            @click="declara(j)">{{ form[j.id].enviant ? '…' : 'Enviar' }}</button>
          <span v-if="form[j.id].error" class="avis-ss-error">{{ form[j.id].error }}</span>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../services/apiClient'

const emit = defineEmits(['carregat'])
const jornades = ref([])
const form = reactive({})
const obert = ref(false)
const pendents = computed(() => jornades.value.filter(j => !j.declaracio))

// Les hores arriben en hora de Madrid sense zona ('2026-10-02 08:27:00'): es llegeixen del text.
const hora = (t) => String(t || '').slice(11, 16)
const dia = (t) => String(t || '').slice(0, 10)
const dataCurta = (t) => dia(t).split('-').reverse().join('/')

// La sortida es declara el mateix dia; si l'hora és anterior a l'entrada, és de l'endemà (torn de nit).
function sortidaDe(j, h) {
  const d = new Date(`${dia(j.start_time)}T00:00:00`)
  if (h <= hora(j.start_time)) d.setDate(d.getDate() + 1)
  const ymd = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
  return `${ymd} ${h}`
}

async function carrega() {
  try {
    const r = await api.get('/v1/work-logs/sense-sortida')
    jornades.value = Array.isArray(r) ? r : []
    for (const j of jornades.value) {
      form[j.id] ??= { hora: '', explicacio: '', enviant: false, error: '' }
    }
  } catch (e) {
    jornades.value = []
  }
  emit('carregat', pendents.value)
}

async function declara(j) {
  const f = form[j.id]
  f.error = ''
  f.enviant = true
  try {
    await api.post(`/v1/work-logs/${j.id}/declara-sortida`, { sortida: sortidaDe(j, f.hora), explicacio: f.explicacio.trim() })
    await carrega()
  } catch (e) {
    f.error = e?.message || "No s'ha pogut enviar."
  } finally {
    f.enviant = false
  }
}

onMounted(carrega)
defineExpose({ carrega })
</script>

<style scoped>
.avis-ss { border: 1px solid var(--color-border-light, #e5e7eb); border-left: 4px solid var(--color-danger, #c62828); border-radius: 10px; background: var(--color-surface, #fff); padding: 8px 12px; margin-bottom: 12px; font-size: 0.88rem; }
.avis-ss-resum { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.avis-ss-cos { margin-top: 8px; }
.avis-ss-nota { margin: 0 0 6px; font-size: 0.78rem; color: var(--color-text-muted, #6b7280); }
.avis-ss-fila { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 6px 0; border-top: 1px solid var(--color-border-light, #eee); }
.avis-ss-dia { min-width: 150px; font-weight: 600; }
.avis-ss-hora { width: 110px; padding: 4px 8px; }
.avis-ss-text { flex: 1; min-width: 180px; padding: 4px 8px; }
.avis-ss-feta { color: var(--color-success, #2e7d32); }
.avis-ss-error { width: 100%; color: var(--color-danger, #c62828); font-size: 0.8rem; }
</style>
