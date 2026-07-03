<template>
  <div class="customers-index">
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

    <FileUpload v-model="showUploadModal" @imported="fetchCustomers" />

    <DetailModal :row="detailRow" @close="detailRow = null" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Upload, Filter, Download, Eye } from '@lucide/vue'
import api from '@/helpers/api'
import { usePagination } from '@/composables/usePagination'
import { useIsMobile } from '@/composables/useIsMobile'
import DatatableServer from '@/components/table/DatatableServer.vue'
import FileUpload from './components/FileUpload.vue'
import DetailModal from './components/DetailModal.vue'

const COLUMNS = {
  customer_code: { text: 'Customer Code', width: 140, fixed: true },
  year: { text: 'Year', width: 90 },
  name: { text: 'Name', width: 160, fixed: true },
  email: { text: 'Email', width: 220 },
  phone: { text: 'Phone', width: 140 },
  address: { text: 'Address', width: 220 },
  city: { text: 'City', width: 130 },
  country: { text: 'Country', width: 130 },
}

const isMobile = useIsMobile()

// sticky columns need spare width to pin against — on narrow screens the
// pinned columns alone eat the whole viewport, so fall back to a plain
// scrollable table instead of freezing anything
const headers = computed(() => [
  { text: 'Details', value: 'details', width: 90, fixed: !isMobile.value },
  ...Object.entries(COLUMNS).map(([value, { text, width, fixed }]) => ({
    text,
    value,
    width,
    fixed: fixed && !isMobile.value,
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
</style>
