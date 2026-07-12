<template>
  <AppModal
    :model-value="rows.length > 0"
    @update:model-value="$emit('close')"
    :title="title"
    max-width="sm"
  >
    <div class="modal-content">
      <p v-if="isBulk" class="bulk-note">
        The selected representative becomes the owner of all
        {{ rows.length }} selected customers.
      </p>

      <label class="field">
        <span class="field-label">Sales representative</span>
        <select v-model="selectedRepId" class="field-input" aria-label="Sales representative">
          <option :value="null">Unassigned</option>
          <option v-for="rep in reps" :key="rep.id" :value="rep.id">{{ rep.name }}</option>
        </select>
      </label>
      <p v-if="repsError" class="reps-error">{{ repsError }}</p>

      <div class="modal-actions">
        <span class="spacer"></span>
        <button class="btn-ghost" @click="$emit('close')">Cancel</button>
        <button class="btn-primary" :disabled="saving || loadingReps" @click="save">
          {{ saving ? 'Saving…' : 'Save' }}
        </button>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '@/helpers/api'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppModal from '@/components/AppModal.vue'

const props = defineProps({
  // customers to (re)assign: one row from the row action, many from the
  // toolbar bulk action. Empty array = modal closed (DetailModal convention).
  rows: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved'])

const toast = useToast()

const isBulk = computed(() => props.rows.length > 1)
const title = computed(() =>
  isBulk.value
    ? `Assign representative — ${props.rows.length} customers`
    : `Assign representative — ${props.rows[0]?.name ?? ''}`
)

// --- rep catalog (admins only reach this modal, so /api/users is allowed) ---
const reps = ref([])
const loadingReps = ref(false)
const repsError = ref('')
let repsLoaded = false

async function fetchReps() {
  loadingReps.value = true
  repsError.value = ''
  try {
    // paginated endpoint; per_page bumped so one call covers the whole list
    const { data } = await api.get('/api/users', { params: { per_page: 200 } })
    reps.value = data.data.filter(
      (u) => u.is_active && u.roles.some((r) => r.name === 'sales_representative')
    )
    repsLoaded = true
  } catch {
    repsError.value = 'Could not load the representative list.'
  } finally {
    loadingReps.value = false
  }
}

const selectedRepId = ref(null)

watch(
  () => props.rows,
  (rows) => {
    if (!rows.length) return
    if (!repsLoaded) fetchReps()
    // single: preselect the current owner; bulk: start from Unassigned
    selectedRepId.value = isBulk.value ? null : rows[0].assigned_representative?.id ?? null
  }
)

// --- save (single PATCH per row-action, bulk PATCH for a selection) ---
const saving = ref(false)

async function save() {
  const repName = reps.value.find((r) => r.id === selectedRepId.value)?.name ?? 'Unassigned'
  const text = isBulk.value
    ? `Assign ${props.rows.length} customers to ${repName}?`
    : `Assign ${props.rows[0].name} to ${repName}?`

  saving.value = true
  try {
    const ok = await confirm({
      title: 'Assign representative?',
      text,
      confirmText: 'Assign',
      onConfirm: async () => {
        if (isBulk.value) {
          await api.patch('/api/customers/assign-representative', {
            customer_uuids: props.rows.map((row) => row.uuid),
            assigned_representative_id: selectedRepId.value,
          })
          emit('saved', null) // caller refetches
        } else {
          const { data } = await api.patch(
            `/api/customers/${props.rows[0].uuid}/representative`,
            { assigned_representative_id: selectedRepId.value }
          )
          emit('saved', data) // caller patches the row in place
        }
      },
    })
    if (!ok) return
    toast.success('Representative updated')
    emit('close')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
/* modal field styles are per-component by convention (see UsersView.vue) */
.modal-content {
  padding: 22px 24px 24px;
}

.bulk-note {
  font-size: 13px;
  color: var(--color-text-muted);
  line-height: 1.5;
  margin-bottom: 16px;
}

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

.reps-error {
  font-size: 12.5px;
  color: var(--color-danger);
  margin: -8px 0 12px;
}

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
