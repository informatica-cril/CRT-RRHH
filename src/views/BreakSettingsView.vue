<template>
  <div class="ps">
    <div class="page-header">
      <div>
        <h1 class="page-title">Pausa obligatòria</h1>
        <p class="page-subtitle">Quan ha de parar cada persona durant la jornada i quanta estona.</p>
      </div>
    </div>

    <div v-if="loading" class="card ps-buit">Carregant…</div>
    <div v-else class="ps-graella">
      <!-- Configuració general -->
      <div class="card ps-card">
        <div class="ps-cap">
          <h3 class="ps-titol">Configuració general</h3>
          <label class="ps-interruptor" :title="settings.enabled ? 'Activada' : 'Desactivada'">
            <input type="checkbox" v-model="settings.enabled" />
            <span></span>
            <b>{{ settings.enabled ? 'Activada' : 'Desactivada' }}</b>
          </label>
        </div>

        <div class="ps-camps" :class="{ apagat: !settings.enabled }">
          <label class="ps-camp">
            <span>Si l'horari del dia passa de</span>
            <div class="ps-amb-unitat"><input class="form-input" type="number" v-model.number="settings.threshold_hours" min="1" max="12" /><em>hores</em></div>
          </label>
          <label class="ps-camp">
            <span>La pausa dura</span>
            <div class="ps-amb-unitat"><input class="form-input" type="number" v-model.number="settings.break_duration_minutes" min="5" max="60" /><em>min</em></div>
          </label>
          <label class="ps-camp">
            <span>Tolerància abans de forçar-la</span>
            <div class="ps-amb-unitat"><input class="form-input" type="number" v-model.number="settings.grace_period_minutes" min="0" max="30" /><em>min</em></div>
          </label>
        </div>

        <div class="ps-subtitol">Quan comença</div>
        <div class="ps-modes" :class="{ apagat: !settings.enabled }">
          <label class="ps-mode" :class="{ actiu: settings.break_start_mode === 'offset' }">
            <input type="radio" v-model="settings.break_start_mode" value="offset" />
            <strong>⏱️ Després de fitxar</strong>
            <small>Unes hores després de l'entrada de cada persona.</small>
          </label>
          <label class="ps-mode" :class="{ actiu: settings.break_start_mode === 'fixed' }">
            <input type="radio" v-model="settings.break_start_mode" value="fixed" />
            <strong>🕐 A una hora fixa</strong>
            <small>La mateixa hora per a tothom, fitxi quan fitxi.</small>
          </label>
          <label class="ps-mode" :class="{ actiu: settings.break_start_mode === 'auto' }">
            <input type="radio" v-model="settings.break_start_mode" value="auto" />
            <strong>🤖 La decideix el sistema</strong>
            <small>Domiciliària la posa al forat més natural de la ruta de cada dia.</small>
          </label>
        </div>

        <div class="ps-camps" :class="{ apagat: !settings.enabled }">
          <label v-if="settings.break_start_mode === 'offset'" class="ps-camp">
            <span>Minuts després de fitxar</span>
            <div class="ps-amb-unitat"><input class="form-input" type="number" v-model.number="settings.break_start_offset_minutes" min="1" max="720" /><em>min</em></div>
            <small>{{ textMinuts(settings.break_start_offset_minutes) }} després de l'entrada.</small>
          </label>
          <label v-if="settings.break_start_mode === 'fixed'" class="ps-camp">
            <span>Hora de la pausa</span>
            <input class="form-input" type="time" v-model="breakFixedTimeInput" />
          </label>
          <label class="ps-check">
            <input type="checkbox" v-model="settings.auto_start" />
            <span>Que comenci sola a l'hora, sense que la persona hagi de prémer res</span>
          </label>
        </div>

        <div class="ps-peu">
          <button @click="save" class="btn btn-primary" :disabled="saving">{{ saving ? 'Desant…' : 'Desar la configuració' }}</button>
        </div>
      </div>

      <!-- Com funciona -->
      <div class="card ps-card ps-info">
        <h3 class="ps-titol">Com funciona</h3>
        <ol>
          <li>En fitxar, l'app mira l'horari de la persona per a aquell dia.</li>
          <li>Si passa de <strong>{{ settings.threshold_hours }} h</strong>, es programa una pausa de <strong>{{ settings.break_duration_minutes }} min</strong>.</li>
          <li>Comença {{ resumInici }}.</li>
          <li>La pausa <strong>no es pot ometre</strong>: s'ha de completar.</li>
        </ol>
        <p class="text-small text-muted">A sota es pot canviar per a una persona concreta: activar-la sempre, treure-la o posar-li una hora pròpia.</p>
      </div>
    </div>

    <!-- Per persona -->
    <div class="card ps-card">
      <div class="ps-cap">
        <h3 class="ps-titol">Per persona</h3>
        <div class="ps-filtres">
          <input class="form-input" v-model="cerca" placeholder="Cerca una persona…" />
          <select class="form-select" v-model="filtre">
            <option value="tots">Tothom</option>
            <option value="propis">Només amb canvis propis</option>
          </select>
        </div>
      </div>

      <div v-if="workersLoading" class="ps-buit">Carregant…</div>
      <div v-else-if="!treballadorsVisibles.length" class="ps-buit text-muted">No hi ha ningú amb aquest filtre.</div>
      <div v-else class="table-container">
        <table class="ps-taula">
          <thead><tr><th>Persona</th><th>Horari</th><th>Pausa</th><th>Hora pròpia</th></tr></thead>
          <tbody>
            <tr v-for="w in treballadorsVisibles" :key="w.id">
              <td><strong>{{ w.name }}</strong><div v-if="w.job_profile" class="text-small text-muted">{{ w.job_profile }}</div></td>
              <td class="text-small">
                <span v-if="w.work_schedule">{{ w.work_schedule.name }}</span>
                <span v-else class="ps-avis">Sense horari</span>
              </td>
              <td>
                <select class="form-select ps-select" :value="w.break_override === null ? 'global' : w.break_override ? 'on' : 'off'"
                  @change="updateWorkerOverride(w, $event.target.value)">
                  <option value="global">Com tothom</option>
                  <option value="on">Sempre</option>
                  <option value="off">Mai</option>
                </select>
              </td>
              <td>
                <input type="time" class="form-input ps-hora" :value="formatTime(w.break_override_time)"
                  @change="updateWorkerTime(w, $event.target.value)" :disabled="w.break_override === false"
                  title="Hora pròpia de la pausa (buit = la general)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import db from '../services/db'
import { avisa } from '../utils/avisos'

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
const cerca = ref('')
const filtre = ref('tots')
const normalitza = t => String(t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
const treballadorsVisibles = computed(() => {
  const c = normalitza(cerca.value.trim())
  return workers.value.filter(w => (!c || normalitza(w.name).includes(c))
    && (filtre.value === 'tots' || w.break_override !== null || w.break_override_time))
})
function textMinuts(m) {
  const n = Number(m) || 0
  const h = Math.floor(n / 60), r = n % 60
  return h ? `${h} h${r ? ` ${r} min` : ''}` : `${r} min`
}
const resumInici = computed(() => {
  const m = settings.value.break_start_mode
  if (m === 'fixed') return `a les ${String(settings.value.break_start_fixed_time || '12:00').slice(0, 5)}`
  if (m === 'auto') return "a l'hora que triï el sistema de Domiciliària"
  return `${textMinuts(settings.value.break_start_offset_minutes)} després de fitxar`
})
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
    avisa('✅ Configuració desada.')
  } catch (e) {
    console.error('Error saving break settings:', e)
    avisa("No s'ha pogut desar la configuració.", 'error')
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
    avisa("No s'ha pogut canviar la pausa d'aquesta persona.", 'error')
  }
}

async function updateWorkerTime(worker, value) {
  try {
    const timeVal = value || null
    await db.updateBreakWorker(worker.id, { break_override_time: timeVal })
    worker.break_override_time = timeVal
  } catch (e) {
    console.error('Error updating worker time:', e)
    avisa("No s'ha pogut canviar l'hora d'aquesta persona.", 'error')
  }
}
</script>

<style scoped>
.ps-buit { padding: 28px; text-align: center; }
.ps-graella { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 16px; margin-bottom: 16px; align-items: start; }
.ps-card { padding: 18px 20px; }
.ps-cap { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; }
.ps-titol { margin: 0; font-size: 1rem; font-weight: 700; }
.ps-subtitol { margin: 16px 0 8px; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--color-text-secondary, #64748b); }
.ps-camps { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
.ps-camps + .ps-camps { margin-top: 12px; }
.apagat { opacity: .5; }
.ps-camp { display: flex; flex-direction: column; gap: 4px; font-size: .82rem; color: var(--color-text-secondary, #475569); }
.ps-camp small { color: var(--color-text-muted, #94a3b8); }
.ps-amb-unitat { display: flex; align-items: center; gap: 6px; }
.ps-amb-unitat input { width: 90px; }
.ps-amb-unitat em { font-style: normal; color: var(--color-text-muted, #94a3b8); }
.ps-check { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; font-size: .85rem; cursor: pointer; }
.ps-modes { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; }
.ps-mode { display: flex; flex-direction: column; gap: 4px; padding: 12px; border: 1px solid var(--color-border, #e2e8f0); border-radius: 10px; cursor: pointer; font-size: .85rem; }
.ps-mode input { display: none; }
.ps-mode small { color: var(--color-text-secondary, #64748b); font-size: .76rem; line-height: 1.35; }
.ps-mode.actiu { border-color: var(--color-primary, #094E8C); box-shadow: inset 0 0 0 1px var(--color-primary, #094E8C); background: rgba(9, 78, 140, .05); }
.ps-peu { display: flex; justify-content: flex-end; margin-top: 16px; }
.ps-info ol { margin: 10px 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; font-size: .86rem; }
.ps-interruptor { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: .85rem; }
.ps-interruptor input { display: none; }
.ps-interruptor span { position: relative; width: 44px; height: 24px; border-radius: 99px; background: #cbd5e1; transition: background .15s; }
.ps-interruptor span::before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; border-radius: 50%; background: #fff; transition: transform .15s; }
.ps-interruptor input:checked + span { background: var(--color-success, #0DAF83); }
.ps-interruptor input:checked + span::before { transform: translateX(20px); }
.ps-filtres { display: flex; flex-wrap: wrap; gap: 8px; }
.ps-filtres .form-input { width: 220px; }
.ps-filtres .form-select { width: auto; }
.ps-taula { width: 100%; }
.ps-select { width: auto; min-width: 140px; }
.ps-hora { width: 120px; }
.ps-hora:disabled { opacity: .4; }
.ps-avis { color: #b45309; font-weight: 600; }
@media (max-width: 900px) {
  .ps-graella { grid-template-columns: 1fr; }
}
</style>
