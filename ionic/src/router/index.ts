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
        redirect: '/reports'
      },
      {
        path: 'reports',
        component: () => import('@/views/reports/Reports.vue')
      },
      {
        path: 'account',
        component: () => import('@/views/Tab2Page.vue')
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
  }
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

export default router
