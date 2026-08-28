import api from '../lib/api.js'

export async function listPlatformTenants(filters = {}) {
  const response = await api.get('/api/platform/tenants', {
    params: filters,
  })

  return {
    items: response.data.data,
    meta: response.data.meta,
    links: response.data.links,
  }
}

export async function createPlatformTenant(payload) {
  const response = await api.post(
    '/api/platform/tenants',
    payload,
  )

  return response.data.data
}

export async function updatePlatformTenant(tenantId, payload) {
  const response = await api.patch(
    `/api/platform/tenants/${tenantId}`,
    payload,
  )

  return response.data.data
}

export async function updatePlatformTenantStatus(
  tenantId,
  status,
) {
  const response = await api.patch(
    `/api/platform/tenants/${tenantId}/status`,
    { status },
  )

  return response.data.data
}