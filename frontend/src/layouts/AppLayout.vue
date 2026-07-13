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
        <button v-if="isMobile" class="sidebar-close" @click="sidebarOpen = false" aria-label="Close menu">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section">
          <div class="nav-section-label">General</div>
          <router-link to="/" class="nav-link" exact-active-class="active">
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
              </svg>
            </span>
            <span class="nav-text">Dashboard</span>
            <span class="nav-dot"></span>
          </router-link>
          <router-link
            v-if="auth.can('customers.view')"
            to="/customers"
            class="nav-link"
            exact-active-class="active"
          >
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </span>
            <span class="nav-text">Customers</span>
            <span class="nav-dot"></span>
          </router-link>
          <router-link
            v-if="auth.can('prospects.view')"
            to="/prospects"
            class="nav-link"
            exact-active-class="active"
          >
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11l-3 3-2-2"/>
              </svg>
            </span>
            <span class="nav-text">Prospects</span>
            <span class="nav-dot"></span>
          </router-link>
          <router-link
            v-if="auth.can('visits.view')"
            to="/visits"
            class="nav-link"
            exact-active-class="active"
          >
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
              </svg>
            </span>
            <span class="nav-text">Visits</span>
            <span class="nav-dot"></span>
          </router-link>
        </div>

        <div v-if="auth.can('roles.manage')" class="nav-section">
          <div class="nav-section-label">Administration</div>
          <router-link to="/users" class="nav-link" exact-active-class="active">
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
              </svg>
            </span>
            <span class="nav-text">Users</span>
            <span class="nav-dot"></span>
          </router-link>
          <router-link to="/roles" class="nav-link" exact-active-class="active">
            <span class="nav-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4z"/>
              </svg>
            </span>
            <span class="nav-text">Roles</span>
            <span class="nav-dot"></span>
          </router-link>
        </div>
      </nav>
    </aside>

    <div class="main-area">
      <div class="topbar">
        <button v-if="isMobile" class="menu-toggle" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle menu">
          <Menu :size="18" :stroke-width="2" />
        </button>
        <!-- topbar carries the description; the module name lives in the
             sidebar (active link) and each view's card title -->
        <h1 class="page-title">{{ route.meta.subtitle || route.meta.title }}</h1>
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

// On mobile the drawer sits over the content, so it must close itself once a
// nav link actually navigates — otherwise it lingers over the page you just
// opened. No-op on desktop, where sidebarOpen is always false.
watch(() => route.path, () => {
  sidebarOpen.value = false
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
  width: 248px;
  min-width: 248px;
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

.sidebar-close {
  margin-left: auto;
  border: none;
  background: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 4px;
  border-radius: 6px;
}
.sidebar-close:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
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
  gap: 22px;
}

.nav-section {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-section-label {
  padding: 0 12px 8px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.45);
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 12px;
  border-radius: 9px;
  font-size: 14px;
  font-weight: 500;
  color: rgba(255, 255, 255, 0.6);
  cursor: pointer;
  text-decoration: none;
  transition: background 0.15s ease, color 0.15s ease;
}

.nav-link:hover:not(.active) {
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.9);
}

.nav-icon {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.nav-text {
  flex: 1;
}

.nav-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #fff;
  opacity: 0;
  flex-shrink: 0;
}

.nav-link.active {
  font-weight: 600;
  color: #fff;
  background: linear-gradient(90deg, var(--color-accent), rgba(var(--color-accent-rgb), 0.82));
  box-shadow: 0 4px 14px rgba(var(--color-accent-rgb), 0.35);
}

.nav-link.active .nav-dot {
  opacity: 0.9;
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
