import { useState } from 'react'

function pollingCenterLabel(pollingCenter) {
  return [
    pollingCenter.area?.district?.governorate?.name_en,
    pollingCenter.area?.district?.name_en,
    pollingCenter.area?.name_en,
    pollingCenter.name_en,
  ]
    .filter(Boolean)
    .join(' — ')
}

function PollingStationForm({
  pollingStation = null,
  pollingCenters,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    polling_center_id:
      pollingStation?.polling_center_id?.toString() ?? '',
    station_number: pollingStation?.station_number ?? '',
    name_en: pollingStation?.name_en ?? '',
    name_ar: pollingStation?.name_ar ?? '',
    room: pollingStation?.room ?? '',
    registered_voters:
      pollingStation?.registered_voters?.toString() ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = pollingStation !== null

  function updateField(event) {
    const { name, value } = event.target

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
        polling_center_id: Number(form.polling_center_id),
        station_number: form.station_number,
        name_en: form.name_en === '' ? null : form.name_en,
        name_ar: form.name_ar === '' ? null : form.name_ar,
        room: form.room === '' ? null : form.room,
        registered_voters:
          form.registered_voters === ''
            ? null
            : Number(form.registered_voters),
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The polling station could not be saved. Please try again.',
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
        aria-labelledby="polling-station-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Geography management</p>
            <h3 id="polling-station-form-title">
              {isEditing
                ? 'Edit polling station'
                : 'Add polling station'}
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
            <span>Parent polling center</span>
            <select
              name="polling_center_id"
              value={form.polling_center_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select a polling center</option>

              {pollingCenters.map((pollingCenter) => (
                <option
                  key={pollingCenter.id}
                  value={pollingCenter.id}
                >
                  {pollingCenterLabel(pollingCenter)}
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
            <span>Station number</span>
            <input
              type="text"
              name="station_number"
              value={form.station_number}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: 1"
              required
            />
            <small className="field-help">
              Must be unique inside the selected polling center.
            </small>
            {errors.station_number && (
              <small className="field-error">
                {errors.station_number[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>English label (optional)</span>
            <input
              type="text"
              name="name_en"
              value={form.name_en}
              onChange={updateField}
              maxLength="255"
            />
            {errors.name_en && (
              <small className="field-error">
                {errors.name_en[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Arabic label (optional)</span>
            <input
              type="text"
              name="name_ar"
              value={form.name_ar}
              onChange={updateField}
              maxLength="255"
              dir="rtl"
            />
            {errors.name_ar && (
              <small className="field-error">
                {errors.name_ar[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Room (optional)</span>
            <input
              type="text"
              name="room"
              value={form.room}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Room 101"
            />
            {errors.room && (
              <small className="field-error">
                {errors.room[0]}
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
              max="4294967295"
              step="1"
              placeholder="Example: 850"
            />
            {errors.registered_voters && (
              <small className="field-error">
                {errors.registered_voters[0]}
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
                  : 'Create polling station'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default PollingStationForm