import { useCallback, useEffect, useState } from 'react'
import { listPollingCenters } from '../services/pollingCenters.js'
import { listPollingStations } from '../services/pollingStations.js'
import TallySheetDetails from './TallySheetDetails.jsx'
import {
  listElectionContests,
  listTallySheets,
} from '../services/resultsIngestion.js'
import TallySheetForm from './TallySheetForm.jsx'

const statuses = [
  'pending',
  'awaiting_second_entry',
  'ready_for_review',
  'discrepancy',
  'approved',
  'rejected',
]

function formatLabel(value) {
  return value
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
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

function locationLabel(sheet) {
  const center = sheet.polling_center?.name_en
    ?? sheet.polling_center?.code
    ?? 'Unknown center'

  const station = sheet.polling_station
    ? `Station ${sheet.polling_station.station_number}`
    : 'Unknown station'

  return `${center} — ${station}`
}

function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback
}

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

export default function TallySheetsPanel({
  permissions = [], user,
}) {
  const [sheets, setSheets] = useState([])
  const [activeContests, setActiveContests] = useState([])
  const [pollingCenters, setPollingCenters] = useState([])
  const [pollingStations, setPollingStations] = useState([])
  const [meta, setMeta] = useState(null)
  const [selectedSheetId, setSelectedSheetId] = useState(null)
  const [filters, setFilters] = useState({
    search: '',
    electionContestId: '',
    status: '',
    page: 1,
  })
  const [isLoading, setIsLoading] = useState(true)
  const [isLookupLoading, setIsLookupLoading] = useState(true)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const canCreate = permissions.includes(
    'results.tallies.create',
  )

  const loadSheets = useCallback(async () => {
    setIsLoading(true)

    try {
      const response = await listTallySheets(filters)

      setSheets(response.data ?? [])
      setMeta(response.meta ?? null)
      setError('')
    } catch (requestError) {
      setError(
        errorMessage(
          requestError,
          'Tally sheets could not be loaded.',
        ),
      )
    } finally {
      setIsLoading(false)
    }
  }, [filters])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadSheets()
    }, 0)

    return () => {
      window.clearTimeout(timer)
    }
  }, [loadSheets])

  useEffect(() => {
    let cancelled = false

    async function loadLookups() {
      try {
        const [contests, centers, stations] =
          await Promise.all([
            loadEveryPage((page) =>
              listElectionContests({
                status: 'active',
                page,
                perPage: 100,
              }),
            ),
            canCreate
              ? loadEveryPage((page) =>
                  listPollingCenters({ page }),
                )
              : Promise.resolve([]),
            canCreate
              ? loadEveryPage((page) =>
                  listPollingStations({ page }),
                )
              : Promise.resolve([]),
          ])

        if (!cancelled) {
          setActiveContests(contests)
          setPollingCenters(centers)
          setPollingStations(stations)
        }
      } catch {
        if (!cancelled) {
          setError(
            'Some tally-sheet form options could not be loaded.',
          )
        }
      } finally {
        if (!cancelled) {
          setIsLookupLoading(false)
        }
      }
    }

    void loadLookups()

    return () => {
      cancelled = true
    }
  }, [canCreate])

  function updateFilter(field, value) {
    setFilters((current) => ({
      ...current,
      [field]: value,
      page: 1,
    }))
  }

  function clearFilters() {
    setFilters({
      search: '',
      electionContestId: '',
      status: '',
      page: 1,
    })
  }

  function handleSaved(sheet) {
    setIsFormOpen(false)
    setNotice(
      `Tally sheet ${sheet.reference_code} was created successfully.`,
    )
    void loadSheets()
  }

  return (
    <section className="results-panel">
      <div className="page-heading">
        <div>
          <p className="eyebrow">RESULTS INGESTION</p>
          <h2>Tally sheets</h2>
          <p>
            Capture polling-station totals, perform independent
            double entry, and approve reconciled results.
          </p>
        </div>

        {canCreate && (
          <button
            className="primary-button"
            disabled={
              isLookupLoading || activeContests.length === 0
            }
            onClick={() => setIsFormOpen(true)}
            type="button"
          >
            Create tally sheet
          </button>
        )}
      </div>

      {notice && (
        <div className="success-banner">{notice}</div>
      )}
      {error && (
        <div className="form-error-banner">{error}</div>
      )}

      {canCreate &&
        !isLookupLoading &&
        activeContests.length === 0 && (
          <div className="info-banner">
            Activate an election contest before creating tally
            sheets.
          </div>
        )}

      <section className="filters-card">
        <label className="form-field form-field-wide">
          <span>Search tally sheets</span>
          <div className="search-row">
            <input
              onChange={(event) =>
                updateFilter('search', event.target.value)
              }
              placeholder="Reference, contest, or polling center"
              value={filters.search}
            />
            <button
              className="primary-button"
              onClick={loadSheets}
              type="button"
            >
              Search
            </button>
          </div>
        </label>

        <div className="filter-grid">
          <label className="form-field">
            <span>Contest</span>
            <select
              onChange={(event) =>
                updateFilter(
                  'electionContestId',
                  event.target.value,
                )
              }
              value={filters.electionContestId}
            >
              <option value="">All contests</option>
              {activeContests.map((contest) => (
                <option key={contest.id} value={contest.id}>
                  {contest.name}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Status</span>
            <select
              onChange={(event) =>
                updateFilter('status', event.target.value)
              }
              value={filters.status}
            >
              <option value="">All statuses</option>
              {statuses.map((status) => (
                <option key={status} value={status}>
                  {formatLabel(status)}
                </option>
              ))}
            </select>
          </label>

          <button
            className="secondary-button results-clear-filters"
            onClick={clearFilters}
            type="button"
          >
            Clear filters
          </button>
        </div>
      </section>

      <section className="table-card">
        {isLoading ? (
          <div className="empty-state">
            <h3>Loading tally sheets...</h3>
          </div>
        ) : sheets.length === 0 ? (
          <div className="empty-state">
            <h3>No tally sheets found</h3>
            <p>
              Create a tally sheet for an active contest or change
              the current filters.
            </p>
          </div>
        ) : (
          <div className="table-scroll">
            <table className="data-table results-data-table">
              <thead>
                <tr>
                  <th>Tally sheet</th>
                  <th>Contest</th>
                  <th>Polling location</th>
                  <th>Status</th>
                  <th>Entries</th>
                  <th>Attachments</th>
                  <th>Created by</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {sheets.map((sheet) => (
                  <tr key={sheet.id}>
                    <td>
                      <strong>{sheet.reference_code}</strong>
                      {sheet.notes && (
                        <span className="table-subtext">
                          {sheet.notes}
                        </span>
                      )}
                    </td>
                    <td>
                      <strong>{sheet.contest?.name}</strong>
                      <span className="table-subtext">
                        {sheet.contest?.code}
                      </span>
                    </td>
                    <td>{locationLabel(sheet)}</td>
                    <td>
                      <span
                        className={`status-pill status-${sheet.status}`}
                      >
                        {formatLabel(sheet.status)}
                      </span>
                    </td>
                    <td>{sheet.submissions_count ?? 0}</td>
                    <td>{sheet.attachments_count ?? 0}</td>
                    <td>
                      {sheet.creator?.name ?? 'Unknown'}
                      <span className="table-subtext">
                        {sheet.creator?.email}
                      </span>
                    </td>
                    <td>{formatDateTime(sheet.updated_at)}</td>
                    <td>
                        <button
                            className="table-action"
                            onClick={() => setSelectedSheetId(sheet.id)}
                            type="button"
                        >
                            Details
                        </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {selectedSheetId && (
            <TallySheetDetails
                key={selectedSheetId}
                onChanged={() => {
                void loadSheets()
                }}
                onClose={() => setSelectedSheetId(null)}
                permissions={permissions}
                sheetId={selectedSheetId}
                user={user}
            />
            )}
      </section>

      {meta?.last_page > 1 && (
        <div className="pagination-row">
          <button
            className="secondary-button"
            disabled={meta.current_page <= 1}
            onClick={() =>
              setFilters((current) => ({
                ...current,
                page: current.page - 1,
              }))
            }
            type="button"
          >
            Previous
          </button>

          <span>
            Page {meta.current_page} of {meta.last_page}
          </span>

          <button
            className="secondary-button"
            disabled={meta.current_page >= meta.last_page}
            onClick={() =>
              setFilters((current) => ({
                ...current,
                page: current.page + 1,
              }))
            }
            type="button"
          >
            Next
          </button>
        </div>
      )}

      {isFormOpen && (
        <TallySheetForm
          contests={activeContests}
          key="new-tally-sheet"
          onCancel={() => setIsFormOpen(false)}
          onSaved={handleSaved}
          pollingCenters={pollingCenters}
          pollingStations={pollingStations}
        />
      )}
    </section>
  )
}