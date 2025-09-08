<template>
  <div class="auth-wrap">
    <div class="auth-card">
      <h1>Crear cuenta</h1>
      <p class="sub">Completa los datos para registrarte.</p>

      <form @submit.prevent="submit">
        <label for="name">Nombre</label>
        <input id="name" v-model="name" type="text" required placeholder="Tu nombre" />

        <label for="email">Correo</label>
        <input id="email" v-model="email" type="email" required placeholder="tucorreo@dominio.com" />

        <label for="password">Contraseña</label>
        <input id="password" v-model="password" type="password" minlength="8" required placeholder="********" />

        <button class="btn-primary" :disabled="loading">
          <span v-if="!loading">Registrarme</span><span v-else>Creando…</span>
        </button>

        <p v-if="error" class="error">{{ error }}</p>

        <p class="muted small">
          ¿Ya tienes cuenta?
          <router-link to="/login">Inicia sesión</router-link>
        </p>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '../services/api'
import { useRouter } from 'vue-router'
const router = useRouter()
const name = ref(''), email = ref(''), password = ref(''), loading = ref(false), error = ref('')

async function submit(){
  error.value=''; loading.value=true
  try{
    const { data } = await api.post('/register', { name: name.value, email: email.value, password: password.value })
    localStorage.setItem('token', data.token)
    router.replace('/')
  }catch(e){
    error.value = e?.response?.data?.message || 'No se pudo crear la cuenta'
  }finally{ loading.value=false }
}
</script>
