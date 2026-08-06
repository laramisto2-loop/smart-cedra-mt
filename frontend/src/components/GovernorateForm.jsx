import { useState } from 'react'
import lebanonGovernorates from '../data/lebanonGovernorates.js'

function GovernorateForm({
  governorate = null,
  usedCodes = [],
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    name_en: governorate?.name_en ?? '',
    name_ar: governorate?.name_ar ?? '',
    code: governorate?.code ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = governorate !== null

  function selectGovernorate(event) {
    const selected = lebanonGovernorates.find(
      (option) => option.code === event.target.value,
    )

    if (!selected) {
      setForm({
        name_en: '',
        name_ar: '',
        code: '',
      })

      return
    }

    setForm({
      name_en: selected.name_en,
      name_ar: selected.name_ar,
      code: selected.code,
    })

    setErrors({})
  }

  function updateName(event) {
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
      await onSubmit(form)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The governorate could not be saved. Please try again.',
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
        aria-labelledby="governorate-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Geography management</p>
            <h3 id="governorate-form-title">
              {isEditing ? 'Edit governorate' : 'Add governorate'}
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
            <span>Governorate preset</span>
            <select
              value={form.code}
              onChange={selectGovernorate}
              required
              autoFocus
            >
              <option value="">Select a governorate</option>

              {lebanonGovernorates.map((option) => {
                const alreadyUsed =
                  usedCodes.includes(option.code) &&
                  option.code !== governorate?.code

                return (
                  <option
                    key={option.code}
                    value={option.code}
                    disabled={alreadyUsed}
                  >
                    {option.name_en} — {option.name_ar} — {option.code}
                    {alreadyUsed ? ' (already added)' : ''}
                  </option>
                )
              })}
            </select>

            {errors.code && (
              <small className="field-error">{errors.code[0]}</small>
            )}
          </label>

          <label className="form-field">
            <span>English name</span>
            <input
              type="text"
              name="name_en"
              value={form.name_en}
              onChange={updateName}
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
              onChange={updateName}
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
            <input type="text" value={form.code} readOnly />
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
                  : 'Create governorate'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default GovernorateForm