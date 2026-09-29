import { defineStore } from 'pinia'
import { db } from '../services/db'
import { useAuthStore } from './auth'

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    pendingWorkLogs: 0,
    pendingAbsences: 0,
    _interval: null,
  }),

  getters: {
    total: (state) => state.pendingWorkLogs + state.pendingAbsences,
    hasAny: (state) => state.pendingWorkLogs + state.pendingAbsences > 0,
  },

  actions: {
    async refresh() {
      const authStore = useAuthStore()
      if (!authStore.isAdmin) return
      try {
        const [workLogs, absences] = await Promise.all([
          db.getWorkLogs(),
          db.getAbsences(),
        ])
        this.pendingWorkLogs = workLogs.filter(l => l.status === 'pending').length
        // approved is boolean-cast in Laravel: null stays null, but check both null and undefined
        this.pendingAbsences = absences.filter(a => a.approved == null).length
      } catch (e) {
        console.error('[Notifications] refresh failed:', e)
      }
    },

    startPolling() {
      this.refresh()
      if (!this._interval) {
        this._interval = setInterval(() => this.refresh(), 60000)
      }
    },

    stopPolling() {
      if (this._interval) {
        clearInterval(this._interval)
        this._interval = null
      }
    },
  },
})
