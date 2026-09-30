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
                <div>
                  <h3 class="text-lg font-bold text-gray-900">{{ record?.exam_paper?.title }} · 成绩详情</h3>
                  <p class="text-xs text-gray-400 mt-0.5">
                    总分：<span class="font-bold text-indigo-600">{{ record?.score }}</span> / {{ record?.exam_paper?.total_score }}
                  </p>
                </div>
                <button class="text-gray-400 hover:text-gray-600 p-1" @click="close">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div class="px-6 py-5 max-h-[70vh] overflow-y-auto space-y-4">
                <div v-if="record?.appeal_summary?.review_status"
                  class="flex items-center gap-2 text-sm rounded-lg px-3 py-2"
                  :class="record.appeal_summary.review_status === 'completed'
                    ? 'bg-teal-50 text-teal-700' : 'bg-amber-50 text-amber-700'">
                  <svg v-if="record.appeal_summary.review_status === 'completed'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <span class="font-semibold">
                    {{ record.appeal_summary.review_status === 'completed' ? '复核完成' : '复核中' }}
                  </span>
                  <span class="text-xs">（共 {{ record.appeal_summary.appeal_count }} 条申诉）</span>
                  <router-link v-if="record.appeal_summary.has_appeal" to="/my-appeals"
                    class="ml-auto text-indigo-600 font-semibold hover:underline" @click="close">
                    查看复核轨迹 →
                  </router-link>
                </div>

                <div v-for="(ans, idx) in record?.answers" :key="ans.id"
                  class="border border-gray-100 rounded-xl p-4">
                  <div class="flex items-start justify-between gap-3">
                    <div class="text-sm font-semibold text-gray-800">
                      {{ idx + 1 }}. {{ ans.question?.title }}
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0"
                      :class="ans.is_correct ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                      {{ ans.is_correct ? '正确' : '错误' }}
                    </span>
                  </div>
                  <div class="mt-2 space-y-1 text-sm">
                    <div class="text-gray-600">你的答案：
                      <span :class="ans.is_correct ? 'text-green-600 font-medium' : 'text-red-600 font-medium'">
                        {{ ans.answer || '（未作答）' }}
                      </span>
                    </div>
                    <div class="text-gray-600">正确答案：<span class="text-green-700 font-medium">{{ ans.question?.answer }}</span></div>
                    <div class="text-gray-500 text-xs">本题得分：{{ ans.score }}</div>
                  </div>
                </div>
              </div>

              <div class="px-6 py-4 bg-gray-50/60 rounded-b-2xl flex justify-end gap-3">
                <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white rounded-lg ring-1 ring-gray-300 hover:bg-gray-50"
                  @click="close">关闭</button>
                <button v-if="canAppeal" type="button"
                  class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 rounded-lg hover:bg-rose-500"
                  @click="$emit('appeal')">对此成绩申诉</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
defineProps({
  show: { type: Boolean, default: false },
  record: { type: Object, default: null },
  canAppeal: { type: Boolean, default: false }
})
const emit = defineEmits(['close', 'appeal'])
const close = () => emit('close')
</script>
