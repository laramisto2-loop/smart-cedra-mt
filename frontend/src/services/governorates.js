import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listGovernorates({
  page = 1,
  search = '',
} = {}) {
  const params = { page }

  if (search !== '') {
    params.search = search
  }

  const response = await api.get('/api/governorates', {
    params,
  })

  return response.data
}

export async function getGovernorate(governorateId) {
  const response = await api.get(
    `/api/governorates/${governorateId}`,
  )

  return response.data.data
}

export async function createGovernorate(payload) {
  await prepareForWrite()

  const response = await api.post('/api/governorates', payload)

  return response.data.data
}

export async function updateGovernorate(governorateId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/governorates/${governorateId}`,
    payload,
  )

  return response.data.data
}

export async function deleteGovernorate(governorateId) {
  await prepareForWrite()

  await api.delete(`/api/governorates/${governorateId}`)
}

