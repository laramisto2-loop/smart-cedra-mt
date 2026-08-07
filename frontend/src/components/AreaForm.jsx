import { useState } from 'react'

const areaTypes = [
  {
    value: 'locality',
    label: 'Locality',
  },
  {
    value: 'city',
    label: 'City',
  },
  {
    value: 'town',
    label: 'Town',
  },
  {
    value: 'village',
    label: 'Village',
  },
  {
    value: 'municipality',
    label: 'Municipality',
  },
  {
    value: 'neighbourhood',
    label: 'Neighbourhood',
  },
]

function generateAreaCode(districts, districtId, areaName) {
  const district = districts.find(
    (item) => item.id === Number(districtId),
  )

  const areaSuffix = areaName
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-|-$/g, '')

  if (!district || !areaSuffix) {
    return ''
  }

  return `${district.code}-${areaSuffix}`
}

function AreaForm({
  area = null,
  districts,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    district_id: area?.district_id?.toString() ?? '',
    name_en: area?.name_en ?? '',
    name_ar: area?.name_ar ?? '',
    type: area?.type ?? 'locality',
    latitude: area?.latitude?.toString() ?? '',
    longitude: area?.longitude?.toString() ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = area !== null
  const generatedCode = generateAreaCode(
    districts,
    form.district_id,
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
        district_id: Number(form.district_id),
        name_en: form.name_en,
        name_ar: form.name_ar,
        code: generatedCode,
        type: form.type,
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
          'The area could not be saved. Please try again.',
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
        aria-labelledby="area-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Geography management</p>
            <h3 id="area-form-title">
              {isEditing ? 'Edit area' : 'Add area'}
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
            <span>Parent district</span>
            <select
              name="district_id"
              value={form.district_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select a district</option>

              {districts.map((district) => (
                <option key={district.id} value={district.id}>
                  {district.governorate?.name_en} —{' '}
                  {district.name_en}
                </option>
              ))}
            </select>

            {errors.district_id && (
              <small className="field-error">
                {errors.district_id[0]}
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
            <span>Area type</span>
            <select
              name="type"
              value={form.type}
              onChange={updateField}
              required
            >
              {areaTypes.map((areaType) => (
                <option
                  key={areaType.value}
                  value={areaType.value}
                >
                  {areaType.label}
                </option>
              ))}
            </select>

            {errors.type && (
              <small className="field-error">
                {errors.type[0]}
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
              Generated from the district code and English area
              name.
            </small>
            {errors.code && (
              <small className="field-error">{errors.code[0]}</small>
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
                  : 'Create area'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default AreaForm