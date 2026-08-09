import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import SegmentForm from './SegmentForm.jsx'
import SegmentMembersForm from './SegmentMembersForm.jsx'
import { listAreas } from '../services/areas.js'
import { listContacts } from '../services/contacts.js'
import {
  createSegment,
  deleteSegment,
  listSegmentMembers,
  listSegments,
  syncSegmentMembers,
  updateSegment,
} from '../services/segments.js'

async function loadEveryPage(loadPage) {
  const firstResponse = await loadPage(1)
  const records = [...(firstResponse.data ?? [])]
  const lastPage = Math.min(
    firstResponse.meta?.last_page ?? 1,
    10,
  )

  for (let page = 2; page <= lastPage; page += 1) {
    const response = await loadPage(page)
    records.push(...(response.data ?? []))
  }

  return records
}

function formatRuleValue(key, value, areas) {
  if (key === 'area_id') {
    return (
      areas.find((area) => area.id === Number(value))
        ?.name_en ?? `Area #${value}`
    )
  }

  if (key.includes('channel')) {
    return value === 'whatsapp'
      ? 'WhatsApp'
      : value.toUpperCase()
  }

  return String(value)
    .replaceAll('_', ' ')
    .replace(/^\w/, (letter) => letter.toUpperCase())
}

function ruleLabel(key) {
  const labels = {
    contact_status: 'Contact status',
    area_id: 'Area',
    preferred_language: 'Language',
    preferred_channel: 'Preferred channel',
    consent_channel: 'Consent channel',
    consent_status: 'Consent decision',
  }

  return labels[key] ?? key
}

function SegmentsPage({ user }) {
  const [segments, setSegments] = useState([])
  const [areas, setAreas] = useState([])
  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedSegment, setSelectedSegment] = useState(null)
  const [segmentToDelete, setSegmentToDelete] = useState(null)
  const [memberSegment, setMemberSegment] = useState(null)
  const [availableContacts, setAvailableContacts] = useState([])
  const [currentMembers, setCurrentMembers] = useState([])
  const [loadingMembersId, setLoadingMembersId] = useState(null)

  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('segments.create')
  const canUpdate = permissions.includes('segments.update')
  const canDelete = permissions.includes('segments.delete')
  const canManageMembers = permissions.includes(
    'segments.members.manage',
  )

  useEffect(() => {
    let cancelled = false

    async function loadAreaOptions() {
      try {
        const loadedAreas = await loadEveryPage((areaPage) =>
          listAreas({
            page: areaPage,
          }),
        )

        if (!cancelled) {
          setAreas(loadedAreas)
        }
      } catch {
        if (!cancelled) {
          setAreas([])
        }
      }
    }

    loadAreaOptions()

    return () => {
      cancelled = true
    }
  }, [])

  useEffect(() => {
    let cancelled = false

    async function loadSegmentRecords() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listSegments({
          page,
          search,
          type,
          status,
        })

        if (!cancelled) {
          setSegments(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(
            requestError.response?.status === 403
              ? 'You do not have permission to view segments.'
              : 'Segments could not be loaded. Please try again.',
          )
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadSegmentRecords()

    return () => {
      cancelled = true
    }
  }, [page, reloadKey, search, status, type])

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setType('')
    setStatus('')
    setPage(1)
  }

  function openCreateForm() {
    setSelectedSegment(null)
    setIsFormOpen(true)
  }

  function openEditForm(segment) {
    setSelectedSegment(segment)
    setIsFormOpen(true)
  }

  function closeForm() {
    setSelectedSegment(null)
    setIsFormOpen(false)
  }

  async function handleSave(payload) {
    if (selectedSegment) {
      await updateSegment(selectedSegment.id, payload)
    } else {
      await createSegment(payload)
    }

    closeForm()
    setReloadKey((current) => current + 1)
  }

  async function openMemberManager(segment) {
    setLoadingMembersId(segment.id)
    setError('')

    try {
      const [contacts, members] = await Promise.all([
        loadEveryPage((contactPage) =>
          listContacts({
            page: contactPage,
            perPage: 100,
          }),
        ),
        loadEveryPage((memberPage) =>
          listSegmentMembers(segment.id, {
            page: memberPage,
            perPage: 100,
          }),
        ),
      ])

      setAvailableContacts(contacts)
      setCurrentMembers(members)
      setMemberSegment(segment)
    } catch {
      setError(
        'Segment members could not be loaded. Please try again.',
      )
    } finally {
      setLoadingMembersId(null)
    }
  }

  function closeMemberManager() {
    setMemberSegment(null)
    setAvailableContacts([])
    setCurrentMembers([])
  }

  async function handleMemberSync(contactIds) {
    await syncSegmentMembers(memberSegment.id, contactIds)
    closeMemberManager()
    setReloadKey((current) => current + 1)
  }

  async function handleDelete() {
    await deleteSegment(segmentToDelete.id)
    setSegmentToDelete(null)

    if (segments.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      setReloadKey((current) => current + 1)
    }
  }

  return (
    <section className="segments-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">CRM segmentation</p>
          <h2>Segments</h2>
          <p className="page-description">
            Organize contacts into manual lists or automatic
            rule-based audiences.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add segment
          </button>
        )}
      </div>

      <article className="content-card contacts-filter-card">
        <form className="contacts-search" onSubmit={applySearch}>
          <label className="form-field">
            <span>Search segments</span>
            <input
              type="search"
              value={searchDraft}
              onChange={(event) =>
                setSearchDraft(event.target.value)
              }
              maxLength="100"
              placeholder="Name, code, or description"
            />
          </label>

          <button type="submit" className="primary-button">
            Search
          </button>
        </form>

        <div className="contacts-filters">
          <label className="form-field">
            <span>Segment type</span>
            <select
              value={type}
              onChange={(event) => {
                setType(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All types</option>
              <option value="static">Static</option>
              <option value="dynamic">Dynamic</option>
            </select>
          </label>

          <label className="form-field">
            <span>Segment status</span>
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All statuses</option>
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
          <p className="state-message">Loading segments...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && segments.length === 0 && (
          <div className="empty-state">
            <h3>No segments found</h3>
            <p>
              Create the first segment or change the current
              filters.
            </p>
          </div>
        )}

        {!isLoading && !error && segments.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table segments-table">
                <thead>
                  <tr>
                    <th>Code</th>
                    <th>Segment</th>
                    <th>Type</th>
                    <th>Membership rules</th>
                    <th>Members</th>
                    <th>Status</th>
                    {(canUpdate ||
                      canDelete ||
                      canManageMembers) && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {segments.map((segment) => {
                    const rules = Object.entries(
                      segment.criteria ?? {},
                    )

                    return (
                      <tr key={segment.id}>
                        <td>
                          <span className="code-pill">
                            {segment.code}
                          </span>
                        </td>

                        <td>
                          <strong>{segment.name}</strong>
                          <span className="table-secondary">
                            {segment.description
                              || 'No description'}
                          </span>
                        </td>

                        <td>
                          <span
                            className={`segment-type-pill ${segment.type}`}
                          >
                            {segment.type}
                          </span>
                        </td>

                        <td>
                          {segment.type === 'static' ? (
                            <span className="table-secondary">
                              Members selected manually
                            </span>
                          ) : (
                            <div className="segment-rule-list">
                              {rules.map(([key, value]) => (
                                <span key={key}>
                                  <strong>{ruleLabel(key)}:</strong>{' '}
                                  {formatRuleValue(
                                    key,
                                    value,
                                    areas,
                                  )}
                                </span>
                              ))}
                            </div>
                          )}
                        </td>

                        <td>
                          <strong>{segment.member_count ?? 0}</strong>
                          <span className="table-secondary">
                            {segment.type === 'dynamic'
                              ? 'matched automatically'
                              : 'selected contacts'}
                          </span>
                        </td>

                        <td>
                          <span
                            className={`status-pill ${segment.status}`}
                          >
                            {segment.status}
                          </span>
                        </td>

                        {(canUpdate ||
                          canDelete ||
                          canManageMembers) && (
                          <td>
                            <div className="table-actions">
                              {segment.type === 'static'
                                && canManageMembers && (
                                  <button
                                    type="button"
                                    className="text-button"
                                    disabled={
                                      loadingMembersId === segment.id
                                    }
                                    onClick={() =>
                                      openMemberManager(segment)
                                    }
                                  >
                                    {loadingMembersId === segment.id
                                      ? 'Loading...'
                                      : 'Members'}
                                  </button>
                                )}

                              {canUpdate && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(segment)
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
                                    setSegmentToDelete(segment)
                                  }
                                >
                                  Delete
                                </button>
                              )}
                            </div>
                          </td>
                        )}
                      </tr>
                    )
                  })}
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
                    pagination.current_page
                    === pagination.last_page
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

      {isFormOpen && (
        <SegmentForm
          segment={selectedSegment}
          areas={areas}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {memberSegment && (
        <SegmentMembersForm
          segment={memberSegment}
          contacts={availableContacts}
          members={currentMembers}
          onSubmit={handleMemberSync}
          onCancel={closeMemberManager}
        />
      )}

      {segmentToDelete && (
        <ConfirmDialog
          title="Delete segment?"
          message={`Delete ${segmentToDelete.name}? Contact records will remain, but this segment and its membership will be removed.`}
          confirmLabel="Delete segment"
          onConfirm={handleDelete}
          onCancel={() => setSegmentToDelete(null)}
        />
      )}
    </section>
  )
}

export default SegmentsPage