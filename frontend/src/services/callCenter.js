import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

function cleanParams(params) {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => (
      value !== ''
      && value !== null
      && value !== undefined
      && value !== false
    )),
  )
}

// Call scripts

export async function listCallScripts({
  page = 1,
  search = '',
  languageCode = '',
  status = '',
  perPage = 20,
} = {}) {
  const response = await api.get('/api/call-scripts', {
    params: cleanParams({
      page,
      per_page: perPage,
      search: search.trim(),
      language_code: languageCode,
      status,
    }),
  })

  return response.data
}

export async function getCallScript(scriptId) {
  const response = await api.get(
    `/api/call-scripts/${scriptId}`,
  )

  return response.data.data
}

export async function createCallScript(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/call-scripts',
    payload,
  )

  return response.data.data
}

export async function updateCallScript(scriptId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/call-scripts/${scriptId}`,
    payload,
  )

  return response.data.data
}

export async function activateCallScript(scriptId, status) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/call-scripts/${scriptId}/activate`,
    { status },
  )

  return response.data.data
}

export async function deleteCallScript(scriptId) {
  await prepareForWrite()

  await api.delete(`/api/call-scripts/${scriptId}`)
}

// Call queues

export async function listCallQueues({
  page = 1,
  search = '',
  callScriptId = '',
  createdByUserId = '',
  priority = '',
  status = '',
  startsFrom = '',
  startsTo = '',
  perPage = 20,
} = {}) {
  const response = await api.get('/api/call-queues', {
    params: cleanParams({
      page,
      per_page: perPage,
      search: search.trim(),
      call_script_id: callScriptId,
      created_by_user_id: createdByUserId,
      priority,
      status,
      starts_from: startsFrom,
      starts_to: startsTo,
    }),
  })

  return response.data
}

export async function getCallQueue(queueId) {
  const response = await api.get(
    `/api/call-queues/${queueId}`,
  )

  return response.data.data
}

export async function createCallQueue(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/call-queues',
    payload,
  )

  return response.data.data
}

export async function updateCallQueue(queueId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/call-queues/${queueId}`,
    payload,
  )

  return response.data.data
}

export async function assignCallQueue(queueId, payload) {
  await prepareForWrite()

  const response = await api.post(
    `/api/call-queues/${queueId}/assign`,
    payload,
  )

  return response.data.data
}

export async function deleteCallQueue(queueId) {
  await prepareForWrite()

  await api.delete(`/api/call-queues/${queueId}`)
}

// Call assignments

export async function listCallAssignments({
  page = 1,
  search = '',
  mine = false,
  unassigned = false,
  callQueueId = '',
  contactId = '',
  assignedToUserId = '',
  status = '',
  priority = '',
  scheduledFrom = '',
  scheduledTo = '',
  perPage = 20,
} = {}) {
  const response = await api.get('/api/call-assignments', {
    params: cleanParams({
      page,
      per_page: perPage,
      search: search.trim(),
      mine: mine ? 1 : false,
      unassigned: unassigned ? 1 : false,
      call_queue_id: callQueueId,
      contact_id: contactId,
      assigned_to_user_id: assignedToUserId,
      status,
      priority,
      scheduled_from: scheduledFrom,
      scheduled_to: scheduledTo,
    }),
  })

  return response.data
}

export async function getCallAssignment(assignmentId) {
  const response = await api.get(
    `/api/call-assignments/${assignmentId}`,
  )

  return response.data.data
}

export async function claimCallAssignment(assignmentId) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/call-assignments/${assignmentId}/claim`,
  )

  return response.data.data
}

export async function updateCallAssignment(
  assignmentId,
  payload,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/call-assignments/${assignmentId}`,
    payload,
  )

  return response.data.data
}

// Call attempts

export async function listCallAttempts({
  page = 1,
  search = '',
  mine = false,
  callAssignmentId = '',
  performedByUserId = '',
  outcome = '',
  attemptedFrom = '',
  attemptedTo = '',
  perPage = 20,
} = {}) {
  const response = await api.get('/api/call-attempts', {
    params: cleanParams({
      page,
      per_page: perPage,
      search: search.trim(),
      mine: mine ? 1 : false,
      call_assignment_id: callAssignmentId,
      performed_by_user_id: performedByUserId,
      outcome,
      attempted_from: attemptedFrom,
      attempted_to: attemptedTo,
    }),
  })

  return response.data
}

export async function getCallAttempt(attemptId) {
  const response = await api.get(
    `/api/call-attempts/${attemptId}`,
  )

  return response.data.data
}

export async function createCallAttempt(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/call-attempts',
    payload,
  )

  return response.data.data
}