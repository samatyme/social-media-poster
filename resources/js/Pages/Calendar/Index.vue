<template>
  <AppLayout>
    <div class="space-y-4">
      <!-- Controls -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <button @click="prevMonth" class="btn-secondary btn-sm"><ChevronLeft class="w-4 h-4" /></button>
          <h2 class="text-lg font-semibold text-gray-900 w-40 text-center">{{ monthLabel }}</h2>
          <button @click="nextMonth" class="btn-secondary btn-sm"><ChevronRight class="w-4 h-4" /></button>
          <button @click="goToToday" class="btn-ghost btn-sm">Today</button>
        </div>
        <div class="flex gap-2">
          <button @click="view = 'month'" :class="['btn-sm', view === 'month' ? 'btn-primary' : 'btn-secondary']">Month</button>
          <button @click="view = 'list'"  :class="['btn-sm', view === 'list'  ? 'btn-primary' : 'btn-secondary']">List</button>
        </div>
      </div>

      <!-- Calendar grid -->
      <div v-if="view === 'month'" class="card overflow-hidden">
        <!-- Day headers -->
        <div class="grid grid-cols-7 border-b border-gray-200">
          <div v-for="day in dayNames" :key="day" class="py-2 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">
            {{ day }}
          </div>
        </div>

        <!-- Calendar cells -->
        <LoadingSpinner v-if="loading" class="py-16" />
        <div v-else class="grid grid-cols-7">
          <div
            v-for="cell in calendarCells"
            :key="cell.date"
            :class="['min-h-24 p-1.5 border-b border-r border-gray-100 last:border-r-0',
                     cell.isCurrentMonth ? '' : 'bg-gray-50',
                     cell.isToday ? 'bg-blue-50' : '']"
          >
            <div :class="['text-xs font-medium mb-1 w-6 h-6 flex items-center justify-center rounded-full',
                          cell.isToday ? 'bg-blue-600 text-white' : 'text-gray-600']">
              {{ cell.day }}
            </div>
            <div class="space-y-0.5">
              <div
                v-for="post in cell.posts"
                :key="post.id"
                @click="selectedPost = post"
                :class="['rounded px-1.5 py-0.5 text-xs cursor-pointer truncate flex items-center gap-1',
                         statusColors[post.status] ?? 'bg-gray-100 text-gray-700']"
              >
                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="{ backgroundColor: getPlatformColor(post.platforms?.[0]) }" />
                {{ post.title }}
              </div>
              <div v-if="cell.overflow > 0" class="text-xs text-gray-400 pl-1">+{{ cell.overflow }} more</div>
            </div>
          </div>
        </div>
      </div>

      <!-- List view -->
      <div v-else class="card divide-y divide-gray-100">
        <LoadingSpinner v-if="loading" class="py-16" />
        <div v-else-if="!scheduledPosts.length" class="text-center py-16 text-sm text-gray-400">
          No scheduled posts this month.
        </div>
        <div v-else v-for="post in scheduledPosts" :key="post.id" class="px-6 py-4 flex items-center gap-4">
          <div class="text-center w-12">
            <p class="text-xl font-bold text-gray-900">{{ format(new Date(post.start), 'd') }}</p>
            <p class="text-xs text-gray-500">{{ format(new Date(post.start), 'MMM') }}</p>
          </div>
          <div class="flex -space-x-1">
            <PlatformIcon v-for="p in post.platforms" :key="p" :platform="p" size="sm" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ post.title }}</p>
            <p class="text-xs text-gray-500">{{ format(new Date(post.start), 'h:mm a') }}</p>
          </div>
          <StatusBadge :status="post.status" />
          <Link :href="`/posts/${post.id}`" class="text-gray-400 hover:text-gray-600">
            <ChevronRight class="w-4 h-4" />
          </Link>
        </div>
      </div>
    </div>

    <!-- Post quick view modal -->
    <div v-if="selectedPost" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="selectedPost = null">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm mx-4 p-6">
        <div class="flex items-center justify-between mb-4">
          <StatusBadge :status="selectedPost.status" />
          <button @click="selectedPost = null" class="btn-ghost btn-sm"><X class="w-4 h-4" /></button>
        </div>
        <h3 class="font-semibold text-gray-900 mb-2">{{ selectedPost.title }}</h3>
        <p class="text-sm text-gray-500 mb-4">{{ format(new Date(selectedPost.start), 'MMMM d, yyyy h:mm a') }}</p>
        <div class="flex gap-2">
          <PlatformIcon v-for="p in selectedPost.platforms" :key="p" :platform="p" size="sm" />
        </div>
        <div class="mt-4 flex gap-3">
          <Link :href="`/posts/${selectedPost.id}`" class="flex-1">
            <button class="btn-primary w-full btn-sm">View Post</button>
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight, X } from 'lucide-vue-next'
import { format, startOfMonth, endOfMonth, startOfWeek, endOfWeek, eachDayOfInterval, isSameMonth, isToday, isSameDay } from 'date-fns'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusBadge from '@/Components/Common/StatusBadge.vue'
import PlatformIcon from '@/Components/Common/PlatformIcon.vue'
import LoadingSpinner from '@/Components/Common/LoadingSpinner.vue'
import { useApi } from '@/Composables/useApi'
import { usePlatform } from '@/Composables/usePlatform'

const { get, loading } = useApi()
const { getPlatformColor } = usePlatform()

const currentDate    = ref(new Date())
const view           = ref('month')
const scheduledPosts = ref([])
const selectedPost   = ref(null)

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const statusColors = {
  scheduled:          'bg-blue-100 text-blue-700',
  published:          'bg-green-100 text-green-700',
  partially_published: 'bg-orange-100 text-orange-700',
  failed:             'bg-red-100 text-red-700',
  publishing:         'bg-yellow-100 text-yellow-700',
}

const monthLabel = computed(() => format(currentDate.value, 'MMMM yyyy'))

const calendarCells = computed(() => {
  const start   = startOfWeek(startOfMonth(currentDate.value))
  const end     = endOfWeek(endOfMonth(currentDate.value))
  const days    = eachDayOfInterval({ start, end })
  const MAX_PER_CELL = 3

  return days.map(day => {
    const dayPosts = scheduledPosts.value.filter(p => isSameDay(new Date(p.start), day))
    return {
      date:           format(day, 'yyyy-MM-dd'),
      day:            day.getDate(),
      isCurrentMonth: isSameMonth(day, currentDate.value),
      isToday:        isToday(day),
      posts:          dayPosts.slice(0, MAX_PER_CELL),
      overflow:       Math.max(0, dayPosts.length - MAX_PER_CELL),
    }
  })
})

async function fetchCalendar() {
  const start = format(startOfWeek(startOfMonth(currentDate.value)), 'yyyy-MM-dd')
  const end   = format(endOfWeek(endOfMonth(currentDate.value)), 'yyyy-MM-dd')
  scheduledPosts.value = await get('calendar', { start, end })
}

onMounted(fetchCalendar)
watch(currentDate, fetchCalendar)

function prevMonth() { currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() - 1, 1) }
function nextMonth() { currentDate.value = new Date(currentDate.value.getFullYear(), currentDate.value.getMonth() + 1, 1) }
function goToToday() { currentDate.value = new Date() }
</script>
