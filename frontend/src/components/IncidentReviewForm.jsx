import { useState } from 'react'

const reviewStatuses = [
  { value: 'in_review', label: 'In review' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'dismissed', label: 'Dismissed' },
]

function IncidentReviewForm({ incident, onSubmit, onCancel }) {
  const [status, setStatus] = useState(
    incident.status === 'submitted'
      ? 'in_review'
      : incident.status,
  )
  const [resolutionNotes, setResolutionNotes] = useState(
    incident.resolution_notes ?? '',
  )
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    try {
      await onSubmit(status, resolutionNotes)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 409) {
        setGeneralError(
          'This incident changed. Close this form, refresh, and try again.',
        )
      } else {
        setGeneralError('The review decision could not be saved.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const notesRequired = ['resolved', 'dismissed'].includes(status)

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="incident-review-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Incident review</p>
            <h3 id="incident-review-title">Review incident</h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close review form"
          >
            ×
          </button>
        </div>

        <p className="page-description">
          {incident.reference_code} · {incident.title}
        </p>

        <div className="form-message">
          Use In review while investigating. Resolution notes are
          required when resolving or dismissing an incident.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Review decision</span>
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setErrors({})
              }}
              required
            >
              {reviewStatuses.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            {errors.status && (
              <small className="field-error">{errors.status[0]}</small>
            )}
          </label>

          <label className="form-field">
            <span>
              Resolution notes {notesRequired ? '' : '(optional)'}
            </span>
            <textarea
              value={resolutionNotes}
              onChange={(event) => {
                setResolutionNotes(event.target.value)
                setErrors({})
              }}
              maxLength="10000"
              rows="5"
              required={notesRequired}
              placeholder="Record the investigation outcome and action taken."
            />
            {errors.resolution_notes && (
              <small className="field-error">
                {errors.resolution_notes[0]}
              </small>
            )}
          </label>

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
              {isSubmitting ? 'Saving...' : 'Save review'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default IncidentReviewForm
