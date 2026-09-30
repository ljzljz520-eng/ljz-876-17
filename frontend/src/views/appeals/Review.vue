<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">成绩申诉复核</h1>

    <!-- 统计卡片 -->
    <div class="grid grid-cols-3 gap-4">
      <button v-for="tab in tabs" :key="tab.key"
        class="text-left bg-white rounded-xl p-4 border-2 transition-all"
        :class="filter.status === tab.key ? 'border-indigo-500 shadow-md' : 'border-transparent shadow-sm hover:border-gray-200'"
        @click="switchTab(tab.key)">
        <div class="flex items-center justify-between">
          <span class="text-sm text-gray-500">{{ tab.label }}</span>
          <span class="w-8 h-8 rounded-lg flex items-center justify-center" :class="tab.iconBg">
            <svg class="w-4 h-4" :class="tab.iconClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon" />
            </svg>
          </span>
        </div>
        <div class="mt-2 text-2xl font-extrabold" :class="tab.textClass">{{ counts[tab.key] ?? 0 }}</div>
      </button>
    </div>

    <!-- 筛选 -->
    <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-center">
      <select v-model="filter.appeal_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"
        @change="reload">
        <option value="">全部申诉类型</option>
        <option value="score">分数异议</option>
        <option value="scoring">判题异议</option>
        <option value="abnormal">异常标记</option>
      </select>
      <input v-model="filter.keyword" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-52"
        placeholder="按学生用户名/姓名搜索" @keyup.enter="reload" />
      <button class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-500"
        @click="reload">查询</button>
      <button class="px-3 py-2 text-sm text-gray-600 hover:text-indigo-600" @click="resetFilter">重置</button>
    </div>

    <!-- 列表 -->
    <div v-if="loading" class="text-center py-10">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="appeals.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center text-gray-500">
      暂无{{ currentTabLabel }}申诉
    </div>
    <div v-else class="bg-white shadow-sm rounded-xl overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">学生</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">试卷 / 题目</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">类型</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">状态</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">轨迹</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">提交时间</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">操作</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="a in appeals" :key="a.id" class="hover:bg-gray-50/70">
            <td class="px-4 py-3 text-sm">
              <div class="font-semibold text-gray-800">{{ a.student?.real_name || a.student?.username }}</div>
              <div class="text-xs text-gray-400">{{ a.student?.username }}</div>
            </td>
            <td class="px-4 py-3 text-sm max-w-xs">
              <div class="font-medium text-gray-800 truncate">{{ a.exam_paper?.title }}</div>
              <div class="text-xs text-gray-500 truncate">{{ a.question ? a.question.title : '整卷申诉' }}</div>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="typeBadge(a.appeal_type)">
                {{ a.appeal_type_text }}
              </span>
            </td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="statusBadge(a.status)">
                {{ a.status === 'completed' ? '复核完成' : a.status_text }}
              </span>
              <div v-if="a.final_result" class="text-xs text-gray-400 mt-1">{{ a.final_result_text }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500">
              <div v-if="a.reviews.length">
                <div v-for="r in a.reviews.slice(-2)" :key="r.id" class="whitespace-nowrap">
                  {{ r.reviewer?.real_name || r.reviewer?.username || (r.reviewer?.role_text) }}
                  <span class="text-gray-400">{{ r.action_text }}</span>
                </div>
              </div>
              <span v-else class="text-gray-400">未处理</span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
              {{ new Date(a.created_at).toLocaleString() }}
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button v-if="canAct(a)"
                class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg"
                :class="a.status === 'to_academic' ? 'bg-purple-600 hover:bg-purple-500' : 'bg-indigo-600 hover:bg-indigo-500'"
                @click="openReview(a.id)">
                {{ a.status === 'to_academic' ? '教务处理' : a.reviews.length ? '继续处理' : '去复核' }}
              </button>
              <button v-else class="px-3 py-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-500"
                @click="openReview(a.id)">
                查看轨迹
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- 分页 -->
    <div v-if="meta.last_page > 1" class="flex justify-center items-center gap-3 text-sm">
      <button class="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">上一页</button>
      <span class="text-gray-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">下一页</button>
    </div>

    <AppealDetailModal :show="detailShow" :appeal-id="detailId" :can-review="true" :role="role"
      @close="detailShow = false" @reviewed="onReviewed" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '../../api'
import { useAuthStore } from '../../stores/auth'
import AppealDetailModal from '../../components/appeals/AppealDetailModal.vue'

const authStore = useAuthStore()
const role = computed(() => authStore.user?.role || 'teacher')

const appeals = ref([])
const counts = ref({ pending: 0, to_academic: 0, completed: 0 })
const loading = ref(true)
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1 })
const detailShow = ref(false)
const detailId = ref(null)

const filter = reactive({ status: 'pending', appeal_type: '', keyword: '' })

const tabs = [
  {
    key: 'pending',
    label: '待复核',
    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    iconBg: 'bg-amber-100',
    iconClass: 'text-amber-600',
    textClass: 'text-amber-600'
  },
  {
    key: 'to_academic',
    label: '已转教务',
    icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    iconBg: 'bg-purple-100',
    iconClass: 'text-purple-600',
    textClass: 'text-purple-600'
  },
  {
    key: 'completed',
    label: '复核完成',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    iconBg: 'bg-teal-100',
    iconClass: 'text-teal-600',
    textClass: 'text-teal-600'
  }
]

const currentTabLabel = computed(() => tabs.find(t => t.key === filter.status)?.label || '')

const load = async () => {
  loading.value = true
  try {
    const params = { page: page.value }
    // 教师不传 status 时后端默认只返回待处理；这里始终显式传入
    params.status = filter.status
    if (filter.appeal_type) params.appeal_type = filter.appeal_type
    if (filter.keyword) params.keyword = filter.keyword

    const res = await api.get('/score-appeals/review', { params })
    appeals.value = res.data.appeals
    meta.value = res.data.meta
    counts.value = res.data.counts
  } finally {
    loading.value = false
  }
}

const switchTab = (key) => {
  filter.status = key
  page.value = 1
  load()
}

const reload = () => {
  page.value = 1
  load()
}

const resetFilter = () => {
  filter.appeal_type = ''
  filter.keyword = ''
  reload()
}

const changePage = (p) => {
  page.value = p
  load()
}

const canAct = (a) => {
  if (a.status === 'completed') return false
  if (a.status === 'to_academic') return role.value === 'admin'
  return true
}

const openReview = (id) => {
  detailId.value = id
  detailShow.value = true
}

const onReviewed = () => {
  detailShow.value = false
  load()
}

const typeBadge = (t) => ({
  score: 'bg-blue-100 text-blue-700',
  scoring: 'bg-purple-100 text-purple-700',
  abnormal: 'bg-rose-100 text-rose-700'
}[t] || 'bg-gray-100 text-gray-700')

const statusBadge = (s) => ({
  pending: 'bg-amber-100 text-amber-700',
  to_academic: 'bg-purple-100 text-purple-700',
  completed: 'bg-teal-100 text-teal-800'
}[s] || 'bg-gray-100 text-gray-700')

onMounted(load)
</script>
