import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listDistricts({
  page = 1,
  governorateId = '',
} = {}) {
  const params = { page }

  if (governorateId !== '') {
    params.governorate_id = governorateId
  }

  const response = await api.get('/api/districts', { params })

  return response.data
}

export async function getDistrict(districtId) {
  const response = await api.get(`/api/districts/${districtId}`)

  return response.data.data
}

export async function createDistrict(payload) {
  await prepareForWrite()

  const response = await api.post('/api/districts', payload)

  return response.data.data
}

export async function updateDistrict(districtId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/districts/${districtId}`,
    payload,
  )

  return response.data.data
}

export async function deleteDistrict(districtId) {
  await prepareForWrite()

  await api.delete(`/api/districts/${districtId}`)
}