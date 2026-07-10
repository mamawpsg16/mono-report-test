<template>
  <Teleport to="body">
    <div class="toast-stack">
      <TransitionGroup name="toast">
        <div v-for="t in toasts" :key="t.id" class="toast" :class="t.type" @click="dismiss(t.id)">
          <component :is="icons[t.type]" :size="17" :stroke-width="2.2" class="toast-icon" />
          <span class="toast-msg">{{ t.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { CircleCheck, CircleX, TriangleAlert, Info } from '@lucide/vue'
import { useToast } from '@/composables/useToast'

const { toasts, dismiss } = useToast()

const icons = {
  success: CircleCheck,
  error: CircleX,
  warning: TriangleAlert,
  info: Info,
}
</script>

<style scoped>
.toast-stack {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 900;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
}

.toast {
  pointer-events: auto;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 260px;
  max-width: 380px;
  padding: 12px 16px;
  border-radius: 10px;
  border: 1px solid var(--color-border);
  border-left-width: 4px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.06);
  font-size: 13px;
  font-weight: 500;
  color: var(--color-ink);
  cursor: pointer;
}

.toast-icon {
  flex-shrink: 0;
}
.toast-msg {
  line-height: 1.4;
}

/* per-type: tinted background, colored left bar + icon */
.toast.success {
  background: var(--color-success-soft);
  border-color: var(--color-success-border);
  border-left-color: var(--color-success);
}
.toast.success .toast-icon { color: var(--color-success); }

.toast.error {
  background: var(--color-danger-soft);
  border-color: var(--color-danger-border);
  border-left-color: var(--color-danger);
}
.toast.error .toast-icon { color: var(--color-danger); }

.toast.warning {
  background: var(--color-warning-soft);
  border-color: var(--color-warning-border);
  border-left-color: var(--color-warning);
}
.toast.warning .toast-icon { color: var(--color-warning); }

.toast.info {
  background: var(--color-accent-soft);
  border-color: var(--color-accent-border);
  border-left-color: var(--color-accent);
}
.toast.info .toast-icon { color: var(--color-accent); }

/* slide in from the right, along the bottom edge */
.toast-enter-active,
.toast-leave-active {
  transition: transform 0.2s ease, opacity 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  transform: translateX(16px);
  opacity: 0;
}
</style>
