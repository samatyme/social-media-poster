<template>
  <div class="min-h-screen flex bg-gray-50">
    <!-- Sidebar -->
    <aside
      :class="['fixed inset-y-0 left-0 z-40 flex flex-col bg-white border-r border-gray-200 transition-all duration-300',
               sidebarOpen ? 'w-64' : 'w-16']"
    >
      <!-- Logo -->
      <div class="flex items-center h-16 px-4 border-b border-gray-200">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
            <span class="text-white font-bold text-sm">SP</span>
          </div>
          <span v-if="sidebarOpen" class="font-semibold text-gray-900 truncate">Social Poster</span>
        </div>
      </div>

      <!-- Navigation -->
      <nav class="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
        <NavItem v-for="item in navItems" :key="item.href" :item="item" :collapsed="!sidebarOpen" />
      </nav>

      <!-- User info -->
      <div class="border-t border-gray-200 p-3">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
            <span class="text-blue-700 text-sm font-medium">{{ userInitials }}</span>
          </div>
          <div v-if="sidebarOpen" class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900 truncate">{{ $page.props.auth.user?.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ $page.props.auth.user?.role }}</p>
          </div>
          <button v-if="sidebarOpen" @click="logout" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
            <LogOut class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Toggle button -->
      <button
        @click="sidebarOpen = !sidebarOpen"
        class="absolute -right-3 top-20 bg-white border border-gray-200 rounded-full w-6 h-6 flex items-center justify-center shadow-sm hover:shadow-md transition-shadow"
      >
        <ChevronLeft v-if="sidebarOpen" class="w-3 h-3 text-gray-600" />
        <ChevronRight v-else class="w-3 h-3 text-gray-600" />
      </button>
    </aside>

    <!-- Main content -->
    <div :class="['flex-1 flex flex-col min-h-screen transition-all duration-300', sidebarOpen ? 'ml-64' : 'ml-16']">
      <!-- Top bar -->
      <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-30">
        <div>
          <h1 class="text-lg font-semibold text-gray-900">{{ pageTitle }}</h1>
        </div>
        <div class="flex items-center gap-3">
          <Link href="/posts/create">
            <button class="btn-primary btn-sm">
              <Plus class="w-4 h-4" />
              New Post
            </button>
          </Link>
        </div>
      </header>

      <!-- Page content -->
      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>

    <!-- Toast container -->
    <ToastContainer />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import {
  LayoutDashboard, FileText, Calendar, Image, Share2,
  Users, Settings, Plus, LogOut, ChevronLeft, ChevronRight
} from 'lucide-vue-next'
import NavItem from '@/Components/Common/NavItem.vue'
import ToastContainer from '@/Components/Common/ToastContainer.vue'

const page = usePage()
const sidebarOpen = ref(true)

const navItems = [
  { href: '/dashboard',  label: 'Dashboard',    icon: LayoutDashboard },
  { href: '/posts',      label: 'Posts',         icon: FileText },
  { href: '/calendar',   label: 'Calendar',      icon: Calendar },
  { href: '/media',      label: 'Media Library', icon: Image },
  { href: '/accounts',   label: 'Social Accounts', icon: Share2 },
  { href: '/team',       label: 'Team',          icon: Users },
  { href: '/settings',   label: 'Settings',      icon: Settings },
]

const pageTitle = computed(() => {
  const path = page.url
  const item = navItems.find(i => path.startsWith(i.href))
  return item?.label ?? 'Social Media Poster'
})

const userInitials = computed(() => {
  const name = page.props.auth.user?.name ?? ''
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function logout() {
  router.post('/api/auth/logout', {}, {
    onSuccess: () => router.visit('/login')
  })
}
</script>
