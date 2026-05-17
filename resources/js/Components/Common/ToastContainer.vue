<template>
  <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="['flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-sm font-medium pointer-events-auto max-w-sm',
          toast.type === 'success' ? 'bg-green-600 text-white' :
          toast.type === 'error'   ? 'bg-red-600 text-white' :
          toast.type === 'warning' ? 'bg-yellow-500 text-white' :
          'bg-gray-800 text-white']"
      >
        <CheckCircle v-if="toast.type === 'success'" class="w-4 h-4 flex-shrink-0" />
        <XCircle     v-else-if="toast.type === 'error'"   class="w-4 h-4 flex-shrink-0" />
        <AlertTriangle v-else-if="toast.type === 'warning'" class="w-4 h-4 flex-shrink-0" />
        <Info v-else class="w-4 h-4 flex-shrink-0" />
        <span class="flex-1">{{ toast.message }}</span>
        <button @click="remove(toast.id)" class="opacity-70 hover:opacity-100">
          <X class="w-4 h-4" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'
import { useToast } from '@/Composables/useToast'
const { toasts, remove } = useToast()
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.3s ease; }
.toast-enter-from { transform: translateX(100%); opacity: 0; }
.toast-leave-to   { transform: translateX(100%); opacity: 0; }
</style>
