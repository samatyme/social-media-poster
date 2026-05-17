<template>
  <div
    :style="{ backgroundColor: color + '20', color: color }"
    :class="['rounded-full flex items-center justify-center font-bold text-xs flex-shrink-0', sizeClass]"
    :title="label"
  >
    {{ abbr }}
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { PLATFORM_COLORS, PLATFORM_LABELS } from '@/Composables/usePlatform'

const props = defineProps({
  platform: String,
  size: { type: String, default: 'md' }
})

const color = computed(() => PLATFORM_COLORS[props.platform] ?? '#6b7280')
const label = computed(() => PLATFORM_LABELS[props.platform] ?? props.platform)
const abbr  = computed(() => {
  const map = { facebook: 'FB', instagram: 'IG', tiktok: 'TT', x: 'X', linkedin: 'LI' }
  return map[props.platform] ?? props.platform.slice(0, 2).toUpperCase()
})
const sizeClass = computed(() => ({
  'sm': 'w-6 h-6',
  'md': 'w-8 h-8',
  'lg': 'w-10 h-10',
}[props.size] ?? 'w-8 h-8'))
</script>
