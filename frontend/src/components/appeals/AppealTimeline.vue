<template>
  <div>
    <h4 class="text-sm font-bold text-gray-800 mb-3 flex items-center">
      <svg class="w-4 h-4 mr-1.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      复核轨迹
    </h4>
    <div v-if="reviews.length === 0" class="text-sm text-gray-400 py-3">
      暂无处理记录，等待老师复核。
    </div>
    <ol v-else class="relative border-l-2 border-gray-200 ml-2 space-y-5">
      <li v-for="(r, idx) in [...reviews].reverse()" :key="r.id" class="ml-5">
        <span
          class="absolute -left-[9px] flex items-center justify-center w-4 h-4 rounded-full ring-4 ring-white"
          :class="actionDotClass(r.action)"
        ></span>
        <div class="bg-gray-50 rounded-lg p-3">
          <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="font-semibold text-gray-800">{{ r.reviewer?.real_name || r.reviewer?.username || '处理人' }}</span>
            <span class="px-1.5 py-0.5 text-xs rounded" :class="roleBadgeClass(r.reviewer?.role)">
              {{ r.reviewer?.role_text || '教师' }}
            </span>
            <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="actionBadgeClass(r.action)">
              {{ r.action_text }}
            </span>
            <span v-if="r.score_adjustment !== null && r.score_adjustment !== undefined && Number(r.score_adjustment) !== 0"
              class="text-xs font-semibold"
              :class="Number(r.score_adjustment) > 0 ? 'text-green-600' : 'text-red-600'">
              {{ Number(r.score_adjustment) > 0 ? '+' : '' }}{{ r.score_adjustment }} 分
            </span>
            <span v-if="r.score_after !== null && r.score_after !== undefined" class="text-xs text-gray-400">
              处理后总分 {{ r.score_after }}
            </span>
          </div>
          <p class="mt-2 text-sm text-gray-600 whitespace-pre-wrap break-words">{{ r.comment || '（未填写意见）' }}</p>
          <div class="mt-1.5 text-xs text-gray-400">{{ formatTime(r.created_at) }}</div>
        </div>
      </li>
    </ol>
  </div>
</template>

<script setup>
defineProps({
  reviews: { type: Array, default: () => [] }
})

const formatTime = (t) => (t ? new Date(t).toLocaleString() : '')

const actionBadgeClass = (action) => {
  switch (action) {
    case 'add': return 'bg-green-100 text-green-700'
    case 'deduct': return 'bg-red-100 text-red-700'
    case 'transfer': return 'bg-amber-100 text-amber-700'
    default: return 'bg-gray-200 text-gray-700'
  }
}

const actionDotClass = (action) => {
  switch (action) {
    case 'add': return 'bg-green-500'
    case 'deduct': return 'bg-red-500'
    case 'transfer': return 'bg-amber-500'
    default: return 'bg-gray-400'
  }
}

const roleBadgeClass = (role) => {
  return role === 'admin'
    ? 'bg-purple-100 text-purple-700'
    : 'bg-blue-100 text-blue-700'
}
</script>
