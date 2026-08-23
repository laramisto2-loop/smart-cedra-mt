import { useMemo, useState } from 'react'

const standardRoleSlugs = [
  'tenant_admin',
  'coordinator',
  'field_agent',
]

function RoleForm({
  role = null,
  permissions = [],
  onSubmit,
  onCancel,
}) {
  const isEditing = role !== null
  const isStandardRole = standardRoleSlugs.includes(
    role?.slug,
  )

  const [form, setForm] = useState({
    name: role?.name ?? '',
    slug: role?.slug ?? '',
    description: role?.description ?? '',
    permissionIds:
      role?.permissions?.map((permission) =>
        Number(permission.id),
      ) ?? [],
  })

  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const groupedPermissions = useMemo(() => {
    return permissions.reduce((groups, permission) => {
      const groupName =
        permission.slug.split('.')[0] || 'general'

      if (!groups[groupName]) {
        groups[groupName] = []
      }

      groups[groupName].push(permission)

      return groups
    }, {})
  }, [permissions])

  function clearError(name) {
    setErrors((current) => ({
      ...current,
      [name]: undefined,
      permission_ids:
        name === 'permissionIds'
          ? undefined
          : current.permission_ids,
    }))
  }

  function updateField(event) {
    const { name, value } = event.target

    setForm((current) => ({
      ...current,
      [name]:
        name === 'slug'
          ? value.toLowerCase().replace(/\s+/g, '_')
          : value,
    }))

    clearError(name)
  }

  function togglePermission(permissionId) {
    setForm((current) => ({
      ...current,
      permissionIds: current.permissionIds.includes(
        permissionId,
      )
        ? current.permissionIds.filter(
            (id) => id !== permissionId,
          )
        : [...current.permissionIds, permissionId],
    }))

    clearError('permissionIds')
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')

    if (form.permissionIds.length === 0) {
      setErrors({
        permission_ids: [
          'Select at least one permission.',
        ],
      })

      return
    }

    setIsSubmitting(true)

    try {
      await onSubmit({
        profile: {
          name: form.name.trim(),
          slug: form.slug.trim().toLowerCase(),
          description:
            form.description.trim() === ''
              ? null
              : form.description.trim(),
        },
        permissionIds: form.permissionIds,
      })
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        setErrors(requestError.response.data.errors ?? {})
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to save this role.',
        )
      } else {
        setGeneralError(
          'The role could not be saved. Please try again.',
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card role-form-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="role-form-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">
              Role administration
            </p>

            <h3 id="role-form-title">
              {isEditing ? 'Edit role' : 'Create role'}
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
          Roles combine permissions into reusable access
          profiles. Standard role slugs cannot be changed.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="incident-form-grid">
            <label className="form-field">
              <span>Role name</span>

              <input
                type="text"
                name="name"
                value={form.name}
                onChange={updateField}
                maxLength="255"
                placeholder="Example: Regional supervisor"
                required
                autoFocus
              />

              {errors.name && (
                <small className="field-error">
                  {errors.name[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Role slug</span>

              <input
                type="text"
                name="slug"
                value={form.slug}
                onChange={updateField}
                maxLength="255"
                placeholder="regional_supervisor"
                disabled={isStandardRole}
                required
              />

              <small className="field-help">
                Lowercase letters, numbers, underscores, or
                hyphens.
              </small>

              {errors.slug && (
                <small className="field-error">
                  {errors.slug[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Description (optional)</span>

            <textarea
              name="description"
              value={form.description}
              onChange={updateField}
              maxLength="1000"
              rows="3"
              placeholder="Explain what users with this role can do."
            />

            {errors.description && (
              <small className="field-error">
                {errors.description[0]}
              </small>
            )}
          </label>

          <fieldset className="permission-fieldset">
            <legend>Permissions</legend>

            <p className="field-help">
              Select at least one permission for this role.
            </p>

            <div className="permission-groups">
              {Object.entries(groupedPermissions).map(
                ([groupName, groupPermissions]) => (
                  <section
                    key={groupName}
                    className="permission-group"
                  >
                    <h4>
                      {groupName
                        .replaceAll('_', ' ')
                        .replace(
                          /\b\w/g,
                          (letter) =>
                            letter.toUpperCase(),
                        )}
                    </h4>

                    <div className="permission-options">
                      {groupPermissions.map(
                        (permission) => (
                          <label
                            key={permission.id}
                            className="permission-option"
                          >
                            <input
                              type="checkbox"
                              checked={form.permissionIds.includes(
                                Number(permission.id),
                              )}
                              onChange={() =>
                                togglePermission(
                                  Number(permission.id),
                                )
                              }
                            />

                            <span>
                              <strong>
                                {permission.name}
                              </strong>

                              <small>
                                {permission.slug}
                              </small>
                            </span>
                          </label>
                        ),
                      )}
                    </div>
                  </section>
                ),
              )}
            </div>

            {errors.permission_ids && (
              <small className="field-error">
                {errors.permission_ids[0]}
              </small>
            )}

            {errors['permission_ids.0'] && (
              <small className="field-error">
                {errors['permission_ids.0'][0]}
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
                permissions.length === 0
              }
            >
              {isSubmitting
                ? 'Saving...'
                : isEditing
                  ? 'Save role'
                  : 'Create role'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default RoleForm