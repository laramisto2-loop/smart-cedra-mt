import { useState } from 'react'

const channels = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'sms', label: 'SMS' },
]

const categories = [
  { value: 'utility', label: 'Utility' },
  { value: 'marketing', label: 'Marketing' },
  { value: 'authentication', label: 'Authentication' },
]

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function parseVariables(value) {
  return [
    ...new Set(
      value
        .split(/[\n,]/)
        .map((variable) => variable.trim())
        .filter(Boolean),
    ),
  ]
}

function MessageTemplateForm({
  template = null,
  onSubmit,
  onCancel,
}) {
  const isEditing = template !== null

  const [form, setForm] = useState({
    name: template?.name ?? '',
    code: template?.code ?? '',
    channel: template?.channel ?? 'whatsapp',
    category: template?.category ?? 'utility',
    language_code: template?.language_code ?? 'en',
    provider: template?.provider ?? 'mock-provider',
    provider_template_name:
      template?.provider_template_name ?? '',
    body: template?.body ?? '',
    variables: template?.variables?.join(', ') ?? '',
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
      channel: form.channel,
      category: form.category,
      language_code: form.language_code.trim(),
      provider: optionalValue(form.provider),
      provider_template_name: optionalValue(
        form.provider_template_name,
      ),
      body: form.body.trim(),
      variables: parseVariables(form.variables),
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to save this template.',
        )
      } else {
        setGeneralError(
          'The message template could not be saved. Please try again.',
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
        aria-labelledby="message-template-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Messaging templates</p>
            <h3 id="message-template-form-title">
              {isEditing
                ? 'Edit message template'
                : 'Create message template'}
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
          Templates begin as drafts. A tenant administrator
          must approve them before they can be used.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Template name</span>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Volunteer shift reminder"
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
            <span>Template code</span>
            <input
              type="text"
              name="code"
              value={form.code}
              onChange={updateField}
              maxLength="50"
              placeholder="VOLUNTEER_REMINDER"
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

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Channel</span>
              <select
                name="channel"
                value={form.channel}
                onChange={updateField}
                required
              >
                {channels.map((channel) => (
                  <option
                    key={channel.value}
                    value={channel.value}
                  >
                    {channel.label}
                  </option>
                ))}
              </select>
              {errors.channel && (
                <small className="field-error">
                  {errors.channel[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Category</span>
              <select
                name="category"
                value={form.category}
                onChange={updateField}
                required
              >
                {categories.map((category) => (
                  <option
                    key={category.value}
                    value={category.value}
                  >
                    {category.label}
                  </option>
                ))}
              </select>
              {errors.category && (
                <small className="field-error">
                  {errors.category[0]}
                </small>
              )}
            </label>
          </div>

          <div className="incident-form-grid">
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
                Examples: en, ar, en-US.
              </small>
              {errors.language_code && (
                <small className="field-error">
                  {errors.language_code[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Provider (optional)</span>
              <input
                type="text"
                name="provider"
                value={form.provider}
                onChange={updateField}
                maxLength="30"
                placeholder="mock-provider"
              />
              {errors.provider && (
                <small className="field-error">
                  {errors.provider[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Provider template name (optional)</span>
            <input
              type="text"
              name="provider_template_name"
              value={form.provider_template_name}
              onChange={updateField}
              maxLength="191"
              placeholder="volunteer_reminder"
            />
            {errors.provider_template_name && (
              <small className="field-error">
                {errors.provider_template_name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Template variables (optional)</span>
            <input
              type="text"
              name="variables"
              value={form.variables}
              onChange={updateField}
              placeholder="first_name, shift_time"
            />
            <small className="field-help">
              Separate variable names with commas. Use them in
              the message as {'{{first_name}}'}.
            </small>
            {errors.variables && (
              <small className="field-error">
                {errors.variables[0]}
              </small>
            )}
            {errors['variables.0'] && (
              <small className="field-error">
                {errors['variables.0'][0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Message body</span>
            <textarea
              name="body"
              value={form.body}
              onChange={updateField}
              maxLength="10000"
              rows="6"
              placeholder="Hello {{first_name}}, your volunteer shift begins at {{shift_time}}."
              required
            />
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
                  : 'Create template'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default MessageTemplateForm