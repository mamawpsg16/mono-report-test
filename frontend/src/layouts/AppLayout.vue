<template>
  <div class="app-shell">
    <div v-if="isMobile && sidebarOpen" class="sidebar-backdrop" @click="sidebarOpen = false"></div>

    <aside class="sidebar" :class="{ 'is-open': sidebarOpen }">
      <div class="sidebar-brand">
        <div class="brand-icon">
          <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
            <path d="M7.5 11.5V3.5M4 7l3.5-3.5L11 7" stroke="#fff" stroke-width="1.8"
              stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <span class="brand-name">DataForge</span>
      </div>
      <nav class="sidebar-nav">
        <router-link to="/" class="nav-link" exact-active-class="active">
          Dashboard
        </router-link>
        <router-link
          v-if="auth.can('customers.view')"
          to="/customers"
          class="nav-link"
          exact-active-class="active"
        >
          Customers
        </router-link>
        <router-link
          v-if="auth.can('roles.manage')"
          to="/users"
          class="nav-link"
          exact-active-class="active"
        >
          Users
        </router-link>
        <router-link
          v-if="auth.can('roles.manage')"
          to="/roles"
          class="nav-link"
          exact-active-class="active"
        >
          Roles
        </router-link>
      </nav>
    </aside>

    <div class="main-area">
      <div class="topbar">
        <button v-if="isMobile" class="menu-toggle" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle menu">
          <Menu :size="18" :stroke-width="2" />
        </button>
        <h1 class="page-title">{{ route.meta.title }}</h1>
        <span class="topbar-spacer"></span>
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

      <div class="page-body">
        <router-view />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { Menu } from '@lucide/vue'
import { useAuth } from '@/composables/useAuth'
import { useIsMobile } from '@/composables/useIsMobile'

const router = useRouter()
const route = useRoute()
const auth = useAuth()
const isMobile = useIsMobile()

const showDropdown = ref(false)
const loggingOut = ref(false)
const avatarWrapper = ref(null)
const sidebarOpen = ref(false)

watch(isMobile, (mobile) => {
  if (!mobile) sidebarOpen.value = false
})

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
.app-shell {
  display: flex;
  height: 100vh;
  overflow: hidden;
}

.sidebar {
  width: 232px;
  min-width: 232px;
  background: var(--color-ink);
  display: flex;
  flex-direction: column;
}

.sidebar-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 250;
}

.menu-toggle {
  border: none;
  background: none;
  cursor: pointer;
  color: var(--color-text);
  padding: 6px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.menu-toggle:hover {
  background: var(--color-surface-hover);
}

@media (max-width: 640px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    z-index: 300;
    transform: translateX(-100%);
    transition: transform 0.2s ease;
  }
  .sidebar.is-open {
    transform: translateX(0);
  }
}

.sidebar-brand {
  height: 52px;
  min-height: 52px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 0 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-icon {
  width: 30px;
  height: 30px;
  background: var(--color-accent);
  border-radius: 7px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.brand-name {
  font-weight: 700;
  font-size: 17px;
  color: #fff;
  letter-spacing: -0.3px;
}

.sidebar-nav {
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-link {
  padding: 10px 12px;
  border-radius: 6px;
  font-size: 14.5px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.55);
  cursor: pointer;
  text-decoration: none;
  display: block;
}

.nav-link.active {
  font-weight: 600;
  color: #fff;
  background: rgba(var(--color-accent-rgb), 0.35);
}

.main-area {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.topbar {
  height: 52px;
  min-height: 52px;
  background: var(--color-surface);
  border-bottom: 1px solid var(--color-border);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 28px;
  flex-shrink: 0;
  position: relative;
  z-index: 10;
}

.page-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-ink);
  letter-spacing: -0.2px;
  margin: 0;
}

.topbar-spacer {
  flex: 1;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: var(--color-success);
}

.avatar-wrapper {
  position: relative;
}

.avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-accent);
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
  background: var(--color-surface);
  border-radius: 10px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.06);
  border: 1px solid var(--color-border);
  overflow: hidden;
  z-index: 200;
}

.dropdown-user {
  padding: 14px 16px;
}

.dropdown-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-ink);
}

.dropdown-email {
  font-size: 12px;
  color: var(--color-text-muted);
  margin-top: 2px;
}

.dropdown-divider {
  height: 1px;
  background: var(--color-border);
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
  color: var(--color-danger);
  cursor: pointer;
  transition: background 0.12s;
}

.dropdown-logout:hover:not(:disabled) {
  background: var(--color-danger-soft);
}

.dropdown-logout:disabled {
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.logout-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-text-muted);
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
  flex: 1;
  overflow-y: auto;
  /* always reserve the vertical scrollbar's gutter so it appearing when data
     loads doesn't nudge the table sideways (part of the refresh "shake" fix) */
  scrollbar-gutter: stable;
  background: var(--color-bg);
}
</style>
