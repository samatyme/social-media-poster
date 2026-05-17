<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto space-y-6">
      <!-- Profile settings -->
      <div class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Profile Settings</h2>
        </div>
        <div class="card-body space-y-4">
          <div>
            <label class="label">Full Name</label>
            <input v-model="profileForm.name" type="text" class="input" />
          </div>
          <div>
            <label class="label">Email</label>
            <input :value="authUser?.email" type="email" class="input opacity-50" disabled />
          </div>
          <div>
            <label class="label">Timezone</label>
            <select v-model="profileForm.timezone" class="input">
              <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
            </select>
          </div>
          <button @click="saveProfile" :disabled="saving" class="btn-primary btn-sm">Save Profile</button>
        </div>
      </div>

      <!-- Organization settings -->
      <div v-if="canManage" class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Organization</h2>
        </div>
        <div class="card-body space-y-4">
          <div>
            <label class="label">Organization Name</label>
            <input v-model="orgForm.name" type="text" class="input" />
          </div>
          <div>
            <label class="label">Default Timezone</label>
            <select v-model="orgForm.timezone" class="input">
              <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
            </select>
          </div>
          <button @click="saveOrg" :disabled="saving" class="btn-primary btn-sm">Save Organization</button>
        </div>
      </div>

      <!-- Feature flags -->
      <div v-if="canManage" class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Features</h2>
          <p class="text-sm text-gray-500 mt-0.5">Toggle platform features for your organization.</p>
        </div>
        <div class="card-body space-y-4">
          <label class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-900">Require Approval</p>
              <p class="text-xs text-gray-500">Posts must be approved before publishing.</p>
            </div>
            <input
              v-model="flags.require_approval"
              type="checkbox"
              class="w-4 h-4 text-blue-600 rounded"
              @change="saveFlags"
            />
          </label>
        </div>
      </div>

      <!-- Plan info -->
      <div class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Plan & Limits</h2>
        </div>
        <div class="card-body">
          <div class="flex items-center gap-3 mb-4">
            <span class="badge bg-blue-100 text-blue-700 text-sm px-3 py-1">{{ settings?.organization?.plan ?? 'free' }}</span>
          </div>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-gray-500">Social Accounts</p>
              <p class="font-semibold text-gray-900">— / {{ settings?.organization?.max_social_accounts }}</p>
            </div>
            <div>
              <p class="text-gray-500">Scheduled Posts</p>
              <p class="font-semibold text-gray-900">— / {{ settings?.organization?.max_scheduled_posts }}</p>
            </div>
            <div>
              <p class="text-gray-500">Team Members</p>
              <p class="font-semibold text-gray-900">— / {{ settings?.organization?.max_team_members }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const { get, put } = useApi()
const toast = useToast()
const page  = usePage()

const settings  = ref(null)
const saving    = ref(false)
const authUser  = computed(() => page.props.auth.user)
const canManage = computed(() => ['owner', 'admin'].includes(authUser.value?.role))

const profileForm = ref({ name: '', timezone: 'UTC' })
const orgForm     = ref({ name: '', timezone: 'UTC' })
const flags       = ref({ require_approval: false })

const timezones = [
  { value: 'UTC',                  label: 'UTC' },
  { value: 'America/New_York',     label: 'Eastern Time' },
  { value: 'America/Chicago',      label: 'Central Time' },
  { value: 'America/Los_Angeles',  label: 'Pacific Time' },
  { value: 'Europe/London',        label: 'London' },
  { value: 'Europe/Paris',         label: 'Central European' },
  { value: 'Asia/Dubai',           label: 'Dubai' },
  { value: 'Asia/Singapore',       label: 'Singapore' },
  { value: 'Asia/Tokyo',           label: 'Tokyo' },
  { value: 'Australia/Sydney',     label: 'Sydney' },
  { value: 'Africa/Nairobi',       label: 'Nairobi' },
]

onMounted(async () => {
  settings.value = await get('settings')
  profileForm.value.name     = settings.value.user.name
  profileForm.value.timezone = settings.value.user.timezone
  orgForm.value.name         = settings.value.organization.name
  orgForm.value.timezone     = settings.value.organization.timezone
  flags.value.require_approval = settings.value.organization.feature_flags?.require_approval ?? false
})

async function saveProfile() {
  saving.value = true
  try {
    await put('settings/profile', profileForm.value)
    toast.success('Profile saved.')
  } catch { toast.error('Failed to save profile.') }
  finally { saving.value = false }
}

async function saveOrg() {
  saving.value = true
  try {
    await put('settings/organization', orgForm.value)
    toast.success('Organization settings saved.')
  } catch { toast.error('Failed to save settings.') }
  finally { saving.value = false }
}

async function saveFlags() {
  await put('settings/feature-flags', { feature_flags: flags.value })
  toast.success('Feature flags updated.')
}
</script>
