<template>
  <aside class="sidebar">
    <div class="sidebar-logo" style="padding:16px 12px;">
      <img src="/assets/crt-logo-white.png" alt="CRT - Centre de Rehabilitació Terapèutica" style="width:150px;height:auto;border-radius:0;" />
    </div>

    <div class="sidebar-section-title">{{ t('menu') }}</div>
    <ul class="sidebar-nav">
      <li>
        <router-link to="/" :class="{ active: $route.name === 'dashboard' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          <span>{{ t('dashboard') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/work-logs" :class="{ active: $route.name === 'work-logs' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          <span>{{ t('work_logs') }}</span>
          <span v-if="pendingCount > 0" class="nav-badge">{{ pendingCount }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/employees" :class="{ active: $route.name === 'employees' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>{{ t('employees') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/absences" :class="{ active: $route.name === 'absences' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>{{ t('absences') }}</span>
          <span v-if="pendingAbsenceCount > 0" class="nav-badge">{{ pendingAbsenceCount }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/permisos-config" :class="{ active: $route.name === 'permisos-config' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>{{ t('permissions_config') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/excedencies" :class="{ active: $route.name === 'excedencies' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
          <span>{{ t('sabbatical') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/documents" :class="{ active: $route.name === 'documents' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>{{ t('documentation') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/auth-codes" :class="{ active: $route.name === 'auth-codes' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>{{ t('auth_codes') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/reports" :class="{ active: $route.name === 'reports' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          <span>{{ t('reports') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/payrolls-admin" :class="{ active: $route.name === 'payrolls-admin' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span>{{ t('payrolls') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/audit" :class="{ active: $route.name === 'audit' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>{{ t('audit') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/chat-admin" :class="{ active: $route.name === 'chat-admin' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span>{{ t('chat') }} (Supervisió)</span>
        </router-link>
      </li>
    </ul>

    <div class="sidebar-section-title">{{ t('general') }}</div>
    <ul class="sidebar-nav">
      <li>
        <router-link to="/settings" :class="{ active: $route.name === 'settings' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span>{{ t('settings') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/privacy" :class="{ active: $route.name === 'privacy' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>{{ t('privacy') }}</span>
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
import { computed, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '../../stores/notifications'
import { i18n } from '../../i18n'

const authStore = useAuthStore()
const router = useRouter()
const notifStore = useNotificationsStore()
const t = (key) => i18n.t(key)

const pendingCount = computed(() => notifStore.pendingWorkLogs)
const pendingAbsenceCount = computed(() => notifStore.pendingAbsences)

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

onMounted(() => notifStore.startPolling())
onUnmounted(() => notifStore.stopPolling())
</script>
