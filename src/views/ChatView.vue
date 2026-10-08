<template>
  <div class="chat-root">
    <!-- Policy Modal -->
    <div v-if="showPolicyModal" class="modal-overlay" style="z-index:99999;">
      <div class="modal" style="max-width:650px;max-height:85vh;display:flex;flex-direction:column;">
        <div class="modal-header" style="background:linear-gradient(135deg,rgba(239,68,68,0.08),rgba(147,51,234,0.06));border-radius:12px 12px 0 0;">
          <div>
            <span class="badge badge-warning" style="font-size:0.75rem;">⚠️ OBLIGATORI</span>
            <h3 class="modal-title">Política d'Ús del Xat Corporatiu</h3>
          </div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:20px;font-size:0.88rem;line-height:1.8;white-space:pre-wrap;">{{ chatSettings.chat_policy_text }}</div>
        <div style="padding:16px;">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:14px;">
            <input type="checkbox" v-model="policyAccepted" style="width:18px;height:18px;margin-top:2px;flex-shrink:0;" />
            <span style="font-size:0.85rem;line-height:1.5;"><strong>Declaro</strong> que he llegit i accepto la política d'ús del xat corporatiu.</span>
          </label>
          <button class="btn btn-primary" style="width:100%;padding:12px;border-radius:14px;" @click="confirmPolicy" :disabled="!policyAccepted">
            🚀 Acceptar i accedir al xat
          </button>
        </div>
      </div>
    </div>

    <!-- Geo Zone Alert Banner -->
    <div v-if="geoAlerts.length" class="geo-alert-banner">
      <div v-for="alert in geoAlerts" :key="alert.id" class="geo-alert-item">
        <div class="geo-alert-icon">📍</div>
        <div class="geo-alert-content">
          <strong>Fora de zona detectat!</strong>
          <span>{{ formatDate(alert.created_at) }} — {{ alert.minutes_outside }} min fora de zona. Temps descomptat del registre.</span>
        </div>
        <button class="geo-alert-dismiss" @click="dismissGeoAlert(alert.id)">✓ Entès</button>
      </div>
    </div>

    <!-- Left: Conversation List -->
    <div class="chat-sidebar" :class="{ hidden: isMobile && activeConversation }">
      <!-- Header with status -->
      <div class="chat-sidebar-header">
        <div style="display:flex;align-items:center;gap:10px;">
          <div class="my-avatar-ring" :class="myStatus">
            <div class="header-avatar" style="width:36px;height:36px;font-size:0.75rem;">{{ myInitials }}</div>
          </div>
          <div style="flex:1;">
            <div style="font-weight:700;font-size:0.95rem;">💬 Xat CRT</div>
            <div class="status-selector">
              <button v-for="s in statuses" :key="s.key" @click="setMyStatus(s.key)"
                :class="['status-btn', { active: myStatus === s.key }]"
                :title="s.label">
                <span class="status-dot-mini" :class="s.key"></span>
                <span class="status-label">{{ s.label }}</span>
              </button>
            </div>
          </div>
          <button class="chat-action-btn" @click="showNewConvModal = true" title="Nova conversa">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>
        <div class="chat-search-box">
          <span class="chat-search-icon">🔍</span>
          <input v-model="searchQuery" placeholder="Cercar converses..." />
        </div>
      </div>

      <!-- Conversation list -->
      <div class="chat-conv-list">
        <div v-for="conv in filteredConversations" :key="conv?.id" 
          @click="conv && openConversation(conv)"
          class="chat-conv-item" :class="{ active: activeConversation?.id === conv?.id }">
          <div class="conv-avatar-wrap">
            <div class="conv-avatar" :style="getAvatarGradient(conv)">{{ getConvEmoji(conv) }}</div>
            <span v-if="conv?.type === 'dm'" class="conv-status-dot" :class="getDmStatus(conv)"></span>
          </div>
          <div class="conv-info">
            <div class="conv-name">{{ getConvName(conv) }}</div>
            <div class="conv-preview">{{ getLastMsgPreview(conv.id) }}</div>
          </div>
          <div class="conv-meta">
            <span class="conv-time">{{ formatTime(getLastMsgTime(conv.id)) }}</span>
            <span v-if="getUnread(conv.id)" class="conv-badge bounce">{{ getUnread(conv.id) }}</span>
          </div>
        </div>
        <div v-if="!filteredConversations.length" class="chat-empty-list">
          <div style="font-size:2.5rem;margin-bottom:8px;">🦗</div>
          <div>Cap conversa trobada</div>
        </div>
      </div>
    </div>

    <!-- Right: Message Area -->
    <div v-if="activeConversation" class="chat-main">
      <!-- Conversation header -->
      <div class="chat-main-header">
        <button v-if="isMobile" class="chat-back-btn" @click="activeConversation = null">←</button>
        <div class="conv-avatar" :style="getAvatarGradient(activeConversation)" style="width:38px;height:38px;font-size:1.2rem;">{{ getConvEmoji(activeConversation) }}</div>
        <div style="flex:1;">
          <div style="font-weight:700;font-size:1rem;">{{ getConvName(activeConversation) }}</div>
          <div class="chat-header-meta">
            <span v-if="activeConversation.type === 'dm'" class="status-dot-mini" :class="getDmStatus(activeConversation)"></span>
            <span>{{ getHeaderSubtitle() }}</span>
          </div>
        </div>
        <div class="online-avatars">
          <div v-for="memberId in (activeConversation?.members || []).slice(0, 5)" :key="memberId" 
            class="mini-avatar" :class="getUserPresenceClass(memberId)"
            :title="getUserName(memberId) + ' — ' + getPresenceLabel(memberId)">
            {{ getUserInitials(memberId) }}
          </div>
        </div>
      </div>

      <!-- Messages -->
      <div ref="messagesContainer" class="chat-messages">
        <!-- Date separators and messages -->
        <template v-for="(msg, i) in activeMessages" :key="msg.id">
          <div v-if="shouldShowDateSep(msg, i)" class="chat-date-sep">
            <span>{{ formatDateSep(msg.created_at) }}</span>
          </div>
          <div class="chat-msg" :class="{ mine: msg.sender_id === authStore.userId, system: msg.type === 'system' }">
            <div v-if="msg.type === 'system'" class="system-msg">
              <span>{{ msg.content }}</span>
            </div>
            <template v-else>
              <div v-if="msg.sender_id !== authStore.userId" class="msg-avatar" :style="getAvatarGradientById(msg.sender_id)">
                {{ getUserInitials(msg.sender_id) }}
              </div>
              <div class="msg-bubble-wrap">
                <div v-if="msg.sender_id !== authStore.userId" class="msg-sender">{{ getUserName(msg.sender_id) }}</div>
                <div class="msg-bubble" :class="{ mine: msg.sender_id === authStore.userId }">
                  <div v-if="msg.type === 'image' && msg.file_data" class="msg-image">
                    <img :src="msg.file_data" :alt="msg.file_name" />
                  </div>
                  <div v-else-if="msg.type === 'file'" class="msg-file">
                    <span class="msg-file-icon">📎</span>
                    <span>{{ msg.file_name }}</span>
                  </div>
                  <span class="msg-text">{{ msg.content }}</span>
                  <span class="msg-time">{{ formatMsgTime(msg.created_at) }}</span>
                </div>
              </div>
            </template>
          </div>
        </template>
        <div v-if="!activeMessages.length" class="chat-empty-msgs">
          <div class="empty-wave">👋</div>
          <div style="font-weight:600;font-size:1.1rem;">Hola!</div>
          <div class="text-muted">Comença la conversa enviant un missatge</div>
        </div>
      </div>

      <!-- Emoji quick-bar + Input -->
      <div class="chat-input-area">
        <div class="emoji-bar">
          <button v-for="emoji in quickEmojis" :key="emoji" @click="addEmoji(emoji)" class="emoji-btn">{{ emoji }}</button>
        </div>
        <div class="chat-input-row">
          <button class="chat-attach-btn" @click="$refs.fileInput.click()" title="Adjuntar fitxer">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
          </button>
          <input ref="fileInput" type="file" @change="onFileAttach" style="display:none;" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx" />
          <div class="chat-input-wrap" :class="{ 'has-file': pendingFile }">
            <div v-if="pendingFile" class="pending-file-tag">
              <span>📎 {{ pendingFile.name }}</span>
              <button @click="pendingFile = null">✕</button>
            </div>
            <FrasesRapides class="chat-frases" clau="xat" v-model="newMessage" />
            <textarea 
              v-model="newMessage" 
              @keydown.enter.exact.prevent="sendMessage"
              placeholder="Escriu un missatge... 💬"
              rows="1"
            ></textarea>
          </div>
          <button class="chat-send-btn" @click="sendMessage" :disabled="!newMessage.trim() && !pendingFile" :class="{ ready: newMessage.trim() || pendingFile }">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- No conversation selected (desktop) -->
    <div v-else-if="!isMobile" class="chat-empty-state">
      <div class="empty-illustration">
        <div class="float-emoji e1">💬</div>
        <div class="float-emoji e2">🎉</div>
        <div class="float-emoji e3">🤝</div>
        <div class="main-emoji">🗨️</div>
      </div>
      <div style="font-size:1.2rem;font-weight:700;margin-top:16px;">Seleccioneu una conversa</div>
      <div class="text-muted" style="font-size:0.9rem;">o creeu-ne una de nova amb el botó ➕</div>
    </div>

    <!-- New Conversation Modal -->
    <div v-if="showNewConvModal" class="modal-overlay" @click.self="showNewConvModal = false">
      <div class="modal" style="max-width:450px;">
        <div class="modal-header"><h3 class="modal-title">✨ Nova Conversa</h3><button class="icon-btn" @click="showNewConvModal = false">✕</button></div>
        <div class="form-group">
          <label class="form-label">Tipus</label>
          <select class="form-select" v-model="newConv.type">
            <option value="dm">💌 Missatge Directe</option>
            <option value="group">👥 Grup</option>
          </select>
        </div>
        <div v-if="newConv.type === 'dm'" class="form-group">
          <label class="form-label">Destinatari</label>
          <SelectorPersona v-model="newConv.targetUser" :persones="otherUsers" placeholder="Tria la persona…" :detall="u => getPresenceEmoji(u.id)" style="width:100%;" />
        </div>
        <div v-if="newConv.type === 'group'" class="form-group">
          <label class="form-label">Nom del grup</label>
          <input class="form-input" v-model="newConv.name" placeholder="p.ex. Equip Zona Nord 🏥" />
        </div>
        <div v-if="newConv.type === 'group'" class="form-group">
          <label class="form-label">Membres</label>
          <div style="max-height:150px;overflow-y:auto;border:1px solid var(--color-border-light);border-radius:12px;padding:8px;">
            <label v-for="u in otherUsers" :key="u.id" style="display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;font-size:0.85rem;border-radius:8px;" class="member-pick">
              <input type="checkbox" :value="u.id" v-model="newConv.members" style="width:16px;height:16px;" />
              <span class="status-dot-mini" :class="getUserPresenceClass(u.id)"></span>
              {{ u.name }}
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline" @click="showNewConvModal = false">Cancel·lar</button>
          <button class="btn btn-primary" style="border-radius:12px;" @click="createConversation" :disabled="newConv.type === 'dm' ? !newConv.targetUser : (!newConv.name || newConv.members.length === 0)">🚀 Crear</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import FrasesRapides from '../components/FrasesRapides.vue'
import SelectorPersona from '../components/SelectorPersona.vue'
import { ref, computed, reactive, onMounted, onUnmounted, nextTick } from 'vue'
import { db } from '../services/db'
import { useAuthStore } from '../stores/auth'
import { auditLog } from '../services/audit'

const authStore = useAuthStore()
const isMobile = ref(window.innerWidth <= 768)
window.addEventListener('resize', () => isMobile.value = window.innerWidth <= 768)

const statuses = [
  { key: 'online', label: 'Connectat' },
  { key: 'paused', label: 'En pausa' },
  { key: 'offline', label: 'No disponible' }
]

const chatSettings = ref({})
const showPolicyModal = ref(false)
const policyAccepted = ref(false)
const showNewConvModal = ref(false)
const searchQuery = ref('')
const activeConversation = ref(null)
const activeMessages = ref([])
const newMessage = ref('')
const pendingFile = ref(null)
const messagesContainer = ref(null)
const myStatus = ref('online')
const conversations = ref([])
const users = ref([])
const presenceData = ref({}) // userId -> presence
const unreadCounts = ref({}) // convId -> count
const lastMessages = ref({}) // convId -> lastMsg
const newConv = reactive({ type: 'dm', targetUser: '', name: '', members: [] })

const otherUsers = computed(() => users.value.filter(u => u.id !== authStore.userId && u.active))
const myInitials = computed(() => authStore.userName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase())

// Geo alerts
const geoAlerts = ref([])
async function fetchGeoAlerts() {
  geoAlerts.value = await db.getUnacknowledgedGeoAlerts(authStore.userId, 'worker')
}
async function dismissGeoAlert(id) { 
  await db.acknowledgeGeoAlert(id, 'worker')
  await fetchGeoAlerts()
}
// fetchGeoAlerts called in fetchData or refresh

// ── Presence ──
async function fetchData() {
  try {
    chatSettings.value = await db.getChatSettings().catch(() => ({}))
    users.value = await db.getUsers().catch(() => [])
    const rawConversations = await db.getChatConversations(authStore.userId).catch(() => [])
    // Filter out any invalid conversations (null, undefined, or missing type)
    conversations.value = (rawConversations || []).filter(c => c && c.type && c.members)
    
    // Fetch initial meta
    for (const c of conversations.value) {
      try {
        unreadCounts.value[c.id] = await db.getUnreadCount(c.id, authStore.userId)
      } catch { unreadCounts.value[c.id] = 0 }
      try {
        lastMessages.value[c.id] = await db.getLastMessage(c.id)
      } catch { lastMessages.value[c.id] = null }
    }
    
    // Una sola crida per a tota la plantilla (abans, una per persona i refresc).
    try {
      const viu = await db.getAllPresence()
      presenceData.value = Object.fromEntries(
        Object.entries(viu || {}).map(([id, on]) => [id, { status: on ? 'online' : 'offline' }])
      )
    } catch { presenceData.value = {} }
    
    try {
      if (!await db.hasChatPolicyAccepted(authStore.userId)) {
        showPolicyModal.value = true
      }
    } catch { /* ignore policy check failure */ }
  } catch (e) {
    console.error('[Chat] fetchData failed:', e)
  }
}

let heartbeatInterval = null
let refreshInterval = null

onMounted(async () => {
  await fetchData()
  await db.setUserPresence(authStore.userId, 'online')
  heartbeatInterval = setInterval(async () => {
    await db.heartbeat(authStore.userId)
  }, 30000)
  
  refreshInterval = setInterval(async () => {
    try {
      // Background refresh
      const rawConversations = await db.getChatConversations(authStore.userId).catch(() => [])
      conversations.value = (rawConversations || []).filter(c => c && c.type && c.members)
      for (const c of conversations.value) {
        try { unreadCounts.value[c.id] = await db.getUnreadCount(c.id, authStore.userId) } catch {}
        try { lastMessages.value[c.id] = await db.getLastMessage(c.id) } catch {}
      }
      try {
        const viu = await db.getAllPresence()
        presenceData.value = Object.fromEntries(
          Object.entries(viu || {}).map(([id, on]) => [id, { status: on ? 'online' : 'offline' }])
        )
      } catch { /* la presència és un adorn: el xat ha de seguir servint */ }
      if (activeConversation.value) {
        await loadMessages()
      }
    } catch (e) {
      console.warn('[Chat] Background refresh failed:', e)
    }
  }, 8000)
})

onUnmounted(async () => {
  await db.setUserPresence(authStore.userId, 'offline')
  if (heartbeatInterval) clearInterval(heartbeatInterval)
  if (refreshInterval) clearInterval(refreshInterval)
})

async function setMyStatus(status) {
  myStatus.value = status
  await db.setUserPresence(authStore.userId, status)
  presenceData.value[authStore.userId] = { status }
}

function getUserPresenceClass(userId) {
  return presenceData.value[userId]?.status || 'offline'
}
function getPresenceLabel(userId) {
  const s = presenceData.value[userId]?.status
  return s === 'online' ? 'Connectat' : s === 'paused' ? 'En pausa' : 'No disponible'
}
function getPresenceEmoji(userId) {
  const s = presenceData.value[userId]?.status
  return s === 'online' ? '🟢' : s === 'paused' ? '🟡' : '⚫'
}
function getDmStatus(conv) {
  if (!conv?.members) return 'offline'
  const otherId = conv.members.find(id => id !== authStore.userId)
  return getUserPresenceClass(otherId)
}

async function confirmPolicy() {
  await db.acceptChatPolicy(authStore.userId)
  auditLog(authStore.userId, 'ACCEPT_CHAT_POLICY', 'chat', null, 'Política del xat acceptada')
  showPolicyModal.value = false
}

// conversations ref defined above
const filteredConversations = computed(() => {
  if (!searchQuery.value) return conversations.value
  const q = searchQuery.value.toLowerCase()
  return conversations.value.filter(c => getConvName(c).toLowerCase().includes(q))
})

function getConvName(conv) {
  if (!conv) return ''
  if (conv.type === 'dm') {
    const otherId = (conv.members || []).find(id => id !== authStore.userId)
    return users.value.find(u => u.id === otherId)?.name || 'Usuari'
  }
  return conv.name || 'Grup'
}
function getConvEmoji(conv) {
  if (!conv) return '💬'
  if (conv.type === 'dm') {
    const otherId = (conv.members || []).find(id => id !== authStore.userId)
    return getUserInitials(otherId)
  }
  if (conv.name?.includes('General')) return '📢'
  if (conv.name?.includes('Fisio')) return '🏥'
  if (conv.name?.includes('Logo')) return '🗣️'
  return '👥'
}
const gradients = [
  'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
  'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
  'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
  'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
  'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
  'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)'
]
function getAvatarGradient(conv) { return `background:${gradients[conv.id % gradients.length]};` }
function getAvatarGradientById(userId) { return `background:${gradients[userId % gradients.length]};` }
function getUserInitials(id) { 
  const u = users.value.find(u => u.id === id)
  return u ? u.name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase() : '??' 
}
function getUserName(id) { 
  return users.value.find(u => u.id === id)?.name || 'Desconegut' 
}

function getUnread(convId) { return unreadCounts.value[convId] || 0 }
function getLastMsgTime(convId) { return lastMessages.value[convId]?.created_at }
function getLastMsgPreview(convId) {
  const msg = lastMessages.value[convId]
  if (!msg) return '✨ Cap missatge'
  const sender = msg.sender_id === authStore.userId ? 'Tu' : getUserName(msg.sender_id).split(' ')[0]
  const text = msg.type === 'image' ? '📷 Imatge' : msg.type === 'file' ? `📎 ${msg.file_name}` : msg.content
  return `${sender}: ${text?.substring(0, 35) || ''}${text?.length > 35 ? '...' : ''}`
}

function getHeaderSubtitle() {
  if (!activeConversation.value) return ''
  if (activeConversation.value.type === 'dm') {
    const otherId = (activeConversation.value.members || []).find(id => id !== authStore.userId)
    return getPresenceLabel(otherId)
  }
  const members = activeConversation.value.members || []
  const online = members.filter(m => getUserPresenceClass(m) === 'online').length
  return `${members.length} membres · ${online} en línia`
}

function formatTime(ts) {
  if (!ts) return ''
  const d = new Date(ts); const diff = Date.now() - d
  if (diff < 86400000) return d.toLocaleTimeString('ca', { hour: '2-digit', minute: '2-digit' })
  if (diff < 604800000) return d.toLocaleDateString('ca', { weekday: 'short' })
  return d.toLocaleDateString('ca', { day: '2-digit', month: '2-digit' })
}
function formatMsgTime(ts) { return new Date(ts).toLocaleTimeString('ca', { hour: '2-digit', minute: '2-digit' }) }
function formatDate(ts) { return new Date(ts).toLocaleString('ca', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) }
function formatDateSep(ts) {
  const d = new Date(ts); const today = new Date()
  if (d.toDateString() === today.toDateString()) return '— Avui —'
  const yesterday = new Date(today); yesterday.setDate(yesterday.getDate() - 1)
  if (d.toDateString() === yesterday.toDateString()) return '— Ahir —'
  return d.toLocaleDateString('ca', { weekday: 'long', day: 'numeric', month: 'long' })
}
function shouldShowDateSep(msg, i) {
  if (i === 0) return true
  const prev = activeMessages.value[i - 1]
  return new Date(msg.created_at).toDateString() !== new Date(prev.created_at).toDateString()
}

async function openConversation(conv) {
  activeConversation.value = conv
  newMessage.value = ''
  pendingFile.value = null
  await loadMessages()
  await db.markMessagesRead(conv.id, authStore.userId)
  unreadCounts.value[conv.id] = 0
}

async function loadMessages() {
  if (!activeConversation.value) return
  activeMessages.value = await db.getChatMessages(activeConversation.value.id)
  nextTick(() => { if (messagesContainer.value) messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight })
}

async function sendMessage() {
  const content = newMessage.value.trim()
  if (!content && !pendingFile.value) return
  try {
    await db.addChatMessage({
      conversation_id: activeConversation.value.id, sender_id: authStore.userId, content,
      type: pendingFile.value?.type || 'text', file_data: pendingFile.value?.data || null, file_name: pendingFile.value?.name || null
    })
    newMessage.value = ''; pendingFile.value = null
    await loadMessages()
    lastMessages.value[activeConversation.value.id] = activeMessages.value[activeMessages.value.length - 1]
  } catch (e) { console.error(e) }
}

function addEmoji(emoji) { newMessage.value += emoji }

function onFileAttach(e) {
  const file = e.target.files?.[0]; if (!file) return
  if (file.size > 10 * 1024 * 1024) { alert('Fitxer massa gran (màx. 10MB)'); return }
  const reader = new FileReader()
  reader.onload = () => {
    pendingFile.value = { name: file.name, data: reader.result, type: file.type.startsWith('image/') ? 'image' : 'file' }
  }
  reader.readAsDataURL(file)
}

async function createConversation() {
  if (newConv.type === 'dm') {
    try {
      const conv = await db.getOrCreateDm(authStore.userId, newConv.targetUser)
      await openConversation(conv)
      showNewConvModal.value = false
    } catch (e) {
      console.error(e)
      alert('Error al crear la conversa directa. Torneu-ho a provar.')
    }
  } else {
    try {
      const conv = await db.addChatConversation({ 
        type: 'group', 
        name: newConv.name, 
        members: [authStore.userId, ...newConv.members], 
        created_by: authStore.userId, 
        pinned: false 
      })
      auditLog(authStore.userId, 'CREATE_CHAT_GROUP', 'chat', conv.id, `Grup creat: ${newConv.name}`)
      await openConversation(conv)
      showNewConvModal.value = false
    } catch (e) { 
      console.error(e)
      alert('Error en crear el grup. Verifiqueu que el nom no sigui buit i que hi hagi membres seleccionats.')
    }
  }
  Object.assign(newConv, { type: 'dm', targetUser: '', name: '', members: [] })
  conversations.value = await db.getChatConversations(authStore.userId)
}

// Auto-refresh handled in onMounted
</script>

<style scoped>
.chat-root { display: flex; height: calc(100vh - 70px); overflow: hidden; background: var(--color-bg); }

/* ── GEO ALERT BANNER ── */
.geo-alert-banner { position: absolute; top: 0; left: 0; right: 0; z-index: 100; }
.geo-alert-item { display: flex; align-items: center; gap: 12px; padding: 10px 20px; background: linear-gradient(90deg, #ff6b6b, #ee5a24); color: #fff; font-size: 0.85rem; animation: slideDown 0.4s ease; }
.geo-alert-icon { font-size: 1.5rem; animation: shake 0.5s ease infinite alternate; }
.geo-alert-content { flex: 1; }
.geo-alert-content strong { display: block; margin-bottom: 2px; }
.geo-alert-dismiss { background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 4px 12px; border-radius: 8px; cursor: pointer; font-size: 0.8rem; font-weight: 600; }
.geo-alert-dismiss:hover { background: rgba(255,255,255,0.35); }

/* ── SIDEBAR ── */
.chat-sidebar { width: 320px; border-right: 1px solid var(--color-border-light); display: flex; flex-direction: column; flex-shrink: 0; background: var(--color-bg-secondary); }
.chat-sidebar.hidden { display: none; }
@media (max-width: 768px) { .chat-sidebar { width: 100%; } }
.chat-sidebar-header { padding: 16px; border-bottom: 1px solid var(--color-border-light); }
.my-avatar-ring { padding: 2px; border-radius: 50%; display: inline-flex; }
.my-avatar-ring.online { box-shadow: 0 0 0 2px #22c55e; }
.my-avatar-ring.paused { box-shadow: 0 0 0 2px #f59e0b; }
.my-avatar-ring.offline { box-shadow: 0 0 0 2px #94a3b8; }

.status-selector { display: flex; gap: 4px; margin-top: 4px; }
.status-btn { display: flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 10px; border: 1px solid transparent; background: none; cursor: pointer; font-size: 0.68rem; color: var(--color-text-secondary); transition: all 0.2s; }
.status-btn:hover { background: rgba(0,0,0,0.04); }
.status-btn.active { border-color: var(--color-border-light); background: var(--color-bg); font-weight: 600; }
.status-label { }

.status-dot-mini { width: 7px; height: 7px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.status-dot-mini.online { background: #22c55e; box-shadow: 0 0 4px #22c55e; }
.status-dot-mini.paused { background: #f59e0b; box-shadow: 0 0 4px #f59e0b; }
.status-dot-mini.offline { background: #94a3b8; }

.chat-action-btn { width: 36px; height: 36px; border-radius: 12px; border: none; background: var(--color-primary); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: transform 0.15s, box-shadow 0.2s; }
.chat-action-btn:hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }

.chat-search-box { display: flex; align-items: center; gap: 8px; background: var(--color-bg); border-radius: 12px; padding: 6px 12px; margin-top: 12px; border: 1px solid var(--color-border-light); }
.chat-search-box input { border: none; background: none; outline: none; font-size: 0.85rem; width: 100%; color: var(--color-text); }
.chat-search-icon { font-size: 0.9rem; }

/* ── CONVERSATION LIST ── */
.chat-conv-list { flex: 1; overflow-y: auto; }
.chat-conv-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; transition: all 0.15s; border-left: 3px solid transparent; }
.chat-conv-item:hover { background: rgba(59,130,246,0.04); }
.chat-conv-item.active { background: rgba(59,130,246,0.08); border-left-color: var(--color-primary); }

.conv-avatar-wrap { position: relative; flex-shrink: 0; }
.conv-avatar { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff; font-weight: 700; }
.conv-status-dot { position: absolute; bottom: -1px; right: -1px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid var(--color-bg-secondary); }
.conv-status-dot.online { background: #22c55e; }
.conv-status-dot.paused { background: #f59e0b; }
.conv-status-dot.offline { background: #94a3b8; }

.conv-info { flex: 1; min-width: 0; }
.conv-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.conv-preview { font-size: 0.78rem; color: var(--color-text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.conv-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.conv-time { font-size: 0.68rem; color: var(--color-text-secondary); }
.conv-badge { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; font-size: 0.65rem; font-weight: 700; padding: 2px 7px; border-radius: 10px; min-width: 20px; text-align: center; }
.bounce { animation: bounce 2s infinite; }
@keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }

.chat-empty-list { padding: 30px; text-align: center; color: var(--color-text-secondary); font-size: 0.85rem; }

/* ── MAIN CHAT AREA ── */
.chat-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.chat-main-header { padding: 12px 16px; border-bottom: 1px solid var(--color-border-light); display: flex; align-items: center; gap: 12px; background: var(--color-bg); }
.chat-back-btn { background: none; border: 1px solid var(--color-border-light); border-radius: 8px; padding: 4px 10px; cursor: pointer; font-size: 1rem; }
.chat-header-meta { font-size: 0.78rem; color: var(--color-text-secondary); display: flex; align-items: center; gap: 5px; }

.online-avatars { display: flex; gap: -4px; }
.mini-avatar { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: 700; color: #fff; background: var(--color-text-secondary); margin-left: -6px; border: 2px solid var(--color-bg); }
.mini-avatar.online { background: #22c55e; }
.mini-avatar.paused { background: #f59e0b; }
.mini-avatar.offline { background: #94a3b8; }

/* ── MESSAGES ── */
.chat-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 4px; background: repeating-linear-gradient(180deg, transparent 0, transparent 40px, rgba(0,0,0,0.008) 40px, rgba(0,0,0,0.008) 80px); }
.chat-date-sep { text-align: center; padding: 12px 0 8px; }
.chat-date-sep span { background: var(--color-bg-secondary); padding: 4px 16px; border-radius: 12px; font-size: 0.72rem; color: var(--color-text-secondary); font-weight: 500; }

.chat-msg { display: flex; gap: 8px; align-items: flex-end; max-width: 80%; animation: fadeIn 0.2s ease; }
.chat-msg.mine { flex-direction: row-reverse; margin-left: auto; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

.msg-avatar { width: 30px; height: 30px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.65rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.msg-sender { font-size: 0.72rem; font-weight: 600; color: var(--color-text-secondary); margin-bottom: 2px; padding-left: 4px; }
.msg-bubble { padding: 10px 14px; border-radius: 18px 18px 18px 6px; background: var(--color-bg-secondary); font-size: 0.9rem; line-height: 1.5; word-break: break-word; position: relative; }
.msg-bubble.mine { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border-radius: 18px 18px 6px 18px; }
.msg-text { margin-right: 40px; }
.msg-time { position: absolute; bottom: 4px; right: 10px; font-size: 0.6rem; opacity: 0.6; }
.msg-image img { max-width: 100%; max-height: 280px; border-radius: 12px; cursor: pointer; display: block; margin-bottom: 4px; }
.msg-file { display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px; background: rgba(0,0,0,0.06); margin-bottom: 4px; font-size: 0.85rem; }
.system-msg { text-align: center; font-size: 0.78rem; color: var(--color-text-secondary); padding: 8px; font-style: italic; }

.chat-empty-msgs { text-align: center; padding: 60px 20px; margin: auto; }
.empty-wave { font-size: 3rem; animation: wave 1.5s ease infinite; display: inline-block; }
@keyframes wave { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(25deg); } 75% { transform: rotate(-15deg); } }

/* ── INPUT AREA ── */
.chat-input-area { border-top: 1px solid var(--color-border-light); background: var(--color-bg); }
.emoji-bar { display: flex; gap: 2px; padding: 6px 12px; overflow-x: auto; border-bottom: 1px solid var(--color-border-light); }
.emoji-btn { background: none; border: none; font-size: 1.15rem; cursor: pointer; padding: 2px 6px; border-radius: 8px; transition: all 0.15s; }
.emoji-btn:hover { background: rgba(0,0,0,0.06); transform: scale(1.25); }
.chat-input-row { display: flex; gap: 8px; padding: 10px 14px; align-items: flex-end; }
.chat-attach-btn { width: 38px; height: 38px; border-radius: 12px; border: 1px solid var(--color-border-light); background: var(--color-bg-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--color-text-secondary); transition: all 0.15s; flex-shrink: 0; }
.chat-attach-btn:hover { color: var(--color-primary); border-color: var(--color-primary); }

.chat-input-wrap { flex: 1; border: 1px solid var(--color-border-light); border-radius: 16px; overflow: hidden; transition: border-color 0.2s; }
.chat-input-wrap:focus-within { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
.chat-frases { padding: 8px 12px 0; margin: 0; }
.chat-input-wrap textarea { border: none; background: none; width: 100%; resize: none; padding: 10px 14px; font-size: 0.9rem; min-height: 38px; max-height: 100px; font-family: inherit; outline: none; color: var(--color-text); }
.pending-file-tag { display: flex; align-items: center; gap: 6px; padding: 4px 10px; background: rgba(59,130,246,0.08); font-size: 0.78rem; color: var(--color-primary); }
.pending-file-tag button { background: none; border: none; cursor: pointer; color: var(--color-danger); padding: 0 4px; }

.chat-send-btn { width: 42px; height: 42px; border-radius: 50%; border: none; background: var(--color-bg-secondary); color: var(--color-text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.25s; flex-shrink: 0; }
.chat-send-btn.ready { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; transform: scale(1.05); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
.chat-send-btn.ready:hover { transform: scale(1.12); }

/* ── EMPTY STATE ── */
.chat-empty-state { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 8px; color: var(--color-text-secondary); }
.empty-illustration { position: relative; width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; }
.main-emoji { font-size: 4rem; animation: pulse 2s ease infinite; }
@keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
.float-emoji { position: absolute; font-size: 1.5rem; animation: float 3s ease infinite; }
.e1 { top: 0; left: 0; animation-delay: 0s; }
.e2 { top: -10px; right: 0; animation-delay: 1s; }
.e3 { bottom: 0; left: 10px; animation-delay: 2s; }
@keyframes float { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-10px) rotate(10deg); } }

@keyframes slideDown { from { transform: translateY(-100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes shake { 0% { transform: translateX(0); } 25% { transform: translateX(-3px); } 75% { transform: translateX(3px); } 100% { transform: translateX(0); } }

.member-pick:hover { background: rgba(0,0,0,0.03); }
</style>
