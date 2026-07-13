<template>
  <div class="dashboard">
    <!-- Admin board: org-wide numbers. Gated on the admin permission so a rep
         (who may only see their own customers) never lands here. -->
    <template v-if="isAdmin">
      <div class="stat-grid stat-grid--4">
        <div class="stat-card">
          <span class="stat-label">Total Customers</span>
          <span v-if="loading" class="skeleton skeleton-value"></span>
          <span v-else class="stat-value">{{ metrics.total_customers }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Unassigned Customers</span>
          <div class="stat-inline">
            <span v-if="loading" class="skeleton skeleton-value"></span>
            <template v-else>
              <span class="stat-value stat-value--warning">{{ metrics.unassigned_customers }}</span>
              <span v-if="metrics.unassigned_customers > 0" class="pill pill--warning">needs coverage</span>
            </template>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-label">Active Reps</span>
          <span v-if="loading" class="skeleton skeleton-value"></span>
          <span v-else class="stat-value">{{ metrics.active_reps }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Pending Invitations</span>
          <span v-if="loading" class="skeleton skeleton-value"></span>
          <span v-else class="stat-value">{{ metrics.pending_invitations }}</span>
        </div>
      </div>

      <div class="panel-grid">
        <section class="panel">
          <header class="panel-header">Rep coverage</header>
          <template v-if="loading">
            <div v-for="n in 3" :key="n" class="row">
              <div class="row-lead">
                <span class="skeleton skeleton-avatar"></span>
                <span class="skeleton skeleton-text" style="width: 120px"></span>
              </div>
              <span class="skeleton skeleton-text" style="width: 70px"></span>
            </div>
          </template>
          <template v-else>
            <div v-if="metrics.rep_coverage.length === 0" class="row">
              <span class="row-meta">No active reps yet</span>
            </div>
            <div v-for="rep in metrics.rep_coverage" :key="rep.name" class="row">
              <div class="row-lead">
                <span class="avatar">{{ initialsOf(rep.name) }}</span>
                <span class="row-name">{{ rep.name }}</span>
              </div>
              <span class="row-meta">{{ rep.customers_count }} customers</span>
            </div>
          </template>
        </section>

        <section class="panel">
          <!-- Still placeholder: needs the deferred `activities` table before
               it can show real events. -->
          <header class="panel-header">Recent upload activity</header>
          <div v-for="(ev, i) in activity" :key="i" class="row row--dim">
            <div class="row-stack">
              <div class="row-text">{{ ev.text }}</div>
              <div class="row-when">{{ ev.when }}</div>
            </div>
          </div>
        </section>
      </div>

      <p class="dashboard-note">
        Weekly visit planning (P4) rolls in here once it ships — pipeline
        size and unconverted prospects stay mobile-only field metrics (see
        the Prospects/Visits pages for what's already live).
      </p>
    </template>

    <!-- Rep board: scoped to the signed-in rep's own book of business. -->
    <template v-else>
      <div class="stat-grid stat-grid--3">
        <div class="stat-card">
          <span class="stat-label">My Customers</span>
          <span v-if="loading" class="skeleton skeleton-value"></span>
          <span v-else class="stat-value">{{ myBook.my_customers_count }}</span>
        </div>
        <div class="stat-card stat-card--soon">
          <span class="stat-label">Today's Planned Visits</span>
          <span class="stat-soon">Coming in P4</span>
        </div>
        <div class="stat-card" :class="{ 'stat-card--soon': !loading && !myBook.open_visit }">
          <span class="stat-label">Open Visit</span>
          <span v-if="loading" class="skeleton skeleton-value"></span>
          <span v-else-if="myBook.open_visit" class="stat-value stat-value--open">{{ myBook.open_visit.customer_name }}</span>
          <span v-else class="stat-soon">None open</span>
        </div>
      </div>

      <section class="panel">
        <header class="panel-header">My customers</header>
        <template v-if="loading">
          <div v-for="n in 3" :key="n" class="row">
            <span class="skeleton skeleton-text" style="width: 140px"></span>
            <span class="skeleton skeleton-text" style="width: 60px"></span>
          </div>
        </template>
        <template v-else>
          <div v-if="myBook.my_customers.length === 0" class="row">
            <span class="row-meta">No customers assigned to you yet</span>
          </div>
          <div v-for="c in myBook.my_customers" :key="c.name" class="row">
            <span class="row-name">{{ c.name }}</span>
            <span class="row-meta">{{ c.city }}</span>
          </div>
        </template>
      </section>

      <section class="panel">
        <header class="panel-header">My activity</header>
        <div v-for="(ev, i) in repActivity" :key="i" class="row" :class="{ 'row--dim': ev.dim }">
          <div class="row-stack">
            <div class="row-text">{{ ev.text }}</div>
            <div class="row-when">{{ ev.when }}</div>
          </div>
          <span class="pill pill--accent">{{ ev.tag }}</span>
        </div>
      </section>

      <p class="dashboard-note">
        Weekly visit planning (P4) will replace "Today's Planned Visits";
        visit check-in/out already works from the field (see Visits) — this
        activity feed itself still needs an events log to show it live.
      </p>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import api from '@/helpers/api'

const auth = useAuth()
// roles.manage is the codebase's admin gate (same one guarding /users). Reps
// (customers.view only) fall through to the rep board.
const isAdmin = computed(() => auth.can('roles.manage'))

// Live admin metrics from GET /api/dashboard/metrics (admin-gated). Only the
// admin board consumes these; reps never call it (would 403).
const metrics = ref({
  total_customers: 0,
  unassigned_customers: 0,
  active_reps: 0,
  pending_invitations: 0,
  rep_coverage: [],
})
// The signed-in rep's own book of business (their assigned customers). Only the
// rep board consumes this; admins hit /metrics instead.
const myBook = ref({ my_customers_count: 0, my_customers: [], open_visit: null })
const loading = ref(true)

onMounted(async () => {
  try {
    if (isAdmin.value) {
      const { data } = await api.get('/api/dashboard/metrics')
      metrics.value = data
    } else {
      const { data } = await api.get('/api/dashboard/my-book')
      myBook.value = data
    }
  } finally {
    loading.value = false
  }
})

// Build a 2-letter avatar from a rep's name (formatting is a view concern, so
// the API returns the plain name and we derive initials here).
function initialsOf(name) {
  return name
    .split(' ')
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

// Placeholder data, mirroring the Dashboard design mock. Real counts/lists wait
// on a stats endpoint (and the P2–P4 tables); the notes above say what's coming.
const activity = [
  { text: 'customers_july.csv processed — 42 rows, 3 updates', when: '2 hours ago' },
  { text: '"which customers are in Accra?" asked via RAG', when: '5 hours ago' },
  { text: 'customers_batch2.csv processed — 18 new rows', when: 'yesterday' },
  { text: 'Invitation resent to rep@dataforge.test', when: '2 days ago' },
]

// dim = the action belongs to a phase (Visit P3 / Prospect P2 / Plan P4) that
// isn't built yet, so it reads as "preview" rather than something you can do.
const repActivity = [
  { text: 'Uploaded customers_july.csv — 12 rows, 2 updates', when: '3 hours ago', tag: 'Upload', dim: false },
  { text: 'Logged visit — Accra Textiles Ltd', when: 'yesterday', tag: 'Visit', dim: true },
  { text: 'Added prospect — Osu Wholesale', when: '2 days ago', tag: 'Prospect', dim: true },
  { text: 'Ticked planned visit — Kumasi Fresh Foods', when: '3 days ago', tag: 'Plan', dim: true },
  { text: 'Uploaded customers_batch2.csv — 5 rows, 1 update', when: 'last week', tag: 'Upload', dim: false },
]
</script>

<style scoped>
.dashboard {
  padding: 24px 28px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* --- loading skeleton --- */
.skeleton {
  display: inline-block;
  border-radius: 6px;
  background: var(--color-border);
  animation: skeleton-pulse 1.4s ease-in-out infinite;
}
.skeleton-value {
  width: 56px;
  height: 30px; /* matches .stat-value's line-height */
}
.skeleton-text {
  height: 13px;
}
.skeleton-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  flex-shrink: 0;
}
@keyframes skeleton-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

/* --- stat cards --- */
.stat-grid { display: grid; gap: 16px; }
.stat-grid--4 { grid-template-columns: repeat(4, 1fr); }
.stat-grid--3 { grid-template-columns: repeat(3, 1fr); }

@media (max-width: 900px) {
  .stat-grid--4 { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .stat-grid--4, .stat-grid--3 { grid-template-columns: 1fr; }
}

.stat-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.stat-card--soon { opacity: 0.6; }

.stat-label { font-size: 13px; font-weight: 600; color: var(--color-text-muted); }
.stat-value { font-size: 30px; font-weight: 800; color: var(--color-ink); letter-spacing: -0.4px; }
.stat-value--warning { color: var(--color-warning); }
.stat-value--open { font-size: 20px; color: var(--color-accent); }
.stat-inline { display: flex; align-items: baseline; gap: 8px; }
.stat-soon { font-size: 20px; font-weight: 700; color: var(--color-text-muted); }

/* --- pills --- */
.pill { font-size: 11.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; white-space: nowrap; }
.pill--warning {
  color: var(--color-warning);
  background: var(--color-warning-soft);
  border: 1px solid var(--color-warning-border);
}
.pill--accent { color: var(--color-accent); background: var(--color-accent-soft); }

/* --- panels (list cards) --- */
.panel-grid {
  display: grid;
  grid-template-columns: 1.3fr 1fr;
  gap: 16px;
  align-items: start;
}
@media (max-width: 900px) {
  .panel-grid { grid-template-columns: 1fr; }
}

.panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
}
.panel-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--color-border-subtle);
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
}

.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 13px 20px;
  border-bottom: 1px solid var(--color-border-subtle);
}
.row:last-child { border-bottom: none; }
.row--dim { opacity: 0.55; }

.row-lead { display: flex; align-items: center; gap: 10px; min-width: 0; }
.row-stack { min-width: 0; }
.row-name { font-size: 13.5px; font-weight: 600; color: var(--color-ink); }
.row-meta { font-size: 13px; color: var(--color-text-muted); flex-shrink: 0; }
.row-text { font-size: 13.5px; color: var(--color-text); font-weight: 500; }
.row-when { font-size: 12px; color: var(--color-text-muted); margin-top: 2px; }

.avatar {
  width: 28px; height: 28px;
  border-radius: 50%;
  background: var(--color-accent-soft);
  color: var(--color-accent);
  font-size: 12px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.dashboard-note { font-size: 12.5px; color: var(--color-text-muted); }
</style>
