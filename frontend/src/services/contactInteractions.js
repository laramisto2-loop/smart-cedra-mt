import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listContactInteractions(
  contactId,
  {
    page = 1,
    channel = '',
    direction = '',
    outcome = '',
    dateFrom = '',
    dateTo = '',
    perPage = 20,
  } = {},
) {
  const params = {
    page,
    per_page: perPage,
  }

  if (channel !== '') {
    params.channel = channel
  }

  if (direction !== '') {
    params.direction = direction
  }

  if (outcome !== '') {
    params.outcome = outcome
  }

  if (dateFrom !== '') {
    params.date_from = dateFrom
  }

  if (dateTo !== '') {
    params.date_to = dateTo
  }

  const response = await api.get(
    `/api/contacts/${contactId}/interactions`,
    { params },
  )

  return response.data
}

export async function getContactInteraction(interactionId) {
  const response = await api.get(
    `/api/contact-interactions/${interactionId}`,
  )

  return response.data.data
}

export async function createContactInteraction(
  contactId,
  payload,
) {
  await prepareForWrite()

  const response = await api.post(
    `/api/contacts/${contactId}/interactions`,
    payload,
  )

  return response.data.data
}

export async function updateContactInteraction(
  interactionId,
  payload,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/contact-interactions/${interactionId}`,
    payload,
  )

  return response.data.data
}

export async function deleteContactInteraction(interactionId) {
  await prepareForWrite()

  await api.delete(
    `/api/contact-interactions/${interactionId}`,
  )
}