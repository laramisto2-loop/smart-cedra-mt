import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listMessageTemplates({
  page = 1,
  search = '',
  channel = '',
  category = '',
  status = '',
  perPage = 20,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (channel !== '') {
    params.channel = channel
  }

  if (category !== '') {
    params.category = category
  }

  if (status !== '') {
    params.status = status
  }

  const response = await api.get('/api/message-templates', {
    params,
  })

  return response.data
}

export async function getMessageTemplate(templateId) {
  const response = await api.get(
    `/api/message-templates/${templateId}`,
  )

  return response.data.data
}

export async function createMessageTemplate(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/message-templates',
    payload,
  )

  return response.data.data
}

export async function updateMessageTemplate(
  templateId,
  payload,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/message-templates/${templateId}`,
    payload,
  )

  return response.data.data
}

export async function reviewMessageTemplate(
  templateId,
  status,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/message-templates/${templateId}/approve`,
    {
      status,
    },
  )

  return response.data.data
}

export async function deleteMessageTemplate(templateId) {
  await prepareForWrite()

  await api.delete(`/api/message-templates/${templateId}`)
}

export async function listOutboundMessages({
  page = 1,
  search = '',
  channel = '',
  source = '',
  status = '',
  consentStatus = '',
  contactId = '',
  messageTemplateId = '',
  sentByUserId = '',
  createdFrom = '',
  createdTo = '',
  perPage = 20,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (channel !== '') {
    params.channel = channel
  }

  if (source !== '') {
    params.source = source
  }

  if (status !== '') {
    params.status = status
  }

  if (consentStatus !== '') {
    params.consent_status = consentStatus
  }

  if (contactId !== '') {
    params.contact_id = contactId
  }

  if (messageTemplateId !== '') {
    params.message_template_id = messageTemplateId
  }

  if (sentByUserId !== '') {
    params.sent_by_user_id = sentByUserId
  }

  if (createdFrom !== '') {
    params.created_from = createdFrom
  }

  if (createdTo !== '') {
    params.created_to = createdTo
  }

  const response = await api.get('/api/outbound-messages', {
    params,
  })

  return response.data
}

export async function getOutboundMessage(messageId) {
  const response = await api.get(
    `/api/outbound-messages/${messageId}`,
  )

  return response.data.data
}

export async function sendOutboundMessage(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/outbound-messages',
    payload,
  )

  return response.data.data
}

export async function listMessageDeliveryEvents(messageId) {
  const response = await api.get(
    `/api/outbound-messages/${messageId}/delivery-events`,
  )

  return response.data.data
}