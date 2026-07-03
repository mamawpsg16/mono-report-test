import { createRouter, createWebHistory } from 'vue-router'

const LoginView = () => import('@/views/authentication/LoginView.vue')
const AppLayout = () => import('@/layouts/AppLayout.vue')
const CustomersIndex = () => import('@/views/customers/Index.vue')
const NotFound = () => import('@/views/authentication/NotFound.vue')
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { guest: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: CustomersIndex,
          meta: { title: 'Customers' },
        },
        { 
          path: '/:pathMatch(.*)*',
          name: 'not-found',
          component: NotFound 
        }
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const { useAuth } = await import('../composables/useAuth')
  const { isAuthenticated, checked, fetchUser } = useAuth()

  if (!checked.value) {
    await fetchUser()
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return { name: 'login' }
  }

  if (to.meta.guest && isAuthenticated.value) {
    return { name: 'home' }
  }
})

export default router
