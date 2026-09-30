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
        <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm" @click="cancel"></div>
        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-start justify-center p-4 sm:py-10">
            <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl border border-gray-100">
              <!-- Header -->
              <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                  <h3 class="text-lg font-bold text-gray-900">提交成绩申诉</h3>
                  <p class="text-xs text-gray-400 mt-0.5">{{ record?.exam_paper?.title }} · 当前得分 {{ record?.score }} 分</p>
                </div>
                <button class="text-gray-400 hover:text-gray-600 p-1" @click="cancel">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <!-- Body -->
              <div class="px-6 py-5 space-y-5">
                <!-- 选择题目 -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    申诉题目 <span class="text-red-500">*</span>
                  </label>
                  <select v-model="form.question_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option :value="null">整卷申诉（不针对具体题目）</option>
                    <option v-for="ans in answerOptions" :key="ans.question_id" :value="ans.question_id">
                    第{{ questionOrder(ans.question_id) }}题：{{ ans.question?.title }}
                    </option>
                  </select>
                  <p v-if="selectedAnswer" class="mt-2 text-xs text-gray-500 bg-gray-50 rounded-lg p-2.5">
                    你的作答：
                    <span class="font-medium" :class="selectedAnswer.is_correct ? 'text-green-600' : 'text-red-600'">
                      {{ selectedAnswer.answer || '（未作答）' }}
                    </span>
                    ｜本题得分：<span class="font-semibold">{{ selectedAnswer.score }} 分</span>
                  </p>
                </div>

                <!-- 申诉类型 -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    申诉类型 <span class="text-red-500">*</span>
                  </label>
                  <div class="grid grid-cols-3 gap-2">
                    <button
                      v-for="(label, key) in APPEAL_TYPES"
                      :key="key"
                      type="button"
                      class="px-3 py-2 text-sm rounded-lg border transition-all"
                      :class="form.appeal_type === key
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 font-semibold'
                        : 'border-gray-300 text-gray-600 hover:border-indigo-300'"
                      @click="form.appeal_type = key"
                    >{{ label }}</button>
                  </div>
                </div>

                <!-- 原因 -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    申诉原因 <span class="text-red-500">*</span>
                  </label>
                  <textarea
                    v-model="form.reason"
                    rows="4"
                    maxlength="1000"
                    placeholder="请详细说明你对分数、判题或异常标记有异议的理由（至少 5 个字）"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                  ></textarea>
                  <div class="text-right text-xs text-gray-400 mt-1">{{ form.reason.length }}/1000</div>
                  <p v-if="errors.reason" class="text-xs text-red-500 mt-1">{{ errors.reason[0] }}</p>
                </div>

                <!-- 证据上传 -->
                <div>
                  <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                    上传证据 <span class="text-gray-400 font-normal">（可选，最多 5 个文件，单个不超过 10MB，支持 jpg/png/pdf/doc/docx/txt/zip）</span>
                  </label>
                  <label class="flex flex-col items-center justify-center border-2 border-dashed border-gray-300 rounded-lg py-6 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/30 transition-colors">
                    <svg class="w-8 h-8 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <span class="text-sm text-gray-500">点击选择文件（可多选）</span>
                    <input ref="fileInput" type="file" multiple class="hidden"
                      accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.txt,.zip"
                      @change="onFilePick" />
                  </label>
                  <ul v-if="form.evidences.length" class="mt-2 space-y-1.5">
                    <li v-for="(f, i) in form.evidences" :key="i"
                      class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm">
                      <span class="flex items-center text-gray-700 truncate">
                        <svg class="w-4 h-4 mr-2 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <span class="truncate">{{ f.name }}</span>
                        <span class="ml-2 text-xs text-gray-400 flex-shrink-0">{{ formatBytes(f.size) }}</span>
                      </span>
                      <button type="button" class="text-gray-400 hover:text-red-500 ml-2 flex-shrink-0" @click="removeFile(i)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </li>
                  </ul>
                  <p v-if="errors.evidences" class="text-xs text-red-500 mt-1">{{ errors.evidences[0] }}</p>
                </div>
              </div>

              <!-- Footer -->
              <div class="px-6 py-4 bg-gray-50/60 rounded-b-2xl flex justify-end gap-3">
                <button type="button" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white rounded-lg ring-1 ring-gray-300 hover:bg-gray-50"
                  @click="cancel">取消</button>
                <button type="button"
                  class="px-5 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500 disabled:opacity-50 flex items-center"
                  :disabled="submitting"
                  @click="submit">
                  <svg v-if="submitting" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  提交申诉
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import api from '../../api'
import { APPEAL_TYPES, formatBytes } from '../../utils/appeal'

const props = defineProps({
  show: { type: Boolean, default: false },
  record: { type: Object, default: null }
})
const emit = defineEmits(['close', 'submitted'])

const fileInput = ref(null)
const submitting = ref(false)
const errors = ref({})

const form = ref({
  question_id: null,
  appeal_type: 'score',
  reason: '',
  evidences: []
})

const answerOptions = computed(() => props.record?.answers || [])

const questionOrder = (questionId) => {
  const questions = props.record?.exam_paper?.questions || []
  const idx = questions.findIndex(q => q.id === questionId)
  return idx >= 0 ? idx + 1 : '?'
}

const selectedAnswer = computed(() =>
  form.value.question_id
    ? answerOptions.value.find(a => a.question_id === form.value.question_id)
    : null
)

watch(() => props.show, (v) => {
  if (v) {
    form.value = { question_id: null, appeal_type: 'score', reason: '', evidences: [] }
    errors.value = {}
  }
})

const onFilePick = (e) => {
  const picked = Array.from(e.target.files || [])
  const next = [...form.value.evidences, ...picked].slice(0, 5)
  form.value.evidences = next
  if (fileInput.value) fileInput.value.value = ''
}

const removeFile = (i) => form.value.evidences.splice(i, 1)

const cancel = () => {
  if (submitting.value) return
  emit('close')
}

const submit = async () => {
  errors.value = {}
  if (!form.value.reason.trim() || form.value.reason.trim().length < 5) {
    errors.value.reason = ['申诉原因至少 5 个字符']
    return
  }

  const fd = new FormData()
  fd.append('exam_record_id', props.record.id)
  if (form.value.question_id) fd.append('question_id', form.value.question_id)
  fd.append('appeal_type', form.value.appeal_type)
  fd.append('reason', form.value.reason)
  form.value.evidences.forEach(f => fd.append('evidences[]', f))

  submitting.value = true
  try {
    const res = await api.post('/score-appeals', fd, { skipGlobalError: true })
    emit('submitted', res.data.appeal)
    emit('close')
  } catch (e) {
    if (e.response?.data?.errors) {
      errors.value = e.response.data.errors
    } else if (e.response?.data?.message) {
      errors.value = { reason: [e.response.data.message] }
    }
  } finally {
    submitting.value = false
  }
}
</script>
