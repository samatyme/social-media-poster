<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <button @click="$router.back()" class="btn-ghost btn-sm"><ArrowLeft class="w-4 h-4" /></button>
          <h1 class="text-xl font-semibold text-gray-900">{{ isEditing ? 'Edit Post' : 'Create Post' }}</h1>
        </div>
        <div class="flex items-center gap-3">
          <button @click="saveDraft" :disabled="saving" class="btn-secondary">
            <Save class="w-4 h-4" /> {{ saving ? 'Saving…' : 'Save Draft' }}
          </button>
          <button @click="showScheduleModal = true" :disabled="saving || !hasAccounts" class="btn-secondary">
            <Clock class="w-4 h-4" /> Schedule
          </button>
          <button @click="publishNow" :disabled="saving || !hasAccounts" class="btn-primary">
            <Send class="w-4 h-4" /> Publish Now
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Left: Composer -->
        <div class="xl:col-span-2 space-y-4">
          <!-- Internal title -->
          <div class="card">
            <div class="card-body">
              <label class="label">Post Title <span class="text-gray-400 font-normal">(internal label)</span></label>
              <input v-model="form.title" type="text" class="input" placeholder="e.g. Product launch - Week 3" />
            </div>
          </div>

          <!-- Base content -->
          <div class="card">
            <div class="card-header flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">Base Content</h2>
              <span :class="['text-xs font-mono', baseContentLength > 280 ? 'text-orange-500' : 'text-gray-400']">
                {{ baseContentLength }} chars
              </span>
            </div>
            <div class="card-body pt-0">
              <textarea
                v-model="form.base_content"
                rows="6"
                class="input resize-none"
                placeholder="Write your post content here. Each platform can be customized below."
              />
              <div class="flex items-center gap-4 mt-3">
                <div class="flex-1">
                  <label class="label text-xs">Hashtags (applies to all)</label>
                  <input
                    v-model="hashtagInput"
                    type="text"
                    class="input"
                    placeholder="#marketing #socialmedia"
                    @keydown.enter.prevent="addHashtag"
                    @keydown.space.prevent="addHashtag"
                  />
                  <div class="flex flex-wrap gap-1 mt-2">
                    <span
                      v-for="tag in form.tags" :key="tag"
                      class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs"
                    >
                      {{ tag }}
                      <button @click="removeTag(tag)"><X class="w-3 h-3" /></button>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Media upload -->
          <div class="card">
            <div class="card-header flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">Media</h2>
              <button @click="showMediaLibrary = true" class="btn-secondary btn-sm">
                <Image class="w-4 h-4" /> Add from Library
              </button>
            </div>
            <div class="card-body">
              <!-- Drop zone -->
              <div
                @dragover.prevent="dragging = true"
                @dragleave="dragging = false"
                @drop.prevent="handleDrop"
                :class="['border-2 border-dashed rounded-xl p-6 text-center transition-colors',
                         dragging ? 'border-blue-400 bg-blue-50' : 'border-gray-200 hover:border-gray-300']"
              >
                <Upload class="w-8 h-8 text-gray-300 mx-auto mb-2" />
                <p class="text-sm text-gray-500">
                  Drop files here or
                  <button @click="$refs.fileInput.click()" class="text-blue-600 hover:underline">browse</button>
                </p>
                <p class="text-xs text-gray-400 mt-1">Images, videos up to 500MB</p>
                <input ref="fileInput" type="file" multiple accept="image/*,video/*" class="hidden" @change="handleFileSelect" />
              </div>

              <!-- Selected media -->
              <div v-if="form.media_ids.length" class="grid grid-cols-4 gap-3 mt-4">
                <div v-for="(asset, i) in selectedMedia" :key="asset.id" class="relative group">
                  <img
                    v-if="asset.is_image"
                    :src="asset.url"
                    class="w-full aspect-square object-cover rounded-lg"
                  />
                  <div v-else class="w-full aspect-square bg-gray-100 rounded-lg flex items-center justify-center">
                    <Video class="w-8 h-8 text-gray-400" />
                  </div>
                  <button
                    @click="removeMedia(i)"
                    class="absolute top-1 right-1 bg-black/50 text-white rounded-full w-5 h-5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <X class="w-3 h-3" />
                  </button>
                </div>
                <!-- Uploading indicator -->
                <div v-for="u in uploadQueue" :key="u.id" class="relative">
                  <div class="w-full aspect-square bg-gray-100 rounded-lg flex items-center justify-center">
                    <LoadingSpinner size="sm" />
                  </div>
                  <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-200 rounded-b-lg overflow-hidden">
                    <div class="h-full bg-blue-500 transition-all" :style="{ width: u.progress + '%' }" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Account selector -->
          <div class="card">
            <div class="card-header flex items-center justify-between">
              <h2 class="font-semibold text-gray-900">Target Accounts</h2>
              <Link href="/accounts" class="text-sm text-blue-600 hover:underline">Manage</Link>
            </div>
            <div class="card-body">
              <LoadingSpinner v-if="loadingAccounts" size="sm" />
              <EmptyState
                v-else-if="!socialAccounts.length"
                title="No connected accounts"
                description="Connect social accounts first."
                class="py-4"
              />
              <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label
                  v-for="account in socialAccounts"
                  :key="account.id"
                  :class="['flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors',
                           isAccountSelected(account.id)
                             ? 'border-blue-500 bg-blue-50'
                             : 'border-gray-200 hover:border-gray-300']"
                >
                  <input
                    type="checkbox"
                    :checked="isAccountSelected(account.id)"
                    @change="toggleAccount(account)"
                    class="hidden"
                  />
                  <PlatformIcon :platform="account.platform" size="sm" />
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ account.account_name }}</p>
                    <p class="text-xs text-gray-500">{{ account.account_handle }}</p>
                  </div>
                  <span
                    v-if="isAccountSelected(account.id)"
                    class="w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center"
                  >
                    <Check class="w-3 h-3 text-white" />
                  </span>
                  <span
                    v-if="!account.is_healthy"
                    class="text-xs text-red-500"
                  >Token expired</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Per-platform customization -->
          <div v-if="form.account_ids.length" class="card">
            <div class="card-header">
              <h2 class="font-semibold text-gray-900">Platform Customization</h2>
              <p class="text-sm text-gray-500 mt-0.5">Customize the content for each platform. Leave blank to use base content.</p>
            </div>
            <div class="card-body space-y-4 pt-2">
              <div
                v-for="account in selectedAccounts"
                :key="account.id"
                class="border border-gray-200 rounded-xl overflow-hidden"
              >
                <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 border-b border-gray-200">
                  <PlatformIcon :platform="account.platform" size="sm" />
                  <span class="font-medium text-gray-900 text-sm">{{ account.account_name }}</span>
                  <span class="text-xs text-gray-500">({{ getPlatformLabel(account.platform) }})</span>
                  <div class="flex-1" />
                  <!-- Validation status -->
                  <div v-if="getVariantErrors(account.id).length" class="flex items-center gap-1 text-xs text-red-600">
                    <AlertTriangle class="w-3 h-3" />
                    {{ getVariantErrors(account.id).length }} issue(s)
                  </div>
                </div>
                <div class="p-4 space-y-3">
                  <!-- Custom content -->
                  <div>
                    <div class="flex items-center justify-between mb-1">
                      <label class="label text-xs">Custom Caption</label>
                      <span :class="['text-xs font-mono', getVariantContentLength(account.id) > getMaxLength(account.platform) ? 'text-red-500' : 'text-gray-400']">
                        {{ getVariantContentLength(account.id) }} / {{ getMaxLength(account.platform) }}
                      </span>
                    </div>
                    <textarea
                      v-model="getVariant(account.id).content"
                      rows="3"
                      class="input resize-none text-sm"
                      :placeholder="`Defaults to base content (max ${getMaxLength(account.platform)} chars)`"
                    />
                  </div>

                  <!-- Errors -->
                  <div v-if="getVariantErrors(account.id).length" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2">
                    <p v-for="err in getVariantErrors(account.id)" :key="err" class="text-xs text-red-700">• {{ err }}</p>
                  </div>

                  <!-- Link URL -->
                  <div>
                    <label class="label text-xs">Link URL</label>
                    <input v-model="getVariant(account.id).link_url" type="url" class="input text-sm" placeholder="https://example.com" />
                  </div>

                  <!-- First comment (where supported) -->
                  <div v-if="platformSupportsFirstComment(account.platform)">
                    <label class="label text-xs">First Comment</label>
                    <input v-model="getVariant(account.id).first_comment" type="text" class="input text-sm" placeholder="Add as first comment…" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Preview panel -->
        <div class="space-y-4">
          <div class="card sticky top-24">
            <div class="card-header">
              <h2 class="font-semibold text-gray-900">Preview</h2>
              <div class="flex gap-2 mt-2">
                <button
                  v-for="account in selectedAccounts.slice(0, 4)" :key="account.id"
                  @click="previewAccount = account"
                  :class="['p-1 rounded-lg transition-colors',
                           previewAccount?.id === account.id ? 'bg-gray-100' : 'hover:bg-gray-50']"
                >
                  <PlatformIcon :platform="account.platform" size="sm" />
                </button>
              </div>
            </div>
            <div class="card-body">
              <div v-if="!previewAccount" class="text-sm text-gray-400 text-center py-8">
                Select accounts to preview
              </div>
              <PostPreviewCard v-else :account="previewAccount" :variant="getVariant(previewAccount.id)" :base-content="form.base_content" :media="selectedMedia" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Schedule modal -->
    <ScheduleModal
      v-if="showScheduleModal"
      @close="showScheduleModal = false"
      @schedule="schedulePost"
    />

    <!-- Media library modal -->
    <MediaLibraryModal
      v-if="showMediaLibrary"
      :selected-ids="form.media_ids"
      @close="showMediaLibrary = false"
      @select="addMediaFromLibrary"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import {
  ArrowLeft, Save, Clock, Send, Image, Upload, Video,
  X, Check, AlertTriangle, Plus
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import PostPreviewCard from '@/Components/Post/PostPreviewCard.vue'
import ScheduleModal from '@/Components/Post/ScheduleModal.vue'
import MediaLibraryModal from '@/Components/Media/MediaLibraryModal.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'
import { usePlatform } from '@/Composables/usePlatform'

const props = defineProps({ postId: String })

const { get, post: apiPost, put, loading } = useApi()
const toast = useToast()
const { getPlatformLabel } = usePlatform()

const isEditing       = computed(() => !!props.postId)
const saving          = ref(false)
const loadingAccounts = ref(false)
const dragging        = ref(false)
const hashtagInput    = ref('')
const uploadQueue     = ref([])
const socialAccounts  = ref([])
const selectedMedia   = ref([])
const showScheduleModal  = ref(false)
const showMediaLibrary   = ref(false)
const previewAccount     = ref(null)

const form = ref({
  title:        '',
  base_content: '',
  timezone:     Intl.DateTimeFormat().resolvedOptions().timeZone,
  tags:         [],
  media_ids:    [],
  account_ids:  [],
  variants:     [],
})

const baseContentLength = computed(() => (form.value.base_content ?? '').length)
const hasAccounts = computed(() => form.value.account_ids.length > 0)

const selectedAccounts = computed(() =>
  socialAccounts.value.filter(a => form.value.account_ids.includes(a.id))
)

const platformRules = computed(() => window.__platformRules ?? {})

onMounted(async () => {
  loadingAccounts.value = true
  try {
    const data = await get('social-accounts')
    socialAccounts.value = data
    if (data.length && !previewAccount.value) {
      previewAccount.value = data[0]
    }
  } finally {
    loadingAccounts.value = false
  }

  if (isEditing.value) {
    const post = await get(`posts/${props.postId}`)
    form.value.title        = post.title
    form.value.base_content = post.base_content
    form.value.timezone     = post.timezone
    form.value.tags         = post.tags ?? []
    form.value.media_ids    = post.post_media?.map(m => m.media_asset_id) ?? []
    form.value.account_ids  = post.variants?.map(v => v.social_account_id) ?? []
    form.value.variants     = post.variants ?? []
    selectedMedia.value     = post.post_media?.map(m => m.asset) ?? []
  }
})

function isAccountSelected(id) { return form.value.account_ids.includes(id) }

function toggleAccount(account) {
  const idx = form.value.account_ids.indexOf(account.id)
  if (idx > -1) {
    form.value.account_ids.splice(idx, 1)
    form.value.variants = form.value.variants.filter(v => v.social_account_id !== account.id)
  } else {
    form.value.account_ids.push(account.id)
    form.value.variants.push({
      social_account_id: account.id,
      content: '', hashtags: [], first_comment: '', link_url: '', visibility: 'public',
      validation_errors: [], platform: account.platform,
    })
    if (!previewAccount.value) previewAccount.value = account
  }
}

function getVariant(accountId) {
  let v = form.value.variants.find(v => v.social_account_id === accountId)
  if (!v) {
    const account = socialAccounts.value.find(a => a.id === accountId)
    v = { social_account_id: accountId, content: '', hashtags: [], first_comment: '', link_url: '', visibility: 'public', validation_errors: [], platform: account?.platform }
    form.value.variants.push(v)
  }
  return v
}

function getVariantErrors(accountId) {
  return getVariant(accountId).validation_errors ?? []
}

function getVariantContentLength(accountId) {
  const v = getVariant(accountId)
  return (v.content || form.value.base_content || '').length
}

function getMaxLength(platform) {
  const rules = window.__platformRules?.[platform] ?? {}
  return rules.max_text_length ?? 5000
}

function platformSupportsFirstComment(platform) {
  const rules = window.__platformRules?.[platform] ?? {}
  return !!rules.supports_first_comment
}

function addHashtag() {
  const tag = hashtagInput.value.trim().replace(/^#*/, '#')
  if (tag && tag !== '#' && !form.value.tags.includes(tag)) {
    form.value.tags.push(tag)
  }
  hashtagInput.value = ''
}

function removeTag(tag) {
  form.value.tags = form.value.tags.filter(t => t !== tag)
}

function addMediaFromLibrary(assets) {
  for (const asset of assets) {
    if (!form.value.media_ids.includes(asset.id)) {
      form.value.media_ids.push(asset.id)
      selectedMedia.value.push(asset)
    }
  }
  showMediaLibrary.value = false
}

function removeMedia(index) {
  form.value.media_ids.splice(index, 1)
  selectedMedia.value.splice(index, 1)
}

async function handleFileSelect(event) {
  for (const file of event.target.files) {
    await uploadFile(file)
  }
}

async function handleDrop(event) {
  dragging.value = false
  for (const file of event.dataTransfer.files) {
    await uploadFile(file)
  }
}

async function uploadFile(file) {
  const id = Date.now() + Math.random()
  uploadQueue.value.push({ id, progress: 0 })
  const formData = new FormData()
  formData.append('file', file)
  try {
    const res = await window.axios.post('/api/media', formData, {
      onUploadProgress: (e) => {
        const item = uploadQueue.value.find(u => u.id === id)
        if (item) item.progress = Math.round((e.loaded * 100) / e.total)
      },
    })
    form.value.media_ids.push(res.data.id)
    selectedMedia.value.push(res.data)
    toast.success('Media uploaded.')
  } catch {
    toast.error('Failed to upload ' + file.name)
  } finally {
    uploadQueue.value = uploadQueue.value.filter(u => u.id !== id)
  }
}

async function saveDraft() {
  saving.value = true
  try {
    const payload = buildPayload()
    if (isEditing.value) {
      await put(`posts/${props.postId}`, payload)
      toast.success('Draft saved.')
    } else {
      const post = await apiPost('posts', payload)
      toast.success('Draft saved.')
      router.visit(`/posts/${post.id}/edit`)
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to save draft.')
  } finally {
    saving.value = false
  }
}

async function publishNow() {
  saving.value = true
  try {
    const payload = buildPayload()
    let postId = props.postId
    if (!isEditing.value) {
      const post = await apiPost('posts', payload)
      postId = post.id
    } else {
      await put(`posts/${postId}`, payload)
    }
    await apiPost(`posts/${postId}/publish-now`)
    toast.success('Publishing started!')
    router.visit(`/posts/${postId}`)
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to publish.')
  } finally {
    saving.value = false
  }
}

async function schedulePost({ scheduled_at, timezone }) {
  saving.value = true
  try {
    const payload = buildPayload()
    let postId = props.postId
    if (!isEditing.value) {
      const post = await apiPost('posts', payload)
      postId = post.id
    } else {
      await put(`posts/${postId}`, payload)
    }
    await apiPost(`posts/${postId}/schedule`, { scheduled_at, timezone })
    toast.success('Post scheduled successfully!')
    showScheduleModal.value = false
    router.visit(`/posts/${postId}`)
  } catch (err) {
    toast.error(err.response?.data?.message || 'Failed to schedule post.')
  } finally {
    saving.value = false
  }
}

function buildPayload() {
  return {
    title:        form.value.title,
    base_content: form.value.base_content,
    timezone:     form.value.timezone,
    tags:         form.value.tags,
    media_ids:    form.value.media_ids,
    account_ids:  form.value.account_ids,
    variants:     form.value.variants.map(v => ({
      social_account_id: v.social_account_id,
      content:           v.content || null,
      hashtags:          v.hashtags ?? [],
      first_comment:     v.first_comment || null,
      link_url:          v.link_url || null,
      visibility:        v.visibility ?? 'public',
      platform_options:  v.platform_options ?? null,
    })),
  }
}
</script>
