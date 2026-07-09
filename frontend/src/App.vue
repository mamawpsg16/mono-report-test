<template>
  <router-view />
</template>

<script setup>
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

  --color-success: #22c55e;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'DM Sans', system-ui, sans-serif;
  background: var(--color-bg);
  color: var(--color-ink);
  -webkit-font-smoothing: antialiased;
}
</style>
