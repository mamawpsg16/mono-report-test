import { createRouter, createWebHistory } from 'vue-router'
// The authed shell (layout + customers view) loads on every session, so eager
// import it — code-splitting it only adds a post-auth "pop" on refresh. Login
// and 404 stay lazy since they're rarely on the hot path.
import AppLayout from '@/layouts/AppLayout.vue'
import DashboardIndex from '@/views/dashboard/Index.vue'
import CustomersIndex from '@/views/customers/Index.vue'

const LoginView = () => import('@/views/authentication/LoginView.vue')
const ChangePassword = () => import('@/views/authentication/ChangePassword.vue')
const SetPassword = () => import('@/views/authentication/SetPassword.vue')
const NotFound = () => import('@/views/authentication/NotFound.vue')
// Secondary screens off the hot path, so lazy-load them.
const ProspectsIndex = () => import('@/views/prospects/Index.vue')
const VisitsIndex = () => import('@/views/visits/Index.vue')
const WeeklyVisitPlanIndex = () => import('@/views/visit-plan/Index.vue')
const CoverageReportIndex = () => import('@/views/reports/CoverageReport.vue')
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
      // stands alone (no AppLayout shell): a must-reset user shouldn't see the
      // nav until they've set a real password
      path: '/change-password',
      name: 'change-password',
      component: ChangePassword,
      meta: { requiresAuth: true },
    },
    {
      // public: invited users land here from the emailed link (token in query)
      path: '/set-password',
      name: 'set-password',
      component: SetPassword,
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
          meta: { title: 'Dashboard', subtitle: 'Overview of your workspace.' },
        },
        {
          path: 'customers',
          name: 'customers',
          component: CustomersIndex,
          meta: { title: 'Customers', subtitle: 'Browse, search, and import your customer records.', permission: 'customers.view' },
        },
        {
          path: 'prospects',
          name: 'prospects',
          component: ProspectsIndex,
          meta: { title: 'Prospects', subtitle: 'Sales leads your reps are working in the field.', permission: 'prospects.view' },
        },
        {
          path: 'visits',
          name: 'visits',
          component: VisitsIndex,
          meta: { title: 'Visits', subtitle: 'Customer visit history from the field.', permission: 'visits.view' },
        },
        {
          path: 'weekly-visit-plan',
          name: 'weekly-visit-plan',
          component: WeeklyVisitPlanIndex,
          meta: { title: 'Weekly Visit Plan', subtitle: 'Plan which customers you\'ll visit each day.', permission: 'visits.view' },
        },
        {
          path: 'coverage-report',
          name: 'coverage-report',
          component: CoverageReportIndex,
          meta: { title: 'Coverage Report', subtitle: 'Planned visits vs. what actually happened.', permission: 'visits.view' },
        },
        {
          path: 'users',
          name: 'users',
          component: UsersView,
          meta: { title: 'Users', subtitle: 'Manage accounts, roles, and access.', permission: 'roles.manage' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: RolesView,
          meta: { title: 'Roles', subtitle: 'Manage roles and the permissions each one grants.', permission: 'roles.manage' },
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
  const { isAuthenticated, checked, fetchUser, can, mustChangePassword } = useAuth()

  if (!checked.value) {
    await fetchUser()
  }

  if (to.meta.requiresAuth && !isAuthenticated.value) {
    return { name: 'login' }
  }

  // A user still on a temporary password can go ONLY to the reset screen — this
  // outranks guest/permission handling, so they can't slip into the app first.
  if (isAuthenticated.value && mustChangePassword.value) {
    return to.name === 'change-password' ? undefined : { name: 'change-password' }
  }

  if (to.meta.guest && isAuthenticated.value) {
    return { name: 'home' }
  }

  // authenticated and no longer required to reset: don't linger on that screen
  if (to.name === 'change-password') {
    return { name: 'home' }
  }

  if (to.meta.permission && !can(to.meta.permission)) {
    return { name: 'home' }
  }
})

export default router
