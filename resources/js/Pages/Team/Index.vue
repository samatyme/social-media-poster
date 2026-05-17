<template>
  <AppLayout>
    <div class="max-w-3xl mx-auto space-y-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-gray-500">Manage who has access to your organization.</p>
        <button @click="showInviteModal = true" class="btn-primary">
          <UserPlus class="w-4 h-4" /> Invite Member
        </button>
      </div>

      <div class="card">
        <LoadingSpinner v-if="loading" class="py-8" />
        <div v-else class="divide-y divide-gray-100">
          <div v-for="member in members" :key="member.id" class="px-6 py-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
              <span class="text-blue-700 font-medium text-sm">{{ initials(member.name) }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900">{{ member.name }}</p>
              <p class="text-xs text-gray-500">{{ member.email }}</p>
            </div>
            <div v-if="isCurrentUser(member)">
              <span class="badge bg-blue-50 text-blue-700">You</span>
            </div>
            <div v-else class="flex items-center gap-2">
              <select
                :value="member.role"
                @change="updateRole(member, $event.target.value)"
                class="input text-xs py-1 w-28"
                :disabled="!canManage"
              >
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="approver">Approver</option>
                <option value="viewer">Viewer</option>
              </select>
              <button v-if="canManage" @click="removeMember(member)" class="btn-ghost btn-sm text-red-500">
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
            <span
              v-if="!isCurrentUser(member) && !canManage"
              class="badge capitalize"
              :class="roleColors[member.role]"
            >{{ member.role }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Invite modal -->
    <div v-if="showInviteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="px-6 py-4 border-b flex items-center justify-between">
          <h2 class="font-semibold text-gray-900">Invite Team Member</h2>
          <button @click="showInviteModal = false" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="label">Name</label>
            <input v-model="inviteForm.name" type="text" class="input" placeholder="Jane Doe" />
          </div>
          <div>
            <label class="label">Email</label>
            <input v-model="inviteForm.email" type="email" class="input" placeholder="jane@example.com" />
          </div>
          <div>
            <label class="label">Role</label>
            <select v-model="inviteForm.role" class="input">
              <option value="admin">Admin — Manage accounts, users, posts</option>
              <option value="editor">Editor — Create and edit posts</option>
              <option value="approver">Approver — Approve/reject posts</option>
              <option value="viewer">Viewer — Read-only access</option>
            </select>
          </div>
          <div v-if="inviteError" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
            {{ inviteError }}
          </div>
        </div>
        <div class="px-6 py-4 border-t flex gap-3 justify-end">
          <button @click="showInviteModal = false" class="btn-secondary">Cancel</button>
          <button @click="inviteMember" :disabled="inviting" class="btn-primary">
            {{ inviting ? 'Inviting…' : 'Send Invite' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { UserPlus, Trash2, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const { get, post: apiPost, put, delete: apiDelete, loading } = useApi()
const toast = useToast()
const page  = usePage()

const members         = ref([])
const showInviteModal = ref(false)
const inviting        = ref(false)
const inviteError     = ref(null)
const inviteForm      = ref({ name: '', email: '', role: 'editor' })

const canManage    = computed(() => ['owner', 'admin'].includes(page.props.auth.user?.role))
const isCurrentUser = (m) => m.id === page.props.auth.user?.id

const roleColors = {
  owner:    'bg-purple-100 text-purple-700',
  admin:    'bg-blue-100 text-blue-700',
  editor:   'bg-green-100 text-green-700',
  approver: 'bg-yellow-100 text-yellow-700',
  viewer:   'bg-gray-100 text-gray-600',
}

function initials(name) { return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2) }

onMounted(async () => { members.value = await get('team') })

async function inviteMember() {
  inviting.value = true
  inviteError.value = null
  try {
    const member = await apiPost('team/invite', inviteForm.value)
    members.value.push(member)
    toast.success('Invitation sent!')
    showInviteModal.value = false
    inviteForm.value = { name: '', email: '', role: 'editor' }
  } catch (err) {
    inviteError.value = err.response?.data?.message || 'Failed to invite member.'
  } finally {
    inviting.value = false
  }
}

async function updateRole(member, role) {
  await put(`team/${member.id}`, { role })
  member.role = role
  toast.success('Role updated.')
}

async function removeMember(member) {
  if (!confirm(`Remove ${member.name} from the team?`)) return
  await apiDelete(`team/${member.id}`)
  members.value = members.value.filter(m => m.id !== member.id)
  toast.success('Member removed.')
}
</script>
