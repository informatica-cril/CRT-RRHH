<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">🔍 Supervisió del Xat Corporatiu</h1>
        <p class="page-subtitle">Panel de control, alertes forenses i configuració de paraules clau</p>
      </div>
      <div class="page-actions" style="display:flex;gap:8px;">
        <button class="btn btn-outline" @click="showSettingsModal = true">⚙️ Configuració</button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-lg">
      <div class="stat-card" style="border-left:3px solid var(--color-danger);">
        <div class="stat-label">Alertes no revisades</div>
        <div class="stat-value" style="color:var(--color-danger);">{{ unreviewedAlerts.length }}</div>
      </div>
      <div class="stat-card primary">
        <div class="stat-label">Total converses</div>
        <div class="stat-value">{{ allConversations.length }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Missatges totals</div>
        <div class="stat-value">{{ totalMessages }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Paraules clau</div>
        <div class="stat-value">{{ forensicKeywords.length }}</div>
      </div>
      <div class="stat-card" style="border-left:3px solid #f97316;">
        <div class="stat-label">📍 Alertes geo-zona</div>
        <div class="stat-value" style="color:#f97316;">{{ unacknowledgedGeoAlerts.length }}</div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;gap:4px;margin-bottom:20px;">
      <button v-for="tab in ['alerts','geo','conversations','search']" :key="tab" class="btn" 
        :class="activeTab === tab ? 'btn-primary' : 'btn-outline'" @click="activeTab = tab" style="text-transform:capitalize;">
        {{ tab === 'alerts' ? '🚨 Alertes' : tab === 'geo' ? '📍 Geo-Zona' : tab === 'conversations' ? '💬 Converses' : '🔍 Cerca forense' }}
      </button>
    </div>

    <!-- ═══ ALERTS TAB ═══ -->
    <div v-if="activeTab === 'alerts'" class="card">
      <div class="card-header">
        <h3 class="card-title">Alertes per paraules clau</h3>
      </div>
      <div v-if="!allAlerts.length" class="empty-state"><p>No hi ha alertes registrades</p></div>
      <div class="table-container" v-else>
        <table>
          <thead>
            <tr><th>Data</th><th>Treballador</th><th>Paraula clau</th><th>Conversa</th><th>Extracte</th><th>Estat</th><th>Accions</th></tr>
          </thead>
          <tbody>
            <tr v-for="alert in allAlerts" :key="alert.id" :style="!alert.reviewed ? 'background:rgba(239,68,68,0.04);' : ''">
              <td class="text-small">{{ formatDate(alert.created_at) }}</td>
              <td style="font-weight:500;">{{ getUserName(alert.message?.sender_id) }}</td>
              <td><span class="badge badge-danger" style="font-size:0.7rem;">{{ alert.keyword }}</span></td>
              <td class="text-small">{{ getConvName(alert.message?.conversation_id) }}</td>
              <td style="max-width:250px;font-size:0.82rem;word-break:break-word;">
                <span v-html="highlightKeyword(alert.content_excerpt, alert.keyword)"></span>
              </td>
              <td>
                <span v-if="alert.reviewed" class="badge badge-success" style="font-size:0.65rem;">✓ Revisat</span>
                <span v-else class="badge badge-danger" style="font-size:0.65rem;animation:pulse 2s infinite;">⚠️ Pendent</span>
              </td>
              <td>
                <button v-if="!alert.reviewed" class="btn btn-outline btn-sm" @click="markReviewed(alert.id)">✓ Revisar</button>
                <button class="btn btn-outline btn-sm" @click="viewAlertContext(alert)">👁️</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ GEO-ZONE ALERTS TAB ═══ -->
    <div v-if="activeTab === 'geo'" class="card">
      <div class="card-header">
        <h3 class="card-title">📍 Alertes de sortida de zona</h3>
        <div class="text-small text-muted">Treballadors que han sortit de la seva zona durant horari de treball</div>
      </div>
      <div v-if="!allGeoAlerts.length" class="empty-state"><p>No hi ha alertes de geo-zona registrades</p></div>
      <div class="table-container" v-else>
        <table>
          <thead>
            <tr><th>Data</th><th>Treballador</th><th>Temps fora</th><th>Sortida</th><th>Retorn</th><th>Descomptat</th><th>Estat</th><th>Accions</th></tr>
          </thead>
          <tbody>
            <tr v-for="ga in allGeoAlerts" :key="ga.id" :style="!ga.acknowledged_admin ? 'background:rgba(249,115,22,0.06);border-left:3px solid #f97316;' : ''">
              <td class="text-small">{{ formatDate(ga.created_at) }}</td>
              <td style="font-weight:600;">{{ ga.user_name || getUserName(ga.user_id) }}</td>
              <td>
                <span style="font-weight:700;color:#f97316;font-size:1.05rem;">{{ ga.minutes_outside }} min</span>
              </td>
              <td class="text-small">{{ formatTime(ga.exit_time) }}</td>
              <td class="text-small">{{ formatTime(ga.return_time) }}</td>
              <td>
                <span v-if="ga.deducted" class="badge badge-danger" style="font-size:0.7rem;">−{{ (ga.minutes_outside / 60).toFixed(1) }}h</span>
                <span v-else class="badge badge-info" style="font-size:0.7rem;">No</span>
              </td>
              <td>
                <span v-if="ga.acknowledged_admin" class="badge badge-success" style="font-size:0.65rem;">✓ Revisat</span>
                <span v-else class="badge badge-warning" style="font-size:0.65rem;animation:pulse 2s infinite;">⚠️ Pendent</span>
              </td>
              <td>
                <button v-if="!ga.acknowledged_admin" class="btn btn-outline btn-sm" @click="ackGeoAlert(ga.id)">✓ Revisar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ CONVERSATIONS TAB ═══ -->
    <div v-if="activeTab === 'conversations'" class="card">
      <div class="card-header"><h3 class="card-title">Totes les converses</h3></div>
      <div class="table-container">
        <table>
          <thead><tr><th>Tipus</th><th>Nom</th><th>Membres</th><th>Missatges</th><th>Última activitat</th><th>Accions</th></tr></thead>
          <tbody>
            <tr v-for="conv in allConversations" :key="conv.id">
              <td><span class="badge" :class="conv.type === 'channel' ? 'badge-info' : conv.type === 'dm' ? 'badge-warning' : 'badge-primary'">{{ conv.type }}</span></td>
              <td style="font-weight:500;">{{ conv.name || getDmParticipants(conv) }}</td>
              <td class="text-small">{{ conv.members.map(id => getUserName(id)).join(', ') }}</td>
              <td>{{ getMessageCount(conv.id) }}</td>
              <td class="text-small text-muted">{{ formatDate(getLastMsgTime(conv.id)) }}</td>
              <td><button class="btn btn-outline btn-sm" @click="openConvViewer(conv)">👁️ Veure</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ═══ SEARCH TAB ═══ -->
    <div v-if="activeTab === 'search'" class="card">
      <div style="padding:16px;display:flex;gap:12px;">
        <input class="form-input" v-model="forensicSearch" placeholder="Cercar en tots els missatges..." style="flex:1;" @keyup.enter="doForensicSearch" />
        <SelectorPersona v-model="searchFilterUser" :persones="allUsers" :buida="{ valor: '', text: 'Tots els usuaris' }" style="width:220px;" />
        <button class="btn btn-primary" @click="doForensicSearch">Cercar</button>
      </div>
      <div v-if="searchResults.length" class="table-container">
        <table>
          <thead><tr><th>Data</th><th>Remitent</th><th>Conversa</th><th>Contingut</th></tr></thead>
          <tbody>
            <tr v-for="msg in searchResults" :key="msg.id">
              <td class="text-small">{{ formatDate(msg.created_at) }}</td>
              <td style="font-weight:500;">{{ getUserName(msg.sender_id) }}</td>
              <td class="text-small">{{ getConvName(msg.conversation_id) }}</td>
              <td style="max-width:400px;font-size:0.85rem;word-break:break-word;">
                <span v-html="highlightKeyword(msg.content, forensicSearch)"></span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="searchDone" class="empty-state" style="padding:30px;"><p>Cap resultat trobat</p></div>
    </div>

    <!-- ═══ CONVERSATION VIEWER MODAL ═══ -->
    <div v-if="showConvViewer" class="modal-overlay" @click.self="showConvViewer = false">
      <div class="modal" style="max-width:700px;max-height:85vh;display:flex;flex-direction:column;">
        <div class="modal-header">
          <div>
            <h3 class="modal-title">👁️ {{ viewingConv?.name || 'Conversa' }}</h3>
            <div class="text-small text-muted">Mode de supervisió — {{ viewingMessages.length }} missatges</div>
          </div>
          <button class="icon-btn" @click="showConvViewer = false">✕</button>
        </div>
        <div style="flex:1;overflow-y:auto;padding:16px;">
          <div v-for="msg in viewingMessages" :key="msg.id" style="padding:8px;border-bottom:1px solid var(--color-border-light);font-size:0.85rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:2px;">
              <span style="font-weight:600;">{{ getUserName(msg.sender_id) }}</span>
              <span class="text-muted" style="font-size:0.75rem;">{{ formatDate(msg.created_at) }}</span>
            </div>
            <div style="color:var(--color-text-secondary);">{{ msg.content }}</div>
            <div v-if="msg.file_name" class="text-small" style="color:var(--color-primary);margin-top:2px;">📎 {{ msg.file_name }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══ SETTINGS MODAL ═══ -->
    <div v-if="showSettingsModal" class="modal-overlay" @click.self="showSettingsModal = false">
      <div class="modal" style="max-width:600px;">
        <div class="modal-header">
          <h3 class="modal-title">⚙️ Configuració del Xat</h3>
          <button class="icon-btn" @click="showSettingsModal = false">✕</button>
        </div>
        <div class="form-group">
          <label class="form-label">Paraules clau d'alerta forense</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">
            <span v-for="(kw, i) in editKeywords" :key="i" 
              style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);border-radius:16px;padding:4px 10px;font-size:0.82rem;display:flex;align-items:center;gap:6px;">
              {{ kw }}
              <button @click="editKeywords.splice(i, 1)" style="background:none;border:none;cursor:pointer;font-size:0.9rem;color:var(--color-danger);padding:0;">✕</button>
            </span>
          </div>
          <div style="display:flex;gap:8px;">
            <input class="form-input" v-model="newKeyword" placeholder="Nova paraula clau..." @keyup.enter="addKeyword" />
            <button class="btn btn-outline" @click="addKeyword" :disabled="!newKeyword.trim()">+</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Requerir acceptació de política</label>
          <select class="form-select" v-model="editRequirePolicy">
            <option :value="true">Sí (obligatòria)</option>
            <option :value="false">No</option>
          </select>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showSettingsModal = false">Cancel·lar</button>
          <button class="btn btn-primary" @click="saveSettings">Desar configuració</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import SelectorPersona from '../components/SelectorPersona.vue'
import { ref, computed, reactive, onMounted } from 'vue'
import { db } from '../services/db'
import { useAuthStore } from '../stores/auth'
import { auditLog } from '../services/audit'

const authStore = useAuthStore()
const activeTab = ref('alerts')
const showSettingsModal = ref(false)
const showConvViewer = ref(false)
const viewingConv = ref(null)
const viewingMessages = ref([])
const forensicSearch = ref('')
const searchFilterUser = ref('')
const searchResults = ref([])
const searchDone = ref(false)

const allAlerts = ref([])
const unreviewedAlerts = ref([])
const allConversations = ref([])
const users = ref([])
const settings = ref({})
const forensicKeywords = computed(() => settings.value?.forensic_keywords || [])
const allGeoAlerts = ref([])
const unacknowledgedGeoAlerts = ref([])
const messageCounts = ref({}) // convId -> count
const lastMsgTimes = ref({}) // convId -> time

const totalMessages = computed(() => allConversations.value.reduce((sum, c) => sum + (messageCounts.value[c.id] || 0), 0))
const allUsers = computed(() => users.value)

const editKeywords = ref([])
const editRequirePolicy = ref(true)
const newKeyword = ref('')

async function fetchData() {
  try {
    allAlerts.value = await db.getChatAlerts()
    unreviewedAlerts.value = await db.getUnreviewedAlerts()
    allConversations.value = await db.getAllChatConversations()
    users.value = await db.getUsers()
    settings.value = await db.getChatSettings()
    allGeoAlerts.value = await db.getGeoZoneAlerts()
    unacknowledgedGeoAlerts.value = await db.getUnacknowledgedGeoAlerts(null, 'admin')
    
    // Edit state
    editKeywords.value = [...(settings.value.forensic_keywords || [])]
    editRequirePolicy.value = settings.value.require_policy_acceptance !== false
    
    // Pre-calculate meta
    const counts = {}
    const times = {}
    for (const c of allConversations.value) {
      const msgs = await db.getChatMessages(c.id)
      counts[c.id] = msgs.length
      const last = await db.getLastMessage(c.id)
      times[c.id] = last?.created_at || ''
    }
    messageCounts.value = counts
    lastMsgTimes.value = times
  } catch (e) {
    console.error(e)
  }
}

onMounted(fetchData)

function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || 'Desconegut' 
}
function getConvName(id) {
  const conv = typeof id === 'object' ? id : allConversations.value.find(c => c.id === id)
  if (!conv) return '—'
  if (conv.type === 'dm') return getDmParticipants(conv)
  return conv.name || 'Grup'
}
function getDmParticipants(conv) { return conv.members.map(id => getUserName(id)).join(' ↔ ') }
function getMessageCount(convId) { return messageCounts.value[convId] || 0 }
function getLastMsgTime(convId) { return lastMsgTimes.value[convId] || '' }

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('ca-ES', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function highlightKeyword(text, keyword) {
  if (!text || !keyword) return text
  const regex = new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi')
  return text.replace(regex, '<mark style="background:rgba(239,68,68,0.2);padding:0 2px;border-radius:2px;">$1</mark>')
}

async function markReviewed(alertId) {
  try {
    await db.reviewAlert(alertId, authStore.userId)
    auditLog(authStore.userId, 'REVIEW_CHAT_ALERT', 'chat', alertId, 'Alerta forense revisada')
    await fetchData()
  } catch (e) { console.error(e) }
}

async function openConvViewer(conv) {
  viewingConv.value = conv
  try {
    viewingMessages.value = await db.getChatMessages(conv.id)
    showConvViewer.value = true
  } catch (e) { console.error(e) }
}

async function viewAlertContext(alert) {
  const convId = alert.message?.conversation_id
  const conv = allConversations.value.find(c => c.id === convId)
  if (conv) await openConvViewer(conv)
}

async function doForensicSearch() {
  if (!forensicSearch.value.trim()) return
  try {
    let results = await db.searchMessages(forensicSearch.value)
    if (searchFilterUser.value) results = results.filter(m => m.sender_id === searchFilterUser.value)
    searchResults.value = results.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).slice(0, 100)
    searchDone.value = true
    auditLog(authStore.userId, 'FORENSIC_SEARCH', 'chat', null, `Cerca forense: "${forensicSearch.value}" (${searchResults.value.length} resultats)`)
  } catch (e) { console.error(e) }
}

async function ackGeoAlert(id) {
  try {
    await db.acknowledgeGeoAlert(id, 'admin')
    auditLog(authStore.userId, 'ACK_GEO_ALERT', 'geolocation', id, 'Alerta geo-zona revisada')
    await fetchData()
  } catch (e) { console.error(e) }
}

function formatTime(ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleTimeString('ca-ES', { hour: '2-digit', minute: '2-digit' })
}

// onMounted fetchData handles this

function addKeyword() {
  if (newKeyword.value.trim() && !editKeywords.value.includes(newKeyword.value.trim())) {
    editKeywords.value.push(newKeyword.value.trim().toLowerCase())
    newKeyword.value = ''
  }
}

async function saveSettings() {
  try {
    await db.saveChatSettings({ 
      ...settings.value, 
      forensic_keywords: [...editKeywords.value], 
      require_policy_acceptance: editRequirePolicy.value 
    })
    auditLog(authStore.userId, 'UPDATE_CHAT_SETTINGS', 'chat', null, `Paraules clau: ${editKeywords.value.join(', ')}`)
    await fetchData()
    showSettingsModal.value = false
  } catch (e) { console.error(e) }
}
</script>

<style scoped>
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}
</style>
