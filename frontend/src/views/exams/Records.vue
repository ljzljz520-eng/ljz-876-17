<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
      <h1 class="text-2xl font-bold text-gray-900">我的成绩</h1>
      <router-link to="/my-appeals"
        class="inline-flex items-center px-4 py-2 text-sm font-semibold text-indigo-700 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition-colors">
        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        我的申诉
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
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">判题状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">复核状态</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">考试时间</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50/60">
            <td class="px-6 py-4 whitespace-nowrap">{{ record.exam_paper?.title }}</td>
            <td class="px-6 py-4 whitespace-nowrap font-bold" :class="{'text-green-600': record.score >= 60, 'text-red-600': record.score < 60}">{{ record.score }} 分</td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                {{ record.status === 'graded' ? '已评分' : record.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <!-- 无论维持/加分/减分/转教务，处理完成后成绩单统一显示“复核完成” -->
              <span v-if="record.appeal_summary?.review_status === 'completed'"
                class="px-2 inline-flex items-center text-xs leading-5 font-semibold rounded-full bg-teal-100 text-teal-800">
                <svg class="w-3 h-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                复核完成
              </span>
              <span v-else-if="record.appeal_summary?.review_status === 'pending'"
                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-100 text-amber-800">
                复核中（{{ record.appeal_summary.pending_count }}）
              </span>
              <span v-else class="text-xs text-gray-400">未申诉</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(record.created_at).toLocaleString() }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-right">
              <div class="flex items-center justify-end gap-2">
                <button class="text-indigo-600 hover:text-indigo-500 text-xs font-semibold"
                  @click="viewRecord(record)">查看详情</button>
                <template v-if="record.status === 'graded'">
                  <span class="text-gray-300">|</span>
                  <button v-if="canAppeal(record)"
                    class="text-rose-600 hover:text-rose-500 text-xs font-semibold"
                    @click="openAppeal(record)">成绩申诉</button>
                  <span v-else class="text-xs text-gray-400">复核中</span>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 成绩详情 -->
    <RecordDetailModal :show="detailShow" :record="activeRecord" :can-appeal="activeRecord && canAppeal(activeRecord)"
      @close="detailShow = false"
      @appeal="fromDetailToAppeal" />

    <!-- 提交申诉弹窗 -->
    <SubmitAppealModal :show="appealShow" :record="activeRecord"
      @close="appealShow = false"
      @submitted="onAppealSubmitted" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import { useToast } from '../../composables/useToast'
import SubmitAppealModal from '../../components/appeals/SubmitAppealModal.vue'
import RecordDetailModal from '../../components/appeals/RecordDetailModal.vue'

const { success } = useToast()

const records = ref([])
const loading = ref(true)
const appealShow = ref(false)
const detailShow = ref(false)
const activeRecord = ref(null)

const load = async () => {
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

onMounted(load)

// 已评分且没有处理中的申诉时可以发起申诉
const canAppeal = (record) => {
  return record.status === 'graded' && !record.appeal_summary?.pending_count
}

const fetchFullRecord = async (record) => {
  const res = await api.get(`/exams/records/${record.id}`)
  activeRecord.value = res.data.record
}

const viewRecord = async (record) => {
  await fetchFullRecord(record)
  detailShow.value = true
}

const openAppeal = async (record) => {
  if (!activeRecord.value || activeRecord.value.id !== record.id) {
    await fetchFullRecord(record)
  }
  detailShow.value = false
  appealShow.value = true
}

const fromDetailToAppeal = () => {
  detailShow.value = false
  appealShow.value = true
}

const onAppealSubmitted = () => {
  success('申诉已提交，请等待老师复核')
  load()
}
</script>
