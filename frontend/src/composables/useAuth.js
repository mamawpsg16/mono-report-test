import { ref, computed } from 'vue'
import api from '../helpers/api'

const user = ref(null)
const checked = ref(false)

export function useAuth() {
  const isAuthenticated = computed(() => !!user.value)

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
    await api.get('/sanctum/csrf-cookie')
    const { data } = await api.post('/api/login', credentials)
    await fetchUser()
    return data
  }

  async function logout() {
    await api.post('/api/logout')
    user.value = null
    checked.value = true
  }

  return { user, checked, isAuthenticated, can, fetchUser, login, logout }
}
