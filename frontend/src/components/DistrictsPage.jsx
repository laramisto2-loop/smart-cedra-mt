import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import DistrictForm from './DistrictForm.jsx'
import GeographyFilters from './GeographyFilters.jsx'
import { listGovernorates } from '../services/governorates.js'
import {
  createDistrict,
  deleteDistrict,
  listDistricts,
  updateDistrict,
} from '../services/districts.js'

function DistrictsPage({ user }) {
  const [districts, setDistricts] = useState([])
  const [governorates, setGovernorates] = useState([])
  const [governorateFilter, setGovernorateFilter] = useState('')
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [governoratesError, setGovernoratesError] = useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedDistrict, setSelectedDistrict] = useState(null)
  const [districtToDelete, setDistrictToDelete] = useState(null)
  const [reloadKey, setReloadKey] = useState(0)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('geography.create')
  const canUpdate = permissions.includes('geography.update')
  const canDelete = permissions.includes('geography.delete')

  useEffect(() => {
    let cancelled = false

    async function loadGovernorates() {
      setGovernoratesError('')

      try {
        const response = await listGovernorates({ page: 1 })

        if (!cancelled) {
          setGovernorates(response.data ?? [])
        }
      } catch {
        if (!cancelled) {
          setGovernoratesError(
            'Governorates could not be loaded.',
          )
        }
      }
    }

    loadGovernorates()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadDistricts() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listDistricts({
          page,
          governorateId: governorateFilter,
          search,
        })

        if (!cancelled) {
          setDistricts(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view geography.'
              : 'Districts could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadDistricts()

    return () => {
      cancelled = true
    }
  }, [governorateFilter, page, reloadKey, search])

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setGovernorateFilter('')
    setPage(1)
  }

  function changeGovernorateFilter(event) {
    setGovernorateFilter(event.target.value)
    setPage(1)
  }

  function openCreateForm() {
    setSelectedDistrict(null)
    setIsFormOpen(true)
  }

  function openEditForm(district) {
    setSelectedDistrict(district)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedDistrict(null)
  }

  async function handleSave(payload) {
    if (selectedDistrict) {
      await updateDistrict(selectedDistrict.id, payload)
    } else {
      await createDistrict(payload)
    }

    closeForm()

    if (page !== 1 || governorateFilter !== '') {
      setGovernorateFilter('')
      setPage(1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  function openDeleteDialog(district) {
    setDistrictToDelete(district)
  }

  function closeDeleteDialog() {
    setDistrictToDelete(null)
  }

  async function handleDelete() {
    await deleteDistrict(districtToDelete.id)

    closeDeleteDialog()

    if (districts.length === 1 && page > 1) {
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
          <h2>Districts</h2>
          <p className="page-description">
            Manage districts and their parent governorates.
          </p>
        </div>

        {canCreate && governorates.length > 0 && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add district
          </button>
        )}
      </div>

      <GeographyFilters
        searchLabel="Search districts"
        searchPlaceholder="English name, Arabic name, or code"
        searchDraft={searchDraft}
        onSearchDraftChange={setSearchDraft}
        onSubmit={applySearch}
        onClear={clearFilters}
        filterLabel="Filter by governorate"
        filterValue={governorateFilter}
        onFilterChange={changeGovernorateFilter}
        filterOptions={[
          { value: '', label: 'All governorates' },
          ...governorates.map((governorate) => ({
            value: governorate.id,
            label: governorate.name_en,
          })),
        ]}
      />

      {governoratesError && (
        <div className="error-message" role="alert">
          {governoratesError}
        </div>
      )}

      <article className="content-card geography-card">
        {isLoading && (
          <p className="state-message">Loading districts...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && districts.length === 0 && (
          <div className="empty-state">
            <h3>No districts found</h3>
            <p>
              Add a district or change the current filters.
            </p>
          </div>
        )}

        {!isLoading && !error && districts.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table">
                <thead>
                  <tr>
                    <th>English name</th>
                    <th>Arabic name</th>
                    <th>Governorate</th>
                    <th>Code</th>
                    <th>Areas</th>
                    {(canUpdate || canDelete) && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {districts.map((district) => (
                    <tr key={district.id}>
                      <td>{district.name_en}</td>
                      <td lang="ar" dir="rtl">
                        {district.name_ar}
                      </td>
                      <td>{district.governorate?.name_en ?? '—'}</td>
                      <td>
                        <span className="code-pill">
                          {district.code}
                        </span>
                      </td>
                      <td>{district.areas_count}</td>

                      {(canUpdate || canDelete) && (
                        <td>
                          <div className="table-actions">
                            {canUpdate && (
                              <button
                                type="button"
                                className="text-button"
                                onClick={() => openEditForm(district)}
                              >
                                Edit
                              </button>
                            )}

                            {canDelete && (
                              <button
                                type="button"
                                className="text-button danger"
                                onClick={() =>
                                  openDeleteDialog(district)
                                }
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
                  onClick={() =>
                    setPage((current) => current - 1)
                  }
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
                  onClick={() =>
                    setPage((current) => current + 1)
                  }
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </article>

      {isFormOpen && (
        <DistrictForm
          district={selectedDistrict}
          governorates={governorates}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {districtToDelete && (
        <ConfirmDialog
          title="Delete district?"
          message={
            districtToDelete.areas_count > 0
              ? `Deleting ${districtToDelete.name_en} will also delete its areas and all nested geography. This action cannot be undone.`
              : `Delete ${districtToDelete.name_en}? This action cannot be undone.`
          }
          confirmLabel="Delete district"
          onConfirm={handleDelete}
          onCancel={closeDeleteDialog}
        />
      )}
    </section>
  )
}

export default DistrictsPage
