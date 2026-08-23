import { useState } from 'react'

function UserForm({
  user = null,
  roles = [],
  onSubmit,
  onCancel,
}) {
  const isEditing = user !== null

  const [form, setForm] = useState({
    name: user?.name ?? '',
    email: user?.email ?? '',
    password: '',
    password_confirmation: '',
    roleIds:
      user?.roles?.map((role) => Number(role.id)) ?? [],
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  function clearError(name) {
    setErrors((current) => ({
      ...current,
      [name]: undefined,
      role_ids: name === 'roleIds'
        ? undefined
        : current.role_ids,
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

  function toggleRole(roleId) {
    setForm((current) => {
      const roleIds = current.roleIds.includes(roleId)
        ? current.roleIds.filter(
            (currentRoleId) => currentRoleId !== roleId,
          )
        : [...current.roleIds, roleId]

      return {
        ...current,
        roleIds,
      }
    })

    clearError('roleIds')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')

    if (form.roleIds.length === 0) {
      setErrors({
        role_ids: ['Select at least one role.'],
      })

      return
    }

    setIsSubmitting(true)

    const profile = {
      name: form.name.trim(),
      email: form.email.trim().toLowerCase(),
    }

    if (!isEditing || form.password !== '') {
      profile.password = form.password
      profile.password_confirmation =
        form.password_confirmation
    }

    try {
      await onSubmit({
        profile,
        roleIds: form.roleIds,
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to save this user.',
        )
      } else {
        setGeneralError(
          'The user could not be saved. Please try again.',
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
        aria-labelledby="user-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">
              User administration
            </p>

            <h3 id="user-form-title">
              {isEditing ? 'Edit user' : 'Create user'}
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

        <div className="info-message">
          Users belong to the active tenant. Assign at least
          one role to determine their available features.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Full name</span>

            <input
              type="text"
              name="name"
              value={form.name}
              onChange={updateField}
              maxLength="255"
              placeholder="Example: Cedra Coordinator"
              autoFocus
              required
            />

            {errors.name && (
              <small className="field-error">
                {errors.name[0]}
              </small>
            )}
          </label>

          <label className="form-field">
            <span>Email address</span>

            <input
              type="email"
              name="email"
              value={form.email}
              onChange={updateField}
              maxLength="255"
              placeholder="coordinator@cedra.test"
              required
            />

            {errors.email && (
              <small className="field-error">
                {errors.email[0]}
              </small>
            )}
          </label>

          <div className="incident-form-grid">
            <label className="form-field">
              <span>
                Password
                {isEditing ? ' (optional)' : ''}
              </span>

              <input
                type="password"
                name="password"
                value={form.password}
                onChange={updateField}
                autoComplete="new-password"
                placeholder={
                  isEditing
                    ? 'Leave blank to keep current password'
                    : 'Enter a secure password'
                }
                required={!isEditing}
              />

              {isEditing && (
                <small className="field-help">
                  Enter a password only when it should be
                  changed.
                </small>
              )}

              {errors.password && (
                <small className="field-error">
                  {errors.password[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>
                Confirm password
                {isEditing ? ' (optional)' : ''}
              </span>

              <input
                type="password"
                name="password_confirmation"
                value={form.password_confirmation}
                onChange={updateField}
                autoComplete="new-password"
                placeholder="Repeat the password"
                required={!isEditing || form.password !== ''}
              />
            </label>
          </div>

          <fieldset className="role-selection-fieldset">
            <legend>Assigned roles</legend>

            <p className="field-help">
              Select one or more roles for this user.
            </p>

            <div className="role-selection-list">
              {roles.map((role) => (
                <label
                  key={role.id}
                  className="role-selection-option"
                >
                  <input
                    type="checkbox"
                    checked={form.roleIds.includes(
                      Number(role.id),
                    )}
                    onChange={() =>
                      toggleRole(Number(role.id))
                    }
                  />

                  <span>
                    <strong>{role.name}</strong>

                    <small>
                      {role.description ||
                        `${role.permissions?.length ?? 0} permissions`}
                    </small>
                  </span>
                </label>
              ))}
            </div>

            {roles.length === 0 && (
              <div className="info-message">
                No roles are currently available.
              </div>
            )}

            {errors.role_ids && (
              <small className="field-error">
                {errors.role_ids[0]}
              </small>
            )}

            {errors['role_ids.0'] && (
              <small className="field-error">
                {errors['role_ids.0'][0]}
              </small>
            )}
          </fieldset>

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
              disabled={
                isSubmitting ||
                roles.length === 0
              }
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save user'
                  : 'Create user'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default UserForm