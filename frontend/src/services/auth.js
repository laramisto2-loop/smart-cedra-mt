import api from '../lib/api.js'

export async function getAuthenticatedUser() {
  const response = await api.get('/api/user')

  return response.data.data
}

export async function login(credentials) {
  await api.get('/sanctum/csrf-cookie')

  const response = await api.post('/login', credentials)

  return response.data.data
}

export async function logout() {
  await api.post('/logout')
}