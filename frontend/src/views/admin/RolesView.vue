<template>
  <div class="roles-view">
    <p v-if="loadError" class="feedback error">{{ loadError }}</p>

    <DatatableClient
      v-else
      :headers="listHeaders"
      :items="roles"
      :loading="loading"
      :title="$route.meta.title"
      search-placeholder="Search roles..."
      empty-message="No roles found"
    >
      <template #actions>
        <button class="btn-primary" @click="openCreate">
          <Plus :size="15" :stroke-width="2.2" />
          New role
        </button>
      </template>
      <template #item-action="role">
        <div class="row-actions">
          <button class="btn-icon" aria-label="Edit role" @click="openEdit(role)">
            <Pencil :size="15" :stroke-width="2" />
          </button>
        </div>
      </template>
      <template #item-name="role">
        <span class="cell-name">{{ role.name }}</span>
      </template>
      <template #item-access="role">
        <span class="access-text">{{ accessSummary(role) }}</span>
      </template>
    </DatatableClient>

    <AppModal v-model="modalOpen" :title="modalTitle" max-width="md">
      <div class="modal-content">
        <label class="field">
          <span class="field-label">Role name</span>
          <input
            v-model.trim="form.name"
            class="field-input"
            type="text"
            placeholder="e.g. Support agent"
            aria-label="Role name"
          />
        </label>

        <div class="perm-head">
          <span class="field-label">Permissions</span>
          <input
            v-model="resourceFilter"
            class="perm-filter"
            type="text"
            placeholder="Filter resources..."
            aria-label="Filter resources"
          />
        </div>

        <div class="perm-grid-scroll">
          <div class="perm-grid">
            <div class="perm-grid-head" :style="{ gridTemplateColumns: gridTemplate }">
              <label class="grid-check-cell">
                <input
                  type="checkbox"
                  :checked="headerAllChecked"
                  aria-label="Select all permissions"
                  @change="toggleAll"
                />
              </label>
              <span class="grid-resource-head">Resource</span>
              <span v-for="action in allActions" :key="action" class="grid-action-head">
                {{ humanize(action) }}
              </span>
            </div>

            <div
              v-for="row in gridRows"
              :key="row.module"
              class="perm-grid-row"
              :style="{ gridTemplateColumns: gridTemplate }"
            >
              <label class="grid-check-cell">
                <input
                  type="checkbox"
                  :checked="moduleChecked(row.module)"
                  :aria-label="`Select all ${row.label}`"
                  @change="toggleModule(row.module)"
                />
              </label>
              <span class="grid-resource">{{ row.label }}</span>
              <span v-for="cell in row.cells" :key="cell.action" class="grid-cell">
                <input
                  v-if="cell.name"
                  type="checkbox"
                  :checked="assignedPerms.includes(cell.name)"
                  :aria-label="cell.name"
                  @change="togglePerm(cell.name)"
                />
                <span v-else class="grid-na">—</span>
              </span>
            </div>

            <div v-if="!gridRows.length" class="grid-empty">No matching permissions</div>
          </div>
        </div>

        <div class="modal-actions">
          <span class="spacer"></span>
          <button class="btn-ghost" @click="modalOpen = false">Cancel</button>
          <button class="btn-primary" :disabled="!canSave" @click="save">Save</button>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Plus, Pencil } from '@lucide/vue'
import api from '@/helpers/api'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppModal from '@/components/AppModal.vue'
import DatatableClient from '@/components/table/DatatableClient.vue'

const toast = useToast()

const listHeaders = [
  { text: 'Action', value: 'action', width: 90 },
  { text: 'Name', value: 'name', width: 240 },
  { text: 'Roles & access', value: 'access' },
]

// preferred action-column order; anything else is appended after
const ACTION_ORDER = ['view', 'create', 'update', 'delete', 'manage']

const roles = ref([])
const permissions = ref([]) // the full permission catalog
const loading = ref(true)
const loadError = ref('')

// permissions are `module.action`; split on the first dot
const parsed = computed(() =>
  permissions.value.map((p) => {
    const dot = p.name.indexOf('.')
    return {
      name: p.name,
      module: dot === -1 ? p.name : p.name.slice(0, dot),
      action: dot === -1 ? p.name : p.name.slice(dot + 1),
    }
  })
)

const modules = computed(() => [...new Set(parsed.value.map((p) => p.module))])
const allPermNames = computed(() => parsed.value.map((p) => p.name))

function humanize(word) {
  const w = word.replaceAll('_', ' ')
  return w.charAt(0).toUpperCase() + w.slice(1)
}

// list column: which modules the role touches
function accessSummary(role) {
  const mods = new Set()
  for (const p of role.permissions) {
    const dot = p.name.indexOf('.')
    mods.add(dot === -1 ? p.name : p.name.slice(0, dot))
  }
  return mods.size ? [...mods].map(humanize).join(', ') : 'No access granted'
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const [rolesRes, permsRes] = await Promise.all([
      api.get('/api/roles'),
      api.get('/api/permissions'),
    ])
    roles.value = rolesRes.data
    permissions.value = permsRes.data
  } catch {
    loadError.value = 'Could not load roles. You may not have permission, or the server is unreachable.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// --- create / edit modal ---
const modalOpen = ref(false)
const editing = ref(null) // the role being edited, or null when creating
const form = reactive({ name: '' })
const assignedPerms = ref([]) // permission names ticked in the grid
const resourceFilter = ref('')
let originalPerms = []
let originalName = ''

const modalTitle = computed(() =>
  editing.value ? `Edit role — ${editing.value.name}` : 'New role'
)

// --- permission grid derivation ---
const allActions = computed(() => {
  const present = new Set(parsed.value.map((p) => p.action))
  const ordered = ACTION_ORDER.filter((a) => present.has(a))
  const extras = [...present].filter((a) => !ACTION_ORDER.includes(a))
  return [...ordered, ...extras]
})

function permOf(module, action) {
  return parsed.value.find((p) => p.module === module && p.action === action)?.name ?? null
}

// one row per module; columns are always every action. The Filter box narrows
// the ROWS by resource name OR action name — typing "roles" or "view" both work.
const gridRows = computed(() => {
  const q = resourceFilter.value.trim().toLowerCase()
  const rows = modules.value.map((module) => ({
    module,
    label: humanize(module),
    cells: allActions.value.map((action) => ({ action, name: permOf(module, action) })),
  }))
  if (!q) return rows
  return rows.filter(
    (row) =>
      row.label.toLowerCase().includes(q) ||
      row.cells.some((c) => c.name && humanize(c.action).toLowerCase().includes(q))
  )
})

const gridTemplate = computed(
  () => `34px minmax(110px, 1fr) repeat(${allActions.value.length}, 78px)`
)

const assignedSet = computed(() => new Set(assignedPerms.value))
const headerAllChecked = computed(
  () => allPermNames.value.length > 0 && allPermNames.value.every((n) => assignedSet.value.has(n))
)

function moduleChecked(module) {
  const names = parsed.value.filter((p) => p.module === module).map((p) => p.name)
  return names.length > 0 && names.every((n) => assignedSet.value.has(n))
}

function togglePerm(name) {
  const set = new Set(assignedPerms.value)
  set.has(name) ? set.delete(name) : set.add(name)
  assignedPerms.value = [...set]
}

function toggleModule(module) {
  const names = parsed.value.filter((p) => p.module === module).map((p) => p.name)
  const set = new Set(assignedPerms.value)
  const allOn = names.every((n) => set.has(n))
  names.forEach((n) => (allOn ? set.delete(n) : set.add(n)))
  assignedPerms.value = [...set]
}

function toggleAll() {
  assignedPerms.value = headerAllChecked.value ? [] : [...allPermNames.value]
}

// --- open / save ---
const canSave = computed(() => {
  if (!form.name.trim()) return false
  if (!editing.value) return true
  return form.name !== originalName || !sameMembers(assignedPerms.value, originalPerms)
})

function openCreate() {
  editing.value = null
  form.name = ''
  assignedPerms.value = []
  originalPerms = []
  originalName = ''
  resourceFilter.value = ''
  modalOpen.value = true
}

function openEdit(role) {
  editing.value = role
  form.name = role.name
  const names = role.permissions.map((p) => p.name)
  assignedPerms.value = [...names]
  originalPerms = [...names]
  originalName = role.name
  resourceFilter.value = ''
  modalOpen.value = true
}

async function save() {
  const isEdit = !!editing.value
  const ok = await confirm({
    title: isEdit ? 'Save role?' : 'Create role?',
    text: isEdit ? `Update "${originalName}"?` : `Create role "${form.name}"?`,
    confirmText: 'Save',
    onConfirm: async () => {
      const payload = { name: form.name, permissions: assignedPerms.value }
      if (isEdit) {
        await api.put(`/api/roles/${editing.value.id}`, payload)
      } else {
        await api.post('/api/roles', payload)
      }
      await load()
    },
  })
  if (!ok) return
  modalOpen.value = false
  toast.success(isEdit ? 'Role updated' : 'Role created')
}

function sameMembers(a, b) {
  if (a.length !== b.length) return false
  const set = new Set(b)
  return a.every((x) => set.has(x))
}
</script>

<style scoped>
.roles-view {
  padding: 28px 36px;
}
@media (max-width: 640px) {
  .roles-view { padding: 16px; }
}

.feedback {
  font-size: 13px;
  padding: 8px 0;
}
.feedback.error { color: var(--color-danger); }

.cell-name { font-weight: 700; color: var(--color-ink); }
.access-text { color: var(--color-text-muted); }

.row-actions { display: flex; gap: 8px; }
/* buttons (.btn-icon / .btn-primary / .btn-ghost) come from the global system in App.vue */

/* --- modal --- */
.modal-content { padding: 24px 28px; }

.field { display: block; margin-bottom: 22px; }
.field-label {
  display: block; font-size: 12px; font-weight: 700;
  letter-spacing: 0.04em; text-transform: uppercase;
  color: var(--color-text-muted); margin-bottom: 9px;
}
.field-input {
  width: 100%; box-sizing: border-box;
  padding: 10px 13px; border-radius: 10px;
  border: 1px solid var(--color-border);
  font-size: 15px; font-family: inherit;
  color: var(--color-text); background: var(--color-surface);
}
.field-input:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px rgba(var(--color-accent-rgb), 0.14);
}

.perm-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}
.perm-head .field-label { margin-bottom: 0; }
.perm-filter {
  width: 180px;
  box-sizing: border-box;
  padding: 6px 11px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13px; font-family: inherit;
  color: var(--color-text); background: var(--color-surface);
}
.perm-filter:focus {
  outline: none;
  border-color: var(--color-accent);
  box-shadow: 0 0 0 3px rgba(var(--color-accent-rgb), 0.14);
}

/* --- permission grid --- */
.perm-grid-scroll { overflow-x: auto; }
.perm-grid {
  min-width: 460px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
}
.perm-grid-head,
.perm-grid-row {
  display: grid;
  align-items: center;
  padding: 11px 16px;
}
.perm-grid-head {
  background: var(--color-surface-hover);
  border-bottom: 1px solid var(--color-border);
}
.perm-grid-row {
  border-bottom: 1px solid var(--color-border-subtle);
}
.perm-grid-row:last-child { border-bottom: none; }

.grid-check-cell { display: flex; align-items: center; }
.grid-check-cell input,
.grid-cell input {
  width: 17px; height: 17px; cursor: pointer;
  accent-color: var(--color-accent);
}
.grid-resource-head,
.grid-action-head {
  font-size: 12.5px; font-weight: 700; color: var(--color-text-muted);
}
.grid-action-head { text-align: center; }
.grid-resource { font-weight: 700; font-size: 14px; color: var(--color-ink); }
.grid-cell { display: flex; justify-content: center; }
.grid-na { color: var(--color-text-faint); }
.grid-empty {
  padding: 18px 16px; text-align: center;
  font-size: 13px; color: var(--color-text-muted);
}

.modal-actions {
  display: flex; align-items: center; gap: 10px;
  margin: 24px -28px -24px; padding: 18px 28px;
  border-top: 1px solid var(--color-border-subtle);
}
.spacer { flex: 1; }
</style>
