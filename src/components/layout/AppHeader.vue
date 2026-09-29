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

      <!-- Notifications -->
      <button class="icon-btn" id="btn-notifications" :title="t('alerts')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <span class="badge" v-if="hasNotifications"></span>
      </button>

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
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useSettingsStore } from '../../stores/settings'
import { i18n } from '../../i18n'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
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

const hasNotifications = computed(() => false)
</script>
