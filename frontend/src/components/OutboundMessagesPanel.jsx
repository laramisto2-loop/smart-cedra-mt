import { useEffect, useState } from 'react'
import OutboundMessageDetails from './OutboundMessageDetails.jsx'
import OutboundMessageForm from './OutboundMessageForm.jsx'
import { listContacts } from '../services/contacts.js'
import {
  getOutboundMessage,
  listMessageTemplates,
  listOutboundMessages,
  sendOutboundMessage,
} from '../services/messaging.js'

function formatLabel(value) {
  if (!value) {
    return 'Unknown'
  }

  return value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
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

function OutboundMessagesPanel({ user }) {
  const permissions = user.permissions ?? []
  const canSend = permissions.includes('messages.send')

  const [messages, setMessages] = useState([])
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)

  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')
  const [channel, setChannel] = useState('')
  const [source, setSource] = useState('')
  const [status, setStatus] = useState('')
  const [consentStatus, setConsentStatus] = useState('')

  const [contacts, setContacts] = useState([])
  const [templates, setTemplates] = useState([])
  const [optionsError, setOptionsError] = useState('')
  const [isLoadingOptions, setIsLoadingOptions] =
    useState(false)

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [actionError, setActionError] = useState('')
  const [successMessage, setSuccessMessage] =
    useState('')
  const [refreshToken, setRefreshToken] = useState(0)

  const [showSendForm, setShowSendForm] = useState(false)
  const [selectedMessage, setSelectedMessage] =
    useState(null)
  const [loadingDetailsId, setLoadingDetailsId] =
    useState(null)

  useEffect(() => {
    let isCurrent = true

    async function loadMessages() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listOutboundMessages({
          page,
          search,
          channel,
          source,
          status,
          consentStatus,
        })

        if (!isCurrent) {
          return
        }

        setMessages(response.data ?? [])
        setMeta(response.meta ?? null)
      } catch {
        if (isCurrent) {
          setMessages([])
          setMeta(null)
          setLoadError(
            'Outbound messages could not be loaded. Please try again.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadMessages()

    return () => {
      isCurrent = false
    }
  }, [
    page,
    search,
    channel,
    source,
    status,
    consentStatus,
    refreshToken,
  ])

  useEffect(() => {
    if (!canSend) {
      return undefined
    }

    let isCurrent = true

    async function loadFormOptions() {
      setIsLoadingOptions(true)
      setOptionsError('')

      try {
        const [contactResponse, templateResponse] =
          await Promise.all([
            listContacts({
              status: 'active',
              perPage: 100,
            }),
            listMessageTemplates({
              status: 'approved',
              perPage: 100,
            }),
          ])

        if (!isCurrent) {
          return
        }

        setContacts(contactResponse.data ?? [])
        setTemplates(templateResponse.data ?? [])
      } catch {
        if (isCurrent) {
          setContacts([])
          setTemplates([])
          setOptionsError(
            'Contacts or approved templates could not be loaded. Refresh before sending a message.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoadingOptions(false)
        }
      }
    }

    loadFormOptions()

    return () => {
      isCurrent = false
    }
  }, [canSend, refreshToken])

  function refreshMessages() {
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
    setSource('')
    setStatus('')
    setConsentStatus('')
    setPage(1)
  }

  function openSendForm() {
    setActionError('')
    setSuccessMessage('')

    if (optionsError) {
      setActionError(optionsError)
      return
    }

    if (templates.length === 0) {
      setActionError(
        'Create and approve a message template before sending a message.',
      )
      return
    }

    if (contacts.length === 0) {
      setActionError(
        'No active contacts are available for messaging.',
      )
      return
    }

    setShowSendForm(true)
  }

  async function submitMessage(payload) {
    const message = await sendOutboundMessage(payload)

    setShowSendForm(false)
    setPage(1)

    if (message.status === 'suppressed') {
      setSuccessMessage(
        `The message was safely suppressed. ${
          message.suppression_reason ??
          'The contact does not have matching granted consent.'
        }`,
      )
    } else if (message.status === 'scheduled') {
      setSuccessMessage(
        'The message was accepted and scheduled outside the tenant quiet hours.',
      )
    } else {
      setSuccessMessage(
        'The message was accepted and queued successfully.',
      )
    }

    refreshMessages()
  }

  async function openMessageDetails(message) {
    setLoadingDetailsId(message.id)
    setActionError('')

    try {
      const detailedMessage = await getOutboundMessage(
        message.id,
      )

      setSelectedMessage(detailedMessage)
    } catch (requestError) {
      setActionError(
        requestError.response?.status === 403
          ? 'You do not have permission to view this message.'
          : 'Message details could not be loaded. Please try again.',
      )
    } finally {
      setLoadingDetailsId(null)
    }
  }

  return (
    <section className="messaging-panel">
      <div className="messaging-section-heading">
        <div>
          <p className="eyebrow">Delivery history</p>
          <h3>Outbound messages</h3>
          <p className="page-description">
            Send approved templates and monitor consent,
            scheduling, suppression, and delivery outcomes.
          </p>
        </div>

        {canSend && (
          <button
            type="button"
            className="primary-button"
            onClick={openSendForm}
            disabled={isLoadingOptions}
          >
            {isLoadingOptions
              ? 'Loading options...'
              : 'Send message'}
          </button>
        )}
      </div>

      {successMessage && (
        <div className="form-message success-message">
          {successMessage}
        </div>
      )}

      {(actionError || optionsError) && (
        <div
          className="form-message error-message"
          role="alert"
        >
          {actionError || optionsError}
        </div>
      )}

      <article className="content-card contacts-filter-card">
        <form
          className="contacts-search"
          onSubmit={applySearch}
        >
          <label className="form-field">
            <span>Search messages</span>
            <input
              type="search"
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Reference, recipient, template, or message"
            />
          </label>

          <button
            type="submit"
            className="primary-button"
          >
            Search
          </button>
        </form>

        <div className="contacts-filters messaging-filters">
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
            <span>Message status</span>
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
              <option value="queued">Queued</option>
              <option value="scheduled">Scheduled</option>
              <option value="sent">Sent</option>
              <option value="delivered">Delivered</option>
              <option value="read">Read</option>
              <option value="failed">Failed</option>
              <option value="suppressed">Suppressed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </label>

          <label className="form-field">
            <span>Consent result</span>
            <select
              value={consentStatus}
              onChange={(event) =>
                updateFilter(
                  setConsentStatus,
                  event.target.value,
                )
              }
            >
              <option value="">All consent results</option>
              <option value="granted">Granted</option>
              <option value="unknown">Unknown</option>
              <option value="denied">Denied</option>
              <option value="revoked">Revoked</option>
            </select>
          </label>

          <label className="form-field">
            <span>Source</span>
            <select
              value={source}
              onChange={(event) =>
                updateFilter(
                  setSource,
                  event.target.value,
                )
              }
            >
              <option value="">All sources</option>
              <option value="manual">Manual</option>
              <option value="campaign">Campaign</option>
              <option value="automation">Automation</option>
              <option value="call_center">
                Call center
              </option>
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
            Loading outbound messages...
          </p>
        )}

        {!isLoading && loadError && (
          <div className="error-message" role="alert">
            {loadError}
          </div>
        )}

        {!isLoading &&
          !loadError &&
          messages.length === 0 && (
            <div className="empty-state">
              <h3>No outbound messages found</h3>
              <p>
                Send the first message or change the current
                filters.
              </p>
            </div>
          )}

        {!isLoading &&
          !loadError &&
          messages.length > 0 && (
            <>
              <div className="table-wrapper">
                <table className="geography-table messaging-table">
                  <thead>
                    <tr>
                      <th>Message</th>
                      <th>Contact</th>
                      <th>Channel</th>
                      <th>Status</th>
                      <th>Consent</th>
                      <th>Timing</th>
                      <th>Sender</th>
                      <th>Actions</th>
                    </tr>
                  </thead>

                  <tbody>
                    {messages.map((message) => (
                      <tr key={message.id}>
                        <td>
                          <strong>
                            {message.reference_code}
                          </strong>
                          <span className="table-secondary">
                            {message.template?.name ??
                              message.template_code}
                          </span>
                          <span className="table-secondary">
                            {message.rendered_body}
                          </span>
                        </td>

                        <td>
                          <strong>
                            {message.contact?.full_name ??
                              'Unknown contact'}
                          </strong>
                          <span className="table-secondary">
                            {message.recipient}
                          </span>
                        </td>

                        <td>
                          <span className="message-channel-pill">
                            {message.channel?.toUpperCase()}
                          </span>
                          <span className="table-secondary">
                            {formatLabel(message.source)}
                          </span>
                        </td>

                        <td>
                          <span
                            className={`message-status-pill ${message.status}`}
                          >
                            {formatLabel(message.status)}
                          </span>

                          {message.suppression_reason && (
                            <span className="table-secondary danger-text">
                              {message.suppression_reason}
                            </span>
                          )}
                        </td>

                        <td>
                          <span
                            className={`consent-status-pill ${message.consent_status}`}
                          >
                            {formatLabel(
                              message.consent_status,
                            )}
                          </span>
                        </td>

                        <td>
                          <strong>
                            {formatDateTime(
                              message.created_at,
                            )}
                          </strong>

                          {message.scheduled_at && (
                            <span className="table-secondary">
                              Scheduled:{' '}
                              {formatDateTime(
                                message.scheduled_at,
                              )}
                            </span>
                          )}

                          {message.delivered_at && (
                            <span className="table-secondary">
                              Delivered:{' '}
                              {formatDateTime(
                                message.delivered_at,
                              )}
                            </span>
                          )}
                        </td>

                        <td>
                          <strong>
                            {message.sender?.name ??
                              'Unknown'}
                          </strong>
                          {message.sender?.email && (
                            <span className="table-secondary">
                              {message.sender.email}
                            </span>
                          )}
                        </td>

                        <td>
                          <div className="table-actions">
                            <button
                              type="button"
                              className="text-button"
                              onClick={() =>
                                openMessageDetails(message)
                              }
                              disabled={
                                loadingDetailsId === message.id
                              }
                            >
                              {loadingDetailsId === message.id
                                ? 'Loading...'
                                : 'Details'}
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))}
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

      {showSendForm && (
        <OutboundMessageForm
          contacts={contacts}
          templates={templates}
          onSubmit={submitMessage}
          onCancel={() => setShowSendForm(false)}
        />
      )}

      {selectedMessage && (
        <OutboundMessageDetails
          message={selectedMessage}
          onClose={() => setSelectedMessage(null)}
        />
      )}
    </section>
  )
}

export default OutboundMessagesPanel
//This completes the messaging history, consent outcomes, quiet-hour scheduling display, suppression feedback, filters, details, and sending workflow