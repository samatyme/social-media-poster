<template>
  <AppLayout>
    <div class="space-y-4">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex gap-2 flex-wrap">
          <button
            v-for="tab in statusTabs" :key="tab.value"
            @click="activeStatus = tab.value"
            :class="['px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                     activeStatus === tab.value
                       ? 'bg-blue-600 text-white'
                       : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']"
          >
            {{ tab.label }}
          </button>
        </div>
        <div class="flex items-center gap-3">
          <div class="relative">
            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input v-model="search" type="text" placeholder="Search posts…" class="input pl-9 w-56" />
          </div>
          <Link href="/posts/create">
            <button class="btn-primary"><Plus class="w-4 h-4" /> New Post</button>
          </Link>
        </div>
      </div>

      <!-- Posts list -->
      <div class="card">
        <LoadingSpinner v-if="loading" class="py-12" text="Loading posts…" />
        <EmptyState
          v-else-if="!posts.data?.length"
          title="No posts found"
          description="Create your first post to get started."
          class="py-12"
        >
          <Link href="/posts/create"><button class="btn-primary">Create Post</button></Link>
        </EmptyState>

        <div v-else class="divide-y divide-gray-100">
          <div v-for="post in posts.data" :key="post.id" class="px-6 py-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start gap-4">
              <!-- Platforms -->
              <div class="flex flex-col items-center gap-1 pt-0.5">
                <div class="flex -space-x-1">
                  <PlatformIcon
                    v-for="platform in getPostPlatforms(post)"
                    :key="platform"
                    :platform="platform"
                    size="sm"
                  />
                </div>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <h3 class="text-sm font-semibold text-gray-900 truncate">
                    {{ post.title || 'Untitled Post' }}
                  </h3>
                  <StatusBadge :status="post.status" />
                </div>
                <p class="text-sm text-gray-500 line-clamp-2">
                  {{ post.base_content || '(No content)' }}
                </p>
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                  <span>By {{ post.creator?.name }}</span>
                  <span v-if="post.scheduled_at">
                    <Clock class="w-3 h-3 inline mr-1" />
                    {{ formatDate(post.scheduled_at) }}
                  </span>
                  <span v-if="post.post_media?.length">
                    <Image class="w-3 h-3 inline mr-1" />
                    {{ post.post_media.length }} media
                  </span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2 flex-shrink-0">
                <Link :href="`/posts/${post.id}`">
                  <button class="btn-ghost btn-sm">View</button>
                </Link>
                <Link v-if="post.status === 'draft'" :href="`/posts/${post.id}/edit`">
                  <button class="btn-secondary btn-sm">Edit</button>
                </Link>
                <button
                  v-if="post.status === 'draft' || post.status === 'approved'"
                  @click="publishNow(post)"
                  class="btn-primary btn-sm"
                >Publish</button>
                <button
                  v-if="post.status === 'failed'"
                  @click="retryPost(post)"
                  class="btn-secondary btn-sm text-orange-600"
                >Retry</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="posts.meta?.last_page > 1" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
          <p class="text-sm text-gray-500">
            Showing {{ posts.meta.from }}–{{ posts.meta.to }} of {{ posts.meta.total }}
          </p>
          <div class="flex gap-2">
            <button :disabled="posts.meta.current_page === 1" @click="page--" class="btn-secondary btn-sm">Previous</button>
            <button :disabled="posts.meta.current_page === posts.meta.last_page" @click="page++" class="btn-secondary btn-sm">Next</button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Search, Plus, Clock, Image } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/Common/StatusBadge.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'
import { format } from 'date-fns'

const { get, post: apiPost, loading } = useApi()
const toast = useToast()

const posts        = ref({ data: [], meta: {} })
const activeStatus = ref('')
const search       = ref('')
const page         = ref(1)

const statusTabs = [
  { value: '',              label: 'All' },
  { value: 'draft',         label: 'Drafts' },
  { value: 'scheduled',     label: 'Scheduled' },
  { value: 'published',     label: 'Published' },
  { value: 'failed',        label: 'Failed' },
  { value: 'pending_approval', label: 'Pending' },
]

async function fetchPosts() {
  const params = { page: page.value }
  if (activeStatus.value) params.status = activeStatus.value
  if (search.value) params.search = search.value
  const data = await get('posts', params)
  posts.value = data
}

onMounted(fetchPosts)
watch([activeStatus, search, page], fetchPosts)

function getPostPlatforms(post) {
  return [...new Set(post.variants?.map(v => v.platform) ?? [])]
}

function formatDate(date) {
  return format(new Date(date), 'MMM d, h:mm a')
}

async function publishNow(post) {
  try {
    await apiPost(`posts/${post.id}/publish-now`)
    toast.success('Post is being published!')
    fetchPosts()
  } catch {
    toast.error('Failed to publish post.')
  }
}

async function retryPost(post) {
  try {
    await apiPost(`posts/${post.id}/publish-now`)
    toast.success('Retrying post…')
    fetchPosts()
  } catch {
    toast.error('Failed to retry post.')
  }
}
</script>
