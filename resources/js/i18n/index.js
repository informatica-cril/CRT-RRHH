/**
 * Punto de entrada para i18n del frontend.
 * 
 * El proyecto usa catalán como idioma por defecto (strings hardcoded en los componentes).
 * Este archivo proporciona una estructura para integrar un sistema de traducciones
 * completo en el futuro (ej. con vue-i18n).
 * 
 * Uso provisional:
 *   import { t } from '@/i18n';
 *   t('workLog.segments')  // → 'Trams'
 */

import ca from './ca.js';
import es from './es.js';

const messages = { ca, es };

// Idioma por defecto: catalán
let locale = 'ca';

/**
 * Obtiene el idioma actual.
 */
export function getLocale() {
    return locale;
}

/**
 * Establece el idioma actual.
 * @param {string} lang - 'ca' o 'es'
 */
export function setLocale(lang) {
    if (messages[lang]) {
        locale = lang;
    }
}

/**
 * Traduce una clave usando notación de puntos.
 * @param {string} key - Clave en formato 'seccion.subseccion.clave'
 * @param {object} params - Parámetros para interpolar ({name} → value)
 * @returns {string}
 */
export function t(key, params = {}) {
    const keys = key.split('.');
    let value = messages[locale];

    for (const k of keys) {
        if (value && typeof value === 'object' && k in value) {
            value = value[k];
        } else {
            // Fallback al catalán si no existe en el idioma actual
            value = messages.ca;
            for (const k2 of keys) {
                if (value && typeof value === 'object' && k2 in value) {
                    value = value[k2];
                } else {
                    return key; // Devolver la clave si no se encuentra
                }
            }
            break;
        }
    }

    if (typeof value === 'string' && params) {
        // Interpolar parámetros: {minutes} → params.minutes
        value = value.replace(/\{(\w+)\}/g, (_, match) => params[match] ?? '');
    }

    return typeof value === 'string' ? value : key;
}

export default { t, getLocale, setLocale, messages };
