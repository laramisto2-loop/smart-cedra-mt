import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function getTenantSettings() {
  const response = await api.get('/api/tenant-settings')

  return response.data.data
}

export async function updateTenantSettings(payload) {
  await prepareForWrite()

  const response = await api.patch(
    '/api/tenant-settings',
    payload,
  )

  return response.data.data
}
