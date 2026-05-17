<template>
  <span :class="config.class">{{ config.label }}</span>
</template>

<script setup>
import { computed } from 'vue'
import { usePlatform } from '@/Composables/usePlatform'

const props = defineProps({ status: String })
const { getStatusConfig } = usePlatform()
const config = computed(() => getStatusConfig(props.status))
</script>
