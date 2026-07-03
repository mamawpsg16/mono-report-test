<template>
  <div class="pagination">
    <div class="per-page">
      <label :id="id">Rows per page</label>
      <AppSelect
        :model-value="perPage"
        :options="perPageOptions"
        :labelledby="id"
        @update:model-value="$emit('update:perPage', Number($event))"
      />
    </div>
    <div class="pagination-nav">
      <button class="pagination-btn" :disabled="page === 1" @click="$emit('update:page', page - 1)">Prev</button>
      <span class="pagination-info">Page {{ page }} of {{ lastPage || 1 }} &middot; {{ total }} total</span>
      <button class="pagination-btn" :disabled="page === lastPage" @click="$emit('update:page', page + 1)">Next</button>
    </div>
  </div>
</template>

<script setup>
import { useId } from 'vue'
import AppSelect from '@/components/form/AppSelect.vue'

defineProps({
  page: { type: Number, required: true },
  lastPage: { type: Number, required: true },
  total: { type: Number, required: true },
  perPage: { type: Number, required: true },
  perPageOptions: { type: Array, default: () => [10, 20, 50, 100] },
})
defineEmits(['update:page', 'update:perPage'])

const id = useId()
</script>

<style scoped>
.pagination {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px 16px;
  margin-top: 14px;
}
.per-page { display: flex; align-items: center; gap: 8px; white-space: nowrap; }
.per-page label { font-size: 12px; color: var(--color-text-muted); }
.pagination-nav { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pagination-btn {
  padding: 5px 12px; border-radius: 6px; border: 1px solid var(--color-border);
  background: var(--color-surface); cursor: pointer; font-size: 12px; font-weight: 500;
  color: var(--color-text-muted); font-family: inherit;
}
.pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pagination-info { font-size: 12px; color: var(--color-text-muted); }

@media (max-width: 640px) {
  .pagination { justify-content: center; }
  .per-page, .pagination-nav { justify-content: center; }
}
</style>
