<template>
  <div class="rounded-xl border border-gray-200 overflow-hidden bg-white text-sm">
    <!-- Header -->
    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100">
      <PlatformIcon :platform="account.platform" size="sm" />
      <div>
        <p class="font-semibold text-xs text-gray-900">{{ account.account_name }}</p>
        <p class="text-xs text-gray-400">{{ account.account_handle }}</p>
      </div>
      <div class="ml-auto text-xs text-gray-400">Just now</div>
    </div>

    <!-- Content -->
    <div class="px-3 py-3 space-y-2">
      <!-- Media -->
      <div v-if="media.length" class="rounded-lg overflow-hidden bg-gray-100">
        <img
          v-if="media[0].is_image"
          :src="media[0].url"
          class="w-full max-h-48 object-cover"
        />
        <div v-else class="flex items-center justify-center h-32">
          <Video class="w-10 h-10 text-gray-400" />
          <span class="ml-2 text-xs text-gray-500">Video</span>
        </div>
      </div>

      <!-- Text -->
      <p class="text-xs text-gray-800 whitespace-pre-wrap leading-relaxed">
        {{ displayContent || '(No content)' }}
      </p>

      <!-- Link preview placeholder -->
      <div v-if="variant?.link_url" class="rounded-lg border border-gray-200 px-3 py-2 bg-gray-50">
        <p class="text-xs text-blue-600 truncate">{{ variant.link_url }}</p>
      </div>

      <!-- Character count warning -->
      <div v-if="overLimit" class="flex items-center gap-1 text-xs text-red-600">
        <AlertTriangle class="w-3 h-3" />
        Exceeds {{ maxLength }} character limit by {{ displayContent.length - maxLength }}
      </div>
    </div>

    <!-- Footer -->
    <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-1"><Heart class="w-3 h-3" /> Like</span>
        <span class="flex items-center gap-1"><MessageSquare class="w-3 h-3" /> Comment</span>
        <span class="flex items-center gap-1"><Share2 class="w-3 h-3" /> Share</span>
      </div>
      <span :class="overLimit ? 'text-red-500' : ''">{{ displayContent.length }}/{{ maxLength }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Video, AlertTriangle, Heart, MessageSquare, Share2 } from 'lucide-vue-next'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'

const props = defineProps({
  account:     Object,
  variant:     Object,
  baseContent: String,
  media:       { type: Array, default: () => [] },
})

const displayContent = computed(() => {
  let c = props.variant?.content || props.baseContent || ''
  const hashtags = props.variant?.hashtags
  if (hashtags?.length) {
    c += '\n\n' + hashtags.join(' ')
  }
  return c
})

const platformRules = computed(() => window.__platformRules?.[props.account?.platform] ?? {})
const maxLength     = computed(() => platformRules.value.max_text_length ?? 5000)
const overLimit     = computed(() => displayContent.value.length > maxLength.value)
</script>
