import { useEffect, useState } from 'react'
import {
  downloadResultsExport,
  getResultsAnalytics,
  listElectionContests,
} from '../services/resultsIngestion.js'

const numberFormatter = new Intl.NumberFormat('en-US')

function formatNumber(value) {
  return numberFormatter.format(Number(value) || 0)
}

function formatPercentage(value) {
  return `${Number(value || 0).toFixed(2)}%`
}

function formatStatus(status) {
  return String(status ?? '')
    .split('_')
    .filter(Boolean)
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function getErrorMessage(error, fallback) {
  const validationErrors = error?.response?.data?.errors

  if (validationErrors) {
    const firstMessage = Object.values(validationErrors)
      .flat()
      .find(Boolean)

    if (firstMessage) {
      return firstMessage
    }
  }

  return error?.response?.data?.message ?? fallback
}

export default function ResultsAnalyticsPanel({
  permissions = [],
}) {
  const canExport = permissions.includes(
    'results.exports.create',
  )

  const [contests, setContests] = useState([])
  const [contestId, setContestId] = useState('')
  const [pollingCenterId, setPollingCenterId] =
    useState('')
  const [availableCenters, setAvailableCenters] =
    useState([])
  const [analytics, setAnalytics] = useState(null)
  const [error, setError] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [isExporting, setIsExporting] = useState(false)

  useEffect(() => {
    let cancelled = false

    listElectionContests({ perPage: 100 })
      .then((response) => {
        if (cancelled) {
          return null
        }

        const items = response?.data ?? []
        const defaultContest =
          items.find((contest) => contest.status === 'active')
          ?? items[0]
          ?? null

        setContests(items)

        if (!defaultContest) {
          return null
        }

        setContestId(String(defaultContest.id))

        return getResultsAnalytics({
          electionContestId: defaultContest.id,
        })
      })
      .then((data) => {
        if (cancelled || !data) {
          return
        }

        setAnalytics(data)
        setAvailableCenters(data.center_breakdown ?? [])
      })
      .catch((requestError) => {
        if (!cancelled) {
          setError(
            getErrorMessage(
              requestError,
              'The results dashboard could not be loaded.',
            ),
          )
        }
      })
      .finally(() => {
        if (!cancelled) {
          setIsLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [])

  async function loadAnalytics(
    selectedContestId = contestId,
    selectedCenterId = pollingCenterId,
  ) {
    if (!selectedContestId) {
      setError('Select an election contest first.')
      return
    }

    setIsLoading(true)
    setError('')

    try {
      const data = await getResultsAnalytics({
        electionContestId: selectedContestId,
        pollingCenterId: selectedCenterId || undefined,
      })

      setAnalytics(data)

      if (!selectedCenterId) {
        setAvailableCenters(data.center_breakdown ?? [])
      }
    } catch (requestError) {
      setError(
        getErrorMessage(
          requestError,
          'The results dashboard could not be loaded.',
        ),
      )
    } finally {
      setIsLoading(false)
    }
  }

  function handleContestChange(event) {
    setContestId(event.target.value)
    setPollingCenterId('')
    setAvailableCenters([])
    setAnalytics(null)
    setError('')
  }

  async function clearCenterFilter() {
    setPollingCenterId('')
    await loadAnalytics(contestId, '')
  }

  async function handleExport() {
    if (!contestId) {
      setError('Select an election contest first.')
      return
    }

    setIsExporting(true)
    setError('')

    try {
      await downloadResultsExport({
        electionContestId: contestId,
        pollingCenterId: pollingCenterId || undefined,
      })
    } catch (requestError) {
      setError(
        getErrorMessage(
          requestError,
          'The CSV export could not be downloaded.',
        ),
      )
    } finally {
      setIsExporting(false)
    }
  }

  const summary = analytics?.summary ?? {}
  const optionTotals = analytics?.option_totals ?? []
  const statusEntries = Object.entries(
    analytics?.sheet_statuses ?? {},
  )
  const centerBreakdown =
    analytics?.center_breakdown ?? []

  const highestVoteTotal = Math.max(
    1,
    ...optionTotals.map((option) => Number(option.votes) || 0),
  )

  return (
    <section className="results-analytics-panel">
      <div className="results-analytics-heading">
        <div>
          <p className="eyebrow">VERIFIED RESULTS</p>
          <h2>Results analytics</h2>
          <p>
            Monitor approved polling-station results, reporting
            coverage, turnout, and ballot-option totals.
          </p>
        </div>

        {canExport && (
          <button
            className="secondary-button"
            disabled={!contestId || isExporting}
            onClick={handleExport}
            type="button"
          >
            {isExporting ? 'Preparing CSV...' : 'Export CSV'}
          </button>
        )}
      </div>

      {error && (
        <div aria-live="polite" className="error-banner">
          {error}
        </div>
      )}

      <div className="results-analytics-filters">
        <label>
          <span>Election contest</span>
          <select
            onChange={handleContestChange}
            value={contestId}
          >
            <option value="">Select a contest</option>
            {contests.map((contest) => (
              <option key={contest.id} value={contest.id}>
                {contest.name} — {contest.code}
              </option>
            ))}
          </select>
        </label>

        <label>
          <span>Polling center</span>
          <select
            disabled={!analytics}
            onChange={(event) =>
              setPollingCenterId(event.target.value)
            }
            value={pollingCenterId}
          >
            <option value="">All polling centers</option>
            {availableCenters.map((center) => (
              <option
                key={center.polling_center_id}
                value={center.polling_center_id}
              >
                {center.name_en
                  ?? center.name_ar
                  ?? center.code}
              </option>
            ))}
          </select>
        </label>

        <div className="results-analytics-filter-actions">
          <button
            className="primary-button"
            disabled={!contestId || isLoading}
            onClick={() => loadAnalytics()}
            type="button"
          >
            {isLoading ? 'Loading...' : 'Load dashboard'}
          </button>

          <button
            className="secondary-button"
            disabled={!pollingCenterId || isLoading}
            onClick={clearCenterFilter}
            type="button"
          >
            Clear center
          </button>
        </div>
      </div>

      {isLoading && !analytics && (
        <div className="empty-state table-card">
          <h3>Loading verified results...</h3>
        </div>
      )}

      {!isLoading && contests.length === 0 && (
        <div className="empty-state table-card">
          <h3>No election contests found</h3>
          <p>
            Create a contest before opening the analytics
            dashboard.
          </p>
        </div>
      )}

      {!isLoading && contestId && !analytics && (
        <div className="empty-state table-card">
          <h3>Load this contest&apos;s dashboard</h3>
          <p>
            Select the desired filters and choose Load dashboard.
          </p>
        </div>
      )}

      {analytics && (
        <>
          <div className="results-analytics-context">
            <div>
              <span>Contest</span>
              <strong>{analytics.contest?.name}</strong>
              <small>{analytics.contest?.code}</small>
            </div>

            <div>
              <span>Election date</span>
              <strong>
                {analytics.contest?.election_date
                  ?? 'Not recorded'}
              </strong>
            </div>
          </div>

          <div className="results-kpi-grid">
            <article>
              <span>Reporting stations</span>
              <strong>
                {formatNumber(summary.reporting_stations)}
                {' / '}
                {formatNumber(summary.total_stations)}
              </strong>
              <small>
                {formatPercentage(
                  summary.reporting_percentage,
                )} reporting
              </small>
            </article>

            <article>
              <span>Registered voters</span>
              <strong>
                {formatNumber(summary.registered_voters)}
              </strong>
              <small>Across approved tally sheets</small>
            </article>

            <article>
              <span>Ballots cast</span>
              <strong>
                {formatNumber(summary.ballots_cast)}
              </strong>
              <small>
                {formatPercentage(summary.turnout_percentage)}
                {' turnout'}
              </small>
            </article>

            <article>
              <span>Valid ballots</span>
              <strong>
                {formatNumber(summary.valid_ballots)}
              </strong>
              <small>Included in option totals</small>
            </article>

            <article>
              <span>Invalid ballots</span>
              <strong>
                {formatNumber(summary.invalid_ballots)}
              </strong>
              <small>Recorded but not allocated</small>
            </article>

            <article>
              <span>Blank ballots</span>
              <strong>
                {formatNumber(summary.blank_ballots)}
              </strong>
              <small>Submitted without a selection</small>
            </article>
          </div>

          <div className="results-analytics-grid">
            <section className="table-card results-chart-card">
              <div className="results-card-heading">
                <div>
                  <h3>Ballot-option totals</h3>
                  <p>
                    Aggregated from approved tally submissions.
                  </p>
                </div>
              </div>

              {optionTotals.length > 0 ? (
                <div className="results-option-chart">
                  {optionTotals.map((option) => {
                    const barWidth =
                      (Number(option.votes) / highestVoteTotal)
                      * 100

                    return (
                      <article key={option.election_option_id}>
                        <div className="results-option-heading">
                          <div>
                            <strong>{option.name}</strong>
                            <small>{option.code}</small>
                          </div>

                          <div>
                            <strong>
                              {formatNumber(option.votes)}
                            </strong>
                            <small>
                              {formatPercentage(
                                option.vote_percentage,
                              )}
                            </small>
                          </div>
                        </div>

                        <div className="results-bar-track">
                          <div
                            className="results-bar-fill"
                            style={{
                              width: `${Math.max(
                                0,
                                Math.min(100, barWidth),
                              )}%`,
                            }}
                          />
                        </div>
                      </article>
                    )
                  })}
                </div>
              ) : (
                <p>No active ballot options were found.</p>
              )}
            </section>

            <section className="table-card results-status-card">
              <div className="results-card-heading">
                <div>
                  <h3>Tally-sheet workflow</h3>
                  <p>
                    Current sheet counts for the selected contest.
                  </p>
                </div>
              </div>

              <div className="results-status-list">
                {statusEntries.map(([status, count]) => (
                  <div key={status}>
                    <span>{formatStatus(status)}</span>
                    <strong>{formatNumber(count)}</strong>
                  </div>
                ))}
              </div>
            </section>
          </div>

          <section className="table-card results-center-card">
            <div className="results-card-heading">
              <div>
                <h3>Polling-center reporting</h3>
                <p>
                  Approved results grouped by polling center.
                </p>
              </div>
            </div>

            {centerBreakdown.length > 0 ? (
              <div className="results-table-scroll">
                <table>
                  <thead>
                    <tr>
                      <th>Polling center</th>
                      <th>Approved sheets</th>
                      <th>Registered</th>
                      <th>Ballots cast</th>
                      <th>Turnout</th>
                    </tr>
                  </thead>
                  <tbody>
                    {centerBreakdown.map((center) => (
                      <tr key={center.polling_center_id}>
                        <td>
                          <strong>
                            {center.name_en
                              ?? center.name_ar
                              ?? center.code}
                          </strong>
                          <small>{center.code}</small>
                        </td>
                        <td>
                          {formatNumber(
                            center.approved_sheets,
                          )}
                        </td>
                        <td>
                          {formatNumber(
                            center.registered_voters,
                          )}
                        </td>
                        <td>
                          {formatNumber(center.ballots_cast)}
                        </td>
                        <td>
                          {formatPercentage(
                            center.turnout_percentage,
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="empty-state">
                <h3>No approved polling-center results</h3>
                <p>
                  Approve tally sheets to include them in this
                  dashboard.
                </p>
              </div>
            )}
          </section>
        </>
      )}
    </section>
  )
}