import { useMemo, useState } from 'react'

const channels = [
  { value: 'phone', label: 'Phone calls' },
  { value: 'sms', label: 'SMS' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'Email' },
]

const consentStatuses = [
  { value: 'unknown', label: 'Unknown' },
  { value: 'granted', label: 'Granted' },
  { value: 'denied', label: 'Denied' },
  { value: 'revoked', label: 'Revoked' },
]

function findConsent(contact, channel) {
  return contact.consents?.find(
    (consent) => consent.channel === channel,
  )
}

function ContactConsentForm({
  contact,
  onSubmit,
  onCancel,
}) {
  const firstChannel =
    contact.preferred_channel ?? channels[0].value
  const initialConsent = findConsent(contact, firstChannel)

  const [form, setForm] = useState({
    channel: firstChannel,
    status: initialConsent?.status ?? 'unknown',
    source: initialConsent?.source ?? '',
    notes: initialConsent?.notes ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const existingConsent = useMemo(
    () => findConsent(contact, form.channel),
    [contact, form.channel],
  )

  function updateField(event) {
    const { name, value } = event.target

    if (name === 'channel') {
      const consent = findConsent(contact, value)

      setForm({
        channel: value,
        status: consent?.status ?? 'unknown',
        source: consent?.source ?? '',
        notes: consent?.notes ?? '',
      })
      setErrors({})
      return
    }

    setForm((current) => ({
      ...current,
      [name]: value,
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

    try {
      await onSubmit({
        channel: form.channel,
        status: form.status,
        source: form.source.trim(),
        notes:
          form.notes.trim() === ''
            ? null
            : form.notes.trim(),
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The consent record could not be saved. Please try again.',
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
        aria-labelledby="contact-consent-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Consent management</p>
            <h3 id="contact-consent-title">
              Record communication consent
            </h3>
            <p>
              {contact.full_name} · {contact.reference_code}
            </p>
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

        <div className="consent-notice">
          Record only consent supported by a trustworthy source.
          Selecting a preferred channel does not automatically grant
          permission to contact this person.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Communication channel</span>
            <select
              name="channel"
              value={form.channel}
              onChange={updateField}
              required
              autoFocus
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
            <span>Consent status</span>
            <select
              name="status"
              value={form.status}
              onChange={updateField}
              required
            >
              {consentStatuses.map((status) => (
                <option key={status.value} value={status.value}>
                  {status.label}
                </option>
              ))}
            </select>
            {errors.status && (
              <small className="field-error">
                {errors.status[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Evidence source</span>
            <input
              type="text"
              name="source"
              value={form.source}
              onChange={updateField}
              maxLength="50"
              placeholder="Example: Signed form or phone confirmation"
              required
            />
            <small className="field-help">
              State how this consent decision was obtained.
            </small>
            {errors.source && (
              <small className="field-error">
                {errors.source[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Evidence notes (optional)</span>
            <textarea
              name="notes"
              value={form.notes}
              onChange={updateField}
              maxLength="5000"
              rows="4"
              placeholder="Add relevant details without storing unnecessary sensitive information."
            />
            {errors.notes && (
              <small className="field-error">
                {errors.notes[0]}
              </small>
            )}
          </label>

          {existingConsent && (
            <div className="consent-history-note">
              This will update the existing{' '}
              <strong>{form.channel}</strong> consent record. The
              change will remain available in the audit log.
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
                isSubmitting || form.source.trim() === ''
              }
            >
              {isSubmitting
                ? 'Saving...'
                : 'Save consent record'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default ContactConsentForm