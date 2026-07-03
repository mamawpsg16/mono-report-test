<template>
  <AppModal
    :model-value="!!row"
    @update:model-value="$emit('close')"
    title="Customer Details"
    max-width="md"
  >
    <div class="detail-grid">
      <div
        v-for="field in fields"
        :key="field.label"
        class="detail-row"
        :class="{ 'detail-row--full': field.full }"
      >
        <span class="detail-label">{{ field.label }}</span>
        <span class="detail-value">{{ field.value }}</span>
      </div>
    </div>
  </AppModal>
</template>

<script setup>
import { computed } from 'vue'
import AppModal from '@/components/AppModal.vue'

const props = defineProps({
  row: { type: Object, default: null }, // the customer to show, or null when closed
})

defineEmits(['close'])

// how a customer row maps to displayed fields — full spans both columns
const fields = computed(() => {
  const row = props.row
  if (!row) return []
  return [
    { label: 'Customer Code', value: row.customer_code },
    { label: 'Name', value: row.name },
    { label: 'Year', value: row.year },
    { label: 'Email', value: row.email || '—' },
    { label: 'Phone', value: row.phone || '—' },
    { label: 'Address', value: row.address || '—', full: true },
    { label: 'City', value: row.city || '—' },
    { label: 'Country', value: row.country || '—' },
    { label: 'Created By', value: row.creator?.name ?? '—' },
    { label: 'Updated By', value: row.updater?.name ?? '—' },
  ]
})
</script>

<style scoped>
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  padding: 20px 24px 24px;
}

.detail-row {
  display: flex;
  flex-direction: column;
  gap: 5px;
  min-width: 0;
  padding: 12px 14px;
  background: var(--color-bg);
  border: 1px solid var(--color-border-subtle);
  border-radius: 10px;
}

.detail-row--full {
  grid-column: 1 / -1;
}

.detail-label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: var(--color-text-muted);
}

.detail-value {
  font-size: 14px;
  color: var(--color-ink);
  font-weight: 500;
  word-break: break-word;
}

@media (max-width: 520px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
