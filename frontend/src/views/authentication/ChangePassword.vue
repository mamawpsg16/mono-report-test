<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-header">
        <div class="brand-icon">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
            <path d="M7.5 11.5V3.5M4 7l3.5-3.5L11 7" stroke="#fff" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <h1 class="login-title">Set your password</h1>
        <p class="login-subtitle">Choose a new password to finish signing in.</p>
      </div>

      <form class="login-form" @submit.prevent="handleSubmit">
        <div v-if="errorMessage" class="login-error">
          {{ errorMessage }}
        </div>

        <div class="form-group">
          <label for="password">New password</label>
          <input
            id="password"
            v-model="form.password"
            type="password"
            autocomplete="new-password"
            required
            :disabled="loading"
            placeholder="At least 8 characters"
          />
        </div>

        <div class="form-group">
          <label for="password_confirmation">Confirm password</label>
          <input
            id="password_confirmation"
            v-model="form.password_confirmation"
            type="password"
            autocomplete="new-password"
            required
            :disabled="loading"
            placeholder="Re-enter your password"
          />
        </div>

        <button type="submit" class="btn-login" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          <span v-else>Save password</span>
        </button>
      </form>

      <button class="btn-signout" :disabled="loading" @click="handleLogout">
        Sign out
      </button>
    </div>
  </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/helpers/api'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const auth = useAuth()

const form = reactive({ password: '', password_confirmation: '' })
const loading = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
  loading.value = true
  errorMessage.value = ''

  try {
    await api.post('/api/change-password', form)
    // refresh the user so must_change_password flips false, then the router
    // lets them into the app
    await auth.fetchUser()
    router.push({ name: 'home' })
  } catch (err) {
    if (err.response?.status === 422) {
      errorMessage.value = err.response.data.errors?.password?.[0] || 'Invalid password'
    } else {
      errorMessage.value = 'Something went wrong. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<style scoped>
.login-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-bg);
  padding: 20px;
}

.login-card {
  width: 100%;
  max-width: 400px;
  background: var(--color-surface);
  border-radius: 16px;
  padding: 40px 36px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 12px rgba(0, 0, 0, 0.06);
}

.login-header {
  text-align: center;
  margin-bottom: 32px;
}

.login-header .brand-icon {
  width: 40px;
  height: 40px;
  background: var(--color-accent);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
}

.login-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0 0 6px;
  letter-spacing: -0.3px;
}

.login-subtitle {
  font-size: 14px;
  color: var(--color-text-muted);
  margin: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.login-error {
  background: var(--color-danger-soft);
  border: 1px solid var(--color-danger-border);
  color: var(--color-danger);
  font-size: 13px;
  padding: 10px 14px;
  border-radius: 8px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text);
}

.form-group input {
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: 8px;
  font-size: 14px;
  font-family: inherit;
  color: var(--color-ink);
  outline: none;
  transition: border-color 0.15s;
}

.form-group input:focus {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px rgba(var(--color-accent-rgb), 0.1);
}

.form-group input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.form-group input::placeholder {
  color: var(--color-text-faint);
}

.btn-login {
  padding: 11px 20px;
  background: var(--color-accent);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 42px;
}

.btn-login:hover:not(:disabled) {
  background: var(--color-accent-hover);
}

.btn-login:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.btn-signout {
  display: block;
  margin: 18px auto 0;
  background: none;
  border: none;
  color: var(--color-text-muted);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
}
.btn-signout:hover:not(:disabled) {
  color: var(--color-text);
  text-decoration: underline;
}
.btn-signout:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.spinner {
  width: 18px;
  height: 18px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
