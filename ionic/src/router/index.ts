import { createRouter, createWebHistory } from '@ionic/vue-router';
import { RouteRecordRaw } from 'vue-router';
import TabsPage from '../views/TabsPage.vue';

const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    component: TabsPage,
    children: [
      {
        path: '',
        redirect: '/login'
      },
      {
        path: 'my-reports',
        component: () => import('@/views/reports/Reports.vue')
      },
      {
        path: 'all-reports',
        component: () => import('@/views/reports/AllReports.vue')
      },
      {
        path: 'account',
        component: () => import('@/views/account/Account.vue')
      }
    ]
  },
  {
    path: '/login',
    component: () => import('@/views/login/Login.vue')
  },
  {
    path: '/reports/:id',
    component: () => import('@/views/reports/Report.vue')
  },
  {
    path: '/users',
    component: () => import('@/views/users/Users.vue')
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

export default router
