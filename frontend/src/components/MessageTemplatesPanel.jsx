import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import MessageTemplateForm from './MessageTemplateForm.jsx'
import {
  createMessageTemplate,
  deleteMessageTemplate,
  listMessageTemplates,
  reviewMessageTemplate,
  updateMessageTemplate,
} from '../services/messaging.js'

const channelLabels = {
  whatsapp: 'WhatsApp',
  sms: 'SMS',
}

const categoryLabels = {
  utility: 'Utility',
  marketing: 'Marketing',
  authentication: 'Authentication',
}

function formatStatus(status) {
  return status
    .split('_')
    .map(
      (word) =>
        word.charAt(0).toUpperCase() + word.slice(1),
    )
    .join(' ')
}

function MessageTemplatesPanel({ user }) {
  const permissions = user.permissions ?? []

  const canCreate = permissions.includes(
    'messages.templates.create',
  )
  const canUpdate = permissions.includes(
    'messages.templates.update',
  )
  const canApprove = permissions.includes(
    'messages.templates.approve',
  )
  const canDelete = permissions.includes(
    'messages.templates.delete',
  )

  const [templates, setTemplates] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [channel, setChannel] = useState('')
  const [category, setCategory] = useState('')
  const [status, setStatus] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [processingTemplateId, setProcessingTemplateId] =
    useState(null)
  const [refreshToken, setRefreshToken] = useState(0)

  const [formState, setFormState] = useState(null)
  const [templateToDelete, setTemplateToDelete] =
    useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadTemplates() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listMessageTemplates({
          page,
          search,
          channel,
          category,
          status,
        })

        if (!isCurrent) {
          return
        }

        setTemplates(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setTemplates([])
          setMeta(null)
          setLoadError(
            'Message templates could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadTemplates()

    return () => {
      isCurrent = false
    }
  }, [
    page,
    search,
    channel,
    category,
    status,
    refreshToken,
  ])

  function refreshTemplates() {
    setRefreshToken((current) => current + 1)
  }

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchInput.trim())
  }

  function updateFilter(setter, value) {
    setPage(1)
    setter(value)
  }

  function clearFilters() {
    setSearchInput('')
    setSearch('')
    setChannel('')
    setCategory('')
    setStatus('')
    setPage(1)
  }

  function openCreateForm() {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'create',
      template: null,
    })
  }

  function openEditForm(template) {
    setActionError('')
    setSuccessMessage('')
    setFormState({
      mode: 'edit',
      template,
    })
  }

  async function saveTemplate(payload) {
    if (formState.mode === 'edit') {
      await updateMessageTemplate(
        formState.template.id,
        payload,
      )

      setSuccessMessage(
        'Message template updated successfully.',
      )
    } else {
      await createMessageTemplate(payload)

      setSuccessMessage(
        'Draft message template created successfully.',
      )
    }

    setFormState(null)
    setPage(1)
    refreshTemplates()
  }

  async function changeTemplateStatus(
    template,
    nextStatus,
  ) {
    setProcessingTemplateId(template.id)
    setActionError('')
    setSuccessMessage('')

    try {
      await reviewMessageTemplate(template.id, nextStatus)

      setSuccessMessage(
        nextStatus === 'approved'
          ? 'Message template approved successfully.'
          : 'Message template rejected successfully.',
      )

      refreshTemplates()
    } catch (requestError) {
      if (requestError.response?.status === 403) {
        setActionError(
          'You do not have permission to review this template.',
        )
      } else if (requestError.response?.status === 422) {
        const errors =
          requestError.response.data.errors ?? {}

        setActionError(
          Object.values(errors).flat()[0] ??
            'The template status could not be changed.',
        )
      } else {
        setActionError(
          'The template status could not be changed. Please try again.',
        )
      }
    } finally {
      setProcessingTemplateId(null)
    }
  }

  async function confirmDelete() {
    await deleteMessageTemplate(templateToDelete.id)

    setTemplateToDelete(null)
    setSuccessMessage(
      'Message template deleted successfully.',
    )

    if (templates.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshTemplates()
    }
  }

  return (
    <section className="messaging-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Reusable content</p>
          <h3>Message templates</h3>
          <p className="page-description">
            Draft, review, and approve consent-aware WhatsApp
            and SMS content.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Create template
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
            <span>Search templates</span>
            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Name, code, or message body"
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
            <span>Channel</span>
            <select
              value={channel}
              onChange={(event) =>
                updateFilter(
                  setChannel,
                  event.target.value,
                )
              }
            >
              <option value="">All channels</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="sms">SMS</option>
            </select>
          </label>

          <label className="form-field">
            <span>Category</span>
            <select
              value={category}
              onChange={(event) =>
                updateFilter(
                  setCategory,
                  event.target.value,
                )
              }
            >
              <option value="">All categories</option>
              <option value="utility">Utility</option>
              <option value="marketing">Marketing</option>
              <option value="authentication">
                Authentication
              </option>
            </select>
          </label>

          <label className="form-field">
            <span>Status</span>
            <select
              value={status}
              onChange={(event) =>
                updateFilter(
                  setStatus,
                  event.target.value,
                )
              }
            >
              <option value="">All statuses</option>
              <option value="draft">Draft</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
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
            Loading message templates...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading &&
          !loadError &&
          templates.length === 0 && (
            <div className="empty-state">
              <h3>No message templates found</h3>
              <p>
                Create the first template or change the
                current filters.
              </p>
            </div>
          )}

        {!isLoading &&
          !loadError &&
          templates.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Template</th>
                      <th>Channel</th>
                      <th>Category</th>
                      <th>Status</th>
                      <th>Language</th>
                      <th>Messages</th>
                      <th>Creator</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {templates.map((template) => {
                      const isProcessing =
                        processingTemplateId ===
                        template.id

                      const canEditTemplate =
                        canUpdate &&
                        template.status !== 'approved'

                      const canDeleteTemplate =
                        canDelete &&
                        template.outbound_messages_count ===
                          0

                      return (
                        <tr key={template.id}>
                          <td>
                            <strong>{template.name}</strong>
                            <span className="table-secondary">
                              {template.code}
                            </span>
                            <span className="table-secondary">
                              {template.body}
                            </span>
                          </td>

                          <td>
                            <span className="message-channel-pill">
                              {channelLabels[
                                template.channel
                              ] ?? template.channel}
                            </span>
                          </td>

                          <td>
                            {categoryLabels[
                              template.category
                            ] ?? template.category}
                          </td>

                          <td>
                            <span
                              className={`message-status-pill ${template.status}`}
                            >
                              {formatStatus(
                                template.status,
                              )}
                            </span>
                          </td>

                          <td>
                            {template.language_code}
                          </td>

                          <td>
                            {template.outbound_messages_count ??
                              0}
                          </td>

                          <td>
                            <strong>
                              {template.creator?.name ??
                                'Unknown'}
                            </strong>
                            {template.creator?.email && (
                              <span className="table-secondary">
                                {template.creator.email}
                              </span>
                            )}
                          </td>

                          <td>
                            <div className="table-actions">
                              {canEditTemplate && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(template)
                                  }
                                >
                                  Edit
                                </button>
                              )}

                              {canApprove &&
                                template.status !==
                                  'approved' && (
                                  <button
                                    type="button"
                                    className="text-button"
                                    onClick={() =>
                                      changeTemplateStatus(
                                        template,
                                        'approved',
                                      )
                                    }
                                    disabled={isProcessing}
                                  >
                                    {isProcessing
                                      ? 'Saving...'
                                      : 'Approve'}
                                  </button>
                                )}

                              {canApprove &&
                                template.status !==
                                  'rejected' && (
                                  <button
                                    type="button"
                                    className="text-button warning"
                                    onClick={() =>
                                      changeTemplateStatus(
                                        template,
                                        'rejected',
                                      )
                                    }
                                    disabled={isProcessing}
                                  >
                                    Reject
                                  </button>
                                )}

                              {canDeleteTemplate && (
                                <button
                                  type="button"
                                  className="text-button danger"
                                  onClick={() =>
                                    setTemplateToDelete(
                                      template,
                                    )
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
        <MessageTemplateForm
          key={
            formState.template?.id ??
            'new-message-template'
          }
          template={formState.template}
          onSubmit={saveTemplate}
          onCancel={() => setFormState(null)}
        />
      )}

      {templateToDelete && (
        <ConfirmDialog
          title="Delete message template?"
          message={`Delete “${templateToDelete.name}”? This action cannot be undone.`}
          confirmLabel="Delete template"
          confirmingLabel="Deleting..."
          errorMessage="The message template could not be deleted. Templates already used by messages must be retained."
          forbiddenMessage="You do not have permission to delete this template."
          onConfirm={confirmDelete}
          onCancel={() => setTemplateToDelete(null)}
        />
      )}
    </section>
  )
}

export default MessageTemplatesPanel
//This panel provides template search, filtering, pagination, creation, editing, approval, rejection, deletion, and permission-aware actions