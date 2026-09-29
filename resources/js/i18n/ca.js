/**
 * Traducciones en catalán (idioma por defecto).
 * Claves para el módulo de fichaje segmentado, pausa obligatoria y coordinación.
 */
export default {
    workLog: {
        segments: 'Trams',
        inZone: 'Dins de zona',
        outOfZone: 'Fora de zona',
        inSchedule: 'Dins d\'horari',
        outOfSchedule: 'Fora d\'horari',
        complementaryMinutes: 'Hores complementàries (min)',
        breakRequired: 'Pausa obligatòria requerida',
        breakInProgress: 'Pausa en curs',
        detail: 'Detall de fichatge',
        segmented: 'Segmentat',
        notSegmented: 'No segmentat',
        approve: 'Aprovar',
        reject: 'Rebutjar',
        modify: 'Modificar',
        modifications: 'Traçabilitat de canvis',
        noModifications: 'No hi ha modificacions registrades.',
    },
    coordinator: {
        title: 'Panel de coordinació',
        manageZones: 'Gestionar zones',
        manageSchedules: 'Gestionar horaris',
        pendingReview: 'Pendents de revisió',
        segmented: 'Segmentats',
        outOfZone: 'Fora de zona',
        all: 'Tots',
    },
    alerts: {
        noClockIn: 'No has fitxat l\'entrada',
        noClockOut: 'No has fitxat la sortida',
        breakRequired: 'Pausa obligatòria requerida',
        segmentRejected: 'Tram rebutjat',
        dismiss: 'Descartar',
    },
    break: {
        title: 'Pausa obligatòria',
        description: 'Has superat el llindar d\'hores seguides. Has de fer una pausa de {minutes} minuts.',
        remaining: 'Temps restant',
        skip: 'Ometre pausa',
        blocked: 'L\'aplicació està bloquejada durant la pausa.',
        completed: 'Pausa completada',
        settings: {
            title: 'Configuració de pausa obligatòria',
            enabled: 'Pausa obligatòria activada',
            thresholdHours: 'Llindar d\'hores seguides',
            breakDuration: 'Durada de la pausa',
            autoStart: 'Inici automàtic',
            gracePeriod: 'Període de gràcia',
            save: 'Desar configuració',
        },
    },
};
