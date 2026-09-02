import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listPollingCenters({
  page = 1,
  areaId = '',
  search = '',
} = {}) {
  const params = { page }

  if (areaId !== '') {
    params.area_id = areaId
  }

  if (search !== '') {
    params.search = search
  }

  const response = await api.get('/api/polling-centers', {
    params,
  })

  return response.data
}

export async function getPollingCenter(pollingCenterId) {
  const response = await api.get(
    `/api/polling-centers/${pollingCenterId}`,
  )

  return response.data.data
}

export async function createPollingCenter(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/polling-centers',
    payload,
  )

  return response.data.data
}

export async function updatePollingCenter(
  pollingCenterId,
  payload,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/polling-centers/${pollingCenterId}`,
    payload,
  )

  return response.data.data
}

export async function deletePollingCenter(pollingCenterId) {
  await prepareForWrite()

  await api.delete(`/api/polling-centers/${pollingCenterId}`)
}
