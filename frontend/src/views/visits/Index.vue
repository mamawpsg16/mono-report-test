<template>
  <div class="visits-view">
    <!-- View/report only: the field workflow (start -> notes -> finish) lives
         on mobile. The web surface is for browsing/reporting visit history. -->
    <DatatableServer
      v-model="searchInput"
      :title="$route.meta.title"
      search-placeholder="Search by customer..."
      v-model:page="page"
      v-model:per-page="perPage"
      :last-page="lastPage"
      :total="total"
      :headers="headers"
      :items="visits"
      :loading="loading"
      empty-message="No visits yet"
    >
      <template #actions>
        <button
          class="btn-icon"
          :disabled="loading"
          aria-label="Refresh list"
          title="Refresh"
          @click="fetchVisits"
        >
          <RefreshCw :size="15" :stroke-width="2" :class="{ spinning: loading }" />
        </button>
      </template>

      <template #item-customer="visit">
        {{ visit.customer?.name ?? '—' }}
      </template>

      <template #item-representative="visit">
        {{ visit.representative?.name ?? '—' }}
      </template>

      <template #item-status="visit">
        <span v-if="visit.ended_at" class="status-badge is-closed">Finished</span>
        <span v-else class="status-badge is-open">Open</span>
      </template>

      <template #item-started_at="visit">
        {{ formatDate(visit.started_at) }}
      </template>
    </DatatableServer>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { RefreshCw } from '@lucide/vue'
import api from '@/helpers/api'
import DatatableServer from '@/components/table/DatatableServer.vue'
import { usePagination } from '@/composables/usePagination'

// Admins see every rep's visits (Representative matters); a rep sees only
// their own.
const headers = [
  { text: 'Customer', value: 'customer' },
  { text: 'Representative', value: 'representative' },
  { text: 'Started', value: 'started_at' },
  { text: 'Status', value: 'status', width: 110 },
  { text: 'Notes', value: 'notes' },
]

const visits = ref([])
const loading = ref(false)
const { page, perPage, lastPage, total } = usePagination()
const searchInput = ref('')
const search = ref('')

let searchDebounce = null
let abortController = null

async function fetchVisits() {
  abortController?.abort()
  const myController = new AbortController()
  abortController = myController
  loading.value = true
  try {
    const { data } = await api.get('/api/visits', {
      params: { page: page.value, per_page: perPage.value, search: search.value || undefined },
      signal: myController.signal,
    })
    visits.value = data.data
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

watch([page, perPage, search], fetchVisits)

onMounted(fetchVisits)

function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  })
}
</script>

<style scoped>
.visits-view {
  padding: 24px 36px;
}

@media (max-width: 640px) {
  .visits-view { padding: 16px; }
}

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
.status-badge.is-open {
  color: var(--color-accent);
  background: rgba(var(--color-accent-rgb), 0.12);
}
.status-badge.is-closed {
  color: var(--color-success);
  background: var(--color-success-soft);
}
</style>
