<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-900">我的申诉</h1>
      <router-link to="/records" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">← 返回我的成绩</router-link>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="appeals.length === 0" class="text-center py-12 text-gray-500 bg-white rounded-lg shadow">
      暂无申诉记录，可在「我的成绩」中对已评分试卷发起申诉
    </div>

    <!-- 列表 -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-5 gap-6">
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
              {{ typeLabel(item.type) }}{{ item.question ? ' · 针对具体题目' : ' · 整份试卷' }}
            </p>
            <p class="mt-1 text-xs text-gray-400">{{ new Date(item.created_at).toLocaleString() }}</p>
          </li>
        </ul>
      </div>

      <div class="lg:col-span-3">
        <div v-if="detailLoading" class="text-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
        </div>
        <div v-else-if="!detail" class="text-center py-12 text-gray-400 text-sm bg-white rounded-lg shadow">
          请选择左侧申诉查看复核轨迹
        </div>
        <AppealDetail v-else :appeal="detail" @download="onDownload" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../api'
import AppealDetail from '../../components/appeal/AppealDetail.vue'
import { statusInfo, typeLabel } from '../../utils/appeal'
import { downloadFile } from '../../utils/download'
import { useToast } from '../../composables/useToast'

const route = useRoute()
const toast = useToast()

const appeals = ref([])
const loading = ref(true)
const selectedId = ref(null)
const detail = ref(null)
const detailLoading = ref(false)

const selectAppeal = async (id) => {
  selectedId.value = id
  detailLoading.value = true
  detail.value = null
  try {
    const response = await api.get(`/appeals/${id}`)
    detail.value = response.data.appeal
  } catch (e) {
    console.error('Failed to load appeal:', e)
  } finally {
    detailLoading.value = false
  }
}

const onDownload = async (file) => {
  try {
    await downloadFile(`/appeals/${selectedId.value}/evidences/${file.id}/download`, file.original_name)
  } catch (e) {
    toast.error('文件下载失败')
  }
}

onMounted(async () => {
  try {
    const response = await api.get('/appeals/mine')
    appeals.value = response.data.appeals.data
    const highlight = route.query.highlight
    if (highlight && appeals.value.some((a) => a.id === Number(highlight))) {
      selectAppeal(Number(highlight))
    } else if (appeals.value.length) {
      selectAppeal(appeals.value[0].id)
    }
  } catch (e) {
    console.error('Failed to fetch appeals:', e)
  } finally {
    loading.value = false
  }
})
</script>
