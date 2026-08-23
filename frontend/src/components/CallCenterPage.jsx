import { useState } from 'react'
import CallAssignmentsPanel from './CallAssignmentsPanel.jsx'
import CallQueuesPanel from './CallQueuesPanel.jsx'
import CallScriptsPanel from './CallScriptsPanel.jsx'

function CallCenterPage({ user }) {
  const permissions = user.permissions ?? []

  const canViewAssignments = permissions.includes(
    'calls.assignments.view',
  )
  const canViewQueues = permissions.includes(
    'calls.queues.view',
  )
  const canViewScripts = permissions.includes(
    'calls.scripts.view',
  )

  const firstAvailableSection = canViewAssignments
    ? 'assignments'
    : canViewQueues
      ? 'queues'
      : 'scripts'

  const [activeSection, setActiveSection] = useState(
    firstAvailableSection,
  )

  return (
    <section className="messaging-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">
            Campaign call operations
          </p>
          <h2>Call Center</h2>
          <p className="page-description">
            Prepare call scripts, organize contact queues,
            distribute assignments, and record immutable call
            outcomes.
          </p>
        </div>
      </div>

      <div
        className="messaging-tabs"
        role="tablist"
        aria-label="Call center sections"
      >
        {canViewAssignments && (
          <button
            type="button"
            role="tab"
            aria-selected={
              activeSection === 'assignments'
            }
            className={
              activeSection === 'assignments'
                ? 'active'
                : ''
            }
            onClick={() =>
              setActiveSection('assignments')
            }
          >
            Call assignments
          </button>
        )}

        {canViewQueues && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'queues'}
            className={
              activeSection === 'queues' ? 'active' : ''
            }
            onClick={() => setActiveSection('queues')}
          >
            Call queues
          </button>
        )}

        {canViewScripts && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'scripts'}
            className={
              activeSection === 'scripts' ? 'active' : ''
            }
            onClick={() => setActiveSection('scripts')}
          >
            Call scripts
          </button>
        )}
      </div>

      {activeSection === 'assignments'
        && canViewAssignments && (
          <CallAssignmentsPanel user={user} />
        )}

      {activeSection === 'queues' && canViewQueues && (
        <CallQueuesPanel user={user} />
      )}

      {activeSection === 'scripts'
        && canViewScripts && (
          <CallScriptsPanel user={user} />
        )}

      {!canViewAssignments
        && !canViewQueues
        && !canViewScripts && (
          <article className="content-card">
            <div className="empty-state">
              <h3>Call Center is restricted</h3>
              <p>
                Your role does not have permission to access
                call scripts, queues, or assignments.
              </p>
            </div>
          </article>
        )}
    </section>
  )
}

export default CallCenterPage