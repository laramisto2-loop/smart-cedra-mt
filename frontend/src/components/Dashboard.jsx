import { useState } from 'react'
import CampaignTasksPage from './CampaignTasksPage.jsx'
import ContactsPage from './ContactsPage.jsx'
import GeographyPage from './GeographyPage.jsx'
import SegmentsPage from './SegmentsPage.jsx'
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
    enabled: false,
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
    enabled: false,
  },
  {
    label: 'Results',
    icon: '▤',
    enabled: false,
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
]

function Dashboard({ user, onLogout }) {
  const [activePage, setActivePage] = useState('Dashboard')
  const permissions = user.permissions ?? []

  const visibleNavigationItems = navigationItems.filter(
    (item) =>
      !item.permission || permissions.includes(item.permission),
  )

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
            <p className="eyebrow">Tenant administration</p>
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
              onClick={onLogout}
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

        {activePage === 'Dashboard' && (
          <>
            <section className="welcome-panel">
              <div>
                <span className="panel-badge">
                  MT-4 CRM + Tasks
                </span>
                <h3>Welcome to {user.tenant.name}</h3>
                <p>
                  Manage tenant-isolated contacts, audiences,
                  communication history, and campaign assignments
                  from one protected workspace.
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
                    <h3>MT-4 delivery</h3>
                  </div>
                </div>

                <ul className="progress-list">
                  {deliveryItems.map((item) => (
                    <li key={item}>
                      <span className="progress-check">✓</span>
                      {item}
                    </li>
                  ))}
                </ul>
              </article>
            </section>
          </>
        )}
      </main>
    </div>
  )
}

export default Dashboard