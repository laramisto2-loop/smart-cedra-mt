import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import IncidentAssignmentForm from './IncidentAssignmentForm.jsx'
import IncidentDetails from './IncidentDetails.jsx'
import IncidentForm from './IncidentForm.jsx'
import IncidentReviewForm from './IncidentReviewForm.jsx'
import { listAreas } from '../services/areas.js'
import {
  assignIncident,
  createIncident,
  deleteIncident,
  getIncident,
  listIncidents,
  reviewIncident,
  updateIncident,
} from '../services/incidents.js'
import { listPollingCenters } from '../services/pollingCenters.js'
import { listPollingStations } from '../services/pollingStations.js'
import {
  listCampaignTasks,
  listTaskAssignees,
} from '../services/campaignTasks.js'

const categoryLabels = {
  general: 'General',
  access: 'Access',
  safety: 'Safety',
  medical: 'Medical',
  equipment: 'Equipment',
  logistics: 'Logistics',
  conduct: 'Conduct',
  other: 'Other',
}

const severityLabels = {
  low: 'Low',
  medium: 'Medium',
  high: 'High',
  critical: 'Critical',
}

const statusLabels = {
  submitted: 'Submitted',
  in_review: 'In review',
  resolved: 'Resolved',
  dismissed: 'Dismissed',
}

async function loadEveryPage(loadPage) {
  const firstResponse = await loadPage(1)
  const records = [...(firstResponse.data ?? [])]
  const lastPage = Math.min(firstResponse.meta?.last_page ?? 1, 20)

  for (let page = 2; page <= lastPage; page += 1) {
    const response = await loadPage(page)
    records.push(...(response.data ?? []))
  }

  return records
}

function formatDateTime(value) {
  if (!value) return 'Not recorded'

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function locationLabel(incident) {
  return [
    incident.area?.name_en,
    incident.polling_center?.name_en,
    incident.polling_station
      ? `Station ${incident.polling_station.station_number}`
      : null,
  ]
    .filter(Boolean)
    .join(' — ') || 'No location'
}

function IncidentsPage({ user }) {
  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('incidents.create')
  const canReviewAll = permissions.includes('incidents.review')
  const canAssignAny = permissions.includes('incidents.assign')
  const canEditAny = permissions.includes('incidents.update')

  const [incidents, setIncidents] = useState([])
  const [areas, setAreas] = useState([])
  const [pollingCenters, setPollingCenters] = useState([])
  const [pollingStations, setPollingStations] = useState([])
  const [tasks, setTasks] = useState([])
  const [assignees, setAssignees] = useState([])

  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [severity, setSeverity] = useState('')
  const [status, setStatus] = useState('')
  const [areaId, setAreaId] = useState('')
  const [assigneeId, setAssigneeId] = useState('')
  const [scope, setScope] = useState(canReviewAll ? '' : 'mine')

  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [lookupWarning, setLookupWarning] = useState('')
  const [reloadKey, setReloadKey] = useState(0)
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [editingIncident, setEditingIncident] = useState(null)
  const [detailIncident, setDetailIncident] = useState(null)
  const [assignmentIncident, setAssignmentIncident] = useState(null)
  const [reviewingIncident, setReviewingIncident] = useState(null)
  const [incidentToDelete, setIncidentToDelete] = useState(null)

  useEffect(() => {
    let cancelled = false

    async function loadLookups() {
      try {
        const needsFormLookups = canCreate || canEditAny
        const [loadedAreas, loadedCenters, loadedStations, loadedTasks, loadedAssignees] = await Promise.all([
          needsFormLookups
            ? loadEveryPage((lookupPage) => listAreas({ page: lookupPage }))
            : Promise.resolve([]),
          needsFormLookups
            ? loadEveryPage((lookupPage) => listPollingCenters({ page: lookupPage }))
            : Promise.resolve([]),
          needsFormLookups
            ? loadEveryPage((lookupPage) => listPollingStations({ page: lookupPage }))
            : Promise.resolve([]),
          needsFormLookups
            ? loadEveryPage((lookupPage) => listCampaignTasks({
                page: lookupPage,
                perPage: 100,
                mine: !canReviewAll,
              }))
            : Promise.resolve([]),
          canAssignAny ? listTaskAssignees() : Promise.resolve([]),
        ])

        if (!cancelled) {
          setAreas(loadedAreas)
          setPollingCenters(loadedCenters)
          setPollingStations(loadedStations)
          setTasks(loadedTasks)
          setAssignees(loadedAssignees)
          setLookupWarning('')
        }
      } catch {
        if (!cancelled) {
          setLookupWarning(
            'Some form options could not be loaded. Refresh before reporting, editing, or assigning an incident.',
          )
        }
      }
    }

    loadLookups()
    return () => { cancelled = true }
  }, [canAssignAny, canCreate, canEditAny, canReviewAll])

  useEffect(() => {
    let cancelled = false

    async function loadRecords() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listIncidents({
          page,
          search,
          category,
          severity,
          status,
          areaId,
          assignedToUserId: assigneeId,
          mine: scope === 'mine',
        })

        if (!cancelled) {
          setIncidents(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(
            requestError.response?.status === 403
              ? 'You do not have permission to view incidents.'
              : 'Incidents could not be loaded. Please try again.',
          )
        }
      } finally {
        if (!cancelled) setIsLoading(false)
      }
    }

    loadRecords()
    return () => { cancelled = true }
  }, [areaId, assigneeId, category, page, reloadKey, scope, search, severity, status])

  function refreshIncidents() {
    setReloadKey((current) => current + 1)
  }

  function applySearch(event) {
    event.preventDefault()
    setSearch(searchDraft.trim())
    setPage(1)
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setCategory('')
    setSeverity('')
    setStatus('')
    setAreaId('')
    setAssigneeId('')
    setScope(canReviewAll ? '' : 'mine')
    setPage(1)
  }

  async function loadFullIncident(incident, setter) {
    setError('')
    try {
      setter(await getIncident(incident.id))
    } catch {
      setError('The incident details could not be loaded.')
    }
  }

  async function refreshDetail() {
    if (!detailIncident) return
    setDetailIncident(await getIncident(detailIncident.id))
    refreshIncidents()
  }

  async function saveIncident(payload) {
    if (editingIncident) {
      await updateIncident(editingIncident.id, payload)
    } else {
      await createIncident(payload)
    }

    setIsFormOpen(false)
    setEditingIncident(null)
    setPage(1)
    refreshIncidents()
  }

  async function saveAssignment(assignedToUserId) {
    await assignIncident(
      assignmentIncident.id,
      assignedToUserId,
      assignmentIncident.sync_version,
    )
    setAssignmentIncident(null)
    refreshIncidents()
  }

  async function saveReview(nextStatus, resolutionNotes) {
    await reviewIncident(
      reviewingIncident.id,
      nextStatus,
      resolutionNotes,
      reviewingIncident.sync_version,
    )
    setReviewingIncident(null)
    refreshIncidents()
  }

  async function confirmDelete() {
    await deleteIncident(incidentToDelete.id)
    setIncidentToDelete(null)
    refreshIncidents()
  }

  return (
    <section className="incidents-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">Field operations</p>
          <h2>Incidents</h2>
          <p className="page-description">
            Report, triage, and resolve tenant-isolated field incidents for {user.tenant.name}.
          </p>
        </div>
        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={() => {
              setEditingIncident(null)
              setIsFormOpen(true)
            }}
          >
            Report incident
          </button>
        )}
      </div>

      {lookupWarning && (
        <div className="form-message error-message" role="alert">
          {lookupWarning}
        </div>
      )}

      <article className="content-card contacts-filter-card">
        <form className="contacts-search" onSubmit={applySearch}>
          <label className="form-field">
            <span>Search incidents</span>
            <input
              type="search"
              value={searchDraft}
              onChange={(event) => setSearchDraft(event.target.value)}
              maxLength="100"
              placeholder="Reference, title, or description"
            />
          </label>
          <button type="submit" className="primary-button">Search</button>
        </form>

        <div className="contacts-filters incident-filters">
          <label className="form-field">
            <span>Category</span>
            <select value={category} onChange={(event) => { setCategory(event.target.value); setPage(1) }}>
              <option value="">All categories</option>
              {Object.entries(categoryLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Severity</span>
            <select value={severity} onChange={(event) => { setSeverity(event.target.value); setPage(1) }}>
              <option value="">All severities</option>
              {Object.entries(severityLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Status</span>
            <select value={status} onChange={(event) => { setStatus(event.target.value); setPage(1) }}>
              <option value="">All statuses</option>
              {Object.entries(statusLabels).map(([value, label]) => (
                <option key={value} value={value}>{label}</option>
              ))}
            </select>
          </label>

          <label className="form-field">
            <span>Area</span>
            <select value={areaId} onChange={(event) => { setAreaId(event.target.value); setPage(1) }}>
              <option value="">All areas</option>
              {areas.map((area) => (
                <option key={area.id} value={area.id}>{area.name_en}</option>
              ))}
            </select>
          </label>

          {canReviewAll && (
            <label className="form-field">
              <span>Incident ownership</span>
              <select value={scope} onChange={(event) => { setScope(event.target.value); setPage(1) }}>
                <option value="">All accessible incidents</option>
                <option value="mine">Reported or assigned to me</option>
              </select>
            </label>
          )}

          {canAssignAny && (
            <label className="form-field">
              <span>Assignee</span>
              <select value={assigneeId} onChange={(event) => { setAssigneeId(event.target.value); setPage(1) }}>
                <option value="">All assignees</option>
                {assignees.map((assignee) => (
                  <option key={assignee.id} value={assignee.id}>{assignee.name}</option>
                ))}
              </select>
            </label>
          )}

          <button type="button" className="secondary-button" onClick={clearFilters}>
            Clear filters
          </button>
        </div>
      </article>

      <article className="content-card contacts-card">
        {isLoading && <p className="state-message">Loading incidents...</p>}
        {!isLoading && error && <div className="error-message" role="alert">{error}</div>}
        {!isLoading && !error && incidents.length === 0 && (
          <div className="empty-state">
            <h3>No incidents found</h3>
            <p>Report the first incident or change the current filters.</p>
          </div>
        )}

        {!isLoading && !error && incidents.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table incidents-table">
                <thead>
                  <tr>
                    <th>Incident</th>
                    <th>Category</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Occurred</th>
                    <th>People</th>
                    <th>Location</th>
                    <th>Evidence</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {incidents.map((incident) => (
                    <tr key={incident.id}>
                      <td>
                        <strong>{incident.title}</strong>
                        <span className="table-secondary">{incident.reference_code}</span>
                      </td>
                      <td><span className="incident-category">{categoryLabels[incident.category] ?? incident.category}</span></td>
                      <td><span className={`incident-severity ${incident.severity}`}>{severityLabels[incident.severity] ?? incident.severity}</span></td>
                      <td><span className={`status-pill ${incident.status}`}>{statusLabels[incident.status] ?? incident.status}</span></td>
                      <td>{formatDateTime(incident.occurred_at)}</td>
                      <td>
                        <strong>{incident.reporter?.name ?? 'Unknown reporter'}</strong>
                        <span className="table-secondary">Assigned: {incident.assignee?.name ?? 'Unassigned'}</span>
                      </td>
                      <td>{locationLabel(incident)}</td>
                      <td>{incident.attachments_count} attachment{incident.attachments_count === 1 ? '' : 's'}</td>
                      <td>
                        <div className="table-actions">
                          <button type="button" className="text-button" onClick={() => loadFullIncident(incident, setDetailIncident)}>Details</button>
                          {incident.actions.update && !incident.is_terminal && (
                            <button type="button" className="text-button" onClick={() => loadFullIncident(incident, (loaded) => { setEditingIncident(loaded); setIsFormOpen(true) })}>Edit</button>
                          )}
                          {incident.actions.assign && !incident.is_terminal && (
                            <button type="button" className="text-button" onClick={() => loadFullIncident(incident, setAssignmentIncident)}>Assign</button>
                          )}
                          {incident.actions.review && (
                            <button type="button" className="text-button" onClick={() => loadFullIncident(incident, setReviewingIncident)}>Review</button>
                          )}
                          {incident.actions.delete && (
                            <button type="button" className="text-button danger" onClick={() => setIncidentToDelete(incident)}>Delete</button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {pagination && pagination.last_page > 1 && (
              <div className="pagination">
                <button type="button" className="secondary-button" disabled={pagination.current_page === 1} onClick={() => setPage((current) => current - 1)}>Previous</button>
                <span>Page {pagination.current_page} of {pagination.last_page}</span>
                <button type="button" className="secondary-button" disabled={pagination.current_page === pagination.last_page} onClick={() => setPage((current) => current + 1)}>Next</button>
              </div>
            )}
          </>
        )}
      </article>

      {isFormOpen && (
        <IncidentForm
          incident={editingIncident}
          areas={areas}
          pollingCenters={pollingCenters}
          pollingStations={pollingStations}
          tasks={tasks}
          onSubmit={saveIncident}
          onCancel={() => { setIsFormOpen(false); setEditingIncident(null) }}
        />
      )}

      {detailIncident && (
        <IncidentDetails
          incident={detailIncident}
          onRefresh={refreshDetail}
          onClose={() => setDetailIncident(null)}
        />
      )}

      {assignmentIncident && (
        <IncidentAssignmentForm
          incident={assignmentIncident}
          assignees={assignees}
          onSubmit={saveAssignment}
          onCancel={() => setAssignmentIncident(null)}
        />
      )}

      {reviewingIncident && (
        <IncidentReviewForm
          incident={reviewingIncident}
          onSubmit={saveReview}
          onCancel={() => setReviewingIncident(null)}
        />
      )}

      {incidentToDelete && (
        <ConfirmDialog
          title="Delete incident?"
          message={`Delete ${incidentToDelete.reference_code}? Its private attachments will also be removed.`}
          onConfirm={confirmDelete}
          onCancel={() => setIncidentToDelete(null)}
          errorMessage="The incident could not be deleted."
        />
      )}
    </section>
  )
}

export default IncidentsPage
