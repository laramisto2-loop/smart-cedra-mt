import { useMemo, useState } from 'react'

const automaticVariables = new Set([
  'first_name',
  'last_name',
  'full_name',
  'name_ar',
  'phone',
  'email',
  'reference_code',
])

function contactLabel(contact) {
  const name =
    contact.full_name ||
    [contact.first_name, contact.last_name]
      .filter(Boolean)
      .join(' ')

  return `${name} — ${contact.reference_code}`
}

function OutboundMessageForm({
  contacts = [],
  templates = [],
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    contact_id: '',
    message_template_id: '',
    variables: {},
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const selectedTemplate = useMemo(
    () =>
      templates.find(
        (template) =>
          template.id.toString() ===
          form.message_template_id,
      ) ?? null,
    [form.message_template_id, templates],
  )

  const templateVariables = useMemo(() => {
  if (!selectedTemplate) {
    return []
  }

  const declaredVariables = Array.isArray(
    selectedTemplate.variables,
  )
    ? selectedTemplate.variables
    : []

  const bodyVariables = [
    ...(selectedTemplate.body ?? '').matchAll(
      /\{\{\s*([a-zA-Z][a-zA-Z0-9_]*)\s*\}\}/g,
    ),
  ].map((match) => match[1])

  return [...new Set([...declaredVariables, ...bodyVariables])]
}, [selectedTemplate])

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
      ...(name === 'message_template_id'
        ? { variables: {} }
        : {}),
    }))

    clearError(name)
  }

  function updateVariable(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      variables: {
        ...current.variables,
        [name]: value,
      },
    }))

    clearError(`variables.${name}`)
  }

  function formatVariableValue(name, value) {
  const trimmedValue = value.trim()

  if (name !== 'shift_time' || trimmedValue === '') {
    return trimmedValue
  }

  const selectedDate = new Date(trimmedValue)

  if (Number.isNaN(selectedDate.getTime())) {
    return trimmedValue
  }

  return new Intl.DateTimeFormat('en', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(selectedDate)
}

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const variables = Object.fromEntries(
      Object.entries(form.variables)
        .map(([name, value]) => [
            name,
        formatVariableValue(name, value),
        ])
        .filter(([, value]) => value !== ''),
    )

    const payload = {
      contact_id: Number(form.contact_id),
      message_template_id: Number(
        form.message_template_id,
      ),
      client_uuid: crypto.randomUUID(),
      variables,
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to send messages.',
        )
      } else {
        setGeneralError(
          'The message could not be processed. Please try again.',
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
        aria-labelledby="outbound-message-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Consent-aware messaging</p>
            <h3 id="outbound-message-form-title">
              Send message
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
          The server will verify the contact&apos;s consent
          before queueing the message. Messages without granted
          consent are safely suppressed and recorded.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Contact</span>
            <select
              name="contact_id"
              value={form.contact_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select a contact</option>

              {contacts.map((contact) => (
                <option key={contact.id} value={contact.id}>
                  {contactLabel(contact)}
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
            <span>Approved template</span>
            <select
              name="message_template_id"
              value={form.message_template_id}
              onChange={updateField}
              required
            >
              <option value="">Select a template</option>

              {templates.map((template) => (
                <option key={template.id} value={template.id}>
                  {template.name} —{' '}
                  {template.channel.toUpperCase()}
                </option>
              ))}
            </select>
            {errors.message_template_id && (
              <small className="field-error">
                {errors.message_template_id[0]}
              </small>
            )}
          </label>

          {selectedTemplate && (
            <div className="info-message">
              <strong>{selectedTemplate.name}</strong>
              <p>{selectedTemplate.body}</p>
              <small>
                Channel:{' '}
                {selectedTemplate.channel.toUpperCase()} ·
                Language: {selectedTemplate.language_code}
              </small>
            </div>
          )}

          {templateVariables.map((variable) => (
            <label className="form-field" key={variable}>
              <span>{variable}</span>
              <input
              type={
                    variable === 'shift_time'
                    ? 'datetime-local'
                    : 'text'
                }
                name={variable}
                value={form.variables[variable] ?? ''}
                onChange={updateVariable}
                maxLength="1000"
                placeholder={
                  automaticVariables.has(variable)
                    ? 'Leave blank to use the contact value'
                    : `Enter ${variable}`
                }
              />
              {automaticVariables.has(variable) && (
                <small className="field-help">
                  This value can be filled automatically from
                  the selected contact.
                </small>
              )}
              {errors[`variables.${variable}`] && (
                <small className="field-error">
                  {errors[`variables.${variable}`][0]}
                </small>
              )}
            </label>
          ))}

          {errors.variables && (
            <div className="field-error">
              {errors.variables[0]}
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
              disabled={
                isSubmitting ||
                form.contact_id === '' ||
                form.message_template_id === ''
              }
            >
              {isSubmitting ? 'Processing...' : 'Send message'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default OutboundMessageForm