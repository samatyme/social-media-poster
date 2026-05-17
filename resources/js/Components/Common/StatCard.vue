<template>
  <div class="card p-5">
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ label }}</span>
      <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', colorClass]">
        <component :is="icon" class="w-4 h-4" />
      </div>
    </div>
    <p class="text-2xl font-bold text-gray-900">{{ value.toLocaleString() }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({ label: String, value: Number, icon: Object, color: String })
const colorClass = computed(() => ({
  gray:   'bg-gray-100 text-gray-600',
  blue:   'bg-blue-100 text-blue-600',
  green:  'bg-green-100 text-green-600',
  red:    'bg-red-100 text-red-600',
  yellow: 'bg-yellow-100 text-yellow-600',
  purple: 'bg-purple-100 text-purple-600',
}[props.color] ?? 'bg-gray-100 text-gray-600'))
</script>
