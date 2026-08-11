import { useState } from 'react'

const emptyCriteria = {
  contact_status: '',
  area_id: '',
  preferred_language: '',
  preferred_channel: '',
  consent_channel: '',
  consent_status: '',
}

function optionalValue(value) {
  const trimmedValue = value.trim()

  return trimmedValue === '' ? null : trimmedValue
}

function SegmentForm({
  segment = null,
  areas = [],
  onSubmit,
  onCancel,
}) {
  const [form, setForm] = useState({
    code: segment?.code ?? '',
    name: segment?.name ?? '',
    description: segment?.description ?? '',
    type: segment?.type ?? 'static',
    status: segment?.status ?? 'active',
  })

  const [criteria, setCriteria] = useState({
    ...emptyCriteria,
    contact_status:
      segment?.criteria?.contact_status ?? '',
    area_id:
      segment?.criteria?.area_id?.toString() ?? '',
    preferred_language:
      segment?.criteria?.preferred_language ?? '',
    preferred_channel:
      segment?.criteria?.preferred_channel ?? '',
    consent_channel:
      segment?.criteria?.consent_channel ?? '',
    consent_status:
      segment?.criteria?.consent_status ?? '',
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const isEditing = segment !== null
  const isDynamic = form.type === 'dynamic'

  function clearError(name) {
    setErrors((current) => ({
      ...current,
      [name]: undefined,
    }))
  }

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]: value,
    }))

    clearError(name)
  }

  function updateCriteria(event) {
    const { name, value } = event.target

    setCriteria((current) => ({
      ...current,
      [name]: value,
    }))

    clearError(`criteria.${name}`)
    clearError('criteria')
  }

  function buildCriteria() {
    const rules = {}

    if (criteria.contact_status !== '') {
      rules.contact_status = criteria.contact_status
    }

    if (criteria.area_id !== '') {
      rules.area_id = Number(criteria.area_id)
    }

    if (criteria.preferred_language !== '') {
      rules.preferred_language =
        criteria.preferred_language
    }

    if (criteria.preferred_channel !== '') {
      rules.preferred_channel =
        criteria.preferred_channel
    }

    if (criteria.consent_channel !== '') {
      rules.consent_channel = criteria.consent_channel
    }

    if (criteria.consent_status !== '') {
      rules.consent_status = criteria.consent_status
    }

    return rules
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')

    const dynamicCriteria = buildCriteria()

    if (
      isDynamic
      && Object.keys(dynamicCriteria).length === 0
    ) {
      setErrors({
        criteria: [
          'Choose at least one rule for a dynamic segment.',
        ],
      })

      return
    }

    setIsSubmitting(true)

    try {
      const payload = {
        code: form.code.trim(),
        name: form.name.trim(),
        description: optionalValue(form.description),
        type: form.type,
        status: form.status,
      }

      if (isDynamic) {
        payload.criteria = dynamicCriteria
      }

      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else {
        setGeneralError(
          'The segment could not be saved. Please try again.',
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
        aria-labelledby="segment-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">CRM segmentation</p>
            <h3 id="segment-form-title">
              {isEditing ? 'Edit segment' : 'Add segment'}
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
            <span>Segment code</span>
            <input
              type="text"
              name="code"
              value={form.code}
              onChange={updateField}
              maxLength="50"
              placeholder="Example: ACTIVE-VOLUNTEERS"
              required
              autoFocus
            />
            <small className="field-help">
              A unique internal identifier. It is saved in
              uppercase.
            </small>
            {errors.code && (
              <small className="field-error">
                {errors.code[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Segment name</span>
            <input
              type="text"
              name="name"
              value={form.name}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Active volunteers"
              required
            />
            {errors.name && (
              <small className="field-error">
                {errors.name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Description (optional)</span>
            <textarea
              name="description"
              value={form.description}
              onChange={updateField}
              maxLength="5000"
              rows="3"
              placeholder="Explain who belongs to this segment."
            />
            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Segment type</span>
            <select
              name="type"
              value={form.type}
              onChange={updateField}
            >
              <option value="static">
                Static — choose members manually
              </option>
              <option value="dynamic">
                Dynamic — members match rules automatically
              </option>
            </select>
            <small className="field-help">
              Static membership is managed manually. Dynamic
              membership changes automatically as contacts change.
            </small>
            {errors.type && (
              <small className="field-error">
                {errors.type[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Status</span>
            <select
              name="status"
              value={form.status}
              onChange={updateField}
            >
              <option value="active">Active</option>
              <option value="archived">Archived</option>
            </select>
            {errors.status && (
              <small className="field-error">
                {errors.status[0]}
              </small>
            )}
          </label>

          {isDynamic && (
            <div className="segment-rules">
              <div className="info-message">
                A contact must match every selected rule. Leave a
                rule blank when it should not restrict membership.
              </div>

              <h4>Automatic membership rules</h4>

              {errors.criteria && (
                <small className="field-error">
                  {errors.criteria[0]}
                </small>
              )}

              <label className="form-field">
                <span>Contact status</span>
                <select
                  name="contact_status"
                  value={criteria.contact_status}
                  onChange={updateCriteria}
                >
                  <option value="">Any contact status</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="archived">Archived</option>
                </select>
                {errors['criteria.contact_status'] && (
                  <small className="field-error">
                    {errors['criteria.contact_status'][0]}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Area</span>
                <select
                  name="area_id"
                  value={criteria.area_id}
                  onChange={updateCriteria}
                >
                  <option value="">Any area</option>

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
                {errors['criteria.area_id'] && (
                  <small className="field-error">
                    {errors['criteria.area_id'][0]}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Preferred language</span>
                <select
                  name="preferred_language"
                  value={criteria.preferred_language}
                  onChange={updateCriteria}
                >
                  <option value="">Any language</option>
                  <option value="en">English</option>
                  <option value="ar">Arabic</option>
                </select>
                {errors['criteria.preferred_language'] && (
                  <small className="field-error">
                    {errors['criteria.preferred_language'][0]}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Preferred channel</span>
                <select
                  name="preferred_channel"
                  value={criteria.preferred_channel}
                  onChange={updateCriteria}
                >
                  <option value="">Any preferred channel</option>
                  <option value="phone">Phone</option>
                  <option value="sms">SMS</option>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="email">Email</option>
                </select>
                <small className="field-help">
                  A preferred channel does not prove consent.
                </small>
                {errors['criteria.preferred_channel'] && (
                  <small className="field-error">
                    {errors['criteria.preferred_channel'][0]}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Consent channel</span>
                <select
                  name="consent_channel"
                  value={criteria.consent_channel}
                  onChange={updateCriteria}
                >
                  <option value="">Any consent channel</option>
                  <option value="phone">Phone</option>
                  <option value="sms">SMS</option>
                  <option value="whatsapp">WhatsApp</option>
                  <option value="email">Email</option>
                </select>
                {errors['criteria.consent_channel'] && (
                  <small className="field-error">
                    {errors['criteria.consent_channel'][0]}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Consent decision</span>
                <select
                  name="consent_status"
                  value={criteria.consent_status}
                  onChange={updateCriteria}
                >
                  <option value="">Any consent decision</option>
                  <option value="unknown">Unknown</option>
                  <option value="granted">Granted</option>
                  <option value="denied">Denied</option>
                  <option value="revoked">Revoked</option>
                </select>
                {errors['criteria.consent_status'] && (
                  <small className="field-error">
                    {errors['criteria.consent_status'][0]}
                  </small>
                )}
              </label>
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
              disabled={isSubmitting}
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create segment'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default SegmentForm