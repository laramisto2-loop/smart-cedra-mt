import {
  createTurnoutSnapshot,
} from './turnoutSnapshots.js'
import {
  countQueuedTurnoutSnapshots,
  listQueuedTurnoutSnapshots,
  markQueuedTurnoutAttempt,
  queueTurnoutSnapshot,
  removeQueuedTurnoutSnapshot,
} from './turnoutQueue.js'

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
    return 'Sign in again before this turnout entry can be synchronized.'
  }

  if (status === 403) {
    return 'Your account no longer has permission to record turnout.'
  }

  if (status === 409) {
    return 'The server rejected this entry because of a synchronization conflict.'
  }

  if (status === 422) {
    return 'This turnout entry contains values that are no longer valid.'
  }

  return 'The turnout entry could not be synchronized yet.'
}

export async function submitTurnoutWithOfflineFallback(
  payload,
  user,
) {
  if (navigator.onLine) {
    try {
      const snapshot = await createTurnoutSnapshot(payload)

      return {
        state: 'synced',
        snapshot,
      }
    } catch (error) {
      if (!isNetworkError(error)) {
        throw error
      }
    }
  }

  const queuedRecord = await queueTurnoutSnapshot(
    payload,
    user,
  )

  return {
    state: 'queued',
    queuedRecord,
  }
}

export async function synchronizeQueuedTurnout(user) {
  const queuedRecords =
    await listQueuedTurnoutSnapshots(user)

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
      await createTurnoutSnapshot(record.payload)
      await removeQueuedTurnoutSnapshot(record.client_uuid)
      synchronized += 1
    } catch (error) {
      if (isNetworkError(error)) {
        break
      }

      await markQueuedTurnoutAttempt(
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
    pending: await countQueuedTurnoutSnapshots(user),
  }
}

//This is the synchronization layer: online entries go directly to Laravel; offline entries enter the protected local queue and retry with their original UUID after reconnection