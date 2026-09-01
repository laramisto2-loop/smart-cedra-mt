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
