import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

function addOptional(params, key, value) {
  if (value !== '' && value !== null && value !== undefined) {
    params[key] = value
  }
}

export async function listIncidents({
  page = 1,
  search = '',
  category = '',
  severity = '',
  status = '',
  assignedToUserId = '',
  areaId = '',
  mine = false,
  occurredFrom = '',
  occurredTo = '',
  perPage = 20,
} = {}) {
  const params = { page, per_page: perPage }

  addOptional(params, 'search', search.trim())
  addOptional(params, 'category', category)
  addOptional(params, 'severity', severity)
  addOptional(params, 'status', status)
  addOptional(params, 'assigned_to_user_id', assignedToUserId)
  addOptional(params, 'area_id', areaId)
  addOptional(params, 'occurred_from', occurredFrom)
  addOptional(params, 'occurred_to', occurredTo)

  if (mine) {
    params.mine = 1
  }

  const response = await api.get('/api/incidents', { params })

  return response.data
}

export async function getIncident(incidentId) {
  const response = await api.get(`/api/incidents/${incidentId}`)

  return response.data.data
}

export async function createIncident(payload) {
  await prepareForWrite()

  const response = await api.post('/api/incidents', payload)

  return response.data.data
}

export async function updateIncident(incidentId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/incidents/${incidentId}`,
    payload,
  )

  return response.data.data
}

export async function assignIncident(
  incidentId,
  assignedToUserId,
  expectedSyncVersion,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/incidents/${incidentId}/assign`,
    {
      assigned_to_user_id: assignedToUserId,
      expected_sync_version: expectedSyncVersion,
    },
  )

  return response.data.data
}

export async function reviewIncident(
  incidentId,
  status,
  resolutionNotes,
  expectedSyncVersion,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/incidents/${incidentId}/review`,
    {
      status,
      resolution_notes:
        resolutionNotes.trim() === ''
          ? null
          : resolutionNotes.trim(),
      expected_sync_version: expectedSyncVersion,
    },
  )

  return response.data.data
}

export async function deleteIncident(incidentId) {
  await prepareForWrite()
  await api.delete(`/api/incidents/${incidentId}`)
}

export async function uploadIncidentAttachment(
  incidentId,
  file,
) {
  await prepareForWrite()

  const payload = new FormData()
  payload.append('file', file)
  payload.append('client_uuid', crypto.randomUUID())
  payload.append('captured_at', new Date().toISOString())
  payload.append('client_updated_at', new Date().toISOString())

  const response = await api.post(
    `/api/incidents/${incidentId}/attachments`,
    payload,
  )

  return response.data.data
}

export async function downloadIncidentAttachment(attachment) {
  const response = await api.get(
    `/api/incident-attachments/${attachment.id}/download`,
    { responseType: 'blob' },
  )

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = attachment.original_name
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export async function deleteIncidentAttachment(attachmentId) {
  await prepareForWrite()
  await api.delete(`/api/incident-attachments/${attachmentId}`)
}
