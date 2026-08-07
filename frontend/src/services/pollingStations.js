import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listPollingStations({
  page = 1,
  pollingCenterId = '',
} = {}) {
  const params = { page }

  if (pollingCenterId !== '') {
    params.polling_center_id = pollingCenterId
  }

  const response = await api.get('/api/polling-stations', {
    params,
  })

  return response.data
}

export async function getPollingStation(pollingStationId) {
  const response = await api.get(
    `/api/polling-stations/${pollingStationId}`,
  )

  return response.data.data
}

export async function createPollingStation(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/polling-stations',
    payload,
  )

  return response.data.data
}

export async function updatePollingStation(
  pollingStationId,
  payload,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/polling-stations/${pollingStationId}`,
    payload,
  )

  return response.data.data
}

export async function deletePollingStation(pollingStationId) {
  await prepareForWrite()

  await api.delete(
    `/api/polling-stations/${pollingStationId}`,
  )
}