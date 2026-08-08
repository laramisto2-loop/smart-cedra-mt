import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listContacts({
  page = 1,
  search = '',
  areaId = '',
  status = '',
  preferredLanguage = '',
  preferredChannel = '',
  consentChannel = '',
  consentStatus = '',
  perPage = 15,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search !== '') params.search = search
  if (areaId !== '') params.area_id = areaId
  if (status !== '') params.status = status
  if (preferredLanguage !== '') {
    params.preferred_language = preferredLanguage
  }
  if (preferredChannel !== '') {
    params.preferred_channel = preferredChannel
  }
  if (consentChannel !== '') {
    params.consent_channel = consentChannel
  }
  if (consentStatus !== '') {
    params.consent_status = consentStatus
  }

  const response = await api.get('/api/contacts', { params })

  return response.data
}

export async function getContact(contactId) {
  const response = await api.get(`/api/contacts/${contactId}`)

  return response.data.data
}

export async function createContact(payload) {
  await prepareForWrite()

  const response = await api.post('/api/contacts', payload)

  return response.data.data
}

export async function updateContact(contactId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/contacts/${contactId}`,
    payload,
  )

  return response.data.data
}

export async function deleteContact(contactId) {
  await prepareForWrite()

  await api.delete(`/api/contacts/${contactId}`)
}

export async function recordContactConsent(contactId, payload) {
  await prepareForWrite()

  const response = await api.post(
    `/api/contacts/${contactId}/consents`,
    payload,
  )

  return response.data.data
}