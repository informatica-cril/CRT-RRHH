import { createRouter, createWebHashHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
    meta: { guest: true }
  },
  // ── Admin routes ──
  {
    path: '/',
    name: 'dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    // Safata de pendents — visible per a staff (admin/hr/coordinator)
    path: '/safata',
    name: 'safata',
    component: () => import('../views/SafataView.vue'),
    meta: { requiresAuth: true }
  },
  {
    // Control d'hores complementàries i extraordinàries — staff
    path: '/hours-control',
    name: 'hours-control',
    component: () => import('../views/HoursControlView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/work-logs',
    name: 'work-logs',
    component: () => import('../views/WorkLogsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/absences',
    name: 'absences',
    component: () => import('../views/AbsencesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    /* Política de segon factor (Direcció, 01-08-2026). Només admin: no és administrar un
       compte, és decidir com entra tothom. Qui talla de veritat és l'API. */
    path: '/seguretat/segon-factor',
    name: 'segon-factor',
    component: () => import('../views/SegonFactorPoliticaView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    /* Quadre de disponibilitat setmanal (Direcció, 31-07-2026). requiresAdmin cobreix el
       personal de gestió; qui talla de veritat és l'API amb role:admin,hr. */
    path: '/disponibilitat',
    name: 'disponibilitat',
    component: () => import('../views/DisponibilitatView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/employees',
    name: 'employees',
    component: () => import('../views/EmployeesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/expedient',
    name: 'expedient',
    component: () => import('../views/ExpedientView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/millores',
    name: 'millores',
    component: () => import('../views/MilloresView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/rlt',
    name: 'rlt',
    component: () => import('../views/RltView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/comunicacio-certificada',
    name: 'certified-mail',
    component: () => import('../views/CertifiedMailView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/rendiment',
    name: 'rendiment',
    component: () => import('../views/RendimentView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/disciplinary',
    name: 'disciplinary',
    component: () => import('../views/DisciplinaryView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/conciliacio',
    name: 'conciliacio',
    component: () => import('../views/ConciliacioView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/compliance',
    name: 'compliance',
    component: () => import('../views/ComplianceView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/integrity',
    name: 'integrity',
    component: () => import('../views/IntegrityView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  },
  {
    path: '/permisos-config',
    name: 'permisos-config',
    component: () => import('../views/PermisosConfigView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/auth-codes',
    name: 'auth-codes',
    component: () => import('../views/AuthCodesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/reports',
    name: 'reports',
    component: () => import('../views/ReportsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/excedencies',
    name: 'excedencies',
    component: () => import('../views/ExcedenciesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/payrolls-admin',
    name: 'payrolls-admin',
    component: () => import('../views/PayrollsAdminView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  },
  {
    path: '/audit',
    name: 'audit',
    component: () => import('../views/AuditView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  },
  {
    path: '/documents',
    name: 'documents',
    component: () => import('../views/DocumentsAdminView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../views/SettingsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    // Portal d'accés únic: el llançador el veu TOTHOM autenticat (worker i gestió).
    path: '/portal',
    name: 'portal',
    component: () => import('../views/PortalView.vue'),
    meta: { requiresAuth: true }
  },
  {
    // Qui pot entrar a què: només admin/hr (el backend també ho imposa).
    path: '/portal/accessos',
    name: 'portal-accessos',
    component: () => import('../views/PortalAccessosView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/privacy',
    name: 'privacy',
    component: () => import('../views/PrivacyView.vue'),
    meta: { requiresAuth: true }
  },
  // ── Worker routes ──
  {
    path: '/worker',
    name: 'worker-dashboard',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    path: '/worker/calendar',
    name: 'worker-calendar',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'calendar' }
  },
  {
    path: '/worker/absences',
    name: 'worker-absences',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'absences' }
  },
  {
    path: '/worker/excedencies',
    name: 'worker-excedencies',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'excedencies' }
  },
  {
    path: '/worker/history',
    name: 'worker-history',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'history' }
  },
  {
    path: '/worker/safata',
    name: 'worker-safata',
    component: () => import('../views/WorkerInboxView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    path: '/worker/settings',
    name: 'worker-settings',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'settings' }
  },
  {
    path: '/worker/menu',
    name: 'worker-menu',
    component: () => import('../views/WorkerDashboard.vue'),
    meta: { requiresAuth: true, requiresWorker: true },
    props: { initialTab: 'menu' }
  },
  {
    path: '/admin/menu',
    name: 'admin-menu',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
    props: { initialTab: 'menu' }
  },
  {
    path: '/worker/documents',
    name: 'worker-documents',
    component: () => import('../views/DocumentsWorkerView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    path: '/worker/payrolls',
    name: 'worker-payrolls',
    component: () => import('../views/PayrollsWorkerView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    path: '/worker/chat',
    name: 'worker-chat',
    component: () => import('../views/ChatView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    path: '/worker/compliance',
    name: 'worker-compliance',
    component: () => import('../views/WorkerComplianceView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  {
    // El rendiment propi: qui és mesurat ha de poder veure la seva mesura i rebatre-la.
    path: '/worker/rendiment',
    name: 'worker-rendiment',
    component: () => import('../views/ElMeuRendimentView.vue'),
    meta: { requiresAuth: true, requiresWorker: true }
  },
  // ── Admin Chat Supervision ──
  {
    path: '/chat-admin',
    name: 'chat-admin',
    component: () => import('../views/ChatAdminView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  },
  // ── Work Log Detail (vista de tramos segmentados) ──
  {
    path: '/work-logs/:id/detail',
    name: 'work-log-detail',
    component: () => import('../views/WorkLogDetailView.vue'),
    meta: { requiresAuth: true }
  },
  // ── Break Settings (configuración de pausa) ──
  {
    path: '/break-settings',
    name: 'break-settings',
    component: () => import('../views/BreakSettingsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  },
  // ── Mail Settings (configuració de correu) ──
  {
    path: '/mail-settings',
    name: 'mail-settings',
    component: () => import('../views/MailSettingsView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true, blockHr: true }
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Wait for initial auth check if not already done
  if (!authStore.isInitialized) {
    await authStore.init()
  }
  
  // If requires auth and not authenticated, redirect to login
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next({ name: 'login' })
    return
  }
  
  // If admin-only route and user is worker, redirect to worker dashboard
  // Coordinators can access admin routes too
  if (to.meta.requiresAdmin && authStore.user?.role === 'worker') {
    next({ name: 'worker-dashboard' })
    return
  }

  // Responsable RRHH (hr): casi-admin però SENSE nòmines ni configuració de sistema/seguretat.
  if (to.meta.blockHr && authStore.user?.role === 'hr') {
    next({ name: 'dashboard' })
    return
  }

  // If worker-only route and user is admin, allow (admin can preview worker view)
  // But redirect workers trying to access admin routes
  if (to.meta.requiresWorker && authStore.user?.role !== 'worker' && authStore.user?.role !== 'admin') {
    next({ name: 'dashboard' })
    return
  }
  
  // If logged in and trying to access login page, redirect based on role
  if (to.meta.guest && authStore.isAuthenticated) {
    if (authStore.isWorker) {
      next({ name: 'worker-dashboard' })
    } else {
      next({ name: 'dashboard' })
    }
    return
  }
  
  next()
})

export default router
