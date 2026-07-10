<template>
  <div class="roles-view">
    <div class="card">
      <div class="card-header">
        <p class="card-title">Roles</p>
        <button class="btn-primary" @click="openCreate">
          <Plus :size="15" :stroke-width="2" />
          New role
        </button>
      </div>

      <p v-if="loadError" class="feedback error">{{ loadError }}</p>

      <AppDataTable
        v-else
        :headers="listHeaders"
        :items="roles"
        hide-footer
        :loading="loading"
        empty-message="No roles yet"
      >
        <template #item-action="role">
          <button class="btn-icon" aria-label="Edit role" @click="openEdit(role)">
            <Pencil :size="15" :stroke-width="2" />
          </button>
        </template>
        <template #item-name="role">
          <span class="cell-name">{{ role.name }}</span>
        </template>
      </AppDataTable>
    </div>

    <AppModal v-model="modalOpen" :title="modalTitle" max-width="lg">
      <div class="modal-content">
        <label v-if="!editing" class="field">
          <span class="field-label">Role name</span>
          <input v-model.trim="form.name" class="field-input" type="text" placeholder="e.g. Rewards Admin" aria-label="Role name" />
        </label>

        <div class="section-label">Permissions</div>
        <TransferList
          v-model="assignedPerms"
          :options="permOptions"
          left-label="Permission List"
          right-label="Assigned Permission"
          search-placeholder="Search permission…"
          empty-text="No permissions"
        />

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
import AppDataTable from '@/components/table/AppDataTable.vue'
import TransferList from '@/components/TransferList.vue'

const toast = useToast()

const listHeaders = [
  { text: '', value: 'action', width: 60 },
  { text: 'Name', value: 'name' },
]

const roles = ref([])
const permissions = ref([]) // the full permission catalog
const loading = ref(true)
const loadError = ref('')

// the catalog fed to the transfer list — same for every role
const permOptions = computed(() => permissions.value.map((p) => ({ value: p.name, label: p.name })))

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
const assignedPerms = ref([]) // this role's permission names (transfer v-model)
let originalPerms = []

const modalTitle = computed(() =>
  editing.value ? `Edit role — ${editing.value.name}` : 'New role'
)

const canSave = computed(() => {
  if (!editing.value) return form.name.length > 0 // create: needs a name
  return !sameMembers(assignedPerms.value, originalPerms) // edit: needs a change
})

function openCreate() {
  editing.value = null
  form.name = ''
  assignedPerms.value = []
  originalPerms = []
  modalOpen.value = true
}

function openEdit(role) {
  editing.value = role
  const names = role.permissions.map((p) => p.name)
  assignedPerms.value = [...names]
  originalPerms = [...names]
  modalOpen.value = true
}

async function save() {
  const isEdit = !!editing.value
  const ok = await confirm({
    title: isEdit ? 'Save role?' : 'Create role?',
    text: isEdit
      ? `Update permissions for ${editing.value.name}?`
      : `Create role "${form.name}"?`,
    confirmText: 'Save',
    onConfirm: async () => {
      if (isEdit) {
        await api.put(`/api/roles/${editing.value.id}`, { permissions: assignedPerms.value })
      } else {
        await api.post('/api/roles', { name: form.name, permissions: assignedPerms.value })
      }
      // reload so the list reflects the server's canonical state
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
  padding: 24px 36px;
}
@media (max-width: 640px) {
  .roles-view { padding: 16px; }
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
}
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.card-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-ink);
}

.feedback {
  font-size: 13px;
  padding: 8px 0;
}
.feedback.error { color: var(--color-danger); }

.cell-name { font-weight: 600; color: var(--color-ink); }

.btn-icon {
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  border-radius: 6px;
  width: 30px; height: 30px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  color: var(--color-text-muted);
}
.btn-icon:hover { background: var(--color-surface-hover); color: var(--color-text); }

.btn-primary {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 16px;
  border-radius: 8px;
  border: 1px solid var(--color-accent);
  background: var(--color-accent);
  color: #fff;
  font-size: 13px; font-weight: 600; font-family: inherit;
  cursor: pointer;
}
.btn-primary:hover:not(:disabled) { opacity: 0.9; }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }

/* --- modal --- */
.modal-content { padding: 22px 24px 24px; }

.field { display: block; margin-bottom: 18px; }
.field-label {
  display: block; font-size: 12px; font-weight: 600;
  color: var(--color-text-muted); margin-bottom: 7px;
}
.field-input {
  width: 100%; box-sizing: border-box;
  padding: 10px 13px; border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13.5px; font-family: inherit;
  color: var(--color-text); background: var(--color-surface-hover);
}
.field-input:focus {
  outline: none; border-color: var(--color-accent-border); background: var(--color-surface);
}

.section-label {
  font-size: 12px; font-weight: 600; color: var(--color-text-muted); margin-bottom: 10px;
}

.modal-actions {
  display: flex; align-items: center; gap: 10px;
  margin: 24px -24px -24px; padding: 16px 24px;
  border-top: 1px solid var(--color-border-subtle);
}
.spacer { flex: 1; }
.btn-ghost {
  padding: 9px 16px; border-radius: 7px;
  border: 1px solid var(--color-border); background: var(--color-surface);
  color: var(--color-text); font-size: 13px; font-weight: 600; font-family: inherit;
  cursor: pointer;
}
.btn-ghost:hover { background: var(--color-surface-hover); }
</style>
