// ============================================================
// GeoPolygon Service — CP boundary polygons for Barcelona
// ============================================================
// PRIMARY SOURCE: Embedded GeoJSON polygons from official data
// (see ../data/barcelonaPostalCodes.js — Correos boundaries).
// SECONDARY: Overpass API for CPs outside Barcelona city.
// Also provides point-in-polygon validation (ray casting).
// ============================================================

import { BCN_POSTAL_POLYGONS } from '../data/barcelonaPostalCodes'

const CACHE_KEY = 'crt_cp_polygons_v3'
const OVERPASS_MIRRORS = [
  'https://overpass-api.de/api/interpreter',
  'https://overpass.kumi.systems/api/interpreter',
  'https://overpass.openstreetmap.ru/api/interpreter'
]
const TIMEOUT_MS = 20000
const REQUEST_DELAY_MS = 600

// In-memory cache for the session (avoids redundant localStorage reads)
const _memCache = {}

// Queue for rate-limiting Overpass requests (one at a time)
let _queue = Promise.resolve()

// ─── Embedded data helper ──────────────────────────────────────────────────

/**
 * Returns the embedded polygon geometry for a Barcelona CP, or undefined.
 * This is instant — no network, no cache, no async.
 */
function getEmbeddedPolygon(cp) {
  return BCN_POSTAL_POLYGONS[cp] || undefined
}

// ─── Cache helpers ─────────────────────────────────────────────────────────

function loadCache() {
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    return raw ? JSON.parse(raw) : {}
  } catch { return {} }
}

function saveToCache(cp, geometry) {
  try {
    const store = loadCache()
    store[cp] = { geometry, ts: Date.now() }
    localStorage.setItem(CACHE_KEY, JSON.stringify(store))
  } catch (e) {
    // localStorage quota exceeded — clear old entries and retry
    try { localStorage.removeItem(CACHE_KEY) } catch {}
  }
}

function getCached(cp) {
  // 1. Embedded data has highest priority (always available, always correct)
  const embedded = getEmbeddedPolygon(cp)
  if (embedded) {
    _memCache[cp] = embedded
    return embedded
  }
  // 2. In-memory cache
  if (_memCache[cp] !== undefined) return _memCache[cp]
  // 3. localStorage cache (for non-embedded CPs fetched via Overpass)
  const store = loadCache()
  const entry = store[cp]
  if (!entry) return undefined
  // Cache valid for 30 days
  if (Date.now() - entry.ts > 30 * 24 * 60 * 60 * 1000) return undefined
  _memCache[cp] = entry.geometry
  return entry.geometry
}

/**
 * Synchronous cached polygon lookup (no network fetch).
 * Returns the cached/embedded GeoJSON geometry or null if not available.
 * Used by geolocation.js for fast validation without awaiting network.
 */
export function getCachedPolygon(cp) {
  const v = getCached(cp)
  return v !== undefined ? v : null
}


// ─── Overpass fetching (secondary, for non-Barcelona CPs) ───────────────────

async function fetchFromOverpass(cp) {
  // Union of two queries: by postal_code tag AND by ref tag (for maximal compatibility)
  const query = `[out:json][timeout:25];
(
  relation["boundary"="postal_code"]["postal_code"="${cp}"];
  relation["boundary"="postal_code"]["ref"="${cp}"];
);
out geom;`
  const body = 'data=' + encodeURIComponent(query)

  for (const mirror of OVERPASS_MIRRORS) {
    try {
      const ctrl = new AbortController()
      const tid = setTimeout(() => ctrl.abort(), TIMEOUT_MS)
      const res = await fetch(mirror, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
        signal: ctrl.signal
      })
      clearTimeout(tid)
      if (!res.ok) continue

      const data = await res.json()
      const geometry = overpassToGeoJson(data, cp)
      if (geometry) return geometry
    } catch (e) {
      console.warn(`[GeoPolygon] Overpass ${mirror} failed for ${cp}:`, e.message)
    }
  }
  return null
}

// ─── Overpass → GeoJSON conversion ─────────────────────────────────────────

function overpassToGeoJson(data, cp) {
  // Find the best-matching relation (prefer exact postal_code match)
  const elements = data.elements || []
  const relation = elements.find(e =>
    e.type === 'relation' &&
    e.members?.length &&
    (e.tags?.postal_code === cp || e.tags?.ref === cp)
  ) || elements.find(e => e.type === 'relation' && e.members?.length)

  if (!relation) return null

  // Separate outer and inner rings
  const outerWays = relation.members
    .filter(m => m.type === 'way' && (m.role === 'outer' || m.role === '') && m.geometry?.length > 1)
    .map(m => m.geometry.map(p => [p.lon, p.lat]))

  const innerWays = relation.members
    .filter(m => m.type === 'way' && m.role === 'inner' && m.geometry?.length > 1)
    .map(m => m.geometry.map(p => [p.lon, p.lat]))

  if (!outerWays.length) return null

  const outerRings = stitchWaysToRings(outerWays)
  const innerRings = stitchWaysToRings(innerWays)

  if (!outerRings.length) return null

  if (outerRings.length === 1) {
    // Simple polygon: one outer ring + optional holes
    const coordinates = [outerRings[0], ...innerRings]
    return { type: 'Polygon', coordinates }
  }

  // MultiPolygon: multiple outer rings
  return {
    type: 'MultiPolygon',
    coordinates: outerRings.map(outer => [outer, ...innerRings])
  }
}

// Stitch unordered OSM way segments into closed GeoJSON rings
function stitchWaysToRings(ways) {
  if (!ways.length) return []
  const pt = p => `${p[0].toFixed(6)},${p[1].toFixed(6)}`
  const remaining = ways.map(w => [...w])
  const rings = []

  while (remaining.length > 0) {
    let ring = [...remaining.shift()]
    let changed = true
    let iterations = 0

    while (changed && iterations < remaining.length * 2 + 10) {
      changed = false
      iterations++
      const tail = pt(ring[ring.length - 1])
      for (let i = 0; i < remaining.length; i++) {
        const w = remaining[i]
        if (pt(w[0]) === tail) {
          ring = ring.concat(w.slice(1))
          remaining.splice(i, 1)
          changed = true
          break
        } else if (pt(w[w.length - 1]) === tail) {
          ring = ring.concat([...w].reverse().slice(1))
          remaining.splice(i, 1)
          changed = true
          break
        }
      }
      if (pt(ring[0]) === pt(ring[ring.length - 1])) break
    }

    // Force close the ring
    if (pt(ring[0]) !== pt(ring[ring.length - 1])) {
      ring.push(ring[0])
    }

    if (ring.length >= 4) rings.push(ring)
  }
  return rings
}

// ─── Public API ─────────────────────────────────────────────────────────────

/**
 * Get the GeoJSON polygon for a postal code.
 * For Barcelona CPs (08001-08042): instant from embedded data.
 * For other CPs: fetched from Overpass API with caching.
 * Returns null if not available.
 */
export async function getPolygon(cp) {
  // 1. Check embedded data first (instant, no network)
  const embedded = getEmbeddedPolygon(cp)
  if (embedded) {
    _memCache[cp] = embedded
    return embedded
  }

  // 2. Check memory/localStorage cache
  const cached = getCached(cp)
  if (cached !== undefined) return cached

  // 3. Fetch from Overpass (rate-limited queue) — only for non-embedded CPs
  const result = await new Promise(resolve => {
    _queue = _queue.then(async () => {
      await new Promise(r => setTimeout(r, REQUEST_DELAY_MS))
      const geometry = await fetchFromOverpass(cp)
      _memCache[cp] = geometry
      if (geometry) saveToCache(cp, geometry)
      resolve(geometry)
    })
  })
  return result
}

/**
 * Pre-fetch polygons for a list of CPs (fire and forget).
 * Barcelona CPs are already embedded — this only triggers Overpass for others.
 */
export function prefetchPolygons(cpList) {
  for (const cp of cpList) {
    if (!getEmbeddedPolygon(cp) && getCached(cp) === undefined) {
      getPolygon(cp).catch(() => {})
    }
  }
}

/**
 * Clear all cached polygons (force re-fetch on next use).
 * Note: embedded polygons are always available regardless.
 */
export function clearPolygonCache() {
  Object.keys(_memCache).forEach(k => delete _memCache[k])
  try { localStorage.removeItem(CACHE_KEY) } catch {}
}

// ─── Point-in-Polygon (ray casting) ────────────────────────────────────────

/**
 * Test if [lat, lng] is inside a GeoJSON geometry.
 * Supports Polygon and MultiPolygon.
 */
export function pointInGeoJson(lat, lng, geojson) {
  if (!geojson) return false
  // GeoJSON uses [lng, lat] order
  const point = [lng, lat]

  if (geojson.type === 'Polygon') {
    return pointInPolygon(point, geojson.coordinates)
  }
  if (geojson.type === 'MultiPolygon') {
    return geojson.coordinates.some(poly => pointInPolygon(point, poly))
  }
  return false
}

/**
 * Ray-casting point-in-polygon test.
 * coordinates is an array of rings: [outerRing, ...holes]
 * Each ring is [[lng, lat], ...]
 */
function pointInPolygon(point, rings) {
  const [px, py] = point
  const outer = rings[0]
  const holes = rings.slice(1)

  if (!ringContainsPoint(px, py, outer)) return false
  for (const hole of holes) {
    if (ringContainsPoint(px, py, hole)) return false
  }
  return true
}

function ringContainsPoint(px, py, ring) {
  let inside = false
  const n = ring.length
  for (let i = 0, j = n - 1; i < n; j = i++) {
    const [xi, yi] = ring[i]
    const [xj, yj] = ring[j]
    const intersect = ((yi > py) !== (yj > py)) &&
      (px < (xj - xi) * (py - yi) / (yj - yi) + xi)
    if (intersect) inside = !inside
  }
  return inside
}

/**
 * Get bounding box of a GeoJSON geometry as [[minLat, minLng], [maxLat, maxLng]]
 * Useful for Leaflet fitBounds.
 */
export function getGeoJsonBounds(geojson) {
  if (!geojson) return null
  let allCoords = []
  if (geojson.type === 'Polygon') {
    allCoords = geojson.coordinates[0]
  } else if (geojson.type === 'MultiPolygon') {
    allCoords = geojson.coordinates.flatMap(p => p[0])
  }
  if (!allCoords.length) return null
  const lngs = allCoords.map(c => c[0])
  const lats = allCoords.map(c => c[1])
  return [
    [Math.min(...lats), Math.min(...lngs)],
    [Math.max(...lats), Math.max(...lngs)]
  ]
}
