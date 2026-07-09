<template>
  <EasyDataTable
    :headers="headers"
    :items="items"
    :rows-per-page="effectiveRowsPerPage"
    :hide-footer="hideFooter"
    :border-cell="borderCell"
    :table-min-height="0"
    :loading="loading"
    theme-color="#1d4ed8"
    :table-class-name="tableClassName"
    :empty-message="emptyMessage"
    :items-selected="itemsSelected"
    @update:items-selected="$emit('update:itemsSelected', $event)"
  >
    <template v-for="(_, slot) in $slots" #[slot]="scope">
      <slot :key="slot" :name="slot" v-bind="scope" />
    </template>
  </EasyDataTable>
</template>

<script setup>
import { computed } from 'vue'
import EasyDataTable from 'vue3-easy-data-table'
import 'vue3-easy-data-table/dist/style.css'

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  rowsPerPage: { type: Number, default: 10 },
  hideFooter: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  borderCell: { type: Boolean, default: true },   // vertical column separators
  emptyMessage: { type: String, default: 'No data to display' },
  // null = no checkbox column (library default); pass an array to enable selection
  itemsSelected: { type: Array, default: null },
})

defineEmits(['update:itemsSelected'])

// vue3-easy-data-table seeds its internal rows-per-page from this prop
// exactly once, at mount, into a plain (non-watched) ref — later prop
// changes are silently ignored outside its own server-options mode. When
// `hideFooter` is set we're already server-side paginating externally and
// handing it exactly one page of items, so its internal slicing should
// never kick in. A value derived from `items.length` would seed at 0/1
// before the first fetch resolves and get stuck there, so use a constant
// safely above any real page size instead (perPageOptions tops out at 100).
const NO_INTERNAL_PAGINATION = 1000
const effectiveRowsPerPage = computed(() =>
  props.hideFooter ? NO_INTERNAL_PAGINATION : props.rowsPerPage
)

// table-min-height is 0 (below) so a short result set doesn't leave dead
// space under the table -- but that also means a table with zero rows
// collapses to just its header height, so the library's loading spinner
// (centered within whatever height the table currently has) ends up
// squashed near the top instead of centered. Reserve room ONLY for that
// specific window -- loading with nothing to show yet -- and let it go
// back to fitting real content the instant data arrives either way.
const tableClassName = computed(() =>
  props.loading && props.items.length === 0 ? 'app-table app-table-loading' : 'app-table'
)
</script>

<style scoped>
/* keep cells on one line — compact rows, horizontal scroll on overflow
   (the library's __main already has overflow:auto). Once any column is
   `fixed`, the library forces table-layout:fixed and clamps every unwidthed
   column to 100px, so overflowing text must be ellipsized rather than left
   to spill into the next (fixed-width) cell. */
.app-table :deep(.vue3-easy-data-table__header th),
.app-table :deep(.vue3-easy-data-table__body td) {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-table {
  --easy-table-border: 1px solid var(--color-border);
  --easy-table-row-border: 1px solid var(--color-border-subtle);

  --easy-table-header-font-size: 12.5px;
  --easy-table-header-height: 38px;
  --easy-table-header-font-color: var(--color-text);
  --easy-table-header-background-color: var(--color-surface-hover);
  --easy-table-header-item-padding: 8px 12px;

  --easy-table-body-row-font-size: 12.5px;
  --easy-table-body-row-height: 40px;
  --easy-table-body-row-font-color: var(--color-text-muted);
  --easy-table-body-row-background-color: var(--color-surface);
  --easy-table-body-row-hover-font-color: var(--color-text);
  --easy-table-body-row-hover-background-color: var(--color-surface-hover);
  --easy-table-body-item-padding: 8px 12px;

  --easy-table-footer-background-color: var(--color-surface);
  --easy-table-footer-font-color: var(--color-text-muted);
  --easy-table-footer-font-size: 11px;
  --easy-table-footer-padding: 8px 12px;
  --easy-table-footer-height: 40px;

  --easy-table-rows-per-page-selector-width: 70px;

  --easy-table-message-font-color: var(--color-text-muted);
  --easy-table-message-font-size: 12.5px;

  --easy-table-scrollbar-track-color: var(--color-surface-hover);
  --easy-table-scrollbar-color: var(--color-surface-hover);
  --easy-table-scrollbar-thumb-color: var(--color-border-strong);
  --easy-table-scrollbar-corner-color: var(--color-surface-hover);
}

/* see tableClassName above -- only active while loading with zero rows */
.app-table-loading {
  min-height: 240px;
}
</style>
