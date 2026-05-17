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

      <!-- Platform API Credentials -->
      <div v-if="canManage" class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Platform API Credentials</h2>
          <p class="text-sm text-gray-500 mt-0.5">
            Enter your own developer app credentials for each platform. These are stored encrypted and are specific to your organization.
          </p>
        </div>
        <div class="card-body space-y-4">
          <!-- Platform accordion items -->
          <div
            v-for="(info, platform) in platformCredentials"
            :key="platform"
            class="border border-gray-200 rounded-lg overflow-hidden"
          >
            <!-- Header -->
            <button
              type="button"
              class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
              @click="togglePlatform(platform)"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                  :class="info.configured ? 'bg-green-400' : 'bg-gray-300'"
                />
                <span class="font-medium text-gray-900 capitalize">{{ platformLabel(platform) }}</span>
                <span v-if="info.configured" class="text-xs text-gray-400">
                  Updated {{ formatDate(info.updated_at) }}
                </span>
                <span v-else class="text-xs text-amber-600 font-medium">Not configured</span>
              </div>
              <ChevronDown
                class="w-4 h-4 text-gray-400 transition-transform"
                :class="openPlatform === platform ? 'rotate-180' : ''"
              />
            </button>

            <!-- Fields -->
            <div v-if="openPlatform === platform" class="px-4 pb-4 pt-1 bg-gray-50 border-t border-gray-100 space-y-3">
              <div v-for="field in info.fields" :key="field">
                <label class="label capitalize">{{ fieldLabel(field) }}</label>
                <input
                  v-model="credForms[platform][field]"
                  :type="isSecret(field) ? 'password' : 'text'"
                  :placeholder="info.credentials?.[field] ?? ''"
                  class="input font-mono text-sm"
                  autocomplete="off"
                />
              </div>
              <div class="flex items-center gap-2 pt-1">
                <button
                  @click="saveCredentials(platform)"
                  :disabled="credSaving[platform]"
                  class="btn-primary btn-sm"
                >
                  {{ credSaving[platform] ? 'Saving…' : 'Save' }}
                </button>
                <button
                  v-if="info.configured"
                  @click="removeCredentials(platform)"
                  class="btn-sm text-red-600 hover:text-red-700 hover:bg-red-50 border border-red-200 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                >
                  Remove
                </button>
              </div>
            </div>
          </div>
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
import { ref, computed, reactive, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ChevronDown } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const { get, put, delete: del } = useApi()
const toast = useToast()
const page  = usePage()

const settings           = ref(null)
const saving             = ref(false)
const authUser           = computed(() => settings.value?.user ?? page.props.auth.user)
const canManage          = computed(() => ['owner', 'admin'].includes(authUser.value?.role))

const profileForm = ref({ name: '', timezone: 'UTC' })
const orgForm     = ref({ name: '', timezone: 'UTC' })
const flags       = ref({ require_approval: false })

// Platform credentials state
const platformCredentials = ref({})
const openPlatform        = ref(null)
const credForms           = reactive({})
const credSaving          = reactive({})

const PLATFORM_LABELS = {
  facebook:  'Facebook',
  instagram: 'Instagram',
  x:         'X (Twitter)',
  linkedin:  'LinkedIn',
  tiktok:    'TikTok',
}

const FIELD_LABELS = {
  app_id:       'App ID',
  app_secret:   'App Secret',
  api_key:      'API Key',
  api_secret:   'API Secret',
  bearer_token: 'Bearer Token',
  client_id:    'Client ID',
  client_secret:'Client Secret',
  client_key:   'Client Key',
}

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

function platformLabel(p) { return PLATFORM_LABELS[p] ?? p }
function fieldLabel(f)    { return FIELD_LABELS[f] ?? f.replace(/_/g, ' ') }
function isSecret(f)      { return f.includes('secret') || f.includes('token') }
function formatDate(d)    { return d ? new Date(d).toLocaleDateString() : '' }

function togglePlatform(platform) {
  openPlatform.value = openPlatform.value === platform ? null : platform
}

function initCredForms(data) {
  for (const [platform, info] of Object.entries(data)) {
    credForms[platform] = {}
    credSaving[platform] = false
    for (const field of info.fields) {
      credForms[platform][field] = ''
    }
  }
}

onMounted(async () => {
  settings.value = await get('settings')
  profileForm.value.name     = settings.value.user.name
  profileForm.value.timezone = settings.value.user.timezone
  orgForm.value.name         = settings.value.organization.name
  orgForm.value.timezone     = settings.value.organization.timezone
  flags.value.require_approval = settings.value.organization.feature_flags?.require_approval ?? false

  if (canManage.value) {
    try {
      const creds = await get('platform-credentials')
      platformCredentials.value = creds
      initCredForms(creds)
    } catch {}
  }
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

async function saveCredentials(platform) {
  credSaving[platform] = true
  try {
    const payload = { ...credForms[platform] }
    // Drop empty fields (user may leave unchanged fields blank to keep existing)
    const filtered = Object.fromEntries(Object.entries(payload).filter(([, v]) => v.trim() !== ''))

    if (Object.keys(filtered).length === 0) {
      toast.error('Enter at least one credential field.')
      return
    }

    const result = await put(`platform-credentials/${platform}`, filtered)
    platformCredentials.value[platform].configured = true
    platformCredentials.value[platform].updated_at = result.updated_at
    platformCredentials.value[platform].credentials = result.credentials
    // Clear form
    for (const field of platformCredentials.value[platform].fields) {
      credForms[platform][field] = ''
    }
    toast.success(`${platformLabel(platform)} credentials saved.`)
  } catch (e) {
    toast.error(e?.response?.data?.message ?? 'Failed to save credentials.')
  } finally {
    credSaving[platform] = false
  }
}

async function removeCredentials(platform) {
  if (!confirm(`Remove ${platformLabel(platform)} credentials? Connected accounts using this app will stop working.`)) return
  try {
    await del(`platform-credentials/${platform}`)
    platformCredentials.value[platform].configured = false
    platformCredentials.value[platform].credentials = null
    platformCredentials.value[platform].updated_at = null
    toast.success(`${platformLabel(platform)} credentials removed.`)
  } catch {
    toast.error('Failed to remove credentials.')
  }
}
</script>
