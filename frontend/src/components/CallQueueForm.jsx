import { useEffect, useState } from 'react'
import { listCallScripts } from '../services/callCenter.js'

const priorities = [
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
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
  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset)
    .toISOString()
    .slice(0, 16)
}

function CallQueueForm({
  queue = null,
  onSubmit,
  onCancel,
}) {
  const isEditing = queue !== null

  const [scripts, setScripts] = useState([])
  const [isLoadingScripts, setIsLoadingScripts] =
    useState(true)

  const [form, setForm] = useState({
    name: queue?.name ?? '',
    code: queue?.code ?? '',
    call_script_id:
      queue?.call_script_id?.toString() ?? '',
    description: queue?.description ?? '',
    priority: queue?.priority ?? 'normal',
    status: queue?.status ?? 'draft',
    starts_at: toLocalDateTime(queue?.starts_at),
    ends_at: toLocalDateTime(queue?.ends_at),
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    let isCurrent = true

    async function loadScripts() {
      try {
        const response = await listCallScripts({
          status: 'active',
          perPage: 100,
        })

        if (isCurrent) {
          setScripts(response.data ?? [])
        }
      } catch {
        if (isCurrent) {
          setGeneralError(
            'Active call scripts could not be loaded.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoadingScripts(false)
        }
      }
    }

    loadScripts()

    return () => {
      isCurrent = false
    }
  }, [])

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]:
        name === 'code' ? value.toUpperCase() : value,
    }))

    setErrors((current) => ({
      ...current,
      [name]: undefined,
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      code: form.code.trim().toUpperCase(),
      call_script_id:
        form.call_script_id === ''
          ? null
          : Number(form.call_script_id),
      description: optionalValue(form.description),
      priority: form.priority,
      status: form.status,
      starts_at: form.starts_at || null,
      ends_at: form.ends_at || null,
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to save call queues.',
        )
      } else {
        setGeneralError(
          'The call queue could not be saved. Please try again.',
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
        aria-labelledby="call-queue-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Call center queues</p>
            <h3 id="call-queue-form-title">
              {isEditing
                ? 'Edit call queue'
                : 'Create call queue'}
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

        <div className="info-message">
          Active queues require an active call script. Contacts
          can be assigned after the queue is created.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Queue name</span>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Volunteer confirmation calls"
              required
              autoFocus
            />
            {errors.name && (
              <small className="field-error">
                {errors.name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Queue code</span>
            <input
              type="text"
              name="code"
              value={form.code}
              onChange={updateField}
              maxLength="50"
              placeholder="VOLUNTEER_CONFIRMATION"
              required
            />
            <small className="field-help">
              Use uppercase letters, numbers, underscores, or
              hyphens.
            </small>
            {errors.code && (
              <small className="field-error">
                {errors.code[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Call script</span>
            <select
              name="call_script_id"
              value={form.call_script_id}
              onChange={updateField}
              required={form.status === 'active'}
              disabled={isLoadingScripts}
            >
              <option value="">
                {isLoadingScripts
                  ? 'Loading active scripts...'
                  : 'No script selected'}
              </option>

              {scripts.map((script) => (
                <option key={script.id} value={script.id}>
                  {script.name} — {script.language_code}
                </option>
              ))}
            </select>
            {errors.call_script_id && (
              <small className="field-error">
                {errors.call_script_id[0]}
              </small>
            )}
          </label>

          <div className="incident-form-grid">
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
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                {isEditing && (
                  <>
                    <option value="paused">Paused</option>
                    <option value="completed">
                      Completed
                    </option>
                    <option value="archived">
                      Archived
                    </option>
                  </>
                )}
              </select>
              {errors.status && (
                <small className="field-error">
                  {errors.status[0]}
                </small>
              )}
            </label>
          </div>

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Starts at (optional)</span>
              <input
                type="datetime-local"
                name="starts_at"
                value={form.starts_at}
                onChange={updateField}
              />
              {errors.starts_at && (
                <small className="field-error">
                  {errors.starts_at[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Ends at (optional)</span>
              <input
                type="datetime-local"
                name="ends_at"
                value={form.ends_at}
                onChange={updateField}
                min={form.starts_at || undefined}
              />
              {errors.ends_at && (
                <small className="field-error">
                  {errors.ends_at[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Description (optional)</span>
            <textarea
              name="description"
              value={form.description}
              onChange={updateField}
              maxLength="5000"
              rows="4"
              placeholder="Describe the purpose and target contacts for this queue."
            />
            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
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
              disabled={isSubmitting || isLoadingScripts}
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create queue'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CallQueueForm