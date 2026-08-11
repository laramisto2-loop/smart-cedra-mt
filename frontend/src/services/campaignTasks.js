import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listCampaignTasks({
  page = 1,
  search = '',
  type = '',
  priority = '',
  status = '',
  assignedToUserId = '',
  contactId = '',
  areaId = '',
  dueFrom = '',
  dueTo = '',
  mine = false,
  overdue = false,
  perPage = 20,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (type !== '') {
    params.type = type
  }

  if (priority !== '') {
    params.priority = priority
  }

  if (status !== '') {
    params.status = status
  }

  if (assignedToUserId !== '') {
    params.assigned_to_user_id = assignedToUserId
  }

  if (contactId !== '') {
    params.contact_id = contactId
  }

  if (areaId !== '') {
    params.area_id = areaId
  }

  if (dueFrom !== '') {
    params.due_from = dueFrom
  }

  if (dueTo !== '') {
    params.due_to = dueTo
  }

  if (mine) {
    params.mine = 1
  }

  if (overdue) {
    params.overdue = 1
  }

  const response = await api.get('/api/campaign-tasks', {
    params,
  })

  return response.data
}

export async function listTaskAssignees() {
  const response = await api.get(
    '/api/campaign-tasks/assignees',
  )

  return response.data.data
}

export async function getCampaignTask(taskId) {
  const response = await api.get(
    `/api/campaign-tasks/${taskId}`,
  )

  return response.data.data
}

export async function createCampaignTask(payload) {
  await prepareForWrite()

  const response = await api.post(
    '/api/campaign-tasks',
    payload,
  )

  return response.data.data
}

export async function updateCampaignTask(taskId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/campaign-tasks/${taskId}`,
    payload,
  )

  return response.data.data
}

export async function assignCampaignTask(
  taskId,
  assignedToUserId,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/campaign-tasks/${taskId}/assign`,
    {
      assigned_to_user_id: assignedToUserId,
    },
  )

  return response.data.data
}

export async function completeCampaignTask(
  taskId,
  completionNotes = '',
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/campaign-tasks/${taskId}/complete`,
    {
      completion_notes:
        completionNotes.trim() === ''
          ? null
          : completionNotes.trim(),
    },
  )

  return response.data.data
}

export async function deleteCampaignTask(taskId) {
  await prepareForWrite()

  await api.delete(`/api/campaign-tasks/${taskId}`)
}