const DATABASE_NAME = 'electoflow-turnout-operations'
const DATABASE_VERSION = 1
const TURNOUT_STORE = 'turnout-snapshots'

export const TURNOUT_QUEUE_CHANGED =
  'electoflow:turnout-queue-changed'

function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(
      DATABASE_NAME,
      DATABASE_VERSION,
    )

    request.onupgradeneeded = () => {
      const database = request.result

      if (!database.objectStoreNames.contains(TURNOUT_STORE)) {
        const store = database.createObjectStore(
          TURNOUT_STORE,
          {
            keyPath: 'client_uuid',
          },
        )

        store.createIndex('tenant_id', 'tenant_id')
        store.createIndex('user_id', 'user_id')
        store.createIndex('queued_at', 'queued_at')
      }
    }

    request.onsuccess = () => {
      resolve(request.result)
    }

    request.onerror = () => {
      reject(
        request.error ??
          new Error(
            'The offline turnout database could not be opened.',
          ),
      )
    }

    request.onblocked = () => {
      reject(
        new Error(
          'The offline turnout database is blocked by another ElectoFlow tab.',
        ),
      )
    }
  })
}

function notifyQueueChanged() {
  window.dispatchEvent(
    new CustomEvent(TURNOUT_QUEUE_CHANGED),
  )
}

function userIdentity(user) {
  return {
    tenantId: Number(user.tenant.id),
    userId: Number(user.id),
  }
}

export async function queueTurnoutSnapshot(payload, user) {
  const database = await openDatabase()
  const { tenantId, userId } = userIdentity(user)

  const record = {
    client_uuid: payload.client_uuid,
    tenant_id: tenantId,
    user_id: userId,
    payload,
    queued_at: new Date().toISOString(),
    attempt_count: 0,
    last_attempt_at: null,
    last_error: null,
  }

  await new Promise((resolve, reject) => {
    const transaction = database.transaction(
      TURNOUT_STORE,
      'readwrite',
    )

    transaction.objectStore(TURNOUT_STORE).put(record)

    transaction.oncomplete = () => resolve()
    transaction.onerror = () => reject(transaction.error)
    transaction.onabort = () => reject(transaction.error)
  })

  database.close()
  notifyQueueChanged()

  return record
}

export async function listQueuedTurnoutSnapshots(user) {
  const database = await openDatabase()
  const { tenantId, userId } = userIdentity(user)

  const records = await new Promise((resolve, reject) => {
    const transaction = database.transaction(
      TURNOUT_STORE,
      'readonly',
    )

    const request = transaction
      .objectStore(TURNOUT_STORE)
      .getAll()

    request.onsuccess = () => resolve(request.result ?? [])
    request.onerror = () => reject(request.error)
  })

  database.close()

  return records
    .filter(
      (record) =>
        record.tenant_id === tenantId &&
        record.user_id === userId,
    )
    .sort((first, second) =>
      first.queued_at.localeCompare(second.queued_at),
    )
}

export async function countQueuedTurnoutSnapshots(user) {
  const records = await listQueuedTurnoutSnapshots(user)

  return records.length
}

export async function removeQueuedTurnoutSnapshot(
  clientUuid,
) {
  const database = await openDatabase()

  await new Promise((resolve, reject) => {
    const transaction = database.transaction(
      TURNOUT_STORE,
      'readwrite',
    )

    transaction
      .objectStore(TURNOUT_STORE)
      .delete(clientUuid)

    transaction.oncomplete = () => resolve()
    transaction.onerror = () => reject(transaction.error)
    transaction.onabort = () => reject(transaction.error)
  })

  database.close()
  notifyQueueChanged()
}

export async function markQueuedTurnoutAttempt(
  clientUuid,
  errorMessage,
) {
  const database = await openDatabase()

  await new Promise((resolve, reject) => {
    const transaction = database.transaction(
      TURNOUT_STORE,
      'readwrite',
    )

    const store = transaction.objectStore(TURNOUT_STORE)
    const request = store.get(clientUuid)

    request.onsuccess = () => {
      const record = request.result

      if (!record) {
        return
      }

      store.put({
        ...record,
        attempt_count: record.attempt_count + 1,
        last_attempt_at: new Date().toISOString(),
        last_error: errorMessage,
      })
    }

    request.onerror = () => reject(request.error)
    transaction.oncomplete = () => resolve()
    transaction.onerror = () => reject(transaction.error)
    transaction.onabort = () => reject(transaction.error)
  })

  database.close()
  notifyQueueChanged()
}

//This uses a separate browser database from incident reports, preventing a database-version conflict with the existing incident queue