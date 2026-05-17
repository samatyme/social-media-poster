<template>
  <div :class="['flex items-center justify-center', fullPage ? 'min-h-64' : '']">
    <div :class="['animate-spin rounded-full border-2 border-gray-200 border-t-blue-600', sizeClass]" />
    <span v-if="text" class="ml-3 text-sm text-gray-500">{{ text }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  size:     { type: String, default: 'md' },
  text:     String,
  fullPage: Boolean,
})
const sizeClass = computed(() => ({ sm: 'w-4 h-4', md: 'w-8 h-8', lg: 'w-12 h-12' }[props.size]))
</script>
