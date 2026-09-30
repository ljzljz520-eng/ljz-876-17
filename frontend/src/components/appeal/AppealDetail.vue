<template>
  <div v-if="appeal" class="space-y-6">
    <!-- 基本信息 -->
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
      <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
          <h2 class="text-lg font-bold text-gray-900">
            {{ appeal.exam_record?.exam_paper?.title || `考试记录 #${appeal.exam_record_id}` }}
          </h2>
          <p v-if="showStudent && appeal.student" class="text-sm text-gray-500 mt-1">
            申诉学生：{{ appeal.student.real_name || appeal.student.username }}
          </p>
        </div>
        <span class="px-3 py-1 inline-flex text-xs font-semibold rounded-full" :class="statusInfo(appeal.status).badge">
          {{ statusInfo(appeal.status).label }}
        </span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
          <span class="text-gray-500">申诉类型：</span>
          <span class="font-medium text-gray-900">{{ typeLabel(appeal.type) }}</span>
        </div>
        <div>
          <span class="text-gray-500">申诉题目：</span>
          <span class="font-medium text-gray-900">{{ appeal.question ? appeal.question.title.slice(0, 40) : '整份试卷' }}</span>
        </div>
        <div>
          <span class="text-gray-500">提交时间：</span>
          <span class="font-medium text-gray-900">{{ formatTime(appeal.submitted_at || appeal.created_at) }}</span>
        </div>
        <div v-if="appeal.closed_at">
          <span class="text-gray-500">完成时间：</span>
          <span class="font-medium text-gray-900">{{ formatTime(appeal.closed_at) }}</span>
        </div>
      </div>

      <div class="bg-gray-50 rounded-lg p-4">
        <p class="text-xs font-semibold text-gray-500 mb-1">申诉原因</p>
        <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ appeal.reason }}</p>
      </div>
    </div>

    <!-- 证据材料 -->
    <div class="bg-white rounded-xl shadow p-6">
      <h3 class="text-base font-bold text-gray-900 mb-3">证据材料（{{ appeal.evidences?.length || 0 }}）</h3>
      <p v-if="!appeal.evidences || appeal.evidences.length === 0" class="text-sm text-gray-400">未上传证据</p>
      <ul v-else class="divide-y divide-gray-100">
        <li v-for="file in appeal.evidences" :key="file.id" class="flex items-center justify-between py-3">
          <div class="flex items-center gap-3 min-w-0">
            <svg class="h-8 w-8 text-indigo-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ file.original_name }}</p>
              <p class="text-xs text-gray-400">{{ formatFileSize(file.size) }}</p>
            </div>
          </div>
          <button
            class="ml-4 text-sm font-medium text-indigo-600 hover:text-indigo-800 flex-shrink-0"
            @click="$emit('download', file)"
          >
            下载
          </button>
        </li>
      </ul>
    </div>

    <!-- 处理轨迹 -->
    <div class="bg-white rounded-xl shadow p-6">
      <h3 class="text-base font-bold text-gray-900 mb-4">复核处理轨迹</h3>
      <ol class="relative border-l-2 border-gray-100 ml-2 space-y-6">
        <li v-for="(review, idx) in appeal.reviews" :key="review.id" class="ml-6">
          <span
            class="absolute -left-[11px] flex items-center justify-center w-5 h-5 rounded-full ring-4 ring-white"
            :class="dotClass(review.action)"
          >
            <span class="w-2 h-2 rounded-full bg-white"></span>
          </span>
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="actionInfo(review.action).badge">
              {{ actionInfo(review.action).label }}
            </span>
            <span class="text-sm font-semibold text-gray-800">
              {{ review.handler?.real_name || review.handler?.username }}
              <span class="text-gray-400 font-normal">（{{ roleLabel(review.handler_role) }}）</span>
            </span>
            <span class="text-xs text-gray-400">{{ formatTime(review.created_at) }}</span>
          </div>
          <p v-if="review.comment" class="mt-1.5 text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded-lg px-3 py-2">{{ review.comment }}</p>
          <div v-if="review.action === 'transfer' && review.to_user" class="mt-1 text-xs text-blue-600">
            转交给：{{ review.to_user.real_name || review.to_user.username }}（{{ roleLabel(review.to_user.role) }}）
          </div>
          <div v-if="scoreChanged(review)" class="mt-1.5 flex items-center gap-2 text-sm">
            <span class="text-gray-500">成绩：{{ review.score_before }} 分</span>
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
            <span class="font-bold" :class="Number(review.score_adjustment) > 0 ? 'text-emerald-600' : 'text-red-600'">{{ review.score_after }} 分</span>
            <span class="text-xs text-gray-400">（{{ Number(review.score_adjustment) > 0 ? '+' : '' }}{{ review.score_adjustment }}）</span>
          </div>
        </li>
      </ol>

      <div v-if="appeal.status === 'closed'" class="mt-5 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3 flex items-center gap-2">
        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-medium text-emerald-800">本申诉已复核完成，最终成绩：{{ currentScore }} 分</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { statusInfo, typeLabel, actionInfo, roleLabel, formatFileSize } from '../../utils/appeal'

const props = defineProps({
  appeal: { type: Object, default: null },
  showStudent: { type: Boolean, default: false }
})

defineEmits(['download'])

const currentScore = computed(() => {
  const reviews = props.appeal?.reviews || []
  for (let i = reviews.length - 1; i >= 0; i--) {
    if (reviews[i].score_after !== null && reviews[i].score_after !== undefined) {
      return reviews[i].score_after
    }
  }
  return props.appeal?.exam_record?.score ?? '-'
})

const formatTime = (t) => (t ? new Date(t).toLocaleString() : '-')

const scoreChanged = (review) => {
  return review.score_before !== null && review.score_before !== undefined &&
    review.score_after !== null && review.score_after !== undefined &&
    Number(review.score_adjustment) !== 0
}

const dotClass = (action) => {
  switch (action) {
    case 'submit': return 'bg-gray-400'
    case 'transfer': return 'bg-blue-500'
    case 'add_score': return 'bg-emerald-500'
    case 'deduct_score': return 'bg-red-500'
    default: return 'bg-gray-500'
  }
}
</script>
