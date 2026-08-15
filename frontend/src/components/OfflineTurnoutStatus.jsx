import {
  useCallback,
  useEffect,
  useRef,
  useState,
} from 'react'
import {
  listQueuedTurnoutSnapshots,
  TURNOUT_QUEUE_CHANGED,
} from '../services/turnoutQueue.js'
import {
  synchronizeQueuedTurnout,
} from '../services/turnoutSync.js'

function pluralizeEntries(count) {
  return `${count} entr${count === 1 ? 'y' : 'ies'}`
}

function OfflineTurnoutStatus({
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
      const records =
        await listQueuedTurnoutSnapshots(user)

      setQueuedRecords(records)
    } catch {
      setError(
        'The offline turnout queue could not be read.',
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
          await synchronizeQueuedTurnout(user)

        await refreshQueue()

        if (result.synchronized > 0) {
          setMessage(
            `${pluralizeEntries(
              result.synchronized,
            )} synchronized successfully.`,
          )

          onSynchronized?.()
        } else if (result.failed > 0) {
          setMessage('')
        } else if (!automatic) {
          setMessage(
            'No turnout entries are waiting to synchronize.',
          )
        }

        if (result.failed > 0) {
          setError(
            `${pluralizeEntries(
              result.failed,
            )} could not be synchronized. Check the saved values and try again.`,
          )
        }
      } catch {
        setError(
          'Queued turnout entries could not be synchronized yet.',
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
        'You are offline. New turnout entries will remain in this browser until the connection returns.',
      )
    }

    function handleQueueChanged() {
      refreshQueue()
    }

    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    window.addEventListener(
      TURNOUT_QUEUE_CHANGED,
      handleQueueChanged,
    )

    return () => {
      window.clearTimeout(initialCheck)
      window.removeEventListener('online', handleOnline)
      window.removeEventListener('offline', handleOffline)
      window.removeEventListener(
        TURNOUT_QUEUE_CHANGED,
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
              ? `${pluralizeEntries(
                  queuedCount,
                )} waiting to synchronize.`
              : isOnline
                ? 'Turnout totals will be sent immediately.'
                : 'New turnout totals will be kept locally in this browser.'}
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
            A queued turnout entry needs another synchronization
            attempt.
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

export default OfflineTurnoutStatus

//It deliberately reuses the existing incident status CSS, so we don’t need to duplicate styles