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
        <!-- Cua de revisió d'ubicació: el mateix criteri que a domi — es revisa amb la
             DISTÀNCIA a la zona, mai amb la posició (les coordenades no viatgen al llistat). -->
        <label v-if="authStore.isStaff" class="fz-toggle" :class="{ actiu: foraZona }">
          <input type="checkbox" v-model="foraZona">
          📍 Només fora de zona
          <span v-if="foraZonaCount" class="fz-n">{{ foraZonaCount }}</span>
        </label>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-if="authStore.isStaff">{{ t('employee') }}</th>
              <th>{{ t('date') }}</th>
              <th>{{ t('start_time') }}</th>
              <th>{{ t('end_time') }}</th>
              <th>{{ t('worked_hours') }}</th>
              <th>{{ t('extra_hours') }}</th>
              <th>{{ t('status') }}</th>
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { i18n } from '../i18n'
import { formatHM } from '../utils/formatHours'

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
    await workLogStore.loadLogs()
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const months = computed(() => {
  const result = []
  for (let i = 0; i < 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    result.push({
      value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      label: d.toLocaleDateString(i18n.locale === 'ca' ? 'ca-ES' : 'es-ES', { month: 'long', year: 'numeric' })
    })
  }
  return result
})

const foraZona = ref(false)

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

const foraZonaCount = computed(() =>
  workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === selectedMonth.value
    && esForaZona(l) && l.status === 'pending').length)

const filteredLogs = computed(() => {
  let logs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === selectedMonth.value)
  if (authStore.isStaff && selectedUser.value !== 'all') {
    logs = logs.filter(l => l.user_id == selectedUser.value)
  }
  if (authStore.isWorker) {
    logs = logs.filter(l => l.user_id === authStore.userId)
  }
  if (foraZona.value) {
    logs = logs.filter(esForaZona)
  }
  return logs.sort((a, b) => new Date(b.date) - new Date(a.date))
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
.fz-toggle {
  display: inline-flex; align-items: center; gap: 7px;
  border: 1px solid var(--color-border, #e2e8f0); border-radius: 999px;
  padding: 10px 16px; min-height: 44px; /* objectiu tàctil (regla Zeno 5) */
  font-size: .84rem; font-weight: 700; cursor: pointer; user-select: none;
  background: var(--color-surface, #fff); color: var(--color-text-secondary, #64748B);
}
.fz-toggle.actiu { background: #FDF1F0; border-color: #B3352F; color: #B3352F; }
.fz-toggle input { margin: 0; }
.fz-n {
  background: #B3352F; color: #fff; border-radius: 999px;
  padding: 1px 9px; font-size: .74rem; font-weight: 800;
}
.fz-dist {
  font-size: .74rem; font-weight: 700; color: #B3352F; white-space: nowrap;
}
</style>
