import api from '../api'

export const APPEAL_STATUS = {
  pending: '待复核',
  to_academic: '已转教务',
  completed: '复核完成'
}

export const APPEAL_TYPES = {
  score: '分数异议',
  scoring: '判题异议',
  abnormal: '异常标记'
}

export const REVIEW_ACTIONS = {
  maintain: '维持原判',
  add: '加分',
  deduct: '减分',
  transfer: '转教务'
}

export function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return `${(bytes / Math.pow(1024, i)).toFixed(1)} ${units[i]}`
}

export async function downloadEvidence(evidenceId, filename) {
  const res = await api.get(`/score-appeals/evidences/${evidenceId}/download`, {
    responseType: 'blob'
  })
  const blob = new Blob([res.data])
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename || '证据附件'
  document.body.appendChild(a)
  a.click()
  a.remove()
  window.URL.revokeObjectURL(url)
}
