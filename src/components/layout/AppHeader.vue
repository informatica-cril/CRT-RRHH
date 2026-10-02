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
      <div class="alertes-wrap" ref="alertesWrap">
        <button class="icon-btn" id="btn-notifications" :title="t('alerts')" @click="toggleAlertes">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="badge" v-if="hasNotifications"></span>
        </button>
        <div v-if="alertesObert" class="alertes-panel">
          <div class="alertes-cap">
            <strong>{{ t('alerts') }}</strong>
            <span class="text-small text-muted">{{ totalAlertes }}</span>
          </div>
          <!-- Personal de gestió: el que espera una decisió seva (el mateix recompte que la Safata) -->
          <router-link v-for="c in pendents" :key="c.clau" :to="c.enllac" class="alertes-item alertes-cua" :class="c.urgencia" @click="alertesObert = false">
            <span class="alertes-ico">{{ c.icona }}</span>
            <div class="alertes-cos"><div class="alertes-msg">{{ c.titol }}</div></div>
            <span class="alertes-n">{{ c.n }}</span>
          </router-link>
          <div v-if="totalAlertes === 0" class="alertes-buit">✓ No tens cap avís pendent</div>
          <div v-for="a in alertes" :key="a.id" class="alertes-item">
            <span class="alertes-ico">{{ iconaAlerta(a.type) }}</span>
            <div class="alertes-cos">
              <div class="alertes-msg">{{ a.message || missatgeAlerta(a.type) }}</div>
              <div class="alertes-meta">
                <span>{{ quan(a.sent_at || a.scheduled_at || a.created_at) }}</span>
                <router-link v-if="enllacAlerta(a)" :to="enllacAlerta(a)" @click="alertesObert = false">Obrir ▸</router-link>
              </div>
            </div>
            <button class="alertes-x" title="Descartar" @click="descarta(a)">✕</button>
          </div>
          <router-link :to="authStore.isWorker ? '/worker/safata' : '/safata'" class="alertes-peu" @click="alertesObert = false">
            {{ authStore.isWorker ? 'Obrir la meva safata' : 'Obrir la safata de pendents' }} ▸
          </router-link>
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
import { useAuthStore } from '../../stores/auth'
import { useSettingsStore } from '../../stores/settings'
import { i18n } from '../../i18n'
import db from '../../services/db'
import api from '../../services/apiClient'

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

// ── Alertes: els avisos pendents de la persona (work_log_alerts), els mateixos que el bàner ──
const alertes = ref([])
const alertesObert = ref(false)
const alertesWrap = ref(null)
const pendents = ref([])
const totalAlertes = computed(() => alertes.value.length + pendents.value.reduce((s, c) => s + c.n, 0))
const hasNotifications = computed(() => totalAlertes.value > 0)

async function carregaAlertes() {
  if (!authStore.userId) return
  try {
    const r = await db.getPendingAlerts(authStore.userId)
    alertes.value = Array.isArray(r) ? r : []
  } catch { /* la campana no ha de trencar la capçalera */ }
  if (authStore.isStaff) {
    try { pendents.value = (await api.get('/v1/safata/resum'))?.cues || [] } catch { /* idem */ }
  }
}

function toggleAlertes() {
  alertesObert.value = !alertesObert.value
  if (alertesObert.value) carregaAlertes()
}

async function descarta(a) {
  try {
    await db.dismissAlert(a.id)
    alertes.value = alertes.value.filter(x => x.id !== a.id)
  } catch (e) { console.error(e) }
}

function iconaAlerta(type) {
  return { no_clock_in: '⏰', no_clock_out: '🚪', break_required: '☕', audiencia: '🗣️',
    segment_rejected: '⚠️', out_of_zone: '📍', resolucio_rrhh: '📬' }[type] || '🔔'
}

function missatgeAlerta(type) {
  return {
    no_clock_in: "No has fitxat l'entrada avui.",
    no_clock_out: 'Tens un fitxatge sense tancar.',
    break_required: 'Has de fer la pausa obligatòria.',
    audiencia: 'Tens un marcatge pendent de revisió: pots presentar la teva explicació.',
    segment_rejected: 'Un tram del teu fitxatge ha estat rebutjat.',
    out_of_zone: "S'ha detectat fitxatge fora de zona.",
    resolucio_rrhh: 'Tens una sol·licitud resolta.',
  }[type] || 'Tens un avís nou.'
}

function enllacAlerta(a) {
  if (['audiencia', 'segment_rejected', 'out_of_zone'].includes(a.type) && a.work_log_id) return `/work-logs/${a.work_log_id}/detail`
  if (a.type === 'resolucio_rrhh') return authStore.isWorker ? '/worker/absences' : '/absences'
  if (['no_clock_in', 'no_clock_out', 'break_required'].includes(a.type)) return authStore.isWorker ? '/worker' : null
  return null
}

function quan(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  return isNaN(d) ? '' : d.toLocaleString('ca-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function tancaFora(e) {
  if (alertesObert.value && alertesWrap.value && !alertesWrap.value.contains(e.target)) alertesObert.value = false
}

let interval = null
onMounted(() => {
  carregaAlertes()
  interval = setInterval(carregaAlertes, 60000)
  document.addEventListener('click', tancaFora)
})
onUnmounted(() => {
  clearInterval(interval)
  document.removeEventListener('click', tancaFora)
})
</script>

<style scoped>
.alertes-wrap { position: relative; }
.alertes-panel { position: absolute; right: 0; top: calc(100% + 8px); width: 340px; max-width: calc(100vw - 32px); max-height: 70vh; overflow-y: auto; background: var(--color-surface, #fff); border: 1px solid var(--color-border, #DCE4EE); border-radius: 12px; box-shadow: 0 10px 30px rgba(10, 42, 74, .15); z-index: 1000; }
.alertes-cap { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border-bottom: 1px solid var(--color-border, #DCE4EE); }
.alertes-buit { padding: 20px 14px; text-align: center; color: #0A7C5E; font-size: .88rem; }
.alertes-item { display: flex; gap: 10px; align-items: flex-start; padding: 10px 14px; border-bottom: 1px solid var(--color-border, #eef2f6); }
.alertes-ico { font-size: 1.1rem; line-height: 1.3; }
.alertes-cos { flex: 1; min-width: 0; }
.alertes-msg { font-size: .85rem; color: var(--color-text); line-height: 1.35; }
.alertes-meta { display: flex; gap: 10px; margin-top: 4px; font-size: .74rem; color: var(--color-text-muted, #7a8aa0); }
.alertes-meta a { color: var(--color-primary, #094E8C); font-weight: 600; text-decoration: none; }
.alertes-x { background: none; border: none; cursor: pointer; color: var(--color-text-muted, #7a8aa0); font-size: .85rem; padding: 2px 4px; }
.alertes-x:hover { color: var(--color-danger, #B3352F); }
.alertes-cua { text-decoration: none; align-items: center; border-left: 4px solid #cfd8e3; }
.alertes-cua:hover { background: rgba(10, 42, 74, .04); }
.alertes-cua.alta { border-left-color: #B3352F; }
.alertes-cua.mitjana { border-left-color: #D9A400; }
.alertes-cua.baixa { border-left-color: #0DAF83; }
.alertes-n { min-width: 26px; height: 22px; padding: 0 7px; border-radius: 99px; background: #B3352F; color: #fff; font-size: .78rem; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; }
.alertes-cua.mitjana .alertes-n { background: #D9A400; }
.alertes-cua.baixa .alertes-n { background: #0DAF83; }
.alertes-peu { display: block; padding: 10px 14px; text-align: center; font-size: .82rem; font-weight: 600; color: var(--color-primary, #094E8C); text-decoration: none; }
</style>
