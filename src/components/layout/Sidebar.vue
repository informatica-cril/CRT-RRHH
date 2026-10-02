<template>
  <aside class="sidebar">
    <div class="sidebar-logo" style="padding:16px 12px;">
      <img src="/assets/crt-logo-white.png" alt="CRT - Centre de Rehabilitació Terapèutica" style="width:150px;height:auto;border-radius:0;" />
    </div>

    <!-- Menú agrupat per tasques. Les condicions de rol de cada entrada són les de sempre; una secció
         només surt si el rol en pot veure alguna entrada. -->
    <div class="sidebar-section-title">Dia a dia</div>
    <ul class="sidebar-nav">
      <li>
        <router-link to="/" :class="{ active: $route.name === 'dashboard' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
          <span>{{ t('dashboard') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isStaff">
        <router-link to="/safata" :class="{ active: $route.name === 'safata' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
          <span>📥 Safata</span>
          <span v-if="pendingCount > 0" class="nav-badge">{{ pendingCount }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/work-logs" :class="{ active: $route.name === 'work-logs' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
          <span>{{ t('work_logs') }}</span>
          <span v-if="pendingCount > 0" class="nav-badge">{{ pendingCount }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isStaff">
        <router-link to="/hours-control" :class="{ active: $route.name === 'hours-control' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Control d'hores</span>
        </router-link>
      </li>
      <li>
        <router-link to="/absences" :class="{ active: $route.name === 'absences' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <span>{{ t('absences') }}</span>
        </router-link>
      </li>
      <li>
        <router-link to="/excedencies" :class="{ active: $route.name === 'excedencies' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="12" y1="14" x2="12" y2="18"/></svg>
          <span>{{ t('sabbatical') }}</span>
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
    </ul>

    <div class="sidebar-section-title" v-if="authStore.canManageStaff || esRepresentant">Persones</div>
    <ul class="sidebar-nav" v-if="authStore.canManageStaff || esRepresentant">
      <li v-if="authStore.canManageStaff">
        <router-link to="/employees" :class="{ active: $route.name === 'employees' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>{{ t('employees') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff && ['admin','hr'].includes(authStore.user?.role)">
        <router-link to="/disponibilitat" :class="{ active: $route.name === 'disponibilitat' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
          <span>Disponibilitat</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/payrolls-admin" :class="{ active: $route.name === 'payrolls-admin' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
          <span>{{ t('payrolls') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/documents" :class="{ active: $route.name === 'documents' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>{{ t('documentation') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/comite" :class="{ active: $route.name === 'comite' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><path d="M16 3.5l2 2"/></svg>
          <span>Comitè d'empresa</span>
        </router-link>
      </li>
      <li v-if="esRepresentant">
        <router-link to="/worker/comite" :class="{ active: $route.name === 'worker-comite' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/><path d="M16 3.5l2 2"/></svg>
          <span>Les meves hores de comitè</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/rlt" :class="{ active: $route.name === 'rlt' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span>Representació legal</span>
        </router-link>
      </li>
    </ul>

    <div class="sidebar-section-title" v-if="authStore.canManageStaff">Expedient i compliment</div>
    <ul class="sidebar-nav" v-if="authStore.canManageStaff">
      <li v-if="authStore.canManageStaff">
        <a href="#" @click.prevent="expedientObert = !expedientObert" :class="{ active: ['expedient','disciplinary'].includes($route.name) && !expedientObert }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <span>Expedient</span>
          <svg class="nav-icon" style="width:14px;height:14px;margin-left:auto;transition:transform .2s;" :style="expedientObert ? 'transform:rotate(90deg)' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      </li>
      <li v-if="authStore.canManageStaff && expedientObert">
        <router-link to="/expedient" :class="{ active: $route.name === 'expedient' }" style="padding-left:34px;">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>Evidències</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff && expedientObert">
        <router-link to="/disciplinary" :class="{ active: $route.name === 'disciplinary' }" style="padding-left:34px;">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M5 7l7-4 7 4"/><path d="M3 11l2-4 2 4a2.5 2.5 0 0 1-4 0z"/><path d="M17 11l2-4 2 4a2.5 2.5 0 0 1-4 0z"/><path d="M8 21h8"/></svg>
          <span>Procediments</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/conciliacio" :class="{ active: $route.name === 'conciliacio' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 7h8M8 12h8M8 17h5"/><path d="M4 5v14"/><path d="M20 5v14"/></svg>
          <span>Conciliació</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/rendiment" :class="{ active: $route.name === 'rendiment' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
          <span>Rendiment</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/compliance" :class="{ active: $route.name === 'compliance' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg>
          <span>Compliment</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/comunicacio-certificada" :class="{ active: $route.name === 'certified-mail' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/><path d="M16 15l2 2 4-4"/></svg>
          <span>Comunicació certificada</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/millores" :class="{ active: $route.name === 'millores' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6"/><path d="M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2z"/></svg>
          <span>Millores</span>
        </router-link>
      </li>
    </ul>

    <div class="sidebar-section-title" v-if="authStore.canManageStaff">Configuració</div>
    <ul class="sidebar-nav" v-if="authStore.canManageStaff">
      <li v-if="authStore.canManageStaff">
        <router-link to="/permisos-config" :class="{ active: $route.name === 'permisos-config' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          <span>{{ t('permissions_config') }}</span>
        </router-link>
      </li>
      <li v-if="authStore.isAdmin">
        <router-link to="/break-settings" :class="{ active: $route.name === 'break-settings' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Pausa Obligatòria</span>
        </router-link>
      </li>
      <li v-if="authStore.user?.role === 'admin'">
        <router-link to="/seguretat/segon-factor" :class="{ active: $route.name === 'segon-factor' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
          <span>Segon factor</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff">
        <router-link to="/portal/accessos" :class="{ active: $route.name === 'portal-accessos' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Accessos a apps</span>
        </router-link>
      </li>
      <li v-if="authStore.canManageStaff && authStore.user?.role !== 'hr'">
        <router-link to="/integrity" :class="{ active: $route.name === 'integrity' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <span>Integritat</span>
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
      <li v-if="teApps">
        <router-link to="/portal" :class="{ active: $route.name === 'portal' }">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
          <span>Les meves apps</span>
        </router-link>
      </li>
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
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import { useWorkLogStore } from '../../stores/workLog'
import { db } from '../../services/db'
import api from '../../services/apiClient'
import { i18n } from '../../i18n'

const authStore = useAuthStore()
const router = useRouter()
const workLogStore = useWorkLogStore()
const t = (key) => i18n.t(key)

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

const pendingCount = ref(0)
const esRepresentant = ref(false)
const teApps = ref(false)
const unreadCount = ref(0)
const expedientObert = ref(['expedient', 'disciplinary'].includes(router.currentRoute.value.name))

async function fetchCounts() {
  if (!authStore.isStaff) return
  try {
    // Recompte al servidor (només COUNTs): descarregar els fitxatges per comptar-los es quedava curt
    // amb l'històric (només en venien 500) i a més pesava cada minut.
    const r = await api.get('/v1/safata/resum')
    pendingCount.value = (r?.cues || []).find(c => c.clau === 'fitxatges')?.n || 0
    // unreadCount.value = await db.getTotalUnreadCount(authStore.userId)
  } catch (e) { console.error(e) }
}

onMounted(() => {
  fetchCounts()
  // Personal de gestió que també és representant: enllaç a les seves pròpies hores.
  api.get('/v1/comite/me').then(r => { esRepresentant.value = !!r?.membre }).catch(() => {})
  // «Les meves apps» només té sentit si n'hi ha alguna concedida (a un compte d'administració, cap).
  api.get('/v1/portal/apps').then(r => { teApps.value = (r?.apps || []).length > 0 }).catch(() => {})
  const interval = setInterval(fetchCounts, 60000)
  onUnmounted(() => clearInterval(interval))
})
</script>
