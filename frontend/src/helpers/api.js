import axios from 'axios'
import { confirm } from '@/composables/useConfirm'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

// public pages where a 401 is expected and must NOT trigger the expired-session
// flow (the visitor was never signed in on these to begin with)
const PUBLIC_PATHS = new Set(['/login', '/set-password'])

// Once a 401 lands, several in-flight requests can 401 together; only the first
// should raise the notice. Resets naturally on the full-page reload to /login.
let sessionExpiredShown = false

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    // GET /api/user is the router's "am I logged in?" probe -- a 401 there
    // means "not signed in yet", not "session expired". Let the router guard
    // handle routing silently instead of alarming a never-logged-in visitor.
    const isAuthProbe = error.config?.url?.endsWith('/api/user')
    const onPublicPage = PUBLIC_PATHS.has(globalThis.location.pathname)

    if (status === 401 && !isAuthProbe && !onPublicPage && !sessionExpiredShown) {
      sessionExpiredShown = true
      confirm({
        alert: true,
        title: 'Session expired',
        text: 'Your session has ended. Please sign in again to continue.',
        confirmText: 'Sign in again',
      }).then(() => {
        globalThis.location.href = '/login'
      })
    }
    return Promise.reject(error)
  }
)

export default api
