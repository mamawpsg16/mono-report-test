<template>
  <div class="resource-card">
    <div class="card-header">
      <div class="card-heading">
        <p v-if="title" class="card-title">{{ title }}</p>
        <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
      </div>
      <div class="toolbar-actions">
        <slot name="actions" />
      </div>
    </div>

    <div class="table-toolbar">
      <AppSearchInput
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
        :placeholder="searchPlaceholder"
      />
    </div>

    <!--
      Any slot the caller passes (besides #actions above) forwards straight
      through to AppDataTable, which forwards it again to vue3-easy-data-table.
      That library's convention: name a slot `item-<column value>` to
      customize how that column's cells render, e.g. #item-customer_code.
      See https://www.npmjs.com/package/vue3-easy-data-table#slots
    -->
    <AppDataTable
      :headers="headers"
      :items="items"
      hide-footer
      :loading="loading"
      :empty-message="emptyMessage"
      :items-selected="itemsSelected"
      @update:items-selected="$emit('update:itemsSelected', $event)"
    >
      <template v-for="slot in tableSlotNames" #[slot]="scope">
        <slot :key="slot" :name="slot" v-bind="scope" />
      </template>
    </AppDataTable>

    <AppPagination
      :page="page"
      :per-page="perPage"
      :last-page="lastPage"
      :total="total"
      @update:page="$emit('update:page', $event)"
      @update:per-page="$emit('update:perPage', $event)"
    />
  </div>
</template>

<script setup>
import { computed, useSlots } from 'vue'
import AppSearchInput from '@/components/form/AppSearchInput.vue'
import AppDataTable from './AppDataTable.vue'
import AppPagination from './AppPagination.vue'

defineProps({
  title: { type: String, required: false, default: '' },
  subtitle: { type: String, required: false, default: '' },
  modelValue: { type: String, default: '' },
  searchPlaceholder: { type: String, default: 'Search...' },
  page: { type: Number, required: true },
  perPage: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  total: { type: Number, required: true },
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  emptyMessage: { type: String, default: 'No data to display' },
  itemsSelected: { type: Array, default: null },
})

defineEmits(['update:modelValue', 'update:page', 'update:perPage', 'update:itemsSelected'])

// forward every slot we're given straight to AppDataTable (column templates
// etc) except `actions`, which belongs to the header button row above
const slots = useSlots()
const tableSlotNames = computed(() => Object.keys(slots).filter((name) => name !== 'actions'))
</script>

<style scoped>
.resource-card {
  background: var(--color-surface); border-radius: 16px; padding: 20px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05), 0 6px 24px rgba(var(--color-accent-rgb),0.05);
}

.card-header {
  display: flex; align-items: center; justify-content: space-between;
  margin: 0 -20px 16px; padding: 0 20px 16px; border-bottom: 1px solid var(--color-border-subtle);
}
.card-heading { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.card-title { font-size: 21px; font-weight: 700; color: var(--color-ink); letter-spacing: -0.4px; }
/* optional "what is this list" line under the card title */
.card-subtitle { font-size: 13.5px; color: var(--color-text-muted); }

.table-toolbar {
  display: flex; align-items: center; justify-content: flex-end; gap: 12px;
  margin-bottom: 14px;
}

.toolbar-actions { display: flex; align-items: center; gap: 8px; }

@media (max-width: 640px) {
  .card-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .toolbar-actions {
    justify-content: center;
    flex-wrap: wrap;
  }
}
</style>
