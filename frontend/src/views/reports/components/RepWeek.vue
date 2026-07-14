<template>
  <div class="rep-week">
    <div class="stat-row">
      <div class="stat-pill">
        <span class="stat-label">Planned</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value">{{ report.planned_count }}</span>
      </div>
      <div class="stat-pill">
        <span class="stat-label">Visited</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value stat-value--visited">{{ report.visited_count }}</span>
      </div>
      <div class="stat-pill">
        <span class="stat-label">Missed</span>
        <span v-if="loading" class="skeleton skeleton-value"></span>
        <span v-else class="stat-value stat-value--missed">{{ report.missed_count }}</span>
      </div>
    </div>

    <section class="panel">
      <template v-if="loading">
        <div v-for="n in 3" :key="n" class="row">
          <span class="skeleton skeleton-text" style="width: 140px"></span>
          <span class="skeleton skeleton-text" style="width: 70px"></span>
        </div>
      </template>
      <template v-else>
        <div v-if="report.entries.length === 0" class="row">
          <span class="row-meta">No entries planned for this week</span>
        </div>
        <div v-for="(entry, i) in report.entries" :key="i" class="row">
          <div class="row-stack">
            <span class="row-name">{{ entry.customer_name }}</span>
            <span class="row-when">{{ formatDate(entry.planned_date) }}</span>
          </div>
          <span class="status-badge" :class="`is-${entry.status}`">{{ statusLabel(entry.status) }}</span>
        </div>
      </template>
    </section>
  </div>
</template>

<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  report: {
    type: Object,
    required: true,
  },
})

function formatDate(value) {
  return new Date(value).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}

function statusLabel(status) {
  return { visited: 'Visited', missed: 'Missed', pending: 'Pending' }[status] ?? status
}
</script>

<style scoped>
.rep-week {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.stat-row {
  display: flex;
  gap: 12px;
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

.stat-label { font-size: 12px; font-weight: 600; color: var(--color-text-muted); }
.stat-value { font-size: 22px; font-weight: 800; color: var(--color-ink); }
.stat-value--visited { color: var(--color-success); }
.stat-value--missed { color: var(--color-danger); }

.skeleton {
  display: inline-block;
  border-radius: 6px;
  background: var(--color-border);
  animation: skeleton-pulse 1.4s ease-in-out infinite;
}
.skeleton-value { width: 36px; height: 24px; }
.skeleton-text { height: 13px; }
@keyframes skeleton-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 1; }
}

.panel {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  overflow: hidden;
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

.row-stack { min-width: 0; }
.row-name { display: block; font-size: 13.5px; font-weight: 600; color: var(--color-ink); }
.row-when { display: block; font-size: 12px; color: var(--color-text-muted); margin-top: 2px; }
.row-meta { font-size: 13px; color: var(--color-text-muted); }

.status-badge {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
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
</style>
