import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listSegments({
  page = 1,
  search = '',
  type = '',
  status = '',
} = {}) {
  const params = { page }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (type !== '') {
    params.type = type
  }

  if (status !== '') {
    params.status = status
  }

  const response = await api.get('/api/segments', {
    params,
  })

  return response.data
}

export async function getSegment(segmentId) {
  const response = await api.get(
    `/api/segments/${segmentId}`,
  )

  return response.data.data
}

export async function createSegment(payload) {
  await prepareForWrite()

  const response = await api.post('/api/segments', payload)

  return response.data.data
}

export async function updateSegment(segmentId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/segments/${segmentId}`,
    payload,
  )

  return response.data.data
}

export async function deleteSegment(segmentId) {
  await prepareForWrite()

  await api.delete(`/api/segments/${segmentId}`)
}

export async function listSegmentMembers(
  segmentId,
  {
    page = 1,
    perPage = 100,
  } = {},
) {
  const response = await api.get(
    `/api/segments/${segmentId}/members`,
    {
      params: {
        page,
        per_page: perPage,
      },
    },
  )

  return response.data
}

export async function syncSegmentMembers(
  segmentId,
  contactIds,
) {
  await prepareForWrite()

  const response = await api.put(
    `/api/segments/${segmentId}/members`,
    {
      contact_ids: contactIds,
    },
  )

  return response.data.data
}