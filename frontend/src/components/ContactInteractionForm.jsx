import { useState } from 'react'

const channels = [
  { value: 'phone', label: 'Phone call' },
  { value: 'sms', label: 'SMS' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'Email' },
  { value: 'in_person', label: 'In-person meeting' },
  { value: 'note', label: 'Internal note' },
]

const directions = [
  {
    value: 'outbound',
    label: 'Outbound — campaign contacted the person',
  },
  {
    value: 'inbound',
    label: 'Inbound — the person contacted the campaign',
  },
  {
    value: 'internal',
    label: 'Internal — no communication occurred',
  },
]

const outcomes = [
  { value: '', label: 'No outcome recorded' },
  { value: 'completed', label: 'Completed' },
  { value: 'no_answer', label: 'No answer' },
  { value: 'follow_up', label: 'Follow-up needed' },
  { value: 'declined', label: 'Declined' },
  { value: 'failed', label: 'Failed' },
  { value: 'informational', label: 'Informational' },
]

const consentChannels = [
  'phone',
  'sms',
  'whatsapp',
  'email',
]

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function toDateTimeLocal(value = null) {
  const date = value ? new Date(value) : new Date()
  const offsetInMilliseconds =
    date.getTimezoneOffset() * 60 * 1000

  return new Date(date.getTime() - offsetInMilliseconds)
    .toISOString()
    .slice(0, 16)
}

function findConsent(contact, channel) {
  return contact.consents?.find(
    (consent) => consent.channel === channel,
  )
}

function ContactInteractionForm({
  contact,
  interaction = null,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    channel: interaction?.channel ?? 'phone',
    direction: interaction?.direction ?? 'outbound',
    outcome: interaction?.outcome ?? '',
    subject: interaction?.subject ?? '',
    notes: interaction?.notes ?? '',
    duration_minutes:
      interaction?.duration_seconds === null ||
      interaction?.duration_seconds === undefined
        ? ''
        : String(interaction.duration_seconds / 60),
    occurred_at: toDateTimeLocal(
      interaction?.occurred_at,
    ),
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = interaction !== null
  const selectedConsent = findConsent(
    contact,
    form.channel,
  )
  const checksConsent =
    form.direction === 'outbound' &&
    consentChannels.includes(form.channel)
  const hasGrantedConsent =
    selectedConsent?.status === 'granted'

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => {
      if (name === 'channel' && value === 'note') {
        return {
          ...current,
          channel: value,
          direction: 'internal',
          outcome:
            current.outcome === ''
              ? 'informational'
              : current.outcome,
        }
      }

      if (
        name === 'channel' &&
        current.channel === 'note' &&
        current.direction === 'internal'
      ) {
        return {
          ...current,
          channel: value,
          direction: 'outbound',
        }
      }

      return {
        ...current,
        [name]: value,
      }
    })

    setErrors((current) => ({
      ...current,
      [name]: undefined,
      duration_seconds:
        name === 'duration_minutes'
          ? undefined
          : current.duration_seconds,
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const payload = {
      outcome:
        form.outcome === '' ? null : form.outcome,
      subject: optionalValue(form.subject),
      notes: optionalValue(form.notes),
      duration_seconds:
        form.duration_minutes === ''
          ? null
          : Math.round(
              Number(form.duration_minutes) * 60,
            ),
      occurred_at: new Date(
        form.occurred_at,
      ).toISOString(),
    }

    if (
      !isEditing ||
      form.channel !== interaction.channel
    ) {
      payload.channel = form.channel
    }

    if (
      !isEditing ||
      form.direction !== interaction.direction
    ) {
      payload.direction = form.direction
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The interaction could not be saved. Please try again.',
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
        aria-labelledby="interaction-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Interaction timeline</p>
            <h3 id="interaction-form-title">
              {isEditing
                ? 'Edit interaction'
                : 'Record interaction'}
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
          Record what actually happened. An outbound phone,
          SMS, WhatsApp, or email interaction requires granted
          consent for that same channel.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Interaction channel</span>
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
            <span>Direction</span>
            <select
              name="direction"
              value={form.direction}
              onChange={updateField}
              required
              disabled={form.channel === 'note'}
            >
              {directions.map((direction) => (
                <option
                  key={direction.value}
                  value={direction.value}
                >
                  {direction.label}
                </option>
              ))}
            </select>
            {errors.direction && (
              <small className="field-error">
                {errors.direction[0]}
              </small>
            )}
          </label>

          {checksConsent && (
            <div
              className={
                hasGrantedConsent
                  ? 'interaction-consent-status granted'
                  : 'interaction-consent-status blocked'
              }
            >
              {hasGrantedConsent
                ? `Granted ${form.channel} consent is recorded.`
                : `Outbound ${form.channel} cannot be saved because granted consent is not recorded.`}
            </div>
          )}

          <label className="form-field">
            <span>Outcome (optional)</span>
            <select
              name="outcome"
              value={form.outcome}
              onChange={updateField}
            >
              {outcomes.map((outcome) => (
                <option
                  key={outcome.value}
                  value={outcome.value}
                >
                  {outcome.label}
                </option>
              ))}
            </select>
            {errors.outcome && (
              <small className="field-error">
                {errors.outcome[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Subject (optional)</span>
            <input
              type="text"
              name="subject"
              value={form.subject}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Volunteer availability follow-up"
            />
            {errors.subject && (
              <small className="field-error">
                {errors.subject[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Notes (optional)</span>
            <textarea
              name="notes"
              value={form.notes}
              onChange={updateField}
              maxLength="5000"
              rows="4"
              placeholder="Record relevant details without unnecessary sensitive information."
            />
            {errors.notes && (
              <small className="field-error">
                {errors.notes[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Duration in minutes (optional)</span>
            <input
              type="number"
              name="duration_minutes"
              value={form.duration_minutes}
              onChange={updateField}
              min="0"
              max="1440"
              step="1"
              placeholder="Example: 5"
            />
            {errors.duration_seconds && (
              <small className="field-error">
                {errors.duration_seconds[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Date and time</span>
            <input
              type="datetime-local"
              name="occurred_at"
              value={form.occurred_at}
              onChange={updateField}
              max={toDateTimeLocal()}
              required
            />
            {errors.occurred_at && (
              <small className="field-error">
                {errors.occurred_at[0]}
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
              disabled={
                isSubmitting ||
                (checksConsent &&
                  !hasGrantedConsent &&
                  (!isEditing ||
                    form.channel !==
                      interaction.channel ||
                    form.direction !==
                      interaction.direction))
              }
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Record interaction'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default ContactInteractionForm