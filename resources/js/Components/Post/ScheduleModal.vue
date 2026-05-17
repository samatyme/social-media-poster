<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
      <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h2 class="font-semibold text-gray-900">Schedule Post</h2>
        <button @click="$emit('close')" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
      </div>
      <div class="px-6 py-5 space-y-4">
        <div>
          <label class="label">Date & Time</label>
          <input v-model="scheduledAt" type="datetime-local" class="input" :min="minDateTime" />
        </div>
        <div>
          <label class="label">Timezone</label>
          <select v-model="timezone" class="input">
            <option v-for="tz in commonTimezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
          </select>
        </div>
        <div v-if="error" class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
          {{ error }}
        </div>
      </div>
      <div class="px-6 py-4 border-t border-gray-200 flex gap-3 justify-end">
        <button @click="$emit('close')" class="btn-secondary">Cancel</button>
        <button @click="submit" class="btn-primary" :disabled="!scheduledAt">
          <Clock class="w-4 h-4" /> Schedule
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { X, Clock } from 'lucide-vue-next'
import { format } from 'date-fns'

const emit = defineEmits(['close', 'schedule'])

const scheduledAt = ref('')
const timezone    = ref(Intl.DateTimeFormat().resolvedOptions().timeZone)
const error       = ref(null)

const minDateTime = computed(() => {
  return format(new Date(Date.now() + 5 * 60 * 1000), "yyyy-MM-dd'T'HH:mm")
})

const commonTimezones = [
  { value: 'UTC',                  label: 'UTC' },
  { value: 'America/New_York',     label: 'Eastern Time (ET)' },
  { value: 'America/Chicago',      label: 'Central Time (CT)' },
  { value: 'America/Denver',       label: 'Mountain Time (MT)' },
  { value: 'America/Los_Angeles',  label: 'Pacific Time (PT)' },
  { value: 'Europe/London',        label: 'London (GMT/BST)' },
  { value: 'Europe/Paris',         label: 'Central European (CET)' },
  { value: 'Asia/Dubai',           label: 'Dubai (GST)' },
  { value: 'Asia/Singapore',       label: 'Singapore (SGT)' },
  { value: 'Asia/Tokyo',           label: 'Tokyo (JST)' },
  { value: 'Australia/Sydney',     label: 'Sydney (AEDT)' },
  { value: 'Africa/Nairobi',       label: 'Nairobi (EAT)' },
]

function submit() {
  error.value = null
  if (!scheduledAt.value) {
    error.value = 'Please select a date and time.'
    return
  }
  const dt = new Date(scheduledAt.value)
  if (dt <= new Date()) {
    error.value = 'Scheduled time must be in the future.'
    return
  }
  emit('schedule', { scheduled_at: dt.toISOString(), timezone: timezone.value })
}
</script>
