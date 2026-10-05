// Catàleg de pantalles per al cercador de la capçalera. Les condicions de rol són les MATEIXES que les
// del menú lateral (Sidebar / WorkerSidebar): el cercador no ha d'oferir res que el rol no pugui obrir.
// `claus` porta sinònims (també en castellà) perquè «nóminas» o «fichajes» trobin la pantalla.

const GESTIO = [
  { nom: 'Tauler', ruta: '/', icona: '🏠', claus: 'inici dashboard panel inicio' },
  { nom: 'Safata de pendents', ruta: '/safata', icona: '📥', claus: 'pendents bandeja pendientes', pot: a => a.isStaff },
  { nom: 'Registres horaris', ruta: '/work-logs', icona: '⏱', claus: 'fitxatges jornada fichajes registros horas' },
  { nom: "Control d'hores", ruta: '/hours-control', icona: '🕐', claus: 'complementaries extra horas control', pot: a => a.isStaff },
  { nom: 'Absències', ruta: '/absences', icona: '📋', claus: 'permisos vacances baixes ausencias vacaciones bajas' },
  { nom: 'Excedències', ruta: '/excedencies', icona: '📄', claus: 'excedencias' },
  { nom: "Codis d'autorització", ruta: '/auth-codes', icona: '🔑', claus: 'hores extra codigos autorizacion' },
  { nom: 'Informes', ruta: '/reports', icona: '📊', claus: 'reports informes estadistiques' },
  { nom: 'Treballadors', ruta: '/employees', icona: '👥', claus: 'personal plantilla trabajadores empleados fitxa', pot: a => a.canManageStaff },
  { nom: 'Disponibilitat', ruta: '/disponibilitat', icona: '📅', claus: 'disponibilidad setmanal', pot: a => a.canManageStaff },
  { nom: 'Nòmines', ruta: '/payrolls-admin', icona: '💶', claus: 'nominas payroll sou sueldo', pot: a => a.isAdmin },
  { nom: 'Documentació', ruta: '/documents', icona: '📁', claus: 'documents documentos signatura', pot: a => a.isAdmin },
  { nom: "Comitè d'empresa", ruta: '/comite', icona: '🤝', claus: 'comite representants credit horari sindical', pot: a => a.canManageStaff },
  { nom: 'Representació legal', ruta: '/rlt', icona: '🏛️', claus: 'rlt representacion informes', pot: a => a.canManageStaff },
  { nom: 'Expedient · Evidències', ruta: '/expedient', icona: '🗂️', claus: 'expediente evidencias', pot: a => a.canManageStaff },
  { nom: 'Expedient · Procediments', ruta: '/disciplinary', icona: '⚖️', claus: 'disciplinari sancions procedimientos', pot: a => a.canManageStaff },
  { nom: 'Conciliació', ruta: '/conciliacio', icona: '🔗', claus: 'domi identitats conciliacion', pot: a => a.canManageStaff },
  { nom: 'Rendiment', ruta: '/rendiment', icona: '📈', claus: 'rendimiento indicadors', pot: a => a.canManageStaff },
  { nom: 'Compliment', ruta: '/compliance', icona: '✅', claus: 'cumplimiento politiques acusaments', pot: a => a.canManageStaff },
  { nom: 'Comunicació certificada', ruta: '/comunicacio-certificada', icona: '✉️', claus: 'burofax comunicacion certificada', pot: a => a.canManageStaff },
  { nom: 'Millores', ruta: '/millores', icona: '💡', claus: 'mejoras suggeriments', pot: a => a.canManageStaff },
  { nom: 'Configuració de permisos', ruta: '/permisos-config', icona: '⚙️', claus: 'tipus absencia permisos configuracion', pot: a => a.canManageStaff },
  { nom: 'Pausa obligatòria', ruta: '/break-settings', icona: '☕', claus: 'pausa descans descanso', pot: a => a.isAdmin },
  { nom: 'Segon factor', ruta: '/seguretat/segon-factor', icona: '🔐', claus: '2fa seguretat seguridad doble factor', pot: a => a.isAdmin },
  { nom: 'Accessos a apps', ruta: '/portal/accessos', icona: '🧩', claus: 'portal domi accesos aplicaciones', pot: a => a.canManageStaff },
  { nom: 'Integritat', ruta: '/integrity', icona: '🔏', claus: 'segell integridad', pot: a => a.canManageStaff && a.user?.role !== 'hr' },
  { nom: 'Auditoria', ruta: '/audit', icona: '🧾', claus: 'auditoria registre accions logs', pot: a => a.isAdmin },
  { nom: 'Xat (supervisió)', ruta: '/chat-admin', icona: '💬', claus: 'chat missatges supervision', pot: a => a.isAdmin },
  { nom: 'Configuració', ruta: '/settings', icona: '⚙️', claus: 'ajustos configuracion contrasenya' },
  { nom: 'Privadesa', ruta: '/privacy', icona: '🔒', claus: 'privacidad rgpd dades' },
]

const TREBALLADOR = [
  { nom: 'Fitxatge', ruta: '/worker', icona: '⏱', claus: 'fitxar jornada fichar entrada sortida' },
  { nom: 'Calendari i horari', ruta: '/worker/calendar', icona: '📅', claus: 'calendario horario setmana festius' },
  { nom: 'Permisos i absències', ruta: '/worker/absences', icona: '📋', claus: 'vacances permisos ausencias vacaciones' },
  { nom: 'Excedències', ruta: '/worker/excedencies', icona: '📄', claus: 'excedencias' },
  { nom: 'Historial de fitxatges', ruta: '/worker/history', icona: '🕐', claus: 'historial fichajes registros' },
  { nom: 'La meva safata', ruta: '/worker/safata', icona: '📥', claus: 'avisos pendents bandeja' },
  { nom: 'Documents', ruta: '/worker/documents', icona: '📁', claus: 'documentos signar firmar' },
  { nom: 'Nòmines', ruta: '/worker/payrolls', icona: '💶', claus: 'nominas sou sueldo' },
  { nom: 'Xat', ruta: '/worker/chat', icona: '💬', claus: 'chat missatges mensajes' },
  { nom: 'El meu rendiment', ruta: '/worker/rendiment', icona: '📈', claus: 'rendimiento' },
  { nom: 'Informació i polítiques', ruta: '/worker/compliance', icona: '✅', claus: 'politicas informacion' },
  { nom: 'Configuració', ruta: '/worker/settings', icona: '⚙️', claus: 'ajustos configuracion contrasenya' },
  { nom: 'Privadesa', ruta: '/privacy', icona: '🔒', claus: 'privacidad rgpd dades' },
]

export const normCerca = (t) => String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()

/** Pantalles que el rol pot obrir i que coincideixen amb el text. */
export function cercaPantalles(text, auth) {
  const q = normCerca(text.trim())
  if (!q) return []
  const llista = auth.isWorker ? TREBALLADOR : GESTIO.filter(p => !p.pot || p.pot(auth))
  return llista.filter(p => normCerca(p.nom + ' ' + p.claus).includes(q)).slice(0, 6)
}
