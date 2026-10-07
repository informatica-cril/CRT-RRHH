<template>
  <div id="crt-app" :class="{ 'dark-mode': isDark, 'is-mobile': isMobile }">
    <!-- Avisos dins de l'app (substitueixen la finestra «… diu» del navegador) -->
    <AvisosApp />
    <DialegApp />
    <!-- Global Navigation Blocker / Loader -->
    <div v-if="isNavigating" class="nav-loader-overlay">
      <div class="nav-loader-content">
        <div class="premium-spinner"></div>
        <div class="nav-loader-text">Carregant la pàgina...</div>
      </div>
    </div>

    <!-- Privacy Consent Banner -->
    <PrivacyBanner v-if="showPrivacyBanner" @accept="acceptPrivacy" @reject="rejectPrivacy" />
    
    <!-- Login / Public View -->
    <template v-if="!isAuthenticated || isLoginRoute">
      <router-view v-slot="{ Component, route }">
        <transition name="fade" mode="out-in">
          <component :is="Component" :key="route.path" />
        </transition>
      </router-view>
    </template>
    
    <!-- Main App Layout (Authenticated & Not Login) -->
    <div v-else class="app-layout">
      <!-- Sidebars — hidden on mobile via CSS -->
      <WorkerSidebar v-if="isWorker" />
      <Sidebar v-else />

      <div class="app-main">
        <AppHeader />
        <main class="app-content">
          <router-view v-slot="{ Component, route }">
            <transition name="fade" mode="out-in">
              <component :is="Component" :key="route.path" />
            </transition>
          </router-view>
        </main>
      </div>

      <!-- Bottom navigation for mobile -->
      <BottomNav />
    </div>

    <!-- ═══ GEOLOCATION CONSENT (highest priority — blocks everything for workers) ═══ -->
    <GeolocationConsent v-if="needsGeoConsent && isAuthenticated && isWorker" @accepted="geoConsentDone" />

    <!-- ═══ AVÍS D'UBICACIÓ DENEGADA ═══
         La ubicació és obligatòria per REGISTRAR LA JORNADA (art. 34.9 ET), no per
         entrar a l'aplicació. Abans això era una pantalla que tapava tota l'app i
         deixava el treballador sense nòmina, sense sol·licituds i —el més greu— sense
         poder llegir ni contestar una notificació disciplinària (art. 55.1 ET).
         Ara bloqueja el fitxatge, que és el que depèn de la ubicació, i no la resta. -->
    <div v-if="!needsGeoConsent && nativeGeoDenied && isAuthenticated && isWorker && !geoAvisTancat"
         style="position:fixed;left:0;right:0;bottom:0;z-index:9000;background:var(--color-card-bg,#fff);
                border-top:3px solid var(--color-danger,#b42318);box-shadow:0 -8px 30px rgba(0,0,0,.15);padding:14px 18px;">
      <div style="max-width:900px;margin:0 auto;display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;">
        <div style="font-size:1.6rem;line-height:1;">📍</div>
        <div style="flex:1;min-width:240px;">
          <div style="font-weight:700;margin-bottom:4px;">La ubicació està desactivada</div>
          <div class="text-small text-muted" style="line-height:1.55;">
            Pots registrar la jornada igualment: en iniciar-la se't demanarà una justificació
            escrita i quedarà pendent de revisió. La resta de l'aplicació —nòmines,
            sol·licituds, documents i notificacions— la pots fer servir amb normalitat.
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <button class="btn btn-primary" @click="checkNativeGeo">Ja l'he activat — Reintentar</button>
          <button class="btn btn-outline" @click="geoAvisTancat = true">Ara no</button>
        </div>
      </div>
    </div>

    <!-- ═══ ONBOARDING WIZARD (blocks everything, after geo consent) ═══ -->
    <OnboardingWizard v-if="!needsGeoConsent && needsOnboarding && isAuthenticated && isWorker" @completed="onboardingDone" />

    <!-- ═══ URGENT DOCUMENT MODAL (blocks all access) ═══ -->
    <div v-if="!needsGeoConsent && !needsOnboarding && urgentDoc && isAuthenticated && isWorker" class="modal-overlay" style="z-index:99999;">
      <div class="modal" style="max-width:750px;max-height:90vh;display:flex;flex-direction:column;">
        <div class="modal-header" style="background:rgba(239,68,68,0.08);border-radius:12px 12px 0 0;">
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
              <span class="badge badge-danger" style="font-size:0.75rem;">🔴 DOCUMENT URGENT</span>
            </div>
            <h3 class="modal-title">{{ urgentDoc.title }}</h3>
            <div class="text-small text-muted">{{ urgentDoc.description }}</div>
          </div>
        </div>

        <div style="padding:12px 16px;background:rgba(239,68,68,0.04);font-size:0.82rem;color:var(--color-danger);font-weight:500;text-align:center;">
          ⚠️ No podeu accedir a l'aplicació fins que llegiu i signeu aquest document obligatori.
          <span class="text-muted" style="display:block;font-size:0.75rem;font-weight:400;margin-top:4px;">
            Restem {{ urgentDocsCount }} document{{ urgentDocsCount > 1 ? 's' : '' }} urgent{{ urgentDocsCount > 1 ? 's' : '' }} per signar
          </span>
        </div>

        <!-- Document content: PDF or text -->
        <iframe v-if="urgentDoc.pdf_data" :src="urgentDoc.pdf_data" style="flex:1;border:none;border-radius:8px;margin:0 12px;min-height:350px;"></iframe>
        <div v-else style="flex:1;overflow-y:auto;padding:20px;background:var(--color-bg);margin:0 12px;border-radius:8px;font-size:0.88rem;line-height:1.7;white-space:pre-wrap;font-family:'Segoe UI',system-ui,sans-serif;border:1px solid var(--color-border-light);">{{ urgentDoc.content }}</div>

        <!-- Signature section -->
        <div style="padding:16px;">
          <div style="background:rgba(59,130,246,0.06);padding:12px;border-radius:10px;margin-bottom:14px;">
            <div style="font-weight:600;font-size:0.85rem;margin-bottom:6px;">✍️ Firma Electrònica Avançada (eIDAS Art. 26)</div>
            <div class="text-small text-muted" style="line-height:1.5;">
              Hash SHA-256 · Sello RFC 3161 · Conforme Reg. (UE) 910/2014 i ENS RD 311/2022
            </div>
          </div>
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:14px;">
            <input type="checkbox" v-model="urgentAccepted" style="width:18px;height:18px;margin-top:2px;flex-shrink:0;" />
            <span style="font-size:0.85rem;line-height:1.5;">
              <strong>Declaro</strong> que he llegit íntegrament el contingut d'aquest document i n'accepto els termes.
            </span>
          </label>
          <button class="btn btn-primary" style="width:100%;padding:12px;" @click="signUrgentDoc" :disabled="!urgentAccepted || urgentSigning">
            {{ urgentSigning ? '⏳ Signant...' : '✍️ Firmar i continuar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AvisosApp from './components/AvisosApp.vue'
import DialegApp from './components/DialegApp.vue'
import { computed, ref, onMounted, onUnmounted, watch } from 'vue'
import { useAuthStore } from './stores/auth'
import { useSettingsStore } from './stores/settings'
import { useRouter, useRoute } from 'vue-router'
import { db } from './services/db'
import { createSignaturePackage } from './services/signatureService'
import { auditLog } from './services/audit'
import Sidebar from './components/layout/Sidebar.vue'
import WorkerSidebar from './components/layout/WorkerSidebar.vue'
import AppHeader from './components/layout/AppHeader.vue'
import BottomNav from './components/layout/BottomNav.vue'
import OnboardingWizard from './components/OnboardingWizard.vue'
import GeolocationConsent from './components/GeolocationConsent.vue'
import PrivacyBanner from './components/PrivacyBanner.vue'

const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const router = useRouter()
const route = useRoute()

const isNavigating = ref(false)

// Router hooks for global navigation blocking
router.beforeEach((to, from, next) => {
  if (to.path !== from.path) {
    isNavigating.value = true
  }
  next()
})

router.afterEach(() => {
  // Add a tiny delay to ensure the component is actually "hydrated" and rendered
  setTimeout(() => {
    isNavigating.value = false
  }, 250)
})

const isAuthenticated = computed(() => authStore.isAuthenticated)
const isWorker = computed(() => authStore.isWorker)
const isDark = computed(() => settingsStore.darkMode)
const showPrivacyBanner = ref(false)
const isLoginRoute = computed(() => route.name === 'login')

// ── Geolocation consent check (highest priority for workers) ────
const needsGeoConsent = ref(false)

function checkGeoConsent() {
  if (authStore.isAuthenticated && authStore.isWorker && authStore.user) {
    if (authStore.user.job_profile === 'Gerencia') {
      needsGeoConsent.value = false
      return
    }
    const dbConsent = !!authStore.user.geo_consent_accepted_at
    const localConsent = !!localStorage.getItem(`crt_geo_consent_${authStore.userId}`)
    needsGeoConsent.value = !dbConsent && !localConsent
  } else {
    needsGeoConsent.value = false
  }
}

function geoConsentDone() {
  needsGeoConsent.value = false
  checkNativeGeo()
}

// ── Native Geo Permission Blocker ────
const nativeGeoDenied = ref(false)
// L'avís es pot apartar per treballar; torna a sortir a cada sessió mentre no hi hagi ubicació.
const geoAvisTancat = ref(false)

async function checkNativeGeo() {
  if (!authStore.isAuthenticated || !authStore.isWorker) return
  if (authStore.user?.job_profile === 'Gerencia') return

  if (navigator.permissions) {
    try {
      const status = await navigator.permissions.query({ name: 'geolocation' })
      nativeGeoDenied.value = status.state === 'denied'
      status.onchange = () => {
        nativeGeoDenied.value = status.state === 'denied'
      }
    } catch (e) {
      console.warn("Permissions API error", e)
    }
  }
  
  if (!navigator.permissions || nativeGeoDenied.value === false) {
    // Force prompt / check fallback
    navigator.geolocation.getCurrentPosition(
      () => { nativeGeoDenied.value = false },
      (err) => { 
        if (err.code === 1) nativeGeoDenied.value = true 
      },
      { timeout: 10000, enableHighAccuracy: false }
    )
  }
}

// ── Onboarding check ────────────────────────────────────────────
const needsOnboarding = ref(false)

async function checkOnboarding() {
  if (authStore.isAuthenticated && authStore.isWorker) {
    try {
      const user = authStore.user || await db.getUser(authStore.userId)
      // Mana el perfil d'alta que li ha assignat gestió, no la seva categoria: el filtre
      // per «Fisioterapeuta» feia que assignar una alta a una terapeuta ocupacional o a
      // coordinació no servís de res, sense dir-ho enlloc.
      needsOnboarding.value = !!(user && user.onboarding_completed === false && user.onboarding_profile_id)
    } catch (e) {
      console.error('[Onboarding] Error:', e)
      needsOnboarding.value = false
    }
  } else {
    needsOnboarding.value = false
  }
}

async function onboardingDone() {
  needsOnboarding.value = false
  // Refresh user in store
  authStore.user = await db.getUser(authStore.userId)
  // Check urgent docs after onboarding
  await checkUrgentDocs()
}

// ── Urgent document blocking ────────────────────────────────────
const urgentDocs = ref([])
const urgentDoc = computed(() => urgentDocs.value[0] || null)
const urgentDocsCount = computed(() => urgentDocs.value.length)
const urgentAccepted = ref(false)
const urgentSigning = ref(false)

async function checkUrgentDocs() {
  if (authStore.isAuthenticated && authStore.isWorker) {
    try {
      const user = authStore.user || await db.getUser(authStore.userId)
      if (user?.job_profile === 'Gerencia') {
        urgentDocs.value = []
        return
      }
      urgentDocs.value = await db.getUnsignedUrgentDocs(authStore.userId)
      console.log('[UrgentDocs] Unsigned count:', urgentDocs.value.length)
      urgentAccepted.value = false
    } catch (e) {
      console.error('[UrgentDocs] Error:', e)
      urgentDocs.value = []
    }
  } else {
    urgentDocs.value = []
  }
}

async function signUrgentDoc() {
  if (!urgentDoc.value || !urgentAccepted.value) return
  urgentSigning.value = true

  try {
    // Record view if needed
    let sig = await db.getDocSignature(urgentDoc.value.id, authStore.userId)
    if (!sig) {
      sig = await db.addDocSignature({
        document_id: urgentDoc.value.id, user_id: authStore.userId,
        viewed_at: new Date().toISOString(), signed_at: null,
        document_hash: null, signature_hash: null,
        timestamp_source: null, timestamp_token: null,
        ip_address: '127.0.0.1', user_agent: navigator.userAgent
      })
    }

    // Create signature package
    const sigPackage = await createSignaturePackage({
      documentId: urgentDoc.value.id,
      documentContent: urgentDoc.value.content,
      userId: authStore.userId,
      userName: authStore.userName,
      userEmail: authStore.userEmail,
      userDni: authStore.user?.dni
    })

    console.log('[UrgentSign] Updating signature:', sig.id, sigPackage.signed_at)
    const updateResult = await db.updateDocSignature({
      ...sig,
      signed_at: sigPackage.signed_at,
      document_hash: sigPackage.document_hash,
      signature_hash: sigPackage.signature_hash,
      timestamp_source: sigPackage.timestamp.source,
      timestamp_token: sigPackage.timestamp.token || sigPackage.timestamp.bindingHash,
      signing_payload: sigPackage.signing_payload,
      legal_basis: sigPackage.legal_basis,
      ip_address: sigPackage.signer.ip_address,
      user_agent: sigPackage.signer.user_agent
    })
    console.log('[UrgentSign] Update response:', updateResult)

    console.log('[UrgentSign] Signature successful')
    auditLog(authStore.userId, 'SIGN_URGENT_DOCUMENT', 'document', urgentDoc.value.id,
      `Firma urgent: ${urgentDoc.value.title} | Hash: ${sigPackage.signature_hash.substring(0, 16)}...`)

    // Refresh urgent docs list — IMPORTANT: await to ensure modal closes only when data is updated
    await checkUrgentDocs()
  } catch (err) {
    console.error('[UrgentSign] Error:', err)
    alert('Error en la firma. Torneu-ho a provar.')
  } finally {
    urgentSigning.value = false
  }
}

// Watch auth state changes to check for urgent docs and redirect from login
watch(() => settingsStore.darkMode, (val) => {
  if (val) {
    document.body.classList.add('dark-mode')
  } else {
    document.body.classList.remove('dark-mode')
  }
}, { immediate: true })

watch(() => authStore.isAuthenticated, async (val) => {
  if (val) {
    checkGeoConsent()
    if (!needsGeoConsent.value) checkNativeGeo()
    await checkOnboarding()
    await checkUrgentDocs()
  } else {
    // Redirect to login if session is lost and not on a public route
    if (route.name !== 'login' && !route.meta?.guest) {
      router.push('/login')
    }
  }
}, { immediate: true })

// We no longer need the route name watcher for login redirects as the router's beforeEach and meta.guest already handle it properly.

// Mobile detection (reactive)
const isMobile = ref(window.innerWidth <= 1024)
function onResize() {
  isMobile.value = window.innerWidth <= 1024
}

onMounted(async () => {
  await authStore.checkAuth()
  const consent = localStorage.getItem('crt_privacy_consent')
  if (!consent) {
    showPrivacyBanner.value = true
  }
  window.addEventListener('resize', onResize)
  // Check geo consent, onboarding and urgent docs on load
  checkGeoConsent()
  await checkOnboarding()
  await checkUrgentDocs()
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
})

function acceptPrivacy() {
  localStorage.setItem('crt_privacy_consent', JSON.stringify({
    accepted: true,
    date: new Date().toISOString(),
    types: ['data_processing', 'geolocation']
  }))
  showPrivacyBanner.value = false
}

function rejectPrivacy() {
  localStorage.setItem('crt_privacy_consent', JSON.stringify({
    accepted: false,
    date: new Date().toISOString(),
    types: []
  }))
  showPrivacyBanner.value = false
}
</script>

<style>
/* Global transition */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* ============================================
   MOBILE RESPONSIVE — Global overrides
   ============================================ */
@media (max-width: 1024px) {
  /* Hide desktop sidebars on mobile */
  .sidebar,
  aside.sidebar,
  .worker-sidebar,
  aside.worker-sidebar {
    display: none !important;
  }

  /* Full width main on mobile (no sidebar) */
  .app-layout {
    display: flex;
    flex-direction: column;
  }

  .app-main {
    width: 100% !important;
    margin-left: 0 !important;
  }

  /* Add padding at bottom for the bottom nav bar */
  .app-content {
    padding-bottom: calc(64px + env(safe-area-inset-bottom, 0px)) !important;
  }

  /* Prevent horizontal overflow on mobile */
  #crt-app,
  body,
  html {
    overflow-x: hidden;
  }

  /* Safe area for top notch */
  .app-header,
  header.app-header {
    padding-top: env(safe-area-inset-top, 0);
  }
}

/* ============================================
   PREMIUM NAVIGATION LOADER
   ============================================ */
.nav-loader-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(var(--color-bg-rgb), 0.6);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  z-index: 999999;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: all; /* Blocks all interaction */
}

.nav-loader-content {
  text-align: center;
  animation: fadeInDown 0.4s ease;
}

.nav-loader-text {
  margin-top: 16px;
  font-weight: 600;
  letter-spacing: 0.5px;
  color: var(--color-primary);
  font-size: 0.95rem;
}

.premium-spinner {
  width: 48px;
  height: 48px;
  border: 3px solid rgba(var(--color-primary-rgb), 0.1);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s cubic-bezier(0.5, 0.1, 0.4, 0.9) infinite;
  margin: 0 auto;
  box-shadow: 0 0 20px rgba(var(--color-primary-rgb), 0.2);
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
