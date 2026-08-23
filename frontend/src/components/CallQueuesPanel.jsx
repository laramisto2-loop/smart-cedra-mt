import { useEffect, useState } from 'react'
import CallQueueAssignmentForm from './CallQueueAssignmentForm.jsx'
import CallQueueForm from './CallQueueForm.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import {
  assignCallQueue,
  createCallQueue,
  deleteCallQueue,
  listCallQueues,
  updateCallQueue,
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

function CallQueuesPanel({ user }) {
  const permissions = user.permissions ?? []

  const canCreate = permissions.includes(
    'calls.queues.create',
  )
  const canUpdate = permissions.includes(
    'calls.queues.update',
  )
  const canAssign = permissions.includes(
    'calls.queues.assign',
  )
  const canDelete = permissions.includes(
    'calls.queues.delete',
  )

  const [queues, setQueues] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [priority, setPriority] = useState('')
  const [status, setStatus] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [refreshToken, setRefreshToken] = useState(0)

  const [formState, setFormState] = useState(null)
  const [queueToAssign, setQueueToAssign] = useState(null)
  const [queueToDelete, setQueueToDelete] = useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadQueues() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listCallQueues({
          page,
          search,
          priority,
          status,
        })

        if (!isCurrent) {
          return
        }

        setQueues(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setQueues([])
          setMeta(null)
          setLoadError(
            'Call queues could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadQueues()

    return () => {
      isCurrent = false
    }
  }, [page, search, priority, status, refreshToken])

  function refreshQueues() {
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
    setPriority('')
    setStatus('')
    setPage(1)
  }

  function openCreateForm() {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'create',
      queue: null,
    })
  }

  function openEditForm(queue) {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'edit',
      queue,
    })
  }

  async function saveQueue(payload) {
    if (formState.mode === 'edit') {
      await updateCallQueue(
        formState.queue.id,
        payload,
      )
      setSuccessMessage(
        'Call queue updated successfully.',
      )
    } else {
      await createCallQueue(payload)
      setSuccessMessage(
        'Call queue created successfully.',
      )
    }

    setFormState(null)
    setPage(1)
    refreshQueues()
  }

  async function saveAssignments(payload) {
    const createdAssignments = await assignCallQueue(
      queueToAssign.id,
      payload,
    )

    setQueueToAssign(null)
    setSuccessMessage(
      `${createdAssignments.length} call assignments created successfully.`,
    )
    refreshQueues()
  }

  async function confirmDelete() {
    await deleteCallQueue(queueToDelete.id)

    setQueueToDelete(null)
    setSuccessMessage(
      'Call queue deleted successfully.',
    )

    if (queues.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshQueues()
    }
  }

  return (
    <section className="messaging-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Campaign workloads</p>
          <h3>Call queues</h3>
          <p className="page-description">
            Organize contacts into prioritized calling
            workloads and distribute assignments.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Create queue
          </button>
        )}
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
            <span>Search queues</span>
            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Queue name, code, or description"
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
            <span>Priority</span>
            <select
              value={priority}
              onChange={(event) => {
                setPriority(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All priorities</option>
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </label>

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
              <option value="draft">Draft</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="completed">Completed</option>
              <option value="archived">Archived</option>
            </select>
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
            Loading call queues...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading
          && !loadError
          && queues.length === 0 && (
            <div className="empty-state">
              <h3>No call queues found</h3>
              <p>
                Create the first queue or change the current
                filters.
              </p>
            </div>
          )}

        {!isLoading
          && !loadError
          && queues.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Queue</th>
                      <th>Script</th>
                      <th>Priority</th>
                      <th>Status</th>
                      <th>Assignments</th>
                      <th>Schedule</th>
                      <th>Creator</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {queues.map((queue) => {
                      const mayEdit =
                        canUpdate
                        && ![
                          'completed',
                          'archived',
                        ].includes(queue.status)

                      const mayAssign =
                        canAssign
                        && queue.status === 'active'

                      const mayDelete =
                        canDelete
                        && queue.status === 'draft'
                        && (queue.assignments_count ?? 0) === 0

                      return (
                        <tr key={queue.id}>
                          <td>
                            <strong>{queue.name}</strong>
                            <span className="table-secondary">
                              {queue.code}
                            </span>
                            {queue.description && (
                              <span className="table-secondary">
                                {queue.description}
                              </span>
                            )}
                          </td>

                          <td>
                            {queue.call_script?.name
                              ?? 'No script'}
                          </td>

                          <td>
                            <span
                              className={`message-status-pill ${queue.priority}`}
                            >
                              {formatLabel(queue.priority)}
                            </span>
                          </td>

                          <td>
                            <span
                              className={`message-status-pill ${queue.status}`}
                            >
                              {formatLabel(queue.status)}
                            </span>
                          </td>

                          <td>
                            {queue.assignments_count ?? 0}
                          </td>

                          <td>
                            <span>
                              {formatDate(queue.starts_at)}
                            </span>
                            {queue.ends_at && (
                              <span className="table-secondary">
                                Until {formatDate(queue.ends_at)}
                              </span>
                            )}
                          </td>

                          <td>
                            <strong>
                              {queue.creator?.name ?? 'Unknown'}
                            </strong>
                          </td>

                          <td>
                            <div className="table-actions">
                              {mayEdit && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(queue)
                                  }
                                >
                                  Edit
                                </button>
                              )}

                              {mayAssign && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    setQueueToAssign(queue)
                                  }
                                >
                                  Assign contacts
                                </button>
                              )}

                              {mayDelete && (
                                <button
                                  type="button"
                                  className="text-button danger"
                                  onClick={() =>
                                    setQueueToDelete(queue)
                                  }
                                >
                                  Delete
                                </button>
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

      {formState && (
        <CallQueueForm
          key={formState.queue?.id ?? 'new-call-queue'}
          queue={formState.queue}
          onSubmit={saveQueue}
          onCancel={() => setFormState(null)}
        />
      )}

      {queueToAssign && (
        <CallQueueAssignmentForm
          key={queueToAssign.id}
          queue={queueToAssign}
          onSubmit={saveAssignments}
          onCancel={() => setQueueToAssign(null)}
        />
      )}

      {queueToDelete && (
        <ConfirmDialog
          title="Delete call queue?"
          message={`Delete “${queueToDelete.name}”? This action cannot be undone.`}
          confirmLabel="Delete queue"
          confirmingLabel="Deleting..."
          errorMessage="The queue could not be deleted. Queues with assignments must be retained."
          forbiddenMessage="You do not have permission to delete this queue."
          onConfirm={confirmDelete}
          onCancel={() => setQueueToDelete(null)}
        />
      )}
    </section>
  )
}

export default CallQueuesPanel