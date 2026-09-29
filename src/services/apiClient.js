// ============================================================
// CRT — API Client for Laravel Backend
// Configurable via VITE_API_URL environment variable
// Uses Sanctum token authentication
// ============================================================

const API_URL = import.meta.env.VITE_API_URL || ''
const TOKEN_KEY = 'crt_api_token'

function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
}

export function apiUrl(endpoint) {
  return `${API_URL}/api${endpoint}`
}

export async function apiFetchRaw(endpoint, options = {}) {
  const token = getToken()
  const headers = { ...(options.headers || {}) }
  if (token) headers['Authorization'] = `Bearer ${token}`
  return fetch(apiUrl(endpoint), { ...options, headers })
}

/**
 * Make an authenticated API request.
 * @param {string} endpoint - API path (e.g. '/v1/users')
 * @param {object} options - fetch options (method, body, etc.)
 * @returns {Promise<any>} Parsed JSON response
 */
export async function apiRequest(endpoint, options = {}) {
  const url = `${API_URL}/api${endpoint}`
  const token = getToken()

  const headers = {
    'Accept': 'application/json',
    ...(options.headers || {}),
  }

  // Add auth token if available
  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  // Auto-set Content-Type for non-FormData bodies
  if (options.body && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
    if (typeof options.body === 'object') {
      options.body = JSON.stringify(options.body)
    }
  }

  const response = await fetch(url, {
    ...options,
    headers,
  })

  // Handle 401 — token expired
  if (response.status === 401) {
    const isAuthEndpoint = endpoint.includes('/auth/login')
    const isInitEndpoint = endpoint.includes('/users/me')
    if (!isAuthEndpoint) {
      // Only do hard redirect for non-init endpoints (actual user actions)
      // For init checks, just throw so the auth store can handle it gracefully
      if (!isInitEndpoint) {
        clearToken()
        window.localStorage.setItem('auth_error', 'La sessió ha caducat. Torneu a iniciar sessió.')
        window.location.hash = '#/login'
      }
      throw new Error('Session expired')
    }
  }

  // Handle validation errors (422)
  //
  // Les garanties del procediment (ET, conveni) es fan complir al backend i responen 422 amb un
  // MOTIU en llenguatge natural dins de `message`. Fins ara aquest motiu es perdia aquí —només
  // es propagava `errors`— i les vistes acabaven ensenyant un genèric «no s'ha pogut fer».
  // Ara el motiu viatja sempre a `.message`; `errors` es manté amb la mateixa forma de sempre
  // perquè els consumidors que llegeixen camp a camp (e.errors.end_date[0]) segueixin funcionant.
  if (response.status === 422) {
    const data = await response.json().catch(() => ({}))
    const fieldErrors = data && typeof data.errors === 'object' && data.errors !== null ? data.errors : null
    // El primer error de camp és més concret que el «The given data was invalid» de Laravel;
    // quan no hi ha errors de camp, el `message` és el motiu que ha escrit la guarda.
    const firstField = fieldErrors ? Object.values(fieldErrors).flat().find(Boolean) : null
    throw {
      status: 422,
      errors: fieldErrors || data,
      message: firstField || data?.message || 'Dades no vàlides.',
    }
  }

  // Handle other errors
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}))
    throw { status: response.status, message: errorData.message || response.statusText }
  }

  // Handle 204 No Content
  if (response.status === 204) {
    return null
  }

  return response.json()
}

// ── Convenience methods ──

export const api = {
  get: (endpoint) => apiRequest(endpoint, { method: 'GET' }),
  post: (endpoint, data) => apiRequest(endpoint, { method: 'POST', body: data }),
  put: (endpoint, data) => apiRequest(endpoint, { method: 'PUT', body: data }),
  delete: (endpoint) => apiRequest(endpoint, { method: 'DELETE' }),

  /**
   * Upload file with FormData
   */
  upload: (endpoint, formData) => apiRequest(endpoint, {
    method: 'POST',
    body: formData,
    headers: {} // Let browser set Content-Type with boundary
  }),

  // ── Auth helpers ──

  async login(email, password) {
    const result = await apiRequest('/v1/auth/login', {
      method: 'POST',
      body: { email, password },
    })
    if (result.token) {
      setToken(result.token)
    }
    return result
  },

  async logout() {
    try {
      await apiRequest('/v1/auth/logout', { method: 'POST' })
    } finally {
      clearToken()
    }
  },

  async me() {
    return apiRequest('/v1/auth/me')
  },
}

/**
 * Check if the app should use the API backend.
 * Returns true if VITE_API_URL is configured.
 */
export function isApiMode() { return !!API_URL }

export default api
