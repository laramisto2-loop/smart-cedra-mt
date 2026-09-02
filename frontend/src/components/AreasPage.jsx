import { useEffect, useState } from 'react'
import AreaForm from './AreaForm.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import GeographyFilters from './GeographyFilters.jsx'
import {
  createArea,
  deleteArea,
  listAreas,
  updateArea,
} from '../services/areas.js'
import { listDistricts } from '../services/districts.js'

async function loadAllDistricts() {
  const firstResponse = await listDistricts({ page: 1 })
  const districts = [...(firstResponse.data ?? [])]
  const lastPage = firstResponse.meta?.last_page ?? 1

  if (lastPage === 1) {
    return districts
  }

  const remainingRequests = Array.from(
    {
      length: lastPage - 1,
    },
    (_, index) =>
      listDistricts({
        page: index + 2,
      }),
  )

  const remainingResponses = await Promise.all(remainingRequests)

  remainingResponses.forEach((response) => {
    districts.push(...(response.data ?? []))
  })

  return districts
}

function formatAreaType(type) {
  if (!type) {
    return '—'
  }

  return type.charAt(0).toUpperCase() + type.slice(1)
}

function AreasPage({ user }) {
  const [areas, setAreas] = useState([])
  const [districts, setDistricts] = useState([])
  const [districtFilter, setDistrictFilter] = useState('')
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [districtsError, setDistrictsError] = useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedArea, setSelectedArea] = useState(null)
  const [areaToDelete, setAreaToDelete] = useState(null)
  const [reloadKey, setReloadKey] = useState(0)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('geography.create')
  const canUpdate = permissions.includes('geography.update')
  const canDelete = permissions.includes('geography.delete')

  useEffect(() => {
    let cancelled = false

    async function loadDistrictOptions() {
      setDistrictsError('')

      try {
        const districtOptions = await loadAllDistricts()

        if (!cancelled) {
          setDistricts(districtOptions)
        }
      } catch {
        if (!cancelled) {
          setDistrictsError('Districts could not be loaded.')
        }
      }
    }

    loadDistrictOptions()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadAreaRecords() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listAreas({
          page,
          districtId: districtFilter,
          search,
        })

        if (!cancelled) {
          setAreas(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view geography.'
              : 'Areas could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadAreaRecords()

    return () => {
      cancelled = true
    }
  }, [districtFilter, page, reloadKey, search])

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setDistrictFilter('')
    setPage(1)
  }

  function changeDistrictFilter(event) {
    setDistrictFilter(event.target.value)
    setPage(1)
  }

  function openCreateForm() {
    setSelectedArea(null)
    setIsFormOpen(true)
  }

  function openEditForm(area) {
    setSelectedArea(area)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedArea(null)
  }

  async function handleSave(payload) {
    if (selectedArea) {
      await updateArea(selectedArea.id, payload)
    } else {
      await createArea(payload)
    }

    closeForm()

    if (page !== 1 || districtFilter !== '') {
      setDistrictFilter('')
      setPage(1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  function openDeleteDialog(area) {
    setAreaToDelete(area)
  }

  function closeDeleteDialog() {
    setAreaToDelete(null)
  }

  async function handleDelete() {
    await deleteArea(areaToDelete.id)

    closeDeleteDialog()

    if (areas.length === 1 && page > 1) {
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
          <h2>Areas</h2>
          <p className="page-description">
            Manage areas and their parent districts.
          </p>
        </div>

        {canCreate && districts.length > 0 && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add area
          </button>
        )}
      </div>

      <GeographyFilters
        searchLabel="Search areas"
        searchPlaceholder="English name, Arabic name, or code"
        searchDraft={searchDraft}
        onSearchDraftChange={setSearchDraft}
        onSubmit={applySearch}
        onClear={clearFilters}
        filterLabel="Filter by district"
        filterValue={districtFilter}
        onFilterChange={changeDistrictFilter}
        filterOptions={[
          { value: '', label: 'All districts' },
          ...districts.map((district) => ({
            value: district.id,
            label: `${district.governorate?.name_en} — ${district.name_en}`,
          })),
        ]}
      />

      {districtsError && (
        <div className="error-message" role="alert">
          {districtsError}
        </div>
      )}

      <article className="content-card geography-card">
        {isLoading && (
          <p className="state-message">Loading areas...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && areas.length === 0 && (
          <div className="empty-state">
            <h3>No areas found</h3>
            <p>Add an area or change the current filters.</p>
          </div>
        )}

        {!isLoading && !error && areas.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table">
                <thead>
                  <tr>
                    <th>English name</th>
                    <th>Arabic name</th>
                    <th>District</th>
                    <th>Type</th>
                    <th>Code</th>
                    <th>Polling centers</th>
                    {(canUpdate || canDelete) && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {areas.map((area) => (
                    <tr key={area.id}>
                      <td>{area.name_en}</td>
                      <td lang="ar" dir="rtl">
                        {area.name_ar}
                      </td>
                      <td>
                        <span>{area.district?.name_en ?? '—'}</span>
                        <small className="table-detail">
                          {area.district?.governorate?.name_en ?? ''}
                        </small>
                      </td>
                      <td>{formatAreaType(area.type)}</td>
                      <td>
                        <span className="code-pill">{area.code}</span>
                      </td>
                      <td>{area.polling_centers_count}</td>

                      {(canUpdate || canDelete) && (
                        <td>
                          <div className="table-actions">
                            {canUpdate && (
                              <button
                                type="button"
                                className="text-button"
                                onClick={() => openEditForm(area)}
                              >
                                Edit
                              </button>
                            )}

                            {canDelete && (
                              <button
                                type="button"
                                className="text-button danger"
                                onClick={() =>
                                  openDeleteDialog(area)
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
        <AreaForm
          area={selectedArea}
          districts={districts}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {areaToDelete && (
        <ConfirmDialog
          title="Delete area?"
          message={
            areaToDelete.polling_centers_count > 0
              ? `Deleting ${areaToDelete.name_en} will also delete its polling centers and stations. This action cannot be undone.`
              : `Delete ${areaToDelete.name_en}? This action cannot be undone.`
          }
          confirmLabel="Delete area"
          onConfirm={handleDelete}
          onCancel={closeDeleteDialog}
        />
      )}
    </section>
  )
}

export default AreasPage
