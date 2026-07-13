<template>
  <div class="users-view">
    <DatatableServer
      v-model="searchInput"
      :title="$route.meta.title"
      search-placeholder="Search users..."
      v-model:page="page"
      v-model:per-page="perPage"
      :last-page="lastPage"
      :total="total"
      :headers="headers"
      :items="users"
      :loading="loading"
      empty-message="No users found"
    >
      <template #actions>
        <button
          class="btn-icon"
          :disabled="loading"
          aria-label="Refresh list"
          title="Refresh (pull in users added by others)"
          @click="fetchUsers"
        >
          <RefreshCw :size="15" :stroke-width="2" :class="{ spinning: loading }" />
        </button>
        <button class="btn-primary" @click="openCreate">
          <Plus :size="15" :stroke-width="2" />
          New user
        </button>
      </template>

      <template #item-action="user">
        <div class="row-actions">
          <button class="btn-icon" aria-label="Edit user details" @click="openDetails(user)">
            <Pencil :size="15" :stroke-width="2" />
          </button>
          <button class="btn-icon" aria-label="Manage roles" @click="openRoles(user)">
            <UserCog :size="15" :stroke-width="2" />
          </button>
          <button
            v-if="user.must_change_password"
            class="btn-icon"
            aria-label="Resend invitation"
            title="Resend invitation"
            @click="resendInvitation(user)"
          >
            <Send :size="15" :stroke-width="2" />
          </button>
          <button
            class="btn-icon"
            :class="{ 'is-danger': user.is_active }"
            :disabled="user.id === currentUserId"
            :aria-label="user.is_active ? 'Deactivate user' : 'Activate user'"
            :title="user.id === currentUserId ? 'You cannot deactivate yourself' : (user.is_active ? 'Deactivate' : 'Activate')"
            @click="toggleActive(user)"
          >
            <Power :size="15" :stroke-width="2" />
          </button>
        </div>
      </template>

      <template #item-status="user">
        <span v-if="user.must_change_password" class="status-badge is-pending">Invited</span>
        <span v-else class="status-badge" :class="user.is_active ? 'is-on' : 'is-off'">
          {{ user.is_active ? 'Active' : 'Inactive' }}
        </span>
      </template>
    </DatatableServer>

    <!-- Create user (invite) -->
    <AppModal v-model="createOpen" title="New user" max-width="md">
      <div class="modal-content">
        <label class="field">
          <span class="field-label">Name</span>
          <input v-model.trim="createForm.name" class="field-input" type="text" aria-label="Name" />
        </label>
        <label class="field">
          <span class="field-label">Email</span>
          <input v-model.trim="createForm.email" class="field-input" type="email" aria-label="Email" />
        </label>
        <div class="field">
          <span class="field-label">Roles</span>
          <TransferList
            v-model="createRoles"
            :options="roleOptions"
            left-label="Role List"
            right-label="Assigned Roles"
            search-placeholder="Search role…"
            empty-text="No roles"
          />
        </div>

        <p class="invite-note">
          An invitation email with a set-password link is sent to this address. The
          account stays <strong>Invited</strong> until they set their password.
        </p>

        <div class="modal-actions">
          <span class="spacer"></span>
          <button class="btn-ghost" @click="createOpen = false">Cancel</button>
          <button class="btn-primary" :disabled="!canCreate || creating" @click="submitCreate">
            {{ creating ? 'Sending…' : 'Create & send invite' }}
          </button>
        </div>
      </div>
    </AppModal>

    <!-- Edit user details -->
    <AppModal v-model="detailsOpen" title="Edit user" max-width="sm">
      <div class="modal-content">
        <label class="field">
          <span class="field-label">Name</span>
          <input v-model.trim="form.name" class="field-input" type="text" aria-label="Name" />
        </label>
        <label class="field">
          <span class="field-label">Email</span>
          <input v-model.trim="form.email" class="field-input" type="email" aria-label="Email" />
        </label>
        <div class="modal-actions">
          <span class="spacer"></span>
          <button class="btn-ghost" @click="detailsOpen = false">Cancel</button>
          <button class="btn-primary" @click="saveDetails">Save</button>
        </div>
      </div>
    </AppModal>

    <!-- Manage roles (transfer picker) -->
    <AppModal v-model="rolesOpen" :title="rolesTitle" max-width="lg">
      <div class="modal-content">
        <TransferList
          v-model="assignedRoles"
          :options="roleOptions"
          left-label="Role List"
          right-label="Assigned Roles"
          search-placeholder="Search role…"
          empty-text="No roles"
        />

        <div class="modal-actions">
          <button class="btn-ghost" :disabled="!assignedRoles.length" @click="assignedRoles = []">Clear</button>
          <span class="spacer"></span>
          <button class="btn-ghost" @click="rolesOpen = false">Close</button>
          <button class="btn-primary" :disabled="!rolesDirty" @click="saveRoles">Save</button>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { Pencil, UserCog, Plus, Power, Send, RefreshCw } from '@lucide/vue'
import api from '@/helpers/api'
import { useAuth } from '@/composables/useAuth'
import { usePagination } from '@/composables/usePagination'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import DatatableServer from '@/components/table/DatatableServer.vue'
import AppModal from '@/components/AppModal.vue'
import TransferList from '@/components/TransferList.vue'

const toast = useToast()
const auth = useAuth()

// used to block deactivating your own account (the server enforces it too)
const currentUserId = computed(() => auth.user.value?.id)

// Action leads the row so the row's controls are the first thing scanned; the
// user's current roles live in the Manage-roles modal, not a list column.
const headers = [
  { text: 'Action', value: 'action', width: 140 },
  { text: 'Name', value: 'name' },
  { text: 'Email', value: 'email' },
  { text: 'Status', value: 'status', width: 120 },
]

// --- users list (server-paginated, mirrors customers/Index.vue) ---
const users = ref([])
const allRoles = ref([])
const loading = ref(false)
const { page, perPage, lastPage, total } = usePagination()
const searchInput = ref('')
const search = ref('')

let searchDebounce = null
let abortController = null

async function fetchUsers() {
  abortController?.abort()
  const myController = new AbortController()
  abortController = myController
  loading.value = true
  try {
    const { data } = await api.get('/api/users', {
      params: { page: page.value, per_page: perPage.value, search: search.value || undefined },
      signal: myController.signal,
    })
    users.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
  } catch (err) {
    if (err.code !== 'ERR_CANCELED') throw err
  } finally {
    if (!myController.signal.aborted) loading.value = false
  }
}

watch(searchInput, (value) => {
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    search.value = value
    page.value = 1
  }, 500)
})

watch([page, perPage, search], fetchUsers)

onMounted(async () => {
  // fire immediately so `loading` flips true before first paint (matches
  // customers/Index.vue) -- awaiting roles first left a gap where the table
  // rendered once with loading=false, items=[] ("No users found" flash)
  // before this ever ran.
  fetchUsers()

  // roles are the same for every user, so fetch the catalog once up front,
  // in parallel with the users list rather than blocking it
  try {
    const { data } = await api.get('/api/roles')
    allRoles.value = data
  } catch {
    // non-fatal: the roles modal will just show an empty Role List
  }
})

// catalog fed to every transfer list — the same for every user
const roleOptions = computed(() => allRoles.value.map((r) => ({ value: r.name, label: r.name })))

// --- create user modal ---
const createOpen = ref(false)
const createForm = reactive({ name: '', email: '' })
const createRoles = ref([])
const creating = ref(false)
const canCreate = computed(() => createForm.name.trim() && createForm.email.trim())

function openCreate() {
  createForm.name = ''
  createForm.email = ''
  createRoles.value = []
  creating.value = false
  createOpen.value = true
}

async function submitCreate() {
  creating.value = true
  try {
    // store() creates the user, emails the invite, and returns the row
    const { data } = await api.post('/api/users', {
      name: createForm.name,
      email: createForm.email,
      roles: createRoles.value,
    })
    addUserToList(data) // drop the new row in without a refetch
    toast.success('Invitation sent')
    createOpen.value = false
  } catch (err) {
    const msg =
      err.response?.data?.errors?.email?.[0] ||
      err.response?.data?.message ||
      'Could not create user'
    toast.error(msg)
  } finally {
    creating.value = false
  }
}

// insert a freshly created user without a refetch. On page 1 with no active
// search it belongs at the top (the list is newest-first); anywhere else, sort
// and pagination would misplace an in-place insert, so refetch instead.
function addUserToList(user) {
  if (page.value !== 1 || search.value) {
    fetchUsers()
    return
  }
  users.value = [user, ...users.value].slice(0, perPage.value)
  total.value += 1
  lastPage.value = Math.max(1, Math.ceil(total.value / perPage.value))
}

// Re-send the invitation link (e.g. it expired or was lost). Only offered for
// still-pending users; the server rejects it once they've set a password.
async function resendInvitation(user) {
  const ok = await confirm({
    title: 'Resend invitation?',
    text: `Send a fresh set-password link to ${user.email}? Any previous link stops working.`,
    confirmText: 'Resend',
    onConfirm: async () => {
      await api.post(`/api/users/${user.id}/resend-invitation`)
    },
  })
  if (!ok) return
  toast.success('Invitation sent')
}

// --- activate / deactivate ---
async function toggleActive(user) {
  const deactivating = user.is_active
  const ok = await confirm({
    title: deactivating ? 'Deactivate user?' : 'Activate user?',
    text: deactivating
      ? `${user.name} will no longer be able to sign in.`
      : `${user.name} will be able to sign in again.`,
    confirmText: deactivating ? 'Deactivate' : 'Activate',
    // the confirm dialog owns the spinner + surfaces the server's reason
    // (self / last-admin guard) if onConfirm throws
    onConfirm: async () => {
      const { data } = await api.patch(`/api/users/${user.id}/active`, { active: !user.is_active })
      const row = users.value.find((u) => u.id === user.id)
      if (row) row.is_active = data.is_active
    },
  })
  if (!ok) return
  toast.success(deactivating ? 'User deactivated' : 'User activated')
}

// --- edit user details modal ---
const detailsOpen = ref(false)
const editingDetail = ref(null)
const form = reactive({ name: '', email: '' })

function openDetails(user) {
  editingDetail.value = user
  form.name = user.name
  form.email = user.email
  detailsOpen.value = true
}

async function saveDetails() {
  const user = editingDetail.value
  const ok = await confirm({
    title: 'Save changes?',
    text: `Update details for ${user.name}?`,
    confirmText: 'Save',
    onConfirm: async () => {
      const { data } = await api.put(`/api/users/${user.id}`, {
        name: form.name,
        email: form.email,
      })
      const row = users.value.find((u) => u.id === user.id)
      if (row) {
        row.name = data.name
        row.email = data.email
      }
    },
  })
  if (!ok) return
  detailsOpen.value = false
  toast.success('User updated')
}

// --- manage roles modal ---
const rolesOpen = ref(false)
const editingRoles = ref(null)
const assignedRoles = ref([]) // this user's role names (transfer v-model)
let originalRoles = [] // baseline as loaded, for the dirty check

const rolesTitle = computed(() =>
  editingRoles.value ? `Roles — ${editingRoles.value.name}` : 'Roles'
)

const rolesDirty = computed(() => !sameMembers(assignedRoles.value, originalRoles))

function openRoles(user) {
  editingRoles.value = user
  const names = user.roles.map((r) => r.name)
  assignedRoles.value = [...names]
  originalRoles = [...names]
  rolesOpen.value = true
}

async function saveRoles() {
  const user = editingRoles.value
  const ok = await confirm({
    title: 'Save role changes?',
    text: `Update roles for ${user.name}?`,
    confirmText: 'Save',
    onConfirm: async () => {
      const { data } = await api.put(`/api/users/${user.id}/roles`, {
        roles: assignedRoles.value,
      })
      const row = users.value.find((u) => u.id === user.id)
      if (row) row.roles = data.roles
    },
  })
  if (!ok) return
  rolesOpen.value = false
  toast.success('Roles updated')
}

// order-independent membership compare for the dirty check
function sameMembers(a, b) {
  if (a.length !== b.length) return false
  const set = new Set(b)
  return a.every((x) => set.has(x))
}
</script>

<style scoped>
.users-view {
  padding: 24px 36px;
}

@media (max-width: 640px) {
  .users-view { padding: 16px; }
}

/* --- table cells --- */
.row-actions {
  display: flex;
  gap: 8px;
}
/* buttons (.btn-icon / .btn-icon.is-danger) come from the global system in App.vue */
.spinning {
  animation: spin 0.7s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.status-badge.is-on {
  color: var(--color-success);
  background: var(--color-success-soft);
}
.status-badge.is-off {
  color: var(--color-text-muted);
  background: var(--color-surface-hover);
}
/* invited but not yet activated (hasn't set their password) */
.status-badge.is-pending {
  color: var(--color-accent);
  background: rgba(var(--color-accent-rgb), 0.12);
}

/* --- modal content wrapper (AppModal's body has no padding of its own) --- */
.modal-content {
  padding: 22px 24px 24px;
}

/* --- fields --- */
.field {
  display: block;
  margin-bottom: 18px;
}
.field-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-muted);
  margin-bottom: 7px;
}
.field-input {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 13px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13.5px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-surface-hover);
}
.field-input:focus {
  outline: none;
  border-color: var(--color-accent-border);
  background: var(--color-surface);
}

/* --- invite hint under the create form --- */
.invite-note {
  font-size: 13px;
  color: var(--color-text-muted);
  line-height: 1.5;
  margin: -4px 0 4px;
}

/* --- shared modal footer: full-width divider, flush to panel edges --- */
.modal-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 24px -24px -24px;
  padding: 16px 24px;
  border-top: 1px solid var(--color-border-subtle);
}
.spacer {
  flex: 1;
}
/* .btn-ghost / .btn-primary come from the global system in App.vue */
</style>
