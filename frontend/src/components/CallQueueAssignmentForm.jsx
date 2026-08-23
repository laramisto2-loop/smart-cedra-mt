import { useEffect, useState } from 'react'
import { listContacts } from '../services/contacts.js'

const priorities = [
  { value: '', label: 'Use queue priority' },
  { value: 'low', label: 'Low' },
  { value: 'normal', label: 'Normal' },
  { value: 'high', label: 'High' },
  { value: 'urgent', label: 'Urgent' },
]

function contactName(contact) {
  return contact.full_name
    ?? [contact.first_name, contact.last_name]
      .filter(Boolean)
      .join(' ')
    ?? contact.reference_code
}

function CallQueueAssignmentForm({
  queue,
  onSubmit,
  onCancel,
}) {
  const [contacts, setContacts] = useState([])
  const [selectedIds, setSelectedIds] = useState([])
  const [searchInput, setSearchInput] = useState('')
  const [search, setSearch] = useState('')

  const [priority, setPriority] = useState('')
  const [scheduledFor, setScheduledFor] = useState('')
  const [notes, setNotes] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [loadError, setLoadError] = useState('')
  const [errors, setErrors] = useState({})
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  useEffect(() => {
    let isCurrent = true

    async function loadContacts() {
      setIsLoading(true)
      setLoadError('')

      try {
        const response = await listContacts({
          search,
          status: 'active',
          perPage: 100,
        })

        if (isCurrent) {
          setContacts(response.data ?? [])
        }
      } catch {
        if (isCurrent) {
          setContacts([])
          setLoadError(
            'Active contacts could not be loaded.',
          )
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false)
        }
      }
    }

    loadContacts()

    return () => {
      isCurrent = false
    }
  }, [search])

  function applySearch(event) {
    event.preventDefault()
    setSearch(searchInput.trim())
  }

  function toggleContact(contactId) {
    setSelectedIds((current) => (
      current.includes(contactId)
        ? current.filter((id) => id !== contactId)
        : [...current, contactId]
    ))

    setErrors({})
  }

  function selectVisibleContacts() {
    const visibleIds = contacts.map((contact) => contact.id)

    setSelectedIds((current) => [
      ...new Set([...current, ...visibleIds]),
    ])
  }

  function clearSelection() {
    setSelectedIds([])
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setErrors({})
    setGeneralError('')

    if (selectedIds.length === 0) {
      setGeneralError(
        'Select at least one contact for this queue.',
      )
      return
    }

    setIsSubmitting(true)

    const payload = {
      contact_ids: selectedIds,
      assigned_to_user_id: null,
      scheduled_for: scheduledFor || null,
      notes: notes.trim() === '' ? null : notes.trim(),
    }

    if (priority !== '') {
      payload.priority = priority
    }

    try {
      await onSubmit(payload)
    } catch (requestError) {
      if (requestError.response?.status === 422) {
        const validationErrors =
          requestError.response.data.errors ?? {}

        setErrors(validationErrors)
        setGeneralError(
          Object.values(validationErrors).flat()[0]
            ?? 'The contacts could not be assigned.',
        )
      } else if (requestError.response?.status === 403) {
        setGeneralError(
          'You do not have permission to assign this queue.',
        )
      } else {
        setGeneralError(
          'The contacts could not be assigned. Please try again.',
        )
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop">
      <section
        className="modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="queue-assignment-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Bulk assignment</p>
            <h3 id="queue-assignment-title">
              Assign contacts to queue
            </h3>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close assignment form"
          >
            ×
          </button>
        </div>

        <p className="page-description">
          {queue.name} · {queue.code}
        </p>

        <div className="info-message">
          New assignments will remain unassigned until a field
          agent claims them.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <div className="contacts-search">
            <label className="form-field">
              <span>Find active contacts</span>
              <input
                type="search"
                value={searchInput}
                onChange={(event) =>
                  setSearchInput(event.target.value)
                }
                placeholder="Name, reference, phone, or email"
              />
            </label>

            <button
              type="button"
              className="secondary-button"
              onClick={applySearch}
            >
              Search
            </button>
          </div>

          <div className="table-actions">
            <button
              type="button"
              className="text-button"
              onClick={selectVisibleContacts}
              disabled={contacts.length === 0}
            >
              Select visible contacts
            </button>

            <button
              type="button"
              className="text-button"
              onClick={clearSelection}
              disabled={selectedIds.length === 0}
            >
              Clear selection
            </button>

            <strong>
              {selectedIds.length} selected
            </strong>
          </div>

          <div className="assignment-contact-list">
            {isLoading && (
              <p className="state-message">
                Loading contacts...
              </p>
            )}

            {!isLoading && loadError && (
              <div className="error-message">
                {loadError}
              </div>
            )}

            {!isLoading
              && !loadError
              && contacts.length === 0 && (
                <p className="state-message">
                  No active contacts found.
                </p>
              )}

            {!isLoading
              && !loadError
              && contacts.map((contact) => (
                <label
                  key={contact.id}
                  className="assignment-contact-option"
                >
                  <input
                    type="checkbox"
                    checked={selectedIds.includes(contact.id)}
                    onChange={() =>
                      toggleContact(contact.id)
                    }
                  />

                  <span>
                    <strong>{contactName(contact)}</strong>
                    <small>
                      {contact.reference_code}
                      {contact.phone
                        ? ` · ${contact.phone}`
                        : ''}
                    </small>
                  </span>
                </label>
              ))}
          </div>

          {errors.contact_ids && (
            <small className="field-error">
              {errors.contact_ids[0]}
            </small>
          )}

          <div className="incident-form-grid">
            <label className="form-field">
              <span>Priority</span>
              <select
                value={priority}
                onChange={(event) =>
                  setPriority(event.target.value)
                }
              >
                {priorities.map((option) => (
                  <option
                    key={option.value}
                    value={option.value}
                  >
                    {option.label}
                  </option>
                ))}
              </select>
              {errors.priority && (
                <small className="field-error">
                  {errors.priority[0]}
                </small>
              )}
            </label>

            <label className="form-field">
              <span>Schedule for (optional)</span>
              <input
                type="datetime-local"
                value={scheduledFor}
                onChange={(event) =>
                  setScheduledFor(event.target.value)
                }
              />
              {errors.scheduled_for && (
                <small className="field-error">
                  {errors.scheduled_for[0]}
                </small>
              )}
            </label>
          </div>

          <label className="form-field">
            <span>Assignment notes (optional)</span>
            <textarea
              value={notes}
              onChange={(event) =>
                setNotes(event.target.value)
              }
              maxLength="5000"
              rows="3"
              placeholder="Add instructions for the assigned agents."
            />
            {errors.notes && (
              <small className="field-error">
                {errors.notes[0]}
              </small>
            )}
          </label>

          <div className="modal-actions">
            <button
              type="button"
              className="secondary-button"
              onClick={onCancel}
              disabled={isSubmitting}
            >
              Cancel
            </button>

            <button
              type="submit"
              className="primary-button"
              disabled={
                isSubmitting || selectedIds.length === 0
              }
            >
              {isSubmitting
                ? 'Assigning...'
                : `Assign ${selectedIds.length} contacts`}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default CallQueueAssignmentForm