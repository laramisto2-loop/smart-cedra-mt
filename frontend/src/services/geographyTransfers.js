import api from '../lib/api.js'

export const geographyTransferTypes = [
  {
    id: 'governorates',
    label: 'Governorates',
  },
  {
    id: 'districts',
    label: 'Districts',
  },
  {
    id: 'areas',
    label: 'Areas',
  },
  {
    id: 'polling-centers',
    label: 'Polling centers',
  },
  {
    id: 'polling-stations',
    label: 'Polling stations',
  },
]

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

async function downloadGeographyFile(type, action) {
  const response = await api.get(
    `/api/geography/transfers/${type}/${action}`,
    {
      responseType: 'blob',
    },
  )

  const fallbackFilename =
    action === 'template'
      ? `${type}-template.csv`
      : `${type}-export.csv`

  saveDownload(response, fallbackFilename)
}

export async function downloadGeographyTemplate(type) {
  await downloadGeographyFile(type, 'template')
}

export async function exportGeographyData(type) {
  await downloadGeographyFile(type, 'export')
}

export async function previewGeographyImport(type, file) {
  await prepareForWrite()

  const formData = new FormData()
  formData.append('file', file)

  const response = await api.post(
    `/api/geography/transfers/${type}/preview`,
    formData,
  )

  return response.data.data
}

export async function importGeographyData(type, file) {
  await prepareForWrite()

  const formData = new FormData()
  formData.append('file', file)
  formData.append('confirmed', '1')

  const response = await api.post(
    `/api/geography/transfers/${type}/import`,
    formData,
  )

  return response.data.data
}