import { reactive, readonly } from 'vue'

// A single, app-wide confirm dialog. `confirm()` opens it and returns a promise
// that resolves true (accepted) or false (dismissed). Because there's exactly
// one dialog, the state lives at module scope, not per-component — every caller
// shares it, and <ConfirmModal> (mounted once in App.vue) renders it.

const state = reactive({
  open: false,
  title: '',
  text: '',
  html: '', // trusted markup only — rendered with v-html (see confirm() note)
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  danger: false,
  alert: false, // acknowledge-only: hides Cancel, backdrop won't dismiss
  loading: false,
  error: '',
})

let resolver = null
let onConfirm = null

/**
 * Open the dialog. Options:
 *   title, text, confirmText, cancelText, danger (bool)
 *   alert: acknowledge-only dialog (bool). Hides Cancel and disables
 *     backdrop-dismiss, so the only way out is the confirm button — for
 *     one-way notices like an expired session. Resolves true on acknowledge.
 *   html: optional rich body (like SweetAlert2's `html`). Rendered with v-html,
 *     so pass ONLY trusted/hard-coded markup — never an unescaped user value
 *     (role name, email, …) or it's an XSS hole. Use `text` for dynamic values.
 *   onConfirm: optional async fn run while the dialog shows a spinner. If it
 *     throws, the dialog stays open and shows the error; the promise only
 *     resolves true once it succeeds. Without it, accepting resolves immediately.
 * @returns {Promise<boolean>}
 */
export function confirm(options = {}) {
  state.open = true
  state.loading = false
  state.error = ''
  state.title = options.title ?? 'Are you sure?'
  state.text = options.text ?? ''
  state.html = options.html ?? ''
  state.confirmText = options.confirmText ?? 'Confirm'
  state.cancelText = options.cancelText ?? 'Cancel'
  state.danger = options.danger ?? false
  state.alert = options.alert ?? false
  onConfirm = options.onConfirm ?? null

  return new Promise((resolve) => {
    resolver = resolve
  })
}

// --- handlers wired to the dialog's buttons (used by ConfirmModal only) ---

async function accept() {
  if (onConfirm) {
    state.loading = true
    state.error = ''
    try {
      await onConfirm()
    } catch (e) {
      // keep the dialog open so the user can read the reason and retry/cancel
      state.error = e?.response?.data?.message || e?.message || 'Something went wrong.'
      state.loading = false
      return
    }
  }
  settle(true)
}

function cancel() {
  if (state.loading) return // don't bail out from under an in-flight action
  settle(false)
}

function settle(result) {
  state.open = false
  state.loading = false
  onConfirm = null
  resolver?.(result)
  resolver = null
}

// ConfirmModal needs read access to state + the two handlers; callers only need
// confirm(). Expose state read-only so nothing mutates it out of band.
export function useConfirmDialog() {
  return { state: readonly(state), accept, cancel }
}
