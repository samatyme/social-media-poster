<template>
  <Link
    :href="item.href"
    :class="[
      'flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors group',
      isActive
        ? 'bg-blue-50 text-blue-700'
        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
    ]"
  >
    <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
    <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
  </Link>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({ item: Object, collapsed: Boolean })
const page  = usePage()
const isActive = computed(() => page.url.startsWith(props.item.href))
</script>
