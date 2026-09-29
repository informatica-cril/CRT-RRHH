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
    isHr: (state) => state.user?.role === 'hr',                 // Responsable RRHH
    isCoordinator: (state) => state.user?.role === 'coordinator',
    // Gestió de personal/disponibilitat/permisos: admin o RRHH (RRHH NO accedeix a nòmines/sistema).
    canManageStaff: (state) => state.user?.role === 'admin' || state.user?.role === 'hr',
    isStaff: (state) => ['admin', 'hr', 'coordinator'].includes(state.user?.role),
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
        return { success: false, message: e.message || 'Error en la recuperació' }
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
