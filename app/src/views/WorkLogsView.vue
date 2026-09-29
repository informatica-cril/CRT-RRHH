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
        <select v-if="authStore.isAdmin" class="form-select" v-model="selectedUser">
          <option value="all">{{ t('total') }} {{ t('employees') }}</option>
          <option v-for="u in workers" :key="u.id" :value="u.id">{{ u.name }}</option>
        </select>
        <select v-if="authStore.isAdmin" class="form-select" v-model="filterReview">
          <option value="all">Tots els registres</option>
          <option value="review">🔍 Necessita revisió</option>
          <option value="pending">⏳ Pendents normals</option>
          <option value="approved">✅ Aprovats</option>
        </select>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-if="authStore.isAdmin">{{ t('employee') }}</th>
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
              <td v-if="authStore.isAdmin">{{ getUserName(log.user_id) }}</td>
              <td>{{ formatDate(log.date) }}</td>
              <td>{{ formatTime(log.start_time) }}</td>
              <td>
                <template v-if="log.end_time">
                  <span>{{ formatTime(log.end_time) }}</span>
                  <span v-if="endDateDiffers(log)"
                        style="display:block;font-size:0.75rem;color:#e57373;font-weight:600;"
                        title="La sortida es va registrar en un dia diferent">
                    {{ formatDate(log.end_time) }}
                  </span>
                </template>
                <template v-else>—</template>
              </td>
              <td><strong>{{ log.end_time ? formatH(Number(log.total_hours_worked || 0)) : '—' }}</strong></td>
              <td>
                <div style="display:flex;flex-direction:column;gap:2px;">
                  <span v-if="Number(log.extra_hours_authorized || 0) > 0" class="badge badge-success" title="Hores Extres Autoritzades">
                    +{{ Number(log.extra_hours_authorized).toFixed(1) }}h ✓
                  </span>
                  <span v-if="Number(log.extra_hours_unauthorized || 0) > 0" class="badge badge-warning" title="Hores Extres No Autoritzades">
                    +{{ Number(log.extra_hours_unauthorized).toFixed(1) }}h (pendent)
                  </span>
                  <span v-if="Number(log.hours_out_of_area || 0) > 0" class="badge badge-danger" title="Hores Fora de Zona Descomptades">
                    -{{ Number(log.hours_out_of_area).toFixed(1) }}h (Fora Zona)
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
                  <!-- GPS review badge: completed shift with out-of-area hours pending decision -->
                  <span v-if="needsGpsReview(log)"
                        class="badge"
                        style="font-size:0.75rem;display:flex;align-items:center;gap:4px;background:rgba(245,158,11,0.15);color:#b45309;">
                    🔍 En revisió GPS
                  </span>
                  <span v-else-if="log.hour_status && log.hour_status !== 'ok'"
                        class="badge"
                        style="font-size:0.75rem; display:flex; align-items:center; gap:4px;"
                        :class="log.hour_status === 'out_of_area' ? 'badge-danger' : 'badge-warning'"
                        :title="log.auto_closed ? 'Jornada tancada automàticament perquè el treballador no va fitxar la sortida' : ''">
                    <span v-if="log.hour_status === 'out_of_area'">📍</span>
                    <span v-else-if="log.hour_status === 'in_progress'">🕒</span>
                    <span v-else-if="log.hour_status === 'auto_closed'">🔒</span>
                    {{ t(log.hour_status) }}
                  </span>
                </div>
              </td>
              <td>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                  <!-- GPS review: admin must decide how to handle out-of-area hours -->
                  <template v-if="authStore.isAdmin && needsGpsReview(log)">
                    <div style="display:flex;flex-direction:column;gap:4px;width:100%;">
                      <div style="font-size:0.72rem;color:#b45309;font-weight:600;margin-bottom:2px;">
                        ⚠️ {{ Number(log.hours_out_of_area).toFixed(2) }}h fora GPS — Decideix:
                      </div>
                      <button class="btn btn-success btn-sm"
                              style="justify-content:flex-start;"
                              @click="approve(log.id)"
                              :title="`Aprovar jornada completa (restaurar ${Number(log.hours_out_of_area).toFixed(2)}h)`">
                        ✅ Aprovar jornada completa
                      </button>
                      <button class="btn btn-sm"
                              style="background:rgba(245,158,11,0.12);color:#b45309;border:1px solid rgba(245,158,11,0.4);justify-content:flex-start;"
                              @click="approveUnauthorized(log.id)"
                              :title="`Aprovar però marcar ${Number(log.hours_out_of_area).toFixed(2)}h com a no autoritzades`">
                        ⚠️ Aprovar (hores fora = no auth.)
                      </button>
                      <button class="btn btn-danger btn-sm"
                              style="justify-content:flex-start;"
                              @click="openReject(log.id)">
                        ❌ {{ t('reject') }}
                      </button>
                    </div>
                  </template>
                  <!-- Normal pending: standard approve / reject -->
                  <template v-else-if="authStore.isAdmin && log.status === 'pending'">
                    <button class="btn btn-success btn-sm" @click="approve(log.id)">{{ t('approve') }}</button>
                    <button class="btn btn-danger btn-sm" @click="openReject(log.id)">{{ t('reject') }}</button>
                  </template>
                  <template v-if="!authStore.isAdmin && log.status === 'rejected'">
                    <button class="btn btn-outline btn-sm" @click="openModify(log)">{{ t('modify_record') }}</button>
                  </template>
                </div>
              </td>
            </tr>
            <tr v-if="filteredLogs.length === 0">
              <td :colspan="authStore.isAdmin ? 8 : 7" class="text-center text-muted" style="padding: 32px;">
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
import { useAuthStore } from '../stores/auth'
import { useWorkLogStore } from '../stores/workLog'
import { db } from '../services/db'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const t = (key) => i18n.t(key)

const now = new Date()
const selectedMonth = ref(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`)
const selectedUser = ref('all')
const showRejectModal = ref(false)
const rejectReason = ref('')
const rejectLogId = ref(null)

const workers = ref([])
const users = ref([])
const filterReview = ref('all')

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

const filteredLogs = computed(() => {
  let logs = workLogStore.logs.filter(l => l.date && l.date.substring(0, 7) === selectedMonth.value)
  if (authStore.isAdmin && selectedUser.value !== 'all') {
    logs = logs.filter(l => l.user_id == selectedUser.value)
  }
  if (authStore.isWorker) {
    logs = logs.filter(l => l.user_id === authStore.userId)
  }
  // Filter by review status
  if (authStore.isAdmin && filterReview.value !== 'all') {
    if (filterReview.value === 'review') {
      logs = logs.filter(l => needsGpsReview(l))
    } else if (filterReview.value === 'pending') {
      logs = logs.filter(l => l.status === 'pending' && !needsGpsReview(l))
    } else if (filterReview.value === 'approved') {
      logs = logs.filter(l => l.status === 'approved')
    }
  }
  // Sort: GPS review first, then pending, then approved — within each group by date desc
  return logs.sort((a, b) => {
    const priority = l => needsGpsReview(l) ? 0 : l.status === 'pending' ? 1 : 2
    const pa = priority(a), pb = priority(b)
    if (pa !== pb) return pa - pb
    return new Date(b.date) - new Date(a.date)
  })
})

// onMounted moved up

// A log needs GPS review when: it's completed (has end_time), is pending, and has out-of-area hours
function needsGpsReview(log) {
  return log.status === 'pending'
    && !!log.end_time
    && Number(log.hours_out_of_area || 0) > 0
}

function approve(id) { workLogStore.approveLog(id) }
function approveUnauthorized(id) { workLogStore.approveWithUnauthorized(id) }

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
function formatTime(d) { return new Date(d).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Madrid' }) }
function endDateDiffers(log) {
  if (!log.end_time) return false
  const logDay = new Date(log.date).toLocaleDateString('sv')
  const endDay = new Date(log.end_time).toLocaleDateString('sv', { timeZone: 'Europe/Madrid' })
  return logDay !== endDay
}
function formatH(hoursDecimal) {
  const total = Number(hoursDecimal) || 0
  const h = Math.floor(total)
  const m = Math.round((total - h) * 60)
  if (h === 0) return m + 'min'
  if (m === 0) return h + 'h'
  return h + 'h ' + m + 'min'
}
function statusBadge(s) { return { 'badge-pending': s === 'pending', 'badge-success': s === 'approved', 'badge-danger': s === 'rejected' } }
</script>
