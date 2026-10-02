<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('work_logs_title') }}</h1>
        <p class="page-subtitle">{{ t('rdl_notice') }}</p>
      </div>
      <div class="page-actions">
        <select class="form-select" v-model="selectedMonth">
          <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
        </select>
        <select v-if="authStore.isStaff" class="form-select" v-model="selectedUser">
          <option value="all">{{ t('total') }} {{ t('employees') }}</option>
          <option v-for="u in workers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <button v-if="authStore.isStaff" type="button" class="btn btn-outline wl-excel" :disabled="exportant" @click="exportaExcel"
          title="Descarrega en Excel el que es veu: el mes, la persona i el filtre triats">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M8 13l3 4M11 13l-3 4"/><line x1="14" y1="15" x2="17" y2="15"/></svg>
          {{ exportant ? 'Generant…' : 'Exportar Excel' }}
        </button>
      </div>
    </div>

    <!-- Vistes de revisió: un filtre alhora, amb el recompte del mes i la persona triats.
         Fora de zona es revisa amb la DISTÀNCIA a la zona, mai amb la posició (les coordenades
         no viatgen al llistat). -->
    <div v-if="authStore.isStaff" class="wl-filtres">
      <button v-for="f in filtres" :key="f.clau" type="button" class="wl-filtre"
        :class="[f.to, { actiu: vista === f.clau, buit: !recomptes[f.clau] && f.clau !== 'tots' }]"
        @click="vista = f.clau">
        <span>{{ f.icona }}</span> {{ f.nom }}
        <span class="wl-n">{{ recomptes[f.clau] || 0 }}</span>
      </button>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-for="c in columnes" :key="c.clau" v-show="c.clau !== 'treballador' || authStore.isStaff"
                class="th-ord" :class="{ actiu: ordre.col === c.clau }" :aria-sort="ordre.col === c.clau ? (ordre.dir === 'asc' ? 'ascending' : 'descending') : 'none'"
                @click="ordena(c.clau)" :title="'Ordenar per ' + t(c.text).toLowerCase()">
                {{ t(c.text) }}
                <span class="ord-fletxa">{{ ordre.col === c.clau ? (ordre.dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
              </th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in filteredLogs" :key="log.id">
              <td v-if="authStore.isStaff">{{ getUserName(log.user_id) }}</td>
              <td>{{ formatDate(log.date) }}</td>
              <td>{{ formatTime(log.start_time) }}</td>
              <td>{{ log.end_time ? formatTime(log.end_time) : '—' }}</td>
              <td>
                <strong v-if="log.end_time" :title="'Hores brutes: ' + formatHM(log.total_hours_worked)">
                  {{ formatHM(log.effective_hours ?? log.total_hours_worked) }}
                </strong>
                <span v-else>—</span>
                <span v-if="log.segmented && Number(log.effective_hours) !== Number(log.total_hours_worked)" class="badge badge-warning" style="font-size:0.7rem; margin-left:4px;" title="Temps efectiu segons trams aprovats">efectiu</span>
                <div v-if="sortidaAltreDia(log)" class="sortida-altre-dia" :title="'La jornada es va iniciar el ' + String(log.date).slice(0, 10).split('-').reverse().join('/') + ' i la sortida no es va fitxar fins al ' + sortidaAltreDia(log).data">
                    ⚠ Sortida fitxada el {{ sortidaAltreDia(log).data }}<span v-if="sortidaAltreDia(log).dies > 1"> (+{{ sortidaAltreDia(log).dies }} dies)</span>
                  </div>
              </td>
              <td>
                <div style="display:flex;flex-direction:column;gap:2px;">
                  <span v-if="Number(log.extra_hours_authorized || 0) > 0" class="badge badge-success" title="Hores Extres Autoritzades">
                    +{{ formatHM(log.extra_hours_authorized) }} ✓
                  </span>
                  <span v-if="Number(log.extra_hours_unauthorized || 0) > 0" class="badge badge-warning" title="Hores Extres No Autoritzades">
                    +{{ formatHM(log.extra_hours_unauthorized) }} (pendent)
                  </span>
                  <span v-if="Number(log.hours_out_of_area || 0) > 0" class="badge badge-danger" title="Hores Fora de Zona Descomptades">
                    -{{ formatHM(log.hours_out_of_area) }} (Fora Zona)
                  </span>
                  <span v-if="!Number(log.extra_hours_authorized) && !Number(log.extra_hours_unauthorized) && !Number(log.hours_out_of_area)">—</span>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:6px;flex-direction:column;align-items:flex-start;">
                  <span class="badge" :class="statusBadge(log.status)" style="display:flex; align-items:center; gap:4px;">
                    <span v-if="log.status === 'pending'">⏳</span>
                    <span v-else-if="log.status === 'approved'">✅</span>
                    <span v-else-if="log.status === 'rejected'">❌</span>
                    {{ t(log.status) }}
                  </span>
                  <span v-if="log.hour_status && log.hour_status !== 'ok'"
                        class="badge"
                        style="font-size:0.75rem; display:flex; align-items:center; gap:4px;"
                        :class="log.hour_status === 'out_of_area' ? 'badge-danger' : 'badge-warning'">
                    <span v-if="log.hour_status === 'out_of_area'">📍</span>
                    <span v-else-if="log.hour_status === 'in_progress'">🕒</span>
                    {{ t(log.hour_status) }}
                  </span>
                  <span v-if="distanciaTxt(log)" class="fz-dist" :title="'Distància a la zona assignada (entrada / sortida). No es mostra cap posició.'">
                    {{ distanciaTxt(log) }}
                  </span>
                </div>
              </td>
              <td>
                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                  <button class="btn btn-outline btn-sm" @click="viewDetail(log.id)" title="Veure detall de trams">🔍 Detall</button>
                  <template v-if="authStore.isStaff && log.status === 'pending'">
                    <button class="btn btn-success btn-sm" @click="approve(log.id)">{{ t('approve') }}</button>
                    <button class="btn btn-danger btn-sm" @click="openReject(log.id)">{{ t('reject') }}</button>
                  </template>
                  <template v-if="!authStore.isStaff && log.status === 'rejected'">
                    <button class="btn btn-outline btn-sm" @click="openModify(log)">{{ t('modify_record') }}</button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td :colspan="authStore.isStaff ? 8 : 7" class="text-center text-muted" style="padding: 32px;">
                No hi ha registres per aquest període
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="rdl-notice">{{ t('rdl_retention') }}</div>

    <!-- Reject Modal -->
    <div v-if="showRejectModal" class="modal-overlay" @click.self="showRejectModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ t('reject') }}</h3>
          <button class="icon-btn" @click="showRejectModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('rejection_reason') }}</label>
          <textarea class="form-textarea" v-model="rejectReason" required></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRejectModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-danger" @click="confirmReject">{{ t('reject') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { apiFetchRaw } from '../services/apiClient'
import { i18n } from '../i18n'
import { formatHM } from '../utils/formatHours'
import { sortidaAltreDia } from '../utils/sortidaAltreDia'

const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const router = useRouter()
const t = (key) => i18n.t(key)

function viewDetail(logId) {
  router.push({ name: 'work-log-detail', params: { id: logId } })
}

const now = new Date()
const selectedMonth = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const selectedUser = ref('all')
const showRejectModal = ref(false)
const rejectReason = ref('')
const rejectLogId = ref(null)

const workers = ref([])
const users = ref([])

async function fetchData() {
  try {
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
    workLogStore.mes = selectedMonth.value
    await workLogStore.loadLogs()
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)
// Cada mes es demana sencer a l'API (abans es filtrava sobre els 500 últims i els mesos vells sortien buits).
watch(selectedMonth, (m) => { workLogStore.mes = m; workLogStore.loadLogs() })
onUnmounted(() => { workLogStore.mes = null })

const months = computed(() => {
  const result = []
  for (let i = 0; i < 12; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    result.push({
      value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { month: 'long', year: 'numeric' })
    })
  }
  return result
})


// Fora de zona = alguna verificació d'ubicació fallida o hores descomptades per zona.
// Es treballa amb el booleà i la DISTÀNCIA que ja viatgen al llistat; les coordenades
// no hi són mai (minimització, EIPD §6.3) — igual que a la revisió de marques de domi.
function esForaZona(l) {
  return l.hour_status === 'out_of_area'
    || l.location_match === false
    || l.start_location_match === false
    || l.end_location_match === false
    || Number(l.hours_out_of_area || 0) > 0
}

function distanciaTxt(l) {
  if (!esForaZona(l)) return ''
  const d = Math.max(Number(l.start_location_distance || 0), Number(l.end_location_distance || 0))
  if (!d) return ''
  return d >= 1000 ? (d / 1000).toFixed(1).replace('.', ',') + ' km de la zona' : Math.round(d) + ' m de la zona'
}

// Més de 14 h: gairebé sempre una sortida que no es va fitxar a temps (molts venen de l'app antiga).
function esLlarg(l) {
  return l.start_time && l.end_time && (new Date(l.end_time) - new Date(l.start_time)) > 14 * 3600 * 1000
}

const vista = ref('tots')
const filtres = [
  { clau: 'tots', nom: 'Tots', icona: '📋', to: 'neutre', fn: () => true },
  { clau: 'pendents', nom: "Pendents d'aprovar", icona: '⏳', to: 'groc', fn: l => l.status === 'pending' },
  { clau: 'forazona', nom: 'Fora de zona', icona: '📍', to: 'vermell', fn: esForaZona },
  { clau: 'extra', nom: 'Hores extra no autoritzades', icona: '⏱', to: 'taronja', fn: l => Number(l.extra_hours_unauthorized || 0) > 0 },
  { clau: 'sensesortida', nom: 'Sense sortida', icona: '🕒', to: 'taronja', fn: l => !l.end_time },
  { clau: 'llargs', nom: 'Més de 14 h', icona: '⚠️', to: 'vermell', fn: esLlarg },
  { clau: 'aprovats', nom: 'Aprovats', icona: '✅', to: 'verd', fn: l => l.status === 'approved' },
  { clau: 'rebutjats', nom: 'Rebutjats', icona: '❌', to: 'neutre', fn: l => l.status === 'rejected' },
]

// Mes i persona: la base sobre la qual compten i filtren les vistes.
const logsDelMes = computed(() => {
  let logs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === selectedMonth.value)
  if (authStore.isStaff && selectedUser.value !== 'all') {
    logs = logs.filter(l => l.user_id == selectedUser.value)
  }
  if (authStore.isWorker) {
    logs = logs.filter(l => l.user_id === authStore.userId)
  }
  return logs
})

const exportant = ref(false)
async function exportaExcel() {
  exportant.value = true
  try {
    const q = new URLSearchParams({ mes: selectedMonth.value, vista: vista.value })
    if (selectedUser.value !== 'all') q.set('user_id', selectedUser.value)
    const resp = await apiFetchRaw(`/v1/work-logs/export?${q}`)
    if (!resp.ok) throw new Error()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(await resp.blob())
    a.download = `registre_jornada_${selectedMonth.value}${vista.value !== 'tots' ? '_' + vista.value : ''}.xlsx`
    a.click()
    URL.revokeObjectURL(a.href)
  } catch (e) {
    alert("No s'ha pogut generar l'Excel.")
  } finally {
    exportant.value = false
  }
}

const recomptes = computed(() => Object.fromEntries(filtres.map(f => [f.clau, logsDelMes.value.filter(f.fn).length])))

// Ordenació per columna: un clic ordena, el segon inverteix. Per defecte, data més recent primer.
const columnes = [
  { clau: 'treballador', text: 'employee' },
  { clau: 'data', text: 'date' },
  { clau: 'inici', text: 'start_time' },
  { clau: 'fi', text: 'end_time' },
  { clau: 'hores', text: 'worked_hours' },
  { clau: 'extres', text: 'extra_hours' },
  { clau: 'estat', text: 'status' },
]
const ordre = ref({ col: 'data', dir: 'desc' })
function ordena(col) {
  ordre.value = ordre.value.col === col
    ? { col, dir: ordre.value.dir === 'asc' ? 'desc' : 'asc' }
    // Text i hores comencen per A→Z / menys→més; dates, per la més recent.
    : { col, dir: ['data'].includes(col) ? 'desc' : 'asc' }
}
// start_time/end_time arriben en hora de Madrid sense zona: l'hora es llegeix del text, no via Date.
const horaDe = (ts) => (ts ? String(ts).slice(11, 16) : null)
const ORDRE_ESTAT = { pending: 0, approved: 1, rejected: 2 }
const valorOrdre = {
  treballador: l => getUserName(l.user_id),
  data: l => String(l.date).slice(0, 10) + ' ' + (horaDe(l.start_time) || ''),
  inici: l => horaDe(l.start_time),
  fi: l => horaDe(l.end_time),
  hores: l => (l.end_time ? Number(l.effective_hours ?? l.total_hours_worked ?? 0) : null),
  extres: l => Number(l.extra_hours_authorized || 0) + Number(l.extra_hours_unauthorized || 0),
  estat: l => ORDRE_ESTAT[l.status] ?? 9,
}

const filteredLogs = computed(() => {
  const f = filtres.find(x => x.clau === vista.value) || filtres[0]
  const { col, dir } = ordre.value
  const val = valorOrdre[col]
  const sign = dir === 'asc' ? 1 : -1
  return logsDelMes.value.filter(f.fn).sort((a, b) => {
    const va = val(a), vb = val(b)
    // Els buits (sense sortida, sense hores) sempre al final, ordenis com ordenis.
    if (va === null && vb !== null) return 1
    if (vb === null && va !== null) return -1
    const c = typeof va === 'string' ? va.localeCompare(vb, 'ca') : (va ?? 0) - (vb ?? 0)
    // Desempat: data més recent primer.
    return c !== 0 ? c * sign : String(b.date).localeCompare(String(a.date))
  })
})

// onMounted moved up

function approve(id) { workLogStore.approveLog(id) }

function openReject(id) {
  rejectLogId.value = id
  rejectReason.value = ''
  showRejectModal.value = true
}

function confirmReject() {
  if (rejectLogId.value && rejectReason.value) {
    workLogStore.rejectLog(rejectLogId.value, rejectReason.value)
    showRejectModal.value = false
  }
}

function openModify(_log) { /* To be implemented: edit form */ }

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || '—' 
}
function formatDate(d) { return new Date(d).toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { weekday: 'short', day: 'numeric', month: 'short' }) }
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' }) }
function statusBadge(s) { return { 'badge-pending': s === 'pending', 'badge-success': s === 'approved', 'badge-danger': s === 'rejected' } }
</script>

<style scoped>
/* Jornada tancada un altre dia (sortida oblidada): s'ha de veure d'un cop d'ull. */
.sortida-altre-dia { margin-top: 3px; font-size: .74rem; font-weight: 700; color: var(--color-danger, #B3352F); white-space: nowrap; }
.th-ord { cursor: pointer; user-select: none; white-space: nowrap; }
.th-ord:hover { color: var(--color-primary, #094E8C); }
.th-ord.actiu { color: var(--color-primary, #094E8C); }
.ord-fletxa { font-size: .7rem; margin-left: 3px; opacity: .45; }
.th-ord.actiu .ord-fletxa { opacity: 1; }
.wl-excel { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; white-space: nowrap; }
.wl-filtres { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px; }
.wl-filtre {
  display: inline-flex; align-items: center; gap: 7px;
  border: 1px solid var(--color-border, #e2e8f0); border-radius: 999px;
  padding: 8px 14px; min-height: 40px; /* objectiu tàctil */
  font-size: .82rem; font-weight: 700; cursor: pointer; user-select: none;
  background: var(--color-surface, #fff); color: var(--color-text-secondary, #64748B);
  transition: background .15s, border-color .15s;
}
.wl-filtre.buit { opacity: .55; }
.wl-n {
  border-radius: 999px; padding: 1px 9px; font-size: .74rem; font-weight: 800;
  background: #e2e8f0; color: #475569; font-variant-numeric: tabular-nums;
}
.wl-filtre.groc .wl-n { background: #D9A400; color: #fff; }
.wl-filtre.vermell .wl-n { background: #B3352F; color: #fff; }
.wl-filtre.taronja .wl-n { background: #f97316; color: #fff; }
.wl-filtre.verd .wl-n { background: #0DAF83; color: #fff; }
.wl-filtre.buit .wl-n { background: #e2e8f0; color: #94a3b8; }
.wl-filtre.actiu { border-color: var(--color-primary, #094E8C); background: rgba(9, 78, 140, .08); color: var(--color-primary, #094E8C); }
.wl-filtre.actiu.groc { border-color: #D9A400; background: #FFF8E1; color: #8a6a00; }
.wl-filtre.actiu.vermell { border-color: #B3352F; background: #FDF1F0; color: #B3352F; }
.wl-filtre.actiu.taronja { border-color: #f97316; background: #FFF4EC; color: #c2560c; }
.wl-filtre.actiu.verd { border-color: #0DAF83; background: #E8F8F2; color: #0A7C5E; }
.fz-dist {
  font-size: .74rem; font-weight: 700; color: #B3352F; white-space: nowrap;
}
</style>
