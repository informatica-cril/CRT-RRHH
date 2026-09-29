<template>
  <div class="break-settings">
    <div class="header">
      <button @click="$router.back()" class="back-btn">← Tornar</button>
      <h2>Configuració de Pausa Obligatòria</h2>
    </div>

    <!-- ═══ Configuració Global ═══ -->
    <div v-if="loading" class="loading">Carregant...</div>
    <div v-else class="card">
      <h3 class="section-title">⚙️ Configuració Global</h3>
      <div class="form-group">
        <label>
          <input type="checkbox" v-model="settings.enabled" />
          Pausa obligatòria activada
        </label>
      </div>

      <div class="form-group">
        <label>Hores previstes al horari abans de pausa (llindar)</label>
        <input type="number" v-model.number="settings.threshold_hours" min="1" max="12" />
        <small>El treballador ha de fer pausa si el seu horari configurat per a aquest dia supera aquestes hores seguides.</small>
      </div>

      <div class="form-group">
        <label>Duració de la pausa (minuts)</label>
        <input type="number" v-model.number="settings.break_duration_minutes" min="5" max="60" />
      </div>

      <div class="form-group">
        <label>Mode d'activació de la pausa</label>
        <div class="mode-selector">
          <label class="mode-option">
            <input type="radio" v-model="settings.break_start_mode" value="offset" />
            <span><strong>⏱️ Offset des de l'inici</strong><br />
            <small>La pausa s'activa X minuts després que el treballador fitxi.</small></span>
          </label>
          <label class="mode-option">
            <input type="radio" v-model="settings.break_start_mode" value="fixed" />
            <span><strong>🕐 Hora fixa</strong><br />
            <small>La pausa s'activa a una hora concreta del dia, independentment de quan fitxi.</small></span>
          </label>
          <label class="mode-option">
            <input type="radio" v-model="settings.break_start_mode" value="auto" />
            <span><strong>🤖 El sistema decideix cada dia</strong><br />
            <small>El motor de programació automàtica de CRT Domiciliària col·loca la pausa al forat més natural de la ruta de cada dia (entre visites, prop del migdia). Cada jornada pot tenir una hora diferent.</small></span>
          </label>
        </div>
      </div>

      <div class="form-group" v-if="settings.break_start_mode === 'offset'">
        <label>Minuts des de l'inici del fitxatge</label>
        <input type="number" v-model.number="settings.break_start_offset_minutes" min="1" max="720" />
        <small>Ex: 300 = 5 hores després de fitxar. Es recomana que coincideixi amb el llindar d'hores.</small>
      </div>

      <div class="form-group" v-if="settings.break_start_mode === 'fixed'">
        <label>Hora fixa d'activació</label>
        <input type="time" v-model="breakFixedTimeInput" />
        <small>La pausa es bloquejarà a aquesta hora per a tot treballador amb jornada superior al llindar.</small>
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" v-model="settings.auto_start" />
          Inici automàtic (la pausa comença sola a l'hora configurada)
        </label>
      </div>

      <div class="form-group">
        <label>Període de gràcia (minuts)</label>
        <input type="number" v-model.number="settings.grace_period_minutes" min="0" max="30" />
        <small>Minuts de tolerància abans de forçar la pausa.</small>
      </div>

      <div class="info-box">
        <strong>ℹ️ Com funciona:</strong>
        <ol>
          <li>En fitxar, l'app comprova l'horari del treballador per al dia actual.</li>
          <li>Si la jornada prevista supera el llindar d'hores, es programa la pausa obligatòria.</li>
          <li>La pausa s'activa segons el mode configurat (offset, hora fixa, o l'hora que el motor de Domiciliària hagi triat aquell dia).</li>
          <li>La pausa <strong>no es pot ometre</strong>: el treballador ha de completar-la.</li>
        </ol>
      </div>

      <button @click="save" class="btn-save" :disabled="saving">
        {{ saving ? 'Desant...' : 'Desar configuració global' }}
      </button>
    </div>

    <!-- ═══ Llistat de Treballadors ═══ -->
    <div class="card mt-lg">
      <h3 class="section-title">👥 Configuració per Treballador</h3>
      <p class="text-small text-muted" style="margin-bottom:16px;">
        Podeu activar/desactivar la pausa per a cada treballador individualment i configurar una hora personalitzada.
        Si no es configura, s'aplica la configuració global.
      </p>

      <div v-if="workersLoading" class="loading">Carregant treballadors...</div>
      <div v-else-if="workers.length === 0" class="text-muted" style="padding:16px;text-align:center;">
        No hi ha treballadors actius.
      </div>
      <div v-else class="workers-list">
        <div v-for="w in workers" :key="w.id" class="worker-row">
          <div class="worker-info">
            <div class="worker-name">{{ w.name }}</div>
            <div class="worker-meta">
              <span v-if="w.work_schedule" class="badge badge-info">{{ w.work_schedule.name }}</span>
              <span v-else class="badge badge-warning">Sense horari</span>
              <span v-if="w.job_profile" class="text-muted">{{ w.job_profile }}</span>
            </div>
          </div>
          <div class="worker-controls">
            <select :value="w.break_override === null ? 'global' : w.break_override ? 'on' : 'off'"
              @change="updateWorkerOverride(w, $event.target.value)" class="worker-select">
              <option value="global">🌍 Global</option>
              <option value="on">✅ Forçar ON</option>
              <option value="off">❌ Desactivar</option>
            </select>
            <input type="time"
              :value="formatTime(w.break_override_time)"
              @change="updateWorkerTime(w, $event.target.value)"
              class="worker-time-input"
              :disabled="w.break_override === false"
              title="Hora personalitzada de pausa (deixa buit per usar la global)" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'

const settings = ref({
  enabled: true,
  threshold_hours: 5,
  break_duration_minutes: 20,
  auto_start: false,
  grace_period_minutes: 0,
  break_start_mode: 'offset',
  break_start_offset_minutes: 300,
  break_start_fixed_time: '12:00',
})
const loading = ref(true)
const saving = ref(false)

const workers = ref([])
const workersLoading = ref(true)

const breakFixedTimeInput = computed({
  get() {
    const val = settings.value.break_start_fixed_time
    if (!val) return '12:00'
    return String(val).substring(0, 5)
  },
  set(v) {
    settings.value.break_start_fixed_time = v
  },
})

function formatTime(val) {
  if (!val) return ''
  return String(val).substring(0, 5)
}

onMounted(async () => {
  try {
    const data = await db.getBreakSettings()
    settings.value = { ...settings.value, ...data }
  } catch (e) {
    console.error('Error loading break settings:', e)
  } finally {
    loading.value = false
  }
  await loadWorkers()
})

async function loadWorkers() {
  workersLoading.value = true
  try {
    workers.value = await db.getBreakWorkers()
  } catch (e) {
    console.error('Error loading workers:', e)
  } finally {
    workersLoading.value = false
  }
}

async function save() {
  saving.value = true
  try {
    await db.updateBreakSettings(settings.value)
    alert('Configuració desada correctament')
  } catch (e) {
    console.error('Error saving break settings:', e)
    alert('Error al desar la configuració')
  } finally {
    saving.value = false
  }
}

async function updateWorkerOverride(worker, value) {
  const override = value === 'global' ? null : value === 'on'
  try {
    await db.updateBreakWorker(worker.id, { break_override: override })
    worker.break_override = override
  } catch (e) {
    console.error('Error updating worker override:', e)
    alert('Error al actualitzar el treballador')
  }
}

async function updateWorkerTime(worker, value) {
  try {
    const timeVal = value || null
    await db.updateBreakWorker(worker.id, { break_override_time: timeVal })
    worker.break_override_time = timeVal
  } catch (e) {
    console.error('Error updating worker time:', e)
    alert('Error al actualitzar l\'hora del treballador')
  }
}
</script>

<style scoped>
.break-settings { padding: 20px; max-width: 700px; margin: 0 auto; }
.header { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.back-btn { padding: 8px 16px; border: none; background: #e0e0e0; border-radius: 6px; cursor: pointer; }
.card { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.mt-lg { margin-top: 24px; }
.section-title { margin: 0 0 16px 0; font-size: 1.1rem; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; font-weight: 600; margin-bottom: 6px; }
.form-group input[type="number"],
.form-group input[type="time"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 1em; }
.form-group small { display: block; color: #666; margin-top: 4px; font-size: 0.85em; }
.mode-selector { display: flex; flex-direction: column; gap: 12px; margin-top: 8px; }
.mode-option { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; cursor: pointer; font-weight: normal; }
.mode-option:hover { background: #f5f5f5; }
.mode-option input[type="radio"] { margin-top: 4px; }
.mode-option span { font-weight: normal; }
.info-box { background: #e3f2fd; border-radius: 8px; padding: 14px 16px; margin-bottom: 20px; font-size: 0.9em; }
.info-box ol { margin: 8px 0 0 20px; padding: 0; }
.info-box li { margin-bottom: 4px; }
.btn-save { background: #1976d2; color: white; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer; font-size: 1em; width: 100%; }
.btn-save:disabled { opacity: 0.6; }
.loading { text-align: center; padding: 40px; color: #999; }
.workers-list { display: flex; flex-direction: column; gap: 8px; }
.worker-row { display: flex; justify-content: space-between; align-items: center; padding: 12px; border: 1px solid #e0e0e0; border-radius: 8px; gap: 12px; flex-wrap: wrap; }
.worker-info { flex: 1; min-width: 180px; }
.worker-name { font-weight: 600; }
.worker-meta { display: flex; gap: 8px; align-items: center; margin-top: 4px; font-size: 0.85em; }
.badge { padding: 2px 8px; border-radius: 4px; font-size: 0.8em; }
.badge-info { background: #e3f2fd; color: #1976d2; }
.badge-warning { background: #fff3e0; color: #e65100; }
.worker-controls { display: flex; gap: 8px; align-items: center; }
.worker-select { padding: 6px 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9em; }
.worker-time-input { padding: 6px 8px; border: 1px solid #ccc; border-radius: 6px; font-size: 0.9em; }
.worker-time-input:disabled { opacity: 0.4; }
.text-small { font-size: 0.85em; }
.text-muted { color: #666; }
</style>
