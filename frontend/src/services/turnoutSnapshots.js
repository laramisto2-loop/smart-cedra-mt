import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

function addOptional(params, key, value) {
  if (value !== '' && value !== null && value !== undefined) {
    params[key] = value
  }
}

export async function listTurnoutSnapshots({
  page = 1,
  search = '',
  source = '',
  reportedByUserId = '',
  pollingCenterId = '',
  pollingStationId = '',
  capturedFrom = '',
  capturedTo = '',
  mine = false,
  perPage = 20,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  addOptional(params, 'search', search.trim())
  addOptional(params, 'source', source)
  addOptional(
    params,
    'reported_by_user_id',
    reportedByUserId,
  )
  addOptional(
    params,
    'polling_center_id',
    pollingCenterId,
  )
  addOptional(
    params,
    'polling_station_id',
    pollingStationId,
  )
  addOptional(params, 'captured_from', capturedFrom)
  addOptional(params, 'captured_to', capturedTo)

  if (mine) {
    params.mine = 1
  }

  const response = await api.get(
    '/api/turnout-snapshots',
    { params },
  )

  return response.data
}

export async function getTurnoutSnapshot(snapshotId) {
  const response = await api.get(
    `/api/turnout-snapshots/${snapshotId}`,
  )

  return response.data.data
}

export async function createTurnoutSnapshot(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/turnout-snapshots',
    payload,
  )

  return response.data.data
}

export async function getTurnoutSeries({
  pollingCenterId,
  pollingStationId = '',
  capturedFrom = '',
  capturedTo = '',
}) {
  const params = {
    polling_center_id: pollingCenterId,
  }

  addOptional(
    params,
    'polling_station_id',
    pollingStationId,
  )
  addOptional(params, 'captured_from', capturedFrom)
  addOptional(params, 'captured_to', capturedTo)

  const response = await api.get(
    '/api/turnout-snapshots/series',
    { params },
  )

  return response.data
}