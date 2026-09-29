// ============================================================
// Geolocation & Geofencing Service
// ============================================================
// Supports 3 worker types:
// 1. DOMICILIARIA: postal code polygon/circle geofencing
// 2. DOMICILIARIA_VALLES: municipal term boundary geofencing
// 3. AMBULATORIA: configurable geo-points per worker
// ============================================================

import { pointInGeoJson, getCachedPolygon } from './geoPolygonService'
import { db } from './db'

// Barcelona postal codes with approximate centers + radius fallback
export const GEOLOCATION_TOLERANCE = 100 // metros de cortesía para el GPS

export const BARCELONA_POSTAL_CODES = {
  '08001': { lat: 41.3818, lng: 2.1685, radius: 2000, zone: 'Raval / Gótic' },
  '08002': { lat: 41.3850, lng: 2.1770, radius: 2000, zone: 'Barri Gòtic' },
  '08003': { lat: 41.3870, lng: 2.1890, radius: 2000, zone: 'Barceloneta / Born' },
  '08004': { lat: 41.3730, lng: 2.1650, radius: 2000, zone: 'Poble Sec' },
  '08005': { lat: 41.3940, lng: 2.1980, radius: 2000, zone: 'Vila Olímpica' },
  '08006': { lat: 41.3980, lng: 2.1530, radius: 2000, zone: 'Gràcia / Sant Gervasi' },
  '08007': { lat: 41.3930, lng: 2.1650, radius: 2000, zone: 'Eixample Dret' },
  '08008': { lat: 41.3890, lng: 2.1620, radius: 2000, zone: 'Eixample Dret' },
  '08009': { lat: 41.3920, lng: 2.1700, radius: 2000, zone: 'Eixample Dret' },
  '08010': { lat: 41.3900, lng: 2.1750, radius: 2000, zone: 'Eixample Dret' },
  '08011': { lat: 41.3860, lng: 2.1610, radius: 2000, zone: 'Eixample Esquerra' },
  '08012': { lat: 41.4000, lng: 2.1600, radius: 2000, zone: 'Gràcia' },
  '08013': { lat: 41.4050, lng: 2.1780, radius: 2000, zone: 'Horta-Guinardó' },
  '08014': { lat: 41.3780, lng: 2.1400, radius: 2000, zone: 'Sants' },
  '08015': { lat: 41.3800, lng: 2.1520, radius: 2000, zone: 'Eixample Esquerra' },
  '08016': { lat: 41.4200, lng: 2.1700, radius: 2000, zone: 'Horta / Guinardó' },
  '08017': { lat: 41.4050, lng: 2.1350, radius: 2000, zone: 'Sarrià / Pedralbes' },
  '08018': { lat: 41.4020, lng: 2.1950, radius: 2000, zone: 'Poblenou' },
  '08019': { lat: 41.4100, lng: 2.2100, radius: 2000, zone: 'Sant Martí' },
  '08020': { lat: 41.4080, lng: 2.1870, radius: 2000, zone: 'Sant Martí / Clot' },
  '08021': { lat: 41.3950, lng: 2.1400, radius: 2000, zone: 'Sant Gervasi' },
  '08022': { lat: 41.4100, lng: 2.1350, radius: 2000, zone: 'Vallvidrera / Tibidabo' },
  '08023': { lat: 41.4150, lng: 2.1500, radius: 2000, zone: 'Vallcarca / Penitents' },
  '08024': { lat: 41.4130, lng: 2.1600, radius: 2000, zone: 'Gràcia nord' },
  '08025': { lat: 41.4050, lng: 2.1700, radius: 2000, zone: 'Camp d\'en Grassot' },
  '08026': { lat: 41.4100, lng: 2.1800, radius: 2000, zone: 'Baix Guinardó' },
  '08027': { lat: 41.4200, lng: 2.1850, radius: 2000, zone: 'Sant Andreu / Congrés' },
  '08028': { lat: 41.3850, lng: 2.1280, radius: 2000, zone: 'Les Corts' },
  '08029': { lat: 41.3900, lng: 2.1350, radius: 2000, zone: 'Les Corts / Diagonal' },
  '08030': { lat: 41.4300, lng: 2.1900, radius: 2000, zone: 'Nou Barris / Trinitat' },
  '08031': { lat: 41.4350, lng: 2.1850, radius: 2000, zone: 'Trinitat Vella' },
  '08032': { lat: 41.4250, lng: 2.1690, radius: 2000, zone: 'Canyelles / Roquetes' },
  '08033': { lat: 41.4400, lng: 2.1950, radius: 2000, zone: 'Ciutat Meridiana' },
  '08034': { lat: 41.3900, lng: 2.1180, radius: 2000, zone: 'Pedralbes' },
  '08035': { lat: 41.4200, lng: 2.1550, radius: 2000, zone: 'Vall d\'Hebron' },
  '08036': { lat: 41.3870, lng: 2.1500, radius: 2000, zone: 'Eixample Esquerra' },
  '08037': { lat: 41.3980, lng: 2.1750, radius: 2000, zone: 'Sagrada Família' },
  '08038': { lat: 41.3650, lng: 2.1550, radius: 2000, zone: 'Montjuïc / Zona Franca' },
  '08039': { lat: 41.3570, lng: 2.1600, radius: 2000, zone: 'Port / Zona Franca' },
  '08040': { lat: 41.3600, lng: 2.1700, radius: 2000, zone: 'Marina / Zona Franca' },
  '08041': { lat: 41.4350, lng: 2.1700, radius: 2000, zone: 'Nou Barris nord' },
  '08042': { lat: 41.4400, lng: 2.1800, radius: 2000, zone: 'Torre Baró' }
}

export function getPostalCodeList() {
  return Object.entries(BARCELONA_POSTAL_CODES).map(([code, data]) => ({
    code, zone: data.zone
  }))
}

// ── VALLÈS MUNICIPALITIES ──
// Configurable list of Vallès municipalities with approximate bounding boxes
// In production, these would use official municipal boundary polygons
export const VALLES_MUNICIPALITIES = {
  TERRASSA: { name: 'Terrassa', lat: 41.5639, lng: 2.0090, radius: 5000 },
  SABADELL: { name: 'Sabadell', lat: 41.5483, lng: 2.1075, radius: 4500 },
  RUBI: { name: 'Rubí', lat: 41.4943, lng: 2.0326, radius: 3500 },
  SANT_CUGAT: { name: 'Sant Cugat del Vallès', lat: 41.4734, lng: 2.0826, radius: 4000 },
  CERDANYOLA: { name: 'Cerdanyola del Vallès', lat: 41.4919, lng: 2.1405, radius: 3500 },
  BARBERA: { name: 'Barberà del Vallès', lat: 41.5170, lng: 2.1261, radius: 2500 },
  MONTCADA: { name: 'Montcada i Reixac', lat: 41.4835, lng: 2.1865, radius: 3500 },
  RIPOLLET: { name: 'Ripollet', lat: 41.4976, lng: 2.1568, radius: 2000 },
  SANT_QUIRZE: { name: 'Sant Quirze del Vallès', lat: 41.5318, lng: 2.0838, radius: 2500 },
  CASTELLAR: { name: 'Castellar del Vallès', lat: 41.6135, lng: 2.0869, radius: 3500 },
  MATADEPERA: { name: 'Matadepera', lat: 41.5970, lng: 1.9645, radius: 3000 },
  VILADECAVALLS: { name: 'Viladecavalls', lat: 41.5593, lng: 1.9329, radius: 3000 },
  ULLASTRELL: { name: 'Ullastrell', lat: 41.5274, lng: 1.9579, radius: 2000 },
  VACARISSES: { name: 'Vacarisses', lat: 41.5712, lng: 1.9134, radius: 4000 },
  RELLINARS: { name: 'Rellinars', lat: 41.6350, lng: 1.9186, radius: 2500 },
  SENTMENAT: { name: 'Sentmenat', lat: 41.6303, lng: 2.1479, radius: 3000 },
  POLINYA: { name: 'Polinyà', lat: 41.5526, lng: 2.1558, radius: 2000 },
  PALAU_SOLITA: { name: 'Palau-solità i Plegamans', lat: 41.5734, lng: 2.1832, radius: 3000 },
  MOLLET: { name: 'Mollet del Vallès', lat: 41.5404, lng: 2.1910, radius: 2500 },
  GRANOLLERS: { name: 'Granollers', lat: 41.6086, lng: 2.2878, radius: 3500 },
  CALDES_MONTBUI: { name: 'Caldes de Montbui', lat: 41.6320, lng: 2.1683, radius: 3000 },
  CASTELLBISBAL: { name: 'Castellbisbal', lat: 41.4768, lng: 1.9834, radius: 3500 }
}

export function getMunicipalityList() {
  return Object.entries(VALLES_MUNICIPALITIES).map(([code, data]) => ({
    code, name: data.name
  }))
}

/**
 * Get current GPS position
 */
export function getCurrentPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error('Geolocation not supported'))
      return
    }
    const options = { enableHighAccuracy: true, timeout: 25000, maximumAge: 0 }
    
    const success = (pos) => resolve({
      lat: pos.coords.latitude,
      lng: pos.coords.longitude,
      accuracy: pos.coords.accuracy,
      timestamp: pos.timestamp
    })

    navigator.geolocation.getCurrentPosition(success, (err) => {
      // Fallback 1: Low accuracy
      console.log('[Geolocation] Alta precisió fallida, provant mitja...')
      navigator.geolocation.getCurrentPosition(success, () => {
        // Fallback 2: Cached position
        console.log('[Geolocation] Resposta lenta, usant última posició coneguda...')
        navigator.geolocation.getCurrentPosition(success, (errFinal) => {
          reject(errFinal)
        }, { enableHighAccuracy: false, timeout: 5000, maximumAge: 600000 })
      }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 })
    }, options)
  })
}

/**
 * Haversine distance between two coordinates (meters)
 */
function haversineDistance(lat1, lng1, lat2, lng2) {
  const R = 6371000
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLng = (lng2 - lng1) * Math.PI / 180
  const a = Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
    Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

/**
 * Check if [lat, lng] is within the geofence of a given postal code.
 */
export function isWithinGeofence(lat, lng, postalCode, cachedPolygon = null) {
  const center = BARCELONA_POSTAL_CODES[postalCode]
  const polygon = cachedPolygon || getCachedPolygon(postalCode)
  if (polygon) {
    const valid = pointInGeoJson(lat, lng, polygon)
    return {
      valid,
      method: 'polygon',
      postalCode,
      zone: center?.zone || postalCode,
      message: valid
        ? `✓ Dins de la zona ${postalCode}${center ? ' (' + center.zone + ')' : ''} — polígon real`
        : `✗ Fora de la zona ${postalCode}${center ? ' (' + center.zone + ')' : ''} — polígon real`
    }
  }
  if (!center) {
    return {
      valid: true, method: 'unconfigured',
      postalCode, zone: postalCode,
      message: 'CP no configurat — accés permès (sense polígon)'
    }
  }
  const distance = haversineDistance(lat, lng, center.lat, center.lng)
  const valid = distance <= (center.radius || 2000) + GEOLOCATION_TOLERANCE
  return {
    valid,
    method: 'circle',
    distance: Math.round(distance),
    maxDistance: center.radius || 2000,
    postalCode,
    zone: center.zone,
    message: valid
      ? `Dins de l'àrea ${postalCode} (${center.zone}) — cercle provisional (${Math.round(distance)}m)`
      : `Fora de l'àrea ${postalCode} (${center.zone}) — ${Math.round(distance)}m (cercle provisional)`
  }
}

/**
 * Check position against multiple CPs
 */
export function isWithinAnyGeofence(lat, lng, postalCodes, polygonMap = {}) {
  for (const cp of postalCodes) {
    const result = isWithinGeofence(lat, lng, cp, polygonMap[cp] || null)
    if (result.valid) return { ...result, matchedCp: cp }
  }
  return {
    valid: false, postalCode: postalCodes[0], zone: '', matchedCp: null,
    message: `Fora de totes les zones assignades: ${postalCodes.join(', ')}`
  }
}

/**
 * Check if position is within any of the configurable ambulatory points for a user
 */
export async function isWithinAmbulatoryPoints(lat, lng, userId) {
  const points = await db.getAmbulatoryPoints(userId)
  if (!points || points.length === 0) {
    return { valid: true, method: 'no_points', message: 'Cap punt configurat — accés permès' }
  }

  for (const point of points) {
    const distance = haversineDistance(lat, lng, point.lat, point.lng)
    if (distance <= (point.radius || 100) + GEOLOCATION_TOLERANCE) {
      return {
        valid: true,
        method: 'ambulatory_point',
        distance: Math.round(distance),
        maxDistance: point.radius || 100,
        zone: point.name,
        message: `✓ Dins de ${point.name} (${Math.round(distance)}m)`
      }
    }
  }

  // Return info about nearest point
  const nearest = points.reduce((closest, pt) => {
    const d = haversineDistance(lat, lng, pt.lat, pt.lng)
    return (!closest || d < closest.distance) ? { ...pt, distance: d } : closest
  }, null)

  return {
    valid: false,
    method: 'ambulatory_point',
    distance: Math.round(nearest?.distance || 0),
    zone: nearest?.name || 'Desconegut',
    message: `✗ Fora de tots els punts assignats. El més proper: ${nearest?.name} (${Math.round(nearest?.distance || 0)}m)`
  }
}

/**
 * Check if position is within a Vallès municipality
 */
export function isWithinMunicipality(lat, lng, municipalityCode) {
  const muni = VALLES_MUNICIPALITIES[municipalityCode]
  if (!muni) {
    return { valid: false, method: 'unknown_municipality', message: `Municipi desconegut: ${municipalityCode}` }
  }
  const distance = haversineDistance(lat, lng, muni.lat, muni.lng)
  const valid = distance <= (muni.radius || 5000) + GEOLOCATION_TOLERANCE
  return {
    valid,
    method: 'municipality_circle',
    distance: Math.round(distance),
    maxDistance: muni.radius || 5000,
    zone: muni.name,
    message: valid
      ? `✓ Dins del terme municipal de ${muni.name} (${Math.round(distance)}m)`
      : `✗ Fora del terme municipal de ${muni.name} (${Math.round(distance)}m)`
  }
}

/**
 * Check if position is within any of the assigned municipalities for a DOMICILIARIA_VALLES worker
 */
export async function isWithinAssignedMunicipalities(lat, lng, userId) {
  const assignment = await db.getActiveMunicipalAssignment(userId)
  if (!assignment || !assignment.municipalities || assignment.municipalities.length === 0) {
    return { valid: true, method: 'no_municipalities', message: 'Cap terme municipal assignat — accés permès' }
  }

  for (const muniCode of assignment.municipalities) {
    const result = isWithinMunicipality(lat, lng, muniCode)
    if (result.valid) return { ...result, matchedMunicipality: muniCode }
  }

  const muniNames = assignment.municipalities.map(c => VALLES_MUNICIPALITIES[c]?.name || c).join(', ')
  return {
    valid: false,
    method: 'municipality_circle',
    matchedMunicipality: null,
    message: `✗ Fora de tots els termes municipals assignats: ${muniNames}`
  }
}

export function startTracking(callback, intervalMs = 300000) {
  const watchId = setInterval(async () => {
    try { const pos = await getCurrentPosition(); callback(pos) }
    catch (err) { console.warn('Tracking error:', err) }
  }, intervalMs)
  return watchId
}

export function stopTracking(watchId) {
  clearInterval(watchId)
}

/**
 * Unified location check for any worker type
 * Dispatches to the correct geofencing strategy based on work_type
 */
function puntDinsPoligon(lat, lng, anell) {
  let dins = false
  for (let i = 0, j = anell.length - 1; i < anell.length; j = i++) {
    const yi = anell[i][0], xi = anell[i][1], yj = anell[j][0], xj = anell[j][1]
    if (((yi > lat) !== (yj > lat)) && (lng < (xj - xi) * (lat - yi) / ((yj - yi) || 1e-12) + xi)) dins = !dins
  }
  return dins
}

export async function checkUserLocation(user, activeCpAssignment, lat, lng) {
  // Geovalla decidida a domi (Coordinació/Admin): si existeix, MANA.
  const gv = activeCpAssignment?.domi_geovalla
  if (gv && ((gv.poligons && gv.poligons.length) || (gv.cps && gv.cps.length))) {
    if ((gv.poligons || []).some(p => puntDinsPoligon(lat, lng, p))) return { valid: true }
    if (gv.cps && gv.cps.length) {
      const perCp = await isWithinAnyGeofence(lat, lng, gv.cps)
      if (perCp.valid) return perCp
    }
    return { valid: false, message: 'Fora del territori assignat (geovalla de Coordinació)' }
  }

  // AMBULATORIA mode — check against configurable points
  if (user?.work_type === 'AMBULATORIA') {
    return await isWithinAmbulatoryPoints(lat, lng, user.id)
  }

  // DOMICILIARIA_VALLES mode — check against municipal terms
  if (user?.work_type === 'DOMICILIARIA_VALLES') {
    return await isWithinAssignedMunicipalities(lat, lng, user.id)
  }

  // DOMICILIARIA mode — check against postal code polygons/circles
  const postalCodes = activeCpAssignment?.postal_codes || [user?.postal_code_assigned || '08001']
  return isWithinAnyGeofence(lat, lng, postalCodes)
}

/* startPeriodicVerification / stopPeriodicVerification ELIMINADES (C1 EIPD):
   la captura de posició NOMÉS es fa en hitos de marcatge; el seguiment periòdic
   està prohibit tècnicament — no només organitzativament. */
