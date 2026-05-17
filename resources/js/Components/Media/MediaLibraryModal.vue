<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl mx-4 flex flex-col max-h-[80vh]">
      <div class="flex items-center justify-between px-6 py-4 border-b flex-shrink-0">
        <h2 class="font-semibold text-gray-900">Media Library</h2>
        <button @click="$emit('close')" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
      </div>
      <div class="p-4 border-b flex-shrink-0">
        <input v-model="search" type="text" placeholder="Search…" class="input" />
      </div>
      <div class="flex-1 overflow-y-auto p-4">
        <LoadingSpinner v-if="loading" class="py-8" />
        <div v-else class="grid grid-cols-4 sm:grid-cols-6 gap-3">
          <div
            v-for="asset in assets"
            :key="asset.id"
            @click="toggleSelect(asset)"
            :class="['aspect-square rounded-xl overflow-hidden cursor-pointer border-2 transition-colors relative',
                     isSelected(asset.id) ? 'border-blue-500' : 'border-transparent hover:border-gray-300']"
          >
            <img v-if="asset.is_image" :src="asset.url" class="w-full h-full object-cover" />
            <div v-else class="w-full h-full bg-gray-100 flex items-center justify-center">
              <Video class="w-6 h-6 text-gray-400" />
            </div>
            <div v-if="isSelected(asset.id)" class="absolute top-1 right-1 w-5 h-5 bg-blue-600 rounded-full flex items-center justify-center">
              <Check class="w-3 h-3 text-white" />
            </div>
          </div>
        </div>
      </div>
      <div class="px-6 py-4 border-t flex justify-between items-center flex-shrink-0">
        <span class="text-sm text-gray-500">{{ localSelected.length }} selected</span>
        <div class="flex gap-3">
          <button @click="$emit('close')" class="btn-secondary">Cancel</button>
          <button @click="confirmSelection" :disabled="!localSelected.length" class="btn-primary">
            Add {{ localSelected.length > 0 ? `(${localSelected.length})` : '' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { X, Video, Check } from 'lucide-vue-next'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import { useApi } from '@/Composables/useApi'

const props = defineProps({ selectedIds: { type: Array, default: () => [] } })
const emit  = defineEmits(['close', 'select'])

const { get, loading } = useApi()
const assets        = ref([])
const search        = ref('')
const localSelected = ref([...props.selectedIds])

async function fetchAssets() {
  const params = {}
  if (search.value) params.search = search.value
  const data = await get('media', params)
  assets.value = data.data ?? data
}

onMounted(fetchAssets)
watch(search, fetchAssets)

function isSelected(id) { return localSelected.value.includes(id) }

function toggleSelect(asset) {
  const idx = localSelected.value.indexOf(asset.id)
  if (idx > -1) localSelected.value.splice(idx, 1)
  else localSelected.value.push(asset.id)
}

function confirmSelection() {
  const selected = assets.value.filter(a => localSelected.value.includes(a.id))
  emit('select', selected)
}
</script>
