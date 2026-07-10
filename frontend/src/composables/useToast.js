import { reactive } from 'vue'

// App-wide toast stack. Module-scoped so any component can push a toast and the
// single <ToastHost> (mounted in App.vue) renders them all.

const toasts = reactive([])
let seq = 0

function push(type, message, timeout = 3200) {
  const id = ++seq
  toasts.push({ id, type, message })
  if (timeout) setTimeout(() => dismiss(id), timeout)
  return id
}

function dismiss(id) {
  const i = toasts.findIndex((t) => t.id === id)
  if (i !== -1) toasts.splice(i, 1)
}

export function useToast() {
  return {
    toasts,
    dismiss,
    success: (message, timeout) => push('success', message, timeout),
    error: (message, timeout) => push('error', message, timeout),
    warning: (message, timeout) => push('warning', message, timeout),
    info: (message, timeout) => push('info', message, timeout),
  }
}
