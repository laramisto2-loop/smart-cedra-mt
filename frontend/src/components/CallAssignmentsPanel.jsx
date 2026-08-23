import { useEffect, useState } from 'react'
import CallAssignmentDetails from './CallAssignmentDetails.jsx'
import CallAttemptForm from './CallAttemptForm.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import {
  claimCallAssignment,
  createCallAttempt,
  listCallAssignments,
  updateCallAssignment,
} from '../services/callCenter.js'

function formatLabel(value) {
  return value
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function formatDate(value) {
  return value
    ? new Date(value).toLocaleString()
    : 'Not scheduled'
}

function errorMessage(
  error,
  fallback = 'The requested action could not be completed.',
) {
  const validationErrors = error.response?.data?.errors

  if (validationErrors) {
    return Object.values(validationErrors)
      .flat()
      .join(' ')
  }

  return error.response?.data?.message ?? fallback
}

function CallAssignmentsPanel({ user }) {
  const permissions = user.permissions ?? []

  const canClaim = permissions.includes(
    'calls.assignments.claim',
  )
  const canUpdate = permissions.includes(
    'calls.assignments.update',
  )
  const canCreateAttempt = permissions.includes(
    'calls.attempts.create',
  )

  const mayManageAssignments =
    permissions.includes('calls.queues.assign')

  const [assignments, setAssignments] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [priority, setPriority] = useState('')
  const [ownership, setOwnership] = useState('all')
  const [scheduledFrom, setScheduledFrom] = useState('')
  const [scheduledTo, setScheduledTo] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [refreshToken, setRefreshToken] = useState(0)
  const [claimingId, setClaimingId] = useState(null)

  const [detailsId, setDetailsId] = useState(null)
  const [attemptAssignment, setAttemptAssignment] =
    useState(null)
  const [statusChange, setStatusChange] =
    useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadAssignments() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listCallAssignments({
          page,
          search,
          status,
          priority,
          mine: ownership === 'mine',
          unassigned: ownership === 'unassigned',
          scheduledFrom: scheduledFrom
            ? `${scheduledFrom}T00:00:00`
            : '',
          scheduledTo: scheduledTo
            ? `${scheduledTo}T23:59:59`
            : '',
        })

        if (!isCurrent) {
          return
        }

        setAssignments(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setAssignments([])
          setMeta(null)
          setLoadError(
            'Call assignments could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadAssignments()

    return () => {
      isCurrent = false
    }
  }, [
    page,
    search,
    status,
    priority,
    ownership,
    scheduledFrom,
    scheduledTo,
    refreshToken,
  ])

  function refreshAssignments() {
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
    setStatus('')
    setPriority('')
    setOwnership('all')
    setScheduledFrom('')
    setScheduledTo('')
    setPage(1)
  }

  async function claimAssignment(assignment) {
    setActionError('')
    setSuccessMessage('')
    setClaimingId(assignment.id)

    try {
      await claimCallAssignment(assignment.id)
      setSuccessMessage(
        `The assignment for ${
          assignment.contact?.full_name ?? 'the contact'
        } was claimed successfully.`,
      )
      refreshAssignments()
    } catch (error) {
      setActionError(
        errorMessage(
          error,
          'The assignment could not be claimed. It may already belong to another agent.',
        ),
      )
    } finally {
      setClaimingId(null)
    }
  }

  async function saveAttempt(payload) {
    await createCallAttempt(payload)

    setAttemptAssignment(null)
    setSuccessMessage(
      'Call attempt recorded successfully.',
    )
    refreshAssignments()
  }

  async function confirmStatusChange() {
    const { assignment, nextStatus } = statusChange

    await updateCallAssignment(assignment.id, {
      status: nextStatus,
    })

    setStatusChange(null)
    setSuccessMessage(
      `Assignment marked as ${formatLabel(
        nextStatus,
      ).toLowerCase()}.`,
    )

    if (assignments.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshAssignments()
    }
  }

  function openStatusChange(assignment, nextStatus) {
    setActionError('')
    setSuccessMessage('')
    setStatusChange({
      assignment,
      nextStatus,
    })
  }

  return (
    <section className="messaging-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Agent workload</p>
          <h3>Call assignments</h3>
          <p className="page-description">
            Claim assigned contacts, record call outcomes,
            and track follow-up work.
          </p>
        </div>
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
            <span>Search assignments</span>
            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Contact, phone, queue, or reference"
            />
          </label>

          <button
            type="submit"
            className="primary-button"
          >
            Search
          </button>
        </form>

        <div className="contacts-filters">
          <label className="form-field">
            <span>Status</span>
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All statuses</option>
              <option value="pending">Pending</option>
              <option value="in_progress">
                In progress
              </option>
              <option value="completed">Completed</option>
              <option value="skipped">Skipped</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </label>

          <label className="form-field">
            <span>Priority</span>
            <select
              value={priority}
              onChange={(event) => {
                setPriority(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All priorities</option>
              <option value="urgent">Urgent</option>
              <option value="high">High</option>
              <option value="normal">Normal</option>
              <option value="low">Low</option>
            </select>
          </label>

          {mayManageAssignments && (
            <label className="form-field">
              <span>Assignment ownership</span>
              <select
                value={ownership}
                onChange={(event) => {
                  setOwnership(event.target.value)
                  setPage(1)
                }}
              >
                <option value="all">
                  All accessible assignments
                </option>
                <option value="mine">
                  Assigned to me
                </option>
                <option value="unassigned">
                  Unassigned
                </option>
              </select>
            </label>
          )}

          <label className="form-field">
            <span>Scheduled from</span>
            <input
              type="date"
              value={scheduledFrom}
              onChange={(event) => {
                setScheduledFrom(event.target.value)
                setPage(1)
              }}
            />
          </label>

          <label className="form-field">
            <span>Scheduled to</span>
            <input
              type="date"
              min={scheduledFrom}
              value={scheduledTo}
              onChange={(event) => {
                setScheduledTo(event.target.value)
                setPage(1)
              }}
            />
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
            Loading call assignments...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading
          && !loadError
          && assignments.length === 0 && (
            <div className="empty-state">
              <h3>No call assignments found</h3>
              <p>
                Assign contacts to an active queue or change
                the current filters.
              </p>
            </div>
          )}

        {!isLoading
          && !loadError
          && assignments.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Contact</th>
                      <th>Queue</th>
                      <th>Priority</th>
                      <th>Status</th>
                      <th>Agent</th>
                      <th>Schedule</th>
                      <th>Attempts</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {assignments.map((assignment) => {
                      const isOpen = [
                        'pending',
                        'in_progress',
                      ].includes(assignment.status)

                      const isMine =
                        assignment.assigned_to_user_id
                        === user.id

                      const canRecord =
                        canCreateAttempt
                        && isOpen
                        && (
                          mayManageAssignments
                          || isMine
                        )

                      const canClaimThis =
                        canClaim
                        && assignment.status === 'pending'
                        && !assignment.assigned_to_user_id

                      const canFinish =
                        canUpdate && isOpen

                      return (
                        <tr key={assignment.id}>
                          <td>
                            <strong>
                              {assignment.contact?.full_name
                                ?? 'Unknown contact'}
                            </strong>
                            <span className="table-secondary">
                              {assignment.contact
                                ?.reference_code ?? ''}
                            </span>
                            <span className="table-secondary">
                              {assignment.contact?.phone
                                ?? 'No phone number'}
                            </span>
                          </td>

                          <td>
                            <strong>
                              {assignment.call_queue?.name
                                ?? 'Unknown queue'}
                            </strong>
                            <span className="table-secondary">
                              {assignment.call_queue?.code
                                ?? ''}
                            </span>
                          </td>

                          <td>
                            <span
                              className={`message-status-pill ${assignment.priority}`}
                            >
                              {formatLabel(
                                assignment.priority,
                              )}
                            </span>
                          </td>

                          <td>
                            <span
                              className={`message-status-pill ${assignment.status}`}
                            >
                              {formatLabel(
                                assignment.status,
                              )}
                            </span>
                          </td>

                          <td>
                            <strong>
                              {assignment.assignee?.name
                                ?? 'Unassigned'}
                            </strong>
                          </td>

                          <td>
                            {formatDate(
                              assignment.scheduled_for,
                            )}
                          </td>

                          <td>
                            {assignment.attempts_count ?? 0}
                          </td>

                          <td>
                            <div className="table-actions">
                              <button
                                type="button"
                                className="text-button"
                                onClick={() =>
                                  setDetailsId(
                                    assignment.id,
                                  )
                                }
                              >
                                Details
                              </button>

                              {canClaimThis && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    claimAssignment(
                                      assignment,
                                    )
                                  }
                                  disabled={
                                    claimingId
                                    === assignment.id
                                  }
                                >
                                  {claimingId
                                    === assignment.id
                                    ? 'Claiming...'
                                    : 'Claim'}
                                </button>
                              )}

                              {canRecord && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    setAttemptAssignment(
                                      assignment,
                                    )
                                  }
                                >
                                  Record attempt
                                </button>
                              )}

                              {canFinish && (
                                <>
                                  <button
                                    type="button"
                                    className="text-button"
                                    onClick={() =>
                                      openStatusChange(
                                        assignment,
                                        'completed',
                                      )
                                    }
                                  >
                                    Complete
                                  </button>

                                  <button
                                    type="button"
                                    className="text-button"
                                    onClick={() =>
                                      openStatusChange(
                                        assignment,
                                        'skipped',
                                      )
                                    }
                                  >
                                    Skip
                                  </button>
                                </>
                              )}
                            </div>
                          </td>
                        </tr>
                      )
                    })}
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

      {detailsId && (
        <CallAssignmentDetails
          assignmentId={detailsId}
          onClose={() => setDetailsId(null)}
        />
      )}

      {attemptAssignment && (
        <CallAttemptForm
          key={attemptAssignment.id}
          assignment={attemptAssignment}
          onSubmit={saveAttempt}
          onCancel={() =>
            setAttemptAssignment(null)
          }
        />
      )}

      {statusChange && (
        <ConfirmDialog
          title={`${formatLabel(
            statusChange.nextStatus,
          )} assignment?`}
          message={`Mark the call assignment for ${
            statusChange.assignment.contact?.full_name
              ?? 'this contact'
          } as ${formatLabel(
            statusChange.nextStatus,
          ).toLowerCase()}?`}
          confirmLabel={formatLabel(
            statusChange.nextStatus,
          )}
          confirmingLabel="Saving..."
          errorMessage="The assignment status could not be updated."
          forbiddenMessage="You do not have permission to update this assignment."
          onConfirm={confirmStatusChange}
          onCancel={() => setStatusChange(null)}
        />
      )}
    </section>
  )
}

export default CallAssignmentsPanel