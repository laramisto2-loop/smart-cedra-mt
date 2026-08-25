import { useState } from 'react'
import CampaignTasksPage from './CampaignTasksPage.jsx'
import ContactsPage from './ContactsPage.jsx'
import GeographyPage from './GeographyPage.jsx'
import IncidentsPage from './IncidentsPage.jsx'
import MessagingPage from './MessagingPage.jsx'
import SegmentsPage from './SegmentsPage.jsx'
import ConfirmDialog from './ConfirmDialog.jsx'
import CallCenterPage from './CallCenterPage.jsx'
import ResultsPage from './ResultsPage.jsx'
import UserRoleManagementPage from './UserRoleManagementPage.jsx'
import { countQueuedIncidents } from '../services/incidentQueue.js'
import { countQueuedTurnoutSnapshots } from '../services/turnoutQueue.js'
import '../App.css'

const navigationItems = [
  {
    label: 'Dashboard',
    icon: '▦',
    enabled: true,
  },
  {
    label: 'Users',
    icon: '👥',
    permission: 'users.manage',
    enabled: true,
  },
  {
    label: 'Geography',
    icon: '📍',
    permission: 'geography.view',
    enabled: true,
  },
  {
    label: 'Contacts',
    icon: '📇',
    permission: 'contacts.view',
    enabled: true,
  },
  {
    label: 'Segments',
    icon: '◉',
    permission: 'segments.view',
    enabled: true,
  },
  {
    label: 'Tasks',
    icon: '✓',
    permission: 'tasks.view',
    enabled: true,
  },
  {
    label: 'Incidents',
    icon: '⚠',
    permission: 'incidents.view',
    enabled: true,
  },
  {
    label: 'Results',
    icon: '▤',
    permissions: [
      'turnout.view',
      'results.contests.view',
      'results.tallies.view',
      'results.analytics.view',
    ],
    enabled: true,
  },
  {
    label: 'Messaging',
    icon: '✉',
    permission: 'messages.view',
    enabled: true,
  },
  {
    label: 'Call Center',
    icon: '☎',
    permission: 'calls.assignments.view',
    enabled: true,
  },
  {
    label: 'Settings',
    icon: '⚙',
    enabled: false,
  },
]

const deliveryItems = [
  'Consent-aware contact management',
  'Static and dynamic contact segmentation',
  'Consent-aware interaction timelines',
  'Task creation and assignment workflows',
  'Validated contact CSV import and export',
  'Offline-safe incident reporting and evidence',
  'Offline-safe aggregate turnout reporting',
  'Approved WhatsApp and SMS template workflows',
  'Consent-aware outbound message processing',
  'Quiet-hours scheduling and delivery history',
  'Tenant-safe call scripts and campaign queues',
  'Agent assignment and immutable call history',
]

function Dashboard({ user, onLogout }) {
  const [activePage, setActivePage] = useState('Dashboard')
  const [logoutConfirmation, setLogoutConfirmation] =
  useState(null)
  const permissions = user.permissions ?? []

  const visibleNavigationItems = navigationItems.filter((item) => {
    if (item.permissions) {
      return item.permissions.some((permission) =>
        permissions.includes(permission),
      )
    }

    return !item.permission || permissions.includes(item.permission)
  })

  const capabilities = [
    {
      label: 'CRM contacts',
      permission: 'contacts.view',
      description: 'Profiles, consent, and data transfer',
    },
    {
      label: 'Contact segments',
      permission: 'segments.view',
      description: 'Manual and rule-based audiences',
    },
    {
      label: 'Interaction timeline',
      permission: 'interactions.view',
      description: 'Consent-aware communication history',
    },
    {
      label: 'Campaign tasks',
      permission: 'tasks.view',
      description: 'Assignments and completion workflows',
    },
    {
      label: 'Field incidents',
      permission: 'incidents.view',
      description: 'Reports, triage, and private evidence',
    },
    {
      label: 'Aggregate turnout',
      permission: 'turnout.view',
      description: 'Offline totals and time-series summaries',
    },
    {
      label: 'Campaign messaging',
      permission: 'messages.view',
      description:
        'Consent checks, templates, and delivery tracking',
    },
    {
      label: 'Call center',
      permission: 'calls.assignments.view',
      description:
        'Scripts, queues, assignments, and call outcomes',
    },
  ]

  const initials = user.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  const tenantStatus =
    user.tenant.status.charAt(0).toUpperCase() +
    user.tenant.status.slice(1)

  function selectPage(item) {
    if (item.enabled) {
      setActivePage(item.label)
    }
  }

  async function requestLogout() {
  let incidentCount = 0
  let turnoutCount = 0

  try {
    ;[incidentCount, turnoutCount] = await Promise.all([
      countQueuedIncidents(user),
      countQueuedTurnoutSnapshots(user),
    ])
  } catch {
    // Sign-out confirmation can still be displayed if
    // browser storage is temporarily unavailable.
  }

  setLogoutConfirmation({
    incidentCount,
    turnoutCount,
  })
}

const queuedOfflineItems = logoutConfirmation
  ? logoutConfirmation.incidentCount +
    logoutConfirmation.turnoutCount
  : 0

const logoutMessage =
  queuedOfflineItems > 0
    ? `You have ${queuedOfflineItems} offline ${
        queuedOfflineItems === 1 ? 'entry' : 'entries'
      } waiting to synchronize. ${
        queuedOfflineItems === 1 ? 'It will' : 'They will'
      } remain safely stored for this account after you sign out.`
    : 'Are you sure you want to sign out of ElectoFlow?'

  return (
    <div className="admin-layout">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-logo">EF</div>

          <div>
            <h1>ElectoFlow</h1>
            <p>Campaign Operations</p>
          </div>
        </div>

        <div className="tenant-card">
          <span className="tenant-label">Current tenant</span>
          <strong>{user.tenant.name}</strong>
          <span className="tenant-status">
            <span className="status-dot" />
            {tenantStatus}
          </span>
        </div>

        <nav
          className="navigation"
          aria-label="Main navigation"
        >
          {visibleNavigationItems.map((item) => (
            <button
              type="button"
              key={item.label}
              className={`navigation-item ${
                activePage === item.label ? 'active' : ''
              }`}
              onClick={() => selectPage(item)}
              aria-disabled={!item.enabled}
            >
              <span
                className="navigation-icon"
                aria-hidden="true"
              >
                {item.icon}
              </span>
              <span>{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="sidebar-footer">
          <p>Multi-tenant environment</p>
          <span>Tenant ID: {user.tenant.id}</span>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div>
            <p className="eyebrow">
              Tenant administration
            </p>
            <h2>{activePage}</h2>
          </div>

          <div className="user-profile">
            <div className="user-avatar">{initials}</div>

            <div className="user-details">
              <strong>{user.name}</strong>
              <span>{user.email}</span>
            </div>

            <button
              type="button"
              className="secondary-button"
              onClick={requestLogout}
            >
              Sign out
            </button>
          </div>
        </header>

        {activePage === 'Geography' && (
          <GeographyPage user={user} />
        )}

        {activePage === 'Contacts' && (
          <ContactsPage user={user} />
        )}

        {activePage === 'Segments' && (
          <SegmentsPage user={user} />
        )}

        {activePage === 'Tasks' && (
          <CampaignTasksPage user={user} />
        )}

        {activePage === 'Incidents' && (
          <IncidentsPage user={user} />
        )}

       {activePage === 'Results' && (
          <ResultsPage permissions={permissions} user={user} />
        )}

        {activePage === 'Users' && (
          <UserRoleManagementPage user={user} />
        )}

        {activePage === 'Messaging' && (
          <MessagingPage user={user} />
        )}

        {activePage === 'Call Center' && (
          <CallCenterPage user={user} />
        )}

        {activePage === 'Dashboard' && (
          <>
            <section className="welcome-panel">
              <div>
                <span className="panel-badge">
                  MT-6 Messaging + Call Center
                </span>
                <h3>Welcome to {user.tenant.name}</h3>
                <p>
                  Manage tenant-isolated contacts, audiences,
                  field operations, turnout reporting, and
                  consent-aware campaign communications from
                  one protected workspace.
                </p>
              </div>

              <div className="isolation-indicator">
                <span className="shield-icon">✓</span>
                <div>
                  <strong>Tenant isolation active</strong>
                  <span>
                    Protected by tenant-aware authorization
                  </span>
                </div>
              </div>
            </section>

            <section
              className="statistics-grid"
              aria-label="Available campaign capabilities"
            >
              {capabilities.map((capability) => {
                const available = permissions.includes(
                  capability.permission,
                )

                return (
                  <article
                    className="statistic-card"
                    key={capability.label}
                  >
                    <span>{capability.label}</span>
                    <strong>
                      {available ? 'Ready' : 'Restricted'}
                    </strong>
                    <p>{capability.description}</p>
                  </article>
                )
              })}
            </section>

            <section className="content-grid">
              <article className="content-card">
                <div className="card-heading">
                  <div>
                    <p className="eyebrow">
                      Tenant information
                    </p>
                    <h3>Campaign configuration</h3>
                  </div>
                </div>

                <dl className="details-list">
                  <div>
                    <dt>Tenant name</dt>
                    <dd>{user.tenant.name}</dd>
                  </div>

                  <div>
                    <dt>Tenant slug</dt>
                    <dd>{user.tenant.slug}</dd>
                  </div>

                  <div>
                    <dt>Timezone</dt>
                    <dd>Asia/Beirut</dd>
                  </div>

                  <div>
                    <dt>Status</dt>
                    <dd>
                      <span className="active-pill">
                        {tenantStatus}
                      </span>
                    </dd>
                  </div>
                </dl>
              </article>

              <article className="content-card">
                <div className="card-heading">
                  <div>
                    <p className="eyebrow">
                      Implementation progress
                    </p>
                    <h3>MT-6 delivery</h3>
                  </div>
                </div>

                <ul className="progress-list">
                  {deliveryItems.map((item) => (
                    <li key={item}>
                      <span className="progress-check">
                        ✓
                      </span>
                      {item}
                    </li>
                  ))}
                </ul>
              </article>
            </section>
          </>
        )}
      </main>
      {logoutConfirmation && (
        <ConfirmDialog
          title="Sign out?"
          message={logoutMessage}
          confirmLabel="Sign out"
          confirmingLabel="Signing out..."
          errorMessage="You could not be signed out. Please try again."
          onConfirm={onLogout}
          onCancel={() => setLogoutConfirmation(null)}
        />
)}
    </div>
  )
}

export default Dashboard