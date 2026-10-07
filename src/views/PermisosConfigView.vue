<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">Configuració de Permisos</h1><p class="page-subtitle">XII Conveni Col·lectiu d'Establiments Sanitaris de Catalunya</p></div>
      <div class="page-actions">
        <button class="btn btn-primary" @click="openCreateModal()">+ Nou tipus</button>
        <button class="btn btn-accent" @click="showMassModal = true">👥 Assignació massiva</button>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Tipus de permís</th>
              <th>Categoria</th>
              <th>Dies max</th>
              <th>Max/any</th>
              <th>Recuperable</th>
              <th>Remunerat</th>
              <th>Justificació</th>
              <th>Despl.</th>
              <th>{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="type in types" :key="type.id">
              <td><strong>{{ type.name }}</strong></td>
              <td><span class="badge" :class="categoryBadge(type.category)">{{ catLabel(type.category) }}</span></td>
              <td>{{ type.max_days || '∞' }}</td>
              <td>{{ type.max_per_year || '—' }}<span v-if="type.max_lifetime" class="text-small text-muted"> ({{ type.max_lifetime }}/vida)</span></td>
              <td><span :style="{ color: type.recoverable ? 'var(--color-warning)' : 'var(--color-success)' }">{{ type.recoverable ? 'Sí' : 'No' }}</span></td>
              <td><span :style="{ color: type.remunerated ? 'var(--color-success)' : 'var(--color-danger)' }">{{ type.remunerated ? 'Sí' : 'No' }}</span></td>
              <td>{{ type.requires_justification ? 'Sí' : 'No' }}</td>
              <td>{{ type.extends_with_travel ? `+${type.extra_days_travel || 2}d` : '—' }}</td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button class="btn btn-outline btn-sm" @click="openCreateModal(type)">✎</button>
                  <button class="btn btn-danger btn-sm" @click="deleteType(type.id)">✕</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Usage by Worker -->
    <div class="card mt-lg">
      <h3 class="card-title mb-md">Ús per treballador ({{ new Date().getFullYear() }})</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Treballador</th>
              <th v-for="type in trackedTypes" :key="type.id">{{ type.name.substring(0, 15) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="w in workers" :key="w.id">
              <td><strong>{{ w.name }}</strong></td>
              <td v-for="type in trackedTypes" :key="type.id">
                <span :style="{ color: isOverLimit(w.id, type) ? 'var(--color-danger)' : 'var(--color-text)', fontWeight: isOverLimit(w.id, type) ? '700' : '400' }">
                  {{ getUsage(w.id, type.id) }}
                </span>
                <span class="text-muted">/{{ type.max_per_year * (type.max_days || 1) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showCreateModal" class="modal-overlay" @click.self="showCreateModal = false">
      <div class="modal" style="max-width:650px;">
        <div class="modal-header">
          <h3 class="modal-title">{{ editing ? 'Editar' : 'Crear' }} tipus de permís</h3>
          <button class="icon-btn" @click="showCreateModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Nom del permís *</label>
          <input class="form-input" v-model="form.name" placeholder="Ex: Matrimoni / Parella de fet" />
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Categoria</label>
            <select class="form-select" v-model="form.category">
              <option value="personal">Personal</option>
              <option value="family">Familiar</option>
              <option value="health">Salut</option>
              <option value="training">Formació</option>
              <option value="public">Deure públic</option>
              <option value="vacation">Vacances</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Dies màxims</label>
            <input class="form-input" type="number" v-model.number="form.max_days" min="0" placeholder="0 = il·limitat" />
          </div>
        </div>
        <div class="form-3col-row">
          <div class="form-group">
            <label class="form-label">Max per any</label>
            <input class="form-input" type="number" v-model.number="form.max_per_year" min="0" />
          </div>
          <div class="form-group">
            <label class="form-label">Max per vida</label>
            <input class="form-input" type="number" v-model.number="form.max_lifetime" min="0" />
          </div>
          <div class="form-group">
            <label class="form-label">Preavís (hores)</label>
            <input class="form-input" type="number" v-model.number="form.advance_notice_hours" min="0" />
          </div>
        </div>
        <div class="form-3col-row" style="margin:12px 0;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" v-model="form.recoverable" style="width:16px;height:16px;" /> Recuperable
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" v-model="form.remunerated" style="width:16px;height:16px;" /> Remunerat
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" v-model="form.requires_justification" style="width:16px;height:16px;" /> Justificació
          </label>
        </div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px;">
          <input type="checkbox" v-model="form.extends_with_travel" style="width:16px;height:16px;" /> Es pot ampliar per desplaçament
        </label>
        <div v-if="form.extends_with_travel" class="form-group">
          <label class="form-label">Dies extra per desplaçament</label>
          <input class="form-input" type="number" v-model.number="form.extra_days_travel" min="1" style="width:120px;" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showCreateModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="saveType">{{ t('save') }}</button>
        </div>
      </div>
    </div>

    <!-- Mass Assignment Modal -->
    <div v-if="showMassModal" class="modal-overlay" @click.self="showMassModal = false">
      <div class="modal" style="max-width:500px;">
        <div class="modal-header">
          <h3 class="modal-title">Assignació massiva de permís</h3>
          <button class="icon-btn" @click="showMassModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Tipus de permís</label>
          <select class="form-select" v-model="massForm.absence_type_id">
            <option v-for="t in types" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Data inici</label>
            <input class="form-input" type="date" v-model="massForm.start_date" />
          </div>
          <div class="form-group">
            <label class="form-label">Data fi</label>
            <input class="form-input" type="date" v-model="massForm.end_date" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Treballadors</label>
          <div v-for="w in workers" :key="w.id" style="display:flex;align-items:center;gap:8px;padding:4px 0;">
            <input type="checkbox" :value="w.id" v-model="massForm.user_ids" style="width:16px;height:16px;" />
            <span>{{ w.name }}</span>
          </div>
          <div style="display:flex;gap:8px;margin-top:8px;">
            <button class="btn btn-outline btn-sm" @click="massForm.user_ids = workers.map(w => w.id)">Seleccionar tots</button>
            <button class="btn btn-outline btn-sm" @click="massForm.user_ids = []">Deseleccionar</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Motiu</label>
          <input class="form-input" v-model="massForm.reason" />
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showMassModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-accent" @click="massAssign">Assignar a {{ massForm.user_ids.length }} treballadors</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { confirma, demana } from '../utils/dialegs'
import { ref, computed, reactive, onMounted } from 'vue'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from '../stores/auth'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const t = (key) => i18n.t(key)
const types = ref([])
const workers = ref([])
const trackedTypes = computed(() => types.value.filter(t => t.max_per_year))
const showCreateModal = ref(false), showMassModal = ref(false), editing = ref(null)
const usageData = ref({}) // "userId-typeId" -> count

async function fetchData() {
  try {
    types.value = await db.getAbsenceTypes()
    const allUsers = await db.getUsers()
    workers.value = allUsers.filter(u => u.role === 'worker')
    
    // Fetch usage for all combinations
    const year = new Date().getFullYear()
    const usages = {}
    for (const w of workers.value) {
      for (const t of trackedTypes.value) {
        usages[`${w.id}-${t.id}`] = await db.getUsedDaysByType(w.id, t.id, year)
      }
    }
    usageData.value = usages
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const form = reactive({ name: '', category: 'personal', max_days: null, max_per_year: null, max_lifetime: null, recoverable: false, remunerated: true, requires_justification: false, advance_notice_hours: 48, extends_with_travel: false, extra_days_travel: 2 })
const massForm = reactive({ absence_type_id: 1, start_date: '', end_date: '', user_ids: [], reason: '' })

function openCreateModal(type = null) {
  editing.value = type
  if (type) Object.assign(form, type)
  else Object.assign(form, { name: '', category: 'personal', max_days: null, max_per_year: null, max_lifetime: null, recoverable: false, remunerated: true, requires_justification: false, advance_notice_hours: 48, extends_with_travel: false, extra_days_travel: 2 })
  showCreateModal.value = true
}

async function saveType() {
  try {
    if (editing.value) { 
      await db.updateAbsenceType({ ...editing.value, ...form })
      auditLog(authStore.userId, 'UPDATE_ABSENCE_TYPE', 'absence_type', editing.value.id, `Actualitzat: ${form.name}`) 
    } else { 
      await db.addAbsenceType({ ...form })
      auditLog(authStore.userId, 'CREATE_ABSENCE_TYPE', 'absence_type', null, `Creat: ${form.name}`) 
    }
    await fetchData()
    showCreateModal.value = false
  } catch (e) { console.error(e) }
}

async function deleteType(id) { 
  if (await confirma('Eliminar aquest tipus de permís?')) { 
    try {
      await db.deleteAbsenceType(id)
      await fetchData()
    } catch (e) { console.error(e) }
  } 
}

async function massAssign() {
  const newAbsences = massForm.user_ids.map(uid => ({
    user_id: uid, absence_type_id: massForm.absence_type_id, start_date: massForm.start_date, end_date: massForm.end_date, reason: massForm.reason, with_travel: false, approved: true, approved_by: authStore.userId
  }))
  try {
    await db.addAbsences(newAbsences)
    auditLog(authStore.userId, 'MASS_ASSIGN_PERMISSION', 'absence', null, `${massForm.user_ids.length} treballadors`)
    await fetchData()
    showMassModal.value = false
  } catch (e) { console.error(e) }
}

function getUsage(userId, typeId) { 
  return usageData.value[`${userId}-${typeId}`] || 0 
}
function isOverLimit(userId, type) { return type.max_per_year && getUsage(userId, type.id) >= type.max_per_year * (type.max_days || 1) }
function categoryBadge(c) { return { personal: 'badge-primary', family: 'badge-info', health: 'badge-warning', training: 'badge-success', public: 'badge-pending', vacation: 'badge-success' }[c] || 'badge-primary' }
function catLabel(c) { return { personal: 'Personal', family: 'Familiar', health: 'Salut', training: 'Formació', public: 'Públic', vacation: 'Vacances' }[c] || c }
</script>
