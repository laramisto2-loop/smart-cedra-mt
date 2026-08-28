import { useState } from 'react'
import {
  createPlatformTenant,
  updatePlatformTenant,
} from '../services/platformAdministration.js'

function slugify(value) {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
}

function initialFormValues(tenant) {
  return {
    name: tenant?.name ?? '',
    slug: tenant?.slug ?? '',
    status: tenant?.status ?? 'active',
    brandName:
      tenant?.settings?.brand_name
      ?? tenant?.name
      ?? '',
    primaryColor:
      tenant?.settings?.primary_color
      ?? '#12345B',
    timezone:
      tenant?.settings?.timezone
      ?? 'Asia/Beirut',
    administratorName: '',
    administratorEmail: '',
    administratorPassword: '',
    administratorPasswordConfirmation: '',
  }
}

export default function PlatformTenantForm({
  tenant,
  onCancel,
  onSaved,
}) {
  const [form, setForm] = useState(() =>
    initialFormValues(tenant),
  )
  const [slugTouched, setSlugTouched] = useState(
    Boolean(tenant),
  )
  const [errors, setErrors] = useState({})
  const [submitError, setSubmitError] = useState('')
  const [isSaving, setIsSaving] = useState(false)

  const isEditing = Boolean(tenant?.id)

  function updateField(field, value) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }))
  }

  function updateName(value) {
    setForm((current) => ({
      ...current,
      name: value,
      brandName:
        current.brandName === current.name
        || current.brandName === ''
          ? value
          : current.brandName,
      slug: slugTouched
        ? current.slug
        : slugify(value),
    }))
  }

  function fieldError(field) {
    return errors[field]?.[0] ?? ''
  }

  async function handleSubmit(event) {
    event.preventDefault()

    setIsSaving(true)
    setErrors({})
    setSubmitError('')

    const settingsPayload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      brand_name: form.brandName.trim() || null,
      primary_color: form.primaryColor || null,
      timezone: form.timezone.trim() || null,
    }

    const payload = isEditing
      ? settingsPayload
      : {
          ...settingsPayload,
          status: form.status,
          admin_name:
            form.administratorName.trim(),
          admin_email:
            form.administratorEmail.trim(),
          admin_password:
            form.administratorPassword,
          admin_password_confirmation:
            form.administratorPasswordConfirmation,
        }

    try {
      const savedTenant = isEditing
        ? await updatePlatformTenant(
            tenant.id,
            payload,
          )
        : await createPlatformTenant(payload)

      onSaved(savedTenant)
    } catch (requestError) {
      if (
        requestError.response?.status === 422
        && requestError.response?.data?.errors
      ) {
        setErrors(requestError.response.data.errors)
      } else {
        setSubmitError(
          requestError.response?.data?.message
          ?? 'The tenant could not be saved. Please try again.',
        )
      }

      setIsSaving(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card platform-tenant-modal"
        aria-modal="true"
        role="dialog"
      >
        <div className="modal-header">
          <div>
            <p className="eyebrow">
              PLATFORM ADMINISTRATION
            </p>
            <h2>
              {isEditing
                ? 'Edit tenant'
                : 'Create tenant'}
            </h2>
          </div>

          <button
            aria-label="Close"
            className="modal-close"
            disabled={isSaving}
            onClick={onCancel}
            type="button"
          >
            ×
          </button>
        </div>

        <p className="platform-form-introduction">
          {isEditing
            ? 'Update the campaign account and its visual configuration.'
            : 'Create an isolated campaign workspace and its first tenant administrator.'}
        </p>

        {submitError && (
          <div className="error-message" role="alert">
            {submitError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="platform-form-grid">
            <label className="form-field platform-field-wide">
              <span>Tenant name</span>
              <input
                autoFocus
                onChange={(event) =>
                  updateName(event.target.value)
                }
                placeholder="Example: Beirut Reform Campaign"
                required
                value={form.name}
              />
              {fieldError('name') && (
                <small className="platform-field-error">
                  {fieldError('name')}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Tenant slug</span>
              <input
                onChange={(event) => {
                  setSlugTouched(true)
                  updateField('slug', event.target.value)
                }}
                placeholder="beirut-reform-campaign"
                required
                value={form.slug}
              />
              {fieldError('slug') && (
                <small className="platform-field-error">
                  {fieldError('slug')}
                </small>
              )}
            </label>

            {!isEditing && (
              <label className="form-field">
                <span>Initial status</span>
                <select
                  onChange={(event) =>
                    updateField(
                      'status',
                      event.target.value,
                    )
                  }
                  value={form.status}
                >
                  <option value="active">Active</option>
                  <option value="suspended">
                    Suspended
                  </option>
                </select>
                {fieldError('status') && (
                  <small className="platform-field-error">
                    {fieldError('status')}
                  </small>
                )}
              </label>
            )}

            <label className="form-field">
              <span>Brand name</span>
              <input
                onChange={(event) =>
                  updateField(
                    'brandName',
                    event.target.value,
                  )
                }
                placeholder="ElectoFlow Campaign"
                value={form.brandName}
              />
              {fieldError('brand_name') && (
                <small className="platform-field-error">
                  {fieldError('brand_name')}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Timezone</span>
              <input
                onChange={(event) =>
                  updateField(
                    'timezone',
                    event.target.value,
                  )
                }
                placeholder="Asia/Beirut"
                value={form.timezone}
              />
              {fieldError('timezone') && (
                <small className="platform-field-error">
                  {fieldError('timezone')}
                </small>
              )}
            </label>

            <label className="form-field platform-field-wide">
              <span>Primary color</span>
              <div className="platform-color-control">
                <input
                  aria-label="Select primary color"
                  onChange={(event) =>
                    updateField(
                      'primaryColor',
                      event.target.value,
                    )
                  }
                  type="color"
                  value={form.primaryColor}
                />

                <input
                  onChange={(event) =>
                    updateField(
                      'primaryColor',
                      event.target.value,
                    )
                  }
                  pattern="^#[0-9A-Fa-f]{6}$"
                  placeholder="#12345B"
                  value={form.primaryColor}
                />
              </div>
              {fieldError('primary_color') && (
                <small className="platform-field-error">
                  {fieldError('primary_color')}
                </small>
              )}
            </label>
          </div>

          {!isEditing && (
            <div className="platform-administrator-section">
              <div>
                <h3>First tenant administrator</h3>
                <p>
                  This person receives full administrative
                  access inside the new tenant.
                </p>
              </div>

              <div className="platform-form-grid">
                <label className="form-field platform-field-wide">
                  <span>Administrator name</span>
                  <input
                    onChange={(event) =>
                      updateField(
                        'administratorName',
                        event.target.value,
                      )
                    }
                    placeholder="Example: Beirut Campaign Admin"
                    required
                    value={form.administratorName}
                  />
                  {fieldError('admin_name') && (
                    <small className="platform-field-error">
                      {fieldError('admin_name')}
                    </small>
                  )}
                </label>

                <label className="form-field platform-field-wide">
                  <span>Administrator email</span>
                  <input
                    onChange={(event) =>
                      updateField(
                        'administratorEmail',
                        event.target.value,
                      )
                    }
                    placeholder="admin@example.com"
                    required
                    type="email"
                    value={form.administratorEmail}
                  />
                  {fieldError('admin_email') && (
                    <small className="platform-field-error">
                      {fieldError('admin_email')}
                    </small>
                  )}
                </label>

                <label className="form-field">
                  <span>Password</span>
                  <input
                    minLength="8"
                    onChange={(event) =>
                      updateField(
                        'administratorPassword',
                        event.target.value,
                      )
                    }
                    required
                    type="password"
                    value={form.administratorPassword}
                  />
                  {fieldError('admin_password') && (
                    <small className="platform-field-error">
                      {fieldError('admin_password')}
                    </small>
                  )}
                </label>

                <label className="form-field">
                  <span>Confirm password</span>
                  <input
                    minLength="8"
                    onChange={(event) =>
                      updateField(
                        'administratorPasswordConfirmation',
                        event.target.value,
                      )
                    }
                    required
                    type="password"
                    value={
                      form.administratorPasswordConfirmation
                    }
                  />
                </label>
              </div>
            </div>
          )}

          <div className="modal-actions">
            <button
              className="secondary-button"
              disabled={isSaving}
              onClick={onCancel}
              type="button"
            >
              Cancel
            </button>

            <button
              className="primary-button"
              disabled={isSaving}
              type="submit"
            >
              {isSaving
                ? 'Saving...'
                : isEditing
                  ? 'Save changes'
                  : 'Create tenant'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}