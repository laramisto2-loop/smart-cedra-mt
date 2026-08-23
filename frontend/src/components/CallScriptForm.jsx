import { useState } from 'react'

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function CallScriptForm({
  script = null,
  onSubmit,
  onCancel,
}) {
  const isEditing = script !== null

  const [form, setForm] = useState({
    name: script?.name ?? '',
    code: script?.code ?? '',
    language_code: script?.language_code ?? 'en',
    description: script?.description ?? '',
    body: script?.body ?? '',
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
      [name]:
        name === 'code' ? value.toUpperCase() : value,
    }))

    clearError(name)
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const payload = {
      name: form.name.trim(),
      code: form.code.trim().toUpperCase(),
      language_code: form.language_code.trim(),
      description: optionalValue(form.description),
      body: form.body.trim(),
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to save call scripts.',
        )
      } else {
        setGeneralError(
          'The call script could not be saved. Please try again.',
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
        aria-labelledby="call-script-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Call center scripts</p>
            <h3 id="call-script-form-title">
              {isEditing
                ? 'Edit call script'
                : 'Create call script'}
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
          Scripts begin as drafts. A tenant administrator must
          activate a script before it can be used by a call
          queue.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Script name</span>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Volunteer confirmation call"
              required
              autoFocus
            />
            {errors.name && (
              <small className="field-error">
                {errors.name[0]}
              </small>
            )}
          </label>

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Script code</span>
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
              <span>Language code</span>
              <input
                type="text"
                name="language_code"
                value={form.language_code}
                onChange={updateField}
                maxLength="10"
                placeholder="en"
                required
              />
              <small className="field-help">
                Examples: en, ar, or en-US.
              </small>
              {errors.language_code && (
                <small className="field-error">
                  {errors.language_code[0]}
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
              rows="3"
              placeholder="Explain when call agents should use this script."
            />
            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Call script</span>
            <textarea
              name="body"
              value={form.body}
              onChange={updateField}
              maxLength="10000"
              rows="10"
              placeholder={`Hello, my name is [agent name] and I am calling on behalf of the Cedra Campaign.

May I confirm your availability for the upcoming campaign activity?

Thank you for your time.`}
              required
            />
            <small className="field-help">
              Include the greeting, questions, response guidance,
              and closing statement the agent should follow.
            </small>
            {errors.body && (
              <small className="field-error">
                {errors.body[0]}
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
                : isEditing
                  ? 'Save changes'
                  : 'Create script'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CallScriptForm