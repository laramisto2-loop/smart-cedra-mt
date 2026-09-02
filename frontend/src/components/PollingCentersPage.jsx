import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import GeographyFilters from './GeographyFilters.jsx'
import PollingCenterForm from './PollingCenterForm.jsx'
import { listAreas } from '../services/areas.js'
import {
  createPollingCenter,
  deletePollingCenter,
  listPollingCenters,
  updatePollingCenter,
} from '../services/pollingCenters.js'

async function loadAllAreas() {
  const firstResponse = await listAreas({ page: 1 })
  const areas = [...(firstResponse.data ?? [])]
  const lastPage = firstResponse.meta?.last_page ?? 1

  if (lastPage === 1) {
    return areas
  }

  const remainingRequests = Array.from(
    {
      length: lastPage - 1,
    },
    (_, index) =>
      listAreas({
        page: index + 2,
      }),
  )

  const remainingResponses = await Promise.all(remainingRequests)

  remainingResponses.forEach((response) => {
    areas.push(...(response.data ?? []))
  })

  return areas
}

function PollingCentersPage({ user }) {
  const [pollingCenters, setPollingCenters] = useState([])
  const [areas, setAreas] = useState([])
  const [areaFilter, setAreaFilter] = useState('')
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [areasError, setAreasError] = useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedPollingCenter, setSelectedPollingCenter] =
    useState(null)
  const [pollingCenterToDelete, setPollingCenterToDelete] =
    useState(null)
  const [reloadKey, setReloadKey] = useState(0)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('geography.create')
  const canUpdate = permissions.includes('geography.update')
  const canDelete = permissions.includes('geography.delete')

  useEffect(() => {
    let cancelled = false

    async function loadAreaOptions() {
      setAreasError('')

      try {
        const areaOptions = await loadAllAreas()

        if (!cancelled) {
          setAreas(areaOptions)
        }
      } catch {
        if (!cancelled) {
          setAreasError('Areas could not be loaded.')
        }
      }
    }

    loadAreaOptions()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadPollingCenterRecords() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listPollingCenters({
          page,
          areaId: areaFilter,
          search,
        })

        if (!cancelled) {
          setPollingCenters(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view geography.'
              : 'Polling centers could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadPollingCenterRecords()

    return () => {
      cancelled = true
    }
  }, [areaFilter, page, reloadKey, search])

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setAreaFilter('')
    setPage(1)
  }

  function changeAreaFilter(event) {
    setAreaFilter(event.target.value)
    setPage(1)
  }

  function openCreateForm() {
    setSelectedPollingCenter(null)
    setIsFormOpen(true)
  }

  function openEditForm(pollingCenter) {
    setSelectedPollingCenter(pollingCenter)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedPollingCenter(null)
  }

  async function handleSave(payload) {
    if (selectedPollingCenter) {
      await updatePollingCenter(
        selectedPollingCenter.id,
        payload,
      )
    } else {
      await createPollingCenter(payload)
    }

    closeForm()

    if (page !== 1 || areaFilter !== '') {
      setAreaFilter('')
      setPage(1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  function openDeleteDialog(pollingCenter) {
    setPollingCenterToDelete(pollingCenter)
  }

  function closeDeleteDialog() {
    setPollingCenterToDelete(null)
  }

  async function handleDelete() {
    await deletePollingCenter(pollingCenterToDelete.id)

    closeDeleteDialog()

    if (pollingCenters.length === 1 && page > 1) {
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
          <h2>Polling centers</h2>
          <p className="page-description">
            Manage polling locations and their parent areas.
          </p>
        </div>

        {canCreate && areas.length > 0 && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add polling center
          </button>
        )}
      </div>

      <GeographyFilters
        searchLabel="Search polling centers"
        searchPlaceholder="Name, address, or code"
        searchDraft={searchDraft}
        onSearchDraftChange={setSearchDraft}
        onSubmit={applySearch}
        onClear={clearFilters}
        filterLabel="Filter by area"
        filterValue={areaFilter}
        onFilterChange={changeAreaFilter}
        filterOptions={[
          { value: '', label: 'All areas' },
          ...areas.map((area) => ({
            value: area.id,
            label: `${area.district?.governorate?.name_en} — ${area.district?.name_en} — ${area.name_en}`,
          })),
        ]}
      />

      {areasError && (
        <div className="error-message" role="alert">
          {areasError}
        </div>
      )}

      <article className="content-card geography-card">
        {isLoading && (
          <p className="state-message">
            Loading polling centers...
          </p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading &&
          !error &&
          pollingCenters.length === 0 && (
            <div className="empty-state">
              <h3>No polling centers found</h3>
              <p>Add a polling center or change the current filters.</p>
            </div>
          )}

        {!isLoading &&
          !error &&
          pollingCenters.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table">
                  <thead>
                    <tr>
                      <th>English name</th>
                      <th>Arabic name</th>
                      <th>Area</th>
                      <th>Address</th>
                      <th>Code</th>
                      <th>Stations</th>
                      {(canUpdate || canDelete) && (
                        <th>Actions</th>
                      )}
                    </tr>
                  </thead>

                  <tbody>
                    {pollingCenters.map((pollingCenter) => (
                      <tr key={pollingCenter.id}>
                        <td>{pollingCenter.name_en}</td>
                        <td lang="ar" dir="rtl">
                          {pollingCenter.name_ar}
                        </td>
                        <td>
                          <span>
                            {pollingCenter.area?.name_en ?? '—'}
                          </span>
                          <small className="table-detail">
                            {pollingCenter.area?.district?.name_en ??
                              ''}
                          </small>
                        </td>
                        <td>
                          <span>
                            {pollingCenter.address_en ?? '—'}
                          </span>
                          {pollingCenter.address_ar && (
                            <small
                              className="table-detail"
                              lang="ar"
                              dir="rtl"
                            >
                              {pollingCenter.address_ar}
                            </small>
                          )}
                        </td>
                        <td>
                          <span className="code-pill">
                            {pollingCenter.code}
                          </span>
                        </td>
                        <td>
                          {pollingCenter.polling_stations_count}
                        </td>

                        {(canUpdate || canDelete) && (
                          <td>
                            <div className="table-actions">
                              {canUpdate && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(pollingCenter)
                                  }
                                >
                                  Edit
                                </button>
                              )}

                              {canDelete && (
                                <button
                                  type="button"
                                  className="text-button danger"
                                  onClick={() =>
                                    openDeleteDialog(
                                      pollingCenter,
                                    )
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
                    Page {pagination.current_page} of{' '}
                    {pagination.last_page}
                  </span>

                  <button
                    type="button"
                    className="secondary-button"
                    disabled={
                      pagination.current_page ===
                      pagination.last_page
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
        <PollingCenterForm
          pollingCenter={selectedPollingCenter}
          areas={areas}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {pollingCenterToDelete && (
        <ConfirmDialog
          title="Delete polling center?"
          message={
            pollingCenterToDelete.polling_stations_count > 0
              ? `Deleting ${pollingCenterToDelete.name_en} will also delete all of its polling stations. This action cannot be undone.`
              : `Delete ${pollingCenterToDelete.name_en}? This action cannot be undone.`
          }
          confirmLabel="Delete polling center"
          onConfirm={handleDelete}
          onCancel={closeDeleteDialog}
        />
      )}
    </section>
  )
}

export default PollingCentersPage
