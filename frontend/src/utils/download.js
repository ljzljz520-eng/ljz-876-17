import api from '../api'

// 带鉴权头的文件下载（Content-Disposition attachment）
export const downloadFile = async (url, fallbackName = 'download') => {
  const response = await api.get(url, { responseType: 'blob' })
  const blob = new Blob([response.data], {
    type: response.headers['content-type'] || 'application/octet-stream'
  })

  let filename = fallbackName
  const disposition = response.headers['content-disposition']
  if (disposition) {
    const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
    const plainMatch = disposition.match(/filename="?([^";]+)"?/i)
    if (utf8Match) {
      filename = decodeURIComponent(utf8Match[1])
    } else if (plainMatch) {
      filename = plainMatch[1]
    }
  }

  const link = document.createElement('a')
  link.href = window.URL.createObjectURL(blob)
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  window.URL.revokeObjectURL(link.href)
}
