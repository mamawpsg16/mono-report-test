<template>
  <div class="transfer">
    <div class="transfer-col">
      <div class="transfer-label">{{ leftLabel }}</div>
      <input
        v-model="leftSearch"
        class="transfer-search"
        type="text"
        :placeholder="searchPlaceholder"
        :aria-label="`Search ${leftLabel}`"
      />
      <div class="transfer-btns">
        <button class="xfer-btn" title="Assign all" :disabled="!available.length" @click="assign(available.map((o) => o.value))">
          <ChevronsRight :size="15" :stroke-width="2" />
        </button>
        <button class="xfer-btn" title="Assign selected" :disabled="!leftSel.size" @click="assign([...leftSel])">
          <ArrowRight :size="15" :stroke-width="2" />
        </button>
      </div>
      <ul class="transfer-list">
        <li
          v-for="o in availableFiltered"
          :key="o.value"
          class="transfer-item"
          :class="{ selected: leftSel.has(o.value) }"
          @click="toggleLeft(o.value)"
          @dblclick="assign([o.value])"
        >
          {{ o.label }}
        </li>
        <li v-if="!availableFiltered.length" class="transfer-empty">{{ emptyText }}</li>
      </ul>
    </div>

    <div class="transfer-col">
      <div class="transfer-label">{{ rightLabel }}</div>
      <input
        v-model="rightSearch"
        class="transfer-search"
        type="text"
        :placeholder="searchPlaceholder"
        :aria-label="`Search ${rightLabel}`"
      />
      <div class="transfer-btns">
        <button class="xfer-btn" title="Remove selected" :disabled="!rightSel.size" @click="unassign([...rightSel])">
          <ArrowLeft :size="15" :stroke-width="2" />
        </button>
        <button class="xfer-btn" title="Remove all" :disabled="!assignedOptions.length" @click="unassign(assignedOptions.map((o) => o.value))">
          <ChevronsLeft :size="15" :stroke-width="2" />
        </button>
      </div>
      <ul class="transfer-list">
        <li
          v-for="o in assignedFiltered"
          :key="o.value"
          class="transfer-item"
          :class="{ selected: rightSel.has(o.value) }"
          @click="toggleRight(o.value)"
          @dblclick="unassign([o.value])"
        >
          {{ o.label }}
        </li>
        <li v-if="!assignedFiltered.length" class="transfer-empty">{{ emptyText }}</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { ArrowRight, ArrowLeft, ChevronsRight, ChevronsLeft } from '@lucide/vue'

// Generic dual-list (shuttle) picker. `options` is the full catalog
// ([{ value, label }]) — the same for every context; v-model is the array of
// currently-assigned values, which is what differs per role/user. Reused by
// Users (roles) and Roles (permissions).
const props = defineProps({
  modelValue: { type: Array, default: () => [] }, // assigned values
  options: { type: Array, default: () => [] }, // [{ value, label }]
  leftLabel: { type: String, default: 'Available' },
  rightLabel: { type: String, default: 'Assigned' },
  searchPlaceholder: { type: String, default: 'Search…' },
  emptyText: { type: String, default: 'No items' },
})

const emit = defineEmits(['update:modelValue'])

const leftSearch = ref('')
const rightSearch = ref('')
const leftSel = ref(new Set())
const rightSel = ref(new Set())

const assignedSet = computed(() => new Set(props.modelValue))
// left = catalog minus what this role/user already has; right = what it has
const available = computed(() => props.options.filter((o) => !assignedSet.value.has(o.value)))
const assignedOptions = computed(() => props.options.filter((o) => assignedSet.value.has(o.value)))

const availableFiltered = computed(() => filterOpts(available.value, leftSearch.value))
const assignedFiltered = computed(() => filterOpts(assignedOptions.value, rightSearch.value))

function assign(values) {
  emit('update:modelValue', [...new Set([...props.modelValue, ...values])])
  leftSel.value = new Set()
}

function unassign(values) {
  const remove = new Set(values)
  emit('update:modelValue', props.modelValue.filter((v) => !remove.has(v)))
  rightSel.value = new Set()
}

function toggleLeft(value) {
  leftSel.value = toggled(leftSel.value, value)
}
function toggleRight(value) {
  rightSel.value = toggled(rightSel.value, value)
}
function toggled(set, value) {
  const next = new Set(set)
  next.has(value) ? next.delete(value) : next.add(value)
  return next
}

function filterOpts(opts, term) {
  const q = term.trim().toLowerCase()
  return q ? opts.filter((o) => o.label.toLowerCase().includes(q)) : opts
}
</script>

<style scoped>
.transfer {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px;
}
.transfer-col {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.transfer-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--color-ink);
}
.transfer-search {
  box-sizing: border-box;
  padding: 9px 12px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-surface-hover);
}
.transfer-search:focus {
  outline: none;
  border-color: var(--color-accent-border);
  background: var(--color-surface);
}
.transfer-btns {
  display: flex;
  gap: 8px;
}
.xfer-btn {
  width: 36px;
  height: 32px;
  border-radius: 7px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.xfer-btn:hover:not(:disabled) {
  background: var(--color-surface-hover);
  color: var(--color-text);
}
.xfer-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.transfer-list {
  list-style: none;
  margin: 0;
  padding: 6px;
  min-height: 240px;
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid var(--color-border);
  border-radius: 8px;
}
.transfer-item {
  padding: 9px 11px;
  border-radius: 6px;
  font-size: 13px;
  color: var(--color-text);
  cursor: pointer;
  user-select: none;
}
.transfer-item:hover {
  background: var(--color-surface-hover);
}
.transfer-item.selected {
  background: rgba(var(--color-accent-rgb), 0.14);
  color: var(--color-accent);
  font-weight: 600;
}
.transfer-empty {
  list-style: none;
  padding: 16px 10px;
  font-size: 12.5px;
  color: var(--color-text-muted);
  text-align: center;
}
</style>
