<template>
  <Teleport to="body">
    <Transition name="confirm-fade">
      <div v-if="state.open" class="confirm-backdrop" @click.self="cancel">
        <div class="confirm-panel" :class="{ danger: state.danger }">
          <p class="confirm-title">{{ state.title }}</p>

          <!-- html is trusted markup only (see useConfirm.js); text is escaped -->
          <div v-if="state.html" class="confirm-body" v-html="state.html"></div>
          <p v-else-if="state.text" class="confirm-body">{{ state.text }}</p>

          <p v-if="state.error" class="confirm-error">{{ state.error }}</p>

          <div class="confirm-actions">
            <button class="btn-ghost" :disabled="state.loading" @click="cancel">
              {{ state.cancelText }}
            </button>
            <button
              class="btn-confirm"
              :class="{ danger: state.danger }"
              :disabled="state.loading"
              @click="accept"
            >
              <span v-if="state.loading" class="spinner"></span>
              {{ state.loading ? 'Working…' : state.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { useConfirmDialog } from '@/composables/useConfirm'

const { state, accept, cancel } = useConfirmDialog()
</script>

<style scoped>
/* z-index above AppModal (500) so a confirm can sit over an open modal */
.confirm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 700;
  background: rgba(15, 15, 25, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.confirm-panel {
  width: 100%;
  max-width: 420px;
  background: var(--color-surface);
  border-radius: 16px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.16), 0 2px 8px rgba(0, 0, 0, 0.08);
  padding: 24px;
}

.confirm-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-ink);
  margin-bottom: 8px;
}

.confirm-body {
  font-size: 13.5px;
  line-height: 1.5;
  color: var(--color-text);
}
.confirm-body :deep(strong) {
  color: var(--color-ink);
  font-weight: 600;
}

.confirm-error {
  margin-top: 12px;
  font-size: 13px;
  color: var(--color-danger);
}

.confirm-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 22px;
}

/* .btn-ghost comes from the global system in App.vue. .btn-confirm stays local:
   it's the primary action plus a danger variant and a loading spinner. */
.btn-confirm {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 8px;
  border: 1px solid var(--color-accent);
  background: var(--color-accent);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
  transition: opacity 0.15s;
}
.btn-confirm:hover:not(:disabled) {
  opacity: 0.9;
}
.btn-confirm:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.btn-confirm.danger {
  border-color: var(--color-danger);
  background: var(--color-danger);
}

.spinner {
  width: 13px;
  height: 13px;
  border: 2px solid rgba(255, 255, 255, 0.5);
  border-top-color: #fff;
  border-radius: 50%;
  animation: confirm-spin 0.6s linear infinite;
}
@keyframes confirm-spin {
  to { transform: rotate(360deg); }
}

.confirm-fade-enter-active,
.confirm-fade-leave-active {
  transition: background-color 0.15s ease;
}
.confirm-fade-enter-from,
.confirm-fade-leave-to {
  background-color: rgba(15, 15, 25, 0);
}
.confirm-fade-enter-active .confirm-panel,
.confirm-fade-leave-active .confirm-panel {
  transition: transform 0.15s ease;
}
.confirm-fade-enter-from .confirm-panel,
.confirm-fade-leave-to .confirm-panel {
  transform: translateY(8px);
}
</style>
