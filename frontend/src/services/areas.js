import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listAreas({
  page = 1,
  districtId = '',
  search = '',
} = {}) {
  const params = { page }

  if (districtId !== '') {
    params.district_id = districtId
  }

  if (search !== '') {
    params.search = search
  }

  const response = await api.get('/api/areas', { params })

  return response.data
}

export async function getArea(areaId) {
  const response = await api.get(`/api/areas/${areaId}`)

  return response.data.data
}

export async function createArea(payload) {
  await prepareForWrite()

  const response = await api.post('/api/areas', payload)

  return response.data.data
}

export async function updateArea(areaId, payload) {
  await prepareForWrite()

  const response = await api.patch(`/api/areas/${areaId}`, payload)

  return response.data.data
}

export async function deleteArea(areaId) {
  await prepareForWrite()

  await api.delete(`/api/areas/${areaId}`)
}
