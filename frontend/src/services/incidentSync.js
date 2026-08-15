import { createIncident } from './incidents.js'
import {
  countQueuedIncidents,
  listQueuedIncidents,
  markQueuedIncidentAttempt,
  queueIncident,
  removeQueuedIncident,
} from './incidentQueue.js'

function isNetworkError(error) {
  return (
    !navigator.onLine ||
    !error.response ||
    error.code === 'ERR_NETWORK'
  )
}

function syncErrorMessage(error) {
  const status = error.response?.status

  if (status === 401) {
    return 'Sign in again before this report can be synchronized.'
  }

  if (status === 403) {
    return 'Your account no longer has permission to submit this report.'
  }

  if (status === 409) {
    return 'The server rejected this report because of a synchronization conflict.'
  }

  if (status === 422) {
    return 'This report contains values that are no longer valid.'
  }

  return 'The report could not be synchronized yet.'
}

export async function submitIncidentWithOfflineFallback(
  payload,
  user,
) {
  if (navigator.onLine) {
    try {
      const incident = await createIncident(payload)

      return {
        state: 'synced',
        incident,
      }
    } catch (error) {
      if (!isNetworkError(error)) {
        throw error
      }
    }
  }

  const queuedRecord = await queueIncident(payload, user)

  return {
    state: 'queued',
    queuedRecord,
  }
}

export async function synchronizeQueuedIncidents(user) {
  const queuedRecords = await listQueuedIncidents(user)

  if (!navigator.onLine || queuedRecords.length === 0) {
    return {
      synchronized: 0,
      failed: 0,
      pending: queuedRecords.length,
    }
  }

  let synchronized = 0
  let failed = 0

  for (const record of queuedRecords) {
    try {
      await createIncident(record.payload)
      await removeQueuedIncident(record.client_uuid)
      synchronized += 1
    } catch (error) {
      if (isNetworkError(error)) {
        break
      }

      await markQueuedIncidentAttempt(
        record.client_uuid,
        syncErrorMessage(error),
      )

      failed += 1

      if (error.response?.status === 401) {
        break
      }
    }
  }

  return {
    synchronized,
    failed,
    pending: await countQueuedIncidents(user),
  }
}

//this layer decides whether to send a new report immediately or place it in the local queue. When internet returns, it safely retries queued reports one at a time
//The unchanged client_uuid travels from the form, into browser storage, and finally to Laravel. If synchronization is retried, the backend recognizes the same report instead of creating a duplicate