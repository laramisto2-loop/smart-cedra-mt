import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from 'react'
import OfflineTurnoutStatus from './OfflineTurnoutStatus.jsx'
import TurnoutSeries from './TurnoutSeries.jsx'
import TurnoutSnapshotForm from './TurnoutSnapshotForm.jsx'
import { listPollingCenters } from '../services/pollingCenters.js'
import { listPollingStations } from '../services/pollingStations.js'
import {
  getTurnoutSeries,
  listTurnoutSnapshots,
} from '../services/turnoutSnapshots.js'
import {
  submitTurnoutWithOfflineFallback,
} from '../services/turnoutSync.js'

async function loadEveryPage(loadPage) {
  const firstResponse = await loadPage(1)
  const records = [...(firstResponse.data ?? [])]
  const lastPage = Math.min(
    firstResponse.meta?.last_page ?? 1,
    20,
  )

  for (let page = 2; page <= lastPage; page += 1) {
    const response = await loadPage(page)
    records.push(...(response.data ?? []))
  }

  return records
}

function formatDateTime(value) {
  if (!value) {
    return 'Not recorded'
  }

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatPercentage(value) {
  return value === null || value === undefined
    ? '—'
    : `${Number(value).toFixed(2)}%`
}

function sourceLabel(source) {
  return source === 'admin'
    ? 'Administration'
    : 'Field'
}

function locationLabel(snapshot) {
  const center =
    snapshot.polling_center?.name_en
    ?? 'Unknown polling center'

  if (snapshot.polling_station) {
    return `${center} — Station ${
      snapshot.polling_station.station_number
    }`
  }

  return `${center} — Entire center`
}

function TurnoutPage({ user }) {
  const permissions = user.permissions ?? []
  const roles = user.roles ?? []
  const canCreate = permissions.includes('turnout.create')
  const canViewTenantSnapshots = roles.some(
    (role) =>
      role.slug === 'tenant_admin'
      || role.slug === 'coordinator',
  )

  const [snapshots, setSnapshots] = useState([])
  const [pollingCenters, setPollingCenters] = useState([])
  const [pollingStations, setPollingStations] = useState([])

  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [source, setSource] = useState('')
  const [pollingCenterId, setPollingCenterId] =
    useState('')
  const [pollingStationId, setPollingStationId] =
    useState('')
  const [capturedFrom, setCapturedFrom] = useState('')
  const [capturedTo, setCapturedTo] = useState('')
  const [scope, setScope] = useState(
    canViewTenantSnapshots ? '' : 'mine',
  )

  const [series, setSeries] = useState(null)
  const [isSeriesLoading, setIsSeriesLoading] =
    useState(false)
  const [seriesError, setSeriesError] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [lookupWarning, setLookupWarning] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [isFormOpen, setIsFormOpen] = useState(false)

  const availableStations = useMemo(
    () =>
      pollingStations.filter(
        (station) =>
          pollingCenterId !== ''
          && station.polling_center_id.toString()
            === pollingCenterId,
      ),
    [pollingCenterId, pollingStations],
  )

  const selectedCenter = useMemo(
    () =>
      pollingCenters.find(
        (center) =>
          center.id.toString() === pollingCenterId,
      ) ?? null,
    [pollingCenterId, pollingCenters],
  )

  const selectedStation = useMemo(
    () =>
      pollingStations.find(
        (station) =>
          station.id.toString() === pollingStationId,
      ) ?? null,
    [pollingStationId, pollingStations],
  )

  const refreshTurnout = useCallback(() => {
    setReloadKey((current) => current + 1)
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadLookups() {
      try {
        const [centers, stations] = await Promise.all([
          loadEveryPage((lookupPage) =>
            listPollingCenters({ page: lookupPage }),
          ),
          loadEveryPage((lookupPage) =>
            listPollingStations({ page: lookupPage }),
          ),
        ])

        if (!cancelled) {
          setPollingCenters(centers)
          setPollingStations(stations)
          setLookupWarning('')
        }
      } catch {
        if (!cancelled) {
          setLookupWarning(
            'Polling locations could not be loaded. Reconnect and refresh before recording turnout.',
          )
        }
      }
    }

    loadLookups()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadSnapshots() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listTurnoutSnapshots({
          page,
          search,
          source,
          pollingCenterId,
          pollingStationId,
          capturedFrom:
            capturedFrom === ''
              ? ''
              : `${capturedFrom}T00:00:00`,
          capturedTo:
            capturedTo === ''
              ? ''
              : `${capturedTo}T23:59:59`,
          mine: scope === 'mine',
        })

        if (!cancelled) {
          setSnapshots(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(
            requestError.response?.status === 403
              ? 'You do not have permission to view turnout.'
              : 'Turnout snapshots could not be loaded. Please try again.',
          )
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadSnapshots()

    return () => {
      cancelled = true
    }
  }, [
    capturedFrom,
    capturedTo,
    page,
    pollingCenterId,
    pollingStationId,
    reloadKey,
    scope,
    search,
    source,
  ])

  useEffect(() => {
    let cancelled = false

    async function loadSeries() {
      if (pollingCenterId === '') {
        setSeries(null)
        setSeriesError('')
        setIsSeriesLoading(false)
        return
      }

      setIsSeriesLoading(true)
      setSeriesError('')

      try {
        const response = await getTurnoutSeries({
          pollingCenterId,
          pollingStationId,
          capturedFrom:
            capturedFrom === ''
              ? ''
              : `${capturedFrom}T00:00:00`,
          capturedTo:
            capturedTo === ''
              ? ''
              : `${capturedTo}T23:59:59`,
        })

        if (!cancelled) {
          setSeries(response)
        }
      } catch {
        if (!cancelled) {
          setSeries(null)
          setSeriesError(
            navigator.onLine
              ? 'The turnout series could not be loaded.'
              : 'Reconnect to load the turnout series.',
          )
        }
      } finally {
        if (!cancelled) {
          setIsSeriesLoading(false)
        }
      }
    }

    loadSeries()

    return () => {
      cancelled = true
    }
  }, [
    capturedFrom,
    capturedTo,
    pollingCenterId,
    pollingStationId,
    reloadKey,
  ])

  function applySearch(event) {
    event.preventDefault()
    setSearch(searchDraft.trim())
    setPage(1)
  }

  function changeCenter(event) {
    setPollingCenterId(event.target.value)
    setPollingStationId('')
    setPage(1)
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setSource('')
    setPollingCenterId('')
    setPollingStationId('')
    setCapturedFrom('')
    setCapturedTo('')
    setScope(canViewTenantSnapshots ? '' : 'mine')
    setPage(1)
  }

  async function saveSnapshot(payload) {
    const result =
      await submitTurnoutWithOfflineFallback(
        payload,
        user,
      )

    setIsFormOpen(false)

    if (result.state === 'queued') {
      setNotice(
        'Turnout entry saved offline. It will synchronize automatically when the connection returns.',
      )
      return
    }

    setNotice('Turnout snapshot recorded successfully.')
    setPage(1)
    refreshTurnout()
  }

  const handleSynchronized = useCallback(() => {
    setNotice(
      'Queued turnout entries synchronized successfully.',
    )
    setPage(1)
    refreshTurnout()
  }, [refreshTurnout])

  return (
    <section className="turnout-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">Election operations</p>
          <h2>Aggregate turnout</h2>
          <p className="page-description">
            Track cumulative polling-center and polling-station
            totals for {user.tenant.name} without recording
            individual voter activity.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={() => setIsFormOpen(true)}
            disabled={pollingCenters.length === 0}
          >
            Record turnout
          </button>
        )}
      </div>

      <OfflineTurnoutStatus
        user={user}
        onSynchronized={handleSynchronized}
      />

      {notice && (
        <div
          className="form-message success-message"
          role="status"
        >
          {notice}
        </div>
      )}

      {lookupWarning && (
        <div
          className="form-message error-message"
          role="alert"
        >
          {lookupWarning}
        </div>
      )}

      <article className="content-card contacts-filter-card">
        <form
          className="contacts-search"
          onSubmit={applySearch}
        >
          <label className="form-field">
            <span>Search turnout records</span>
            <input
              type="search"
              value={searchDraft}
              onChange={(event) =>
                setSearchDraft(event.target.value)
              }
              maxLength="100"
              placeholder="Reference or operational notes"
            />
          </label>

          <button
            type="submit"
            className="primary-button"
          >
            Search
          </button>
        </form>

        <div className="contacts-filters turnout-filters">
          <label className="form-field">
            <span>Polling center</span>
            <select
              value={pollingCenterId}
              onChange={changeCenter}
            >
              <option value="">All polling centers</option>

              {pollingCenters.map((center) => (
                <option key={center.id} value={center.id}>
                  {center.name_en} — {center.code}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Polling station</span>
            <select
              value={pollingStationId}
              onChange={(event) => {
                setPollingStationId(event.target.value)
                setPage(1)
              }}
              disabled={pollingCenterId === ''}
            >
              <option value="">Entire center / all stations</option>

              {availableStations.map((station) => (
                <option key={station.id} value={station.id}>
                  Station {station.station_number}
                  {station.name_en
                    ? ` — ${station.name_en}`
                    : ''}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Source</span>
            <select
              value={source}
              onChange={(event) => {
                setSource(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All sources</option>
              <option value="field">Field</option>
              <option value="admin">Administration</option>
            </select>
          </label>

          <label className="form-field">
            <span>Captured from</span>
            <input
              type="date"
              value={capturedFrom}
              onChange={(event) => {
                setCapturedFrom(event.target.value)
                setPage(1)
              }}
            />
          </label>

          <label className="form-field">
            <span>Captured to</span>
            <input
              type="date"
              value={capturedTo}
              min={capturedFrom || undefined}
              onChange={(event) => {
                setCapturedTo(event.target.value)
                setPage(1)
              }}
            />
          </label>

          {canViewTenantSnapshots && (
            <label className="form-field">
              <span>Record ownership</span>
              <select
                value={scope}
                onChange={(event) => {
                  setScope(event.target.value)
                  setPage(1)
                }}
              >
                <option value="">
                  All accessible snapshots
                </option>
                <option value="mine">
                  Reported by me
                </option>
              </select>
            </label>
          )}

          <button
            type="button"
            className="secondary-button"
            onClick={clearFilters}
          >
            Clear filters
          </button>
        </div>
      </article>

      <TurnoutSeries
        series={series}
        selectedCenter={selectedCenter}
        selectedStation={selectedStation}
        isLoading={isSeriesLoading}
        error={seriesError}
      />

      <article className="content-card contacts-card">
        {isLoading && (
          <p className="state-message">
            Loading turnout snapshots...
          </p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading
          && !error
          && snapshots.length === 0 && (
            <div className="empty-state">
              <h3>No turnout snapshots found</h3>
              <p>
                Record the first aggregate total or change the
                current filters.
              </p>
            </div>
          )}

        {!isLoading
          && !error
          && snapshots.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table turnout-table">
                  <thead>
                    <tr>
                      <th>Reference</th>
                      <th>Location</th>
                      <th>Turnout</th>
                      <th>Registered</th>
                      <th>Percentage</th>
                      <th>Captured</th>
                      <th>Reporter</th>
                      <th>Source</th>
                    </tr>
                  </thead>

                  <tbody>
                    {snapshots.map((snapshot) => (
                      <tr key={snapshot.id}>
                        <td>
                          <strong>
                            {snapshot.reference_code}
                          </strong>

                          {snapshot.notes && (
                            <span className="table-secondary">
                              {snapshot.notes}
                            </span>
                          )}
                        </td>

                        <td>{locationLabel(snapshot)}</td>

                        <td>
                          <strong className="turnout-count">
                            {snapshot.turnout_count}
                          </strong>
                        </td>

                        <td>
                          {snapshot.registered_voters ?? '—'}
                        </td>

                        <td>
                          {formatPercentage(
                            snapshot.turnout_percentage,
                          )}
                        </td>

                        <td>
                          {formatDateTime(
                            snapshot.captured_at,
                          )}
                        </td>

                        <td>
                          {snapshot.reporter?.name
                            ?? 'Former user'}
                        </td>

                        <td>
                          <span
                            className={`turnout-source ${snapshot.source}`}
                          >
                            {sourceLabel(snapshot.source)}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {pagination
                && pagination.last_page > 1 && (
                  <div className="pagination">
                    <button
                      type="button"
                      className="secondary-button"
                      disabled={
                        pagination.current_page === 1
                      }
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
                        pagination.current_page
                        === pagination.last_page
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
        <TurnoutSnapshotForm
          pollingCenters={pollingCenters}
          pollingStations={pollingStations}
          onSubmit={saveSnapshot}
          onCancel={() => setIsFormOpen(false)}
        />
      )}
    </section>
  )
}

export default TurnoutPage

//This page provides aggregate entry, offline status, tenant/role-aware history, filters, pagination, and the center/station time-series summary