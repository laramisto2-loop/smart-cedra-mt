import { useState } from 'react'

function roleLabel(roles = []) {
  if (roles.length === 0) {
    return 'No role'
  }

  return roles
    .map((role) => role.replaceAll('_', ' '))
    .join(', ')
}

function CampaignTaskAssignmentForm({
  task,
  assignees = [],
  onSubmit,
  onCancel,
}) {
  const [assigneeId, setAssigneeId] = useState(
    task.assigned_to_user_id?.toString() ?? '',
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
      await onSubmit(
        assigneeId === '' ? null : Number(assigneeId),
      )
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The task assignment could not be saved.',
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
        aria-labelledby="task-assignment-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Task assignment</p>
            <h3 id="task-assignment-title">
              Assign task
            </h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close assignment form"
          >
            ×
          </button>
        </div>

        <p className="page-description">{task.title}</p>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Assigned team member</span>
            <select
              value={assigneeId}
              onChange={(event) => {
                setAssigneeId(event.target.value)
                setErrors({})
              }}
            >
              <option value="">Leave task unassigned</option>

              {assignees.map((assignee) => (
                <option
                  key={assignee.id}
                  value={assignee.id}
                >
                  {assignee.name} — {assignee.email} (
                  {roleLabel(assignee.roles)})
                </option>
              ))}
            </select>

            <small className="field-help">
              Only users belonging to the active tenant are
              available.
            </small>

            {errors.assigned_to_user_id && (
              <small className="field-error">
                {errors.assigned_to_user_id[0]}
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
                ? 'Saving...'
                : 'Save assignment'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CampaignTaskAssignmentForm