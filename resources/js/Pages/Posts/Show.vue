<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="flex items-center gap-3">
        <button @click="$router.back()" class="btn-ghost btn-sm"><ArrowLeft class="w-4 h-4" /></button>
        <h1 class="text-xl font-semibold text-gray-900">{{ post?.title || 'Post Details' }}</h1>
        <StatusBadge v-if="post" :status="post.status" />
      </div>

      <LoadingSpinner v-if="loading" class="py-16" />

      <template v-else-if="post">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Main info -->
          <div class="lg:col-span-2 space-y-4">
            <div class="card">
              <div class="card-header flex items-center justify-between">
                <h2 class="font-semibold text-gray-900">Content</h2>
                <div class="flex gap-2">
                  <Link v-if="post.status === 'draft'" :href="`/posts/${post.id}/edit`">
                    <button class="btn-secondary btn-sm">Edit</button>
                  </Link>
                  <button v-if="['draft','approved','scheduled'].includes(post.status)" @click="publishNow" class="btn-primary btn-sm">
                    Publish Now
                  </button>
                  <button v-if="post.status === 'scheduled'" @click="cancelPost" class="btn-ghost btn-sm text-red-600">
                    Cancel
                  </button>
                  <button @click="duplicatePost" class="btn-ghost btn-sm">Duplicate</button>
                </div>
              </div>
              <div class="card-body">
                <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ post.base_content || '(No base content)' }}</p>
                <div v-if="post.scheduled_at" class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                  <Clock class="w-4 h-4" />
                  Scheduled for {{ formatDate(post.scheduled_at) }}
                </div>
              </div>
            </div>

            <!-- Platform variants -->
            <div class="card">
              <div class="card-header">
                <h2 class="font-semibold text-gray-900">Platform Variants</h2>
              </div>
              <div class="card-body p-0 divide-y divide-gray-100">
                <div v-for="variant in post.variants" :key="variant.id" class="px-6 py-4">
                  <div class="flex items-center gap-3 mb-3">
                    <PlatformIcon :platform="variant.platform" size="sm" />
                    <span class="font-medium text-sm text-gray-900">{{ variant.social_account?.account_name }}</span>
                    <StatusBadge :status="variant.status" />
                    <div class="flex-1" />
                    <!-- External link if published -->
                    <a
                      v-if="variant.publishing_logs?.find(l => l.status === 'success')?.external_post_url"
                      :href="variant.publishing_logs.find(l => l.status === 'success').external_post_url"
                      target="_blank"
                      class="text-blue-600 hover:underline text-xs flex items-center gap-1"
                    >
                      <ExternalLink class="w-3 h-3" /> View Post
                    </a>
                  </div>
                  <p v-if="variant.content" class="text-sm text-gray-600 mb-2">{{ variant.content }}</p>
                  <!-- Errors -->
                  <div v-if="variant.validation_errors?.length" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                    <p v-for="err in variant.validation_errors" :key="err" class="text-xs text-red-700">• {{ err }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Publishing logs -->
            <div class="card">
              <div class="card-header">
                <h2 class="font-semibold text-gray-900">Publishing Logs</h2>
              </div>
              <div class="card-body p-0 divide-y divide-gray-100">
                <div v-if="!post.publishing_logs?.length" class="px-6 py-8 text-center text-sm text-gray-400">
                  No publishing logs yet.
                </div>
                <div v-for="log in post.publishing_logs" :key="log.id" class="px-6 py-3 flex items-start gap-3">
                  <PlatformIcon :platform="log.platform" size="sm" />
                  <div class="flex-1">
                    <div class="flex items-center gap-2">
                      <span class="text-sm font-medium text-gray-900">{{ log.social_account?.account_name }}</span>
                      <StatusBadge :status="log.status" />
                    </div>
                    <p v-if="log.error_message" class="text-xs text-red-600 mt-1">{{ log.error_message }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ formatDate(log.created_at) }} · Retry {{ log.retry_count }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Sidebar -->
          <div class="space-y-4">
            <div class="card p-5">
              <h3 class="font-semibold text-gray-900 text-sm mb-4">Post Info</h3>
              <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-500">Created by</span>
                  <span class="font-medium">{{ post.creator?.name }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500">Created</span>
                  <span class="font-medium">{{ formatDate(post.created_at) }}</span>
                </div>
                <div v-if="post.approval_status !== 'not_required'" class="flex justify-between">
                  <span class="text-gray-500">Approval</span>
                  <StatusBadge :status="post.approval_status" />
                </div>
              </div>
            </div>

            <!-- Media -->
            <div v-if="post.post_media?.length" class="card p-5">
              <h3 class="font-semibold text-gray-900 text-sm mb-3">Media</h3>
              <div class="grid grid-cols-3 gap-2">
                <div v-for="pm in post.post_media" :key="pm.id" class="aspect-square rounded-lg overflow-hidden bg-gray-100">
                  <img v-if="pm.asset?.is_image" :src="pm.asset.url" class="w-full h-full object-cover" />
                  <div v-else class="flex items-center justify-center h-full">
                    <Video class="w-6 h-6 text-gray-400" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Approval actions -->
            <div v-if="post.approval_status === 'pending' && canApprove" class="card p-5">
              <h3 class="font-semibold text-gray-900 text-sm mb-3">Approval</h3>
              <div class="space-y-2">
                <textarea v-model="approvalNotes" class="input" rows="2" placeholder="Optional notes…" />
                <div class="flex gap-2">
                  <button @click="approvePost" class="btn-primary flex-1 btn-sm">Approve</button>
                  <button @click="rejectPost" class="btn-danger flex-1 btn-sm">Reject</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Clock, ExternalLink, Video } from 'lucide-vue-next'
import { format } from 'date-fns'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/Common/StatusBadge.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const props = defineProps({ postId: String })
const { get, post: apiPost, loading } = useApi()
const toast = useToast()
const page  = usePage()

const post          = ref(null)
const approvalNotes = ref('')

const canApprove = computed(() => ['owner', 'admin', 'approver'].includes(page.props.auth.user?.role))

onMounted(async () => {
  post.value = await get(`posts/${props.postId}`)
})

async function publishNow() {
  await apiPost(`posts/${props.postId}/publish-now`)
  toast.success('Publishing started!')
  post.value = await get(`posts/${props.postId}`)
}

async function cancelPost() {
  if (!confirm('Cancel this post?')) return
  await apiPost(`posts/${props.postId}/cancel`)
  toast.success('Post cancelled.')
  post.value = await get(`posts/${props.postId}`)
}

async function duplicatePost() {
  const newPost = await apiPost(`posts/${props.postId}/duplicate`)
  toast.success('Post duplicated.')
  router.visit(`/posts/${newPost.id}/edit`)
}

async function approvePost() {
  await apiPost(`posts/${props.postId}/approve`, { notes: approvalNotes.value })
  toast.success('Post approved.')
  post.value = await get(`posts/${props.postId}`)
}

async function rejectPost() {
  if (!approvalNotes.value) { toast.error('Please provide rejection notes.'); return }
  await apiPost(`posts/${props.postId}/reject`, { notes: approvalNotes.value })
  toast.success('Post rejected.')
  post.value = await get(`posts/${props.postId}`)
}

function formatDate(date) { return format(new Date(date), 'MMM d, yyyy h:mm a') }
</script>
