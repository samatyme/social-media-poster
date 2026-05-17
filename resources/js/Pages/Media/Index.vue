<template>
  <AppLayout>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="relative">
            <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input v-model="search" type="text" placeholder="Search media…" class="input pl-9 w-56" />
          </div>
          <select v-model="typeFilter" class="input w-36">
            <option value="">All types</option>
            <option value="image">Images</option>
            <option value="video">Videos</option>
          </select>
        </div>
        <button @click="$refs.uploadInput.click()" class="btn-primary">
          <Upload class="w-4 h-4" /> Upload Media
        </button>
        <input ref="uploadInput" type="file" multiple accept="image/*,video/*" class="hidden" @change="handleUpload" />
      </div>

      <!-- Usage bar -->
      <div class="card p-4">
        <div class="flex items-center justify-between text-sm mb-2">
          <span class="text-gray-600">Storage used</span>
          <span class="font-medium text-gray-900">{{ storageUsedHuman }}</span>
        </div>
      </div>

      <LoadingSpinner v-if="loading" class="py-16" text="Loading media…" />

      <EmptyState
        v-else-if="!assets.data?.length"
        title="No media yet"
        description="Upload images and videos to use in your posts."
        class="py-16"
      >
        <button @click="$refs.uploadInput.click()" class="btn-primary">Upload Media</button>
      </EmptyState>

      <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div
          v-for="asset in assets.data"
          :key="asset.id"
          class="group relative rounded-xl overflow-hidden bg-gray-100 border border-gray-200 cursor-pointer hover:border-blue-400 transition-colors"
          @click="selectedAsset = asset"
        >
          <div class="aspect-square">
            <img v-if="asset.is_image" :src="asset.url" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full flex items-center justify-center">
              <Video class="w-8 h-8 text-gray-400" />
            </div>
          </div>
          <!-- Hover overlay -->
          <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
            <button @click.stop="deleteAsset(asset)" class="btn bg-red-500 text-white btn-sm">
              <Trash2 class="w-3 h-3" />
            </button>
          </div>
          <!-- File type badge -->
          <div class="absolute top-1.5 left-1.5">
            <span class="px-1.5 py-0.5 bg-black/60 text-white text-xs rounded">
              {{ asset.is_video ? 'VIDEO' : 'IMG' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="assets.meta?.last_page > 1" class="flex justify-between items-center">
        <p class="text-sm text-gray-500">{{ assets.meta.total }} files</p>
        <div class="flex gap-2">
          <button :disabled="assets.meta.current_page === 1" @click="page--" class="btn-secondary btn-sm">Previous</button>
          <button :disabled="assets.meta.current_page === assets.meta.last_page" @click="page++" class="btn-secondary btn-sm">Next</button>
        </div>
      </div>
    </div>

    <!-- Asset detail modal -->
    <div v-if="selectedAsset" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="selectedAsset = null">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h3 class="font-semibold text-gray-900">{{ selectedAsset.original_name }}</h3>
          <button @click="selectedAsset = null" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
        </div>
        <div class="p-6">
          <img v-if="selectedAsset.is_image" :src="selectedAsset.url" class="w-full rounded-lg mb-4 max-h-64 object-contain" />
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-500">Type</span>
              <span class="font-medium">{{ selectedAsset.mime_type }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-500">Size</span>
              <span class="font-medium">{{ selectedAsset.file_size_human }}</span>
            </div>
            <div v-if="selectedAsset.width" class="flex justify-between">
              <span class="text-gray-500">Dimensions</span>
              <span class="font-medium">{{ selectedAsset.width }}×{{ selectedAsset.height }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { Search, Upload, Video, Trash2, X } from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import EmptyState from '@/Components/Common/EmptyState.vue'
import { useApi } from '@/Composables/useApi'
import { useToast } from '@/Composables/useToast'

const { get, delete: apiDelete, loading } = useApi()
const toast = useToast()

const assets       = ref({ data: [], meta: {} })
const search       = ref('')
const typeFilter   = ref('')
const page         = ref(1)
const selectedAsset = ref(null)

const storageUsedHuman = computed(() => {
  const bytes = assets.value.data?.reduce((acc, a) => acc + (a.file_size ?? 0), 0) ?? 0
  if (bytes < 1024)    return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
})

async function fetchAssets() {
  const params = { page: page.value }
  if (search.value)     params.search = search.value
  if (typeFilter.value) params.type   = typeFilter.value
  assets.value = await get('media', params)
}

onMounted(fetchAssets)
watch([search, typeFilter, page], fetchAssets)

async function handleUpload(event) {
  for (const file of event.target.files) {
    const fd = new FormData()
    fd.append('file', file)
    try {
      const res = await window.axios.post('/api/media', fd)
      toast.success(file.name + ' uploaded.')
      await fetchAssets()
    } catch {
      toast.error('Failed to upload ' + file.name)
    }
  }
}

async function deleteAsset(asset) {
  if (!confirm('Delete this file? It will be removed from all drafts.')) return
  await apiDelete(`media/${asset.id}`)
  toast.success('Media deleted.')
  fetchAssets()
}
</script>
