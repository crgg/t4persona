<template>
  <div class="page">
    <section class="hero">
      <h2>Dashboard</h2>
      <p class="muted">Calm, clean interface designed for clarity.</p>
    </section>

    <section class="panel" style="margin-bottom:16px">
      <h3 style="margin-top:0">My profile</h3>
      <div v-if="me" style="display:grid; grid-template-columns: 1fr 1fr; gap:12px">
        <div class="kv"><span>Full name</span><strong>{{ me.name }}</strong></div>
        <div class="kv"><span>Email</span><strong>{{ me.email }}</strong></div>
        <div class="kv" v-if="me.rol"><span>Role</span><strong>{{ me.rol }}</strong></div>
        <div class="kv" v-if="me.date_register"><span>Registered</span><strong>{{ formatDate(me.date_register) }}</strong></div>
        <div class="kv" v-if="me.last_login"><span>Last login</span><strong>{{ formatDate(me.last_login) }}</strong></div>
      </div>
      <div v-else class="muted">Loading profile…</div>
    </section>

    <section class="panel">
      <h3 style="margin-top:0">Quick actions</h3>
      <div style="display:flex; gap:12px; flex-wrap:wrap">
        <button class="btn-ghost" @click="refresh" :disabled="loading">Refresh</button>
        <button class="btn-ghost" @click="signOut" :disabled="loading">Sign out</button>
      </div>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'

const me = ref(null)
const loading = ref(false)

onMounted(fetchMe)

async function fetchMe () {
  loading.value = true
  try {
    const { data } = await api.get('/me')
    me.value = data
  } finally {
    loading.value = false
  }
}

function refresh () {
  fetchMe()
}

async function signOut () {
  try { await api.post('/logout') } catch {}
  localStorage.removeItem('token')
  sessionStorage.removeItem('token')
  location.href = '/login'
}

function formatDate (iso) {
  try {
    const d = new Date(iso)
    return d.toLocaleString()
  } catch {
    return iso
  }
}
</script>
