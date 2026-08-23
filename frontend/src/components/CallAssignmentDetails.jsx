import { useEffect, useState } from 'react'
import { getCallAssignment } from '../services/callCenter.js'

function formatLabel(value) {
  if (!value) {
    return 'Not recorded'
  }

  return value
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleString()
    : 'Not recorded'
}

function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) {
    return 'Not recorded'
  }

  if (seconds < 60) {
    return `${seconds} seconds`
  }

  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60

  return remainingSeconds > 0
    ? `${minutes} min ${remainingSeconds} sec`
    : `${minutes} min`
}

function CallAssignmentDetails({
  assignmentId,
  onClose,
}) {
  const [assignment, setAssignment] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [errorMessage, setErrorMessage] = useState('')

  useEffect(() => {
    let isCurrent = true

    async function loadAssignment() {
      setIsLoading(true)
      setErrorMessage('')

      try {
        const response = await getCallAssignment(
          assignmentId,
        )

        if (isCurrent) {
          setAssignment(response)
        }
      } catch {
        if (isCurrent) {
          setErrorMessage(
            'The call assignment details could not be loaded.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadAssignment()

    return () => {
      isCurrent = false
    }
  }, [assignmentId])

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card modal-card-wide"
        role="dialog"
        aria-modal="true"
        aria-labelledby="assignment-details-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">
              Call assignment
            </p>
            <h2 id="assignment-details-title">
              {assignment?.contact?.full_name
                ?? 'Assignment details'}
            </h2>
            {assignment?.contact?.reference_code && (
              <p className="page-description">
                {assignment.contact.reference_code}
              </p>
            )}
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onClose}
            aria-label="Close"
          >
            ×
          </button>
        </div>

        {isLoading && (
          <p className="state-message">
            Loading assignment details...
          </p>
        )}

        {!isLoading && errorMessage && (
          <div
            className="form-message error-message"
            role="alert"
          >
            {errorMessage}
          </div>
        )}

        {!isLoading && assignment && (
          <>
            <div className="incident-badge-row">
              <span
                className={`message-status-pill ${assignment.status}`}
              >
                {formatLabel(assignment.status)}
              </span>

              <span
                className={`message-status-pill ${assignment.priority}`}
              >
                {formatLabel(assignment.priority)}
              </span>
            </div>

            <div className="incident-details-grid">
              <article className="incident-detail-card">
                <span>Queue</span>
                <strong>
                  {assignment.call_queue?.name
                    ?? 'Unknown queue'}
                </strong>
                <small>
                  {assignment.call_queue?.code ?? ''}
                </small>
              </article>

              <article className="incident-detail-card">
                <span>Contact</span>
                <strong>
                  {assignment.contact?.full_name
                    ?? 'Unknown contact'}
                </strong>
                <small>
                  {assignment.contact?.phone
                    ?? 'No phone number'}
                </small>
              </article>

              <article className="incident-detail-card">
                <span>Assigned agent</span>
                <strong>
                  {assignment.assignee?.name
                    ?? 'Unassigned'}
                </strong>
                <small>
                  {assignment.assignee?.email ?? ''}
                </small>
              </article>

              <article className="incident-detail-card">
                <span>Assigned by</span>
                <strong>
                  {assignment.assigner?.name
                    ?? 'Not recorded'}
                </strong>
              </article>

              <article className="incident-detail-card">
                <span>Scheduled for</span>
                <strong>
                  {formatDate(assignment.scheduled_for)}
                </strong>
              </article>

              <article className="incident-detail-card">
                <span>Last attempted</span>
                <strong>
                  {formatDate(
                    assignment.last_attempted_at,
                  )}
                </strong>
              </article>
            </div>

            {assignment.notes && (
              <article className="incident-description-card">
                <h3>Assignment notes</h3>
                <p>{assignment.notes}</p>
              </article>
            )}

            {assignment.call_queue?.call_script && (
                <article className="incident-description-card">
                    <h3>
                    Call script: {assignment.call_queue.call_script.name}
                    </h3>

                    <p className="page-description">
                    {assignment.call_queue.call_script.code}
                    {' · '}
                    {assignment.call_queue.call_script.language_code}
                    {' · '}
                    {formatLabel(assignment.call_queue.call_script.status)}
                    </p>

                    <p className="call-script-body">
                    {assignment.call_queue.call_script.body}
                    </p>
                </article>
            )}

            <div className="details-section-heading">
              <div>
                <h3>Call history</h3>
                <p className="page-description">
                  Immutable attempts recorded for this
                  assignment.
                </p>
              </div>

              <span>
                {assignment.attempts?.length ?? 0}{' '}
                attempts
              </span>
            </div>

            {(!assignment.attempts
              || assignment.attempts.length === 0) && (
              <div className="empty-state">
                <h3>No call attempts recorded</h3>
                <p>
                  The first call attempt will appear here.
                </p>
              </div>
            )}

            {assignment.attempts?.length > 0 && (
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Reference</th>
                      <th>Outcome</th>
                      <th>Attempted</th>
                      <th>Duration</th>
                      <th>Agent</th>
                      <th>Follow-up</th>
                      <th>Notes</th>
                    </tr>
                  </thead>

                  <tbody>
                    {assignment.attempts.map((attempt) => (
                      <tr key={attempt.id}>
                        <td>
                          <strong>
                            {attempt.reference_code}
                          </strong>
                        </td>

                        <td>
                          <span
                            className={`message-status-pill ${attempt.outcome}`}
                          >
                            {formatLabel(attempt.outcome)}
                          </span>
                        </td>

                        <td>
                          {formatDate(attempt.attempted_at)}
                        </td>

                        <td>
                          {formatDuration(
                            attempt.duration_seconds,
                          )}
                        </td>

                        <td>
                          {attempt.performer?.name
                            ?? 'Unknown'}
                        </td>

                        <td>
                          {formatDate(attempt.follow_up_at)}
                        </td>

                        <td>
                          {attempt.notes
                            ?? 'No notes'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="modal-actions">
              <button
                type="button"
                className="secondary-button"
                onClick={onClose}
              >
                Close
              </button>
            </div>
          </>
        )}
      </section>
    </div>
  )
}

export default CallAssignmentDetails