<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">✉️ Configuració de correu</h1>
        <p class="page-subtitle">Servidor SMTP i verificació d'enviament</p>
      </div>
      <button class="btn btn-outline" @click="$router.back()">← Tornar</button>
    </div>

    <div v-if="loading" class="card"><div class="empty-state">Carregant…</div></div>

    <div v-else class="card" style="max-width:680px;">
      <div class="form-group">
        <label class="form-label">Mètode d'enviament</label>
        <select class="form-select" v-model="form.mailer">
          <option value="smtp">SMTP (servidor de correu real)</option>
          <option value="log">Log (proves — no envia, escriu al registre)</option>
        </select>
      </div>

      <template v-if="form.mailer === 'smtp'">
        <div class="form-date-row">
          <div class="form-group" style="flex:2;">
            <label class="form-label">Servidor (host)</label>
            <input class="form-input" v-model="form.host" placeholder="smtp.ionos.es" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">Port</label>
            <input class="form-input" type="number" v-model.number="form.port" placeholder="587" />
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label">Xifratge</label>
            <select class="form-select" v-model="form.encryption">
              <option value="tls">TLS</option>
              <option value="ssl">SSL</option>
              <option value="none">Cap</option>
            </select>
          </div>
        </div>
        <div class="form-date-row">
          <div class="form-group">
            <label class="form-label">Usuari</label>
            <input class="form-input" v-model="form.username" placeholder="rrhh@crtbcn.cat" autocomplete="off" />
          </div>
          <div class="form-group">
            <label class="form-label">Contrasenya</label>
            <input class="form-input" type="password" v-model="form.password"
              :placeholder="hasPassword ? '•••••••• (deixa buit per mantenir)' : 'Contrasenya SMTP'" autocomplete="new-password" />
          </div>
        </div>
      </template>

      <div class="form-date-row">
        <div class="form-group">
          <label class="form-label">Remitent (from)</label>
          <input class="form-input" v-model="form.from_address" placeholder="rrhh@crtbcn.cat" />
        </div>
        <div class="form-group">
          <label class="form-label">Nom del remitent</label>
          <input class="form-input" v-model="form.from_name" placeholder="CRT RRHH" />
        </div>
      </div>

      <div v-if="saveMsg" class="text-small" :style="saveOk ? 'color:var(--color-success);' : 'color:var(--color-danger);'" style="margin:4px 0;">{{ saveMsg }}</div>

      <div style="display:flex;gap:8px;margin-top:8px;">
        <button class="btn btn-primary" @click="save" :disabled="saving">{{ saving ? 'Desant…' : 'Desar configuració' }}</button>
      </div>
    </div>

    <!-- Verificació d'enviament -->
    <div v-if="!loading" class="card mt-lg" style="max-width:680px;">
      <div class="card-header"><h3 class="card-title">🧪 Verificar enviament</h3></div>
      <p class="text-small text-muted" style="margin-bottom:12px;">Envia un correu de prova per confirmar que la configuració funciona. <strong>Desa primer</strong> els canvis.</p>
      <div class="form-date-row" style="align-items:flex-end;">
        <div class="form-group" style="flex:2;">
          <label class="form-label">Enviar prova a</label>
          <input class="form-input" type="email" v-model="testTo" placeholder="correu@exemple.com" />
        </div>
        <div class="form-group">
          <button class="btn btn-accent" @click="sendTest" :disabled="testing || !testTo">{{ testing ? 'Enviant…' : '📧 Enviar correu de prova' }}</button>
        </div>
      </div>

      <div v-if="testResult" class="mt-sm" style="padding:12px 14px;border-radius:8px;"
        :style="testResult.success ? 'background:rgba(76,175,80,0.1);color:var(--color-success);' : 'background:rgba(239,68,68,0.1);color:var(--color-danger);'">
        <strong>{{ testResult.success ? '✅ Enviat correctament' : '❌ Error en l\'enviament' }}</strong>
        <div class="text-small" style="margin-top:4px;word-break:break-word;">{{ testResult.message }}</div>
      </div>

      <div v-if="lastTest.at" class="text-small text-muted" style="margin-top:10px;">
        Última prova: {{ formatDateTime(lastTest.at) }} —
        <span :style="lastTest.status === 'ok' ? 'color:var(--color-success);' : 'color:var(--color-danger);'">{{ lastTest.status === 'ok' ? 'correcta' : 'amb error' }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { db } from '../services/db'

const authStore = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const hasPassword = ref(false)
const saveMsg = ref('')
const saveOk = ref(false)
const testTo = ref('')
const testResult = ref(null)
const lastTest = reactive({ at: null, status: null })

const form = reactive({
  mailer: 'smtp', host: '', port: 587, encryption: 'tls',
  username: '', password: '', from_address: '', from_name: ''
})

async function load() {
  loading.value = true
  try {
    const s = await db.getMailSettings()
    form.mailer = s.mailer || 'smtp'
    form.host = s.host || ''
    form.port = s.port || 587
    form.encryption = s.encryption || 'none'
    form.username = s.username || ''
    form.password = '' // mai es rep la contrasenya
    form.from_address = s.from_address || ''
    form.from_name = s.from_name || ''
    hasPassword.value = !!s.has_password
    lastTest.at = s.last_test_at
    lastTest.status = s.last_test_status
    testTo.value = authStore.userEmail || s.from_address || ''
  } catch (e) {
    console.error('[MailSettings] load', e)
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true; saveMsg.value = ''
  try {
    const payload = { ...form }
    if (!payload.password) delete payload.password // no sobreescriure si buit
    const s = await db.updateMailSettings(payload)
    hasPassword.value = !!s.has_password
    form.password = ''
    saveOk.value = true; saveMsg.value = 'Configuració desada correctament.'
  } catch (e) {
    console.error('[MailSettings] save', e)
    saveOk.value = false
    saveMsg.value = e?.errors ? 'Reviseu els camps: dades no vàlides.' : 'No s\'ha pogut desar.'
  } finally {
    saving.value = false
  }
}

async function sendTest() {
  testing.value = true; testResult.value = null
  try {
    const r = await db.sendTestMail(testTo.value)
    testResult.value = { success: true, message: r.message }
    lastTest.at = new Date().toISOString(); lastTest.status = 'ok'
  } catch (e) {
    // El backend retorna 422 amb {success:false, message}; l'api client ho embolica a e.errors
    const msg = e?.errors?.message || e?.message || 'Error desconegut en l\'enviament.'
    testResult.value = { success: false, message: msg }
    lastTest.at = new Date().toISOString(); lastTest.status = 'error'
  } finally {
    testing.value = false
  }
}

function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ca-ES', { dateStyle: 'short', timeStyle: 'short' })
}

onMounted(load)
</script>
