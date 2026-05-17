<template>
  <AppLayout>
    <div class="space-y-6">
      <!-- Stats row -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <StatCard
          v-for="stat in statCards" :key="stat.key"
          :label="stat.label" :value="stats[stat.key] ?? 0"
          :icon="stat.icon" :color="stat.color"
        />
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Upcoming posts -->
        <div class="lg:col-span-2 card">
          <div class="card-header flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Upcoming Posts</h2>
            <Link href="/posts?status=scheduled" class="text-sm text-blue-600 hover:underline">View all</Link>
          </div>
          <div class="card-body p-0">
            <LoadingSpinner v-if="loading" class="py-8" />
            <EmptyState
              v-else-if="!upcomingPosts.length"
              title="No scheduled posts"
              description="Schedule your first post to see it here."
              class="py-8"
            >
              <Link href="/posts/create"><button class="btn-primary btn-sm">Create Post</button></Link>
            </EmptyState>
            <div v-else class="divide-y divide-gray-100">
              <div v-for="post in upcomingPosts" :key="post.id" class="px-6 py-4 flex items-center gap-4">
                <div class="flex -space-x-1">
                  <PlatformIcon
                    v-for="platform in getPostPlatforms(post)"
                    :key="platform"
                    :platform="platform"
                    size="sm"
                  />
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">
                    {{ post.title || post.base_content?.slice(0, 60) || 'Untitled' }}
                  </p>
                  <p class="text-xs text-gray-500">{{ formatDate(post.scheduled_at) }}</p>
                </div>
                <StatusBadge :status="post.status" />
                <Link :href="`/posts/${post.id}`" class="text-gray-400 hover:text-gray-600">
                  <ChevronRight class="w-4 h-4" />
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Connected accounts -->
        <div class="card">
          <div class="card-header flex items-center justify-between">
            <h2 class="font-semibold text-gray-900">Connected Accounts</h2>
            <Link href="/accounts" class="text-sm text-blue-600 hover:underline">Manage</Link>
          </div>
          <div class="card-body p-0">
            <EmptyState
              v-if="!connectedAccounts.length"
              title="No accounts connected"
              description="Connect your social media accounts."
              class="py-8"
            >
              <Link href="/accounts"><button class="btn-secondary btn-sm">Connect Account</button></Link>
            </EmptyState>
            <div v-else class="divide-y divide-gray-100">
              <div v-for="account in connectedAccounts" :key="account.id" class="px-6 py-3 flex items-center gap-3">
                <PlatformIcon :platform="account.platform" size="sm" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ account.account_name }}</p>
                  <p class="text-xs text-gray-500">{{ account.account_handle }}</p>
                </div>
                <span :class="['w-2 h-2 rounded-full', account.status === 'active' ? 'bg-green-500' : 'bg-red-400']" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent activity -->
      <div class="card">
        <div class="card-header">
          <h2 class="font-semibold text-gray-900">Recent Activity</h2>
        </div>
        <div class="card-body p-0">
          <div v-if="!recentActivity.length" class="px-6 py-8 text-center text-sm text-gray-500">
            No publishing activity yet.
          </div>
          <div v-else class="divide-y divide-gray-100">
            <div v-for="log in recentActivity" :key="log.id" class="px-6 py-3 flex items-center gap-4">
              <PlatformIcon :platform="log.platform" size="sm" />
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900">
                  <span class="font-medium">{{ log.social_account?.account_name }}</span>
                  — {{ log.post?.title || 'Post #' + log.post_id }}
                </p>
                <p class="text-xs text-gray-500">{{ formatDate(log.created_at) }}</p>
              </div>
              <StatusBadge :status="log.status" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronRight, FileText, Clock, CheckCircle, XCircle, AlertCircle } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatCard from '@/Components/Common/StatCard.vue'
import StatusBadge from '@/Components/Common/StatusBadge.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import { useApi } from '@/Composables/useApi'
import { format } from 'date-fns'

const { get, loading } = useApi()

const stats            = ref({})
const upcomingPosts    = ref([])
const recentActivity   = ref([])
const connectedAccounts = ref([])

const statCards = [
  { key: 'drafts',          label: 'Drafts',          icon: FileText,     color: 'gray' },
  { key: 'scheduled',       label: 'Scheduled',       icon: Clock,        color: 'blue' },
  { key: 'published',       label: 'Published',       icon: CheckCircle,  color: 'green' },
  { key: 'failed',          label: 'Failed',          icon: XCircle,      color: 'red' },
  { key: 'pending_approval', label: 'Pending',        icon: AlertCircle,  color: 'yellow' },
]

onMounted(async () => {
  const data = await get('dashboard')
  stats.value            = data.stats
  upcomingPosts.value    = data.upcoming_posts
  recentActivity.value   = data.recent_activity
  connectedAccounts.value = data.connected_accounts
})

function getPostPlatforms(post) {
  return [...new Set(post.variants?.map(v => v.platform) ?? [])]
}

function formatDate(date) {
  if (!date) return ''
  return format(new Date(date), 'MMM d, yyyy h:mm a')
}
</script>
