<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">我的成绩</h1>
      <router-link v-if="authStore.user?.role === 'student'" to="/appeals" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
        我的申诉 →
      </router-link>
    </div>
    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="records.length === 0" class="text-center py-8 text-gray-500">
      暂无考试记录
    </div>
    <div v-else class="bg-white shadow overflow-hidden sm:rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">试卷</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">得分</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">复核</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">考试时间</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap">{{ record.exam_paper?.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap font-bold" :class="{'text-green-600': record.score >= 60, 'text-red-600': record.score < 60}">{{ record.score }} 分</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ record.status === 'graded' ? '已评分' : record.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span v-if="reviewInfo(record)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="reviewInfo(record).badge">
                {{ reviewInfo(record).label }}
              </span>
              <span v-else class="text-gray-400 text-xs">—</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(record.created_at).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <button
                v-if="canAppeal(record)"
                @click="openAppealModal(record)"
                class="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
              >
                成绩申诉
              </button>
              <button
                v-if="record.latest_appeal"
                @click="goAppeal(record.latest_appeal.id)"
                class="ml-3 text-gray-600 hover:text-gray-900 text-sm font-medium"
              >
                查看轨迹
              </button>
              <span v-if="!canAppeal(record) && !record.latest_appeal" class="text-gray-400 text-sm">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 发起申诉模态框 -->
    <Teleport to="body">
      <Transition
        enter-active-class="ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="showAppealModal" class="fixed inset-0 z-50 overflow-y-auto">
          <div class="fixed inset-0 bg-gray-600/75 backdrop-blur-sm" @click="closeAppealModal"></div>
          <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-2xl transform overflow-hidden rounded-2xl bg-white shadow-2xl border border-gray-100">
              <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">发起成绩申诉</h3>
                <button @click="closeAppealModal" class="text-gray-400 hover:text-gray-500 bg-white rounded-full p-1 hover:bg-gray-100">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <div class="px-6 py-6 max-h-[calc(100vh-14rem)] overflow-y-auto custom-scrollbar">
                <div v-if="detailLoading" class="text-center py-8">
                  <div class="animate-spin rounded-full h-7 w-7 border-b-2 border-indigo-600 mx-auto"></div>
                  <p class="mt-2 text-sm text-gray-500">加载成绩详情...</p>
                </div>
                <div v-else class="space-y-5">
                  <div class="bg-indigo-50 rounded-xl p-4 text-sm text-indigo-900">
                    <span class="font-semibold">{{ appealForm.record?.exam_paper?.title }}</span>
                    ｜当前成绩：<span class="font-bold">{{ appealForm.record?.score }} 分</span>
                  </div>

                  <!-- 申诉类型 -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉类型 <span class="text-red-500">*</span></label>
                    <div class="grid grid-cols-3 gap-3">
                      <button
                        v-for="(label, value) in typeOptions"
                        :key="value"
                        type="button"
                        @click="appealForm.type = value"
                        class="px-3 py-2 rounded-lg border text-sm font-medium transition-all"
                        :class="appealForm.type === value
                          ? 'border-indigo-500 bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500'
                          : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                      >
                        {{ label }}
                      </button>
                    </div>
                  </div>

                  <!-- 选择题目 -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉题目 <span class="text-gray-400 font-normal">（不选为整体申诉）</span></label>
                    <select v-model="appealForm.question_id" class="input-base">
                      <option :value="null">整份试卷（不针对具体题目）</option>
                      <option v-for="(q, idx) in appealQuestions" :key="q.id" :value="q.id">
                        第{{ idx + 1 }}题：{{ q.title.length > 40 ? q.title.slice(0, 40) + '…' : q.title }}（作答得 {{ q.answer_score ?? 0 }}/{{ q.paper_score }} 分）
                      </option>
                    </select>
                  </div>

                  <!-- 申诉原因 -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">申诉原因 <span class="text-red-500">*</span></label>
                    <textarea
                      v-model="appealForm.reason"
                      rows="4"
                      class="input-base resize-y"
                      placeholder="请详细说明申诉理由，例如判题错误、分数统计异常、考试过程中出现异常标记等（至少 5 个字符）"
                    ></textarea>
                  </div>

                  <!-- 证据上传 -->
                  <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">证据材料 <span class="text-gray-400 font-normal">（图片/PDF/文档/压缩包，最多 5 个，单个 ≤10MB）</span></label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-xl hover:border-indigo-400 transition-colors">
                      <div class="space-y-1 text-center">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <div class="flex text-sm text-gray-600">
                          <label class="relative cursor-pointer rounded-md bg-white font-medium text-indigo-600 hover:text-indigo-500">
                            <span>上传文件</span>
                            <input type="file" class="sr-only" multiple @change="onFilePick" />
                          </label>
                        </div>
                      </div>
                    </div>
                    <ul v-if="appealForm.files.length" class="mt-3 space-y-2">
                      <li v-for="(file, idx) in appealForm.files" :key="idx" class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2 text-sm">
                        <span class="text-gray-700 truncate">{{ file.name }}</span>
                        <span class="flex items-center gap-3">
                          <span class="text-gray-400 text-xs">{{ formatFileSize(file.size) }}</span>
                          <button type="button" class="text-red-500 hover:text-red-700" @click="removeFile(idx)">移除</button>
                        </span>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 md:flex md:flex-row-reverse md:gap-3">
                <button @click="submitAppeal" :disabled="submitting" class="btn-primary w-full md:w-auto min-w-[100px]">
                  {{ submitting ? '提交中...' : '提交申诉' }}
                </button>
                <button @click="closeAppealModal" class="btn-secondary w-full md:w-auto mt-3 md:mt-0 min-w-[80px]">取消</button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../api'
import { recordReviewInfo, formatFileSize } from '../../utils/appeal'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const toast = useToast()
const authStore = useAuthStore()

const records = ref([])
const loading = ref(true)

const showAppealModal = ref(false)
const detailLoading = ref(false)
const submitting = ref(false)
const appealQuestions = ref([])
const appealForm = ref({
  record: null,
  type: 'score',
  question_id: null,
  reason: '',
  files: []
})

const typeOptions = {
  score: '分数申诉',
  judge: '判题异议',
  abnormal: '异常标记'
}

const reviewInfo = (record) => recordReviewInfo(record.latest_appeal)

const canAppeal = (record) => {
  if (record.status !== 'graded') return false
  const appeal = record.latest_appeal
  if (!appeal) return true
  return appeal.status === 'closed'
}

const fetchRecords = async () => {
  loading.value = true
  try {
    const response = await api.get('/exams/records')
    records.value = response.data.records.data
  } catch (e) {
    console.error('Failed to fetch records:', e)
  } finally {
    loading.value = false
  }
}

const openAppealModal = async (record) => {
  appealForm.value = { record, type: 'score', question_id: null, reason: '', files: [] }
  appealQuestions.value = []
  showAppealModal.value = true
  detailLoading.value = true
  try {
    const response = await api.get(`/appeals/records/${record.id}/questions`)
    appealForm.value.record = response.data.record
    appealQuestions.value = response.data.questions
  } catch (e) {
    console.error('Failed to load record detail:', e)
    showAppealModal.value = false
  } finally {
    detailLoading.value = false
  }
}

const closeAppealModal = () => {
  showAppealModal.value = false
}

const onFilePick = (event) => {
  const picked = Array.from(event.target.files || [])
  event.target.value = ''

  const allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip']
  const valid = picked.filter((file) => {
    const ext = file.name.split('.').pop().toLowerCase()
    if (!allowed.includes(ext)) {
      toast.error(`不支持的文件类型：${file.name}`)
      return false
    }
    if (file.size > 10 * 1024 * 1024) {
      toast.error(`文件超过 10MB：${file.name}`)
      return false
    }
    return true
  })

  const remaining = 5 - appealForm.value.files.length
  if (valid.length > remaining) {
    toast.warning(`最多上传 5 个文件，已截取前 ${remaining} 个`)
  }
  appealForm.value.files.push(...valid.slice(0, remaining))
}

const removeFile = (idx) => {
  appealForm.value.files.splice(idx, 1)
}

const submitAppeal = async () => {
  if (appealForm.value.reason.trim().length < 5) {
    toast.error('请填写至少 5 个字符的申诉原因')
    return
  }

  submitting.value = true
  try {
    const payload = new FormData()
    payload.append('exam_record_id', appealForm.value.record.id)
    payload.append('type', appealForm.value.type)
    payload.append('reason', appealForm.value.reason)
    if (appealForm.value.question_id) {
      payload.append('question_id', appealForm.value.question_id)
    }
    appealForm.value.files.forEach((file) => payload.append('evidences[]', file))

    const response = await api.post('/appeals', payload, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    toast.success(response.data.message || '申诉已提交')
    showAppealModal.value = false
    await fetchRecords()
  } catch (e) {
    console.error('Failed to submit appeal:', e)
    if (e.response?.status !== 422 && e.response?.status !== 403) {
      toast.error(e.response?.data?.message || '申诉提交失败')
    }
  } finally {
    submitting.value = false
  }
}

const goAppeal = (id) => {
  router.push(`/appeals?highlight=${id}`)
}

onMounted(fetchRecords)
</script>
