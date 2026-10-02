import { defineStore } from 'pinia'
import { db } from '../services/db'
import { auditLog } from '../services/audit'
import { useAuthStore } from './auth'
import { checkUserLocation } from '../services/geolocation'
import { formatHM } from '../utils/formatHours'

export const useWorkLogStore = defineStore('workLog', {
  state: () => ({
    currentLog: null,
    // Mes (AAAA-MM) que mira la pantalla de registres; null = els últims (per defecte).
    mes: null,
    logs: [],
    elapsed: 0,
    timerInterval: null,
    isWorking: false,
    // Diferencia (ms) entre el rellotge del servidor i el del dispositiu. Si el
    // rellotge del treballador va desquadrat (passa sovint i no sempre es pot
    // arreglar), el comptador seguiria sent correcte igualment.
    clockOffsetMs: 0
  }),

  getters: {
    formattedElapsed: (state) => {
      const h = Math.floor(state.elapsed / 3600)
      const m = Math.floor((state.elapsed % 3600) / 60)
      const s = state.elapsed % 60
      return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
    }
  },

  actions: {
    async loadLogs() {
      const authStore = useAuthStore()
      if (!authStore.userId) return
      
      try {
        if (authStore.isStaff) {
          this.logs = await db.getWorkLogs(this.mes)
        } else {
          this.logs = await db.getWorkLogsByUser(authStore.userId)
        }
        // Check for active workday
        const today = new Date().toISOString().split('T')[0]
        const active = this.logs.find(l => l.user_id === authStore.userId && l.date && l.date.substring(0, 10) === today && !l.end_time)
        if (active) {
          this.currentLog = active
          this.isWorking = true
          this.startTimer()
        }
      } catch (e) {
        console.error('[WorkLogStore] loadLogs failed:', e)
      }
    },

    async startWorkday(lat, lng, justificacio = null, gpsError = null) {
      const authStore = useAuthStore()
      const now = new Date()

      try {
        const cpAssignment = await db.getActiveCpAssignment(authStore.userId)
        // Sense GPS (registre manual amb justificació): no es valida zona amb nulls —
        // el fichatge queda sense veredicte de posició, mai en contra del treballador.
        const hasCoords = lat != null && lng != null
        let geoResult = hasCoords
          ? await checkUserLocation(authStore.user, cpAssignment, lat, lng)
          : { valid: null, distance: null, zone: null }
        const isAmbulatoria = authStore.user?.work_type === 'AMBULATORIA'
        const postalCodes = (isAmbulatoria && hasCoords) ? [geoResult.zone] : (cpAssignment?.postal_codes || [authStore.user?.postal_code_assigned || '08001'])

        const logData = {
          user_id: authStore.userId,
          date: now.toISOString().split('T')[0],
          start_time: now.toISOString(),
          start_location_lat: lat,
          start_location_lng: lng,
          start_location_match: geoResult.valid,
          start_location_distance: geoResult.distance,
          assigned_postal_codes: postalCodes,
          assigned_postal_code: postalCodes[0],
          location_match: geoResult.valid,
          hour_status: 'in_progress',
          status: 'pending'
        }
        if (justificacio) logData.justificacio = justificacio
        if (!hasCoords) {
          logData.gps_error = gpsError || 'unavailable'
          const disp = localStorage.getItem('crt_disp')
          if (disp) logData.disp = disp
        }

        const log = await db.addWorkLog(logData)
        this.currentLog = log
        this.isWorking = true
        // log.start_time l'ha posat el servidor just ara: la diferència amb
        // Date.now() és (aprox.) el desquadre del rellotge del dispositiu.
        this.clockOffsetMs = log.start_time ? (new Date(log.start_time).getTime() - Date.now()) : 0
        this.startTimer()
        auditLog(authStore.userId, 'START_WORKDAY', 'work_log', log.id,
          `Inici jornada: ${now.toLocaleTimeString('ca-ES')} · ` + (hasCoords ? `match: ${geoResult.valid}` : 'registre manual sense GPS'))
        this.logs = await db.getWorkLogsByUser(authStore.userId)
      } catch (e) {
        console.error('[WorkLogStore] startWorkday failed:', e)
        throw e
      }
    },

    async endWorkday(lat, lng, justificacio = null, gpsError = null) {
      if (!this.currentLog) return
      const authStore = useAuthStore()
      const now = new Date()
      const start = new Date(this.currentLog.start_time)
      const totalHours = (now - start) / 3600000

      try {
        // Get schedule for this worker
        const schedule = await db.getWorkSchedule(authStore.user?.work_schedule_id || 1)
        const dayOfWeek = now.getDay()
        const todaySchedule = schedule?.days?.find(d => (d.day === dayOfWeek || d.day_num === dayOfWeek) && d.active)
        const scheduledHours = todaySchedule
          ? (() => {
              const [sh, sm] = (todaySchedule.start || '08:00').split(':').map(Number)
              const [eh, em] = (todaySchedule.end || '16:00').split(':').map(Number)
              return (eh * 60 + em - sh * 60 - sm) / 60
            })()
          : 8

        // Get active CP assignment
        const cpAssignment = await db.getActiveCpAssignment(authStore.userId)
        let geoResult = await checkUserLocation(authStore.user, cpAssignment, lat, lng)

        const isAmbulatory = authStore.user?.work_type === 'AMBULATORIA'
        const startInGeo = this.currentLog.start_location_match !== false
        const endInGeo = geoResult.valid
        
        // For Ambulatory, we prioritize the start location per user's "only at start" rule
        // For others, we consider it out of area if either start or end is invalid
        const inGeo = isAmbulatory ? startInGeo : (startInGeo && endInGeo)

        let validHours = 0
        let extraHoursUnauth = 0
        // Recover accumulated out-of-area hours from real-time tracking
        let hoursOutOfArea = Number(this.currentLog.hours_out_of_area || 0)

        if (totalHours < 0.008) { // Less than 30s
          validHours = 0; extraHoursUnauth = 0; hoursOutOfArea = 0;
        } else if (!inGeo && isAmbulatory) {
          // Ambulatory worker started outside zone — all hours are out-of-area, none count
          hoursOutOfArea = Math.round(totalHours * 100) / 100
          validHours = 0
        } else {
          // Calculation for Domiciliaria/Fisio: total minus already deducted hours
          const netHours = Math.max(0, totalHours - hoursOutOfArea)
          validHours = Math.min(netHours, scheduledHours)
          validHours = Math.round(validHours * 100) / 100
          extraHoursUnauth = Math.max(0, Math.round((netHours - scheduledHours) * 100) / 100)
          hoursOutOfArea = Math.round(hoursOutOfArea * 100) / 100
        }

        const hourStatus = (hoursOutOfArea > 0 || !inGeo) ? 'out_of_area' : extraHoursUnauth > 0 ? 'extra' : 'ok'

        const updatedData = {
          ...this.currentLog,
          end_time: now.toISOString(),
          end_location_lat: lat,
          end_location_lng: lng,
          end_location_match: endInGeo,
          end_location_distance: geoResult.distance,
          location_match: inGeo,
          total_hours_worked: validHours,
          hours_worked: validHours,
          hours_out_of_area: hoursOutOfArea,
          extra_hours_unauthorized: extraHoursUnauth,
          hour_status: hourStatus,
          status: 'pending',
          updated_at: now.toISOString()
        }
        if (justificacio) updatedData.justificacio = justificacio
        if (lat == null) {
          updatedData.gps_error = gpsError || 'unavailable'
          const disp = localStorage.getItem('crt_disp')
          if (disp) updatedData.disp = disp
        }

        await db.updateWorkLog(updatedData)
        auditLog(authStore.userId, 'END_WORKDAY', 'work_log', this.currentLog.id, `Fi jornada: total ${totalHours.toFixed(2)}h · status: ${hourStatus}`)

        this.stopTimer()
        this.isWorking = false
        this.currentLog = null
        this.elapsed = 0
        this.logs = await db.getWorkLogsByUser(authStore.userId)
      } catch (e) {
        console.error('[WorkLogStore] endWorkday failed:', e)
        throw e
      }
    },

    // Authorize extra hours using an admin-generated code
    async authorizeExtraHours(logId, code) {
      const authStore = useAuthStore()
      const log = this.logs.find(l => l.id === logId)
      if (!log) return { success: false, message: 'Registre no trobat' }

      try {
        const validation = await db.validateAuthCode(code)
        if (!validation.valid) {
          return { success: false, message: validation.reason || 'Codi invàlid' }
        }

        const authCode = validation.code
        if (authCode.user_id && authCode.user_id !== log.user_id) {
          return { success: false, message: 'Aquest codi no està assignat a aquest treballador' }
        }

        await db.useAuthCode(code)

        const codeMaxHours = Number(authCode.authorized_hours) || 0
        const extraPending = Number(log.extra_hours_unauthorized) || 0
        const hoursToAuthorize = Math.min(extraPending, codeMaxHours)
        const remainingUnauthorized = Math.round((extraPending - hoursToAuthorize) * 100) / 100

        log.authorized_extra_code = code
        log.extra_hours_authorized = Math.round((Number(log.extra_hours_authorized || 0) + hoursToAuthorize) * 100) / 100
        log.extra_hours_unauthorized = remainingUnauthorized
        log.updated_at = new Date().toISOString()

        await db.updateWorkLog({
          id: log.id,
          authorized_extra_code: log.authorized_extra_code,
          extra_hours_authorized: log.extra_hours_authorized,
          extra_hours_unauthorized: log.extra_hours_unauthorized
        })
        auditLog(authStore.userId, 'AUTHORIZE_EXTRA_HOURS', 'work_log', logId,
          `${hoursToAuthorize}h autoritzades amb codi ${code} (${remainingUnauthorized}h resten pendents)`)
        this.logs = await db.getWorkLogsByUser(authStore.userId)

        const baseMsg = `✓ ${formatHM(hoursToAuthorize)} extra autoritzades correctament.`
        const warningMsg = remainingUnauthorized > 0
          ? ` Només s'han actualitzat ${formatHM(hoursToAuthorize)} de les ${formatHM(extraPending)} acumulades. Per gestionar les ${formatHM(remainingUnauthorized)} restants, contacta amb el teu responsable.`
          : ''
        return { success: true, message: baseMsg + warningMsg, hasRemaining: remainingUnauthorized > 0 }
      } catch (e) {
        console.error('[WorkLogStore] authorizeExtraHours failed:', e)
        return { success: false, message: e.message || 'Error en l\'autorització' }
      }
    },

    startTimer() {
      this.stopTimer()
      if (this.currentLog && this.currentLog.start_time) {
        // start_time arriba en hora de Madrid directa (sense Z): el navegador ja
        // l'interpreta com a hora local seva correctament, sense forçar res.
        // Date.now() + clockOffsetMs corregeix si el rellotge del dispositiu va
        // desquadrat (veure clockOffsetMs).
        const start = new Date(this.currentLog.start_time)
        const diff = Math.floor(((Date.now() + this.clockOffsetMs) - start.getTime()) / 1000)
        // Prevent negative elapsed times due to clock skew
        this.elapsed = Math.max(0, diff)
      }
      this.timerInterval = setInterval(() => {
        if (this.currentLog && this.currentLog.start_time) {
          const start = new Date(this.currentLog.start_time).getTime()
          this.elapsed = Math.max(0, Math.floor(((Date.now() + this.clockOffsetMs) - start) / 1000))
        }
      }, 1000)
    },

    stopTimer() {
      if (this.timerInterval) { clearInterval(this.timerInterval); this.timerInterval = null }
    },

    async approveLog(logId) {
      const authStore = useAuthStore()
      const log = this.logs.find(l => l.id === logId)
      if (log) {
        try {
          const updates = { id: log.id, status: 'approved' }
          // Restoring out-of-area hours as valid when admin explicitly approves
          if ((log.hour_status === 'out_of_area' || log.hours_out_of_area > 0)) {
            const restored = Math.round((Number(log.total_hours_worked || 0) + Number(log.hours_out_of_area || 0)) * 100) / 100
            updates.total_hours_worked = restored
            updates.hours_worked = restored
            updates.hours_out_of_area = 0
            updates.hour_status = 'ok'
          }
          await db.updateWorkLog(updates)
          auditLog(authStore.userId, 'APPROVE_WORKLOG', 'work_log', logId, `Aprovat${updates.total_hours_worked != null ? ` (restaurades ${updates.total_hours_worked}h)` : ''}`)
          await this.loadLogs()
        } catch (e) { console.error(e) }
      }
    },

    async rejectLog(logId, reason) {
      const authStore = useAuthStore()
      const log = this.logs.find(l => l.id === logId)
      if (log) {
        try {
          log.status = 'rejected'
          log.rejection_reason = reason
          log.updated_at = new Date().toISOString()
          await db.updateWorkLog({ id: log.id, status: 'rejected', rejection_reason: reason })
          auditLog(authStore.userId, 'REJECT_WORKLOG', 'work_log', logId, `Rebutjat`)
          await this.loadLogs()
        } catch (e) { console.error(e) }
      }
    }
  }
})
