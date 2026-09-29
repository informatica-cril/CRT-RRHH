<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('absences') }}</h1>
      </div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreateModal = true">+ {{ t('absence_request') }}</button>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th v-if="authStore.isAdmin">{{ t('employee') }}</th>
              <th>{{ t('absence_type') }}</th>
              <th>{{ t('start_date') }}</th>
              <th>{{ t('end_date') }}</th>
              <th>{{ t('absence_reason') }}</th>
              <th>{{ t('status') }}</th>
              <th v-if="authStore.isAdmin">{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="absence in filteredAbsences" :key="absence.id">
              <td v-if="authStore.isAdmin">{{ getUserName(absence.user_id) }}</td>
              <td>{{ getAbsenceTypeName(absence.absence_type_id) }}</td>
              <td>{{ formatDate(absence.start_date) }}</td>
              <td>{{ formatDate(absence.end_date) }}</td>
              <td>{{ absence.reason || '—' }}</td>
              <td>
                <span class="badge" :class="absenceStatusBadge(absence.approved)">
                  {{ absenceStatusText(absence.approved) }}
                </span>
              </td>
              <td v-if="authStore.isAdmin">
                <div v-if="absence.approved === null" style="display: flex; gap: 4px;">
                  <button class="btn btn-success btn-sm" @click="approveAbsence(absence)">{{ t('approve') }}</button>
                  <button class="btn btn-danger btn-sm" @click="rejectAbsence(absence)">{{ t('reject') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Absence Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ t('absence_request') }}</h3>
          <button class="icon-btn" @click="showCreateModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('absence_type') }}</label>
          <select class="form-select" v-model="newAbsence.absence_type_id">
            <option v-for="at in absenceTypes" :key="at.id" :value="at.id">
              {{ at.name }}
              <span v-if="at.recoverable"> ({{ t('recoverable') }})</span>
            </option>
          </select>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">{{ t('start_date') }}</label>
            <input class="form-input" type="date" v-model="newAbsence.start_date" />
          </div>
          <div class="form-group">
            <label class="form-label">{{ t('end_date') }}</label>
            <input class="form-input" type="date" v-model="newAbsence.end_date" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">{{ t('absence_reason') }}</label>
          <textarea class="form-textarea" v-model="newAbsence.reason"></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCreateModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="createAbsence">{{ t('save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const t = (key) => i18n.t(key)
const showCreateModal = ref(false)
const absenceTypes = ref([])
const absences = ref([])
const users = ref([])

async function fetchData() {
  try {
    absenceTypes.value = await db.getAbsenceTypes()
    absences.value = await db.getAbsences()
    if (authStore.isAdmin) {
      users.value = await db.getUsers()
    }
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const newAbsence = reactive({
  absence_type_id: 1,
  start_date: '',
  end_date: '',
  reason: ''
})

const filteredAbsences = computed(() => {
  if (authStore.isAdmin) return absences.value.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  return absences.value.filter(a => a.user_id === authStore.userId).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

async function createAbsence() {
  try {
    await db.addAbsence({
      user_id: authStore.userId,
      absence_type_id: newAbsence.absence_type_id,
      start_date: newAbsence.start_date,
      end_date: newAbsence.end_date,
      reason: newAbsence.reason,
      approved: null
    })
    auditLog(authStore.userId, 'CREATE_ABSENCE', 'absence', null, `Sol·licitud d'absència`)
    await fetchData()
    showCreateModal.value = false
    Object.assign(newAbsence, { absence_type_id: 1, start_date: '', end_date: '', reason: '' })
  } catch (e) {
    console.error(e)
    alert('Error al enviar la sol·licitud')
  }
}

async function approveAbsence(absence) {
  try {
    absence.approved = true
    absence.approved_by = authStore.userId
    await db.updateAbsence(absence)
    auditLog(authStore.userId, 'APPROVE_ABSENCE', 'absence', absence.id, 'Aprovada')
    await fetchData()
  } catch (e) { console.error(e) }
}

async function rejectAbsence(absence) {
  try {
    absence.approved = false
    absence.approved_by = authStore.userId
    await db.updateAbsence(absence)
    auditLog(authStore.userId, 'REJECT_ABSENCE', 'absence', absence.id, 'Rebutjada')
    await fetchData()
  } catch (e) { console.error(e) }
}

function getUserName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function getAbsenceTypeName(id) { return absenceTypes.value.find(a => a.id === id)?.name || '—' }
function formatDate(d) { return new Date(String(d).substring(0, 10) + 'T12:00:00').toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function absenceStatusBadge(approved) {
  if (approved === null) return 'badge-pending'
  return approved ? 'badge-success' : 'badge-danger'
}
function absenceStatusText(approved) {
  if (approved === null) return t('pending')
  return approved ? t('approved') : t('rejected')
}
</script>
