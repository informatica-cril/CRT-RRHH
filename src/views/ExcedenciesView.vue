<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">Excedències</h1><p class="page-subtitle">Sol·licitud i gestió de permisos especials</p></div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="showCreateModal = true">+ Sol·licitar excedència</button>
      </div>
    </div>

    <!-- Excedencia Types Info -->
    <div class="stats-grid mb-lg">
      <div v-for="et in excTypes" :key="et.id" class="stat-card" style="cursor:default;">
        <div class="stat-label">{{ et.name }}</div>
        <div class="text-small text-muted mt-sm">{{ et.description }}</div>
        <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
          <span class="badge badge-info" style="font-size:0.65rem;" v-if="et.min_months">{{ et.min_months }}-{{ et.max_months }}m</span>
          <span class="badge badge-success" style="font-size:0.65rem;" v-if="et.job_reserve">Reserva lloc</span>
          <span class="badge badge-warning" style="font-size:0.65rem;" v-if="et.requires_seniority_months > 0">Antiguitat: {{ et.requires_seniority_months }}m</span>
          <span class="badge badge-primary" style="font-size:0.65rem;" v-if="et.seniority_counts">Compta antiguitat</span>
        </div>
      </div>
    </div>

    <!-- Excedencias List -->
    <div class="card">
      <h3 class="card-title mb-md">Sol·licituds d'excedència</h3>
      <div v-if="excedencias.length === 0" style="text-align:center;padding:32px;color:var(--color-text-secondary);">
        No hi ha excedències registrades
      </div>
      <div class="table-container" v-else>
        <table>
          <thead>
            <tr>
              <th v-if="authStore.isStaff">Treballador</th>
              <th>Tipus</th>
              <th>Data inici</th>
              <th>Data fi</th>
              <th>Motiu</th>
              <th>Estat</th>
              <th v-if="authStore.isStaff">Accions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="exc in excedencias" :key="exc.id">
              <td v-if="authStore.isStaff">{{ getUserName(exc.user_id) }}</td>
              <td><strong>{{ getTypeName(exc.excedencia_type_id) }}</strong></td>
              <td>{{ formatDate(exc.start_date) }}</td>
              <td>{{ formatDate(exc.end_date) }}</td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">{{ exc.reason || '—' }}</td>
              <td><span class="badge" :class="statusBadge(exc.status)">{{ statusText(exc.status) }}</span></td>
              <td v-if="authStore.isStaff">
                <div v-if="exc.status === 'pending'" style="display:flex;gap:4px;">
                  <button class="btn btn-success btn-sm" @click="approveExc(exc)">✓</button>
                  <button class="btn btn-danger btn-sm" @click="rejectExc(exc)">✕</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal">
        <div class="modal-header">
          <h3 class="modal-title">Sol·licitar excedència</h3>
          <button class="icon-btn" @click="showCreateModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Tipus d'excedència</label>
          <select class="form-select" v-model="form.type_id">
            <option v-for="et in excTypes" :key="et.id" :value="et.id">{{ et.name }}</option>
          </select>
          <p v-if="selectedType" class="text-small text-muted mt-sm">{{ selectedType.description }}</p>
          <p v-if="selectedType?.requires_seniority_months > 0" class="text-small mt-sm" :style="{ color: hasSeniority ? 'var(--color-success)' : 'var(--color-danger)' }">
            {{ hasSeniority ? '✓ Compleix' : '✗ No compleix' }} requisit d'antiguitat ({{ selectedType.requires_seniority_months }} mesos)
          </p>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Data inici</label>
            <input class="form-input" type="date" v-model="form.start_date" />
          </div>
          <div class="form-group">
            <label class="form-label">Data fi</label>
            <input class="form-input" type="date" v-model="form.end_date" />
          </div>
        </div>
        <div v-if="durationMonths" class="text-small mb-md" :style="{ color: durationValid ? 'var(--color-success)' : 'var(--color-danger)' }">
          Durada: {{ durationMonths }} mesos {{ durationValid ? '✓' : '(fora de rang)' }}
        </div>
        <div class="form-group">
          <label class="form-label">Motiu / Justificació</label>
          <FrasesRapides clau="motiu_excedencia" v-model="form.reason" />
          <textarea class="form-textarea" v-model="form.reason" placeholder="Descriviu el motiu de la sol·licitud..."></textarea>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCreateModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="submitExc" :disabled="!form.start_date || !form.end_date || !hasSeniority">Sol·licitar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import { ref, computed, reactive, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const t = (key) => i18n.t(key)
const showCreateModal = ref(false)
const excTypes = ref([])
const excedencias = ref([])
const users = ref([])
const form = reactive({ type_id: 1, start_date: '', end_date: '', reason: '' })

async function fetchData() {
  try {
    excTypes.value = await db.getExcedenciaTypes()
    let excs = await db.getExcedencias()
    if (!authStore.isStaff) excs = excs.filter(e => e.user_id === authStore.userId)
    excedencias.value = excs.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
    
    if (authStore.isStaff) {
      users.value = await db.getUsers()
    }
  } catch (err) {
    console.error(err)
  }
}

onMounted(fetchData)

const selectedType = computed(() => excTypes.value.find(ext => ext.id === form.type_id))

const hasSeniority = computed(() => {
  if (!selectedType.value?.requires_seniority_months) return true
  const user = authStore.user
  if (!user?.seniority_date) return false
  const months = Math.floor((new Date() - new Date(user.seniority_date)) / (30.44 * 24 * 60 * 60 * 1000))
  return months >= selectedType.value.requires_seniority_months
})

const durationMonths = computed(() => {
  if (!form.start_date || !form.end_date) return null
  return Math.round((new Date(form.end_date) - new Date(form.start_date)) / (30.44 * 24 * 60 * 60 * 1000))
})

const durationValid = computed(() => {
  if (!durationMonths.value || !selectedType.value) return true
  const st = selectedType.value
  if (st.min_months && durationMonths.value < st.min_months) return false
  if (st.max_months && durationMonths.value > st.max_months) return false
  return true
})

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || '—' 
}

async function submitExc() {
  try {
    await db.addExcedencia({
      user_id: authStore.userId, 
      excedencia_type_id: form.type_id, 
      start_date: form.start_date, 
      end_date: form.end_date, 
      reason: form.reason, 
      status: 'pending'
    })
    auditLog(authStore.userId, 'REQUEST_EXCEDENCIA', 'excedencia', null, `${selectedType.value?.name}`)
    await fetchData()
    showCreateModal.value = false
    Object.assign(form, { type_id: 1, start_date: '', end_date: '', reason: '' })
  } catch (err) {
    console.error(err)
    alert('Error al enviar la sol·licitud')
  }
}

async function approveExc(exc) { 
  try {
    exc.status = 'approved'
    await db.updateExcedencia(exc)
    auditLog(authStore.userId, 'APPROVE_EXCEDENCIA', 'excedencia', exc.id)
    await fetchData()
  } catch (err) { console.error(err) }
}

async function rejectExc(exc) { 
  try {
    exc.status = 'rejected'
    await db.updateExcedencia(exc)
    auditLog(authStore.userId, 'REJECT_EXCEDENCIA', 'excedencia', exc.id)
    await fetchData()
  } catch (err) { console.error(err) }
}

function getTypeName(id) { 
  return excTypes.value.find(et => et.id === parseInt(id))?.name || '—' 
}
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function statusBadge(s) { return { pending: 'badge-pending', approved: 'badge-success', active: 'badge-info', rejected: 'badge-danger', finished: 'badge-primary' }[s] || 'badge-primary' }
function statusText(s) { return { pending: 'Pendent', approved: 'Aprovada', active: 'Activa', rejected: 'Rebutjada', finished: 'Finalitzada' }[s] || s }
</script>
