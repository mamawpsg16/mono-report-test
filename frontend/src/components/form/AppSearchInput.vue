<template>
  <div class="search-wrap">
    <Search :size="15" :stroke-width="2" class="search-icon" />
    <input
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      type="text"
      class="search-input"
      :placeholder="placeholder"
      :aria-label="placeholder"
    />
    <button v-if="modelValue" class="search-clear" type="button" aria-label="Clear search" @click="$emit('update:modelValue', '')">
      <X :size="13" :stroke-width="2" />
    </button>
  </div>
</template>

<script setup>
import { Search, X } from '@lucide/vue'

defineProps({
  modelValue: { type: String, default: '' },
  placeholder: { type: String, default: 'Search...' },
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.search-wrap {
  position: relative; display: flex; align-items: center;
  width: 360px;
}
.search-icon {
  position: absolute; left: 11px; color: var(--color-text-muted); pointer-events: none;
}
.search-input {
  width: 100%; padding: 8px 32px 8px 32px; border-radius: 8px;
  border: 1px solid var(--color-border); font-size: 13px; font-family: inherit;
  color: var(--color-text); background: var(--color-surface-hover);
  transition: border-color 0.15s, background 0.15s;
}
.search-input:focus {
  outline: none; border-color: var(--color-accent-border); background: var(--color-surface);
}
.search-clear {
  position: absolute; right: 8px; border: none; background: none;
  cursor: pointer; color: var(--color-text-muted); padding: 3px; border-radius: 4px;
  display: flex; align-items: center; justify-content: center;
}
.search-clear:hover { background: var(--color-border-subtle); color: var(--color-text-muted); }
</style>
