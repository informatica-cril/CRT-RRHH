import { defineStore } from 'pinia'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    darkMode: JSON.parse(localStorage.getItem('crt_darkmode') || 'false'),
    sidebarCollapsed: false
  }),

  actions: {
    toggleDarkMode() {
      this.darkMode = !this.darkMode
      localStorage.setItem('crt_darkmode', JSON.stringify(this.darkMode))
    },
    toggleSidebar() {
      this.sidebarCollapsed = !this.sidebarCollapsed
    }
  }
})
