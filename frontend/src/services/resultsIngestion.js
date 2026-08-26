import api from '../lib/api.js'

async function prepareForWrite() {
  await api.get('/sanctum/csrf-cookie')
}

function cleanParams(params = {}) {
  return Object.fromEntries(
    Object.entries(params).filter(
      ([, value]) =>
        value !== '' &&
        value !== null &&
        value !== undefined &&
        value !== false,
    ),
  )
}

function getDownloadName(disposition, fallbackName) {
  if (!disposition) {
    return fallbackName
  }

  const encodedMatch = disposition.match(/filename\*=UTF-8''([^;]+)/i)

  if (encodedMatch) {
    return decodeURIComponent(encodedMatch[1])
  }

  const regularMatch = disposition.match(/filename="?([^";]+)"?/i)

  return regularMatch?.[1] ?? fallbackName
}

function downloadBlob(blob, filename) {
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')

  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()

  window.URL.revokeObjectURL(url)
}

export async function listElectionContests(filters = {}) {
  const response = await api.get('/api/election-contests', {
    params: cleanParams({
      search: filters.search,
      status: filters.status,
      election_date_from: filters.electionDateFrom,
      election_date_to: filters.electionDateTo,
      per_page: filters.perPage,
      page: filters.page,
    }),
  })

  return response.data
}

export async function getElectionContest(contestId) {
  const response = await api.get(`/api/election-contests/${contestId}`)

  return response.data.data
}

export async function createElectionContest(payload) {
  await prepareForWrite()

  const response = await api.post('/api/election-contests', payload)

  return response.data.data
}

export async function updateElectionContest(contestId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/election-contests/${contestId}`,
    payload,
  )

  return response.data.data
}

export async function activateElectionContest(contestId) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/election-contests/${contestId}/activate`,
  )

  return response.data.data
}

export async function closeElectionContest(contestId) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/election-contests/${contestId}/close`,
  )

  return response.data.data
}

export async function deleteElectionContest(contestId) {
  await prepareForWrite()

  await api.delete(`/api/election-contests/${contestId}`)
}

export async function listTallySheets(filters = {}) {
  const response = await api.get('/api/tally-sheets', {
    params: cleanParams({
      search: filters.search,
      election_contest_id: filters.electionContestId,
      polling_center_id: filters.pollingCenterId,
      polling_station_id: filters.pollingStationId,
      status: filters.status,
      per_page: filters.perPage,
      page: filters.page,
    }),
  })

  return response.data
}

export async function getTallySheet(tallySheetId) {
  const response = await api.get(`/api/tally-sheets/${tallySheetId}`)

  return response.data.data
}

export async function createTallySheet(payload) {
  await prepareForWrite()

  const response = await api.post('/api/tally-sheets', payload)

  return response.data.data
}

export async function updateTallySheet(tallySheetId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-sheets/${tallySheetId}`,
    payload,
  )

  return response.data.data
}

export async function reviewTallySheet(tallySheetId, payload = {}) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-sheets/${tallySheetId}/review`,
    payload,
  )

  return response.data.data
}

export async function approveTallySheet(tallySheetId, payload = {}) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-sheets/${tallySheetId}/approve`,
    payload,
  )

  return response.data.data
}

export async function rejectTallySheet(tallySheetId, payload = {}) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-sheets/${tallySheetId}/reject`,
    payload,
  )

  return response.data.data
}

export async function createTallySubmission(tallySheetId, payload) {
  await prepareForWrite()

  const response = await api.post(
    `/api/tally-sheets/${tallySheetId}/submissions`,
    payload,
  )

  return response.data.data
}

export async function getTallySubmission(submissionId) {
  const response = await api.get(
    `/api/tally-submissions/${submissionId}`,
  )

  return response.data.data
}

export async function updateTallySubmission(submissionId, payload) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-submissions/${submissionId}`,
    payload,
  )

  return response.data.data
}

export async function submitTallySubmission(submissionId) {
  await prepareForWrite()

  const response = await api.patch(
    `/api/tally-submissions/${submissionId}/submit`,
  )

  return response.data.data
}

export async function deleteTallySubmission(submissionId) {
  await prepareForWrite()

  await api.delete(`/api/tally-submissions/${submissionId}`)
}

export async function uploadTallyAttachment(tallySheetId, {
  file,
  clientUuid,
  capturedAt,
  clientUpdatedAt,
}) {
  await prepareForWrite()

  const formData = new FormData()

  formData.append('file', file)

  if (clientUuid) {
    formData.append('client_uuid', clientUuid)
  }

  if (capturedAt) {
    formData.append('captured_at', capturedAt)
  }

  if (clientUpdatedAt) {
    formData.append('client_updated_at', clientUpdatedAt)
  }

  const response = await api.post(
    `/api/tally-sheets/${tallySheetId}/attachments`,
    formData,
  )

  return response.data.data
}

export async function downloadTallyAttachment(
  attachmentId,
  fallbackName = 'tally-attachment',
    ) {
  const response = await api.get(
    `/api/tally-sheet-attachments/${attachmentId}/download`,
    {
      responseType: 'blob',
    },
  )

  const filename = getDownloadName(
    response.headers['content-disposition'],
    fallbackName,
  )

  downloadBlob(response.data, filename)
}

export async function deleteTallyAttachment(attachmentId) {
  await prepareForWrite()

  await api.delete(`/api/tally-sheet-attachments/${attachmentId}`)
}

export async function getResultsAnalytics({
  electionContestId,
  pollingCenterId,
}) {
  const response = await api.get('/api/results/analytics', {
    params: cleanParams({
      election_contest_id: electionContestId,
      polling_center_id: pollingCenterId,
    }),
  })

  return response.data.data
}

export async function downloadResultsExport({
  electionContestId,
  pollingCenterId,
}) {
  const response = await api.get('/api/results/export', {
    params: cleanParams({
      election_contest_id: electionContestId,
      polling_center_id: pollingCenterId,
    }),
    responseType: 'blob',
  })

  const filename = getDownloadName(
    response.headers['content-disposition'],
    'election-results.csv',
  )

  downloadBlob(response.data, filename)
}