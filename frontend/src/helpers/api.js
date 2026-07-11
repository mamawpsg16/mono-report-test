import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000',
  headers: {
    Accept: 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

// public pages where a 401 is expected (the router's /api/user probe) and must
// NOT bounce the visitor to the login screen
const PUBLIC_PATHS = new Set(['/login', '/set-password'])

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && !PUBLIC_PATHS.has(globalThis.location.pathname)) {
      globalThis.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
