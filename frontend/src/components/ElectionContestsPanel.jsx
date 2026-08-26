import { useCallback, useEffect, useState } from 'react'
import {
  activateElectionContest,
  closeElectionContest,
  deleteElectionContest,
  listElectionContests,
} from '../services/resultsIngestion.js'
import ElectionContestForm from './ElectionContestForm.jsx'

const statusOptions = ['draft', 'active', 'closed']

function formatLabel(value) {
  return value
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function formatDate(value) {
  if (!value) {
    return 'Not recorded'
  }

  return new Intl.DateTimeFormat('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(new Date(`${value}T00:00:00`))
}

function errorMessage(error, fallback) {
  return error.response?.data?.message ?? fallback
}

export default function ElectionContestsPanel({ permissions = [] }) {
  const [contests, setContests] = useState([])
  const [meta, setMeta] = useState(null)
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    electionDateFrom: '',
    electionDateTo: '',
    page: 1,
  })
  const [isLoading, setIsLoading] = useState(true)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [formContest, setFormContest] = useState(null)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [workingId, setWorkingId] = useState(null)

  const canCreate = permissions.includes('results.contests.create')
  const canUpdate = permissions.includes('results.contests.update')
  const canActivate = permissions.includes('results.contests.activate')
  const canDelete = permissions.includes('results.contests.delete')

  const loadContests = useCallback(async () => {
  try {
    const response = await listElectionContests(filters)

    setError('')
    setContests(response.data ?? [])
    setMeta(response.meta ?? null)
    } catch (requestError) {
      setError(errorMessage(requestError, 'Election contests could not be loaded.'))
    } finally {
      setIsLoading(false)
    }
  }, [filters])

  useEffect(() => {
    const loadTimer = window.setTimeout(() => {
        void loadContests()
    }, 0)

    return () => {
        window.clearTimeout(loadTimer)
    }
    }, [loadContests])

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
      status: '',
      electionDateFrom: '',
      electionDateTo: '',
      page: 1,
    })
  }

  function openCreateForm() {
    setFormContest(null)
    setIsFormOpen(true)
  }

  function openEditForm(contest) {
    setFormContest(contest)
    setIsFormOpen(true)
  }

  async function handleStatusChange(contest, action) {
    setWorkingId(contest.id)
    setError('')
    setNotice('')

    try {
      if (action === 'activate') {
        await activateElectionContest(contest.id)
        setNotice(`The ${contest.name} contest is now active.`)
      }

      if (action === 'close') {
        await closeElectionContest(contest.id)
        setNotice(`The ${contest.name} contest is now closed.`)
      }

      await loadContests()
    } catch (requestError) {
      setError(errorMessage(requestError, 'The contest status could not be updated.'))
    } finally {
      setWorkingId(null)
    }
  }

  async function handleDelete(contest) {
    const shouldDelete = window.confirm(
      `Delete "${contest.name}"? This cannot be undone.`,
    )

    if (!shouldDelete) {
      return
    }

    setWorkingId(contest.id)
    setError('')
    setNotice('')

    try {
      await deleteElectionContest(contest.id)
      setNotice(`The ${contest.name} contest was deleted.`)
      await loadContests()
    } catch (requestError) {
      setError(errorMessage(requestError, 'The contest could not be deleted.'))
    } finally {
      setWorkingId(null)
    }
  }

  function handleSaved(contest, action) {
    setIsFormOpen(false)
    setFormContest(null)
    setNotice(
      action === 'created'
        ? `${contest.name} was created as a draft contest.`
        : `${contest.name} was updated successfully.`,
    )
    loadContests()
  }

  return (
    <section className="results-panel">
      <div className="page-heading">
        <div>
          <p className="eyebrow">ELECTION CONFIGURATION</p>
          <h2>Election contests</h2>
          <p>
            Create contests, define ballot options, and activate the contests
            that are ready to receive verified results.
          </p>
        </div>

        {canCreate && (
          <button className="primary-button" onClick={openCreateForm} type="button">
            Create contest
          </button>
        )}
      </div>

      {notice && <div className="success-banner">{notice}</div>}
      {error && <div className="form-error-banner">{error}</div>}

      <section className="filters-card">
        <label className="form-field form-field-wide">
          <span>Search contests</span>
          <div className="search-row">
            <input
              onChange={(event) => updateFilter('search', event.target.value)}
              placeholder="Contest name, code, or description"
              value={filters.search}
            />
            <button className="primary-button" onClick={loadContests} type="button">
              Search
            </button>
          </div>
        </label>

        <div className="filter-grid">
          <label className="form-field">
            <span>Status</span>
            <select
              onChange={(event) => updateFilter('status', event.target.value)}
              value={filters.status}
            >
              <option value="">All statuses</option>
              {statusOptions.map((status) => (
                <option key={status} value={status}>
                  {formatLabel(status)}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Election date from</span>
            <input
              onChange={(event) =>
                updateFilter('electionDateFrom', event.target.value)
              }
              type="date"
              value={filters.electionDateFrom}
            />
          </label>

          <label className="form-field">
            <span>Election date to</span>
            <input
              onChange={(event) =>
                updateFilter('electionDateTo', event.target.value)
              }
              type="date"
              value={filters.electionDateTo}
            />
          </label>

          <button className="secondary-button results-clear-filters" onClick={clearFilters} type="button">
            Clear filters
          </button>
        </div>
      </section>

      <section className="table-card">
        {isLoading ? (
          <div className="empty-state">
            <h3>Loading election contests...</h3>
          </div>
        ) : contests.length === 0 ? (
          <div className="empty-state">
            <h3>No election contests found</h3>
            <p>Create the first contest or change the current filters.</p>
          </div>
        ) : (
          <div className="table-scroll">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Contest</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Ballot options</th>
                  <th>Tally sheets</th>
                  <th>Activated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {contests.map((contest) => (
                  <tr key={contest.id}>
                    <td>
                      <strong>{contest.name}</strong>
                      <span className="table-subtext">{contest.code}</span>
                      {contest.description && (
                        <span className="table-subtext">{contest.description}</span>
                      )}
                    </td>
                    <td>{formatDate(contest.election_date)}</td>
                    <td>
                      <span className={`status-pill status-${contest.status}`}>
                        {formatLabel(contest.status)}
                      </span>
                    </td>
                    <td>{contest.options_count}</td>
                    <td>{contest.tally_sheets_count}</td>
                    <td>
                      {contest.activated_at
                        ? new Intl.DateTimeFormat('en-GB', {
                            dateStyle: 'medium',
                            timeStyle: 'short',
                          }).format(new Date(contest.activated_at))
                        : 'Not activated'}
                    </td>
                    <td>
                      <div className="table-actions">
                        {canUpdate && (
                          <button
                            className="table-link"
                            onClick={() => openEditForm(contest)}
                            type="button"
                          >
                            Edit
                          </button>
                        )}

                        {canActivate && contest.status === 'draft' && (
                          <button
                            className="table-link"
                            disabled={workingId === contest.id}
                            onClick={() => handleStatusChange(contest, 'activate')}
                            type="button"
                          >
                            Activate
                          </button>
                        )}

                        {canActivate && contest.status === 'active' && (
                          <button
                            className="table-link warning-link"
                            disabled={workingId === contest.id}
                            onClick={() => handleStatusChange(contest, 'close')}
                            type="button"
                          >
                            Close
                          </button>
                        )}

                        {canDelete && contest.status === 'draft' && (
                          <button
                            className="table-link danger-link"
                            disabled={workingId === contest.id}
                            onClick={() => handleDelete(contest)}
                            type="button"
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
        <ElectionContestForm
            contest={formContest}
            key={formContest?.id ?? 'new-election-contest'}
            onCancel={() => {
            setIsFormOpen(false)
            setFormContest(null)
            }}
            onSaved={handleSaved}
        />
      )}
    </section>
  )
}