import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import RoleForm from './RoleForm.jsx'
import {
  createRole,
  deleteRole,
  listPermissions,
  listRoles,
  syncRolePermissions,
  updateRole,
} from '../services/userRoleManagement.js'

const standardRoleSlugs = [
  'tenant_admin',
  'coordinator',
  'field_agent',
]

function RolesPanel() {
  const [roles, setRoles] = useState([])
  const [permissions, setPermissions] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [permissionId, setPermissionId] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [refreshToken, setRefreshToken] = useState(0)

  const [formState, setFormState] = useState(null)
  const [roleToDelete, setRoleToDelete] = useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadAvailablePermissions() {
      try {
        const response = await listPermissions()

        if (isCurrent) {
          setPermissions(response ?? [])
        }
      } catch {
        if (isCurrent) {
          setPermissions([])
        }
      }
    }

    loadAvailablePermissions()

    return () => {
      isCurrent = false
    }
  }, [])

  useEffect(() => {
    let isCurrent = true

    async function loadRoleRecords() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listRoles({
          page,
          search,
          permissionId,
          perPage: 20,
        })

        if (!isCurrent) {
          return
        }

        setRoles(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setRoles([])
          setMeta(null)
          setLoadError(
            'Roles could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadRoleRecords()

    return () => {
      isCurrent = false
    }
  }, [page, search, permissionId, refreshToken])

  function refreshRoles() {
    setRefreshToken((current) => current + 1)
  }

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  function clearFilters() {
    setSearchInput('')
    setSearch('')
    setPermissionId('')
    setPage(1)
  }

  function openCreateForm() {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'create',
      role: null,
    })
  }

  function openEditForm(role) {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'edit',
      role,
    })
  }

  async function saveRole({
    profile,
    permissionIds,
  }) {
    if (formState.mode === 'edit') {
      await updateRole(formState.role.id, profile)
      await syncRolePermissions(
        formState.role.id,
        permissionIds,
      )

      setSuccessMessage('Role updated successfully.')
    } else {
      await createRole({
        ...profile,
        permission_ids: permissionIds,
      })

      setSuccessMessage('Role created successfully.')
    }

    setFormState(null)
    setPage(1)
    refreshRoles()
  }

  async function confirmDelete() {
    await deleteRole(roleToDelete.id)

    setRoleToDelete(null)
    setSuccessMessage('Role deleted successfully.')

    if (roles.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshRoles()
    }
  }

  return (
    <section className="management-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">
            Access control
          </p>

          <h3>Roles and permissions</h3>

          <p className="page-description">
            Maintain reusable tenant roles and define their
            permitted operations.
          </p>
        </div>

        <button
          type="button"
          className="primary-button"
          onClick={openCreateForm}
        >
          Create role
        </button>
      </div>

      {successMessage && (
        <div className="form-message success-message">
          {successMessage}
        </div>
      )}

      {actionError && (
        <div
          className="form-message error-message"
          role="alert"
        >
          {actionError}
        </div>
      )}

      <article className="content-card contacts-filter-card">
        <form
          className="contacts-search"
          onSubmit={applySearch}
        >
          <label className="form-field">
            <span>Search roles</span>

            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Role name, slug, or description"
            />
          </label>

          <button
            type="submit"
            className="primary-button"
          >
            Search
          </button>
        </form>

        <div className="management-filters">
          <label className="form-field">
            <span>Permission</span>

            <select
              value={permissionId}
              onChange={(event) => {
                setPage(1)
                setPermissionId(event.target.value)
              }}
            >
              <option value="">
                All permissions
              </option>

              {permissions.map((permission) => (
                <option
                  key={permission.id}
                  value={permission.id}
                >
                  {permission.name}
                </option>
              ))}
            </select>
          </label>

          <button
            type="button"
            className="secondary-button"
            onClick={clearFilters}
          >
            Clear filters
          </button>
        </div>
      </article>

      <article className="content-card contacts-card">
        {isLoading && (
          <p className="state-message">
            Loading roles...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading &&
          !loadError &&
          roles.length === 0 && (
            <div className="empty-state">
              <h3>No roles found</h3>

              <p>
                Create a custom role or change the current
                filters.
              </p>
            </div>
          )}

        {!isLoading &&
          !loadError &&
          roles.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table management-table">
                  <thead>
                    <tr>
                      <th>Role</th>
                      <th>Type</th>
                      <th>Users</th>
                      <th>Permissions</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {roles.map((role) => {
                      const isStandard =
                        standardRoleSlugs.includes(
                          role.slug,
                        )

                      return (
                        <tr key={role.id}>
                          <td>
                            <strong>{role.name}</strong>

                            <span className="table-secondary">
                              {role.slug}
                            </span>

                            {role.description && (
                              <span className="table-secondary">
                                {role.description}
                              </span>
                            )}
                          </td>

                          <td>
                            <span
                              className={
                                isStandard
                                  ? 'system-role-pill'
                                  : 'custom-role-pill'
                              }
                            >
                              {isStandard
                                ? 'Standard'
                                : 'Custom'}
                            </span>
                          </td>

                          <td>{role.users_count ?? 0}</td>

                          <td>
                            <div className="permission-summary">
                              {role.permissions
                                ?.slice(0, 4)
                                .map((permission) => (
                                  <span
                                    key={permission.id}
                                  >
                                    {permission.slug}
                                  </span>
                                ))}

                              {(role.permissions?.length ??
                                0) > 4 && (
                                <span>
                                  +
                                  {role.permissions.length -
                                    4}{' '}
                                  more
                                </span>
                              )}
                            </div>
                          </td>

                          <td>
                            <div className="table-actions">
                              <button
                                type="button"
                                className="text-button"
                                onClick={() =>
                                  openEditForm(role)
                                }
                              >
                                Edit
                              </button>

                              {!isStandard &&
                                (role.users_count ?? 0) ===
                                  0 && (
                                  <button
                                    type="button"
                                    className="text-button danger"
                                    onClick={() => {
                                      setActionError('')
                                      setSuccessMessage('')
                                      setRoleToDelete(role)
                                    }}
                                  >
                                    Delete
                                  </button>
                                )}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
              </div>

              {meta && meta.last_page > 1 && (
                <div className="pagination">
                  <button
                    type="button"
                    className="secondary-button"
                    onClick={() =>
                      setPage((current) =>
                        Math.max(1, current - 1),
                      )
                    }
                    disabled={meta.current_page <= 1}
                  >
                    Previous
                  </button>

                  <span>
                    Page {meta.current_page} of{' '}
                    {meta.last_page}
                  </span>

                  <button
                    type="button"
                    className="secondary-button"
                    onClick={() =>
                      setPage((current) =>
                        Math.min(
                          meta.last_page,
                          current + 1,
                        ),
                      )
                    }
                    disabled={
                      meta.current_page >= meta.last_page
                    }
                  >
                    Next
                  </button>
                </div>
              )}
            </>
          )}
      </article>

      {formState && (
        <RoleForm
          key={formState.role?.id ?? 'new-role'}
          role={formState.role}
          permissions={permissions}
          onSubmit={saveRole}
          onCancel={() => setFormState(null)}
        />
      )}

      {roleToDelete && (
        <ConfirmDialog
          title="Delete role?"
          message={`Delete “${roleToDelete.name}”? This action cannot be undone.`}
          confirmLabel="Delete role"
          confirmingLabel="Deleting..."
          errorMessage="The role could not be deleted. Standard roles and roles assigned to users must be retained."
          forbiddenMessage="You do not have permission to delete this role."
          onConfirm={confirmDelete}
          onCancel={() => setRoleToDelete(null)}
        />
      )}
    </section>
  )
}

export default RolesPanel