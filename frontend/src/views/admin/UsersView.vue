<template>
  <div class="users-view">
    <DatatableServer
      v-model="searchInput"
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
      <template #item-action="user">
        <div class="row-actions">
          <button class="btn-icon" aria-label="Edit user details" @click="openDetails(user)">
            <Pencil :size="15" :stroke-width="2" />
          </button>
          <button class="btn-icon" aria-label="Manage roles" @click="openRoles(user)">
            <UserCog :size="15" :stroke-width="2" />
          </button>
        </div>
      </template>
    </DatatableServer>

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
        <div class="transfer">
          <div class="transfer-col">
            <div class="transfer-label">Role List</div>
            <input v-model="availSearch" class="transfer-search" type="text" placeholder="Search role…" aria-label="Search available roles" />
            <div class="transfer-btns">
              <button class="xfer-btn" title="Assign all" :disabled="!available.length" @click="assignNames(available)">
                <ChevronsRight :size="15" :stroke-width="2" />
              </button>
              <button class="xfer-btn" title="Assign selected" :disabled="!availSel.size" @click="assignNames([...availSel])">
                <ArrowRight :size="15" :stroke-width="2" />
              </button>
            </div>
            <ul class="transfer-list">
              <li
                v-for="name in availableFiltered"
                :key="name"
                class="transfer-item"
                :class="{ selected: availSel.has(name) }"
                @click="toggleAvail(name)"
                @dblclick="assignNames([name])"
              >
                {{ name }}
              </li>
              <li v-if="!availableFiltered.length" class="transfer-empty">No roles</li>
            </ul>
          </div>

          <div class="transfer-col">
            <div class="transfer-label">Assigned Roles</div>
            <input v-model="assignSearch" class="transfer-search" type="text" placeholder="Search role…" aria-label="Search assigned roles" />
            <div class="transfer-btns">
              <button class="xfer-btn" title="Remove selected" :disabled="!assignSel.size" @click="unassignNames([...assignSel])">
                <ArrowLeft :size="15" :stroke-width="2" />
              </button>
              <button class="xfer-btn" title="Remove all" :disabled="!assigned.length" @click="unassignNames(assigned)">
                <ChevronsLeft :size="15" :stroke-width="2" />
              </button>
            </div>
            <ul class="transfer-list">
              <li
                v-for="name in assignedFiltered"
                :key="name"
                class="transfer-item"
                :class="{ selected: assignSel.has(name) }"
                @click="toggleAssign(name)"
                @dblclick="unassignNames([name])"
              >
                {{ name }}
              </li>
              <li v-if="!assignedFiltered.length" class="transfer-empty">No roles</li>
            </ul>
          </div>
        </div>

        <div class="modal-actions">
          <button class="btn-ghost" :disabled="!assigned.length" @click="clearAssigned">Clear</button>
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
import { Pencil, UserCog, ArrowRight, ArrowLeft, ChevronsRight, ChevronsLeft } from '@lucide/vue'
import api from '@/helpers/api'
import { usePagination } from '@/composables/usePagination'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import DatatableServer from '@/components/table/DatatableServer.vue'
import AppModal from '@/components/AppModal.vue'

const toast = useToast()

// Action leads the row so the row's controls are the first thing scanned; the
// user's current roles live in the Manage-roles modal, not a list column.
const headers = [
  { text: 'Action', value: 'action', width: 110 },
  { text: 'Name', value: 'name' },
  { text: 'Email', value: 'email' },
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
  // roles are the same for every user, so fetch the catalog once up front
  try {
    const { data } = await api.get('/api/roles')
    allRoles.value = data
  } catch {
    // non-fatal: the roles modal will just show an empty Role List
  }
  fetchUsers()
})

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
  // the confirm dialog owns the loading spinner + error while onConfirm runs
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

// --- manage roles modal (transfer picker) ---
const rolesOpen = ref(false)
const editingRoles = ref(null)
const assigned = ref([]) // working copy of role names on the user
const original = ref([]) // baseline as loaded, for the dirty check
const availSearch = ref('')
const assignSearch = ref('')
const availSel = ref(new Set()) // highlighted names in the Role List column
const assignSel = ref(new Set()) // highlighted names in the Assigned column

const rolesTitle = computed(() =>
  editingRoles.value ? `Roles — ${editingRoles.value.name}` : 'Roles'
)

// available = every role in the catalog not currently assigned
const available = computed(() =>
  allRoles.value.map((r) => r.name).filter((name) => !assigned.value.includes(name))
)

const availableFiltered = computed(() => filterByName(available.value, availSearch.value))
const assignedFiltered = computed(() => filterByName(assigned.value, assignSearch.value))

const rolesDirty = computed(() => !sameMembers(assigned.value, original.value))

function openRoles(user) {
  editingRoles.value = user
  const names = user.roles.map((r) => r.name)
  assigned.value = [...names]
  original.value = [...names]
  availSearch.value = ''
  assignSearch.value = ''
  availSel.value = new Set()
  assignSel.value = new Set()
  rolesOpen.value = true
}

function toggleAvail(name) {
  availSel.value = toggled(availSel.value, name)
}
function toggleAssign(name) {
  assignSel.value = toggled(assignSel.value, name)
}

function assignNames(names) {
  const add = names.filter((n) => !assigned.value.includes(n))
  if (add.length) assigned.value = [...assigned.value, ...add]
  availSel.value = new Set()
}

function unassignNames(names) {
  const remove = new Set(names)
  assigned.value = assigned.value.filter((n) => !remove.has(n))
  assignSel.value = new Set()
}

function clearAssigned() {
  unassignNames([...assigned.value])
}

async function saveRoles() {
  const user = editingRoles.value
  // confirm dialog shows the spinner while saving, and surfaces the server's
  // reason (e.g. the last-admin guard) if onConfirm throws
  const ok = await confirm({
    title: 'Save role changes?',
    text: `Update roles for ${user.name}?`,
    confirmText: 'Save',
    onConfirm: async () => {
      const { data } = await api.put(`/api/users/${user.id}/roles`, {
        roles: assigned.value,
      })
      const row = users.value.find((u) => u.id === user.id)
      if (row) row.roles = data.roles
    },
  })
  if (!ok) return
  rolesOpen.value = false
  toast.success('Roles updated')
}

// --- small helpers ---
function filterByName(names, term) {
  const q = term.trim().toLowerCase()
  return q ? names.filter((n) => n.toLowerCase().includes(q)) : names
}

function toggled(set, name) {
  const next = new Set(set)
  next.has(name) ? next.delete(name) : next.add(name)
  return next
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
.btn-icon {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: 6px;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--color-text-muted);
}
.btn-icon:hover {
  background: var(--color-surface-hover);
  color: var(--color-text);
}

/* --- modal content wrapper (AppModal's body has no padding of its own) --- */
.modal-content {
  padding: 22px 24px 24px;
}

/* --- edit-details fields --- */
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

/* --- transfer (dual-list) picker --- */
.transfer {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.transfer-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.transfer-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--color-ink);
}
.transfer-search {
  box-sizing: border-box;
  padding: 9px 12px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-surface-hover);
}
.transfer-search:focus {
  outline: none;
  border-color: var(--color-accent-border);
  background: var(--color-surface);
}

.transfer-btns {
  display: flex;
  gap: 8px;
}
.xfer-btn {
  width: 36px;
  height: 32px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.xfer-btn:hover:not(:disabled) {
  background: var(--color-surface-hover);
  color: var(--color-text);
}
.xfer-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.transfer-list {
  list-style: none;
  margin: 0;
  padding: 6px;
  min-height: 240px;
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: 8px;
}
.transfer-item {
  padding: 9px 11px;
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text);
  cursor: pointer;
  user-select: none;
}
.transfer-item:hover {
  background: var(--color-surface-hover);
}
.transfer-item.selected {
  background: rgba(var(--color-accent-rgb), 0.14);
  color: var(--color-accent);
  font-weight: 600;
}
.transfer-empty {
  list-style: none;
  padding: 16px 10px;
  font-size: 12.5px;
  color: var(--color-text-muted);
  text-align: center;
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
.btn-ghost {
  padding: 9px 16px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.btn-ghost:hover:not(:disabled) {
  background: var(--color-surface-hover);
}
.btn-ghost:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.btn-primary {
  padding: 9px 18px;
  border-radius: 7px;
  border: 1px solid var(--color-accent);
  background: var(--color-accent);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.15s;
}
.btn-primary:hover:not(:disabled) {
  opacity: 0.88;
}
.btn-primary:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
