/**
 * Traducciones en español.
 * Claves para el módulo de fichaje segmentado, pausa obligatoria y coordinación.
 */
export default {
    workLog: {
        segments: 'Tramos',
        inZone: 'Dentro de zona',
        outOfZone: 'Fuera de zona',
        inSchedule: 'Dentro de horario',
        outOfSchedule: 'Fuera de horario',
        complementaryMinutes: 'Horas complementarias (min)',
        breakRequired: 'Pausa obligatoria requerida',
        breakInProgress: 'Pausa en curso',
        detail: 'Detalle de fichaje',
        segmented: 'Segmentado',
        notSegmented: 'No segmentado',
        approve: 'Aprobar',
        reject: 'Rechazar',
        modify: 'Modificar',
        modifications: 'Trazabilidad de cambios',
        noModifications: 'No hay modificaciones registradas.',
    },
    coordinator: {
        title: 'Panel de coordinación',
        manageZones: 'Gestionar zonas',
        manageSchedules: 'Gestionar horarios',
        pendingReview: 'Pendientes de revisión',
        segmented: 'Segmentados',
        outOfZone: 'Fuera de zona',
        all: 'Todos',
    },
    alerts: {
        noClockIn: 'No has fichado la entrada',
        noClockOut: 'No has fichado la salida',
        breakRequired: 'Pausa obligatoria requerida',
        segmentRejected: 'Tramo rechazado',
        dismiss: 'Descartar',
    },
    break: {
        title: 'Pausa obligatoria',
        description: 'Has superado el umbral de horas seguidas. Debes hacer una pausa de {minutes} minutos.',
        remaining: 'Tiempo restante',
        skip: 'Omitir pausa',
        blocked: 'La aplicación está bloqueada durante la pausa.',
        completed: 'Pausa completada',
        settings: {
            title: 'Configuración de pausa obligatoria',
            enabled: 'Pausa obligatoria activada',
            thresholdHours: 'Umbral de horas seguidas',
            breakDuration: 'Duración de la pausa',
            autoStart: 'Inicio automático',
            gracePeriod: 'Período de gracia',
            save: 'Guardar configuración',
        },
    },
};
