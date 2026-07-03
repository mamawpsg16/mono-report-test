<template>
  <div class="app-select" ref="root">
    <button type="button" class="app-select-trigger" :aria-expanded="open" :aria-labelledby="labelledby" @click="toggle">
      {{ modelValue }}
      <ChevronDown :size="14" :stroke-width="2" class="app-select-caret" />
    </button>
    <!-- teleported: a plain absolutely-positioned dropdown gets hard-clipped
         by any ancestor with overflow:hidden (e.g. AppModal's panel) since
         clipping applies regardless of z-index. Render at <body> level,
         positioned from the trigger's real screen coordinates instead. -->
    <Teleport to="body">
      <div v-if="open" ref="list" class="app-select-list" role="listbox" :style="listStyle">
        <div
          v-for="option in options"
          :key="option"
          role="option"
          tabindex="0"
          :aria-selected="option === modelValue"
          :class="{ active: option === modelValue }"
          @click="select(option)"
          @keydown.enter.space.prevent="select(option)"
          @keydown.esc="open = false"
        >
          {{ option }}
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { ChevronDown } from '@lucide/vue'

defineProps({
  modelValue: { type: [String, Number], required: true },
  options: { type: Array, required: true },
  labelledby: { type: String, default: undefined },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const root = ref(null)
const list = ref(null)
const listStyle = ref({})

async function toggle() {
  if (open.value) {
    open.value = false
    return
  }
  const rect = root.value.getBoundingClientRect()
  // open below by default
  listStyle.value = {
    position: 'fixed',
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
  }
  open.value = true

  // list only exists in the DOM once `open` renders -- measure its real
  // height, then flip to open upward if it would run past the viewport
  // bottom (e.g. trigger sits near the bottom of a modal or the page).
  await nextTick()
  if (!list.value) return
  const overflowsBottom = list.value.getBoundingClientRect().bottom > window.innerHeight
  if (overflowsBottom) {
    listStyle.value = {
      position: 'fixed',
      bottom: `${window.innerHeight - rect.top + 4}px`,
      left: `${rect.left}px`,
      width: `${rect.width}px`,
    }
  }
}

function select(option) {
  emit('update:modelValue', option)
  open.value = false
}

function onClickOutside(event) {
  const inRoot = root.value && root.value.contains(event.target)
  const inList = list.value && list.value.contains(event.target)
  if (!inRoot && !inList) open.value = false
}

onMounted(() => document.addEventListener('click', onClickOutside))
onUnmounted(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.app-select { position: relative; display: inline-block; }

.app-select-trigger {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  min-width: 64px; padding: 5px 10px; border-radius: 6px; border: 1px solid var(--color-border);
  background: var(--color-surface); cursor: pointer; font-size: 12px; font-weight: 500;
  font-family: inherit; color: var(--color-text-muted);
}
.app-select-caret { color: var(--color-text-muted); }

.app-select-list {
  /* position/top/left/width come from the inline :style (computed from the
     trigger's real screen position) since this is teleported to <body>.
     z-index must clear AppModal's backdrop (500) so this still shows when
     opened from inside a modal, e.g. the upload preview's rows-per-page. */
  z-index: 600;
  display: flex; flex-direction: column; gap: 2px;
  margin: 0; padding: 4px;
  background: var(--color-surface); border: 1px solid var(--color-border); border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}
.app-select-list > div {
  padding: 6px 10px; border-radius: 6px; text-align: center;
  font-size: 12px; font-weight: 500; color: var(--color-text); cursor: pointer;
}
.app-select-list > div:hover { background: var(--color-border-subtle); }
.app-select-list > div.active { background: var(--color-accent); color: #fff; }
</style>
