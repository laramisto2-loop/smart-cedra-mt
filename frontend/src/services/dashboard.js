import api from '../lib/api.js'

export async function getDashboardSummary() {
  const response = await api.get('/api/dashboard-summary')

  return response.data.data
}
