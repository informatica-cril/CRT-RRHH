<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-logo">
        <img src="/assets/crt-logo.png" alt="CRT" style="width:220px;height:auto;margin:0 auto 12px;" />
        <h1>Cal canviar la contrasenya</h1>
        <p>Per seguretat, cal establir una contrasenya nova abans de continuar.</p>
      </div>

      <div v-if="result && !result.success" class="login-error">
        {{ result.message }}
      </div>

      <form class="login-form" @submit.prevent="submit">
        <div class="form-group">
          <label class="form-label" for="fpc-current">Contrasenya actual</label>
          <input class="form-input" id="fpc-current" type="password" v-model="currentPw" required autocomplete="current-password" />
        </div>
        <div class="form-group">
          <label class="form-label" for="fpc-new">Contrasenya nova</label>
          <input class="form-input" id="fpc-new" type="password" v-model="newPw" required autocomplete="new-password" />
          <p class="text-small text-muted" style="margin-top:4px;">
            Mínim 12 caràcters, amb majúscula, minúscula, xifra i símbol.
          </p>
        </div>
        <div class="form-group">
          <label class="form-label" for="fpc-confirm">Repeteix la contrasenya nova</label>
          <input class="form-input" id="fpc-confirm" type="password" v-model="confirmPw" required autocomplete="new-password" />
        </div>
        <button class="btn btn-primary btn-lg" type="submit" :disabled="loading">
          <span v-show="loading" class="spinner"></span>
          <span v-show="!loading">Canviar contrasenya</span>
        </button>
      </form>

      <div style="text-align:center;margin-top:16px;">
        <button type="button" style="background:none;border:none;color:var(--color-text-secondary);cursor:pointer;font-size:0.82rem;text-decoration:underline;"
          @click="doLogout">
          Tancar sessió
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
const router = useRouter()

const currentPw = ref('')
const newPw = ref('')
const confirmPw = ref('')
const loading = ref(false)
const result = ref(null)

async function submit() {
  result.value = null
  if (newPw.value !== confirmPw.value) {
    result.value = { success: false, message: 'Les contrasenyes noves no coincideixen.' }
    return
  }
  loading.value = true
  result.value = await authStore.changePassword(currentPw.value, newPw.value)
  loading.value = false
  if (result.value.success) {
    if (authStore.isWorker) {
      router.push({ name: 'worker-dashboard' })
    } else {
      router.push({ name: 'dashboard' })
    }
  }
}

async function doLogout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
