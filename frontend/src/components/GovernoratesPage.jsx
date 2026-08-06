import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import GovernorateForm from './GovernorateForm.jsx'
import {
  createGovernorate,
  deleteGovernorate,
  listGovernorates,
  updateGovernorate,
} from '../services/governorates.js'

function GovernoratesPage({ user }) {
  const [governorates, setGovernorates] = useState([])
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedGovernorate, setSelectedGovernorate] = useState(null)
  const [reloadKey, setReloadKey] = useState(0)
  const [governorateToDelete, setGovernorateToDelete] = useState(null)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('geography.create')
  const canUpdate = permissions.includes('geography.update')
  const canDelete = permissions.includes('geography.delete')

  useEffect(() => {
    let cancelled = false

    async function loadGovernorates() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listGovernorates(page)

        if (!cancelled) {
          setGovernorates(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view geography.'
              : 'Governorates could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadGovernorates()

    return () => {
      cancelled = true
    }
  }, [page, reloadKey])

  function openCreateForm() {
  setSelectedGovernorate(null)
  setIsFormOpen(true)
    }

function openEditForm(governorate) {
  setSelectedGovernorate(governorate)
  setIsFormOpen(true)
}

function closeForm() {
  setIsFormOpen(false)
  setSelectedGovernorate(null)
}

async function handleSave(payload) {
  if (selectedGovernorate) {
    await updateGovernorate(selectedGovernorate.id, payload)
  } else {
    await createGovernorate(payload)
  }

  closeForm()

  if (page === 1) {
    setReloadKey((current) => current + 1)
  } else {
    setPage(1)
  }
}

function openDeleteDialog(governorate) {
  setGovernorateToDelete(governorate)
}

function closeDeleteDialog() {
  setGovernorateToDelete(null)
}

async function handleDelete() {
  await deleteGovernorate(governorateToDelete.id)

  closeDeleteDialog()

  if (governorates.length === 1 && page > 1) {
    setPage((current) => current - 1)
  } else {
    setReloadKey((current) => current + 1)
  }
}

  return (
    <section className="geography-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">Geography management</p>
          <h2>Governorates</h2>
          <p className="page-description">
            Manage the governorates belonging to {user.tenant.name}.
          </p>
        </div>

        {canCreate && (
          <button type="button" className="primary-button" onClick={openCreateForm}>
            Add governorate
          </button>
        )}
      </div>

      <article className="content-card geography-card">
        {isLoading && (
          <p className="state-message">Loading governorates...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && governorates.length === 0 && (
          <div className="empty-state">
            <h3>No governorates yet</h3>
            <p>Create the first governorate for this campaign.</p>
          </div>
        )}

        {!isLoading && !error && governorates.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table">
                <thead>
                  <tr>
                    <th>English name</th>
                    <th>Arabic name</th>
                    <th>Code</th>
                    <th>Districts</th>
                    {(canUpdate || canDelete) && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {governorates.map((governorate) => (
                    <tr key={governorate.id}>
                      <td>{governorate.name_en}</td>
                      <td lang="ar" dir="rtl">
                        {governorate.name_ar}
                      </td>
                      <td>
                        <span className="code-pill">
                          {governorate.code}
                        </span>
                      </td>
                      <td>{governorate.districts_count}</td>

                      {(canUpdate || canDelete) && (
                        <td>
                          <div className="table-actions">
                            {canUpdate && (
                              <button
                                type="button"
                                className="text-button"
                                onClick={() => openEditForm(governorate)}
                              >
                                Edit
                              </button>
                            )}

                            {canDelete && (
                              <button
                                type="button"
                                className="text-button danger"
                                onClick={() => openDeleteDialog(governorate)}
                              >
                                Delete
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {pagination && pagination.last_page > 1 && (
              <div className="pagination">
                <button
                  type="button"
                  className="secondary-button"
                  disabled={pagination.current_page === 1}
                  onClick={() => setPage((current) => current - 1)}
                >
                  Previous
                </button>

                <span>
                  Page {pagination.current_page} of {pagination.last_page}
                </span>

                <button
                  type="button"
                  className="secondary-button"
                  disabled={
                    pagination.current_page === pagination.last_page
                  }
                  onClick={() => setPage((current) => current + 1)}
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </article>

      {isFormOpen && (
        <GovernorateForm
            governorate={selectedGovernorate}
            usedCodes={governorates.map(
            (governorate) => governorate.code,
            )}
            onSubmit={handleSave}
            onCancel={closeForm}
        />
        )}

        {governorateToDelete && (
            <ConfirmDialog
                title="Delete governorate?"
                 message={
                governorateToDelete.districts_count > 0
                ? `Deleting ${governorateToDelete.name_en} will also delete its districts and all nested geography. This action cannot be undone.`
                : `Delete ${governorateToDelete.name_en}? This action cannot be undone.`
                }
                confirmLabel="Delete governorate"
                onConfirm={handleDelete}
                onCancel={closeDeleteDialog}
            />
        )}
    </section>
  )
}

export default GovernoratesPage

