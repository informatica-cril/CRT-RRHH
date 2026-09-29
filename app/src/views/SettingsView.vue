<template>
  <div>
    <div class="page-header">
      <div><h1 class="page-title">{{ t('settings') }}</h1></div>
    </div>

    <div class="settings-grid">
      <div class="card">
        <h3 class="card-title mb-md">{{ t('language') }}</h3>
        <div class="lang-selector" style="display:inline-flex;">
          <button v-for="lang in locales" :key="lang" class="lang-btn"
            :class="{ active: currentLocale === lang }" @click="setLocale(lang)">
            {{ localeNames[lang] }}
          </button>
        </div>
      </div>

      <div class="card">
        <h3 class="card-title mb-md">{{ t('dark_mode') }}</h3>
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;">
          <input type="checkbox" :checked="settingsStore.darkMode" @change="settingsStore.toggleDarkMode()" style="width:18px;height:18px;" />
          <span>{{ t('dark_mode') }}</span>
        </label>
      </div>

      <div class="card" style="grid-column:1/-1;">
        <h3 class="card-title mb-md">{{ t('consent_management') }}</h3>
        <p class="text-muted mb-md">{{ t('data_controller') }}</p>
        <p class="text-muted mb-md">{{ t('legal_basis') }}</p>
        <p class="text-muted mb-md">{{ t('data_retention') }}</p>
        
        <div style="border-top:1px solid var(--color-border);padding-top:16px;margin-top:16px;">
          <h4 style="margin-bottom:12px;">Consentiments</h4>
          <div v-for="consent in consents" :key="consent.type" style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--color-border-light);">
            <div>
              <strong>{{ consent.label }}</strong>
              <p class="text-small text-muted">{{ consent.description }}</p>
            </div>
            <label :style="consent.type === 'data_processing' ? 'cursor:not-allowed;opacity:0.5;' : 'cursor:pointer;'">
              <input type="checkbox" :checked="consent.granted" @change="toggleConsent(consent.type)" :disabled="consent.type === 'data_processing'" style="width:16px;height:16px;" />
            </label>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useSettingsStore } from '../stores/settings'
import { i18n } from '../i18n'

const settingsStore = useSettingsStore()
const t = (key) => i18n.t(key)
const locales = i18n.availableLocales
const localeNames = i18n.localeNames
const currentLocale = computed(() => i18n.locale)

function setLocale(lang) { i18n.locale = lang }

const consents = ref([
  { type: 'data_processing', label: 'Tractament de dades laborals', description: 'Necessari per al registre de jornada (obligatori per RDL 8/2019)', granted: true },
  { type: 'geolocation', label: 'Geolocalització', description: 'Per verificar la ubicació durant la jornada laboral', granted: true },
  { type: 'analytics', label: 'Analítica', description: 'Per millorar l\'experiència d\'ús de l\'aplicació', granted: false }
])

function toggleConsent(type) {
  const c = consents.value.find(c => c.type === type)
  if (c && type !== 'data_processing') c.granted = !c.granted
}
</script>
