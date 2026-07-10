import { createRouter, createWebHistory } from 'vue-router'
// The authed shell (layout + customers view) loads on every session, so eager
// import it — code-splitting it only adds a post-auth "pop" on refresh. Login
// and 404 stay lazy since they're rarely on the hot path.
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardIndex from '@/views/dashboard/Index.vue'
import CustomersIndex from '@/views/customers/Index.vue'

const LoginView = () => import('@/views/authentication/LoginView.vue')
const NotFound = () => import('@/views/authentication/NotFound.vue')
// Admin/Maintenance screens are off the hot path, so lazy-load them.
const UsersView = () => import('@/views/admin/UsersView.vue')
const RolesView = () => import('@/views/admin/RolesView.vue')
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
          component: DashboardIndex,
          meta: { title: 'Dashboard' },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomersIndex,
          meta: { title: 'Customers', permission: 'customers.view' },
        },
        {
          path: 'users',
          name: 'users',
          component: UsersView,
          meta: { title: 'Users', permission: 'roles.manage' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: RolesView,
          meta: { title: 'Roles', permission: 'roles.manage' },
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
  const { isAuthenticated, checked, fetchUser, can } = useAuth()

  if (!checked.value) {
    await fetchUser()
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return { name: 'login' }
  }

  if (to.meta.guest && isAuthenticated.value) {
    return { name: 'home' }
  }

  if (to.meta.permission && !can(to.meta.permission)) {
    return { name: 'home' }
  }
})

export default router
