import { ref, computed } from 'vue'
import api from '@/helpers/api'

const user = ref(null)
const checked = ref(false)

export function useAuth() {
  const isAuthenticated = computed(() => !!user.value)

  // set for admin-created accounts on a temp password: the router pins them to
  // the change-password screen until they set a real one (cleared server-side)
  const mustChangePassword = computed(() => user.value?.must_change_password ?? false)

  function can(permission) {
    return user.value?.permissions?.includes(permission) ?? false
  }

  async function fetchUser() {
    try {
      const { data } = await api.get('/api/user')
      user.value = data
    } catch {
      user.value = null
    } finally {
      checked.value = true
    }
  }

  async function login(credentials) {
    // Fetch CSRF token cookie (Sanctum SPA session auth step 1). axios's
    // withXSRFToken:true (see helpers/api.js) then auto-copies the XSRF-TOKEN
    // cookie into the X-XSRF-TOKEN header on the login POST below.
    await api.get('/sanctum/csrf-cookie')

    const { data } = await api.post('/api/login', credentials)

    // Use the user /api/login already returns instead of an immediate follow-up
    // GET /api/user (was racing session-cookie propagation on the first login).
    user.value = data.user
    checked.value = true
    return data
  }

  async function logout() {
    await api.post('/api/logout')
    user.value = null
    checked.value = true
  }

  return { user, checked, isAuthenticated, mustChangePassword, can, fetchUser, login, logout }
}
