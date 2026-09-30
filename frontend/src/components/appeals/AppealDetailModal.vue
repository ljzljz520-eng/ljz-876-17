<template>
  <Teleport to="body">
    <Transition
      enter-active-class="ease-out duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="ease-in duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div v-if="show" class="fixed inset-0 z-[110]" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm" @click="close"></div>
        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-start justify-center p-4 sm:py-8">
            <div class="relative w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-gray-100">
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">申诉详情与复核轨迹</h3>
                <button class="text-gray-400 hover:text-gray-600 p-1" @click="close">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div v-if="loading" class="py-16 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
              </div>

              <div v-else-if="data" class="px-6 py-5 space-y-5 max-h-[70vh] overflow-y-auto">
                <!-- 概览 -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                  <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-400 mb-1">申诉类型</div>
                    <div class="font-semibold text-gray-800">{{ data.appeal_type_text }}</div>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-400 mb-1">当前状态</div>
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="statusBadge(data.status)">
                      {{ data.status_text }}
                    </span>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-400 mb-1">最终结论</div>
                    <div class="font-semibold" :class="resultClass(data.final_result)">
                      {{ data.final_result_text || '—' }}
                    </div>
                  </div>
                  <div class="bg-gray-50 rounded-lg p-3">
                    <div class="text-xs text-gray-400 mb-1">成绩变化</div>
                    <div class="font-semibold" :class="adjustClass">
                      {{ scoreChangeText }}
                    </div>
                  </div>
                </div>

                <!-- 学生与题目 -->
                <div class="text-sm space-y-2">
                  <div class="text-gray-500">
                    申诉学生：<span class="font-semibold text-gray-800">{{ data.student?.real_name || data.student?.username }}</span>
                  </div>
                  <div class="text-gray-500">
                    申诉试卷：<span class="font-semibold text-gray-800">{{ data.exam_paper?.title }}</span>
                  </div>
                  <div class="text-gray-500">
                    申诉题目：
                    <span v-if="data.question" class="font-medium text-gray-800">{{ data.question.title }}</span>
                    <span v-else class="font-medium text-gray-800">整卷申诉</span>
                  </div>
                </div>

                <!-- 作答情况 -->
                <div v-if="data.appealed_answer" class="text-sm bg-amber-50/60 border border-amber-100 rounded-lg p-3 space-y-1">
                  <div class="text-gray-600">学生作答：<span class="font-medium text-gray-800">{{ data.appealed_answer.answer || '（未作答）' }}</span></div>
                  <div class="text-gray-600">本题得分：<span class="font-semibold">{{ data.appealed_answer.score }}</span></div>
                  <div class="text-gray-600">正确答案：<span class="font-medium text-green-700">{{ data.appealed_answer.correct_answer }}</span></div>
                  <div v-if="data.appealed_answer.analysis" class="text-gray-500 text-xs mt-1">解析：{{ data.appealed_answer.analysis }}</div>
                </div>

                <!-- 原因 -->
                <div>
                  <h4 class="text-sm font-bold text-gray-800 mb-2">申诉原因</h4>
                  <p class="text-sm text-gray-600 whitespace-pre-wrap break-words bg-red-50/50 border border-red-100 rounded-lg p-3">{{ data.reason }}</p>
                </div>

                <!-- 证据 -->
                <div>
                  <h4 class="text-sm font-bold text-gray-800 mb-2">证据附件（{{ data.evidences.length }}）</h4>
                  <div v-if="data.evidences.length === 0" class="text-sm text-gray-400">未上传证据</div>
                  <ul v-else class="space-y-1.5">
                    <li v-for="ev in data.evidences" :key="ev.id"
                      class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm">
                      <span class="flex items-center min-w-0">
                        <svg class="w-4 h-4 mr-2 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span class="truncate text-gray-700">{{ ev.original_name }}</span>
                        <span class="ml-2 text-xs text-gray-400 flex-shrink-0">{{ formatBytes(ev.size) }}</span>
                      </span>
                      <button class="text-indigo-600 hover:text-indigo-500 text-xs font-semibold ml-3 flex-shrink-0"
                        @click="download(ev)">下载</button>
                    </li>
                  </ul>
                </div>

                <!-- 复核轨迹 -->
                <AppealTimeline :reviews="data.reviews" />

                <!-- 转教务后普通教师只读提示 -->
                <div v-if="canReview && isAcademicOnly"
                  class="border-t border-gray-100 pt-4 flex items-center gap-2 text-sm text-purple-700 bg-purple-50 rounded-lg px-3 py-2.5">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  该申诉已转教务处理，教师仅可查看轨迹。
                </div>

                <!-- 已结案只读提示 -->
                <div v-if="canReview && isClosed"
                  class="border-t border-gray-100 pt-4 flex items-center gap-2 text-sm text-teal-700 bg-teal-50 rounded-lg px-3 py-2.5">
                  <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  该申诉已复核完成并记录到成绩单，无法再次处理。
                </div>

                <!-- 教师/教务操作区（结案后只读） -->
                <div v-if="canReview && !isAcademicOnly && !isClosed" class="border-t border-gray-100 pt-4">
                  <h4 class="text-sm font-bold text-gray-800 mb-3">复核操作</h4>

                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                    <button v-if="!isAcademicOnly"
                      v-for="(label, key) in availableActions" :key="key"
                      type="button"
                      class="px-3 py-2 text-sm rounded-lg border transition-all"
                      :class="reviewForm.action === key
                        ? actionActiveClass(key)
                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'"
                      @click="setAction(key)">
                      {{ label }}
                    </button>
                  </div>

                  <div v-if="requiresAdjustment" class="mb-3">
                    <label class="block text-xs font-semibold text-gray-600 mb-1">
                      {{ reviewForm.action === 'add' ? '加分分值（正数）' : '减分分值（正数）' }}
                    </label>
                    <div class="flex items-center gap-2">
                      <input type="number" min="0.5" step="0.5"
                        v-model.number="reviewForm.absAmount"
                        class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="如 2" />
                      <span class="text-sm text-gray-500">分</span>
                      <span class="text-xs text-gray-400">
                        {{ data.question ? '按单题得分调整并重算总分' : '按整卷总分调整' }}
                      </span>
                    </div>
                    <p v-if="reviewErrors.score_adjustment" class="text-xs text-red-500 mt-1">{{ reviewErrors.score_adjustment[0] }}</p>
                  </div>

                  <textarea v-model="reviewForm.comment" rows="3" maxlength="1000"
                    placeholder="请填写复核意见（必填，将与处理结果一起留档）"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm resize-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                  <p v-if="reviewErrors.comment" class="text-xs text-red-500 mt-1">{{ reviewErrors.comment[0] }}</p>

                  <div class="flex justify-end mt-3">
                    <button class="px-5 py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50 flex items-center"
                      :class="submitActionClass"
                      :disabled="submitting"
                      @click="submitReview">
                      <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                      </svg>
                      确认{{ REVIEW_ACTIONS[reviewForm.action] || '复核' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import api from '../../api'
import AppealTimeline from './AppealTimeline.vue'
import { APPEAL_TYPES, REVIEW_ACTIONS, formatBytes, downloadEvidence } from '../../utils/appeal'

const props = defineProps({
  show: { type: Boolean, default: false },
  appealId: { type: [Number, String], default: null },
  // 权限：是否可执行复核操作
  canReview: { type: Boolean, default: false },
  // 当前登录用户角色
  role: { type: String, default: 'student' }
})
const emit = defineEmits(['close', 'reviewed'])

const data = ref(null)
const loading = ref(false)
const submitting = ref(false)
const reviewErrors = ref({})

const reviewForm = reactive({
  action: 'maintain',
  comment: '',
  absAmount: null
})

const isAdmin = computed(() => props.role === 'admin')
// 已结案：仅保留轨迹查看
const isClosed = computed(() => data.value?.status === 'completed')
// 转教务状态的单子只有教务能处理；普通老师看到时只读
const isAcademicOnly = computed(() => data.value?.status === 'to_academic' && !isAdmin.value)

const availableActions = computed(() => {
  // 教务为最终环节：不能再转教务
  if (isAdmin.value) {
    return { maintain: REVIEW_ACTIONS.maintain, add: REVIEW_ACTIONS.add, deduct: REVIEW_ACTIONS.deduct }
  }
  return REVIEW_ACTIONS
})

const requiresAdjustment = computed(() =>
  reviewForm.action === 'add' || reviewForm.action === 'deduct'
)

const scoreChangeText = computed(() => {
  if (!data.value?.final_result) return '—'
  if (data.value.final_result === 'maintain') return '维持不变'
  if (data.value.score_adjustment === null || data.value.score_adjustment === undefined) return '—'
  const v = Number(data.value.score_adjustment)
  return `${v > 0 ? '+' : ''}${v} 分 → ${data.value.final_score} 分`
})

const adjustClass = computed(() => {
  const v = Number(data.value?.score_adjustment || 0)
  if (v > 0) return 'text-green-600'
  if (v < 0) return 'text-red-600'
  return 'text-gray-800'
})

const resultClass = (r) => {
  if (r === 'add') return 'text-green-600'
  if (r === 'deduct') return 'text-red-600'
  if (r === 'transfer') return 'text-amber-600'
  return 'text-gray-800'
}

const statusBadge = (s) => {
  if (s === 'completed') return 'bg-green-100 text-green-700'
  if (s === 'to_academic') return 'bg-amber-100 text-amber-700'
  return 'bg-blue-100 text-blue-700'
}

const actionActiveClass = (key) => {
  switch (key) {
    case 'add': return 'border-green-500 bg-green-50 text-green-700 font-semibold'
    case 'deduct': return 'border-red-500 bg-red-50 text-red-700 font-semibold'
    case 'transfer': return 'border-amber-500 bg-amber-50 text-amber-700 font-semibold'
    default: return 'border-gray-500 bg-gray-100 text-gray-800 font-semibold'
  }
}

const submitActionClass = computed(() => {
  switch (reviewForm.action) {
    case 'add': return 'bg-green-600 hover:bg-green-500'
    case 'deduct': return 'bg-red-600 hover:bg-red-500'
    case 'transfer': return 'bg-amber-600 hover:bg-amber-500'
    default: return 'bg-indigo-600 hover:bg-indigo-500'
  }
})

const setAction = (key) => {
  reviewForm.action = key
  reviewErrors.value = {}
}

watch(() => props.show, async (v) => {
  if (v && props.appealId) {
    await load()
  } else if (!v) {
    data.value = null
  }
})

const load = async () => {
  loading.value = true
  reviewErrors.value = {}
  Object.assign(reviewForm, { action: 'maintain', comment: '', absAmount: null })
  try {
    const res = await api.get(`/score-appeals/${props.appealId}`)
    data.value = res.data.appeal
  } finally {
    loading.value = false
  }
}

const download = async (ev) => {
  try {
    await downloadEvidence(ev.id, ev.original_name)
  } catch (e) {
    // 全局拦截器已提示
  }
}

const close = () => emit('close')

const submitReview = async () => {
  reviewErrors.value = {}
  const payload = {
    action: reviewForm.action,
    comment: reviewForm.comment
  }
  if (requiresAdjustment.value) {
    const abs = Math.abs(Number(reviewForm.absAmount) || 0)
    if (abs <= 0) {
      reviewErrors.value.score_adjustment = ['请填写大于 0 的调整分值']
      return
    }
    // 减分提交负数，加分提交正数（与后端校验一致）
    payload.score_adjustment = reviewForm.action === 'deduct' ? -abs : abs
  }

  submitting.value = true
  try {
    const res = await api.post(`/score-appeals/${props.appealId}/review`, payload, { skipGlobalError: true })
    data.value = res.data.appeal
    emit('reviewed', res.data.appeal)
  } catch (e) {
    if (e.response?.data?.errors) {
      reviewErrors.value = e.response.data.errors
    } else if (e.response?.data?.message) {
      reviewErrors.value = { comment: [e.response.data.message] }
    }
  } finally {
    submitting.value = false
  }
}
</script>
