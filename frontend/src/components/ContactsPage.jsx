import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import ContactConsentForm from './ContactConsentForm.jsx'
import ContactForm from './ContactForm.jsx'
import ContactTimeline from './ContactTimeline.jsx'
import ContactTransferDialog from './ContactTransferDialog.jsx'
import { listAreas } from '../services/areas.js'
import {
  createContact,
  deleteContact,
  listContacts,
  recordContactConsent,
  updateContact,
} from '../services/contacts.js'

function formatChannel(channel) {
  if (!channel) {
    return 'Not selected'
  }

  return channel === 'whatsapp'
    ? 'WhatsApp'
    : channel.toUpperCase()
}

function ContactsPage({ user }) {
  const [contacts, setContacts] = useState([])
  const [areas, setAreas] = useState([])
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [consentChannel, setConsentChannel] = useState('')
  const [consentStatus, setConsentStatus] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedContact, setSelectedContact] = useState(null)
  const [consentContact, setConsentContact] = useState(null)
  const [timelineContact, setTimelineContact] = useState(null)
  const [contactToDelete, setContactToDelete] = useState(null)
  const [isTransferOpen, setIsTransferOpen] = useState(false)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('contacts.create')
  const canUpdate = permissions.includes('contacts.update')
  const canDelete = permissions.includes('contacts.delete')
  const canImport = permissions.includes('contacts.import')
  const canExport = permissions.includes('contacts.export')
  const canTransfer = canImport || canExport
  const canManageConsent = permissions.includes(
  'contacts.consent.manage',
  )
  const canViewTimeline = permissions.includes(
  'interactions.view',
  )
  useEffect(() => {
    let cancelled = false

    async function loadAreas() {
      try {
        const response = await listAreas({ page: 1 })

        if (!cancelled) {
          setAreas(response.data ?? [])
        }
      } catch {
        if (!cancelled) {
          setAreas([])
        }
      }
    }

    loadAreas()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadContacts() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listContacts({
          page,
          search,
          status,
          consentChannel,
          consentStatus,
          perPage: 20,
        })

        if (!cancelled) {
          setContacts(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          const message =
            requestError.response?.status === 403
              ? 'You do not have permission to view contacts.'
              : 'Contacts could not be loaded. Please try again.'

          setError(message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadContacts()

    return () => {
      cancelled = true
    }
  }, [
    page,
    search,
    status,
    consentChannel,
    consentStatus,
    reloadKey,
  ])

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setStatus('')
    setConsentChannel('')
    setConsentStatus('')
    setPage(1)
  }

  function openCreateForm() {
    setSelectedContact(null)
    setIsFormOpen(true)
  }

  function openEditForm(contact) {
    setSelectedContact(contact)
    setIsFormOpen(true)
  }

  function closeForm() {
    setIsFormOpen(false)
    setSelectedContact(null)
  }

  async function handleSave(payload) {
    if (selectedContact) {
      await updateContact(selectedContact.id, payload)
    } else {
      await createContact(payload)
    }

    closeForm()

    if (page === 1) {
      setReloadKey((current) => current + 1)
    } else {
      setPage(1)
    }
  }

  function openConsentForm(contact) {
    setConsentContact(contact)
  }

  function closeConsentForm() {
    setConsentContact(null)
  }

  async function handleConsentSave(payload) {
    await recordContactConsent(consentContact.id, payload)
    closeConsentForm()
    setReloadKey((current) => current + 1)
  }

  function openTimeline(contact) {
  setTimelineContact(contact)
  }

function closeTimeline() {
  setTimelineContact(null)
}

function openTransferDialog() {
  setIsTransferOpen(true)
}

function closeTransferDialog() {
  setIsTransferOpen(false)
}

function handleContactsImported() {
  if (page === 1) {
    setReloadKey((current) => current + 1)
  } else {
    setPage(1)
  }
  }

  function openDeleteDialog(contact) {
    setContactToDelete(contact)
  }

  function closeDeleteDialog() {
    setContactToDelete(null)
  }

  async function handleDelete() {
    await deleteContact(contactToDelete.id)
    closeDeleteDialog()

    if (contacts.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  return (
    <section className="contacts-page">
      <div className="page-heading">
  <div>
    <p className="eyebrow">CRM management</p>
    <h2>Contacts</h2>
    <p className="page-description">
      Manage consent-aware contacts belonging to{' '}
      {user.tenant.name}.
    </p>
  </div>

  {(canTransfer || canCreate) && (
    <div className="page-heading-actions">
      {canTransfer && (
        <button
          type="button"
          className="secondary-button"
          onClick={openTransferDialog}
        >
          Import / export
        </button>
      )}

      {canCreate && (
        <button
          type="button"
          className="primary-button"
          onClick={openCreateForm}
        >
          Add contact
        </button>
      )}
    </div>
  )}
</div>

      <article className="content-card contacts-filter-card">
        <form className="contacts-search" onSubmit={applySearch}>
          <label className="form-field">
            <span>Search contacts</span>
            <input
              type="search"
              value={searchDraft}
              onChange={(event) =>
                setSearchDraft(event.target.value)
              }
              maxLength="100"
              placeholder="Name, reference, phone, or email"
            />
          </label>

          <button type="submit" className="primary-button">
            Search
          </button>
        </form>

        <div className="contacts-filters">
          <label className="form-field">
            <span>Contact status</span>
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="archived">Archived</option>
            </select>
          </label>

          <label className="form-field">
            <span>Consent channel</span>
            <select
              value={consentChannel}
              onChange={(event) => {
                setConsentChannel(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All channels</option>
              <option value="phone">Phone</option>
              <option value="sms">SMS</option>
              <option value="whatsapp">WhatsApp</option>
              <option value="email">Email</option>
            </select>
          </label>

          <label className="form-field">
            <span>Consent status</span>
            <select
              value={consentStatus}
              onChange={(event) => {
                setConsentStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All decisions</option>
              <option value="unknown">Unknown</option>
              <option value="granted">Granted</option>
              <option value="denied">Denied</option>
              <option value="revoked">Revoked</option>
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
          <p className="state-message">Loading contacts...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && contacts.length === 0 && (
          <div className="empty-state">
            <h3>No contacts found</h3>
            <p>
              Add the first contact or change the current filters.
            </p>
          </div>
        )}

        {!isLoading && !error && contacts.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table contacts-table">
                <thead>
                  <tr>
                    <th>Reference</th>
                    <th>Contact</th>
                    <th>Contact details</th>
                    <th>Area</th>
                    <th>Status</th>
                    <th>Consent</th>
                    {(canViewTimeline ||
                      canUpdate ||
                      canDelete ||
                      canManageConsent ||
                      canViewTimeline) && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {contacts.map((contact) => (
                    <tr key={contact.id}>
                      <td>
                        <span className="code-pill">
                          {contact.reference_code}
                        </span>
                      </td>

                      <td>
                        <strong>{contact.full_name}</strong>
                        <span className="table-secondary">
                          {contact.name_ar || '—'}
                        </span>
                      </td>

                      <td>
                        <span>{contact.phone || 'No phone'}</span>
                        <span className="table-secondary">
                          {contact.email || 'No email'}
                        </span>
                        <span className="table-secondary">
                          Preferred:{' '}
                          {formatChannel(
                            contact.preferred_channel,
                          )}
                        </span>
                      </td>

                      <td>
                        {contact.area ? (
                          <>
                            <span>{contact.area.name_en}</span>
                            <span className="table-secondary">
                              {contact.area.district?.name_en ?? ''}
                            </span>
                          </>
                        ) : (
                          'Unassigned'
                        )}
                      </td>

                      <td>
                        <span
                          className={`status-pill ${contact.status}`}
                        >
                          {contact.status}
                        </span>
                      </td>

                      <td>
                        {contact.consents?.length > 0 ? (
                          <div className="consent-list">
                            {contact.consents.map((consent) => (
                              <span
                                className={`consent-pill ${consent.status}`}
                                key={consent.id}
                              >
                                {formatChannel(consent.channel)}:{' '}
                                {consent.status}
                              </span>
                            ))}
                          </div>
                        ) : (
                          <span className="consent-pill unknown">
                            No decisions recorded
                          </span>
                        )}
                      </td>
                      {(canViewTimeline ||
                        canUpdate ||
                        canDelete ||
                        canManageConsent) && (
                        <td>
                          <div className="table-actions">
                              {canViewTimeline && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() => openTimeline(contact)}
                                >
                                  Timeline
                                </button>
                              )}

                            {canManageConsent && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() => openConsentForm(contact)}
                                >
                                  Consent
                                </button>
                            )}
                            {canUpdate && (
                              <button
                                type="button"
                                className="text-button"
                                onClick={() =>
                                  openEditForm(contact)
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
                                  openDeleteDialog(contact)
                                }
                              >
                                Delete
                              </button>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
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
          </>
        )}
      </article>

      {isTransferOpen && (
          <ContactTransferDialog
            user={user}
            onImported={handleContactsImported}
            onClose={closeTransferDialog}
          />
      )}

      {isFormOpen && (
        <ContactForm
          contact={selectedContact}
          areas={areas}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {consentContact && (
        <ContactConsentForm
          contact={consentContact}
          onSubmit={handleConsentSave}
          onCancel={closeConsentForm}
        />
      )}

      {timelineContact && (
        <ContactTimeline
          contact={timelineContact}
          user={user}
          onClose={closeTimeline}
        />
      )}

      {contactToDelete && (
        <ConfirmDialog
          title="Delete contact?"
          message={`Delete ${contactToDelete.full_name} and their consent records? This action cannot be undone.`}
          confirmLabel="Delete contact"
          onConfirm={handleDelete}
          onCancel={closeDeleteDialog}
        />
      )}
    </section>
  )
}

export default ContactsPage