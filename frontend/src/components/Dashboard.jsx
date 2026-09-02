import { useEffect, useState } from 'react'
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
import SettingsPage from './SettingsPage.jsx'
import { countQueuedIncidents } from '../services/incidentQueue.js'
import { countQueuedTurnoutSnapshots } from '../services/turnoutQueue.js'
import { getDashboardSummary } from '../services/dashboard.js'
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
    permissions: [
      'calls.scripts.view',
      'calls.queues.view',
      'calls.assignments.view',
    ],
    enabled: true,
  },
  {
    label: 'Settings',
    icon: '⚙',
    permission: 'settings.manage',
    enabled: true,
  },
]

const navigationIconPaths = {
  Dashboard: [
    'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z',
  ],
  Users: [
    'M15.5 20v-1.5a4 4 0 0 0-4-4h-5a4 4 0 0 0-4 4V20',
    'M9 10.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
    'M17 11a3 3 0 0 0 0-6M21.5 20v-1.5a4 4 0 0 0-3-3.87',
  ],
  Geography: [
    'M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z',
    'M12 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  ],
  Contacts: [
    'M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v13a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 18.5v-13Z',
    'M9.25 10a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5ZM6.5 16a2.75 2.75 0 0 1 5.5 0M14.5 8h3M14.5 12h3',
  ],
  Segments: [
    'M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM17 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 22a4 4 0 0 1 7.5-2M10 7l4 5',
  ],
  Tasks: [
    'M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z',
    'm7 12 3 3 7-7',
  ],
  Incidents: [
    'M10.3 3.7 2.8 17a2 2 0 0 0 1.75 3h14.9a2 2 0 0 0 1.75-3L13.7 3.7a2 2 0 0 0-3.4 0Z',
    'M12 9v4M12 17h.01',
  ],
  Results: [
    'M4 20V10M10 20V4M16 20v-7M22 20H2',
  ],
  Messaging: [
    'M4 5h16v14H4V5Z',
    'm4 6 8 6 8-6',
  ],
  'Call Center': [
    'M21 16.5v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6.02-6A19.8 19.8 0 0 1 1.1 3.77 2 2 0 0 1 3.08 1.6h3a2 2 0 0 1 2 1.72c.13.96.36 1.89.68 2.79a2 2 0 0 1-.45 2.11L7.04 9.58a16 16 0 0 0 7.38 7.38l1.36-1.36a2 2 0 0 1 2.11-.45c.9.32 1.83.55 2.79.68A2 2 0 0 1 21 16.5Z',
  ],
  Settings: [
    'M12 15.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Z',
    'M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.55V20.3h-3v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7.08 15a1.7 1.7 0 0 0-1.55-1H5.45v-3h.08a1.7 1.7 0 0 0 1.55-1 1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.12-2.12.06.06a1.7 1.7 0 0 0 1.88.34 1.7 1.7 0 0 0 1-1.55V4.7h3v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0 0 19.4 10a1.7 1.7 0 0 0 1.55 1h.09v3h-.09a1.7 1.7 0 0 0-1.55 1Z',
  ],
}

function NavigationIcon({ label }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true">
      {(navigationIconPaths[label] ?? []).map((path) => (
        <path d={path} key={path} />
      ))}
    </svg>
  )
}

const capabilityAccessItems = [
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
    description: 'Consent checks and delivery tracking',
  },
  {
    label: 'Call center',
    permission: 'calls.assignments.view',
    description: 'Assignments and immutable call outcomes',
  },
]

function readableStatus(status) {
  return status
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase())
}

function StatusBreakdown({ eyebrow, title, counts }) {
  const entries = Object.entries(counts ?? {})
  const total = entries.reduce(
    (sum, [, count]) => sum + count,
    0,
  )
  const largest = Math.max(...entries.map(([, count]) => count), 0)

  return (
    <article className="dashboard-analysis-card">
      <div className="dashboard-card-heading">
        <div>
          <p className="eyebrow">{eyebrow}</p>
          <h3>{title}</h3>
        </div>
        <strong className="dashboard-chart-total">{total}</strong>
      </div>

      {total === 0 ? (
        <p className="dashboard-empty-chart">
          No records have been added yet.
        </p>
      ) : (
        <div className="dashboard-status-list">
          {entries.map(([status, count]) => (
            <div className="dashboard-status-row" key={status}>
              <div className="dashboard-status-label">
                <span>{readableStatus(status)}</span>
                <strong>{count}</strong>
              </div>
              <div className="dashboard-status-track">
                <span
                  className={`dashboard-status-fill status-${status}`}
                  style={{
                    width: `${largest === 0
                      ? 0
                      : (count / largest) * 100}%`,
                  }}
                />
              </div>
            </div>
          ))}
        </div>
      )}
    </article>
  )
}

function PerformanceMetric({ label, rate, detail, color }) {
  return (
    <div className="dashboard-performance-metric">
      <div
        className="dashboard-rate-ring"
        style={{
          '--dashboard-rate': rate,
          '--dashboard-rate-color': color,
        }}
        aria-label={`${label}: ${rate}%`}
      >
        <span>{rate}%</span>
      </div>
      <div>
        <strong>{label}</strong>
        <span>{detail}</span>
      </div>
    </div>
  )
}

function Dashboard({ user, onLogout }) {
  const [activePage, setActivePage] = useState('Dashboard')
  const [logoutConfirmation, setLogoutConfirmation] =
  useState(null)
  const [tenantSettings, setTenantSettings] = useState(
    user.tenant.settings ?? {},
  )
  const [dashboardSummary, setDashboardSummary] = useState(null)
  const [dashboardLoading, setDashboardLoading] = useState(true)
  const [dashboardError, setDashboardError] = useState('')
  const [dashboardRefreshKey, setDashboardRefreshKey] = useState(0)
  const permissions = user.permissions ?? []

  const visibleNavigationItems = navigationItems.filter((item) => {
    if (item.permissions) {
      return item.permissions.some((permission) =>
        permissions.includes(permission),
      )
    }

    return !item.permission || permissions.includes(item.permission)
  })

  const initials = user.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  const tenantStatus =
    user.tenant.status.charAt(0).toUpperCase() +
    user.tenant.status.slice(1)
  const brandName = tenantSettings.brand_name?.trim()
    || 'ElectoFlow'
  const primaryColor = tenantSettings.primary_color
    || '#28a9e2'
  const tenantInitial = user.tenant.name.charAt(0).toUpperCase()

  useEffect(() => {
    if (activePage !== 'Dashboard') {
      return undefined
    }

    let cancelled = false

    async function loadSummary() {
      setDashboardLoading(true)
      setDashboardError('')

      try {
        const summary = await getDashboardSummary()

        if (!cancelled) {
          setDashboardSummary(summary)
        }
      } catch (error) {
        if (!cancelled) {
          setDashboardError(
            error.response?.data?.message
              ?? 'The dashboard statistics could not be loaded.',
          )
        }
      } finally {
        if (!cancelled) {
          setDashboardLoading(false)
        }
      }
    }

    loadSummary()

    return () => {
      cancelled = true
    }
  }, [activePage, dashboardRefreshKey])

  const dashboardKpis = dashboardSummary
    ? [
        dashboardSummary.contacts && {
          label: 'Active contacts',
          value: dashboardSummary.contacts.active,
          context: `${dashboardSummary.contacts.total} total contacts`,
          accent: 'blue',
        },
        dashboardSummary.contacts && {
          label: 'Consent coverage',
          value: `${dashboardSummary.contacts.consent_coverage_rate}%`,
          context: `${dashboardSummary.contacts.with_granted_consent} active ${
            dashboardSummary.contacts.with_granted_consent === 1
              ? 'contact'
              : 'contacts'
          } opted in`,
          accent: 'teal',
          progress: dashboardSummary.contacts.consent_coverage_rate,
        },
        dashboardSummary.tasks && {
          label: 'Open tasks',
          value: dashboardSummary.tasks.open,
          context: `${dashboardSummary.tasks.completion_rate}% completion rate`,
          accent: 'amber',
        },
        dashboardSummary.incidents && {
          label: 'Open incidents',
          value: dashboardSummary.incidents.open,
          context: `${dashboardSummary.incidents.critical_open} critical`,
          accent: 'red',
        },
        dashboardSummary.messages && {
          label: 'Message delivery',
          value: `${dashboardSummary.messages.delivery_rate}%`,
          context: `${dashboardSummary.messages.delivered} delivered or read`,
          accent: 'purple',
          progress: dashboardSummary.messages.delivery_rate,
        },
        dashboardSummary.calls && {
          label: 'Call completion',
          value: `${dashboardSummary.calls.completion_rate}%`,
          context: `${dashboardSummary.calls.open} assignments still open`,
          accent: 'navy',
          progress: dashboardSummary.calls.completion_rate,
        },
      ].filter(Boolean)
    : []

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
          <div
            className="brand-logo"
            style={{ backgroundColor: primaryColor }}
          >
            EF
          </div>

          <div>
            <h1>{brandName}</h1>
            <p>Campaign Operations</p>
          </div>
        </div>

        <div className="tenant-card">
          <div className="tenant-card-heading">
            <span
              className="tenant-symbol"
              style={{ backgroundColor: primaryColor }}
            >
              {tenantInitial}
            </span>
            <div>
              <span className="tenant-label">Current workspace</span>
              <strong>{user.tenant.name}</strong>
            </div>
          </div>
          <span className="tenant-status">
            <span className="status-dot" />
            {tenantStatus}
          </span>
        </div>

        <p className="navigation-label">Workspace</p>

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
                <NavigationIcon label={item.label} />
              </span>
              <span>{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="sidebar-footer">
          <span className="sidebar-secure-icon">✓</span>
          <div>
            <p>Protected workspace</p>
            <span>Tenant isolated · ID {user.tenant.id}</span>
          </div>
        </div>
      </aside>

      <main
        className={`main-content ${
          activePage === 'Dashboard'
            ? ''
            : 'section-content'
        }`}
      >
        <header
          className={`topbar ${
            activePage === 'Dashboard'
              ? ''
              : 'topbar-profile-only'
          }`}
        >
          {activePage === 'Dashboard' && (
            <div>
              <p className="eyebrow">
                Tenant administration
              </p>
              <h2>Dashboard</h2>
            </div>
          )}

          <div className="user-profile">
            <div className="user-avatar">
              {initials}
              <span className="user-presence" />
            </div>

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

        {activePage === 'Settings' && (
          <SettingsPage
            user={user}
            onSaved={setTenantSettings}
          />
        )}

        {activePage === 'Dashboard' && (
          <>
            <section className="welcome-panel">
              <div>
                <span className="panel-badge">
                  Live campaign overview
                </span>
                <h3>Welcome to {user.tenant.name}</h3>
                <p>
                  Track campaign activity, operational workload,
                  incidents, and communication performance from
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

            {dashboardError && (
              <div className="dashboard-error" role="alert">
                <span>{dashboardError}</span>
                <button
                  type="button"
                  onClick={() => setDashboardRefreshKey(
                    (value) => value + 1,
                  )}
                >
                  Try again
                </button>
              </div>
            )}

            {dashboardLoading ? (
              <section
                className="dashboard-kpi-grid"
                aria-label="Loading campaign statistics"
              >
                {[1, 2, 3, 4].map((item) => (
                  <article
                    className="dashboard-kpi-card dashboard-kpi-skeleton"
                    key={item}
                  >
                    <span />
                    <strong />
                    <p />
                  </article>
                ))}
              </section>
            ) : (
              <section
                className="dashboard-kpi-grid"
                aria-label="Campaign statistics"
              >
                {dashboardKpis.map((kpi) => (
                  <article
                    className={`dashboard-kpi-card dashboard-kpi-${kpi.accent}`}
                    key={kpi.label}
                  >
                    <span>{kpi.label}</span>
                    <strong>{kpi.value}</strong>
                    <p>{kpi.context}</p>
                    {kpi.progress !== undefined && (
                      <div
                        className="dashboard-kpi-progress"
                        aria-hidden="true"
                      >
                        <span
                          style={{ width: `${kpi.progress}%` }}
                        />
                      </div>
                    )}
                  </article>
                ))}
              </section>
            )}

            <section
              className="dashboard-access-panel"
              aria-labelledby="workspace-access-heading"
            >
              <div className="dashboard-access-heading">
                <div>
                  <p className="eyebrow">Permissions</p>
                  <h3 id="workspace-access-heading">
                    Workspace access
                  </h3>
                </div>
                <p>
                  Availability for your current role
                </p>
              </div>

              <div className="dashboard-access-grid">
                {capabilityAccessItems.map((capability) => {
                  const available = permissions.includes(
                    capability.permission,
                  )

                  return (
                    <article
                      className={`dashboard-access-item ${
                        available
                          ? 'dashboard-access-ready'
                          : 'dashboard-access-restricted'
                      }`}
                      key={capability.label}
                    >
                      <div>
                        <strong>{capability.label}</strong>
                        <span>{capability.description}</span>
                      </div>
                      <span className="dashboard-access-status">
                        <span aria-hidden="true">
                          {available ? '✓' : '—'}
                        </span>
                        {available ? 'Ready' : 'Restricted'}
                      </span>
                    </article>
                  )
                })}
              </div>
            </section>

            {!dashboardLoading && dashboardSummary && (
              <section
                className="dashboard-insights-grid"
                aria-label="Operational charts"
              >
                {dashboardSummary.tasks && (
                  <StatusBreakdown
                    eyebrow="Workload"
                    title="Tasks by status"
                    counts={dashboardSummary.tasks.by_status}
                  />
                )}

                {dashboardSummary.incidents && (
                  <StatusBreakdown
                    eyebrow="Field operations"
                    title="Incidents by status"
                    counts={dashboardSummary.incidents.by_status}
                  />
                )}

                {(dashboardSummary.messages
                  || dashboardSummary.calls) && (
                  <article className="dashboard-analysis-card">
                    <div className="dashboard-card-heading">
                      <div>
                        <p className="eyebrow">Communications</p>
                        <h3>Performance</h3>
                      </div>
                    </div>

                    <div className="dashboard-performance-list">
                      {dashboardSummary.messages && (
                        <PerformanceMetric
                          label="Message delivery"
                          rate={dashboardSummary.messages.delivery_rate}
                          detail={`${dashboardSummary.messages.total} messages tracked`}
                          color="#7c5ce5"
                        />
                      )}
                      {dashboardSummary.calls && (
                        <PerformanceMetric
                          label="Call completion"
                          rate={dashboardSummary.calls.completion_rate}
                          detail={`${dashboardSummary.calls.attempts ?? 0} attempts recorded`}
                          color="#1587b7"
                        />
                      )}
                    </div>
                  </article>
                )}

                <article className="dashboard-analysis-card">
                  <div className="dashboard-card-heading">
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
                      <dd>
                        {tenantSettings.timezone ?? 'Asia/Beirut'}
                      </dd>
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
              </section>
            )}

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
