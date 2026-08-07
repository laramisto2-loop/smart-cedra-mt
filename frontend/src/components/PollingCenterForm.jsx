import { useState } from 'react'

function createCodePart(value) {
  return value
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

function generatePollingCenterCode(
  areas,
  areaId,
  pollingCenterName,
) {
  const area = areas.find(
    (item) => item.id === Number(areaId),
  )

  if (!area) {
    return ''
  }

  const areaNamePart = createCodePart(area.name_en)
  let centerNamePart = createCodePart(pollingCenterName)

  if (centerNamePart.startsWith(`${areaNamePart}-`)) {
    centerNamePart = centerNamePart.slice(
      areaNamePart.length + 1,
    )
  }

  if (!centerNamePart) {
    return ''
  }

  return `${area.code}-${centerNamePart}`
}

function PollingCenterForm({
  pollingCenter = null,
  areas,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    area_id: pollingCenter?.area_id?.toString() ?? '',
    name_en: pollingCenter?.name_en ?? '',
    name_ar: pollingCenter?.name_ar ?? '',
    address_en: pollingCenter?.address_en ?? '',
    address_ar: pollingCenter?.address_ar ?? '',
    latitude: pollingCenter?.latitude?.toString() ?? '',
    longitude: pollingCenter?.longitude?.toString() ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = pollingCenter !== null
  const generatedCode = generatePollingCenterCode(
    areas,
    form.area_id,
    form.name_en,
  )

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]: value,
    }))

    setErrors((current) => ({
      ...current,
      [name]: undefined,
      code: undefined,
    }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')
    setIsSubmitting(true)

    try {
      await onSubmit({
        area_id: Number(form.area_id),
        name_en: form.name_en,
        name_ar: form.name_ar,
        code: generatedCode,
        address_en:
          form.address_en === '' ? null : form.address_en,
        address_ar:
          form.address_ar === '' ? null : form.address_ar,
        latitude:
          form.latitude === '' ? null : Number(form.latitude),
        longitude:
          form.longitude === '' ? null : Number(form.longitude),
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The polling center could not be saved. Please try again.',
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
        aria-labelledby="polling-center-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Geography management</p>
            <h3 id="polling-center-form-title">
              {isEditing
                ? 'Edit polling center'
                : 'Add polling center'}
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
            <span>Parent area</span>
            <select
              name="area_id"
              value={form.area_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select an area</option>

              {areas.map((area) => (
                <option key={area.id} value={area.id}>
                  {area.district?.governorate?.name_en} —{' '}
                  {area.district?.name_en} — {area.name_en}
                </option>
              ))}
            </select>

            {errors.area_id && (
              <small className="field-error">
                {errors.area_id[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>English name</span>
            <input
              type="text"
              name="name_en"
              value={form.name_en}
              onChange={updateField}
              maxLength="255"
              required
            />
            {errors.name_en && (
              <small className="field-error">
                {errors.name_en[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Arabic name</span>
            <input
              type="text"
              name="name_ar"
              value={form.name_ar}
              onChange={updateField}
              maxLength="255"
              required
              dir="rtl"
            />
            {errors.name_ar && (
              <small className="field-error">
                {errors.name_ar[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Standardized code</span>
            <input
              type="text"
              value={generatedCode}
              readOnly
              placeholder="Generated automatically"
            />
            <small className="field-help">
              Generated from the parent area and polling-center
              name.
            </small>
            {errors.code && (
              <small className="field-error">{errors.code[0]}</small>
            )}
          </label>

          <label className="form-field">
            <span>English address (optional)</span>
            <input
              type="text"
              name="address_en"
              value={form.address_en}
              onChange={updateField}
              maxLength="255"
            />
            {errors.address_en && (
              <small className="field-error">
                {errors.address_en[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Arabic address (optional)</span>
            <input
              type="text"
              name="address_ar"
              value={form.address_ar}
              onChange={updateField}
              maxLength="255"
              dir="rtl"
            />
            {errors.address_ar && (
              <small className="field-error">
                {errors.address_ar[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Latitude (optional)</span>
            <input
              type="number"
              name="latitude"
              value={form.latitude}
              onChange={updateField}
              min="-90"
              max="90"
              step="any"
              placeholder="Example: 33.8938"
            />
            {errors.latitude && (
              <small className="field-error">
                {errors.latitude[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Longitude (optional)</span>
            <input
              type="number"
              name="longitude"
              value={form.longitude}
              onChange={updateField}
              min="-180"
              max="180"
              step="any"
              placeholder="Example: 35.5018"
            />
            {errors.longitude && (
              <small className="field-error">
                {errors.longitude[0]}
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
              disabled={isSubmitting || !generatedCode}
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create polling center'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default PollingCenterForm