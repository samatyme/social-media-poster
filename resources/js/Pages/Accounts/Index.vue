<template>
  <AppLayout>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Manage your connected social media accounts.</p>
        <button @click="showConnectModal = true" class="btn-primary">
          <Plus class="w-4 h-4" /> Connect Account
        </button>
      </div>

      <LoadingSpinner v-if="loading" class="py-16" />

      <EmptyState
        v-else-if="!accounts.length"
        title="No accounts connected"
        description="Connect your social media accounts to start publishing."
      >
        <button @click="showConnectModal = true" class="btn-primary">Connect Account</button>
      </EmptyState>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="account in accounts" :key="account.id" class="card p-5">
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <PlatformIcon :platform="account.platform" size="md" />
              <div>
                <h3 class="font-semibold text-gray-900 text-sm">{{ account.account_name }}</h3>
                <p class="text-xs text-gray-500">{{ account.account_handle }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1">
              <span :class="['w-2 h-2 rounded-full', account.is_healthy ? 'bg-green-500' : 'bg-red-400']" />
              <span class="text-xs text-gray-500">{{ account.is_healthy ? 'Active' : 'Issue' }}</span>
            </div>
          </div>

          <div class="space-y-2 mb-4">
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-500">Platform</span>
              <span class="font-medium text-gray-700 capitalize">{{ account.platform }}</span>
            </div>
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-500">Token</span>
              <span :class="['font-medium', account.is_token_expired ? 'text-red-600' : 'text-green-600']">
                {{ account.is_token_expired ? 'Expired' : 'Valid' }}
              </span>
            </div>
            <div v-if="account.last_verified_at" class="flex items-center justify-between text-xs">
              <span class="text-gray-500">Last verified</span>
              <span class="font-medium text-gray-700">{{ formatDate(account.last_verified_at) }}</span>
            </div>
          </div>

          <div class="flex gap-2">
            <button @click="verifyAccount(account)" class="btn-secondary btn-sm flex-1">
              <RefreshCw class="w-3 h-3" /> Verify
            </button>
            <button @click="disconnectAccount(account)" class="btn-ghost btn-sm text-red-600">
              <Unlink class="w-3 h-3" /> Disconnect
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Connect modal -->
    <div v-if="showConnectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">Connect Social Account</h2>
          <button @click="showConnectModal = false" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="label">Platform</label>
            <div class="grid grid-cols-5 gap-2">
              <button
                v-for="p in platforms" :key="p.value"
                @click="connectForm.platform = p.value"
                :class="['p-3 rounded-xl border-2 flex flex-col items-center gap-1 transition-colors',
                         connectForm.platform === p.value ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300']"
              >
                <PlatformIcon :platform="p.value" size="sm" />
                <span class="text-xs text-gray-600">{{ p.label }}</span>
              </button>
            </div>
          </div>
          <div>
            <label class="label">Account Name</label>
            <input v-model="connectForm.account_name" type="text" class="input" placeholder="My Facebook Page" />
          </div>
          <div>
            <label class="label">Handle / Username</label>
            <input v-model="connectForm.account_handle" type="text" class="input" placeholder="@mypage" />
          </div>
          <div class="rounded-lg bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700">
            <strong>Note:</strong> In mock mode, accounts are connected instantly. In production, this will redirect you through OAuth.
          </div>
        </div>
        <div class="px-6 py-4 border-t flex gap-3 justify-end">
          <button @click="showConnectModal = false" class="btn-secondary">Cancel</button>
          <button @click="connectAccount" :disabled="!connectForm.platform || !connectForm.account_name || connecting" class="btn-primary">
            <LoadingSpinner v-if="connecting" size="sm" />
            {{ connecting ? 'Connecting…' : 'Connect' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Plus, RefreshCw, Unlink, X } from 'lucide-vue-next'
import { format } from 'date-fns'
import AppLayout from '@/Layouts/AppLayout.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const { get, post: apiPost, delete: apiDelete, loading } = useApi()
const toast = useToast()

const accounts          = ref([])
const showConnectModal  = ref(false)
const connecting        = ref(false)
const connectForm       = ref({ platform: '', account_name: '', account_handle: '' })

const platforms = [
  { value: 'facebook',  label: 'FB' },
  { value: 'instagram', label: 'IG' },
  { value: 'x',         label: 'X' },
  { value: 'linkedin',  label: 'LI' },
  { value: 'tiktok',    label: 'TT' },
]

async function fetchAccounts() {
  accounts.value = await get('social-accounts')
}

onMounted(fetchAccounts)

async function connectAccount() {
  connecting.value = true
  try {
    await apiPost('social-accounts/connect', connectForm.value)
    toast.success('Account connected!')
    showConnectModal.value = false
    connectForm.value = { platform: '', account_name: '', account_handle: '' }
    fetchAccounts()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to connect account.')
  } finally {
    connecting.value = false
  }
}

async function verifyAccount(account) {
  await apiPost(`social-accounts/${account.id}/verify`)
  toast.success('Account verified.')
  fetchAccounts()
}

async function disconnectAccount(account) {
  if (!confirm('Disconnect this account?')) return
  await apiDelete(`social-accounts/${account.id}`)
  toast.success('Account disconnected.')
  fetchAccounts()
}

function formatDate(date) { return format(new Date(date), 'MMM d, yyyy') }
</script>
