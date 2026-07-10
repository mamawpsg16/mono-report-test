<template>
  <div class="dashboard-index">
    <div class="stat-grid">
      <div class="stat-card">
        <span class="stat-label">Total Customers</span>
        <span class="stat-value">128</span>
      </div>
      <div class="stat-card">
        <span class="stat-label">Uploads This Month</span>
        <span class="stat-value">7</span>
      </div>
      <div class="stat-card">
        <span class="stat-label">Active Roles</span>
        <span class="stat-value">2</span>
      </div>
    </div>
    <p class="dashboard-note">Placeholder data -- wired up once there's more than one module to summarize.</p>
  </div>
</template>

<script setup></script>

<style scoped>
.dashboard-index {
  padding: 24px 28px;
}

.stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.stat-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: 10px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.stat-label {
  font-size: 12.5px;
  font-weight: 500;
  color: var(--color-text-muted);
}

.stat-value {
  font-size: 26px;
  font-weight: 700;
  color: var(--color-ink);
  letter-spacing: -0.3px;
}

.dashboard-note {
  margin-top: 18px;
  font-size: 12.5px;
  color: var(--color-text-muted);
}
</style>
