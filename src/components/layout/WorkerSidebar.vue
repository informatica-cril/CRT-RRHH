<template>
  <aside class="sidebar worker-sidebar">
    <div class="sidebar-logo" style="padding:16px 12px;">
      <img src="/assets/crt-logo-white.png" alt="CRT - Centre de Rehabilitació Terapèutica" style="width:150px;height:auto;border-radius:0;" />
    </div>

    <!-- Worker info -->
    <div style="padding:8px 16px 16px;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:10px;">
        <div class="header-avatar" style="width:36px;height:36px;font-size:0.8rem;background:var(--color-accent);">{{ initials }}</div>
        <div>
          <div style="color:#fff;font-weight:600;font-size:0.85rem;">{{ authStore.userName }}</div>
          <div style="color:rgba(255,255,255,0.5);font-size:0.7rem;">{{ authStore.userEmail }}</div>
        </div>
      </div>
    </div>

    <div class="sidebar-section-title">{{ t('worker_portal') }}</div>
    <ul class="sidebar-nav">
      <li>
        <router-link to="/portal" :class="{ active: $route.name === 'portal' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Les meves apps</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker" :class="{ active: $route.name === 'worker-dashboard' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>{{ t('time_tracking') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/safata" :class="{ active: $route.name === 'worker-safata' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
          <span>📥 Safata</span>
          <span v-if="safataPendingCount > 0" class="nav-badge">{{ safataPendingCount }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/calendar" :class="{ active: $route.name === 'worker-calendar' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>{{ t('calendar') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/absences" :class="{ active: $route.name === 'worker-absences' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          <span>{{ t('leave_absences') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/excedencies" :class="{ active: $route.name === 'worker-excedencies' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
          <span>{{ t('sabbatical') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/history" :class="{ active: $route.name === 'worker-history' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><path d="M4.93 4.93l4.24 4.24"/></svg>
          <span>{{ t('time_history') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/documents" :class="{ active: $route.name === 'worker-documents' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          <span>{{ t('documents') }}</span>
          <span v-if="pendingDocsCount > 0" class="nav-badge">{{ pendingDocsCount }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/payrolls" :class="{ active: $route.name === 'worker-payrolls' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span>{{ t('payrolls') }}</span>
          <span v-if="newPayrollsCount > 0" class="nav-badge" style="background:var(--color-success);">{{ newPayrollsCount }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/chat" :class="{ active: $route.name === 'worker-chat' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span>{{ t('chat') }}</span>
          <span v-if="unreadChatCount > 0" class="nav-badge">{{ unreadChatCount }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/rendiment" :class="{ active: $route.name === 'worker-rendiment' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          <span>El meu rendiment</span>
        </router-link>
      </li>
      <li>
        <router-link to="/worker/compliance" :class="{ active: $route.name === 'worker-compliance' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>
          <span>Informació</span>
        </router-link>
      </li>
    </ul>

    <div class="sidebar-section-title">{{ t('general') }}</div>
    <ul class="sidebar-nav">
      <li>
        <router-link to="/worker/settings" :class="{ active: $route.name === 'worker-settings' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>{{ t('configuration') }}</span>
        </router-link>
      </li>
      <li>
        <button @click="handleLogout()">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          <span>{{ t('logout') }}</span>
        </button>
      </li>
    </ul>

    <div style="margin-top: auto; padding: 12px 16px; font-size: 0.65rem; color: rgba(255,255,255,0.3); line-height: 1.4;">
      {{ t('rdl_notice') }}
    </div>
  </aside>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import { db } from '../../services/db'
import { i18n } from '../../i18n'

const authStore = useAuthStore()
const router = useRouter()
const t = (key) => i18n.t(key)

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

const initials = computed(() => authStore.userName.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase())

const pendingDocsCount = ref(0)
const newPayrollsCount = ref(0)
const unreadChatCount = ref(0)
const safataPendingCount = ref(0)

async function fetchCounts() {
  if (!authStore.userId) return
  try {
    const [docs, sigs, payrolls, unreadCount, alerts] = await Promise.all([
      db.getDocumentsForUser(authStore.userId),
      db.getDocSignaturesByUser(authStore.userId),
      db.getWorkerPayrolls(authStore.userId),
      db.getTotalUnreadCount(authStore.userId),
      db.getPendingAlerts(authStore.userId).catch(() => [])
    ])
    pendingDocsCount.value = docs.filter(d => d.requires_signature && !sigs.find(s => s.document_id === d.id && s.signed_at)).length
    newPayrollsCount.value = payrolls.filter(p => !p.viewed_at).length
    unreadChatCount.value = unreadCount
    // Coincide con lo que agrupa La meva safata: audiencia previa + rebuig de tram.
    safataPendingCount.value = (alerts || []).filter(a => ['audiencia', 'segment_rejected'].includes(a.type)).length
  } catch (e) {
    console.error('[WorkerSidebar] Error fetching counts:', e)
  }
}

onMounted(() => {
  fetchCounts()
  // Refresh counts every 30 seconds
  const interval = setInterval(fetchCounts, 30000)
  onUnmounted(() => clearInterval(interval))
})
</script>
