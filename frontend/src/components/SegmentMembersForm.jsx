import { useMemo, useState } from 'react'

function contactName(contact) {
  return `${contact.first_name} ${contact.last_name}`.trim()
}

function SegmentMembersForm({
  segment,
  contacts = [],
  members = [],
  onSubmit,
  onCancel,
}) {
  const [selectedIds, setSelectedIds] = useState(
    () => new Set(members.map((contact) => contact.id)),
  )
  const [search, setSearch] = useState('')
  const [generalError, setGeneralError] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const availableContacts = useMemo(() => {
    const contactsById = new Map()

    contacts.forEach((contact) => {
      contactsById.set(contact.id, contact)
    })

    members.forEach((contact) => {
      contactsById.set(contact.id, contact)
    })

    return Array.from(contactsById.values()).sort((first, second) =>
      contactName(first).localeCompare(contactName(second)),
    )
  }, [contacts, members])

  const filteredContacts = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase()

    if (normalizedSearch === '') {
      return availableContacts
    }

    return availableContacts.filter((contact) => {
      const searchableText = [
        contact.reference_code,
        contact.first_name,
        contact.last_name,
        contact.name_ar,
        contact.phone,
        contact.email,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()

      return searchableText.includes(normalizedSearch)
    })
  }, [availableContacts, search])

  function toggleContact(contactId) {
    setSelectedIds((current) => {
      const updated = new Set(current)

      if (updated.has(contactId)) {
        updated.delete(contactId)
      } else {
        updated.add(contactId)
      }

      return updated
    })
  }

  function selectVisible() {
    setSelectedIds((current) => {
      const updated = new Set(current)

      filteredContacts.forEach((contact) => {
        updated.add(contact.id)
      })

      return updated
    })
  }

  function clearSelection() {
    setSelectedIds(new Set())
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setGeneralError('')
    setIsSubmitting(true)

    try {
      await onSubmit(Array.from(selectedIds))
    } catch (requestError) {
      const validationMessage =
        requestError.response?.data?.errors?.contact_ids?.[0]
        ?? requestError.response?.data?.message

      setGeneralError(
        validationMessage
        ?? 'The segment members could not be saved.',
      )
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
        aria-labelledby="segment-members-title"
      >
        <div className="modal-heading">
          <div>
            <p className="eyebrow">Static segment membership</p>
            <h3 id="segment-members-title">
              Manage members
            </h3>
            <p>
              {segment.name} · {segment.code}
            </p>
          </div>

          <button
            type="button"
            className="modal-close"
            onClick={onCancel}
            disabled={isSubmitting}
            aria-label="Close member manager"
          >
            ×
          </button>
        </div>

        <div className="info-message">
          Static segments are managed manually. Selecting or
          removing a contact here does not change their contact
          details or communication consent.
        </div>

        {generalError && (
          <div className="error-message" role="alert">
            {generalError}
          </div>
        )}

        <form onSubmit={handleSubmit}>
          <label className="form-field">
            <span>Search available contacts</span>
            <input
              type="search"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Name, reference, phone, or email"
              autoFocus
            />
          </label>

          <div className="member-picker-toolbar">
            <strong>
              {selectedIds.size} contact
              {selectedIds.size === 1 ? '' : 's'} selected
            </strong>

            <div>
              <button
                type="button"
                className="text-button"
                onClick={selectVisible}
              >
                Select visible
              </button>

              <button
                type="button"
                className="text-button"
                onClick={clearSelection}
              >
                Clear selection
              </button>
            </div>
          </div>

          <div className="member-picker">
            {filteredContacts.length === 0 ? (
              <div className="empty-state compact">
                <strong>No contacts found</strong>
                <span>
                  Change the search or add contacts first.
                </span>
              </div>
            ) : (
              filteredContacts.map((contact) => (
                <label
                  className="member-option"
                  key={contact.id}
                >
                  <input
                    type="checkbox"
                    checked={selectedIds.has(contact.id)}
                    onChange={() => toggleContact(contact.id)}
                  />

                  <span className="member-option-details">
                    <strong>{contactName(contact)}</strong>
                    <small>
                      {contact.reference_code}
                      {contact.phone
                        ? ` · ${contact.phone}`
                        : ''}
                    </small>
                    {contact.email && (
                      <small>{contact.email}</small>
                    )}
                  </span>

                  <span
                    className={`status-pill ${
                      contact.status === 'active'
                        ? 'active'
                        : 'neutral'
                    }`}
                  >
                    {contact.status}
                  </span>
                </label>
              ))
            )}
          </div>

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
              disabled={isSubmitting}
            >
              {isSubmitting
                ? 'Saving...'
                : 'Save membership'}
            </button>
          </div>
        </form>
      </section>
    </div>
  )
}

export default SegmentMembersForm