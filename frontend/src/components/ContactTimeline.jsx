import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import ContactInteractionForm from './ContactInteractionForm.jsx'
import {
  createContactInteraction,
  deleteContactInteraction,
  listContactInteractions,
  updateContactInteraction,
} from '../services/contactInteractions.js'

const channels = [
  { value: '', label: 'All channels' },
  { value: 'phone', label: 'Phone call' },
  { value: 'sms', label: 'SMS' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'Email' },
  { value: 'in_person', label: 'In-person meeting' },
  { value: 'note', label: 'Internal note' },
]

const directions = [
  { value: '', label: 'All directions' },
  { value: 'outbound', label: 'Outbound' },
  { value: 'inbound', label: 'Inbound' },
  { value: 'internal', label: 'Internal' },
]

const outcomes = [
  { value: '', label: 'All outcomes' },
  { value: 'completed', label: 'Completed' },
  { value: 'no_answer', label: 'No answer' },
  { value: 'follow_up', label: 'Follow-up needed' },
  { value: 'declined', label: 'Declined' },
  { value: 'failed', label: 'Failed' },
  { value: 'informational', label: 'Informational' },
]

function formatLabel(value) {
  if (!value) {
    return 'Not recorded'
  }

  if (value === 'whatsapp') {
    return 'WhatsApp'
  }

  return value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function formatDateTime(value) {
  if (!value) {
    return 'Date unavailable'
  }

  return new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) {
    return null
  }

  if (seconds < 60) {
    return `${seconds} sec`
  }

  const minutes = Math.round(seconds / 60)

  return `${minutes} min`
}

function ContactTimeline({ contact, user, onClose }) {
  const [interactions, setInteractions] = useState([])
  const [pagination, setPagination] = useState(null)
  const [page, setPage] = useState(1)
  const [channel, setChannel] = useState('')
  const [direction, setDirection] = useState('')
  const [outcome, setOutcome] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedInteraction, setSelectedInteraction] =
    useState(null)
  const [interactionToDelete, setInteractionToDelete] =
    useState(null)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes(
    'interactions.create',
  )
  const canUpdate = permissions.includes(
    'interactions.update',
  )
  const canDelete = permissions.includes(
    'interactions.delete',
  )

  useEffect(() => {
    let cancelled = false

    async function loadInteractions() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listContactInteractions(
          contact.id,
          {
            page,
            channel,
            direction,
            outcome,
            dateFrom,
            dateTo,
            perPage: 20,
          },
        )

        if (!cancelled) {
          setInteractions(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view this timeline.'
              : 'The interaction timeline could not be loaded.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadInteractions()

    return () => {
      cancelled = true
    }
  }, [
    contact.id,
    page,
    channel,
    direction,
    outcome,
    dateFrom,
    dateTo,
    reloadKey,
  ])

  function changeFilter(setter, value) {
    setter(value)
    setPage(1)
  }

  function clearFilters() {
    setChannel('')
    setDirection('')
    setOutcome('')
    setDateFrom('')
    setDateTo('')
    setPage(1)
  }

  function openCreateForm() {
    setSelectedInteraction(null)
    setIsFormOpen(true)
  }

  function openEditForm(interaction) {
    setSelectedInteraction(interaction)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedInteraction(null)
  }

  async function handleSave(payload) {
    if (selectedInteraction) {
      await updateContactInteraction(
        selectedInteraction.id,
        payload,
      )
    } else {
      await createContactInteraction(contact.id, payload)
    }

    closeForm()

    if (page === 1) {
      setReloadKey((current) => current + 1)
    } else {
      setPage(1)
    }
  }

  async function handleDelete() {
    await deleteContactInteraction(interactionToDelete.id)
    setInteractionToDelete(null)

    if (interactions.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card contact-timeline-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="contact-timeline-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">CRM interaction history</p>
            <h3 id="contact-timeline-title">
              Contact timeline
            </h3>
            <p>
              {contact.full_name} - {contact.reference_code}
            </p>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onClose}
            aria-label="Close timeline"
          >
            ×
          </button>
        </div>

        <div className="timeline-toolbar">
          <p>
            View calls, messages, meetings, and internal notes
            in chronological order.
          </p>

          {canCreate && (
            <button
              type="button"
              className="primary-button"
              onClick={openCreateForm}
            >
              Record interaction
            </button>
          )}
        </div>

        <div className="timeline-filters">
          <label className="form-field">
            <span>Channel</span>
            <select
              value={channel}
              onChange={(event) =>
                changeFilter(setChannel, event.target.value)
              }
            >
              {channels.map((option) => (
                <option
                  key={option.value}
                  value={option.value}
                >
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Direction</span>
            <select
              value={direction}
              onChange={(event) =>
                changeFilter(setDirection, event.target.value)
              }
            >
              {directions.map((option) => (
                <option
                  key={option.value}
                  value={option.value}
                >
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Outcome</span>
            <select
              value={outcome}
              onChange={(event) =>
                changeFilter(setOutcome, event.target.value)
              }
            >
              {outcomes.map((option) => (
                <option
                  key={option.value}
                  value={option.value}
                >
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>From date</span>
            <input
              type="date"
              value={dateFrom}
              onChange={(event) =>
                changeFilter(setDateFrom, event.target.value)
              }
            />
          </label>

          <label className="form-field">
            <span>To date</span>
            <input
              type="date"
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(event) =>
                changeFilter(setDateTo, event.target.value)
              }
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

        <div className="timeline-results">
          {isLoading && (
            <p className="state-message">
              Loading interaction timeline...
            </p>
          )}

          {!isLoading && error && (
            <div className="error-message" role="alert">
              {error}
            </div>
          )}

          {!isLoading &&
            !error &&
            interactions.length === 0 && (
              <div className="empty-state">
                <h3>No interactions found</h3>
                <p>
                  Record the first interaction or change the
                  current filters.
                </p>
              </div>
            )}

          {!isLoading &&
            !error &&
            interactions.length > 0 && (
              <div className="interaction-timeline">
                {interactions.map((interaction) => (
                  <article
                    className="interaction-entry"
                    key={interaction.id}
                  >
                    <div
                      className={`interaction-marker ${interaction.channel}`}
                      aria-hidden="true"
                    />

                    <div className="interaction-entry-content">
                      <div className="interaction-entry-heading">
                        <div>
                          <div className="interaction-entry-labels">
                            <span className="code-pill">
                              {formatLabel(
                                interaction.channel,
                              )}
                            </span>

                            <span
                              className={`direction-pill ${interaction.direction}`}
                            >
                              {formatLabel(
                                interaction.direction,
                              )}
                            </span>

                            {interaction.outcome && (
                              <span className="outcome-pill">
                                {formatLabel(
                                  interaction.outcome,
                                )}
                              </span>
                            )}
                          </div>

                          <h4>
                            {interaction.subject ||
                              `${formatLabel(
                                interaction.channel,
                              )} interaction`}
                          </h4>
                        </div>

                        <time
                          dateTime={interaction.occurred_at}
                        >
                          {formatDateTime(
                            interaction.occurred_at,
                          )}
                        </time>
                      </div>

                      {interaction.notes && (
                        <p className="interaction-notes">
                          {interaction.notes}
                        </p>
                      )}

                      <div className="interaction-metadata">
                        <span>
                          Recorded by:{' '}
                          {interaction.recorded_by?.name ??
                            'Former user'}
                        </span>

                        {formatDuration(
                          interaction.duration_seconds,
                        ) && (
                          <span>
                            Duration:{' '}
                            {formatDuration(
                              interaction.duration_seconds,
                            )}
                          </span>
                        )}

                        {interaction
                          .consent_status_snapshot && (
                          <span
                            className={`consent-pill ${interaction.consent_status_snapshot}`}
                          >
                            Consent:{' '}
                            {formatLabel(
                              interaction
                                .consent_status_snapshot,
                            )}
                          </span>
                        )}
                      </div>

                      {(canUpdate || canDelete) && (
                        <div className="interaction-actions">
                          {canUpdate && (
                            <button
                              type="button"
                              className="text-button"
                              onClick={() =>
                                openEditForm(interaction)
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
                                setInteractionToDelete(
                                  interaction,
                                )
                              }
                            >
                              Delete
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  </article>
                ))}
              </div>
            )}
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

        {isFormOpen && (
          <ContactInteractionForm
            contact={contact}
            interaction={selectedInteraction}
            onSubmit={handleSave}
            onCancel={closeForm}
          />
        )}

        {interactionToDelete && (
          <ConfirmDialog
            title="Delete interaction?"
            message="Delete this interaction from the contact timeline? This action cannot be undone."
            confirmLabel="Delete interaction"
            onConfirm={handleDelete}
            onCancel={() =>
              setInteractionToDelete(null)
            }
          />
        )}
      </section>
    </div>
  )
}

export default ContactTimeline