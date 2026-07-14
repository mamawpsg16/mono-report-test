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
            v-if="day.entries.length && !isFrozen(day)"
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
              v-if="!isFrozen(day)"
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
          v-if="!isFrozen(day)"
          class="day-add-trigger"
          @click="openPicker(day)"
        >
          + Add customer…
        </button>
        <!-- planned_date <= today is frozen server-side (VisitPlanService::
             addEntry / VisitPlanEntryPolicy::delete) -- this is just the read-
             only reflection of that, not the enforcement. -->
        <p v-else class="day-locked">
          <Lock :size="11" :stroke-width="2" />
          {{ isPast(day) ? 'Past day — locked' : 'Locked — day has started' }}
        </p>
      </div>
    </div>

    <p v-if="customersError" class="week-note week-note--error">{{ customersError }}</p>

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
          <div v-if="!pickerLoading && pickerOptions.length === 0" class="picker-empty">
            <template v-if="pickerSearch">No matches</template>
            <template v-else-if="pickerTotal > 0">All your customers are already planned for this day</template>
            <template v-else>No customers assigned to you</template>
          </div>
        </div>
      </div>
    </AppModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { X, Trash2, Lock } from '@lucide/vue'
import api from '@/helpers/api'
import { confirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import AppModal from '@/components/AppModal.vue'
import AppSearchInput from '@/components/form/AppSearchInput.vue'
import { formatDate } from '@/helpers/date'

const toast = useToast()

const plan = ref(null)
const loading = ref(true)
const customersError = ref('')
const addingDate = ref(null)
const removingId = ref(null)
const clearingDay = ref(null) // day.date currently being bulk-cleared, or null
const openPickerFor = ref(null) // day.date whose add-customer modal is open, or null
const pickerSearch = ref('')
const pickerResults = ref([]) // current server page of customers for the picker
const pickerTotal = ref(0) // total matching the search server-side (may exceed the page)
const pickerLoading = ref(false)
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

// Server-side search (not a client filter of a preloaded list): a rep's book
// can exceed one page, so filtering a capped preload would silently hide
// customers. Hits the same scoped /api/customers the Customers list uses.
async function fetchPickerCustomers(term) {
  pickerLoading.value = true
  try {
    const { data } = await api.get('/api/customers', {
      params: { search: term, per_page: 50 },
    })
    pickerResults.value = data.data
    pickerTotal.value = data.total
  } catch {
    customersError.value = 'Could not load your customer list.'
  } finally {
    pickerLoading.value = false
  }
}

// Debounce the search so we fire one request after typing settles, not one per
// keystroke -- mirrors the 500ms pattern the Customers/Users lists use.
let pickerDebounce
watch(pickerSearch, (term) => {
  clearTimeout(pickerDebounce)
  pickerDebounce = setTimeout(() => fetchPickerCustomers(term.trim()), 500)
})

onMounted(() => {
  fetchPlan()
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

// planned_date <= today is frozen server-side (VisitPlanService::addEntry /
// VisitPlanEntryPolicy::delete, no bypass, admins included). This is only the
// read-only reflection of that -- string comparison works because both sides
// are fixed-width YYYY-MM-DD.
const todayISO = new Date().toISOString().slice(0, 10)
function isFrozen(day) {
  return day.date <= todayISO
}
// Same freeze, but "day has started" only reads right for today -- a day
// before today didn't just start, it's over. Split the copy accordingly.
function isPast(day) {
  return day.date < todayISO
}

// --- add-customer picker: opens per day, stays open across multiple picks (a
// native <select> closes after every selection), server-side searchable by
// name or customer code ---
const openDay = computed(() => days.value.find((d) => d.date === openPickerFor.value) ?? null)

// A customer already planned for this day shouldn't be offered again (the
// backend would reject it as a duplicate anyway) -- filter the server page.
const pickerOptions = computed(() => {
  if (!openDay.value) return []
  const plannedIds = new Set(openDay.value.entries.map((e) => e.customer_id))
  return pickerResults.value.filter((c) => !plannedIds.has(c.id))
})

function openPicker(day) {
  // search is already '' here (reset on last close), so this doesn't trip the
  // debounced watch -- we fetch the initial page directly instead.
  openPickerFor.value = day.date
  fetchPickerCustomers('')
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
    // The freeze rejects under `planned_date`, duplicates under `customer_id`
    // -- check both. In practice the UI hides the add button on frozen days,
    // so this path is mostly a direct-API-call safety net, not a normal click.
    const errors = err.response?.data?.errors
    toast.error(errors?.customer_id?.[0] || errors?.planned_date?.[0] || 'Could not add to plan')
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

.day-locked {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 100%;
  box-sizing: border-box;
  padding: 7px 8px;
  border-radius: 7px;
  font-size: 11.5px;
  color: var(--color-text-faint, var(--color-text-muted));
  margin: 0;
}

.picker {
  display: flex;
  flex-direction: column;
  gap: 10px;
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
  /* size to content, capped so a long book scrolls instead of pushing the
     modal off-screen. No min-height: forcing one leaves a dead void below the
     content when there are few results, which reads as "off-centre". */
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
  /* modest presence so a no-results modal isn't a cramped sliver, centred
     rather than stretched tall with a void. */
  min-height: 96px;
  display: flex;
  align-items: center;
  justify-content: center;
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
