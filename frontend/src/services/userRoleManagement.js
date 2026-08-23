import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

export async function listUsers({
  page = 1,
  search = '',
  roleId = '',
  perPage = 20,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (roleId !== '') {
    params.role_id = roleId
  }

  const response = await api.get('/api/users', {
    params,
  })

  return response.data
}

export async function getUser(userId) {
  const response = await api.get(`/api/users/${userId}`)

  return response.data.data
}

export async function createUser(payload) {
  await prepareForWrite()

  const response = await api.post('/api/users', payload)

  return response.data.data
}

export async function updateUser(userId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/users/${userId}`,
    payload,
  )

  return response.data.data
}

export async function syncUserRoles(userId, roleIds) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/users/${userId}/roles`,
    {
      role_ids: roleIds,
    },
  )

  return response.data.data
}

export async function deleteUser(userId) {
  await prepareForWrite()

  await api.delete(`/api/users/${userId}`)
}

export async function listRoles({
  page = 1,
  search = '',
  permissionId = '',
  perPage = 100,
} = {}) {
  const params = {
    page,
    per_page: perPage,
  }

  if (search.trim() !== '') {
    params.search = search.trim()
  }

  if (permissionId !== '') {
    params.permission_id = permissionId
  }

  const response = await api.get('/api/roles', {
    params,
  })

  return response.data
}

export async function getRole(roleId) {
  const response = await api.get(`/api/roles/${roleId}`)

  return response.data.data
}

export async function createRole(payload) {
  await prepareForWrite()

  const response = await api.post('/api/roles', payload)

  return response.data.data
}

export async function updateRole(roleId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/roles/${roleId}`,
    payload,
  )

  return response.data.data
}

export async function syncRolePermissions(
  roleId,
  permissionIds,
) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/roles/${roleId}/permissions`,
    {
      permission_ids: permissionIds,
    },
  )

  return response.data.data
}

export async function deleteRole(roleId) {
  await prepareForWrite()

  await api.delete(`/api/roles/${roleId}`)
}

export async function listPermissions() {
  const response = await api.get('/api/roles/permissions')

  return response.data.data
}