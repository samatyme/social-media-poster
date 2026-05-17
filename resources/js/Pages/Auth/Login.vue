<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-2xl mb-4">
          <span class="text-white font-bold text-xl">SP</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Social Media Poster</h1>
        <p class="text-gray-500 mt-1">Sign in to your account</p>
      </div>

      <div class="card">
        <div class="card-body">
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="label">Email address</label>
              <input v-model="form.email" type="email" class="input" placeholder="you@example.com" required />
            </div>
            <div>
              <label class="label">Password</label>
              <input v-model="form.password" type="password" class="input" placeholder="••••••••" required />
            </div>

            <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {{ error }}
            </div>

            <button type="submit" class="btn-primary w-full btn-lg" :disabled="loading">
              <LoadingSpinner v-if="loading" size="sm" />
              {{ loading ? 'Signing in...' : 'Sign in' }}
            </button>
          </form>
        </div>
      </div>

      <p class="text-center text-sm text-gray-500 mt-4">
        Don't have an account?
        <Link href="/register" class="text-blue-600 hover:underline font-medium">Create organization</Link>
      </p>

      <div class="mt-6 card">
        <div class="card-body py-3">
          <p class="text-xs text-gray-500 text-center font-medium mb-2">Demo credentials</p>
          <div class="space-y-1 text-xs text-gray-600">
            <p><strong>Admin:</strong> admin@demo.com / password</p>
            <p><strong>Editor:</strong> editor@demo.com / password</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref(null)

async function submit() {
  loading.value = true
  error.value   = null
  try {
    const res = await axios.post('/api/auth/login', form.value)
    localStorage.setItem('auth_token', res.data.token)
    axios.defaults.headers.common['Authorization'] = 'Bearer ' + res.data.token
    window.location.href = '/dashboard'
  } catch (err) {
    error.value = err.response?.data?.message || 'Invalid credentials.'
  } finally {
    loading.value = false
  }
}
</script>
