import { defineStore } from 'pinia'
import { db } from '../services/db'
import { auditLog } from '../services/audit'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    isAuthenticated: false,
    isInitialized: false,
    loginError: null
  }),

  getters: {
    userId: (state) => state.user?.id,
    isAdmin: (state) => state.user?.role === 'admin',
    isWorker: (state) => state.user?.role === 'worker',
    userRole: (state) => state.user?.role,
    userName: (state) => state.user?.name || '',
    userEmail: (state) => state.user?.email || ''
  },

  actions: {
    checkAuth() {
      return this.init()
    },

    async init() {
      // Skip if already initialized and authenticated
      if (this.isInitialized && this.isAuthenticated) return

      const token = localStorage.getItem('crt_api_token')
      if (token) {
        try {
          const freshUser = await db.getUser('me')
          if (freshUser && freshUser.active) {
            this.user = freshUser
            this.token = token
            this.isAuthenticated = true
          } else {
            this.logout()
          }
        } catch (e) {
          console.error('[AuthStore] Init failed:', e)
          // Don't logout on transient errors if we already had a session
          if (!this.isAuthenticated) {
            this.logout()
          }
        }
      }
      this.isInitialized = true
    },

    async login(email, password) {
      this.loginError = null
      try {
        const result = await db.api.login(email, password)
        if (result.token) {
          this.user = result.user
          this.token = result.token
          this.isAuthenticated = true
          auditLog(this.user.id, 'LOGIN', 'auth', this.user.id, `Inici de sessió: ${this.user.name}`)
          return true
        }
      } catch (e) {
        this.loginError = e.errors?.email?.[0] || e.message || 'Credencials incorrectes o compte inactiu'
        auditLog(null, 'LOGIN_FAILED', 'auth', null, `Email: ${email}`)
      }
      return false
    },

    async requestPasswordReset(email) {
      try {
        const result = await db.resetPassword(email)
        if (result.success) {
          auditLog(null, 'PASSWORD_RESET', 'auth', null, `Recuperació enviada a: ${email}`)
        }
        return result
      } catch (e) {
        // 422 = validation error (e.g. invalid email format) → show real error
        // Any other error (network, 500) → show generic success because the server
        // likely processed the request even if we didn't receive the response
        if (e.status === 422) {
          const msg = e.errors?.email?.[0] || 'Correu electrònic no vàlid'
          return { success: false, message: msg }
        }
        return {
          success: true,
          message: 'Si existeix un compte actiu amb aquest correu, rebràs la nova contrasenya en breu.'
        }
      }
    },

    async changePassword(oldPw, newPw) {
      if (!this.user) return { success: false, message: 'No autenticat' }
      try {
        const res = await db.changePassword(oldPw, newPw)
        this.user = await db.getUser(this.user.id)
        auditLog(this.user.id, 'CHANGE_PASSWORD', 'auth', this.user.id, `Contrasenya canviada`)
        return { success: true, message: res.message || 'Contrasenya actualitzada correctament' }
      } catch (e) {
        const msg = e.errors?.current_password?.[0] || e.errors?.password?.[0] || e.errors?.password_confirmation?.[0] || e.message || 'Error al canviar la contrasenya'
        return { success: false, message: msg }
      }
    },

    async logout() {
      if (this.user) auditLog(this.user.id, 'LOGOUT', 'auth', this.user.id, `Tancament de sessió: ${this.user.name}`)
      try {
        await db.api.logout()
      } finally {
        this.user = null
        this.token = null
        this.isAuthenticated = false
        localStorage.removeItem('crt_api_token')
      }
    }
  }
})
