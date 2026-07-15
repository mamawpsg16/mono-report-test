<template>
  <div class="coverage-report">
    <div class="toolbar">
      <div class="week-nav">
        <!-- Coarse jump first, fine-grained stepping after -- pick the
             range, then nudge week-by-week within it. Native month input:
             one compact control, OS-native picker (a proper wheel picker on
             mobile, not a JS calendar widget). -->
        <span class="week-jump-label">Month</span>
        <input
          type="month"
          class="week-jump"
          aria-label="Jump to month"
          :disabled="loading"
          :value="monthInputValue"
          :min="yearOptions[0] + '-01'"
          :max="yearOptions[yearOptions.length - 1] + '-12'"
          @change="jumpToMonthValue($event.target.value)"
        />
        <button class="btn-secondary" :disabled="loading" @click="shiftWeek(-1)">&larr; Previous week</button>
        <span class="week-label">{{ weekLabel }}</span>
        <button class="btn-secondary" :disabled="loading" @click="shiftWeek(1)">Next week &rarr;</button>
      </div>

      <label v-if="isAdmin" class="rep-filter">
        <span class="rep-filter-label">Representative</span>
        <select v-model="selectedRepId" class="rep-filter-input">
          <option :value="null">All reps</option>
          <option v-for="rep in reps" :key="rep.id" :value="rep.id">{{ rep.name }}</option>
        </select>
      </label>
    </div>

    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-label">Planned</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value">{{ plannedCount }}</span>
      </div>
      <div class="stat-pill">
        <span class="stat-label">Visited</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value stat-value--visited">{{ visitedCount }}</span>
      </div>
      <div class="stat-pill stat-pill--missed">
        <span class="stat-label">Missed</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value stat-value--missed">{{ missedCount }}</span>
      </div>
      <div class="stat-pill">
        <span class="stat-label">Coverage rate</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value">{{ coveragePct }}%</span>
      </div>
    </div>

    <DatatableClient
      :title="$route.meta.title"
      :headers="headers"
      :items="filteredEntries"
      :loading="loading"
      :body-row-class-name="rowClassName"
      empty-message="No visits match this filter."
      search-placeholder="Search by customer..."
    >
      <template #actions>
        <div class="status-tabs">
          <button
            v-for="tab in statusTabs"
            :key="tab.value"
            class="status-tab"
            :class="{ 'is-active': statusFilter === tab.value }"
            @click="statusFilter = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>
      </template>
      <template #item-planned_date="entry">
        {{ formatDate(entry.planned_date) }}
      </template>
      <template #item-status="entry">
        <span class="status-badge" :class="`is-${entry.status}`">{{ statusLabel(entry.status) }}</span>
      </template>
    </DatatableClient>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuth } from '@/composables/useAuth'
import api from '@/helpers/api'
import DatatableClient from '@/components/table/DatatableClient.vue'

const auth = useAuth()
const isAdmin = computed(() => auth.can('roles.manage'))

const loading = ref(true)
const myWeek = ref({ entries: [], planned_count: 0, visited_count: 0, missed_count: 0 })
const team = ref([])

const reps = ref([])
const selectedRepId = ref(null)

async function fetchReps() {
  const { data } = await api.get('/api/users', { params: { per_page: 200 } })
  reps.value = data.data.filter(
    (u) => u.is_active && u.roles.some((r) => r.name === 'sales_representative')
  )
}

// Local calendar date as YYYY-MM-DD, not toISOString() -- that converts to
// UTC, which can shift the date by a day depending on timezone/time-of-day.
function toLocalIsoDate(d) {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function mondayOf(d) {
  const monday = new Date(d)
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7))
  return monday
}

// The Monday of the week currently on screen, as YYYY-MM-DD -- the single
// source of truth both the label and the API param read from, and what
// Previous/Next/the month-year dropdowns all update. Starts on this week's Monday.
const selectedMonday = ref(toLocalIsoDate(mondayOf(new Date())))

const weekLabel = computed(() => {
  // new Date('YYYY-MM-DD') parses as UTC midnight; do all formatting in UTC
  // so the displayed date can't drift a day from what's stored.
  const monday = new Date(selectedMonday.value)
  const sunday = new Date(monday)
  sunday.setUTCDate(sunday.getUTCDate() + 6)

  // "Week N" = which 7-day chunk of the month the Monday's day-of-month
  // falls into (days 1-7 = Week 1, 8-14 = Week 2, ...) -- a simple, not
  // calendar-standard, definition, paired with the exact dates so it's
  // never ambiguous at a month boundary (a Mon-Sun week can span two months).
  const weekOfMonth = Math.ceil(monday.getUTCDate() / 7)
  const mondayLabel = monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' })
  // Include the month on the end date too when the week crosses a month
  // boundary (e.g. "Jul 27 – Aug 2") -- day-only would silently drop the
  // month change and read as if it stayed in July.
  const sundayLabel = sunday.toLocaleDateString(undefined, {
    month: sunday.getUTCMonth() === monday.getUTCMonth() ? undefined : 'short',
    day: 'numeric',
    timeZone: 'UTC',
  })

  return `Week ${weekOfMonth} · ${mondayLabel}–${sundayLabel}`
})

function weekStartParam() {
  return selectedMonday.value
}

function shiftWeek(delta) {
  const d = new Date(selectedMonday.value + 'T00:00:00')
  d.setDate(d.getDate() + delta * 7)
  selectedMonday.value = toLocalIsoDate(d)
}

// A fixed, generous-enough window rather than an open-ended range -- this is
// a business report, not an archive; widen if a real need shows up.
const yearOptions = (() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let y = currentYear - 5; y <= currentYear + 1; y++) years.push(y)
  return years
})()

// <input type="month"> reads/writes "YYYY-MM". Derived from selectedMonday
// (parsed as UTC, matching how it's stored/displayed elsewhere) so the
// control always reflects whatever week is currently on screen.
const monthInputValue = computed(() => {
  const d = new Date(selectedMonday.value)
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}`
})

// Snaps to the Monday of the week containing the 1st of the chosen month --
// this report is week-grained, so a day-of-month picker would be a choice
// with no real answer.
function jumpToMonthValue(monthValue) {
  if (!monthValue) return
  const [year, month] = monthValue.split('-').map(Number)
  selectedMonday.value = toLocalIsoDate(mondayOf(new Date(year, month - 1, 1)))
}

const headers = computed(() => [
  ...(isAdmin.value ? [{ text: 'Representative', value: 'representative_name' }] : []),
  { text: 'Customer', value: 'customer_name' },
  { text: 'Planned date', value: 'planned_date' },
  { text: 'Status', value: 'status', width: 110 },
])

// Team data arrives grouped by rep; flatten to one row per entry with the
// rep's name attached, then optionally narrow to one rep.
const teamEntries = computed(() =>
  team.value.flatMap((rep) =>
    rep.entries.map((entry) => ({ ...entry, representative_name: rep.representative.name, representative_id: rep.representative.id }))
  )
)

const displayedEntries = computed(() => {
  if (!isAdmin.value) return myWeek.value.entries
  if (selectedRepId.value === null) return teamEntries.value
  return teamEntries.value.filter((e) => e.representative_id === selectedRepId.value)
})

// Totals reflect whatever's currently displayed (post rep-filter), not the
// raw API response -- picking a rep should narrow the pills too. These are
// independent of the table's own status-tab filter below (the pills always
// summarize the whole week; the tabs only narrow what the table shows).
const plannedCount = computed(() => displayedEntries.value.length)
const visitedCount = computed(() => displayedEntries.value.filter((e) => e.status === 'visited').length)
const missedCount = computed(() => displayedEntries.value.filter((e) => e.status === 'missed').length)
const coveragePct = computed(() =>
  plannedCount.value === 0 ? 0 : Math.round((visitedCount.value / plannedCount.value) * 100)
)

const statusTabs = [
  { value: 'all', label: 'All' },
  { value: 'missed', label: 'Missed' },
  { value: 'visited', label: 'Visited' },
]
const statusFilter = ref('all')

const filteredEntries = computed(() => {
  if (statusFilter.value === 'all') return displayedEntries.value
  return displayedEntries.value.filter((e) => e.status === statusFilter.value)
})

function rowClassName(entry) {
  return entry.status === 'missed' ? 'row-missed' : ''
}

let abortController = null

async function fetchReport() {
  abortController?.abort()
  const myController = new AbortController()
  abortController = myController
  loading.value = true
  try {
    if (isAdmin.value) {
      const { data } = await api.get('/api/reports/coverage/team', {
        params: { week_start: weekStartParam() },
        signal: myController.signal,
      })
      team.value = data
    } else {
      const { data } = await api.get('/api/reports/coverage/my-week', {
        params: { week_start: weekStartParam() },
        signal: myController.signal,
      })
      myWeek.value = data
    }
  } catch (err) {
    if (err.code !== 'ERR_CANCELED') throw err
  } finally {
    if (!myController.signal.aborted) loading.value = false
  }
}

function formatDate(value) {
  // value is a plain YYYY-MM-DD from the API, which Date parses as UTC
  // midnight -- format in UTC too, or a local-timezone-behind-UTC browser
  // would display the day before.
  return new Date(value).toLocaleDateString(undefined, {
    weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC',
  })
}

function statusLabel(status) {
  return { visited: 'Visited', missed: 'Missed', pending: 'Pending' }[status] ?? status
}

watch(selectedMonday, fetchReport)

onMounted(() => {
  if (isAdmin.value) fetchReps()
  fetchReport()
})
</script>

<style scoped>
.coverage-report {
  padding: 24px 36px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (max-width: 640px) {
  .coverage-report { padding: 16px; }
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  flex-wrap: wrap;
}

.week-nav {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}

@media (max-width: 640px) {
  .toolbar { flex-direction: column; align-items: stretch; }
  .week-nav { gap: 8px; }
  .week-nav .btn-secondary { flex: 1; padding-left: 8px; padding-right: 8px; }
  .rep-filter { justify-content: space-between; }
}

.week-label {
  font-size: 12.5px;
  font-weight: 700;
  color: var(--color-accent);
  background: var(--color-accent-soft);
  padding: 5px 12px;
  border-radius: 999px;
  white-space: nowrap;
}

.week-jump-label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-muted);
}

.week-jump {
  padding: 6px 9px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-surface);
}

.rep-filter {
  display: flex;
  align-items: center;
  gap: 8px;
}

.rep-filter-label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--color-text-muted);
}

.rep-filter-input {
  padding: 7px 10px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  font-size: 13px;
  font-family: inherit;
  color: var(--color-text);
  background: var(--color-surface);
}

.stat-row {
  display: flex;
  gap: 12px;
}

@media (max-width: 640px) {
  .stat-row { display: grid; grid-template-columns: repeat(2, 1fr); }
}

.stat-pill {
  flex: 1;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.stat-pill--missed { border-color: var(--color-danger-border); }

.stat-label { font-size: 12px; font-weight: 600; color: var(--color-text-muted); }
.stat-value { font-size: 22px; font-weight: 800; color: var(--color-ink); }
.stat-value--visited { color: var(--color-success); }
.stat-value--missed { color: var(--color-danger); }

.status-tabs {
  display: flex;
  gap: 3px;
  padding: 3px;
  background: var(--color-surface-hover);
  border-radius: 8px;
}

.status-tab {
  padding: 5px 12px;
  border: none;
  background: transparent;
  border-radius: 6px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--color-text-muted);
  cursor: pointer;
  font-family: inherit;
}
.status-tab.is-active {
  color: #fff;
  background: var(--color-accent);
}

.skeleton {
  display: inline-block;
  border-radius: 6px;
  background: var(--color-border);
  animation: skeleton-pulse 1.4s ease-in-out infinite;
}
.skeleton-value { width: 36px; height: 24px; }
@keyframes skeleton-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.status-badge.is-visited {
  color: var(--color-success);
  background: var(--color-success-soft);
}
.status-badge.is-missed {
  color: var(--color-danger);
  background: var(--color-danger-soft);
}
.status-badge.is-pending {
  color: var(--color-accent);
  background: rgba(var(--color-accent-rgb), 0.12);
}

/* bodyRowClassName (AppDataTable -> vue3-easy-data-table) applies this class
   to the <tr> itself for missed entries -- :deep() reaches into the
   library's unscoped markup, same technique AppDataTable already uses for
   its own header/cell rules. */
:deep(tr.row-missed) {
  background: var(--color-danger-soft);
  box-shadow: inset 3px 0 0 var(--color-danger);
}
</style>
