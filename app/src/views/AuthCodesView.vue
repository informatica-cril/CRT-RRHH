<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('auth_codes') }}</h1><p class="page-subtitle">Gestió completa de codis d'autorització d'hores extres</p></div>
      <div class="page-actions" v-if="authStore.isAdmin">
        <button class="btn btn-primary" @click="openCreateModal">+ {{ t('generate_code') }}</button>
      </div>
    </div>

    <div class="card">
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>{{ t('auth_code') }}</th>
              <th>Concepte</th>
              <th>Hores</th>
              <th>Franja</th>
              <th>Destinatari</th>
              <th>Validesa</th>
              <th>{{ t('status') }}</th>
              <th v-if="authStore.isAdmin">{{ t('actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="code in codes" :key="code.id">
              <td><code style="background:var(--color-bg);padding:4px 8px;border-radius:4px;font-weight:600;">{{ code.code }}</code></td>
              <td>{{ code.concept || '—' }}</td>
              <td><strong>{{ code.authorized_hours }}h</strong></td>
              <td>{{ code.time_slot_start }} - {{ code.time_slot_end }}</td>
              <td>{{ code.user_id ? getUserName(code.user_id) : 'Qualsevol' }}</td>
              <td><span class="text-small">{{ formatDate(code.valid_from) }} → {{ formatDate(code.valid_to) }}</span></td>
              <td>
                <span class="badge" :class="codeBadge(code)">{{ codeStatusText(code) }}</span>
              </td>
              <td v-if="authStore.isAdmin">
                <div style="display:flex;gap:4px;">
                  <button v-if="!code.revoked && !code.used" class="btn btn-danger btn-sm" @click="revokeCode(code)">⊘ Revocar</button>
                  <span v-if="code.notification_sent" class="badge badge-info" style="font-size:0.65rem;">📧 Enviat</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Worker: Validate -->
    <div v-if="authStore.isWorker" class="card mt-lg" style="max-width:500px;">
      <h3 class="card-title mb-md">{{ t('validate_code') }}</h3>
      <div style="display:flex;gap:8px;">
        <input class="form-input" v-model="codeInput" :placeholder="t('enter_code')" />
        <button class="btn btn-accent" @click="validate">{{ t('validate_code') }}</button>
      </div>
      <div v-if="validationResult" class="mt-sm" style="padding:8px 12px;border-radius:8px;" :style="{ background: validationResult.valid ? 'rgba(76,175,80,0.1)' : 'rgba(239,68,68,0.1)', color: validationResult.valid ? 'var(--color-success)' : 'var(--color-danger)' }">
        <strong>{{ validationResult.valid ? '✓ Codi vàlid' : '✗ ' + validationResult.reason }}</strong>
        <div v-if="validationResult.valid && validationResult.code" class="text-small mt-sm">
          Concepte: {{ validationResult.code.concept }} · {{ validationResult.code.authorized_hours }}h · Franja: {{ validationResult.code.time_slot_start }}-{{ validationResult.code.time_slot_end }}
        </div>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="showModal = false">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header">
          <h3 class="modal-title">Nou codi d'autorització</h3>
          <button class="icon-btn" @click="showModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Concepte *</label>
          <input class="form-input" v-model="form.concept" placeholder="Ex: Projecte urgent client A" />
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Hores autoritzades *</label>
            <input class="form-input" type="number" v-model.number="form.authorized_hours" min="1" max="24" />
          </div>
          <div class="form-group">
            <label class="form-label">Destinatari</label>
            <select class="form-select" v-model="form.user_id">
              <option :value="null">Qualsevol treballador</option>
              <option v-for="u in workers" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Franja horària inici</label>
            <input class="form-input" type="time" v-model="form.time_slot_start" />
          </div>
          <div class="form-group">
            <label class="form-label">Franja horària fi</label>
            <input class="form-input" type="time" v-model="form.time_slot_end" />
          </div>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Vàlid des de</label>
            <input class="form-input" type="date" v-model="form.valid_from" />
          </div>
          <div class="form-group">
            <label class="form-label">Vàlid fins a</label>
            <input class="form-input" type="date" v-model="form.valid_to" />
          </div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;margin-top:8px;cursor:pointer;">
          <input type="checkbox" v-model="form.send_notification" style="width:16px;height:16px;" />
          <span>Enviar notificació per email al destinatari</span>
        </label>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-primary" @click="createCode" :disabled="!form.concept || !form.authorized_hours">{{ t('generate_code') }}</button>
        </div>
      </div>
    </div>

    <!-- Revoke Confirm Modal -->
    <div v-if="showRevokeModal" class="modal-overlay" @click.self="showRevokeModal = false">
      <div class="modal" style="max-width:400px;">
        <div class="modal-header">
          <h3 class="modal-title">⚠️ Confirmar revocació</h3>
        </div>
        <p>Esteu segurs de revocar el codi <strong>{{ revokeTarget?.code }}</strong>?</p>
        <p class="text-small text-muted mt-sm">Aquesta acció és immediata i no es pot desfer.</p>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showRevokeModal = false">{{ t('cancel') }}</button>
          <button class="btn btn-danger" @click="confirmRevoke">Revocar immediatament</button>
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

console.log('[AuthCodesView] Compilando componente...')

const authStore = useAuthStore()
const t = (key) => i18n.t(key)

const codes = ref([])
const codeInput = ref('')
const validationResult = ref(null)
const showModal = ref(false)
const showRevokeModal = ref(false)
const revokeTarget = ref(null)
const workers = ref([])
const users = ref([])

async function fetchData() {
  try {
    codes.value = await db.getAuthCodes()
    const allUsers = await db.getUsers()
    users.value = allUsers
    workers.value = allUsers.filter(u => u.role === 'worker')
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

const now = new Date()
const form = reactive({
  concept: '', authorized_hours: 4, user_id: null,
  time_slot_start: '16:00', time_slot_end: '20:00',
  valid_from: now.toISOString().split('T')[0],
  valid_to: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0],
  send_notification: true
})

function openCreateModal() {
  Object.assign(form, { concept: '', authorized_hours: 4, user_id: null, time_slot_start: '16:00', time_slot_end: '20:00', valid_from: now.toISOString().split('T')[0], valid_to: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0], send_notification: true })
  showModal.value = true
}

async function createCode() {
  const arr = new Uint8Array(9)
  crypto.getRandomValues(arr)
  const code = Array.from(arr, b => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'[b % 32]).join('').replace(/(.{4})/g, '$1-').slice(0, 14)
  try {
    await db.addAuthCode({
      code, concept: form.concept, authorized_hours: form.authorized_hours,
      time_slot_start: form.time_slot_start, time_slot_end: form.time_slot_end,
      generated_by: authStore.userId, user_id: form.user_id,
      valid_from: new Date(form.valid_from).toISOString(),
      valid_to: new Date(form.valid_to + 'T23:59:59').toISOString(),
      used: false, revoked: false,
      notification_sent: form.send_notification
    })
    auditLog(authStore.userId, 'GENERATE_AUTH_CODE', 'authorization_code', null, `Codi: ${code}, Concepte: ${form.concept}, ${form.authorized_hours}h`)
    if (form.send_notification && form.user_id) {
      const user = users.value.find(u => u.id === form.user_id)
      auditLog(authStore.userId, 'SEND_NOTIFICATION', 'authorization_code', null, `Email enviat a ${user?.email}: ${code}`)
    }
    await fetchData()
    showModal.value = false
  } catch (e) { console.error(e) }
}

function revokeCode(code) { revokeTarget.value = code; showRevokeModal.value = true }

async function confirmRevoke() {
  if (revokeTarget.value) {
    try {
      await db.revokeAuthCode(revokeTarget.value.id)
      auditLog(authStore.userId, 'REVOKE_AUTH_CODE', 'authorization_code', revokeTarget.value.id, `Revocat: ${revokeTarget.value.code}`)
      await fetchData()
      showRevokeModal.value = false
    } catch (e) { console.error(e) }
  }
}

async function validate() { 
  try {
    validationResult.value = await db.validateAuthCode(codeInput.value) 
  } catch (e) { console.error(e) }
}
function getUserName(id) { return users.value.find(u => u.id === id)?.name || '—' }
function formatDate(d) { return new Date(d).toLocaleDateString('ca-ES', { day: '2-digit', month: 'short', year: 'numeric' }) }
function codeBadge(c) { return c.revoked ? 'badge-danger' : c.used ? 'badge-warning' : isExpired(c) ? 'badge-warning' : 'badge-success' }
function codeStatusText(c) { return c.revoked ? 'Revocat' : c.used ? t('code_used') : isExpired(c) ? t('code_expired') : 'Actiu' }
function isExpired(c) { return new Date() > new Date(c.valid_to) }
</script>
