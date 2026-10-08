import api, { apiFetchRaw } from './apiClient'

// ============================================================
// CRT — Database Service (API Mode)
// Strictly uses the Laravel Backend via apiClient.js
// ============================================================

const db = {
  api,
  // ── Users ──
  get users() { console.warn('[DB] Sync access to users is deprecated in API mode. Use fetchUsers().'); return [] },
  async fetchUsers() { return api.get('/v1/users') },
  getUser: (id) => api.get(`/v1/users/${id}`),
  addUser: (user) => api.post('/v1/users', user),
  addUsers: (users) => api.post('/v1/users/bulk', { users }),
  updateUser: (user) => api.put(`/v1/users/${user.id}`, user),
  getSpecialties: () => api.get('/v1/specialties'),
  getUsers: () => api.get('/v1/users'),
  // Aprovisionament de dispositiu corporatiu (Hexnode): clau → token HMAC
  deviceEnroll: (clau) => api.post('/v1/device/enroll', { clau }),
  // Informe empíric del pilot per a la validació del radi (EIPD §6.1.bis) — admin
  getPilotRadi: () => api.get('/v1/geolocation/pilot-radi'),
  getUsersBulk: () => api.get('/v1/users/bulk-index'),
  getWorkerBulkInfo: () => api.get('/v1/users/worker-bulk-info'),
  acceptGeoConsent: () => api.post('/v1/auth/geo-consent'),

  // ── Work Schedules ──
  getWorkSchedules: () => api.get('/v1/work-schedules'),
  getWorkSchedule: (id) => api.get(`/v1/work-schedules/${id}`),
  addWorkSchedule: (s) => api.post('/v1/work-schedules', s),
  updateWorkSchedule: (s) => api.put(`/v1/work-schedules/${s.id}`, s),

  // ── Work Logs ──
  // mes (AAAA-MM) opcional: el mes sencer. Sense, els 500 últims (comportament de sempre).
  getWorkLogs: (mes) => api.get('/v1/work-logs' + (mes ? `?mes=${mes}` : '')),
  getWorkLogsByUser: (userId) => api.get(`/v1/work-logs/user/${userId}`),
  addWorkLog: (log) => api.post('/v1/work-logs', log),
  updateWorkLog: (log) => api.put(`/v1/work-logs/${log.id}`, log),
  aprovaWorkLogs: (ids) => api.post('/v1/work-logs/aprova-seleccionats', { ids }),

  // ── Location Tracking ──

  // ── Absence Types ──
  getAbsenceTypes: () => api.get('/v1/absence-types'),
  addAbsenceType: (type) => api.post('/v1/absence-types', type),
  updateAbsenceType: (type) => api.put(`/v1/absence-types/${type.id}`, type),
  deleteAbsenceType: (id) => api.delete(`/v1/absence-types/${id}`),

  // ── Absences ──
  getAbsences: () => api.get('/v1/absences'),
  getAbsencesByUser: (userId) => api.get(`/v1/absences/user/${userId}`),
  addAbsence: (absence) => api.post('/v1/absences', absence),
  addAbsences: (absences) => api.post('/v1/absences/bulk', { absences }),
  updateAbsence: (absence) => api.put(`/v1/absences/${absence.id}`, absence),
  getUsedDaysByType: (userId, typeId, year) => api.get(`/v1/absences/used-days/${userId}/${typeId}/${year}`).then(r => r.used_days),
  // Part mèdic / justificant de l'absència (mateix mecanisme de pujada que la resta de l'app)
  uploadAbsenceJustificant: (absenceId, file) => {
    const fd = new FormData()
    fd.append('justificant', file)
    return api.upload(`/v1/absences/${absenceId}/justificant`, fd)
  },
  async downloadAbsenceJustificant(absenceId, nom) {
    const resp = await apiFetchRaw(`/v1/absences/${absenceId}/justificant`)
    if (!resp.ok) throw new Error('No s\'ha pogut baixar el justificant.')
    const blob = await resp.blob()
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = nom || 'justificant'
    a.click()
    URL.revokeObjectURL(a.href)
  },
  getAbsenceBalance: (userId, typeId, year) => api.get(`/v1/absences/used-days/${userId}/${typeId}/${year}`),

  // ── Excedencias ──
  getExcedencias: () => api.get('/v1/excedencias'),
  getExcedenciaTypes: () => api.get('/v1/excedencias/types'),
  getExcedenciasByUser: (userId) => api.get(`/v1/excedencias/user/${userId}`),
  addExcedencia: (exc) => api.post('/v1/excedencias', exc),
  updateExcedencia: (exc) => api.put(`/v1/excedencias/${exc.id}`, exc),

  // ── Authorization Codes ──
  getAuthCodes: () => api.get('/v1/auth-codes'),
  getComplementaryCap: (userId, validFrom) => api.get(`/v1/auth-codes/complementary-cap?user_id=${userId}&valid_from=${encodeURIComponent(validFrom)}`),
  addAuthCode: (code) => api.post('/v1/auth-codes', code),
  revokeAuthCode: (id) => api.post(`/v1/auth-codes/${id}/revoke`),
  renewAuthCode: (id, payload) => api.post(`/v1/auth-codes/${id}/renew`, payload),
  getMyAuthCodes: () => api.get('/v1/auth-codes/my'),
  validateAuthCode: (codeStr) => api.post('/v1/auth-codes/validate', { code: codeStr }),
  useAuthCode: (codeStr) => api.post('/v1/auth-codes/use', { code: codeStr }),

  // ── Zones (NEW) ──
  getZones: () => api.get('/v1/zones'),
  getZone: (id) => api.get(`/v1/zones/${id}`),
  addZone: (z) => api.post('/v1/zones', z),
  updateZone: (z) => api.put(`/v1/zones/${z.id}`, z),
  deleteZone: (id) => api.delete(`/v1/zones/${id}`),
  assignZone: (zoneId, userId, from, to) => api.post(`/v1/zones/${zoneId}/assign`, { user_id: userId, valid_from: from, valid_to: to }),
  removeZoneWorker: (zoneId, userId) => api.post(`/v1/zones/${zoneId}/remove-worker`, { user_id: userId }),

  // ── Work Locations (Points) (NEW) ──
  getWorkLocations: () => api.get('/v1/work-locations'),
  getWorkLocation: (id) => api.get(`/v1/work-locations/${id}`),
  addWorkLocation: (l) => api.post('/v1/work-locations', l),
  updateWorkLocation: (l) => api.put(`/v1/work-locations/${l.id}`, l),
  deleteWorkLocation: (id) => api.delete(`/v1/work-locations/${id}`),
  assignWorkLocation: (locId, userId, from, to) => api.post(`/v1/work-locations/${locId}/assign`, { user_id: userId, valid_from: from, valid_to: to }),
  removeWorkLocationWorker: (locId, userId) => api.post(`/v1/work-locations/${locId}/remove-worker`, { user_id: userId }),

  // ── Ambulatory Centers (NEW) ──
  getAmbulatoryCenters: () => api.get('/v1/ambulatory-centers'),
  assignAmbulatoryCenter: (centerId, userId, from, to) => api.post(`/v1/ambulatory-centers/${centerId}/assign`, { user_id: userId, valid_from: from, valid_to: to }),
  removeAmbulatoryCenterWorker: (centerId, userId) => api.post(`/v1/ambulatory-centers/${centerId}/remove-worker`, { user_id: userId }),

  // ── Compatibility Shims (LEGACY - Mapping to new endpoints) ──
  getCpAssignments: (userId) => userId ? api.get(`/v1/geolocation/municipal-assignment/${userId}`) : api.get('/v1/zones'),
  getActiveCpAssignment: (userId) => api.get(`/v1/cp-assignments/active/${userId}`), // Calls the shim in api.php
  addCpAssignment: (a) => api.post('/v1/zones', a), // Mocking
  updateCpAssignment: (a) => api.put(`/v1/zones/${a.id}`, a),
  deleteCpAssignment: (id) => api.delete(`/v1/zones/${id}`),

  getAmbulatoryPoints: (userId) => api.get(`/v1/geolocation/ambulatory-points/${userId}`),
  getActiveMunicipalAssignment: (userId) => api.get(`/v1/municipal-assignments/active/${userId}`), // Calls shim
  addMunicipalAssignment: (a) => api.post('/v1/zones', a),
  updateMunicipalAssignment: (a) => api.put(`/v1/zones/${a.id}`, a),
  deleteMunicipalAssignment: (id) => api.delete(`/v1/zones/${id}`),
  
  addAmbulatoryPoint: (p) => api.post('/v1/work-locations', p),
  deleteAmbulatoryPoint: (id) => api.delete(`/v1/work-locations/${id}`),

  // ── Geo Zone Alerts ──
  getGeoZoneAlerts: () => api.get('/v1/geo-zone-alerts'),
  addGeoZoneAlert: (alert) => api.post('/v1/geo-zone-alerts', alert),
  getUnacknowledgedGeoAlerts: (userId, role) => api.get(`/v1/geo-zone-alerts/unacknowledged?user_id=${userId || ''}&role=${role || ''}`),
  acknowledgeGeoAlert: (id, role) => api.post(`/v1/geo-zone-alerts/${id}/acknowledge`, { role }),

  // ── Documents ──
  getDocuments: () => api.get('/v1/documents'),
  getDocument: (id) => api.get(`/v1/documents/${id}`),
  addDocument: (doc) => api.post('/v1/documents', doc),
  updateDocument: (doc) => api.put(`/v1/documents/${doc.id}`, doc),
  deleteDocument: (id) => api.delete(`/v1/documents/${id}`),
  getDocumentsForUser: (userId) => api.get(`/v1/documents/user/${userId}`),
  getWorkerPayrolls: (userId) => api.get(`/v1/payroll-files/worker/${userId}`).then(res => res.map(p => ({...p, pdf_data: p.payroll_base64 || p.pdf_data}))),
  // El llistat NO porta el PDF (petava el memory_limit del servidor amb tota la plantilla).
  getPayrolls: () => api.get('/v1/payroll-files'),
  // PDF d'una nòmina concreta, a demanda (obrir / descarregar / imprimir).
  getPayroll: (id) => api.get(`/v1/payroll-files/${id}`).then(p => ({...p, pdf_data: p.payroll_base64 || p.pdf_data})),
  viewPayroll: (id) => api.post(`/v1/payroll-files/${id}/view`),
  signPayroll: (id, hash) => api.post(`/v1/payroll-files/${id}/sign`, { signature_hash: hash }),
  addPayroll: (p) => api.post('/v1/payroll-files', p),
  addPayrollBulk: (payrolls) => api.post('/v1/payroll-files/bulk', { payrolls }),
  importaPortalAntic: (nomines) => api.post('/v1/payroll-files/importa-portal-antic', { nomines }),
  deletePayroll: (id) => api.delete(`/v1/payroll-files/${id}`),

  // ── Document Signatures ──
  getDocSignatures: (docId) => api.get(`/v1/document-signatures/user/${docId}`).catch(() => []),
  getDocSignaturesByUser: (userId) => api.get(`/v1/document-signatures/user/${userId}`).catch(() => []),
  getDocSignature: async (docId, userId) => {
    const sigs = await api.get(`/v1/document-signatures/user/${userId}`).catch(() => [])
    return sigs.find(s => s.document_id === docId) || null
  },
  addDocSignature: (sig) => api.post('/v1/document-signatures', sig),
  updateDocSignature: (sig) => api.put(`/v1/document-signatures/${sig.id}`, sig),
  getUnsignedUrgentDocs: (userId) => api.get(`/v1/document-signatures/urgent-unsigned/${userId}`),
  getPendingSignatures: (docId) => api.get(`/v1/documents/${docId}/pending-signatures`),

  // ── Onboarding ──
  getOnboardingProfiles: () => api.get('/v1/onboarding/profiles'),
  getOnboardingProfile: (id) => api.get(`/v1/onboarding/profiles/${id}`),
  addOnboardingProfile: (p) => api.post('/v1/onboarding/profiles', p),
  updateOnboardingProfile: (p) => api.put(`/v1/onboarding/profiles/${p.id}`, p),
  deleteOnboardingProfile: (id) => api.delete(`/v1/onboarding/profiles/${id}`),
  // L'alta pròpia en una sola crida: el treballador no té permís per llegir ni el
  // catàleg de perfils ni el de documents, i abans l'assistent li'ls demanava.
  getMyOnboarding: () => api.get('/v1/onboarding/me'),
  getOnboardingStatus: (userId) => api.get(`/v1/onboarding/status/${userId}`),
  initOnboardingStatus: (userId, profileId) => api.post('/v1/onboarding/init', { user_id: userId, profile_id: profileId }),
  // La via per identificador és de gestió; la via per usuari l'obre el titular i també
  // la travessa gestió. Com que l'estat d'un treballador SEMPRE té identificador, la
  // primera branca se l'enduia sempre i li tornava 403: no podia desar la seva pròpia alta.
  updateOnboardingStatus: (status, userId_fallback) => {
    const uid = status.user_id || userId_fallback
    if (uid) {
      return api.put(`/v1/onboarding/status-by-user/${uid}`, status)
    }
    return api.put(`/v1/onboarding/status/${status.id}`, status)
  },
  completeOnboarding: (userId) => api.post(`/v1/onboarding/complete/${userId}`),
  getAllOnboardingStatuses: () => api.get('/v1/onboarding/all-statuses'),

  // ── Audit Logs ──
  addAuditLog: (log) => api.post('/v1/audit-logs', log),

  // ── Holidays ──
  getHolidays: () => api.get('/v1/holidays'),
  isHoliday: async (dateStr) => {
    const holidays = await api.get('/v1/holidays').catch(() => [])
    return holidays.some(h => h.date === dateStr)
  },

  // ── Chat ──
  getChatSettings: () => api.get('/v1/chat/settings'),
  saveChatSettings: (s) => api.post('/v1/chat/settings', s),
  hasChatPolicyAccepted: (userId) => api.get(`/v1/chat/policy-status/${userId}`).then(r => r.accepted),
  acceptChatPolicy: (userId) => api.post(`/v1/chat/accept-policy`, { user_id: userId }),
  getChatConversations: (userId) => api.get(`/v1/chat/conversations?user_id=${userId}`),
  getAllChatConversations: () => api.get('/v1/chat/conversations/all'),
  getChatConversation: (id) => api.get(`/v1/chat/conversations/${id}`),
  getChatMessages: (convId) => api.get(`/v1/chat/conversations/${convId}/messages`),
  addChatMessage: (msg) => api.post('/v1/chat/messages', msg),
  getUnreadCount: (convId, userId) => api.get(`/v1/chat/conversations/${convId}/unread/${userId}`).then(r => r.count),
  getLastMessage: (convId) => api.get(`/v1/chat/conversations/${convId}/last-message`),
  markMessagesRead: (convId, userId) => api.post(`/v1/chat/conversations/${convId}/read`, { user_id: userId }),
  getOrCreateDm: (u1, u2) => api.post('/v1/chat/conversations/dm', { user1: u1, user2: u2 }),
  addChatConversation: (conv) => api.post('/v1/chat/conversations', conv),
  searchMessages: (q) => api.get(`/v1/chat/search?q=${encodeURIComponent(q)}`),
  getChatAlerts: () => api.get('/v1/chat/alerts'),
  getUnreviewedAlerts: () => api.get('/v1/chat/alerts/unreviewed'),
  reviewAlert: (id, userId) => api.post(`/v1/chat/alerts/${id}/review`, { user_id: userId }),
  getTotalUnreadCount: (userId) => api.get(`/v1/chat/unread-count/${userId}`).then(r => r.count),

  // ── Presence ──
  setUserPresence: (userId, status) => api.post('/v1/chat/presence', { user_id: userId, status }),
  getUserPresence: (userId) => api.get(`/v1/chat/presence/${userId}`),
  // Presència de tothom en una crida: abans se'n feia una per persona a cada refresc.
  getAllPresence: () => api.get('/v1/chat/presence'),
  heartbeat: (userId) => api.post('/v1/chat/heartbeat', { user_id: userId }),

  // ── Password Change & Reset ──
  changePassword: (currentPassword, newPassword) => api.post('/v1/auth/change-password', {
    current_password: currentPassword,
    password: newPassword,
    password_confirmation: newPassword
  }),
  resetPassword: (email) => api.post('/v1/auth/reset-password', { email }),

  // ── Generate Random Password ──
  generateRandomPassword: (length) => api.post('/v1/auth/generate-random-password', { length }),
  generateRandomPasswordSync: (length = 10) => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*'
    let result = ''
    for (let i = 0; i < length; i++) result += chars.charAt(Math.floor(Math.random() * chars.length))
    return result
  },

  // ── Work Log Segments (tramos segmentados) ──
  getWorkLogSegments: (workLogId) => api.get(`/v1/work-logs/${workLogId}/segments`),
  createWorkLogSegments: (workLogId, segments) => api.post(`/v1/work-logs/${workLogId}/segments`, { segments }),
  updateWorkLogSegment: (workLogId, segmentId, data) => api.put(`/v1/work-logs/${workLogId}/segments/${segmentId}`, data),
  rejectWorkLogSegment: (workLogId, segmentId, reason, faultClau) => api.post(`/v1/work-logs/${workLogId}/segments/${segmentId}/reject`, { rejection_reason: reason, fault_type_clau: faultClau }),
  disciplinaryFaultTypes: () => api.get('/v1/disciplinary/fault-types'),
  disciplinarySuggestions: () => api.get('/v1/disciplinary/suggestions'),
  getConciliacio: () => api.get('/v1/conciliacio'),
  getMyDisciplinaryNotifications: () => api.get('/v1/disciplinary/my-notifications'),
  getHoursControl: (year) => api.get('/v1/auth-codes/hours-control' + (year ? ('?year=' + year) : '')),
  getOvertimeRequests: (status) => api.get('/v1/overtime-requests' + (status ? ('?status=' + status) : '')),
  authorizeOvertime: (id) => api.post(`/v1/overtime-requests/${id}/authorize`),
  denyOvertime: (id, reason) => api.post(`/v1/overtime-requests/${id}/deny`, { reason }),
  // Audiència prèvia (EIPD C6): al·legació del titular sobre un tram pendent
  sendSegmentAllegation: (workLogId, segmentId, text) => api.post(`/v1/work-logs/${workLogId}/segments/${segmentId}/allegation`, { allegation: text }),
  approveWorkLogSegment: (workLogId, segmentId) => api.post(`/v1/work-logs/${workLogId}/segments/${segmentId}/approve`),

  // ── Work Log Detail (vista con tramos) ──
  getWorkLogDetail: (workLogId) => api.get(`/v1/work-logs/${workLogId}/detail`),

  // ── Work Log Modifications (trazabilidad) ──
  getWorkLogModifications: (workLogId) => api.get(`/v1/work-logs/${workLogId}/modifications`),

  // ── Break Settings ──
  getMailSettings: () => api.get('/v1/mail-settings'),
  updateMailSettings: (data) => api.put('/v1/mail-settings', data),
  sendTestMail: (to) => api.post('/v1/mail-settings/test', { to }),

  getBreakSettings: () => api.get('/v1/break-settings'),
  updateBreakSettings: (data) => api.put('/v1/break-settings', data),
  getBreakWorkers: () => api.get('/v1/break-settings/workers'),
  updateBreakWorker: (userId, data) => api.put(`/v1/break-settings/workers/${userId}`, data),

  // ── Break Management ──
  startBreak: (workLogId, payload) => api.post(`/v1/work-logs/${workLogId}/start-break`, payload || {}),
  completeBreak: (workLogId, payload) => api.post(`/v1/work-logs/${workLogId}/complete-break`, payload || {}),
  skipBreak: (workLogId, reason) => api.post(`/v1/work-logs/${workLogId}/skip-break`, { reason }),

  // ── Work Log Alerts ──
  getPendingAlerts: (userId) => api.get(`/v1/work-log-alerts/pending/${userId}`),
  dismissAlert: (alertId) => api.post(`/v1/work-log-alerts/${alertId}/dismiss`),

  // ── Reset (Not used in API mode) ──
  reset() { console.warn('[DB] Reset is not available in API mode. Use backend database tools.') },
  initDB() { console.log('[DB] Running in API Mode (MySQL Backend). Local initialization skipped.') }
}

export { db }
export default db
