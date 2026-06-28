<template>
  <div>
    <nav class="top-nav">
      <div class="nav-brand">
        <div class="brand-icon">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
            <path d="M7.5 11.5V3.5M4 7l3.5-3.5L11 7" stroke="#fff" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <span class="brand-name">DataForge</span>
      </div>
      <div class="nav-links">
        <span class="nav-link active">Import</span>
        <span class="nav-link">Records</span>
        <span class="nav-link">Reports</span>
      </div>
      <div class="nav-right">
        <div class="status-dot"></div>
        <div class="avatar-wrapper" ref="avatarWrapper">
          <div class="avatar" @click="showDropdown = !showDropdown">
            {{ userInitials }}
          </div>
          <Transition name="dropdown">
            <div v-if="showDropdown" class="avatar-dropdown">
              <div class="dropdown-user">
                <div class="dropdown-name">{{ auth.user.value?.name }}</div>
                <div class="dropdown-email">{{ auth.user.value?.email }}</div>
              </div>
              <div class="dropdown-divider"></div>
              <button class="dropdown-logout" :disabled="loggingOut" @click="handleLogout">
                <template v-if="loggingOut">
                  <span class="logout-spinner"></span>
                  Signing out...
                </template>
                <template v-else>
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                    <polyline points="16 17 21 12 16 7"/>
                    <line x1="21" y1="12" x2="9" y2="12"/>
                  </svg>
                  Sign out
                </template>
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </nav>

    <div class="page-body">
      <FileUpload />
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import FileUpload from '../components/FileUpload.vue'

const router = useRouter()
const auth = useAuth()

const showDropdown = ref(false)
const loggingOut = ref(false)
const avatarWrapper = ref(null)

const userInitials = computed(() => {
  if (!auth.user.value?.name) return '?'
  return auth.user.value.name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)
})

async function handleLogout() {
  loggingOut.value = true
  try {
    await auth.logout()
    router.push({ name: 'login' })
  } finally {
    loggingOut.value = false
  }
}

function handleClickOutside(e) {
  if (avatarWrapper.value && !avatarWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
}

onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => document.removeEventListener('click', handleClickOutside))
</script>

<style scoped>
.top-nav {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: #fff;
  border-bottom: 1px solid #e8eaf0;
  display: flex;
  align-items: center;
  padding: 0 28px;
  z-index: 100;
}

.nav-brand {
  display: flex;
  align-items: center;
  gap: 9px;
  flex-shrink: 0;
  margin-right: 28px;
}

.brand-icon {
  width: 30px;
  height: 30px;
  background: #4f46e5;
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.brand-name {
  font-weight: 700;
  font-size: 15px;
  color: #1e1b4b;
  letter-spacing: -0.3px;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 1px;
  flex: 1;
}

.nav-link {
  padding: 5px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #9ca3af;
  cursor: default;
}

.nav-link.active {
  font-weight: 600;
  color: #4f46e5;
  background: #eef2ff;
}

.nav-right {
  display: flex;
  align-items: center;
  gap: 10px;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #22c55e;
}

.avatar-wrapper {
  position: relative;
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #818cf8, #4f46e5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: opacity 0.15s;
}

.avatar:hover {
  opacity: 0.85;
}

.avatar-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 220px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.06);
  border: 1px solid #e8eaf0;
  overflow: hidden;
  z-index: 200;
}

.dropdown-user {
  padding: 14px 16px;
}

.dropdown-name {
  font-size: 13px;
  font-weight: 600;
  color: #1e1b4b;
}

.dropdown-email {
  font-size: 12px;
  color: #9ca3af;
  margin-top: 2px;
}

.dropdown-divider {
  height: 1px;
  background: #e8eaf0;
}

.dropdown-logout {
  width: 100%;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 8px;
  background: none;
  border: none;
  font-family: inherit;
  font-size: 13px;
  font-weight: 500;
  color: #dc2626;
  cursor: pointer;
  transition: background 0.12s;
}

.dropdown-logout:hover:not(:disabled) {
  background: #fef2f2;
}

.dropdown-logout:disabled {
  color: #9ca3af;
  cursor: not-allowed;
}

.logout-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid #e5e7eb;
  border-top-color: #9ca3af;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s, transform 0.15s;
}

.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}

.page-body {
  padding-top: 56px;
  min-height: 100vh;
  background: #f2f3f8;
}
</style>
