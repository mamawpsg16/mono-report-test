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

/* --- App-wide button system -------------------------------------------------
   One definition per variant so every screen matches. These live here (global,
   unscoped) rather than per-view: a component's scoped `.btn-primary` compiles
   to `.btn-primary[data-v-x]` and would silently outrank these, so views must
   NOT redefine them. A view may still add a single extra property (e.g. a
   min-width) in its own scope — the cascade resolves per-property.
      .btn-primary   solid accent, the one main action per context
      .btn-secondary / .btn-ghost   bordered, for Cancel / Filter / Export
      .btn-icon      square 32px icon button; add .is-danger for destructive */
.btn-primary {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  padding: 9px 18px;
  border-radius: 8px;
  border: 1px solid var(--color-accent);
  background: var(--color-accent);
  color: #fff;
  font-size: 13px; font-weight: 600; font-family: inherit;
  cursor: pointer;
  transition: background 0.15s ease, opacity 0.15s ease;
}
.btn-primary:hover:not(:disabled) { background: var(--color-accent-hover); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-secondary,
.btn-ghost {
  display: inline-flex; align-items: center; justify-content: center; gap: 7px;
  padding: 9px 16px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text);
  font-size: 13px; font-weight: 600; font-family: inherit;
  cursor: pointer;
  transition: background 0.15s ease;
}
.btn-secondary:hover:not(:disabled),
.btn-ghost:hover:not(:disabled) { background: var(--color-surface-hover); }
.btn-secondary:disabled,
.btn-ghost:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-surface);
  color: var(--color-text-muted);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.btn-icon:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text); }
.btn-icon:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-icon.is-danger:hover:not(:disabled) {
  border-color: var(--color-danger-border);
  color: var(--color-danger);
  background: var(--color-danger-soft);
}
</style>
