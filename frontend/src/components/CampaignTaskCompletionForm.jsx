import { useState } from 'react'

function CampaignTaskCompletionForm({
  task,
  onSubmit,
  onCancel,
}) {
  const [completionNotes, setCompletionNotes] = useState(
    task.completion_notes ?? '',
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
      await onSubmit(completionNotes)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The task could not be completed. Please try again.',
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card confirmation-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="task-completion-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Task workflow</p>
            <h3 id="task-completion-title">
              Complete task
            </h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close completion form"
          >
            ×
          </button>
        </div>

        <p className="page-description">{task.title}</p>

        <div className="form-message">
          Completing this task records the completion time and
          closes the assignment.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Completion notes (optional)</span>
            <textarea
              value={completionNotes}
              onChange={(event) => {
                setCompletionNotes(event.target.value)
                setErrors({})
              }}
              maxLength="5000"
              rows="4"
              placeholder="Record what was completed or any relevant result."
              autoFocus
            />

            {errors.completion_notes && (
              <small className="field-error">
                {errors.completion_notes[0]}
              </small>
            )}

            {errors.status && (
              <small className="field-error">
                {errors.status[0]}
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
              {isSubmitting
                ? 'Completing...'
                : 'Complete task'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CampaignTaskCompletionForm