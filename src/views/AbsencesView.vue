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
              <th v-if="authStore.isStaff">{{ t('employee') }}</th>
              <th>{{ t('absence_type') }}</th>
              <th>{{ t('start_date') }}</th>
              <th>{{ t('end_date') }}</th>
              <th>{{ t('absence_reason') }}</th>
              <th>{{ t('status') }}</th>
              <th>Resolució</th>
              <th>Justificant</th>
              <th v-if="authStore.isStaff">{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="absence in filteredAbsences" :key="absence.id">
              <td v-if="authStore.isStaff">{{ getUserName(absence.user_id) }}</td>
              <td>{{ getAbsenceTypeName(absence.absence_type_id) }}</td>
              <td>{{ formatDate(absence.start_date) }}</td>
              <td>{{ formatDate(absence.end_date) }}</td>
              <td>{{ absence.reason || '—' }}</td>
              <td>
                <span class="badge" :class="absenceStatusBadge(absence.approved)">
                  {{ absenceStatusText(absence.approved) }}
                </span>
              </td>
              <!-- Qui va resoldre i quan: una decisió sense nom i data no és una decisió -->
              <td class="text-small">
                <template v-if="absence.approved_at">
                  {{ absence.approved_by_user?.name || '—' }}<br>
                  <span class="text-muted">{{ formatDateTime(absence.approved_at) }}</span>
                  <div v-if="absence.denial_reason" style="color:var(--color-danger);margin-top:2px;">
                    Motiu: {{ absence.denial_reason }}
                  </div>
                </template>
                <span v-else class="text-muted">—</span>
              </td>
              <!-- Part mèdic / justificant -->
              <td class="text-small">
                <a v-if="absence.justificant_name" href="#" @click.prevent="baixaJustificant(absence)">📎 {{ absence.justificant_name }}</a>
                <span v-else class="text-muted">—</span>
                <div v-if="pucAdjuntar(absence)" style="margin-top:4px;">
                  <input type="file" accept=".pdf,.jpg,.jpeg,.png" style="max-width:180px;font-size:.75rem;"
                    @change="pujaJustificant(absence, $event)" />
                </div>
              </td>
              <td v-if="authStore.isStaff">
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
        <!-- Vacances: dies naturals del tram + saldo anual (tope 30) -->
        <div v-if="isVacation && newAbsence.start_date && newAbsence.end_date" class="text-small"
          style="margin:-4px 0 8px;padding:8px 12px;border-radius:8px;"
          :style="vacationExceeded ? 'background:rgba(239,68,68,0.08);color:var(--color-danger);' : 'background:var(--color-bg);color:var(--color-text-secondary);'">
          <strong>Aquest tram: {{ tramoDays }} dies</strong>
          <span v-if="vacationBalance"> · ja demanats {{ vacationBalance.used_days }} · disponibles {{ vacationBalance.remaining }} de {{ vacationBalance.max_days }}</span>
          <div v-if="vacationExceeded" style="font-weight:700;margin-top:4px;">⚠️ Superes el màxim de {{ vacationBalance?.max_days || 30 }} dies. Redueix el tram.</div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ t('absence_reason') }}</label>
          <FrasesRapides clau="motiu_absencia" v-model="newAbsence.reason" />
          <textarea class="form-textarea" v-model="newAbsence.reason"></textarea>
        </div>
        <div v-if="createError" class="text-small" style="color:var(--color-danger);margin-bottom:6px;">{{ createError }}</div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCreateModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="createAbsence" :disabled="vacationExceeded">{{ t('save') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, reactive, onMounted, watch } from 'vue'
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
const holidaySet = ref(new Set())

async function fetchData() {
  try {
    absenceTypes.value = await db.getAbsenceTypes()
    absences.value = await db.getAbsences()
    const holidays = await db.getHolidays().catch(() => [])
    holidaySet.value = new Set((holidays || []).map(h => String(h.date).substring(0, 10)))
    if (authStore.isStaff) {
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
const createError = ref('')
const vacationBalance = ref(null)

const selectedType = computed(() => absenceTypes.value.find(t => t.id === Number(newAbsence.absence_type_id)) || null)
const isVacation = computed(() => selectedType.value?.category === 'vacation')

function isNonWorking(d) {
  const dow = d.getDay()
  if (dow === 0 || dow === 6) return true // diumenge/dissabte
  const iso = d.toISOString().slice(0, 10)
  return holidaySet.value.has(iso)
}
// Dies naturals del tram amb extensió per finde i festius fins al següent laborable (mirall del backend)
function vacationDaysJS(start, end) {
  if (!start || !end) return 0
  const s = new Date(start + 'T00:00:00'), e = new Date(end + 'T00:00:00')
  if (e < s) return 0
  // Estendre el final a través de dies no laborables consecutius
  let guard = 0
  let next = new Date(e); next.setDate(next.getDate() + 1)
  while (isNonWorking(next) && guard < 31) {
    e.setDate(e.getDate() + 1)
    next.setDate(next.getDate() + 1)
    guard++
  }
  return Math.round((e - s) / 86400000) + 1
}
const tramoDays = computed(() => {
  if (!newAbsence.start_date || !newAbsence.end_date) return 0
  return isVacation.value
    ? vacationDaysJS(newAbsence.start_date, newAbsence.end_date)
    : Math.max(0, Math.round((new Date(newAbsence.end_date) - new Date(newAbsence.start_date)) / 86400000) + 1)
})
const vacationExceeded = computed(() => {
  if (!isVacation.value || !vacationBalance.value) return false
  return (Number(vacationBalance.value.used_days) + tramoDays.value) > Number(vacationBalance.value.max_days || 30)
})

async function loadVacationBalance() {
  vacationBalance.value = null
  if (!isVacation.value) return
  const year = (newAbsence.start_date ? new Date(newAbsence.start_date) : new Date()).getFullYear()
  try {
    vacationBalance.value = await db.getAbsenceBalance(authStore.userId, newAbsence.absence_type_id, year)
  } catch (e) { console.error('[Absences] balance', e); vacationBalance.value = null }
}
watch(() => [newAbsence.absence_type_id, newAbsence.start_date], loadVacationBalance)

const filteredAbsences = computed(() => {
  if (authStore.isStaff) return absences.value.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  return absences.value.filter(a => a.user_id === authStore.userId).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

async function createAbsence() {
  createError.value = ''
  if (!newAbsence.start_date || !newAbsence.end_date) { createError.value = 'Indica les dates d\'inici i fi.'; return }
  if (vacationExceeded.value) { createError.value = 'Superes el màxim de dies de vacances.'; return }
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
    // El backend retorna 422 amb el detall del tope; l'api client ho embolica a e.errors
    createError.value = e?.errors?.end_date?.[0] || e?.errors?.message || e?.message || 'Error al enviar la sol·licitud.'
  }
}

// L'APROVADOR el posa el servidor a partir del token: no s'envia des d'aquí.
// L'auditoria també la deixa el servidor, no la pantalla.
async function approveAbsence(absence) {
  try {
    await db.updateAbsence({ id: absence.id, approved: true })
    await fetchData()
  } catch (e) { alert(e?.message || 'No s\'ha pogut aprovar.') }
}

async function rejectAbsence(absence) {
  const motiu = await demana('Motiu de la denegació (obligatori):', { frases: 'denegacio' })
  if (motiu === null) return
  if (!motiu.trim()) { alert('Cal escriure el motiu de la denegació.'); return }
  try {
    await db.updateAbsence({ id: absence.id, approved: false, denial_reason: motiu.trim() })
    await fetchData()
  } catch (e) { alert(e?.message || 'No s\'ha pogut denegar.') }
}

/* Part mèdic: el pot adjuntar el titular mentre la sol·licitud no estigui denegada,
   i gestió sempre (sovint arriba en paper i el registra RRHH). */
function pucAdjuntar(absence) {
  return authStore.isStaff || (absence.user_id === authStore.userId && absence.approved !== false)
}

async function pujaJustificant(absence, ev) {
  const fitxer = ev.target.files?.[0]
  if (!fitxer) return
  try {
    await db.uploadAbsenceJustificant(absence.id, fitxer)
    await fetchData()
  } catch (e) {
    alert(e?.errors?.justificant?.[0] || e?.message || 'No s\'ha pogut pujar el justificant.')
  } finally { ev.target.value = '' }
}

async function baixaJustificant(absence) {
  try {
    await db.downloadAbsenceJustificant(absence.id, absence.justificant_name)
  } catch (e) { alert(e?.message || 'No s\'ha pogut baixar el justificant.') }
}

function getUserName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function getAbsenceTypeName(id) { return absenceTypes.value.find(a => a.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function formatDateTime(d) { return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
function absenceStatusBadge(approved) {
  if (approved === null) return 'badge-pending'
  return approved ? 'badge-success' : 'badge-danger'
}
function absenceStatusText(approved) {
  if (approved === null) return t('pending')
  return approved ? t('approved') : t('rejected')
}
</script>
