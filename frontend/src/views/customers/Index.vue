<template>
  <div class="customers-index">
    <div ref="tableWrap">
    <DatatableServer
      v-model="searchInput"
      :title="$route.meta.title"
      search-placeholder="Search customers..."
      v-model:page="page"
      v-model:per-page="perPage"
      :last-page="lastPage"
      :total="total"
      :headers="headers"
      :items="customers"
      :loading="loading"
      empty-message="No customers found"
      v-model:items-selected="selectedRows"
    >
      <template #actions>
        <span v-if="selectedRows.length" class="selection-note">
          {{ selectedRows.length }} selected
          <button v-if="canReassign" class="btn-secondary" @click="openReassign(selectedRows)">
            <UserCog :size="13" :stroke-width="2" />
            Assign rep
          </button>
          <button class="btn-secondary" @click="selectedRows = []">
            <X :size="13" :stroke-width="2" />
            Clear
          </button>
        </span>
        <button class="btn-secondary">
          <Filter :size="13" :stroke-width="2" />
          Filter
        </button>
        <button class="btn-secondary">
          <Download :size="13" :stroke-width="2" />
          Export
        </button>
        <button class="btn-primary" @click="showUploadModal = true">
          <Upload :size="15" :stroke-width="2" />
          Upload
        </button>
      </template>

      <template #item-action="row">
        <div class="row-actions">
          <button class="btn-icon" @click="detailRow = row" aria-label="View details">
            <Eye :size="15" :stroke-width="2" />
          </button>
          <button
            v-if="canReassign"
            class="btn-icon"
            aria-label="Assign representative"
            title="Assign representative"
            @click="openReassign([row])"
          >
            <UserCog :size="15" :stroke-width="2" />
          </button>
        </div>
      </template>
      <template #item-customer_code="row"><span class="td-code">{{ row.customer_code }}</span></template>
      <template #item-assigned_representative="row">
        <span :class="{ 'td-unassigned': !row.assigned_representative }">
          {{ row.assigned_representative?.name ?? 'Unassigned' }}
        </span>
      </template>
      <template #item-creator="row">{{ row.creator?.name ?? '—' }}</template>
      <template #item-updater="row">{{ row.updater?.name ?? '—' }}</template>
    </DatatableServer>
    </div>

    <FileUpload v-model="showUploadModal" @imported="fetchCustomers" />

    <AskPanel v-model="showAskModal" />

    <DetailModal :row="detailRow" @close="detailRow = null" />

    <ReassignRepModal :rows="reassignRows" @close="reassignRows = []" @saved="onReassignSaved" />

    <button class="ask-fab" @click="showAskModal = true" aria-label="Ask about your customers">
      <Sparkles :size="20" :stroke-width="2" />
    </button>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { Upload, Filter, Download, Eye, X, Sparkles, UserCog } from '@lucide/vue'
import api from '@/helpers/api'
import { useAuth } from '@/composables/useAuth'
import { usePagination } from '@/composables/usePagination'
import { useIsMobile } from '@/composables/useIsMobile'
import { useColumnFreeze } from '@/composables/useColumnFreeze'
import DatatableServer from '@/components/table/DatatableServer.vue'
import FileUpload from './components/FileUpload.vue'
import AskPanel from './components/AskPanel.vue'
import DetailModal from './components/DetailModal.vue'
import ReassignRepModal from './components/ReassignRepModal.vue'

const COLUMNS = {
  customer_code: { text: 'Customer Code', width: 140, fixed: true },
  year: { text: 'Year', width: 90 },
  name: { text: 'Name', width: 160, fixed: true },
  email: { text: 'Email', width: 180 },
  phone: { text: 'Phone', width: 140 },
  address: { text: 'Address', width: 160 },
  city: { text: 'City', width: 130 },
  country: { text: 'Country', width: 130 },
}

const isMobile = useIsMobile()
const auth = useAuth()

// Assigning reps is admin work; roles.manage is the codebase's admin gate
// (same permission that guards /api/users, which the modal's rep list needs).
const canReassign = computed(() => auth.can('roles.manage'))

// freeze the lead columns only when the table actually overflows (see
// useColumnFreeze). `tableWrap` is the ref on the wrapper div around the table.
const { container: tableWrap, frozen: columnsFrozen, measure: remeasureColumns } = useColumnFreeze()

// Only pin columns when there's something to scroll: freezing on a table that
// already fits just forces a needless horizontal scrollbar. Also skip pinning
// on mobile, where the pinned columns alone would eat the whole viewport.
const canFreeze = computed(() => columnsFrozen.value && !isMobile.value)

const headers = computed(() => [
  { text: 'Action', value: 'action', width: canReassign.value ? 120 : 90, fixed: canFreeze.value },
  ...Object.entries(COLUMNS).map(([value, { text, width, fixed }]) => ({
    text,
    value,
    width,
    fixed: fixed && canFreeze.value,
  })),
  { text: 'Assigned Rep', value: 'assigned_representative', width: 150 },
  { text: 'Created By', value: 'creator', width: 130 },
  { text: 'Updated By', value: 'updater', width: 130 },
])

const customers = ref([])
const loading = ref(false)
const { page, perPage, lastPage, total } = usePagination()
const showUploadModal = ref(false)
const showAskModal = ref(false)
const searchInput = ref('')
const search = ref('')
const selectedRows = ref([])
const detailRow = ref(null)

// rows queued for the reassign modal: [one row] from the row action, or the
// whole selection from the toolbar's bulk action. Empty = modal closed.
const reassignRows = ref([])

function openReassign(rows) {
  reassignRows.value = rows
}

// Single reassign returns the updated row -> patch it in place, no refetch.
// Bulk returns only a count -> refetch, and drop the now-stale selection.
function onReassignSaved(updatedCustomer) {
  if (updatedCustomer) {
    const index = customers.value.findIndex((c) => c.uuid === updatedCustomer.uuid)
    if (index !== -1) customers.value[index] = updatedCustomer
  } else {
    selectedRows.value = []
    fetchCustomers()
  }
}


let searchDebounce = null
let abortController = null

async function fetchCustomers() {
  abortController?.abort()
  // capture the controller THIS call uses -- `abortController` (the shared
  // module var) gets reassigned the instant a newer call starts, so reading
  // it back in `finally` would check the wrong (newer) request's state
  const myController = new AbortController()
  abortController = myController
  loading.value = true

  try {
    const { data } = await api.get('/api/customers', {
      params: { page: page.value, per_page: perPage.value, search: search.value || undefined },
      signal: myController.signal,
    })
    customers.value = data.data
    lastPage.value = data.last_page
    total.value = data.total
    // rows just changed the table's content width; re-check overflow so the
    // freeze/scroll decision matches the data that's actually rendered
    await nextTick()
    remeasureColumns()
  } catch (err) {
    if (err.code !== 'ERR_CANCELED') throw err
  } finally {
    // a superseded (aborted) request shouldn't flicker the spinner off --
    // only the request that actually landed should control it
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

watch([page, perPage, search], fetchCustomers)

onMounted(fetchCustomers)
</script>

<style scoped>
.customers-index { padding: 24px 36px; }

@media (max-width: 640px) {
  .customers-index { padding: 16px; }
}

/* buttons (.btn-primary / .btn-secondary / .btn-icon) come from the global system in App.vue */

.td-code { font-weight: 500; color: var(--color-text); }
.td-unassigned { color: var(--color-text-muted); font-style: italic; }

.row-actions { display: flex; gap: 8px; }

.selection-note {
  display: flex; align-items: center; gap: 8px;
  font-size: 12.5px; font-weight: 500; color: var(--color-text-muted);
}

.ask-fab {
  position: fixed;
  bottom: 28px; right: 32px;
  z-index: 100;
  width: 52px; height: 52px;
  border-radius: 50%;
  border: none;
  background: var(--color-accent);
  color: #fff;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(var(--color-accent-rgb), 0.35), 0 2px 6px rgba(0, 0, 0, 0.1);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.ask-fab:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(var(--color-accent-rgb), 0.4), 0 3px 8px rgba(0, 0, 0, 0.12);
}

@media (max-width: 640px) {
  .ask-fab { bottom: 20px; right: 20px; width: 48px; height: 48px; }
}
</style>
