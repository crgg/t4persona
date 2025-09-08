<template>
  <div class="app-shell">
    <header class="app-header" v-if="isAuthed">
      <div class="brand"><span class="dot"></span><strong>T4PERSON</strong></div>
      <nav class="actions">
        <router-link class="btn-ghost" to="/">Panel</router-link>
        <button class="btn-ghost" @click="logout">Cerrar sesión</button>
      </nav>
    </header>
    <main><router-view /></main>
  </div>
</template>

<script setup>
import api from './services/api'
import { computed } from 'vue'
const isAuthed = computed(() => !!localStorage.getItem('token'))
async function logout(){ try{ await api.post('/logout') }catch{} localStorage.removeItem('token'); location.href='/login' }
</script>
