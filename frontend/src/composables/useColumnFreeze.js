import { ref, onMounted, onUnmounted, nextTick } from 'vue'

// Pin ("freeze") the lead columns only when the table actually overflows its
// visible area. A table that fits shows no horizontal scrollbar and freezes
// nothing; one that overflows keeps its lead columns pinned while you scroll.
//
// We measure vue3-easy-data-table's own scroll container (its `__main` element
// already has overflow:auto) and compare scrollWidth vs clientWidth, rather
// than guessing from summed column widths — so the decision is correct at any
// viewport and survives column/width changes.
export function useColumnFreeze(scrollSelector = '.vue3-easy-data-table__main') {
  const container = ref(null)   // put this ref on a wrapper around the table
  const frozen = ref(false)
  let observer = null

  async function measure(depth = 0) {
    const scroller = container.value?.querySelector(scrollSelector)
    if (!scroller) return

    // +2px tolerance so sub-pixel/border rounding doesn't freeze for nothing
    const overflowing = scroller.scrollWidth > scroller.clientWidth + 2
    if (overflowing === frozen.value) return

    frozen.value = overflowing
    // toggling `frozen` re-lays-out the table (fixed columns change its width),
    // so re-check once to settle on a stable answer. Depth guard prevents any
    // chance of a flap loop.
    if (depth < 2) {
      await nextTick()
      measure(depth + 1)
    }
  }

  onMounted(() => {
    observer = new ResizeObserver(() => measure())
    if (container.value) observer.observe(container.value)
    measure()
  })
  onUnmounted(() => observer?.disconnect())

  // `measure` is exposed so callers can re-check after async data loads (the
  // container's own size doesn't change when rows arrive, so ResizeObserver
  // won't fire for that).
  return { container, frozen, measure }
}
