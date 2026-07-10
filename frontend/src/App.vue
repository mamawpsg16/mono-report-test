<template>
  <div class="app-root">
    <router-view />
    <ConfirmModal />
    <ToastHost />
  </div>
</template>

<script setup>
import ConfirmModal from '@/components/ConfirmModal.vue'
import ToastHost from '@/components/ToastHost.vue'
</script>

<style>
:root {
  --color-accent: #1d4ed8;
  --color-accent-hover: #1e40af;
  --color-accent-soft: #eff6ff;
  --color-accent-border: #bfdbfe;
  --color-accent-rgb: 29, 78, 216;

  --color-ink: #0f172a;
  --color-text: #374151;
  --color-text-muted: #64748b;
  --color-text-faint: #cbd5e1;

  --color-surface: #ffffff;
  --color-surface-hover: #f8fafc;
  --color-bg: #f1f5f9;

  --color-border: #e2e8f0;
  --color-border-subtle: #f3f4f6;
  --color-border-strong: #cbd5e1;

  --color-danger: #dc2626;
  --color-danger-soft: #fef2f2;
  --color-danger-border: #fecaca;

  --color-success: #16a34a;
  --color-success-soft: #f0fdf4;
  --color-success-border: #bbf7d0;

  --color-warning: #d97706;
  --color-warning-soft: #fffbeb;
  --color-warning-border: #fde68a;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', system-ui, sans-serif;
  background: var(--color-bg);
  color: var(--color-ink);
  -webkit-font-smoothing: antialiased;
}
</style>
