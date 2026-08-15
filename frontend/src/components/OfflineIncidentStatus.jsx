import {
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react'
import {
  INCIDENT_QUEUE_CHANGED,
  listQueuedIncidents,
} from '../services/incidentQueue.js'
import {
  synchronizeQueuedIncidents,
} from '../services/incidentSync.js'

function pluralizeReports(count) {
  return `${count} report${count === 1 ? '' : 's'}`
}

function OfflineIncidentStatus({
  user,
  onSynchronized,
}) {
  const [isOnline, setIsOnline] = useState(
    navigator.onLine,
  )
  const [queuedRecords, setQueuedRecords] = useState([])
  const [isSynchronizing, setIsSynchronizing] =
    useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const synchronizationRef = useRef(false)

  const refreshQueue = useCallback(async () => {
    try {
      const records = await listQueuedIncidents(user)

      setQueuedRecords(records)
    } catch {
      setError(
        'The offline incident queue could not be read.',
      )
    }
  }, [user])

  const synchronize = useCallback(
    async (automatic = false) => {
      if (
        !navigator.onLine ||
        synchronizationRef.current
      ) {
        return
      }

      synchronizationRef.current = true
      setIsSynchronizing(true)
      setError('')

      try {
        const result =
          await synchronizeQueuedIncidents(user)

        await refreshQueue()

        if (result.synchronized > 0) {
          setMessage(
            `${pluralizeReports(
              result.synchronized,
            )} synchronized successfully.`,
          )

          onSynchronized?.()
        } else if (result.failed > 0) {
          setMessage('')
        } else if (!automatic) {
          setMessage(
            'No reports are waiting to synchronize.',
          )
        }

        if (result.failed > 0) {
          setError(
            `${pluralizeReports(
              result.failed,
            )} could not be synchronized. Review the queued report status and try again.`,
          )
        }
      } catch {
        setError(
          'Queued reports could not be synchronized yet.',
        )
      } finally {
        synchronizationRef.current = false
        setIsSynchronizing(false)
      }
    },
    [
      onSynchronized,
      refreshQueue,
      user,
    ],
  )

  useEffect(() => {
    const initialCheck = window.setTimeout(() => {
      refreshQueue()

      if (navigator.onLine) {
        synchronize(true)
      }
    }, 250)

    function handleOnline() {
      setIsOnline(true)
      setMessage('Connection restored.')
      synchronize(true)
    }

    function handleOffline() {
      setIsOnline(false)
      setMessage(
        'You are offline. New reports will remain in this browser until the connection returns.',
      )
    }

    function handleQueueChanged() {
      refreshQueue()
    }

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    window.addEventListener(
      INCIDENT_QUEUE_CHANGED,
      handleQueueChanged,
    )

    return () => {
      window.clearTimeout(initialCheck)
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
      window.removeEventListener(
        INCIDENT_QUEUE_CHANGED,
        handleQueueChanged,
      )
    }
  }, [refreshQueue, synchronize])

  const queuedCount = queuedRecords.length
  const hasFailedRecord = queuedRecords.some(
    (record) => record.last_error,
  )

  return (
    <section
      className={`offline-incident-status ${
        isOnline ? 'online' : 'offline'
      }`}
      aria-live="polite"
    >
      <div className="offline-status-summary">
        <span
          className="offline-status-indicator"
          aria-hidden="true"
        />

        <div>
          <strong>
            {isOnline ? 'Online' : 'Offline'}
          </strong>

          <p>
            {queuedCount > 0
              ? `${pluralizeReports(
                  queuedCount,
                )} waiting to synchronize.`
              : isOnline
                ? 'Incident reports will be sent immediately.'
                : 'New reports will be kept locally in this browser.'}
          </p>
        </div>
      </div>

      <div className="offline-status-actions">
        {message && (
          <span className="offline-status-message">
            {message}
          </span>
        )}

        {hasFailedRecord && !error && (
          <span className="offline-status-warning">
            A queued report needs another synchronization attempt.
          </span>
        )}

        {error && (
          <span
            className="offline-status-warning"
            role="alert"
          >
            {error}
          </span>
        )}

        {isOnline && queuedCount > 0 && (
          <button
            type="button"
            className="secondary-button"
            onClick={() => synchronize(false)}
            disabled={isSynchronizing}
          >
            {isSynchronizing
              ? 'Synchronizing...'
              : 'Sync now'}
          </button>
        )}
      </div>
    </section>
  )
}

export default OfflineIncidentStatus

//This component tells the user whether ElectoFlow is online, shows how many reports are waiting, automatically synchronizes after reconnection, and provides a manual retry button
//This uses an internal lock to prevent duplicate synchronization requests