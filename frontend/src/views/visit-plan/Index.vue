<template>
  <div class="my-week">
    <div class="week-header">
      <h1 class="week-title">{{ $route.meta.title }}</h1>
      <span v-if="plan" class="week-range">{{ weekRangeLabel }}</span>
    </div>

    <div class="week-tabs">
      <button
        class="week-tab"
        :class="{ 'is-active': selectedWeek === 'current' }"
        @click="selectWeek('current')"
      >
        This week
      </button>
      <button
        class="week-tab"
        :class="{ 'is-active': selectedWeek === 'next' }"
        @click="selectWeek('next')"
      >
        Next week
      </button>
    </div>

    <div v-if="loading" class="week-grid" aria-busy="true" aria-label="Loading your plan">
      <div v-for="n in 7" :key="n" class="day-column">
        <div class="day-header">
          <div class="day-heading">
            <span class="skeleton skeleton-line" style="width: 60px"></span>
            <span class="skeleton skeleton-line" style="width: 38px; height: 9px"></span>
          </div>
        </div>
        <div class="day-entries">
          <span class="skeleton skeleton-row" v-for="r in 3" :key="r"></span>
        </div>
        <span class="skeleton skeleton-row skeleton-add"></span>
      </div>
    </div>

    <div v-else class="week-grid">
      <div v-for="day in days" :key="day.date" class="day-column">
        <div class="day-header">
          <div class="day-heading">
            <span class="day-name">{{ day.label }}</span>
            <span class="day-date">{{ day.dateLabel }}</span>
          </div>
          <button
            v-if="day.entries.length"
            class="day-clear"
            :disabled="clearingDay === day.date"
            @click="clearDay(day)"
          >
            <Trash2 :size="13" :stroke-width="2" />
            {{ clearingDay === day.date ? 'Clearing…' : 'Clear all' }}
          </button>
        </div>

        <div class="day-entries">
          <div v-if="day.entries.length === 0" class="day-empty">No visits planned</div>
          <div v-for="entry in day.entries" :key="entry.uuid" class="entry-row">
            <span class="entry-name">{{ entry.customer?.name ?? '—' }}</span>
            <button
              class="btn-icon is-danger"
              aria-label="Remove from plan"
              :disabled="removingId === entry.uuid"
              @click="removeEntry(entry)"
            >
              <X :size="14" :stroke-width="2" />
            </button>
          </div>
        </div>

        <button
          class="day-add-trigger"
          :disabled="customers.length === 0"
          @click="openPicker(day)"
        >
          + Add customer…
        </button>
      </div>
    </div>

    <p v-if="customersError" class="week-note week-note--error">{{ customersError }}</p>
    <p v-else class="week-note">
      Planning is a web feature — the field workflow (starting/finishing a
      visit) happens on mobile and links back to a matching planned entry
      automatically.
    </p>

    <!-- Stays open across multiple picks (unlike a native <select>, which
         closes on every selection) so a rep can load up a day in one go. -->
    <AppModal
      :model-value="openDay !== null"
      :title="openDay ? `Add customers — ${openDay.label}, ${openDay.dateLabel}` : ''"
      max-width="sm"
      @update:model-value="closePicker"
    >
      <div class="picker">
        <AppSearchInput v-model="pickerSearch" placeholder="Search name or code…" />
        <div class="picker-list">
          <button
            v-for="c in pickerOptions"
            :key="c.id"
            class="picker-option"
            :disabled="addingDate === openDay.date"
            @click="addCustomer(openDay.date, c.id)"
          >
            <span class="picker-name">{{ c.name }}</span>
            <span v-if="c.customer_code" class="picker-code">{{ c.customer_code }}</span>
          </button>
          <div v-if="pickerOptions.length === 0" class="picker-empty">
            {{ pickerSearch ? 'No matches' : 'All customers added for this day' }}
          </div>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { X, Trash2 } from '@lucide/vue'
import api from '@/helpers/api'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppModal from '@/components/AppModal.vue'
import AppSearchInput from '@/components/form/AppSearchInput.vue'
import { formatDate } from '@/helpers/date'

const toast = useToast()

const plan = ref(null)
const loading = ref(true)
const customers = ref([])
const customersError = ref('')
const addingDate = ref(null)
const removingId = ref(null)
const clearingDay = ref(null) // day.date currently being bulk-cleared, or null
const openPickerFor = ref(null) // day.date whose add-customer modal is open, or null
const pickerSearch = ref('')
// 'next' by default: reps open this to plan ahead, not to stare at a week
// that's already half over. 'current' stays one tab away, still editable.
const selectedWeek = ref('next')

function weekStartFor(which) {
  const date = new Date()
  if (which === 'next') date.setDate(date.getDate() + 7)
  return date.toISOString().slice(0, 10)
}

async function fetchPlan() {
  loading.value = true
  try {
    const { data } = await api.get('/api/visit-plans', {
      params: { week_start: weekStartFor(selectedWeek.value) },
    })
    plan.value = data
  } finally {
    loading.value = false
  }
}

function selectWeek(which) {
  if (selectedWeek.value === which) return
  selectedWeek.value = which
  fetchPlan()
}

async function fetchCustomers() {
  try {
    // A rep's own book is small (Customer::visibleTo scopes it server-side),
    // so one bumped-per_page call covers it -- no need for a search picker.
    const { data } = await api.get('/api/customers', { params: { per_page: 200 } })
    customers.value = data.data
  } catch {
    customersError.value = 'Could not load your customer list.'
  }
}

onMounted(() => {
  fetchPlan()
  fetchCustomers()
})

// --- week grid: 7 days from plan.week_start_date, entries grouped by day ---
const days = computed(() => {
  if (!plan.value) return []
  const start = new Date(plan.value.week_start_date)
  const labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']

  return labels.map((label, i) => {
    const date = new Date(start)
    date.setDate(start.getDate() + i)
    const iso = date.toISOString().slice(0, 10)

    return {
      date: iso,
      label,
      dateLabel: date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }),
      // planned_date comes back as a full ISO datetime (VisitPlanEntry casts
      // it 'date'), not the plain YYYY-MM-DD `iso` computed above -- compare
      // on just the date portion, not string equality on the whole thing.
      entries: plan.value.entries.filter((e) => e.planned_date.slice(0, 10) === iso),
    }
  })
})

const weekRangeLabel = computed(() => {
  if (!days.value.length) return ''
  return `${days.value[0].dateLabel} – ${days.value[6].dateLabel}`
})

// A customer already planned for a given day shouldn't be offered again for
// that same day (the backend would reject it as a duplicate anyway).
function availableFor(day) {
  const plannedIds = new Set(day.entries.map((e) => e.customer_id))
  return customers.value.filter((c) => !plannedIds.has(c.id))
}

// --- add-customer picker: opens next to whichever day's trigger was
// clicked, stays open across multiple picks (a native <select> closes after
// every selection), searchable by name or customer code ---
const openDay = computed(() => days.value.find((d) => d.date === openPickerFor.value) ?? null)

const pickerOptions = computed(() => {
  if (!openDay.value) return []
  const available = availableFor(openDay.value)
  const q = pickerSearch.value.trim().toLowerCase()
  if (!q) return available
  return available.filter(
    (c) => c.name.toLowerCase().includes(q) || (c.customer_code ?? '').toLowerCase().includes(q),
  )
})

function openPicker(day) {
  pickerSearch.value = ''
  openPickerFor.value = day.date
}

function closePicker() {
  openPickerFor.value = null
  pickerSearch.value = ''
}

async function addCustomer(date, customerId) {
  addingDate.value = date
  try {
    const { data } = await api.post('/api/visit-plan-entries', {
      customer_id: customerId,
      planned_date: date,
    })
    plan.value.entries.push(data)
    toast.success('Added to plan')
  } catch (err) {
    toast.error(err.response?.data?.errors?.customer_id?.[0] || 'Could not add to plan')
  } finally {
    addingDate.value = null
  }
}

// --- remove ---
async function removeEntry(entry) {
  const ok = await confirm({
    title: 'Remove from plan?',
    text: `Remove ${entry.customer?.name ?? 'this customer'} from ${formatDate(entry.planned_date)}?`,
    confirmText: 'Remove',
    onConfirm: async () => {
      removingId.value = entry.uuid
      await api.delete(`/api/visit-plan-entries/${entry.uuid}`)
    },
  })
  removingId.value = null
  if (!ok) return
  plan.value.entries = plan.value.entries.filter((e) => e.uuid !== entry.uuid)
  toast.success('Removed from plan')
}

// --- clear a whole day ---
// There's no bulk endpoint: we fire one DELETE per entry (Promise.allSettled
// so one failure doesn't sink the rest) and reconcile from what actually
// succeeded. A day holds only a handful of entries, so N requests is fine --
// and this reuses the per-entry ownership check the single DELETE already does.
async function clearDay(day) {
  const count = day.entries.length
  const ok = await confirm({
    title: 'Clear this day?',
    text: `Remove all ${count} planned customer${count > 1 ? 's' : ''} from ${formatDate(day.date)}?`,
    confirmText: 'Remove all',
    danger: true,
  })
  if (!ok) return

  clearingDay.value = day.date
  const entries = [...day.entries]
  const results = await Promise.allSettled(
    entries.map((e) => api.delete(`/api/visit-plan-entries/${e.uuid}`)),
  )
  clearingDay.value = null

  const removed = new Set(
    entries.filter((_, i) => results[i].status === 'fulfilled').map((e) => e.uuid),
  )
  plan.value.entries = plan.value.entries.filter((e) => !removed.has(e.uuid))

  const failed = entries.length - removed.size
  if (failed) toast.error(`${failed} couldn't be removed. Please try again.`)
  else toast.success('Day cleared')
}
</script>

<style scoped>
.my-week {
  padding: 24px 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.week-header {
  display: flex;
  align-items: baseline;
  gap: 12px;
}
.week-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0;
}
.week-range {
  font-size: 13px;
  color: var(--color-text-muted);
}

.week-tabs {
  display: flex;
  gap: 4px;
  padding: 4px;
  background: var(--color-surface-hover);
  border-radius: 10px;
  width: fit-content;
}
.week-tab {
  padding: 6px 14px;
  border: none;
  background: transparent;
  border-radius: 7px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
}
.week-tab:hover:not(.is-active) {
  color: var(--color-text);
}
.week-tab.is-active {
  color: #fff;
  background: linear-gradient(90deg, var(--color-accent), rgba(var(--color-accent-rgb), 0.82));
  box-shadow: 0 4px 14px rgba(var(--color-accent-rgb), 0.35);
}

/* --- tab-change loading skeleton (mirrors dashboard's .skeleton language;
   scoped styles can't be shared across SFCs, so it's restated here) --- */
.skeleton {
  display: block;
  border-radius: 6px;
  background: var(--color-border);
  animation: skeleton-pulse 1.4s ease-in-out infinite;
}
.skeleton-line {
  height: 12px;
}
.skeleton-row {
  height: 32px;
  border-radius: 7px;
}
.skeleton-add {
  margin-top: auto;
  opacity: 0.6;
}
@keyframes skeleton-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.week-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 12px;
  overflow-x: auto;
}
@media (max-width: 900px) {
  .week-grid { grid-template-columns: repeat(3, minmax(160px, 1fr)); }
}
@media (max-width: 600px) {
  .week-grid { grid-template-columns: repeat(2, minmax(150px, 1fr)); }
}

.day-column {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 150px;
}

.day-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--color-border-subtle);
}
.day-heading {
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.day-name { font-size: 13px; font-weight: 700; color: var(--color-ink); }
.day-date { font-size: 11.5px; color: var(--color-text-muted); }

.day-clear {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid transparent;
  background: transparent;
  padding: 3px 7px;
  font-family: inherit;
  font-size: 11px;
  font-weight: 600;
  color: var(--color-danger);
  cursor: pointer;
  border-radius: 6px;
}
.day-clear:hover:not(:disabled) {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
}
.day-clear:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.day-entries {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-height: 24px;
}
.day-empty {
  font-size: 12px;
  color: var(--color-text-faint, var(--color-text-muted));
}

.entry-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  padding: 6px 8px;
  background: var(--color-surface-hover);
  border-radius: 7px;
}
.entry-name {
  font-size: 12.5px;
  color: var(--color-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.day-add-trigger {
  width: 100%;
  box-sizing: border-box;
  padding: 7px 8px;
  border-radius: 7px;
  border: 1px dashed var(--color-border);
  font-size: 12px;
  font-family: inherit;
  color: var(--color-text-muted);
  background: transparent;
  cursor: pointer;
  text-align: left;
}
.day-add-trigger:hover:not(:disabled) {
  border-color: var(--color-accent-border);
  color: var(--color-text);
}
.day-add-trigger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.picker {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 4px;
}
/* AppSearchInput hard-codes width:360px; inside the sm modal it should fill */
.picker :deep(.search-wrap) {
  width: 100%;
}
.picker-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: 46vh;
  overflow-y: auto;
}
.picker-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  background: transparent;
  font-family: inherit;
  font-size: 13px;
  color: var(--color-text);
  text-align: left;
  cursor: pointer;
}
.picker-option:hover:not(:disabled) {
  background: var(--color-surface-hover);
}
.picker-option:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.picker-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.picker-code {
  flex-shrink: 0;
  font-size: 11.5px;
  font-variant-numeric: tabular-nums;
  color: var(--color-text-muted);
}
.picker-empty {
  padding: 20px;
  font-size: 12.5px;
  color: var(--color-text-muted);
  text-align: center;
}

.week-note {
  font-size: 12.5px;
  color: var(--color-text-muted);
}
.week-note--error {
  color: var(--color-danger);
}
/* .btn-icon comes from the global system in App.vue */
</style>
