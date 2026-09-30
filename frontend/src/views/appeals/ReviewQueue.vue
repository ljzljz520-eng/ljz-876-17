<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">成绩复核</h1>

    <!-- 状态统计 -->
    <div class="grid grid-cols-3 gap-4">
      <button
        v-for="tab in tabs"
        :key="tab.value"
        class="bg-white rounded-xl shadow p-4 text-left transition-all hover:shadow-md"
        :class="activeStatus === tab.value ? 'ring-2 ring-indigo-500' : ''"
        @click="changeStatus(tab.value)"
      >
        <p class="text-sm text-gray-500">{{ tab.label }}</p>
        <p class="mt-1 text-2xl font-bold" :class="tab.color">{{ counts[tab.value] || 0 }}</p>
      </button>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="appeals.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg shadow">
      暂无{{ tabs.find((t) => t.value === activeStatus)?.label }}申诉
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- 队列列表 -->
      <div class="lg:col-span-2 bg-white shadow rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-100">
          <li
            v-for="item in appeals"
            :key="item.id"
            class="p-4 cursor-pointer transition-colors"
            :class="selectedId === item.id ? 'bg-indigo-50 border-l-4 border-indigo-500' : 'hover:bg-gray-50 border-l-4 border-transparent'"
            @click="selectAppeal(item.id)"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="text-sm font-semibold text-gray-900 truncate">{{ item.exam_record?.exam_paper?.title || `记录 #${item.exam_record_id}` }}</p>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full flex-shrink-0" :class="statusInfo(item.status).badge">
                {{ statusInfo(item.status).label }}
              </span>
            </div>
            <p class="mt-1 text-xs text-gray-500">
              学生：{{ item.student?.real_name || item.student?.username }} · {{ typeLabel(item.type) }}
            </p>
            <p class="mt-1 text-xs text-gray-400">{{ new Date(item.created_at).toLocaleString() }}</p>
          </li>
        </ul>
      </div>

      <!-- 详情 + 复核操作 -->
      <div class="lg:col-span-3 space-y-6">
        <div v-if="detailLoading" class="text-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
        </div>
        <template v-else-if="detail">
          <AppealDetail :appeal="detail" :show-student="true" @download="onDownload" />

          <!-- 复核操作区 -->
          <div v-if="canReview(detail)" class="bg-white rounded-xl shadow p-6 space-y-4">
            <h3 class="text-base font-bold text-gray-900">填写复核意见</h3>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
              <button
                v-for="opt in actionOptions"
                :key="opt.value"
                type="button"
                class="px-3 py-2.5 rounded-lg border text-sm font-medium transition-all"
                :class="reviewForm.action === opt.value
                  ? opt.activeClass
                  : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'"
                :disabled="opt.value === 'transfer' && authStore.isAdmin"
                :title="opt.value === 'transfer' && authStore.isAdmin ? '教务为最终复核角色' : ''"
                @click="reviewForm.action = opt.value"
              >
                {{ opt.label }}
              </button>
            </div>

            <div v-if="['add_score', 'deduct_score'].includes(reviewForm.action)">
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                {{ reviewForm.action === 'add_score' ? '加分分值' : '减分分值' }}
              </label>
              <div class="flex items-center gap-2">
                <input
                  v-model="reviewForm.adjustment"
                  type="number"
                  min="0.01"
                  step="0.5"
                  class="input-base max-w-[160px]"
                  placeholder="如 2"
                />
                <span class="text-sm text-gray-500">分（题目申诉将自动限定在该题分值内）</span>
              </div>
            </div>

            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">处理意见 <span class="text-red-500">*</span></label>
              <textarea
                v-model="reviewForm.comment"
                rows="3"
                class="input-base resize-y"
                placeholder="请填写复核意见，该意见将保留在处理轨迹中，学生与后续处理人可见"
              ></textarea>
            </div>

            <div class="flex justify-end gap-3">
              <button
                @click="submitReview"
                :disabled="submitting"
                class="btn-primary min-w-[120px]"
              >
                {{ submitting ? '提交中...' : '提交复核结论' }}
              </button>
            </div>
          </div>

          <div v-else-if="detail.status === 'closed'" class="bg-white rounded-xl shadow p-4 text-sm text-gray-500 text-center">
            该申诉已复核完成，处理轨迹已归档
          </div>
        </template>
        <div v-else class="text-center py-12 text-gray-400 text-sm bg-white rounded-lg shadow">
          请选择左侧申诉进行复核
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import AppealDetail from '../../components/appeal/AppealDetail.vue'
import { statusInfo, typeLabel } from '../../utils/appeal'
import { downloadFile } from '../../utils/download'
import { useToast } from '../../composables/useToast'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const toast = useToast()

const tabs = [
  { value: 'pending', label: '待复核', color: 'text-amber-600' },
  { value: 'transferred', label: '已转教务', color: 'text-blue-600' },
  { value: 'closed', label: '复核完成', color: 'text-emerald-600' }
]

const actionOptions = [
  { value: 'upheld', label: '维持原判', activeClass: 'border-gray-500 bg-gray-100 text-gray-800 ring-1 ring-gray-500' },
  { value: 'add_score', label: '加分', activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700 ring-1 ring-emerald-500' },
  { value: 'deduct_score', label: '减分', activeClass: 'border-red-500 bg-red-50 text-red-700 ring-1 ring-red-500' },
  { value: 'transfer', label: '转交教务', activeClass: 'border-blue-500 bg-blue-50 text-blue-700 ring-1 ring-blue-500' }
]

const appeals = ref([])
const counts = ref({ pending: 0, transferred: 0, closed: 0 })
const activeStatus = ref('pending')
const loading = ref(true)
const selectedId = ref(null)
const detail = ref(null)
const detailLoading = ref(false)
const submitting = ref(false)
const reviewForm = ref({ action: 'upheld', adjustment: '', comment: '' })

const fetchList = async () => {
  loading.value = true
  try {
    const response = await api.get('/appeals/reviews', { params: { status: activeStatus.value } })
    appeals.value = response.data.appeals.data
    counts.value = response.data.counts
    if (appeals.value.length && appeals.value.some((a) => a.id === selectedId.value)) {
      await selectAppeal(selectedId.value)
    } else if (appeals.value.length) {
      await selectAppeal(appeals.value[0].id)
    } else {
      selectedId.value = null
      detail.value = null
    }
  } catch (e) {
    console.error('Failed to fetch review queue:', e)
  } finally {
    loading.value = false
  }
}

const changeStatus = (status) => {
  activeStatus.value = status
  fetchList()
}

const selectAppeal = async (id) => {
  selectedId.value = id
  detailLoading.value = true
  detail.value = null
  try {
    const response = await api.get(`/appeals/${id}`)
    detail.value = response.data.appeal
    reviewForm.value = { action: 'upheld', adjustment: '', comment: '' }
  } catch (e) {
    console.error('Failed to load appeal:', e)
  } finally {
    detailLoading.value = false
  }
}

const canReview = (appeal) => {
  if (appeal.status === 'closed') return false
  if (appeal.status === 'transferred') return authStore.isAdmin
  return authStore.isTeacher
}

const submitReview = async () => {
  if (!reviewForm.value.comment.trim()) {
    toast.error('请填写处理意见')
    return
  }
  if (['add_score', 'deduct_score'].includes(reviewForm.value.action) && (!reviewForm.value.adjustment || Number(reviewForm.value.adjustment) <= 0)) {
    toast.error('请填写有效的调整分值')
    return
  }

  submitting.value = true
  try {
    const payload = {
      action: reviewForm.value.action,
      comment: reviewForm.value.comment
    }
    if (['add_score', 'deduct_score'].includes(reviewForm.value.action)) {
      payload.adjustment = reviewForm.value.adjustment
    }

    const response = await api.post(`/appeals/${selectedId.value}/review`, payload)
    toast.success(response.data.message || '复核已提交')

    const finishedId = selectedId.value
    activeStatus.value = reviewForm.value.action === 'transfer' ? 'transferred' : 'closed'
    await fetchList()
    await selectAppeal(finishedId)
  } catch (e) {
    console.error('Failed to submit review:', e)
  } finally {
    submitting.value = false
  }
}

const onDownload = async (file) => {
  try {
    await downloadFile(`/appeals/${selectedId.value}/evidences/${file.id}/download`, file.original_name)
  } catch (e) {
    toast.error('文件下载失败')
  }
}

onMounted(fetchList)
</script>
