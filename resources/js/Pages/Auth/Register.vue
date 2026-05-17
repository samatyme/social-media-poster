<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-blue-600 rounded-2xl mb-4">
          <span class="text-white font-bold text-xl">SP</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Create your organization</h1>
        <p class="text-gray-500 mt-1">Start publishing to social media</p>
      </div>

      <div class="card">
        <div class="card-body">
          <form @submit.prevent="submit" class="space-y-4">
            <div>
              <label class="label">Organization name</label>
              <input v-model="form.organization_name" type="text" class="input" placeholder="Acme Inc." required />
            </div>
            <div>
              <label class="label">Your name</label>
              <input v-model="form.name" type="text" class="input" placeholder="John Doe" required />
            </div>
            <div>
              <label class="label">Email address</label>
              <input v-model="form.email" type="email" class="input" placeholder="you@example.com" required />
            </div>
            <div>
              <label class="label">Password</label>
              <input v-model="form.password" type="password" class="input" placeholder="Min. 8 characters" required />
            </div>
            <div>
              <label class="label">Confirm password</label>
              <input v-model="form.password_confirmation" type="password" class="input" placeholder="••••••••" required />
            </div>

            <div v-if="errors" class="rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 space-y-1">
              <p v-for="(msgs, field) in errors" :key="field">
                <span v-for="msg in msgs" :key="msg">{{ msg }}</span>
              </p>
            </div>

            <button type="submit" class="btn-primary w-full btn-lg" :disabled="loading">
              {{ loading ? 'Creating...' : 'Create account' }}
            </button>
          </form>
        </div>
      </div>

      <p class="text-center text-sm text-gray-500 mt-4">
        Already have an account?
        <Link href="/login" class="text-blue-600 hover:underline font-medium">Sign in</Link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const form    = ref({ organization_name: '', name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const errors  = ref(null)

async function submit() {
  loading.value = true
  errors.value  = null
  try {
    const res = await axios.post('/api/auth/register', form.value)
    localStorage.setItem('auth_token', res.data.token)
    window.location.href = '/dashboard'
  } catch (err) {
    errors.value = err.response?.data?.errors ?? { general: [err.response?.data?.message || 'Registration failed.'] }
  } finally {
    loading.value = false
  }
}
</script>
