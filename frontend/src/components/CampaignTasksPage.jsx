import { useEffect, useState } from 'react'
import CampaignTaskAssignmentForm from './CampaignTaskAssignmentForm.jsx'
import CampaignTaskCompletionForm from './CampaignTaskCompletionForm.jsx'
import CampaignTaskForm from './CampaignTaskForm.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import { listAreas } from '../services/areas.js'
import { listContacts } from '../services/contacts.js'
import {
  assignCampaignTask,
  completeCampaignTask,
  createCampaignTask,
  deleteCampaignTask,
  listCampaignTasks,
  listTaskAssignees,
  updateCampaignTask,
} from '../services/campaignTasks.js'

const typeLabels = {
  general: 'General',
  follow_up: 'Follow-up',
  phone_call: 'Phone call',
  message: 'Message',
  field_visit: 'Field visit',
  data_entry: 'Data entry',
}

const priorityLabels = {
  low: 'Low',
  normal: 'Normal',
  high: 'High',
  urgent: 'Urgent',
}

const statusLabels = {
  pending: 'Pending',
  in_progress: 'In progress',
  completed: 'Completed',
  cancelled: 'Cancelled',
}

async function loadEveryPage(loadPage) {
  const firstResponse = await loadPage(1)
  const records = [...(firstResponse.data ?? [])]
  const lastPage = Math.min(
    firstResponse.meta?.last_page ?? 1,
    20,
  )

  for (let page = 2; page <= lastPage; page += 1) {
    const response = await loadPage(page)
    records.push(...(response.data ?? []))
  }

  return records
}

function formatDateTime(value) {
  if (!value) {
    return 'No due date'
  }

  return new Intl.DateTimeFormat('en-LB', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function CampaignTasksPage({ user }) {
  const permissions = user.permissions ?? []
  const canCreate = permissions.includes('tasks.create')
  const canUpdate = permissions.includes('tasks.update')
  const canAssign = permissions.includes('tasks.assign')
  const canComplete = permissions.includes('tasks.complete')
  const canDelete = permissions.includes('tasks.delete')

  const hasActions =
    canUpdate || canAssign || canComplete || canDelete

  const [tasks, setTasks] = useState([])
  const [contacts, setContacts] = useState([])
  const [areas, setAreas] = useState([])
  const [assignees, setAssignees] = useState([])

  const [page, setPage] = useState(1)
  const [pagination, setPagination] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [priority, setPriority] = useState('')
  const [status, setStatus] = useState('')
  const [assigneeId, setAssigneeId] = useState('')
  const [scope, setScope] = useState(
    canUpdate ? '' : 'mine',
  )
  const [dueFilter, setDueFilter] = useState('')

  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [lookupWarning, setLookupWarning] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  const [isFormOpen, setIsFormOpen] = useState(false)
  const [selectedTask, setSelectedTask] = useState(null)
  const [assignmentTask, setAssignmentTask] = useState(null)
  const [completionTask, setCompletionTask] = useState(null)
  const [taskToCancel, setTaskToCancel] = useState(null)
  const [taskToDelete, setTaskToDelete] = useState(null)
  const [startingTaskId, setStartingTaskId] = useState(null)

  useEffect(() => {
    let cancelled = false

    async function loadLookups() {
      try {
        const needsTaskOptions = canCreate || canUpdate

        const [
          loadedContacts,
          loadedAreas,
          loadedAssignees,
        ] = await Promise.all([
          needsTaskOptions
            ? loadEveryPage((contactPage) =>
                listContacts({
                  page: contactPage,
                  perPage: 100,
                }),
              )
            : Promise.resolve([]),
          needsTaskOptions
            ? loadEveryPage((areaPage) =>
                listAreas({
                  page: areaPage,
                }),
              )
            : Promise.resolve([]),
          canAssign
            ? listTaskAssignees()
            : Promise.resolve([]),
        ])

        if (!cancelled) {
          setContacts(loadedContacts)
          setAreas(loadedAreas)
          setAssignees(loadedAssignees)
          setLookupWarning('')
        }
      } catch {
        if (!cancelled) {
          setLookupWarning(
            'Some task form options could not be loaded. Refresh the page before creating or assigning tasks.',
          )
        }
      }
    }

    loadLookups()

    return () => {
      cancelled = true
    }
  }, [canAssign, canCreate, canUpdate])

  useEffect(() => {
    let cancelled = false

    async function loadTasks() {
      setIsLoading(true)
      setError('')

      try {
        const response = await listCampaignTasks({
          page,
          search,
          type,
          priority,
          status,
          assignedToUserId: assigneeId,
          mine: scope === 'mine',
          overdue: dueFilter === 'overdue',
        })

        if (!cancelled) {
          setTasks(response.data ?? [])
          setPagination(response.meta ?? null)
        }
      } catch (requestError) {
        if (!cancelled) {
          setError(
            requestError.response?.status === 403
              ? 'You do not have permission to view tasks.'
              : 'Tasks could not be loaded. Please try again.',
          )
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    loadTasks()

    return () => {
      cancelled = true
    }
  }, [
    assigneeId,
    dueFilter,
    page,
    priority,
    reloadKey,
    scope,
    search,
    status,
    type,
  ])

  function refreshTasks() {
    setReloadKey((current) => current + 1)
  }

  function applySearch(event) {
    event.preventDefault()
    setPage(1)
    setSearch(searchDraft.trim())
  }

  function clearFilters() {
    setSearchDraft('')
    setSearch('')
    setType('')
    setPriority('')
    setStatus('')
    setAssigneeId('')
    setScope(canUpdate ? '' : 'mine')
    setDueFilter('')
    setPage(1)
  }

  function openCreateForm() {
    setSelectedTask(null)
    setIsFormOpen(true)
  }

  function openEditForm(task) {
    setSelectedTask(task)
    setIsFormOpen(true)
  }

  function closeForm() {
    setSelectedTask(null)
    setIsFormOpen(false)
  }

  async function handleSave(payload) {
    if (selectedTask) {
      await updateCampaignTask(selectedTask.id, payload)
    } else {
      await createCampaignTask(payload)
    }

    closeForm()
    refreshTasks()
  }

  async function handleAssignment(assignedToUserId) {
    await assignCampaignTask(
      assignmentTask.id,
      assignedToUserId,
    )

    setAssignmentTask(null)
    refreshTasks()
  }

  async function handleCompletion(completionNotes) {
    await completeCampaignTask(
      completionTask.id,
      completionNotes,
    )

    setCompletionTask(null)
    refreshTasks()
  }

  async function handleStart(task) {
    setStartingTaskId(task.id)
    setError('')

    try {
      await updateCampaignTask(task.id, {
        status: 'in_progress',
      })

      refreshTasks()
    } catch {
      setError(
        'The task could not be started. Please try again.',
      )
    } finally {
      setStartingTaskId(null)
    }
  }

  async function handleCancel() {
    await updateCampaignTask(taskToCancel.id, {
      status: 'cancelled',
    })

    setTaskToCancel(null)
    refreshTasks()
  }

  async function handleDelete() {
    await deleteCampaignTask(taskToDelete.id)
    setTaskToDelete(null)

    if (tasks.length === 1 && page > 1) {
      setPage((current) => current - 1)
    } else {
      refreshTasks()
    }
  }

  return (
    <section className="tasks-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">Campaign operations</p>
          <h2>Tasks</h2>
          <p className="page-description">
            Create, assign, track, and complete campaign work
            belonging to {user.tenant.name}.
          </p>
        </div>

        {canCreate && (
          <button
            type="button"
            className="primary-button"
            onClick={openCreateForm}
          >
            Add task
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
            <span>Search tasks</span>
            <input
              type="search"
              value={searchDraft}
              onChange={(event) =>
                setSearchDraft(event.target.value)
              }
              maxLength="100"
              placeholder="Task title or description"
            />
          </label>

          <button type="submit" className="primary-button">
            Search
          </button>
        </form>

        <div className="contacts-filters task-filters">
          <label className="form-field">
            <span>Task type</span>
            <select
              value={type}
              onChange={(event) => {
                setType(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All types</option>

              {Object.entries(typeLabels).map(
                ([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ),
              )}
            </select>
          </label>

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

              {Object.entries(priorityLabels).map(
                ([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ),
              )}
            </select>
          </label>

          <label className="form-field">
            <span>Task status</span>
            <select
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All statuses</option>

              {Object.entries(statusLabels).map(
                ([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ),
              )}
            </select>
          </label>

          <label className="form-field">
            <span>Task ownership</span>
            <select
              value={scope}
              onChange={(event) => {
                setScope(event.target.value)
                setPage(1)
              }}
            >
              {canUpdate && (
                <option value="">All accessible tasks</option>
              )}
              <option value="mine">My assigned tasks</option>
            </select>
          </label>

          <label className="form-field">
            <span>Due state</span>
            <select
              value={dueFilter}
              onChange={(event) => {
                setDueFilter(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All due dates</option>
              <option value="overdue">Overdue only</option>
            </select>
          </label>

          {canAssign && (
            <label className="form-field">
              <span>Assignee</span>
              <select
                value={assigneeId}
                onChange={(event) => {
                  setAssigneeId(event.target.value)
                  setPage(1)
                }}
              >
                <option value="">All assignees</option>

                {assignees.map((assignee) => (
                  <option
                    key={assignee.id}
                    value={assignee.id}
                  >
                    {assignee.name}
                  </option>
                ))}
              </select>
            </label>
          )}

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
          <p className="state-message">Loading tasks...</p>
        )}

        {!isLoading && error && (
          <div className="error-message" role="alert">
            {error}
          </div>
        )}

        {!isLoading && !error && tasks.length === 0 && (
          <div className="empty-state">
            <h3>No tasks found</h3>
            <p>
              Create the first task or change the current
              filters.
            </p>
          </div>
        )}

        {!isLoading && !error && tasks.length > 0 && (
          <>
            <div className="table-wrapper">
              <table className="geography-table tasks-table">
                <thead>
                  <tr>
                    <th>Task</th>
                    <th>Type</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Due</th>
                    <th>Assignee</th>
                    <th>Context</th>
                    {hasActions && <th>Actions</th>}
                  </tr>
                </thead>

                <tbody>
                  {tasks.map((task) => (
                    <tr
                      key={task.id}
                      className={task.is_overdue ? 'overdue-row' : ''}
                    >
                      <td>
                        <strong>{task.title}</strong>
                        <span className="table-secondary">
                          {task.description || 'No description'}
                        </span>
                      </td>

                      <td>
                        <span className="task-type-pill">
                          {typeLabels[task.type] ?? task.type}
                        </span>
                      </td>

                      <td>
                        <span
                          className={`task-priority-pill ${task.priority}`}
                        >
                          {priorityLabels[task.priority]
                            ?? task.priority}
                        </span>
                      </td>

                      <td>
                        <span
                          className={`status-pill ${task.status}`}
                        >
                          {statusLabels[task.status]
                            ?? task.status}
                        </span>
                      </td>

                      <td>
                        <span
                          className={
                            task.is_overdue
                              ? 'task-overdue'
                              : ''
                          }
                        >
                          {formatDateTime(task.due_at)}
                        </span>

                        {task.is_overdue && (
                          <span className="table-secondary danger-text">
                            Overdue
                          </span>
                        )}
                      </td>

                      <td>
                        <strong>
                          {task.assignee?.name ?? 'Unassigned'}
                        </strong>
                        <span className="table-secondary">
                          {task.assignee?.email ?? 'No team member'}
                        </span>
                      </td>

                      <td>
                        {task.contact && (
                          <span>
                            {task.contact.full_name}
                          </span>
                        )}

                        {task.area && (
                          <span className="table-secondary">
                            {task.area.name_en}
                          </span>
                        )}

                        {!task.contact && !task.area && (
                          <span className="table-secondary">
                            General campaign task
                          </span>
                        )}
                      </td>

                      {hasActions && (
                        <td>
                          <div className="table-actions">
                            {canUpdate
                              && task.status === 'pending' && (
                                <button
                                  type="button"
                                  className="text-button"
                                  disabled={
                                    startingTaskId === task.id
                                  }
                                  onClick={() => handleStart(task)}
                                >
                                  {startingTaskId === task.id
                                    ? 'Starting...'
                                    : 'Start'}
                                </button>
                              )}

                            {canComplete
                              && ![
                                'completed',
                                'cancelled',
                              ].includes(task.status) && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    setCompletionTask(task)
                                  }
                                >
                                  Complete
                                </button>
                              )}

                            {canAssign
                              && ![
                                'completed',
                                'cancelled',
                              ].includes(task.status) && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    setAssignmentTask(task)
                                  }
                                >
                                  Assign
                                </button>
                              )}

                            {canUpdate
                              && task.status !== 'completed' && (
                                <button
                                  type="button"
                                  className="text-button"
                                  onClick={() =>
                                    openEditForm(task)
                                  }
                                >
                                  Edit
                                </button>
                              )}

                            {canUpdate
                              && ![
                                'completed',
                                'cancelled',
                              ].includes(task.status) && (
                                <button
                                  type="button"
                                  className="text-button danger"
                                  onClick={() =>
                                    setTaskToCancel(task)
                                  }
                                >
                                  Cancel
                                </button>
                              )}

                            {canDelete && (
                              <button
                                type="button"
                                className="text-button danger"
                                onClick={() =>
                                  setTaskToDelete(task)
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
        <CampaignTaskForm
          task={selectedTask}
          contacts={contacts}
          areas={areas}
          assignees={assignees}
          canAssign={canAssign}
          onSubmit={handleSave}
          onCancel={closeForm}
        />
      )}

      {assignmentTask && (
        <CampaignTaskAssignmentForm
          task={assignmentTask}
          assignees={assignees}
          onSubmit={handleAssignment}
          onCancel={() => setAssignmentTask(null)}
        />
      )}

      {completionTask && (
        <CampaignTaskCompletionForm
          task={completionTask}
          onSubmit={handleCompletion}
          onCancel={() => setCompletionTask(null)}
        />
      )}

      {taskToCancel && (
        <ConfirmDialog
          title="Cancel task?"
          message={`Cancel “${taskToCancel.title}”? It will remain in the task history but cannot be completed unless it is reactivated.`}
          confirmLabel="Cancel task"
          confirmingLabel="Cancelling..."
          errorMessage="The task could not be cancelled. Please try again."
          onConfirm={handleCancel}
          onCancel={() => setTaskToCancel(null)}
        />
      )}

      {taskToDelete && (
        <ConfirmDialog
          title="Delete task?"
          message={`Permanently delete “${taskToDelete.title}”? This action cannot be undone.`}
          confirmLabel="Delete task"
          onConfirm={handleDelete}
          onCancel={() => setTaskToDelete(null)}
        />
      )}
    </section>
  )
}

export default CampaignTasksPage