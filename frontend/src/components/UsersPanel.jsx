import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import UserForm from './UserForm.jsx'
import {
  createUser,
  deleteUser,
  listRoles,
  listUsers,
  syncUserRoles,
  updateUser,
} from '../services/userRoleManagement.js'

function UsersPanel() {
  const [users, setUsers] = useState([])
  const [roles, setRoles] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [roleId, setRoleId] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [refreshToken, setRefreshToken] = useState(0)

  const [formState, setFormState] = useState(null)
  const [userToDelete, setUserToDelete] = useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadAvailableRoles() {
      try {
        const response = await listRoles({
          perPage: 100,
        })

        if (isCurrent) {
          setRoles(response.data ?? [])
        }
      } catch {
        if (isCurrent) {
          setRoles([])
        }
      }
    }

    loadAvailableRoles()

    return () => {
      isCurrent = false
    }
  }, [refreshToken])

  useEffect(() => {
    let isCurrent = true

    async function loadUsers() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listUsers({
          page,
          search,
          roleId,
        })

        if (!isCurrent) {
          return
        }

        setUsers(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setUsers([])
          setMeta(null)
          setLoadError(
            'Users could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadUsers()

    return () => {
      isCurrent = false
    }
  }, [page, search, roleId, refreshToken])

  function refreshUsers() {
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
    setRoleId('')
    setPage(1)
  }

  function openCreateForm() {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'create',
      user: null,
    })
  }

  function openEditForm(targetUser) {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'edit',
      user: targetUser,
    })
  }

  async function saveUser({ profile, roleIds }) {
    if (formState.mode === 'edit') {
      await updateUser(formState.user.id, profile)
      await syncUserRoles(formState.user.id, roleIds)

      setSuccessMessage('User updated successfully.')
    } else {
      await createUser({
        ...profile,
        role_ids: roleIds,
      })

      setSuccessMessage('User created successfully.')
    }

    setFormState(null)
    setPage(1)
    refreshUsers()
  }

  async function confirmDelete() {
    await deleteUser(userToDelete.id)

    setUserToDelete(null)
    setSuccessMessage('User deleted successfully.')

    if (users.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshUsers()
    }
  }

  return (
    <section className="management-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Tenant accounts</p>
          <h3>Users</h3>

          <p className="page-description">
            Create tenant users, update account details, and
            assign access roles.
          </p>
        </div>

        <button
          type="button"
          className="primary-button"
          onClick={openCreateForm}
        >
          Create user
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
            <span>Search users</span>

            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Name or email address"
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
            <span>Assigned role</span>

            <select
              value={roleId}
              onChange={(event) => {
                setPage(1)
                setRoleId(event.target.value)
              }}
            >
              <option value="">All roles</option>

              {roles.map((role) => (
                <option key={role.id} value={role.id}>
                  {role.name}
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
            Loading users...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading &&
          !loadError &&
          users.length === 0 && (
            <div className="empty-state">
              <h3>No users found</h3>

              <p>
                Create the first user or change the current
                filters.
              </p>
            </div>
          )}

        {!isLoading &&
          !loadError &&
          users.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table management-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Roles</th>
                      <th>Effective permissions</th>
                      <th>Created</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {users.map((targetUser) => (
                      <tr key={targetUser.id}>
                        <td>
                          <strong>
                            {targetUser.name}
                          </strong>

                          <span className="table-secondary">
                            {targetUser.email}
                          </span>

                          {targetUser.is_current_user && (
                            <span className="current-user-pill">
                              Current user
                            </span>
                          )}
                        </td>

                        <td>
                          <div className="role-pill-list">
                            {targetUser.roles?.map(
                              (role) => (
                                <span
                                  key={role.id}
                                  className="role-pill"
                                >
                                  {role.name}
                                </span>
                              ),
                            )}
                          </div>
                        </td>

                        <td>
                          {targetUser.permissions?.length ??
                            0}
                        </td>

                        <td>
                          {targetUser.created_at
                            ? new Date(
                                targetUser.created_at,
                              ).toLocaleDateString()
                            : 'Not recorded'}
                        </td>

                        <td>
                          <div className="table-actions">
                            <button
                              type="button"
                              className="text-button"
                              onClick={() =>
                                openEditForm(targetUser)
                              }
                            >
                              Edit
                            </button>

                            {!targetUser.is_current_user && (
                              <button
                                type="button"
                                className="text-button danger"
                                onClick={() => {
                                  setActionError('')
                                  setSuccessMessage('')
                                  setUserToDelete(
                                    targetUser,
                                  )
                                }}
                              >
                                Delete
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))}
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
        <UserForm
          key={formState.user?.id ?? 'new-user'}
          user={formState.user}
          roles={roles}
          onSubmit={saveUser}
          onCancel={() => setFormState(null)}
        />
      )}

      {userToDelete && (
        <ConfirmDialog
          title="Delete user?"
          message={`Delete “${userToDelete.name}”? Their account will no longer be able to access ElectoFlow.`}
          confirmLabel="Delete user"
          confirmingLabel="Deleting..."
          errorMessage="The user could not be deleted. Every tenant must retain at least one administrator."
          forbiddenMessage="You do not have permission to delete this user."
          onConfirm={confirmDelete}
          onCancel={() => setUserToDelete(null)}
        />
      )}
    </section>
  )
}

export default UsersPanel
