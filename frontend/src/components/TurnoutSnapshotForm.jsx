import { useMemo, useState } from 'react'

function optionalNumber(value) {
  return value === '' ? null : Number(value)
}

function optionalText(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function toLocalDateTime(value = null) {
  const date = value ? new Date(value) : new Date()
  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset)
    .toISOString()
    .slice(0, 16)
}

function centerLabel(center) {
  const location = [
    center.area?.district?.governorate?.name_en,
    center.area?.district?.name_en,
    center.area?.name_en,
  ]
    .filter(Boolean)
    .join(' — ')

  return `${center.name_en} — ${center.code}${
    location ? ` (${location})` : ''
  }`
}

function TurnoutSnapshotForm({
  pollingCenters,
  pollingStations,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    polling_center_id: '',
    polling_station_id: '',
    registered_voters: '',
    turnout_count: '',
    captured_at: toLocalDateTime(),
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const availableStations = useMemo(
    () =>
      pollingStations.filter(
        (station) =>
          form.polling_center_id !== ''
          && station.polling_center_id.toString()
            === form.polling_center_id,
      ),
    [form.polling_center_id, pollingStations],
  )

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => {
      const next = {
        ...current,
        [name]: value,
      }

      if (name === 'polling_center_id') {
        next.polling_station_id = ''
        next.registered_voters = ''
      }

      if (name === 'polling_station_id') {
        const selectedStation = pollingStations.find(
          (station) => station.id.toString() === value,
        )

        next.registered_voters =
          selectedStation?.registered_voters?.toString() ?? ''
      }

      return next
    })

    setErrors((current) => ({
      ...current,
      [name]: undefined,
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')

    const registeredVoters = optionalNumber(
      form.registered_voters,
    )
    const turnoutCount = Number(form.turnout_count)

    if (
      registeredVoters !== null
      && turnoutCount > registeredVoters
    ) {
      setErrors({
        turnout_count: [
          'The turnout count cannot exceed the registered voter count.',
        ],
      })

      return
    }

    setIsSubmitting(true)

    const payload = {
      polling_center_id: Number(form.polling_center_id),
      polling_station_id: optionalNumber(
        form.polling_station_id,
      ),
      registered_voters: registeredVoters,
      turnout_count: turnoutCount,
      captured_at: new Date(
        form.captured_at,
      ).toISOString(),
      notes: optionalText(form.notes),
      client_uuid: crypto.randomUUID(),
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The turnout entry could not be saved. Please try again.',
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card incident-form-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="turnout-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">
              Aggregate turnout reporting
            </p>
            <h3 id="turnout-form-title">
              Record turnout snapshot
            </h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close turnout form"
          >
            ×
          </button>
        </div>

        <div className="form-message">
          Record the total turnout observed at this time. Do not
          enter voter names, identities, or individual voting
          activity. Offline entries are safe to retry.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Polling center</span>
            <select
              name="polling_center_id"
              value={form.polling_center_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select a polling center</option>

              {pollingCenters.map((center) => (
                <option key={center.id} value={center.id}>
                  {centerLabel(center)}
                </option>
              ))}
            </select>

            {errors.polling_center_id && (
              <small className="field-error">
                {errors.polling_center_id[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Polling station (optional)</span>
            <select
              name="polling_station_id"
              value={form.polling_station_id}
              onChange={updateField}
              disabled={form.polling_center_id === ''}
            >
              <option value="">
                Entire polling center
              </option>

              {availableStations.map((station) => (
                <option key={station.id} value={station.id}>
                  Station {station.station_number}
                  {station.name_en
                    ? ` — ${station.name_en}`
                    : ''}
                </option>
              ))}
            </select>

            {errors.polling_station_id && (
              <small className="field-error">
                {errors.polling_station_id[0]}
              </small>
            )}
          </label>

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Turnout count</span>
              <input
                type="number"
                name="turnout_count"
                value={form.turnout_count}
                onChange={updateField}
                min="0"
                step="1"
                placeholder="Example: 420"
                required
              />

              <small>
                Enter the cumulative number recorded at the
                selected location.
              </small>

              {errors.turnout_count && (
                <small className="field-error">
                  {errors.turnout_count[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Registered voters (optional)</span>
              <input
                type="number"
                name="registered_voters"
                value={form.registered_voters}
                onChange={updateField}
                min="0"
                step="1"
                placeholder="Derived when available"
              />

              <small>
                Leave blank to use the configured geography total.
              </small>

              {errors.registered_voters && (
                <small className="field-error">
                  {errors.registered_voters[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Date and time captured</span>
            <input
              type="datetime-local"
              name="captured_at"
              value={form.captured_at}
              onChange={updateField}
              required
            />

            {errors.captured_at && (
              <small className="field-error">
                {errors.captured_at[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Operational notes (optional)</span>
            <textarea
              name="notes"
              value={form.notes}
              onChange={updateField}
              maxLength="2000"
              rows="4"
              placeholder="Example: Count confirmed with the station supervisor."
            />

            {errors.notes && (
              <small className="field-error">
                {errors.notes[0]}
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
                : 'Record snapshot'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default TurnoutSnapshotForm

//“Turnout count” is cumulative. For example, if a station had 200 voters earlier and later reaches 260, the next snapshot is 260, not 60