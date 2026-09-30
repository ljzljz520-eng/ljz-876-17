<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <router-link to="/records" class="text-gray-400 hover:text-indigo-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
      </router-link>
      <h1 class="text-2xl font-bold text-gray-900">我的成绩申诉</h1>
    </div>

    <div v-if="loading" class="text-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
    </div>
    <div v-else-if="appeals.length === 0" class="bg-white rounded-lg shadow p-12 text-center text-gray-500">
      <svg class="w-14 h-14 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p>暂无申诉记录</p>
      <router-link to="/records" class="inline-block mt-4 text-sm text-indigo-600 font-semibold hover:text-indigo-500">
        去成绩单发起申诉 →
      </router-link>
    </div>

    <div v-else class="space-y-4">
      <div v-for="a in appeals" :key="a.id"
        class="bg-white shadow-sm rounded-xl p-5 border border-gray-100 hover:border-indigo-200 transition-colors">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="typeBadge(a.appeal_type)">
                {{ a.appeal_type_text }}
              </span>
              <span class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="statusBadge(a.status)">
                {{ a.status_text }}
              </span>
              <span v-if="a.final_result" class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">
                结论：{{ a.final_result_text }}
              </span>
            </div>
            <div class="text-sm text-gray-800 font-medium">
              {{ a.exam_paper?.title }}
              <span class="text-gray-400 font-normal">·</span>
              <span class="text-gray-500 font-normal">{{ a.question ? a.question.title : '整卷申诉' }}</span>
            </div>
            <p class="mt-1.5 text-sm text-gray-500 line-clamp-2">{{ a.reason }}</p>
            <div class="mt-2 flex items-center gap-4 text-xs text-gray-400">
              <span>提交于 {{ new Date(a.created_at).toLocaleString() }}</span>
              <span v-if="a.evidences.length">证据 {{ a.evidences.length }} 份</span>
              <span v-if="a.reviews.length">处理 {{ a.reviews.length }} 次</span>
            </div>
          </div>
          <div class="flex flex-col items-end gap-2 flex-shrink-0">
            <div v-if="a.final_result" class="text-sm text-right">
              <div class="text-xs text-gray-400">
                申诉前 {{ a.original_score }} 分
              </div>
              <div class="font-bold text-lg" :class="Number(a.score_adjustment) > 0 ? 'text-green-600' : Number(a.score_adjustment) < 0 ? 'text-red-600' : 'text-gray-700'">
                {{ a.final_score }} 分
                <span v-if="Number(a.score_adjustment) !== 0" class="text-xs">
                  ({{ Number(a.score_adjustment) > 0 ? '+' : '' }}{{ a.score_adjustment }})
                </span>
              </div>
            </div>
            <button class="text-indigo-600 hover:text-indigo-500 text-sm font-semibold"
              @click="openDetail(a.id)">
              查看复核轨迹
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- 分页 -->
    <div v-if="meta.last_page > 1" class="flex justify-center items-center gap-3 text-sm">
      <button class="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page <= 1" @click="changePage(meta.current_page - 1)">上一页</button>
      <span class="text-gray-600">{{ meta.current_page }} / {{ meta.last_page }}</span>
      <button class="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40"
        :disabled="meta.current_page >= meta.last_page" @click="changePage(meta.current_page + 1)">下一页</button>
    </div>

    <AppealDetailModal :show="detailShow" :appeal-id="detailId" :can-review="false" role="student"
      @close="detailShow = false" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../api'
import AppealDetailModal from '../../components/appeals/AppealDetailModal.vue'

const appeals = ref([])
const loading = ref(true)
const page = ref(1)
const meta = ref({ current_page: 1, last_page: 1 })
const detailShow = ref(false)
const detailId = ref(null)

const load = async () => {
  loading.value = true
  try {
    const res = await api.get('/score-appeals/mine', { params: { page: page.value } })
    appeals.value = res.data.appeals
    meta.value = res.data.meta
  } finally {
    loading.value = false
  }
}

const changePage = (p) => {
  page.value = p
  load()
}

const openDetail = (id) => {
  detailId.value = id
  detailShow.value = true
}

onMounted(load)

const typeBadge = (t) => ({
  score: 'bg-blue-100 text-blue-700',
  scoring: 'bg-purple-100 text-purple-700',
  abnormal: 'bg-rose-100 text-rose-700'
}[t] || 'bg-gray-100 text-gray-700')

const statusBadge = (s) => ({
  pending: 'bg-amber-100 text-amber-700',
  to_academic: 'bg-orange-100 text-orange-700',
  completed: 'bg-teal-100 text-teal-800'
}[s] || 'bg-gray-100 text-gray-700')
</script>
