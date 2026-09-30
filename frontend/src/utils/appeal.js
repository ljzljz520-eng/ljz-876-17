export const APPEAL_TYPES = {
  score: '分数申诉',
  judge: '判题异议',
  abnormal: '异常标记'
}

export const APPEAL_STATUS = {
  pending: { label: '待复核', badge: 'bg-amber-100 text-amber-800' },
  transferred: { label: '已转教务', badge: 'bg-blue-100 text-blue-800' },
  closed: { label: '复核完成', badge: 'bg-emerald-100 text-emerald-800' }
}

export const REVIEW_ACTIONS = {
  submit: { label: '发起申诉', badge: 'bg-gray-100 text-gray-700' },
  transfer: { label: '转交教务', badge: 'bg-blue-100 text-blue-800' },
  upheld: { label: '维持原判', badge: 'bg-gray-100 text-gray-700' },
  add_score: { label: '加分', badge: 'bg-emerald-100 text-emerald-800' },
  deduct_score: { label: '减分', badge: 'bg-red-100 text-red-800' }
}

export const ROLE_LABELS = {
  admin: '教务',
  teacher: '教师',
  student: '学生'
}

export const statusInfo = (status) => APPEAL_STATUS[status] || { label: status, badge: 'bg-gray-100 text-gray-700' }
export const typeLabel = (type) => APPEAL_TYPES[type] || type
export const actionInfo = (action) => REVIEW_ACTIONS[action] || { label: action, badge: 'bg-gray-100 text-gray-700' }
export const roleLabel = (role) => ROLE_LABELS[role] || role

export const formatFileSize = (bytes) => {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

// 成绩单上的复核状态：仅 closed 显示「复核完成」
export const recordReviewInfo = (appeal) => {
  if (!appeal) return null
  if (appeal.status === 'closed') {
    return { label: '复核完成', badge: 'bg-emerald-100 text-emerald-800' }
  }
  if (appeal.status === 'transferred') {
    return { label: '复核中（已转教务）', badge: 'bg-blue-100 text-blue-800' }
  }
  return { label: '复核中', badge: 'bg-amber-100 text-amber-800' }
}
