import { useEffect, useState } from 'react'
import {
  getTenantSettings,
  updateTenantSettings,
} from '../services/tenantSettings.js'

const DEFAULT_SETTINGS = {
  brand_name: '',
  primary_color: '#167ead',
  timezone: 'Asia/Beirut',
}

const TIMEZONES = [
  'Asia/Beirut',
  'UTC',
  'Europe/London',
  'Europe/Paris',
  'America/New_York',
]

function normalizeSettings(settings = {}) {
  return {
    brand_name: settings?.brand_name ?? '',
    primary_color:
      settings?.primary_color ?? DEFAULT_SETTINGS.primary_color,
    timezone: settings?.timezone ?? DEFAULT_SETTINGS.timezone,
  }
}

function SettingsPage({ user, onSaved }) {
  const [form, setForm] = useState(() =>
    normalizeSettings(user.tenant.settings),
  )
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [errors, setErrors] = useState({})
  const [message, setMessage] = useState('')
  const [saveFailed, setSaveFailed] = useState(false)
  const [loadError, setLoadError] = useState('')

  useEffect(() => {
    let isCurrent = true

    async function loadSettings() {
      try {
        const settings = await getTenantSettings()

        if (isCurrent) {
          setForm(normalizeSettings(settings))
        }
      } catch (requestError) {
        if (isCurrent) {
          setLoadError(
            requestError.response?.data?.message
            ?? 'Tenant settings could not be loaded.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadSettings()

    return () => {
      isCurrent = false
    }
  }, [])

  function updateField(field, value) {
    setForm((current) => ({
      ...current,
      [field]: value,
    }))
    setErrors((current) => {
      const next = { ...current }
      delete next[field]
      return next
    })
    setMessage('')
    setSaveFailed(false)
  }

  function fieldError(field) {
    return errors[field]?.[0] ?? ''
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setIsSaving(true)
    setErrors({})
    setMessage('')
    setSaveFailed(false)

    try {
      const settings = await updateTenantSettings({
        brand_name: form.brand_name.trim() || null,
        primary_color: form.primary_color,
        timezone: form.timezone,
      })

      setForm(normalizeSettings(settings))
      setMessage('Tenant settings saved successfully.')
      setSaveFailed(false)
      onSaved(settings)
    } catch (requestError) {
      if (
        requestError.response?.status === 422
        && requestError.response?.data?.errors
      ) {
        setErrors(requestError.response.data.errors)
        setSaveFailed(true)
        setMessage('Please correct the highlighted settings.')
      } else {
        setSaveFailed(true)
        setMessage(
          requestError.response?.data?.message
          ?? 'Tenant settings could not be saved.',
        )
      }
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <section className="settings-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">Tenant administration</p>
          <h2>Settings</h2>
          <p className="page-description">
            Manage the identity and regional defaults for this
            campaign workspace.
          </p>
        </div>
      </div>

      {loadError && (
        <div className="form-message error-message" role="alert">
          {loadError}
        </div>
      )}

      {!loadError && (
        <div className="settings-layout">
          <form
            className="content-card settings-form"
            onSubmit={handleSubmit}
          >
            <div className="card-heading">
              <div>
                <p className="eyebrow">Workspace identity</p>
                <h3>Branding and locale</h3>
              </div>
            </div>

            {message && (
              <div
                className={`form-message ${
                  saveFailed
                    ? 'error-message'
                    : 'success-message'
                }`}
                role={saveFailed ? 'alert' : 'status'}
              >
                {message}
              </div>
            )}

            <div className="settings-form-grid">
              <label className="form-field settings-field-wide">
                <span>Brand name</span>
                <input
                  disabled={isLoading || isSaving}
                  maxLength="255"
                  onChange={(event) =>
                    updateField('brand_name', event.target.value)
                  }
                  placeholder={user.tenant.name}
                  value={form.brand_name ?? ''}
                />
                <small>
                  Displayed in the workspace header. Leave blank
                  to use the tenant name.
                </small>
                {fieldError('brand_name') && (
                  <small className="settings-field-error">
                    {fieldError('brand_name')}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Timezone</span>
                <select
                  disabled={isLoading || isSaving}
                  onChange={(event) =>
                    updateField('timezone', event.target.value)
                  }
                  value={form.timezone}
                >
                  {(
                    TIMEZONES.includes(form.timezone)
                      ? TIMEZONES
                      : [form.timezone, ...TIMEZONES]
                  ).map((timezone) => (
                    <option key={timezone} value={timezone}>
                      {timezone}
                    </option>
                  ))}
                </select>
                {fieldError('timezone') && (
                  <small className="settings-field-error">
                    {fieldError('timezone')}
                  </small>
                )}
              </label>

              <label className="form-field">
                <span>Primary color</span>
                <div className="settings-color-control">
                  <input
                    aria-label="Select primary color"
                    disabled={isLoading || isSaving}
                    onChange={(event) =>
                      updateField(
                        'primary_color',
                        event.target.value,
                      )
                    }
                    type="color"
                    value={form.primary_color}
                  />
                  <input
                    disabled={isLoading || isSaving}
                    onChange={(event) =>
                      updateField(
                        'primary_color',
                        event.target.value,
                      )
                    }
                    pattern="^#[0-9A-Fa-f]{6}$"
                    value={form.primary_color}
                  />
                </div>
                {fieldError('primary_color') && (
                  <small className="settings-field-error">
                    {fieldError('primary_color')}
                  </small>
                )}
              </label>
            </div>

            <div className="settings-actions">
              <button
                className="primary-button"
                disabled={isLoading || isSaving}
                type="submit"
              >
                {isSaving ? 'Saving...' : 'Save settings'}
              </button>
            </div>
          </form>

          <aside className="content-card settings-summary">
            <p className="eyebrow">Current tenant</p>
            <h3>{user.tenant.name}</h3>
            <dl className="details-list">
              <div>
                <dt>Workspace code</dt>
                <dd>{user.tenant.slug}</dd>
              </div>
              <div>
                <dt>Status</dt>
                <dd>{user.tenant.status}</dd>
              </div>
              <div>
                <dt>Tenant ID</dt>
                <dd>{user.tenant.id}</dd>
              </div>
            </dl>
            <div className="settings-preview">
              <span
                aria-hidden="true"
                style={{ backgroundColor: form.primary_color }}
              >
                EF
              </span>
              <div>
                <strong>
                  {form.brand_name || user.tenant.name}
                </strong>
                <small>{form.timezone}</small>
              </div>
            </div>
          </aside>
        </div>
      )}
    </section>
  )
}

export default SettingsPage
