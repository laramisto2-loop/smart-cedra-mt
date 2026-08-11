import { useState } from 'react'

const taskTypes = [
  { value: 'general', label: 'General' },
  { value: 'follow_up', label: 'Follow-up' },
  { value: 'phone_call', label: 'Phone call' },
  { value: 'message', label: 'Message' },
  { value: 'field_visit', label: 'Field visit' },
  { value: 'data_entry', label: 'Data entry' },
]

const priorities = [
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
]

const statuses = [
  { value: 'pending', label: 'Pending' },
  { value: 'in_progress', label: 'In progress' },
  { value: 'cancelled', label: 'Cancelled' },
]

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function toLocalDateTime(value) {
  if (!value) {
    return ''
  }

  const date = new Date(value)
  const timezoneOffset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - timezoneOffset)
    .toISOString()
    .slice(0, 16)
}

function toApiDateTime(value) {
  if (value === '') {
    return null
  }

  return new Date(value).toISOString()
}

function areaLabel(area) {
  const parts = [
    area.district?.governorate?.name_en,
    area.district?.name_en,
    area.name_en,
  ].filter(Boolean)

  return parts.join(' — ')
}

function CampaignTaskForm({
  task = null,
  contacts = [],
  areas = [],
  assignees = [],
  canAssign = false,
  onSubmit,
  onCancel,
}) {
  const isEditing = task !== null

  const [form, setForm] = useState({
    contact_id: task?.contact_id?.toString() ?? '',
    area_id: task?.area_id?.toString() ?? '',
    assigned_to_user_id:
      task?.assigned_to_user_id?.toString() ?? '',
    title: task?.title ?? '',
    description: task?.description ?? '',
    type: task?.type ?? 'general',
    priority: task?.priority ?? 'normal',
    status: task?.status ?? 'pending',
    due_at: toLocalDateTime(task?.due_at),
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  function clearError(name) {
    setErrors((current) => ({
      ...current,
      [name]: undefined,
    }))
  }

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]: value,
    }))

    clearError(name)
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const payload = {
      contact_id:
        form.contact_id === ''
          ? null
          : Number(form.contact_id),
      area_id:
        form.area_id === ''
          ? null
          : Number(form.area_id),
      title: form.title.trim(),
      description: optionalValue(form.description),
      type: form.type,
      priority: form.priority,
      status: form.status,
      due_at: toApiDateTime(form.due_at),
    }

    if (!isEditing && canAssign) {
      payload.assigned_to_user_id =
        form.assigned_to_user_id === ''
          ? null
          : Number(form.assigned_to_user_id)
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The task could not be saved. Please try again.',
        )
      }
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
        aria-labelledby="campaign-task-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Task management</p>
            <h3 id="campaign-task-form-title">
              {isEditing ? 'Edit task' : 'Add task'}
            </h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close form"
          >
            ×
          </button>
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Task title</span>
            <input
              type="text"
              name="title"
              value={form.title}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Confirm volunteer availability"
              required
              autoFocus
            />
            {errors.title && (
              <small className="field-error">
                {errors.title[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Description (optional)</span>
            <textarea
              name="description"
              value={form.description}
              onChange={updateField}
              maxLength="5000"
              rows="3"
              placeholder="Explain what must be completed."
            />
            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Task type</span>
            <select
              name="type"
              value={form.type}
              onChange={updateField}
              required
            >
              {taskTypes.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
            {errors.type && (
              <small className="field-error">
                {errors.type[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Priority</span>
            <select
              name="priority"
              value={form.priority}
              onChange={updateField}
              required
            >
              {priorities.map((priority) => (
                <option
                  key={priority.value}
                  value={priority.value}
                >
                  {priority.label}
                </option>
              ))}
            </select>
            {errors.priority && (
              <small className="field-error">
                {errors.priority[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Status</span>
            <select
              name="status"
              value={form.status}
              onChange={updateField}
              required
            >
              {statuses.map((status) => (
                <option
                  key={status.value}
                  value={status.value}
                >
                  {status.label}
                </option>
              ))}
            </select>
            <small className="field-help">
              Use the completion action when the work is
              finished.
            </small>
            {errors.status && (
              <small className="field-error">
                {errors.status[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Due date and time (optional)</span>
            <input
              type="datetime-local"
              name="due_at"
              value={form.due_at}
              onChange={updateField}
            />
            {errors.due_at && (
              <small className="field-error">
                {errors.due_at[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Related contact (optional)</span>
            <select
              name="contact_id"
              value={form.contact_id}
              onChange={updateField}
            >
              <option value="">No related contact</option>

              {contacts.map((contact) => (
                <option key={contact.id} value={contact.id}>
                  {contact.full_name} — {contact.reference_code}
                </option>
              ))}
            </select>
            {errors.contact_id && (
              <small className="field-error">
                {errors.contact_id[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Related area (optional)</span>
            <select
              name="area_id"
              value={form.area_id}
              onChange={updateField}
            >
              <option value="">No related area</option>

              {areas.map((area) => (
                <option key={area.id} value={area.id}>
                  {areaLabel(area)}
                </option>
              ))}
            </select>
            {errors.area_id && (
              <small className="field-error">
                {errors.area_id[0]}
              </small>
            )}
          </label>

          {!isEditing && canAssign && (
            <label className="form-field">
              <span>Initial assignee (optional)</span>
              <select
                name="assigned_to_user_id"
                value={form.assigned_to_user_id}
                onChange={updateField}
              >
                <option value="">Leave unassigned</option>

                {assignees.map((assignee) => (
                  <option
                    key={assignee.id}
                    value={assignee.id}
                  >
                    {assignee.name} — {assignee.email}
                  </option>
                ))}
              </select>
              <small className="field-help">
                Assignment can also be changed after the task
                is created.
              </small>
              {errors.assigned_to_user_id && (
                <small className="field-error">
                  {errors.assigned_to_user_id[0]}
                </small>
              )}
            </label>
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
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create task'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CampaignTaskForm