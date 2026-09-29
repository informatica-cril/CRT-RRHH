<template>
  <header class="app-header">
    <div class="header-search hide-mobile" id="header-search">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" :placeholder="t('search')" />
    </div>

    <div class="header-actions">
      <!-- Language Selector -->
      <div class="lang-selector hide-mobile" id="lang-selector">
        <button v-for="lang in availableLocales" :key="lang"
          class="lang-btn" :class="{ active: locale === lang }"
          @click="setLocale(lang)">
          {{ lang.toUpperCase() }}
        </button>
      </div>

      <!-- Dark mode toggle -->
      <button class="icon-btn" id="btn-dark-mode" @click="settingsStore.toggleDarkMode()" :title="t('dark_mode')">
        <svg v-if="!settingsStore.darkMode" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
        <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>

      <!-- Notifications bell -->
      <div class="notif-wrapper" ref="notifRef" v-if="authStore.isAdmin">
        <button class="icon-btn notif-btn" :title="t('alerts')" @click="togglePanel">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span v-if="notifStore.hasAny" class="notif-dot">{{ notifStore.total }}</span>
        </button>

        <!-- Dropdown panel -->
        <div v-if="panelOpen" class="notif-panel">
          <div class="notif-panel-header">
            <span>Alertes</span>
            <span v-if="notifStore.total > 0" class="notif-total-badge">{{ notifStore.total }}</span>
          </div>

          <div v-if="!notifStore.hasAny" class="notif-empty">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <p>Tot al dia! No hi ha alertes pendents.</p>
          </div>

          <template v-else>
            <button v-if="notifStore.pendingWorkLogs > 0" class="notif-item" @click="go('/work-logs')">
              <div class="notif-item-icon notif-icon-orange">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div class="notif-item-body">
                <p class="notif-item-title">Registres horaris pendents</p>
                <p class="notif-item-sub">{{ notifStore.pendingWorkLogs }} registre{{ notifStore.pendingWorkLogs > 1 ? 's' : '' }} pendent{{ notifStore.pendingWorkLogs > 1 ? 's' : '' }} d'aprovació</p>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>

            <button v-if="notifStore.pendingAbsences > 0" class="notif-item" @click="go('/absences')">
              <div class="notif-item-icon notif-icon-blue">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              </div>
              <div class="notif-item-body">
                <p class="notif-item-title">Sol·licituds d'absència</p>
                <p class="notif-item-sub">{{ notifStore.pendingAbsences }} sol·licitud{{ notifStore.pendingAbsences > 1 ? 's' : '' }} pendent{{ notifStore.pendingAbsences > 1 ? 's' : '' }} d'aprovació</p>
              </div>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
          </template>

          <div class="notif-panel-footer">
            <button class="notif-refresh-btn" @click="notifStore.refresh()">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
              Actualitzar
            </button>
          </div>
        </div>
      </div>

      <!-- User -->
      <div class="header-user" id="header-user">
        <div class="header-user-info hide-mobile">
          <div class="header-user-name">{{ authStore.userName }}</div>
          <div class="header-user-email">{{ authStore.userEmail }}</div>
        </div>
        <div class="header-avatar">{{ initials }}</div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useSettingsStore } from '../../stores/settings'
import { useNotificationsStore } from '../../stores/notifications'
import { i18n } from '../../i18n'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const notifStore = useNotificationsStore()
const router = useRouter()
const t = (key) => i18n.t(key)

const availableLocales = i18n.availableLocales
const locale = computed(() => i18n.locale)

function setLocale(lang) {
  i18n.locale = lang
}

const initials = computed(() => {
  const name = authStore.userName
  return name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase()
})

// Panel open/close
const panelOpen = ref(false)
const notifRef = ref(null)

function togglePanel() {
  panelOpen.value = !panelOpen.value
  if (panelOpen.value) notifStore.refresh()
}

function go(path) {
  panelOpen.value = false
  router.push(path)
}

function onClickOutside(e) {
  if (notifRef.value && !notifRef.value.contains(e.target)) {
    panelOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', onClickOutside))
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.notif-wrapper {
  position: relative;
}

.notif-btn {
  position: relative;
}

.notif-dot {
  position: absolute;
  top: 2px;
  right: 2px;
  min-width: 16px;
  height: 16px;
  background: #ef4444;
  color: #fff;
  font-size: 0.6rem;
  font-weight: 700;
  border-radius: 99px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 3px;
  pointer-events: none;
}

.notif-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 300px;
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.12);
  z-index: 1000;
  overflow: hidden;
}

.notif-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px 10px;
  font-weight: 700;
  font-size: 0.9rem;
  border-bottom: 1px solid var(--color-border, #e2e8f0);
}

.notif-total-badge {
  background: #ef4444;
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 99px;
}

.notif-empty {
  padding: 28px 16px;
  text-align: center;
  color: var(--color-text-muted, #9ca3af);
}

.notif-empty svg {
  margin: 0 auto 8px;
  display: block;
  stroke: #22c55e;
}

.notif-empty p {
  font-size: 0.85rem;
  margin: 0;
}

.notif-item {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  padding: 12px 16px;
  background: none;
  border: none;
  border-bottom: 1px solid var(--color-border, #f1f5f9);
  cursor: pointer;
  text-align: left;
  transition: background 0.15s;
}

.notif-item:hover {
  background: var(--color-hover, #f8fafc);
}

.notif-item-icon {
  width: 34px;
  height: 34px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.notif-icon-orange { background: #fff7ed; color: #ea580c; }
.notif-icon-blue   { background: #eff6ff; color: #2563eb; }

.notif-item-body {
  flex: 1;
  min-width: 0;
}

.notif-item-title {
  font-size: 0.85rem;
  font-weight: 600;
  margin: 0 0 2px;
  color: var(--color-text, #1e293b);
}

.notif-item-sub {
  font-size: 0.75rem;
  color: var(--color-text-muted, #64748b);
  margin: 0;
}

.notif-panel-footer {
  padding: 10px 16px;
  display: flex;
  justify-content: flex-end;
}

.notif-refresh-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.75rem;
  color: var(--color-text-muted, #64748b);
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 6px;
  transition: background 0.15s;
}

.notif-refresh-btn:hover {
  background: var(--color-hover, #f1f5f9);
}
</style>
