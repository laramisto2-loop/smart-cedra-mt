import { useMemo, useState } from 'react'

const categories = [
  ['general', 'General'],
  ['access', 'Access'],
  ['safety', 'Safety'],
  ['medical', 'Medical'],
  ['equipment', 'Equipment'],
  ['logistics', 'Logistics'],
  ['conduct', 'Conduct'],
  ['other', 'Other'],
]

const severities = [
  ['low', 'Low'],
  ['medium', 'Medium'],
  ['high', 'High'],
  ['critical', 'Critical'],
]

function optionalNumber(value) {
  return value === '' ? null : Number(value)
}

function optionalText(value) {
  const trimmedValue = value.trim()
  return trimmedValue === '' ? null : trimmedValue
}

function toLocalDateTime(value) {
  const date = value ? new Date(value) : new Date()
  const offset = date.getTimezoneOffset() * 60000

  return new Date(date.getTime() - offset)
    .toISOString()
    .slice(0, 16)
}

function areaLabel(area) {
  return [
    area.district?.governorate?.name_en,
    area.district?.name_en,
    area.name_en,
  ]
    .filter(Boolean)
    .join(' — ')
}

function IncidentForm({
  incident = null,
  areas,
  pollingCenters,
  pollingStations,
  tasks,
  onSubmit,
  onCancel,
}) {
  const isEditing = incident !== null
  const [form, setForm] = useState({
    title: incident?.title ?? '',
    description: incident?.description ?? '',
    category: incident?.category ?? 'general',
    severity: incident?.severity ?? 'medium',
    occurred_at: toLocalDateTime(incident?.occurred_at),
    campaign_task_id:
      incident?.campaign_task_id?.toString() ?? '',
    area_id: incident?.area_id?.toString() ?? '',
    polling_center_id:
      incident?.polling_center_id?.toString() ?? '',
    polling_station_id:
      incident?.polling_station_id?.toString() ?? '',
    location_notes: incident?.location_notes ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const availableCenters = useMemo(
    () =>
      pollingCenters.filter(
        (center) =>
          form.area_id === ''
          || center.area_id.toString() === form.area_id,
      ),
    [form.area_id, pollingCenters],
  )

  const availableStations = useMemo(
    () =>
      pollingStations.filter(
        (station) =>
          form.polling_center_id === ''
          || station.polling_center_id.toString()
            === form.polling_center_id,
      ),
    [form.polling_center_id, pollingStations],
  )

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => {
      const next = { ...current, [name]: value }

      if (name === 'area_id') {
        next.polling_center_id = ''
        next.polling_station_id = ''
      }

      if (name === 'polling_center_id') {
        next.polling_station_id = ''
      }

      return next
    })

    setErrors((current) => ({ ...current, [name]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    const payload = {
      title: form.title.trim(),
      description: form.description.trim(),
      category: form.category,
      severity: form.severity,
      occurred_at: new Date(form.occurred_at).toISOString(),
      campaign_task_id: optionalNumber(form.campaign_task_id),
      area_id: optionalNumber(form.area_id),
      polling_center_id: optionalNumber(form.polling_center_id),
      polling_station_id: optionalNumber(form.polling_station_id),
      location_notes: optionalText(form.location_notes),
      client_updated_at: new Date().toISOString(),
    }

    if (isEditing) {
      payload.expected_sync_version = incident.sync_version
    } else {
      payload.client_uuid = crypto.randomUUID()
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 409) {
        setGeneralError(
          'This incident changed since it was opened. Close the form, refresh, and try again.',
        )
      } else {
        setGeneralError(
          'The incident could not be saved. Please try again.',
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
        aria-labelledby="incident-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Field incident reporting</p>
            <h3 id="incident-form-title">
              {isEditing ? 'Edit incident' : 'Report incident'}
            </h3>
          </div>
          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close incident form"
          >
            ×
          </button>
        </div>

        <div className="form-message">
          Record factual operational information only. New reports
          receive a secure reference and are safe to retry if a
          connection is interrupted.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Incident title</span>
            <input
              type="text"
              name="title"
              value={form.title}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Polling center access blocked"
              required
              autoFocus
            />
            {errors.title && (
              <small className="field-error">{errors.title[0]}</small>
            )}
          </label>

          <label className="form-field">
            <span>Description</span>
            <textarea
              name="description"
              value={form.description}
              onChange={updateField}
              maxLength="10000"
              rows="5"
              placeholder="Explain what happened, who was affected, and what immediate action was taken."
              required
            />
            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
              </small>
            )}
          </label>

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Category</span>
              <select
                name="category"
                value={form.category}
                onChange={updateField}
              >
                {categories.map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              {errors.category && (
                <small className="field-error">
                  {errors.category[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Severity</span>
              <select
                name="severity"
                value={form.severity}
                onChange={updateField}
              >
                {severities.map(([value, label]) => (
                  <option key={value} value={value}>{label}</option>
                ))}
              </select>
              {errors.severity && (
                <small className="field-error">
                  {errors.severity[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Date and time of incident</span>
            <input
              type="datetime-local"
              name="occurred_at"
              value={form.occurred_at}
              onChange={updateField}
              required
            />
            {errors.occurred_at && (
              <small className="field-error">
                {errors.occurred_at[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Related campaign task (optional)</span>
            <select
              name="campaign_task_id"
              value={form.campaign_task_id}
              onChange={updateField}
            >
              <option value="">No related task</option>
              {tasks.map((task) => (
                <option key={task.id} value={task.id}>
                  {task.title}
                </option>
              ))}
            </select>
            {errors.campaign_task_id && (
              <small className="field-error">
                {errors.campaign_task_id[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Area (optional)</span>
            <select
              name="area_id"
              value={form.area_id}
              onChange={updateField}
            >
              <option value="">No area selected</option>
              {areas.map((area) => (
                <option key={area.id} value={area.id}>
                  {areaLabel(area)}
                </option>
              ))}
            </select>
            {errors.area_id && (
              <small className="field-error">{errors.area_id[0]}</small>
            )}
          </label>

          <label className="form-field">
            <span>Polling center (optional)</span>
            <select
              name="polling_center_id"
              value={form.polling_center_id}
              onChange={updateField}
              disabled={form.area_id === ''}
            >
              <option value="">No polling center selected</option>
              {availableCenters.map((center) => (
                <option key={center.id} value={center.id}>
                  {center.name_en} — {center.code}
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
              <option value="">No polling station selected</option>
              {availableStations.map((station) => (
                <option key={station.id} value={station.id}>
                  Station {station.station_number}
                  {station.name_en ? ` — ${station.name_en}` : ''}
                </option>
              ))}
            </select>
            {errors.polling_station_id && (
              <small className="field-error">
                {errors.polling_station_id[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Location notes (optional)</span>
            <textarea
              name="location_notes"
              value={form.location_notes}
              onChange={updateField}
              maxLength="2000"
              rows="3"
              placeholder="Entrance, room, landmark, or other useful location detail."
            />
            {errors.location_notes && (
              <small className="field-error">
                {errors.location_notes[0]}
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
                  : 'Submit incident'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default IncidentForm
