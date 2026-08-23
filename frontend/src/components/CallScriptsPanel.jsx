import { useEffect, useState } from 'react'
import CallScriptForm from './CallScriptForm.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import {
  activateCallScript,
  createCallScript,
  deleteCallScript,
  listCallScripts,
  updateCallScript,
} from '../services/callCenter.js'

function formatStatus(status) {
  return status
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function CallScriptsPanel({ user }) {
  const permissions = user.permissions ?? []

  const canCreate = permissions.includes(
    'calls.scripts.create',
  )
  const canUpdate = permissions.includes(
    'calls.scripts.update',
  )
  const canActivate = permissions.includes(
    'calls.scripts.activate',
  )
  const canDelete = permissions.includes(
    'calls.scripts.delete',
  )

  const [scripts, setScripts] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [languageCode, setLanguageCode] = useState('')
  const [status, setStatus] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [processingId, setProcessingId] =
    useState(null)
  const [refreshToken, setRefreshToken] = useState(0)

  const [formState, setFormState] = useState(null)
  const [scriptToDelete, setScriptToDelete] =
    useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadScripts() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listCallScripts({
          page,
          search,
          languageCode,
          status,
        })

        if (!isCurrent) {
          return
        }

        setScripts(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setScripts([])
          setMeta(null)
          setLoadError(
            'Call scripts could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadScripts()

    return () => {
      isCurrent = false
    }
  }, [
    page,
    search,
    languageCode,
    status,
    refreshToken,
  ])

  function refreshScripts() {
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
    setLanguageCode('')
    setStatus('')
    setPage(1)
  }

  function openCreateForm() {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'create',
      script: null,
    })
  }

  function openEditForm(script) {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'edit',
      script,
    })
  }

  async function saveScript(payload) {
    if (formState.mode === 'edit') {
      await updateCallScript(
        formState.script.id,
        payload,
      )

      setSuccessMessage(
        'Call script updated successfully.',
      )
    } else {
      await createCallScript(payload)

      setSuccessMessage(
        'Draft call script created successfully.',
      )
    }

    setFormState(null)
    setPage(1)
    refreshScripts()
  }

  async function changeStatus(script, nextStatus) {
    setProcessingId(script.id)
    setActionError('')
    setSuccessMessage('')

    try {
      await activateCallScript(script.id, nextStatus)

      setSuccessMessage(
        nextStatus === 'active'
          ? 'Call script activated successfully.'
          : 'Call script archived successfully.',
      )

      refreshScripts()
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setActionError(
          'You do not have permission to change this script.',
        )
      } else if (requestError.response?.status === 422) {
        const errors =
          requestError.response.data.errors ?? {}

        setActionError(
          Object.values(errors).flat()[0]
            ?? 'The script status could not be changed.',
        )
      } else {
        setActionError(
          'The script status could not be changed. Please try again.',
        )
      }
    } finally {
      setProcessingId(null)
    }
  }

  async function confirmDelete() {
    await deleteCallScript(scriptToDelete.id)

    setScriptToDelete(null)
    setSuccessMessage(
      'Call script deleted successfully.',
    )

    if (scripts.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshScripts()
    }
  }

  return (
    <section className="messaging-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Agent guidance</p>
          <h3>Call scripts</h3>
          <p className="page-description">
            Create, review, and activate standardized scripts
            for campaign calls.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Create script
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
            <span>Search scripts</span>
            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Name, code, description, or script text"
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
            <span>Language</span>
            <select
              value={languageCode}
              onChange={(event) => {
                setLanguageCode(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All languages</option>
              <option value="en">English</option>
              <option value="ar">Arabic</option>
              <option value="fr">French</option>
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
            Loading call scripts...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading
          && !loadError
          && scripts.length === 0 && (
            <div className="empty-state">
              <h3>No call scripts found</h3>
              <p>
                Create the first script or change the current
                filters.
              </p>
            </div>
          )}

        {!isLoading
          && !loadError
          && scripts.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Script</th>
                      <th>Language</th>
                      <th>Status</th>
                      <th>Queues</th>
                      <th>Creator</th>
                      <th>Activated</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {scripts.map((script) => {
                      const isProcessing =
                        processingId === script.id

                      const mayEdit =
                        canUpdate
                        && script.status !== 'archived'

                      const mayDelete =
                        canDelete
                        && script.status === 'draft'
                        && (script.queues_count ?? 0) === 0

                      return (
                        <tr key={script.id}>
                          <td>
                            <strong>{script.name}</strong>
                            <span className="table-secondary">
                              {script.code}
                            </span>
                            {script.description && (
                              <span className="table-secondary">
                                {script.description}
                              </span>
                            )}
                          </td>

                          <td>{script.language_code}</td>

                          <td>
                            <span
                              className={`message-status-pill ${script.status}`}
                            >
                              {formatStatus(script.status)}
                            </span>
                          </td>

                          <td>{script.queues_count ?? 0}</td>

                          <td>
                            <strong>
                              {script.creator?.name ?? 'Unknown'}
                            </strong>
                            {script.creator?.email && (
                              <span className="table-secondary">
                                {script.creator.email}
                              </span>
                            )}
                          </td>

                          <td>
                            {script.activated_at
                              ? new Date(
                                  script.activated_at,
                                ).toLocaleString()
                              : 'Not activated'}
                          </td>

                          <td>
                            <div className="table-actions">
                              {mayEdit && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(script)
                                  }
                                >
                                  Edit
                                </button>
                              )}

                              {canActivate
                                && script.status === 'draft' && (
                                  <button
                                    type="button"
                                    className="text-button"
                                    onClick={() =>
                                      changeStatus(
                                        script,
                                        'active',
                                      )
                                    }
                                    disabled={isProcessing}
                                  >
                                    {isProcessing
                                      ? 'Saving...'
                                      : 'Activate'}
                                  </button>
                                )}

                              {canActivate
                                && script.status === 'active' && (
                                  <button
                                    type="button"
                                    className="text-button warning"
                                    onClick={() =>
                                      changeStatus(
                                        script,
                                        'archived',
                                      )
                                    }
                                    disabled={isProcessing}
                                  >
                                    Archive
                                  </button>
                                )}

                              {mayDelete && (
                                <button
                                  type="button"
                                  className="text-button danger"
                                  onClick={() =>
                                    setScriptToDelete(script)
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
        <CallScriptForm
          key={formState.script?.id ?? 'new-call-script'}
          script={formState.script}
          onSubmit={saveScript}
          onCancel={() => setFormState(null)}
        />
      )}

      {scriptToDelete && (
        <ConfirmDialog
          title="Delete call script?"
          message={`Delete “${scriptToDelete.name}”? This action cannot be undone.`}
          confirmLabel="Delete script"
          confirmingLabel="Deleting..."
          errorMessage="The call script could not be deleted. Scripts connected to queues must be retained."
          forbiddenMessage="You do not have permission to delete this script."
          onConfirm={confirmDelete}
          onCancel={() => setScriptToDelete(null)}
        />
      )}
    </section>
  )
}

export default CallScriptsPanel