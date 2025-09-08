<template>
  <div class="auth-wrap">
    <div class="auth-card" role="form" aria-labelledby="login-title">
      <h1 id="login-title">Sign in</h1>
      <p class="sub">Welcome back. Please enter your credentials.</p>

      <form @submit.prevent="submit">
        <label for="email">Email</label>
        <input
          id="email"
          v-model.trim="email"
          type="email"
          required
          :disabled="loading"
          placeholder="you@company.com"
          autocomplete="email"
        />

        <label for="password">Password</label>
        <div style="position:relative">
          <input
            :type="show ? 'text' : 'password'"
            id="password"
            v-model="password"
            required
            :disabled="loading"
            placeholder="••••••••"
            autocomplete="current-password"
            style="padding-right:88px"
          />
          <button
            type="button"
            class="btn-ghost"
            @click="show = !show"
            :aria-pressed="show.toString()"
            style="position:absolute; right:6px; top:6px; height:32px"
          >
            {{ show ? 'Hide' : 'Show' }}
          </button>
        </div>

        <div style="display:flex; align-items:center; gap:8px; margin-top:10px">
          <input id="remember" type="checkbox" v-model="remember" :disabled="loading" />
          <label for="remember" style="margin:0">Remember me</label>
        </div>

        <button class="btn-primary" :disabled="loading">
          <span v-if="!loading">Sign in</span>
          <span v-else>Signing in…</span>
        </button>

        <p v-if="error" class="error" role="alert">{{ error }}</p>

        <p class="muted small" style="margin-top:12px">
          Don't have an account?
          <router-link to="/register">Create one</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../services/api'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const remember = ref(true)
const show = ref(false)
const loading = ref(false)
const error = ref('')

async function submit () {
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/login', { email: email.value, password: password.value })
    const storage = remember.value ? localStorage : sessionStorage
    storage.setItem('token', data.token)
    if (!remember.value) localStorage.removeItem('token')
    router.replace(route.query.redirect || '/')
  } catch (e) {
    error.value = e?.response?.data?.message || 'Invalid credentials'
  } finally {
    loading.value = false
  }
}
</script>
