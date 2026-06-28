import { ref, computed } from 'vue'
import api from '../helpers/api'

const user = ref(null)
const checked = ref(false)

export function useAuth() {
  const isAuthenticated = computed(() => !!user.value)

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
    user.value = data.user
    checked.value = true
    return data
  }

  async function logout() {
    await api.post('/api/logout')
    user.value = null
    checked.value = true
  }

  return { user, checked, isAuthenticated, fetchUser, login, logout }
}
