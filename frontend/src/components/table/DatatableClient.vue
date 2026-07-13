<template>
  <div class="static-table-card" :class="{ 'is-bare': !title && !subtitle && !$slots.actions }">
    <div v-if="title || subtitle || $slots.actions" class="card-header">
      <div class="card-heading">
        <p v-if="title" class="card-title">{{ title }}</p>
        <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
      </div>
      <div class="toolbar-actions">
        <slot name="actions" />
      </div>
    </div>

    <div class="table-toolbar">
      <AppSearchInput v-model="searchText" :placeholder="searchPlaceholder" />
    </div>

    <!-- :key forces a clean remount per page -- the underlying library
         keeps its own internal pagination state and doesn't reliably
         re-slice when handed a pre-paginated items array from outside. -->
    <AppDataTable :key="page" :headers="headers" :items="pagedItems" hide-footer :loading="loading" :empty-message="emptyMessage">
      <template v-for="slot in tableSlotNames" #[slot]="scope">
        <slot :key="slot" :name="slot" v-bind="scope" />
      </template>
    </AppDataTable>

    <AppPagination
      :page="page"
      :last-page="lastPage"
      :total="searchedItems.length"
      :per-page="perPage"
      :per-page-options="perPageOptions"
      @update:page="page = $event"
      @update:per-page="perPage = $event; page = 1"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, useSlots } from 'vue'
import AppDataTable from './AppDataTable.vue'
import AppPagination from './AppPagination.vue'
import AppSearchInput from '@/components/form/AppSearchInput.vue'

const props = defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  headers: { type: Array, required: true },
  items: { type: Array, required: true }, // full array, already in memory -- this component paginates it locally
  loading: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'No data to display' },
  perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
  searchPlaceholder: { type: String, default: 'Search...' },
})

// forward every slot straight to AppDataTable (column templates etc) except
// `actions`, which belongs to the header button row above
const slots = useSlots()
const tableSlotNames = computed(() => Object.keys(slots).filter((name) => name !== 'actions'))

const page = ref(1)
const perPage = ref(10)
const searchText = ref('')

// client-side filter across every column this table actually shows --
// mirrors the server-side `ilike`-across-fields search on the Customers
// list, just done in-memory since this data's already fully loaded.
const searchedItems = computed(() => {
  const needle = searchText.value.trim().toLowerCase()
  if (!needle) return props.items
  return props.items.filter((row) =>
    props.headers.some(({ value }) => String(row[value] ?? '').toLowerCase().includes(needle))
  )
})

const lastPage = computed(() => Math.max(1, Math.ceil(searchedItems.value.length / perPage.value)))

const pagedItems = computed(() => {
  const start = (page.value - 1) * perPage.value
  return searchedItems.value.slice(start, start + perPage.value)
})

// caller's array can be swapped/filtered out from under us (e.g. a status
// filter chip), or our own search text can narrow it -- if either shrinks
// past the current page, snap back to 1 instead of showing an empty page.
watch([() => props.items, searchText], () => {
  page.value = 1
})
</script>

<style scoped>
.static-table-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  overflow: hidden;
  padding: 16px;
}

/* no title -> the border/padding add chrome with no label to justify it,
   just eats vertical space from whatever already frames this table
   (tabs, filter chips, a modal). Collapse to bare table + pagination. */
.static-table-card.is-bare {
  background: none;
  border: none;
  border-radius: 0;
  padding: 0;
}

.card-header {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  margin: -16px -16px 14px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--color-border-subtle);
}

.toolbar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }

@media (max-width: 640px) {
  .card-header { flex-direction: column; align-items: stretch; }
  .toolbar-actions { justify-content: center; flex-wrap: wrap; margin-left: 0; }
}

.card-heading { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.card-title {
  font-size: 15px;
  font-weight: 700;
  color: var(--color-ink);
}
/* optional "what is this list" line under the card title */
.card-subtitle { font-size: 13.5px; color: var(--color-text-muted); }

.table-toolbar {
  display: flex; align-items: center; justify-content: flex-end;
  margin-bottom: 12px;
}
</style>
