import { ref, onMounted, onUnmounted } from 'vue'

export function useIsMobile(breakpoint = 640) {
  const isMobile = ref(false)
  let mql

  function update() {
    isMobile.value = mql.matches
  }

  onMounted(() => {
    mql = matchMedia(`(max-width: ${breakpoint}px)`)
    update()
    mql.addEventListener('change', update)
  })

  onUnmounted(() => {
    mql?.removeEventListener('change', update)
  })

  return isMobile
}
