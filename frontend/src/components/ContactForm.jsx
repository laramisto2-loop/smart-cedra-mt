import { useState } from 'react'

const emptyForm = {
  reference_code: '',
  area_id: '',
  first_name: '',
  last_name: '',
  name_ar: '',
  phone: '',
  email: '',
  address: '',
  preferred_language: 'en',
  preferred_channel: '',
  status: 'active',
  source: '',
  notes: '',
}

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function ContactForm({
  contact = null,
  areas = [],
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    ...emptyForm,
    reference_code: contact?.reference_code ?? '',
    area_id: contact?.area_id?.toString() ?? '',
    first_name: contact?.first_name ?? '',
    last_name: contact?.last_name ?? '',
    name_ar: contact?.name_ar ?? '',
    phone: contact?.phone ?? '',
    email: contact?.email ?? '',
    address: contact?.address ?? '',
    preferred_language:
      contact?.preferred_language ?? 'en',
    preferred_channel: contact?.preferred_channel ?? '',
    status: contact?.status ?? 'active',
    source: contact?.source ?? '',
    notes: contact?.notes ?? '',
  })
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = contact !== null

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
        reference_code: form.reference_code.trim(),
        area_id:
          form.area_id === '' ? null : Number(form.area_id),
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        name_ar: optionalValue(form.name_ar),
        phone: optionalValue(form.phone),
        email: optionalValue(form.email),
        address: optionalValue(form.address),
        preferred_language: form.preferred_language,
        preferred_channel:
          form.preferred_channel === ''
            ? null
            : form.preferred_channel,
        status: form.status,
        source: optionalValue(form.source),
        notes: optionalValue(form.notes),
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The contact could not be saved. Please try again.',
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
        aria-labelledby="contact-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">CRM management</p>
            <h3 id="contact-form-title">
              {isEditing ? 'Edit contact' : 'Add contact'}
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
            <span>Reference code</span>
            <input
              type="text"
              name="reference_code"
              value={form.reference_code}
              onChange={updateField}
              maxLength="50"
              placeholder="Example: CONTACT-0001"
              required
              autoFocus
            />
            <small className="field-help">
              A unique contact identifier from your campaign or
              source dataset.
            </small>
            {errors.reference_code && (
              <small className="field-error">
                {errors.reference_code[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>English first name</span>
            <input
              type="text"
              name="first_name"
              value={form.first_name}
              onChange={updateField}
              maxLength="255"
              required
            />
            {errors.first_name && (
              <small className="field-error">
                {errors.first_name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>English last name</span>
            <input
              type="text"
              name="last_name"
              value={form.last_name}
              onChange={updateField}
              maxLength="255"
              required
            />
            {errors.last_name && (
              <small className="field-error">
                {errors.last_name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Arabic full name (optional)</span>
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
            <span>Area (optional)</span>
            <select
              name="area_id"
              value={form.area_id}
              onChange={updateField}
            >
              <option value="">No area assigned</option>

              {areas.map((area) => (
                <option key={area.id} value={area.id}>
                  {area.district?.governorate?.name_en
                    ? `${area.district.governorate.name_en} — `
                    : ''}
                  {area.district?.name_en
                    ? `${area.district.name_en} — `
                    : ''}
                  {area.name_en}
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
            <span>Phone (optional)</span>
            <input
              type="tel"
              name="phone"
              value={form.phone}
              onChange={updateField}
              maxLength="30"
              placeholder="+961..."
            />
            {errors.phone && (
              <small className="field-error">
                {errors.phone[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Email (optional)</span>
            <input
              type="email"
              name="email"
              value={form.email}
              onChange={updateField}
              maxLength="255"
            />
            {errors.email && (
              <small className="field-error">
                {errors.email[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Address (optional)</span>
            <input
              type="text"
              name="address"
              value={form.address}
              onChange={updateField}
              maxLength="255"
            />
            {errors.address && (
              <small className="field-error">
                {errors.address[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Preferred language</span>
            <select
              name="preferred_language"
              value={form.preferred_language}
              onChange={updateField}
              required
            >
              <option value="en">English</option>
              <option value="ar">Arabic</option>
            </select>
            {errors.preferred_language && (
              <small className="field-error">
                {errors.preferred_language[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Preferred channel (optional)</span>
            <select
              name="preferred_channel"
              value={form.preferred_channel}
              onChange={updateField}
            >
              <option value="">Not selected</option>
              <option value="phone">Phone</option>
              <option value="sms">SMS</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="email">Email</option>
            </select>
            <small className="field-help">
              A preference is not proof of consent.
            </small>
            {errors.preferred_channel && (
              <small className="field-error">
                {errors.preferred_channel[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Status</span>
            <select
              name="status"
              value={form.status}
              onChange={updateField}
              required
            >
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="archived">Archived</option>
            </select>
            {errors.status && (
              <small className="field-error">
                {errors.status[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Source (optional)</span>
            <input
              type="text"
              name="source"
              value={form.source}
              onChange={updateField}
              maxLength="50"
              placeholder="Example: Field registration"
            />
            {errors.source && (
              <small className="field-error">
                {errors.source[0]}
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
                : isEditing
                  ? 'Save changes'
                  : 'Create contact'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default ContactForm