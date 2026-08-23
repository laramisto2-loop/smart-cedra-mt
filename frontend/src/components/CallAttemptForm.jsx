import { useState } from 'react'

const OUTCOMES = [
  ['completed', 'Completed'],
  ['no_answer', 'No answer'],
  ['busy', 'Busy'],
  ['voicemail', 'Voicemail'],
  ['wrong_number', 'Wrong number'],
  ['declined', 'Declined'],
  ['callback_requested', 'Callback requested'],
  ['failed', 'Failed'],
]

function toLocalDateTimeInput(date = new Date()) {
  const offset = date.getTimezoneOffset()
  const localDate = new Date(
    date.getTime() - offset * 60 * 1000,
  )

  return localDate.toISOString().slice(0, 16)
}

function validationMessage(error) {
  const errors = error.response?.data?.errors

  if (errors) {
    return Object.values(errors).flat().join(' ')
  }

  return (
    error.response?.data?.message
    ?? 'The call attempt could not be recorded.'
  )
}

function CallAttemptForm({
  assignment,
  onSubmit,
  onCancel,
}) {
  const [outcome, setOutcome] = useState('completed')
  const [durationMinutes, setDurationMinutes] =
    useState('')
  const [attemptedAt, setAttemptedAt] = useState(
    toLocalDateTimeInput(),
  )
  const [followUpAt, setFollowUpAt] = useState('')
  const [notes, setNotes] = useState('')
  const [isSubmitting, setIsSubmitting] =
    useState(false)
  const [errorMessage, setErrorMessage] = useState('')

  const requiresFollowUp =
    outcome === 'callback_requested'

  async function handleSubmit(event) {
    event.preventDefault()
    setErrorMessage('')

    if (requiresFollowUp && !followUpAt) {
      setErrorMessage(
        'Select a follow-up date and time for the requested callback.',
      )
      return
    }

    setIsSubmitting(true)

    try {
      const durationSeconds =
        durationMinutes === ''
          ? null
          : Math.round(Number(durationMinutes) * 60)

      await onSubmit({
        call_assignment_id: assignment.id,
        client_uuid: crypto.randomUUID(),
        outcome,
        duration_seconds: durationSeconds,
        notes: notes.trim() || null,
        attempted_at: new Date(attemptedAt).toISOString(),
        follow_up_at: requiresFollowUp
          ? new Date(followUpAt).toISOString()
          : null,
      })
    } catch (error) {
      setErrorMessage(validationMessage(error))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="call-attempt-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Call center activity</p>
            <h2 id="call-attempt-title">
              Record call attempt
            </h2>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            aria-label="Close"
            disabled={isSubmitting}
          >
            ×
          </button>
        </div>

        <p className="page-description">
          Record the result of the call with{' '}
          <strong>
            {assignment.contact?.full_name
              ?? 'the selected contact'}
          </strong>
          . Call history cannot be edited or deleted later.
        </p>

        <form
          className="modal-form"
          onSubmit={handleSubmit}
        >
          <div className="incident-form-grid">
            <label className="form-field">
              <span>Call outcome</span>
              <select
                value={outcome}
                onChange={(event) => {
                  const nextOutcome = event.target.value
                  setOutcome(nextOutcome)

                  if (
                    nextOutcome !== 'callback_requested'
                  ) {
                    setFollowUpAt('')
                  }
                }}
                disabled={isSubmitting}
              >
                {OUTCOMES.map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>

            <label className="form-field">
              <span>Duration in minutes (optional)</span>
              <input
                type="number"
                min="0"
                step="1"
                value={durationMinutes}
                onChange={(event) =>
                  setDurationMinutes(event.target.value)
                }
                placeholder="Example: 5"
                disabled={isSubmitting}
              />
            </label>

            <label className="form-field">
              <span>Date and time attempted</span>
              <input
                type="datetime-local"
                value={attemptedAt}
                onChange={(event) =>
                  setAttemptedAt(event.target.value)
                }
                required
                disabled={isSubmitting}
              />
            </label>

            {requiresFollowUp && (
              <label className="form-field">
                <span>Follow-up date and time</span>
                <input
                  type="datetime-local"
                  value={followUpAt}
                  min={attemptedAt}
                  onChange={(event) =>
                    setFollowUpAt(event.target.value)
                  }
                  required
                  disabled={isSubmitting}
                />
              </label>
            )}
          </div>

          <label className="form-field">
            <span>Call notes (optional)</span>
            <textarea
              rows="5"
              maxLength="5000"
              value={notes}
              onChange={(event) =>
                setNotes(event.target.value)
              }
              placeholder="Summarize the conversation or explain the outcome."
              disabled={isSubmitting}
            />
          </label>

          {requiresFollowUp && (
            <div className="form-message">
              A campaign follow-up task will be generated
              automatically for the selected date and time.
            </div>
          )}

          {errorMessage && (
            <div
              className="form-message error-message"
              role="alert"
            >
              {errorMessage}
            </div>
          )}

          <div className="modal-actions">
            <button
              type="button"
              className="secondary-button"
              onClick={onCancel}
              disabled={isSubmitting}
            >
              Cancel
            </button>

            <button
              type="submit"
              className="primary-button"
              disabled={isSubmitting}
            >
              {isSubmitting
                ? 'Recording...'
                : 'Record attempt'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CallAttemptForm
