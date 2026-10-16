<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ t('absences') }}</h1>
      </div>
      <div class="page-actions">
        <router-link v-if="authStore.canManageStaff" to="/absences/importa" class="btn btn-outline">📥 Importar l'Excel de RRHH</router-link>
        <button class="btn btn-primary" @click="showCreateModal = true">+ {{ t('absence_request') }}</button>
      </div>
    </div>

    <p class="abs-ajuda">
      {{ authStore.isStaff
        ? "Aquí hi ha les vacances, els permisos i les baixes de tothom. Les que diuen «Per resoldre» esperen que les aproveu o les denegueu."
        : "Aquí hi ha les teves vacances i permisos. Quan RRHH respongui, ho veuràs a la columna «Estat»." }}
    </p>

    <!-- Un filtre alhora, amb el recompte; gestió entra al que ha de fer -->
    <div class="abs-filtres">
      <button v-for="f in FILTRES" :key="f.clau" type="button" class="abs-filtre" :class="[f.to, { actiu: filtre === f.clau }]" @click="filtre = f.clau">
        {{ f.icona }} {{ f.nom }} <span class="abs-n">{{ recomptes[f.clau] }}</span>
      </button>
      <input v-if="authStore.isStaff" v-model="cerca" class="form-input abs-cerca" type="search" placeholder="🔎 Cerca per nom o tipus" />
    </div>

    <div class="card">
      <div v-if="!llista.length" class="abs-buit">
        {{ filtre === 'pendents' ? '🎉 No hi ha cap sol·licitud per resoldre.' : 'No hi ha cap absència en aquesta vista.' }}
      </div>
      <div v-else class="table-container">
        <table class="abs-taula">
          <thead>
            <tr>
              <th v-if="authStore.isStaff">Persona</th>
              <th>Tipus</th>
              <th>Quan</th>
              <th>Motiu</th>
              <th>Estat</th>
              <th>Justificant</th>
              <th v-if="authStore.isStaff">Accions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="absence in llista" :key="absence.id">
              <td v-if="authStore.isStaff" class="abs-nom">{{ getUserName(absence.user_id) }}</td>
              <td>{{ getAbsenceTypeName(absence.absence_type_id) }}</td>
              <!-- Inici i fi en una sola columna que no es parteix, amb els dies o les hores -->
              <td class="abs-quan">
                <strong>{{ formatDate(absence.start_date) }}</strong>
                <template v-if="dia(absence.end_date) !== dia(absence.start_date)"> → <strong>{{ formatDate(absence.end_date) }}</strong></template>
                <div class="text-small text-muted">
                  <template v-if="absence.hora_inici">de {{ hhmm(absence.hora_inici) }} a {{ hhmm(absence.hora_fi) }}</template>
                  <template v-else>{{ diesNaturals(absence) }} {{ diesNaturals(absence) === 1 ? 'dia' : 'dies' }}</template>
                </div>
              </td>
              <td class="abs-motiu">{{ absence.reason || '—' }}</td>
              <!-- Estat i, a sota, qui ho va resoldre i quan: una decisió sense nom i data no és una decisió -->
              <td class="abs-estat">
                <span class="badge" :class="absenceStatusBadge(absence.approved)">{{ absenceStatusText(absence.approved) }}</span>
                <div v-if="absence.approved_at" class="text-small text-muted">
                  per {{ absence.approved_by_user?.name || '—' }} · {{ formatDateTime(absence.approved_at) }}
                </div>
                <div v-if="absence.denial_reason" class="text-small" style="color:var(--color-danger);">Motiu: {{ absence.denial_reason }}</div>
              </td>
              <!-- Part mèdic / justificant: un botó en lloc del selector de fitxers del navegador -->
              <td class="text-small">
                <a v-if="absence.justificant_name" href="#" class="abs-fitxer" :title="absence.justificant_name" @click.prevent="baixaJustificant(absence)">📎 {{ absence.justificant_name }}</a>
                <label v-if="pucAdjuntar(absence)" class="btn btn-outline btn-sm abs-adjunta">
                  {{ absence.justificant_name ? 'Canviar' : '📎 Adjuntar' }}
                  <input type="file" accept=".pdf,.jpg,.jpeg,.png" hidden @change="pujaJustificant(absence, $event)" />
                </label>
                <span v-else-if="!absence.justificant_name" class="text-muted">—</span>
              </td>
              <td v-if="authStore.isStaff">
                <div v-if="absence.approved === null" class="abs-accions">
                  <button class="btn btn-success btn-sm" @click="approveAbsence(absence)">✅ {{ t('approve') }}</button>
                  <button class="btn btn-danger btn-sm" @click="rejectAbsence(absence)">✕ Denegar</button>
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
              {{ at.name }}{{ quotaText(at) }}{{ at.recoverable ? ` (${t('recoverable')})` : '' }}
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
        <!-- Permís en hores (metge, assumptes propis en hores…): opcional, d'una hora a l'altra -->
        <div v-if="selectedType?.unitat === 'hores' || selectedType?.unitat === 'temps_necessari'" class="form-date-row">
          <div class="form-group">
            <label class="form-label">Des de les (opcional)</label>
            <input class="form-input" type="time" v-model="newAbsence.hora_inici" />
          </div>
          <div class="form-group">
            <label class="form-label">Fins a les</label>
            <input class="form-input" type="time" v-model="newAbsence.hora_fi" />
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
  reason: '',
  hora_inici: '',
  hora_fi: ''
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
  if (authStore.isStaff) return [...absences.value].sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  return absences.value.filter(a => a.user_id === authStore.userId).sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

// Vistes per estat. Gestió entra a «Per resoldre», que és el que ha de fer.
const FILTRES = [
  { clau: 'pendents', nom: 'Per resoldre', icona: '⏳', to: 'groc', fn: a => a.approved === null },
  { clau: 'aprovades', nom: 'Aprovades', icona: '✅', to: 'verd', fn: a => a.approved === true },
  { clau: 'denegades', nom: 'Denegades', icona: '❌', to: 'neutre', fn: a => a.approved === false },
  { clau: 'totes', nom: 'Totes', icona: '📋', to: 'neutre', fn: () => true },
]
const filtre = ref(authStore.isStaff ? 'pendents' : 'totes')
const cerca = ref('')
const ambCerca = computed(() => {
  const q = cerca.value.trim().toLowerCase()
  if (!q) return filteredAbsences.value
  return filteredAbsences.value.filter(a => `${getUserName(a.user_id)} ${getAbsenceTypeName(a.absence_type_id)}`.toLowerCase().includes(q))
})
const recomptes = computed(() => Object.fromEntries(FILTRES.map(f => [f.clau, ambCerca.value.filter(f.fn).length])))
const llista = computed(() => ambCerca.value.filter((FILTRES.find(f => f.clau === filtre.value) || FILTRES[3]).fn))
// Si en entrar no hi ha res per resoldre, es mostren totes: una pantalla buida fa pensar que no hi ha dades.
watch(absences, () => { if (filtre.value === 'pendents' && !recomptes.value.pendents) filtre.value = 'totes' }, { once: true })
const dia = (d) => String(d).slice(0, 10)
const diesNaturals = (a) => Math.round((new Date(dia(a.end_date)) - new Date(dia(a.start_date))) / 86400000) + 1

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
      hora_inici: newAbsence.hora_inici || null,
      hora_fi: newAbsence.hora_fi || null,
      approved: null
    })
    auditLog(authStore.userId, 'CREATE_ABSENCE', 'absence', null, `Sol·licitud d'absència`)
    await fetchData()
    showCreateModal.value = false
    Object.assign(newAbsence, { absence_type_id: 1, start_date: '', end_date: '', reason: '', hora_inici: '', hora_fi: '' })
  } catch (e) {
    console.error(e)
    // El backend retorna 422 amb el detall del tope; l'api client ho embolica a e.errors
    createError.value = e?.errors?.end_date?.[0] || e?.errors?.hora_fi?.[0] || e?.errors?.hora_inici?.[0] || e?.errors?.message || e?.message || 'Error al enviar la sol·licitud.'
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

// «(3 dies/any)», «(24 h/any)», «(15 h/mes)»: el que diu la fulla CONCEPTOS de RRHH.
const UNITATS = { dies: 'dies', hores: 'h', setmanes: 'setmanes' }
const PERIODES = { any: '/any', mes: '/mes', fet: '' }
function quotaText(at) {
  if (at.unitat === 'temps_necessari') return ' (temps necessari)'
  if (at.quantitat == null || !UNITATS[at.unitat]) return ''
  const q = Number(at.quantitat).toLocaleString('ca-ES', { maximumFractionDigits: 2 })
  return ` (${q} ${UNITATS[at.unitat]}${PERIODES[at.periode] || ''})`
}
const hhmm = (h) => (h ? String(h).slice(0, 5) : '')
function getUserName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function getAbsenceTypeName(id) { return absenceTypes.value.find(a => a.id === id)?.name || '—' }
function formatDate(d) { return dia(d).split('-').reverse().join('/') }
function formatDateTime(d) { return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }
function absenceStatusBadge(approved) {
  if (approved === null) return 'badge-pending'
  return approved ? 'badge-success' : 'badge-danger'
}
function absenceStatusText(approved) {
  if (approved === null) return 'Per resoldre'
  return approved ? 'Aprovada' : 'Denegada'
}
</script>

<style scoped>
.abs-ajuda { margin: -6px 0 12px; color: var(--color-text-secondary); font-size: .9rem; }
.abs-filtres { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 12px; }
.abs-filtre { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 99px; border: 1px solid var(--color-border); background: var(--color-bg-card); color: var(--color-text); cursor: pointer; font-size: .86rem; }
.abs-filtre.actiu { background: var(--color-primary); border-color: var(--color-primary); color: #fff; }
.abs-n { background: rgba(0, 0, 0, .08); border-radius: 99px; padding: 0 8px; font-size: .78rem; font-weight: 700; }
.abs-filtre.actiu .abs-n { background: rgba(255, 255, 255, .25); }
.abs-filtre.groc:not(.actiu) .abs-n { background: #fde9b0; color: #6b5100; }
.abs-cerca { margin-left: auto; max-width: 260px; }
.abs-buit { padding: 28px; text-align: center; color: var(--color-text-secondary); }
.abs-taula td { vertical-align: top; }
.abs-nom { font-weight: 600; }
.abs-quan { white-space: nowrap; }
.abs-motiu { max-width: 260px; }
.abs-estat .text-small { margin-top: 3px; }
.abs-fitxer { display: inline-block; max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: middle; }
.abs-adjunta { margin-top: 4px; cursor: pointer; }
.abs-accions { display: flex; gap: 6px; flex-wrap: wrap; }
@media (max-width: 600px) { .abs-cerca { margin-left: 0; max-width: none; flex-basis: 100%; } }
</style>
