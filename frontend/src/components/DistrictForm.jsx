import { useState } from 'react'

function generateDistrictCode(
  governorates,
  governorateId,
  districtName,
) {
  const governorate = governorates.find(
    (item) => item.id === Number(governorateId),
  )

  const districtSuffix = districtName
    .replace(/\s+District$/i, '')
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, '-')
    .replace(/^-|-$/g, '')

  if (!governorate || !districtSuffix) {
    return ''
  }

  return `${governorate.code}-${districtSuffix}`
}

function DistrictForm({
  district = null,
  governorates,
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    governorate_id:
      district?.governorate_id?.toString() ?? '',
    name_en: district?.name_en ?? '',
    name_ar: district?.name_ar ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = district !== null
  const generatedCode = generateDistrictCode(
    governorates,
    form.governorate_id,
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
        ...form,
        governorate_id: Number(form.governorate_id),
        code: generatedCode,
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The district could not be saved. Please try again.',
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
        aria-labelledby="district-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Geography management</p>
            <h3 id="district-form-title">
              {isEditing ? 'Edit district' : 'Add district'}
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
            <span>Parent governorate</span>
            <select
              name="governorate_id"
              value={form.governorate_id}
              onChange={updateField}
              required
              autoFocus
            >
              <option value="">Select a governorate</option>

              {governorates.map((governorate) => (
                <option
                  key={governorate.id}
                  value={governorate.id}
                >
                  {governorate.name_en} — {governorate.name_ar}
                </option>
              ))}
            </select>

            {errors.governorate_id && (
              <small className="field-error">
                {errors.governorate_id[0]}
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
              Generated from the governorate code and English
              district name.
            </small>
            {errors.code && (
              <small className="field-error">{errors.code[0]}</small>
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
                  : 'Create district'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default DistrictForm