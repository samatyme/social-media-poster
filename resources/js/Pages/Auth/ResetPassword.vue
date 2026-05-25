<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-2xl mb-4">
          <span class="text-white font-bold text-xl">SP</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Social Media Poster</h1>
        <p class="text-gray-500 mt-1">Set your password to get started</p>
      </div>

      <div class="card">
        <div class="card-body">
          <div v-if="success" class="text-center space-y-4">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full">
              <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <p class="text-gray-700 font-medium">Password set successfully!</p>
            <p class="text-gray-500 text-sm">Redirecting to dashboard...</p>
          </div>

          <form v-else @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="label">Email address</label>
              <input v-model="form.email" type="email" class="input" readonly />
            </div>
            <div>
              <label class="label">New Password</label>
              <input v-model="form.password" type="password" class="input" placeholder="Minimum 8 characters" required minlength="8" />
            </div>
            <div>
              <label class="label">Confirm Password</label>
              <input v-model="form.password_confirmation" type="password" class="input" placeholder="Repeat your password" required />
            </div>

            <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
              {{ error }}
            </div>

            <button type="submit" class="btn-primary w-full btn-lg" :disabled="loading">
              <LoadingSpinner v-if="loading" size="sm" />
              {{ loading ? 'Setting password...' : 'Set Password & Login' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'

const form    = ref({ email: '', password: '', password_confirmation: '', token: '' })
const loading = ref(false)
const error   = ref(null)
const success = ref(false)

onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  form.value.token = params.get('token') || ''
  form.value.email = params.get('email') || ''
})

async function submit() {
  error.value   = null
  loading.value = true

  try {
    const res = await fetch('/api/auth/reset-password', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body:    JSON.stringify(form.value),
    })

    const data = await res.json()

    if (!res.ok) {
      error.value = data.message || data.errors?.password?.[0] || 'Failed to set password.'
      return
    }

    // Store token and redirect
    localStorage.setItem('auth_token', data.token)
    localStorage.setItem('auth_user', JSON.stringify(data.user))
    success.value = true
    setTimeout(() => window.location.href = '/dashboard', 1500)

  } catch (e) {
    error.value = 'An unexpected error occurred. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>
