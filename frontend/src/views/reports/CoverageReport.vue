<template>
  <div class="coverage-report">
    <div class="week-nav">
      <button class="btn-secondary" :disabled="loading" @click="shiftWeek(-1)">&larr; Previous week</button>
      <span class="week-label">{{ weekLabel }}</span>
      <button class="btn-secondary" :disabled="loading" @click="shiftWeek(1)">Next week &rarr;</button>
    </div>

    <!-- Rep view: one summary + one list, this rep's own week. -->
    <template v-if="!isAdmin">
      <RepWeek :loading="loading" :report="myWeek" />
    </template>

    <!-- Admin view: every rep's week, one block each. -->
    <template v-else>
      <div v-if="!loading && team.length === 0" class="empty-note">
        No plans exist for this week yet.
      </div>
      <section v-for="rep in team" :key="rep.representative.name" class="rep-block">
        <h2 class="rep-block-title">{{ rep.representative.name }}</h2>
        <RepWeek :loading="loading" :report="rep" />
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuth } from '@/composables/useAuth'
import api from '@/helpers/api'
import RepWeek from './components/RepWeek.vue'

const auth = useAuth()
const isAdmin = computed(() => auth.can('roles.manage'))

const loading = ref(true)
const weekOffset = ref(0)
const myWeek = ref({ entries: [], planned_count: 0, visited_count: 0, missed_count: 0 })
const team = ref([])

const weekLabel = computed(() => {
  const monday = new Date()
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7) + weekOffset.value * 7)
  return monday.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' (week of)'
})

function weekStartParam() {
  const monday = new Date()
  monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7) + weekOffset.value * 7)
  return monday.toISOString().slice(0, 10)
}

async function fetchReport() {
  loading.value = true
  try {
    if (isAdmin.value) {
      const { data } = await api.get('/api/reports/coverage/team', {
        params: { week_start: weekStartParam() },
      })
      team.value = data
    } else {
      const { data } = await api.get('/api/reports/coverage/my-week', {
        params: { week_start: weekStartParam() },
      })
      myWeek.value = data
    }
  } finally {
    loading.value = false
  }
}

function shiftWeek(delta) {
  weekOffset.value += delta
}

watch(weekOffset, fetchReport)
onMounted(fetchReport)
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

.week-nav {
  display: flex;
  align-items: center;
  gap: 14px;
}

.week-label {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--color-ink);
}

.empty-note {
  font-size: 13.5px;
  color: var(--color-text-muted);
  padding: 20px;
  text-align: center;
}

.rep-block {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.rep-block-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--color-ink);
  margin: 0;
}
</style>
