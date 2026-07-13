<template>
  <div class="prospects-view">
    <!-- View/report only: reps capture and work prospects in the field (mobile).
         The web surface is for browsing/reporting, so there are no create/edit/
         delete actions here. -->
    <DatatableServer
      v-model="searchInput"
      :title="$route.meta.title"
      search-placeholder="Search prospects..."
      v-model:page="page"
      v-model:per-page="perPage"
      :last-page="lastPage"
      :total="total"
      :headers="headers"
      :items="prospects"
      :loading="loading"
      empty-message="No prospects yet"
    >
      <template #actions>
        <button
          class="btn-icon"
          :disabled="loading"
          aria-label="Refresh list"
          title="Refresh"
          @click="fetchProspects"
        >
          <RefreshCw :size="15" :stroke-width="2" :class="{ spinning: loading }" />
        </button>
      </template>

      <template #item-status="prospect">
        <span v-if="prospect.converted_customer_id" class="status-badge is-converted">Converted</span>
        <span v-else class="status-badge is-open">Open</span>
      </template>

      <template #item-created_by="prospect">
        {{ prospect.creator?.name ?? '—' }}
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

// Read-only columns. Admins see every rep's prospects (Created by matters);
// a rep sees only their own.
const headers = [
  { text: 'Name', value: 'name' },
  { text: 'Phone', value: 'phone' },
  { text: 'Notes', value: 'notes' },
  { text: 'Status', value: 'status', width: 120 },
  { text: 'Created by', value: 'created_by', width: 160 },
]

const prospects = ref([])
const loading = ref(false)
const { page, perPage, lastPage, total } = usePagination()
const searchInput = ref('')
const search = ref('')

let searchDebounce = null
let abortController = null

async function fetchProspects() {
  abortController?.abort()
  const myController = new AbortController()
  abortController = myController
  loading.value = true
  try {
    const { data } = await api.get('/api/prospects', {
      params: { page: page.value, per_page: perPage.value, search: search.value || undefined },
      signal: myController.signal,
    })
    prospects.value = data.data
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

watch([page, perPage, search], fetchProspects)

onMounted(fetchProspects)
</script>

<style scoped>
.prospects-view {
  padding: 24px 36px;
}

@media (max-width: 640px) {
  .prospects-view { padding: 16px; }
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
.status-badge.is-converted {
  color: var(--color-success);
  background: var(--color-success-soft);
}
</style>
