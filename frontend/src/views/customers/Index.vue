<template>
  <div class="customers-index">
    <div ref="tableWrap">
    <DatatableServer
      v-model="searchInput"
      search-placeholder="Search customers..."
      v-model:page="page"
      v-model:per-page="perPage"
      :last-page="lastPage"
      :total="total"
      :headers="headers"
      :items="customers"
      empty-message="No customers found"
      v-model:items-selected="selectedRows"
    >
      <template #actions>
        <span v-if="selectedRows.length" class="selection-note">
          {{ selectedRows.length }} selected
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

      <template #item-details="row">
        <button class="btn-icon" @click="detailRow = row" aria-label="View details">
          <Eye :size="15" :stroke-width="2" />
        </button>
      </template>
      <template #item-customer_code="row"><span class="td-code">{{ row.customer_code }}</span></template>
      <template #item-creator="row">{{ row.creator?.name ?? '—' }}</template>
      <template #item-updater="row">{{ row.updater?.name ?? '—' }}</template>
    </DatatableServer>
    </div>

    <FileUpload v-model="showUploadModal" @imported="fetchCustomers" />

    <DetailModal :row="detailRow" @close="detailRow = null" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { Upload, Filter, Download, Eye, X } from '@lucide/vue'
import api from '@/helpers/api'
import { usePagination } from '@/composables/usePagination'
import { useIsMobile } from '@/composables/useIsMobile'
import { useColumnFreeze } from '@/composables/useColumnFreeze'
import DatatableServer from '@/components/table/DatatableServer.vue'
import FileUpload from './components/FileUpload.vue'
import DetailModal from './components/DetailModal.vue'

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

// freeze the lead columns only when the table actually overflows (see
// useColumnFreeze). `tableWrap` is the ref on the wrapper div around the table.
const { container: tableWrap, frozen: columnsFrozen, measure: remeasureColumns } = useColumnFreeze()

// Only pin columns when there's something to scroll: freezing on a table that
// already fits just forces a needless horizontal scrollbar. Also skip pinning
// on mobile, where the pinned columns alone would eat the whole viewport.
const canFreeze = computed(() => columnsFrozen.value && !isMobile.value)

const headers = computed(() => [
  { text: 'Details', value: 'details', width: 90, fixed: canFreeze.value },
  ...Object.entries(COLUMNS).map(([value, { text, width, fixed }]) => ({
    text,
    value,
    width,
    fixed: fixed && canFreeze.value,
  })),
  { text: 'Created By', value: 'creator', width: 130 },
  { text: 'Updated By', value: 'updater', width: 130 },
])

const customers = ref([])
const { page, perPage, lastPage, total } = usePagination()
const showUploadModal = ref(false)
const searchInput = ref('')
const search = ref('')
const selectedRows = ref([])
const detailRow = ref(null)


let searchDebounce = null
let abortController = null

async function fetchCustomers() {
  abortController?.abort()
  abortController = new AbortController()

  try {
    const { data } = await api.get('/api/customers', {
      params: { page: page.value, per_page: perPage.value, search: search.value || undefined },
      signal: abortController.signal,
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

.btn-primary {
  padding: 9px 22px; border-radius: 8px; border: none; background: var(--color-accent);
  cursor: pointer; font-size: 13px; font-weight: 600; color: #fff;
  font-family: inherit; display: flex; align-items: center; gap: 8px;
}

@media (max-width: 640px) {
  .customers-index { padding: 16px; }
}

.btn-secondary {
  padding: 7px 13px; border-radius: 8px; border: 1px solid var(--color-border);
  background: var(--color-surface); cursor: pointer; font-size: 12.5px; font-weight: 500;
  color: var(--color-text); font-family: inherit; display: flex; align-items: center; gap: 6px;
}
.btn-secondary:hover { background: var(--color-surface-hover); }

.btn-icon {
  border: 1px solid var(--color-border); background: var(--color-surface); border-radius: 6px;
  width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
  cursor: pointer; color: var(--color-text-muted);
}
.btn-icon:hover { background: var(--color-surface-hover); color: var(--color-text); }

.td-code { font-weight: 500; color: var(--color-text); }

.selection-note {
  display: flex; align-items: center; gap: 8px;
  font-size: 12.5px; font-weight: 500; color: var(--color-text-muted);
}
</style>
