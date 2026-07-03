<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="modelValue" class="modal-backdrop" @click.self="close">
        <div class="modal-panel" :style="{ maxWidth: resolvedMaxWidth }">
          <div v-if="title" class="modal-header">
            <p class="modal-title">{{ title }}</p>
            <button class="modal-close" @click="close">&times;</button>
          </div>
          <button v-else class="modal-close modal-close-floating" @click="close">&times;</button>
          <div class="modal-body">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue'

// named presets for the common cases; pass any other string (e.g. "700px",
// "50vw") through as a literal CSS value
const SIZE_PRESETS = {
  sm: '420px',
  md: '640px',
  lg: '860px',
  xl: '1100px',
  '2xl': '1280px',
}

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: '' },
  maxWidth: { type: String, default: 'lg' },
})

const resolvedMaxWidth = computed(() => SIZE_PRESETS[props.maxWidth] ?? props.maxWidth)

const emit = defineEmits(['update:modelValue'])

function close() {
  emit('update:modelValue', false)
}

function onKeydown(e) {
  if (e.key === 'Escape' && props.modelValue) close()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})

// background page shouldn't scroll while a modal sits over it -- otherwise
// its own scrollbar stays visible/active behind the backdrop
watch(() => props.modelValue, (isOpen) => {
  document.body.style.overflow = isOpen ? 'hidden' : ''
}, { immediate: true })
</script>

<style scoped>
.modal-backdrop {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(15, 15, 25, 0.45);
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}

.modal-panel {
  position: relative;
  width: 100%;
  max-height: calc(100vh - 48px);
  background: var(--color-surface);
  border-radius: 16px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05), 0 6px 24px rgba(var(--color-accent-rgb),0.05);
  display: flex; flex-direction: column;
  overflow: hidden;
}

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; border-bottom: 1px solid var(--color-border-subtle); flex-shrink: 0;
}
.modal-title { font-size: 15px; font-weight: 700; color: var(--color-ink); }

.modal-close {
  border: none; background: none; cursor: pointer;
  font-size: 20px; line-height: 1; color: var(--color-text-muted); padding: 4px 8px;
  border-radius: 6px; font-family: inherit;
}
.modal-close:hover { background: var(--color-border-subtle); color: var(--color-text); }
.modal-close-floating {
  position: absolute; top: 14px; right: 14px; z-index: 1;
}

.modal-body { overflow-y: auto; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: background-color 0.15s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { background-color: rgba(15, 15, 25, 0); }
.modal-fade-enter-active .modal-panel, .modal-fade-leave-active .modal-panel { transition: transform 0.15s ease; }
.modal-fade-enter-from .modal-panel, .modal-fade-leave-to .modal-panel { transform: translateY(8px); }
</style>
