// ============================================================
// CRT — API Client for Laravel Backend
// Configurable via VITE_API_URL environment variable
// Uses Sanctum token authentication
// ============================================================

const API_URL = import.meta.env.VITE_API_URL || ''
const TOKEN_KEY = 'crt_api_token'

// Sense VITE_API_URL la web la serveix el mateix Laravel (/app/) i crida /api al
// mateix domini. Sanctum tracta aquestes peticions com a "stateful" (com el panell
// Inertia) i exigeix el token CSRF a les que modifiquen dades.
const SAME_ORIGIN = !API_URL
const UNSAFE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE']

function readXsrfCookie() {
  const m = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return m ? decodeURIComponent(m[1]) : null
}

async function xsrfToken(refresh = false) {
  if (refresh || !readXsrfCookie()) {
    await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' })
  }
  return readXsrfCookie()
}

function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  localStorage.setItem(TOKEN_KEY, token)
}

export function clearToken() {
  localStorage.removeItem(TOKEN_KEY)
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

  const needsXsrf = SAME_ORIGIN && UNSAFE_METHODS.includes((options.method || 'GET').toUpperCase())
  if (needsXsrf) {
    headers['X-XSRF-TOKEN'] = await xsrfToken()
  }

  let response = await fetch(url, {
    ...options,
    headers,
  })

  // 419: el token CSRF ha caducat amb la sessió. Se'n demana un de nou i es reintenta un cop.
  if (response.status === 419 && needsXsrf) {
    headers['X-XSRF-TOKEN'] = await xsrfToken(true)
    response = await fetch(url, { ...options, headers })
  }

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
  if (response.status === 422) {
    const errors = await response.json()
    throw { status: 422, errors: errors.errors || errors }
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
 * Sempre en mode API: amb VITE_API_URL (app mòbil) o al mateix domini (web a /app/).
 */
export function isApiMode() { return true }

export default api
