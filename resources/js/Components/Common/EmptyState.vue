<template>
  <div class="flex flex-col items-center justify-center py-16 text-center">
    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
      <component :is="icon" class="w-8 h-8 text-gray-400" v-if="icon" />
      <Inbox class="w-8 h-8 text-gray-400" v-else />
    </div>
    <h3 class="text-base font-semibold text-gray-900 mb-1">{{ title }}</h3>
    <p class="text-sm text-gray-500 mb-6 max-w-sm">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup>
import { Inbox } from 'lucide-vue-next'
defineProps({ title: String, description: String, icon: Object })
</script>
