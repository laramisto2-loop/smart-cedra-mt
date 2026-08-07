import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import PollingStationForm from './PollingStationForm.jsx'
import { listPollingCenters } from '../services/pollingCenters.js'
import {
  createPollingStation,
  deletePollingStation,
  listPollingStations,
  updatePollingStation,
} from '../services/pollingStations.js'

async function loadAllPollingCenters() {
  const firstResponse = await listPollingCenters({ page: 1 })
  const pollingCenters = [...(firstResponse.data ?? [])]
  const lastPage = firstResponse.meta?.last_page ?? 1

  if (lastPage === 1) {
    return pollingCenters
  }

  const remainingRequests = Array.from(
    {
      length: lastPage - 1,
    },
    (_, index) =>
      listPollingCenters({
        page: index + 2,
      }),
  )

  const remainingResponses = await Promise.all(remainingRequests)

  remainingResponses.forEach((response) => {
    pollingCenters.push(...(response.data ?? []))
  })

  return pollingCenters
}

function pollingCenterLabel(pollingCenter) {
  return [
    pollingCenter.area?.district?.governorate?.name_en,
    pollingCenter.area?.district?.name_en,
    pollingCenter.area?.name_en,
    pollingCenter.name_en,
  ]
    .filter(Boolean)
    .join(' — ')
}

function PollingStationsPage({ user }) {
  const [pollingStations, setPollingStations] = useState([])
  const [pollingCenters, setPollingCenters] = useState([])
  const [pollingCenterFilter, setPollingCenterFilter] =
    useState('')
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [pollingCentersError, setPollingCentersError] =
    useState('')
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedPollingStation, setSelectedPollingStation] =
    useState(null)
  const [pollingStationToDelete, setPollingStationToDelete] =
    useState(null)
  const [reloadKey, setReloadKey] = useState(0)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('geography.create')
  const canUpdate = permissions.includes('geography.update')
  const canDelete = permissions.includes('geography.delete')

  useEffect(() => {
    let cancelled = false

    async function loadPollingCenterOptions() {
      setPollingCentersError('')

      try {
        const options = await loadAllPollingCenters()

        if (!cancelled) {
          setPollingCenters(options)
        }
      } catch {
        if (!cancelled) {
          setPollingCentersError(
            'Polling centers could not be loaded.',
          )
        }
      }
    }

    loadPollingCenterOptions()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadPollingStationRecords() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listPollingStations({
          page,
          pollingCenterId: pollingCenterFilter,
        })

        if (!cancelled) {
          setPollingStations(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view geography.'
              : 'Polling stations could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadPollingStationRecords()

    return () => {
      cancelled = true
    }
  }, [page, pollingCenterFilter, reloadKey])

  function changePollingCenterFilter(event) {
    setPollingCenterFilter(event.target.value)
    setPage(1)
  }

  function openCreateForm() {
    setSelectedPollingStation(null)
    setIsFormOpen(true)
  }

  function openEditForm(pollingStation) {
    setSelectedPollingStation(pollingStation)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedPollingStation(null)
  }

  async function handleSave(payload) {
    if (selectedPollingStation) {
      await updatePollingStation(
        selectedPollingStation.id,
        payload,
      )
    } else {
      await createPollingStation(payload)
    }

    closeForm()

    if (page !== 1 || pollingCenterFilter !== '') {
      setPollingCenterFilter('')
      setPage(1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  function openDeleteDialog(pollingStation) {
    setPollingStationToDelete(pollingStation)
  }

  function closeDeleteDialog() {
    setPollingStationToDelete(null)
  }

  async function handleDelete() {
    await deletePollingStation(pollingStationToDelete.id)

    closeDeleteDialog()

    if (pollingStations.length === 1 && page > 1) {
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
          <h2>Polling stations</h2>
          <p className="page-description">
            Manage individual stations inside polling centers.
          </p>
        </div>

        {canCreate && pollingCenters.length > 0 && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add polling station
          </button>
        )}
      </div>

      <div className="filter-row">
        <label className="filter-control">
          <span>Filter by polling center</span>
          <select
            value={pollingCenterFilter}
            onChange={changePollingCenterFilter}
          >
            <option value="">All polling centers</option>

            {pollingCenters.map((pollingCenter) => (
              <option
                key={pollingCenter.id}
                value={pollingCenter.id}
              >
                {pollingCenterLabel(pollingCenter)}
              </option>
            ))}
          </select>
        </label>
      </div>

      {pollingCentersError && (
        <div className="error-message" role="alert">
          {pollingCentersError}
        </div>
      )}

      <article className="content-card geography-card">
        {isLoading && (
          <p className="state-message">
            Loading polling stations...
          </p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading &&
          !error &&
          pollingStations.length === 0 && (
            <div className="empty-state">
              <h3>No polling stations found</h3>
              <p>
                Add a polling station or select another polling
                center filter.
              </p>
            </div>
          )}

        {!isLoading &&
          !error &&
          pollingStations.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table">
                  <thead>
                    <tr>
                      <th>Station</th>
                      <th>English label</th>
                      <th>Arabic label</th>
                      <th>Polling center</th>
                      <th>Room</th>
                      <th>Registered voters</th>
                      {(canUpdate || canDelete) && (
                        <th>Actions</th>
                      )}
                    </tr>
                  </thead>

                  <tbody>
                    {pollingStations.map((pollingStation) => (
                      <tr key={pollingStation.id}>
                        <td>
                          <span className="code-pill">
                            {pollingStation.station_number}
                          </span>
                        </td>
                        <td>{pollingStation.name_en ?? '—'}</td>
                        <td lang="ar" dir="rtl">
                          {pollingStation.name_ar ?? '—'}
                        </td>
                        <td>
                          <span>
                            {pollingStation.polling_center
                              ?.name_en ?? '—'}
                          </span>
                          <small className="table-detail">
                            {pollingStation.polling_center?.area
                              ?.name_en ?? ''}
                          </small>
                        </td>
                        <td>{pollingStation.room ?? '—'}</td>
                        <td>
                          {pollingStation.registered_voters ?? '—'}
                        </td>

                        {(canUpdate || canDelete) && (
                          <td>
                            <div className="table-actions">
                              {canUpdate && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(pollingStation)
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
                                      pollingStation,
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
        <PollingStationForm
          pollingStation={selectedPollingStation}
          pollingCenters={pollingCenters}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {pollingStationToDelete && (
        <ConfirmDialog
          title="Delete polling station?"
          message={`Delete station ${pollingStationToDelete.station_number}? This action cannot be undone.`}
          confirmLabel="Delete polling station"
          onConfirm={handleDelete}
          onCancel={closeDeleteDialog}
        />
      )}
    </section>
  )
}

export default PollingStationsPage