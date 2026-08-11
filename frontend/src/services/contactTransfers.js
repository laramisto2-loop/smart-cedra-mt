import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

function getDownloadFilename(response, fallback) {
  const disposition =
    response.headers['content-disposition'] ?? ''

  const utfFilename = disposition.match(
    /filename\*=UTF-8''([^;]+)/i,
  )

  if (utfFilename) {
    return decodeURIComponent(utfFilename[1])
  }

  const regularFilename = disposition.match(
    /filename="?([^";]+)"?/i,
  )

  return regularFilename?.[1] ?? fallback
}

function saveDownload(response, fallbackFilename) {
  const filename = getDownloadFilename(
    response,
    fallbackFilename,
  )
  const url = window.URL.createObjectURL(response.data)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.URL.revokeObjectURL(url)
}

async function downloadContactFile(action) {
  const response = await api.get(
    `/api/contacts/transfers/${action}`,
    {
      responseType: 'blob',
    },
  )

  const fallbackFilename =
    action === 'template'
      ? 'contacts-template.csv'
      : 'contacts-export.csv'

  saveDownload(response, fallbackFilename)
}

export async function downloadContactTemplate() {
  await downloadContactFile('template')
}

export async function exportContactData() {
  await downloadContactFile('export')
}

export async function previewContactImport(file) {
  await prepareForWrite()

  const formData = new FormData()
  formData.append('file', file)

  const response = await api.post(
    '/api/contacts/transfers/preview',
    formData,
  )

  return response.data.data
}

export async function importContactData(file) {
  await prepareForWrite()

  const formData = new FormData()
  formData.append('file', file)
  formData.append('confirmed', '1')

  const response = await api.post(
    '/api/contacts/transfers/import',
    formData,
  )

  return response.data.data
}