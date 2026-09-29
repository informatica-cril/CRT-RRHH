<template>
  <nav class="bottom-nav" v-if="isVisible">
    <router-link
      v-for="item in navItems"
      :key="item.to"
      :to="item.to"
      class="bottom-nav-item"
      :class="{ active: $route.name === item.name || $route.path === item.to }"
    >
      <div class="bottom-nav-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" v-html="item.icon"></svg>
        <span v-if="item.badge && item.badge > 0" class="bottom-nav-badge">{{ item.badge }}</span>
      </div>
      <span class="bottom-nav-label">{{ item.label }}</span>
    </router-link>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useWorkLogStore } from '../../stores/workLog'
import { i18n } from '../../i18n'

const route = useRoute()
const authStore = useAuthStore()
const workLogStore = useWorkLogStore()
const t = (key) => i18n.t(key)

const isVisible = computed(() => authStore.isAuthenticated)

const pendingCount = computed(() => {
  if (!authStore.isAdmin) return 0
  return workLogStore.logs.filter(l => l.status === 'pending').length
})

const navItems = computed(() => {
  if (authStore.isWorker) {
    // Worker: dashboard, registros, ausencias
    return [
      {
        to: '/worker',
        name: 'worker-dashboard',
        label: t('dashboard'),
        icon: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'
      },
      {
        to: '/worker/calendar',
        name: 'worker-calendar',
        label: 'Calendari',
        icon: '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'
      },
      {
        to: '/worker/payrolls',
        name: 'worker-payrolls',
        label: 'Nòmines',
        icon: '<path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'
      },
      {
        to: '/worker/chat',
        name: 'worker-chat',
        label: 'Xat',
        icon: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'
      },
      {
        to: '/worker/menu',
        name: 'worker-menu',
        label: 'Més',
        icon: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>'
      }
    ]
  }

  // Admin: dashboard, registros, empleados, informes, más
  return [
    {
      to: '/',
      name: 'dashboard',
      label: t('dashboard'),
      icon: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'
    },
    {
      to: '/work-logs',
      name: 'work-logs',
      label: t('work_logs'),
      badge: pendingCount.value,
      icon: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>'
    },
    {
      to: '/employees',
      name: 'employees',
      label: t('employees'),
      icon: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
    },
    {
      to: '/reports',
      name: 'reports',
      label: t('reports'),
      icon: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>'
    },
    {
      to: '/admin/menu',
      name: 'admin-menu',
      label: t('more'),
      icon: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>'
    }
  ]
})
</script>

<style scoped>
.bottom-nav {
  display: none; /* Hidden on desktop by default */
}

@media (max-width: 1024px) {
  .bottom-nav {
    display: flex;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: var(--color-surface, #1e2435);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    /* Safe area for iOS home bar */
    padding-bottom: env(safe-area-inset-bottom, 0);
    padding-left: env(safe-area-inset-left, 0);
    padding-right: env(safe-area-inset-right, 0);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
  }

  .bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 8px 4px 6px;
    text-decoration: none;
    color: rgba(255, 255, 255, 0.45);
    transition: color 0.2s ease, transform 0.15s ease;
    min-height: 56px;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
    position: relative;
  }

  .bottom-nav-item:active {
    transform: scale(0.92);
  }

  .bottom-nav-item.active {
    color: #4299E1; /* Bright Blue for high contrast */
    text-shadow: 0 0 8px rgba(66, 153, 225, 0.4);
  }

  .bottom-nav-item.active .bottom-nav-icon::before {
    content: '';
    position: absolute;
    top: -4px;
    left: 50%;
    transform: translateX(-50%);
    width: 36px;
    height: 3px;
    background: #4299E1;
    border-radius: 0 0 3px 3px;
  }

  .bottom-nav-icon {
    position: relative;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 3px;
  }

  .bottom-nav-icon svg {
    width: 22px;
    height: 22px;
    stroke-linecap: round;
    stroke-linejoin: round;
  }

  .bottom-nav-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: var(--color-danger, #ef4444);
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3px;
    border: 2px solid var(--color-surface, #1e2435);
  }

  .bottom-nav-label {
    font-size: 0.62rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    line-height: 1;
    text-align: center;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
