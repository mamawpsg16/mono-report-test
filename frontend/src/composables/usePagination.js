import { ref, watch } from 'vue'

export function usePagination({ perPage: initialPerPage = 10 } = {}) {
  const page = ref(1)
  const perPage = ref(initialPerPage)
  const lastPage = ref(1)
  const total = ref(0)

  watch(perPage, () => { page.value = 1 })

  return { page, perPage, lastPage, total }
}
