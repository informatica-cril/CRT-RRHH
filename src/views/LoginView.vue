<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-logo">
        <img src="/assets/crt-logo.png" alt="CRT" style="width:220px;height:auto;margin:0 auto 12px;" />
        <h1>{{ t('app_name') }}</h1>
        <p>{{ t('login_subtitle') }}</p>
      </div>

      <!-- Role Toggle -->
      <div style="display:flex;justify-content:center;margin-bottom:20px;">
        <div style="display:flex;background:var(--color-bg);border-radius:12px;padding:3px;gap:2px;width:100%;">
          <button 
            style="flex:1;padding:8px 16px;border:none;border-radius:10px;cursor:pointer;font-weight:600;font-size:0.85rem;transition:all 0.2s;"
            :style="loginMode === 'admin' ? 'background:var(--color-primary);color:#fff;' : 'background:transparent;color:var(--color-text-secondary);'"
            @click="loginMode = 'admin'; resetForm()">
            🛡️ Administrador
          </button>
          <button 
            style="flex:1;padding:8px 16px;border:none;border-radius:10px;cursor:pointer;font-weight:600;font-size:0.85rem;transition:all 0.2s;"
            :style="loginMode === 'worker' ? 'background:var(--color-accent);color:#fff;' : 'background:transparent;color:var(--color-text-secondary);'"
            @click="loginMode = 'worker'; resetForm()">
            👤 Treballador
          </button>
        </div>
      </div>

      <!-- Login Error -->
      <div v-if="authStore.loginError" class="login-error">
        {{ authStore.loginError }}
      </div>

      <!-- Auth Error (Session Expired) -->
      <div v-if="sessionError" class="login-error" style="background:rgba(214,158,46,0.1);color:var(--color-warning);">
        {{ sessionError }}
      </div>

      <!-- Password Reset Success -->
      <div v-if="resetSuccess" style="background:rgba(76,175,80,0.1);color:var(--color-success);padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:0.85rem;">
        ✓ {{ resetSuccess }}
      </div>

      <!-- Login Form -->
      <form v-if="!showResetForm" class="login-form" @submit.prevent="handleLogin" id="login-form">
        <div class="form-group">
          <label class="form-label" for="login-email">{{ t('login_email') }}</label>
          <input class="form-input" id="login-email" type="email" v-model="email" required autocomplete="email" 
            :placeholder="loginMode === 'admin' ? 'admin@crtbcn.cat' : 'nom@crtbcn.cat'" />
        </div>
        <div class="form-group">
          <label class="form-label" for="login-password">{{ t('login_password') }}</label>
          <input class="form-input" id="login-password" type="password" v-model="password" required autocomplete="current-password" placeholder="••••••" />
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px;">
          <input id="login-remember" type="checkbox" v-model="rememberPassword" style="width:auto;" />
          <label class="form-label" for="login-remember" style="margin:0;cursor:pointer;">{{ t('login_remember') }}</label>
        </div>
        <button class="btn btn-primary btn-lg" type="submit" id="btn-login" :disabled="loading"
          :style="loginMode === 'worker' ? 'background:var(--color-accent);' : ''">
          <span v-show="loading" class="spinner"></span>
          <span v-show="!loading">{{ t('login_button') }}</span>
        </button>
        <div style="text-align:center;margin-top:12px;">
          <button type="button" style="background:none;border:none;color:var(--color-primary);cursor:pointer;font-size:0.82rem;text-decoration:underline;" 
            @click="showResetForm = true; resetSuccess = ''">
            He oblidat la contrasenya
          </button>
        </div>
      </form>

      <!-- Password Reset Form -->
      <form v-else class="login-form" @submit.prevent="handleReset" id="reset-form">
        <div style="text-align:center;margin-bottom:12px;">
          <div style="font-size:2rem;margin-bottom:8px;">🔑</div>
          <p style="font-weight:600;margin-bottom:4px;">Recuperar contrasenya</p>
          <p class="text-small text-muted">Introduïu el correu electrònic del vostre compte</p>
        </div>
        <div class="form-group">
          <label class="form-label" for="reset-email">Correu electrònic</label>
          <input class="form-input" id="reset-email" type="email" v-model="resetEmail" required placeholder="nom@crtbcn.cat" />
        </div>
        <div v-if="resetError" style="background:rgba(239,68,68,0.1);color:var(--color-danger);padding:8px 12px;border-radius:8px;margin-bottom:12px;font-size:0.82rem;">
          {{ resetError }}
        </div>
        <button class="btn btn-primary btn-lg" type="submit" :disabled="loadingReset">
          <span v-if="loadingReset" class="spinner"></span>
          <span v-else>Enviar nova contrasenya</span>
        </button>
        <div style="text-align:center;margin-top:12px;">
          <button type="button" style="background:none;border:none;color:var(--color-primary);cursor:pointer;font-size:0.82rem;text-decoration:underline;" 
            @click="showResetForm = false; resetError = ''">
            ← Tornar a iniciar sessió
          </button>
        </div>
      </form>

      <p class="login-privacy">{{ t('login_privacy') }}</p>

      <!-- Language selector on login -->
      <div style="display: flex; justify-content: center; margin-top: 16px;">
        <div class="lang-selector">
          <button v-for="lang in locales" :key="lang" class="lang-btn" 
            :class="{ active: currentLocale === lang }" @click="setLocale(lang)">
            {{ lang.toUpperCase() }}
          </button>
        </div>
      </div>

      <div class="rdl-notice" style="margin-top: 16px;">
        {{ t('rdl_notice') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { i18n } from '../i18n'

const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const t = (key) => i18n.t(key)

const loginMode = ref('admin')
const email = ref('')
const password = ref('')
const loading = ref(false)

// Remember password
const REMEMBER_KEY = 'remembered_credentials'
const rememberPassword = ref(false)
const remembered = JSON.parse(window.localStorage.getItem(REMEMBER_KEY) || 'null')
if (remembered) {
  email.value = remembered.email || ''
  password.value = remembered.password || ''
  rememberPassword.value = true
}
const locales = i18n.availableLocales
const currentLocale = computed(() => i18n.locale)

// Password reset
const showResetForm = ref(false)
const resetEmail = ref('')
const resetSuccess = ref('')
const resetError = ref('')
const loadingReset = ref(false)

const sessionError = ref(window.localStorage.getItem('auth_error'))
// Clear it after reading so it doesn't persist forever
if (sessionError.value) {
  window.localStorage.removeItem('auth_error')
  setTimeout(() => { sessionError.value = '' }, 8000)
}

function resetForm() {
  email.value = ''
  password.value = ''
  authStore.loginError = null
  resetSuccess.value = ''
  resetError.value = ''
  sessionError.value = ''
}

function setLocale(lang) { i18n.locale = lang }

async function handleLogin() {
  loading.value = true
  const success = await authStore.login(email.value, password.value)
  loading.value = false
  if (success) {
    if (rememberPassword.value) {
      window.localStorage.setItem(REMEMBER_KEY, JSON.stringify({ email: email.value, password: password.value }))
    } else {
      window.localStorage.removeItem(REMEMBER_KEY)
    }
    // Torna a la pàgina on anava (sessió caducada o enllaç directe); només rutes internes.
    const desti = String(route.query.redirect || '')
    if (desti.startsWith('/') && !desti.startsWith('//') && !desti.startsWith('/login')) {
      router.push(desti)
    } else if (authStore.isWorker) {
      router.push('/worker')
    } else {
      router.push('/')
    }
  }
}

async function handleReset() {
  loadingReset.value = true
  resetError.value = ''
  
  const result = await authStore.requestPasswordReset(resetEmail.value)
  loadingReset.value = false
  
  if (result.success) {
    resetSuccess.value = result.message
  } else {
    resetError.value = result.message
  }
}
</script>
