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
    path: '/employees',
    name: 'employees',
    component: () => import('../views/EmployeesView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
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
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/audit',
    name: 'audit',
    component: () => import('../views/AuditView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
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
  // ── Admin Chat Supervision ──
  {
    path: '/chat-admin',
    name: 'chat-admin',
    component: () => import('../views/ChatAdminView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
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
  if (to.meta.requiresAdmin && authStore.user?.role === 'worker') {
    next({ name: 'worker-dashboard' })
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
