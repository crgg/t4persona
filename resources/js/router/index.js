import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '../views/LoginPage.vue'
import RegisterPage from '../views/RegisterPage.vue'
import Dashboard from '../views/Dashboard.vue'

const routes = [
  { path: '/login', name: 'login', component: LoginPage, meta: { public: true } },
  { path: '/register', name: 'register', component: RegisterPage, meta: { public: true } },
  { path: '/', name: 'dashboard', component: Dashboard },
]

const router = createRouter({ history: createWebHistory(), routes })

router.beforeEach((to, _, next) => {
  const token = localStorage.getItem('token')
  if (to.meta.public) return next()
  if (!token) return next({ name: 'login', query: { redirect: to.fullPath } })
  next()
})

export default router
