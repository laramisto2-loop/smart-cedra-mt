import './App.css'

const navigationItems = [
  { label: 'Dashboard', icon: '▦', active: true },
  { label: 'Users', icon: '👥' },
  { label: 'Geography', icon: '📍' },
  { label: 'Contacts', icon: '📇' },
  { label: 'Tasks', icon: '✓' },
  { label: 'Incidents', icon: '⚠' },
  { label: 'Results', icon: '▤' },
  { label: 'Settings', icon: '⚙' },
]

const statistics = [
  {
    label: 'Campaign users',
    value: '1',
    description: 'Active tenant members',
  },
  {
    label: 'Open tasks',
    value: '0',
    description: 'No pending assignments',
  },
  {
    label: 'Registered contacts',
    value: '0',
    description: 'CRM module coming next',
  },
  {
    label: 'Reported incidents',
    value: '0',
    description: 'No incidents submitted',
  },
]

function App() {
  return (
    <div className="admin-layout">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-logo">SC</div>

          <div>
            <h1>Smart Cedra</h1>
            <p>Campaign Operations</p>
          </div>
        </div>

        <div className="tenant-card">
          <span className="tenant-label">Current tenant</span>
          <strong>Cedra Campaign</strong>
          <span className="tenant-status">
            <span className="status-dot" />
            Active
          </span>
        </div>

        <nav className="navigation" aria-label="Main navigation">
          {navigationItems.map((item) => (
            <button
              type="button"
              key={item.label}
              className={`navigation-item ${item.active ? 'active' : ''}`}
            >
              <span className="navigation-icon" aria-hidden="true">
                {item.icon}
              </span>
              <span>{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="sidebar-footer">
          <p>Multi-tenant environment</p>
          <span>Tenant ID: 1</span>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div>
            <p className="eyebrow">Tenant administration</p>
            <h2>Dashboard</h2>
          </div>

          <div className="user-profile">
            <div className="user-avatar">CA</div>

            <div>
              <strong>Cedra Admin</strong>
              <span>admin@cedra.test</span>
            </div>
          </div>
        </header>

        <section className="welcome-panel">
          <div>
            <span className="panel-badge">MT-2 Multi-Tenancy Foundation</span>
            <h3>Welcome to Cedra Campaign</h3>
            <p>
              This administration shell represents one tenant inside the Smart
              Cedra campaign-management platform. Data belonging to other
              tenants is isolated and unavailable here.
            </p>
          </div>

          <div className="isolation-indicator">
            <span className="shield-icon">✓</span>
            <div>
              <strong>Tenant isolation active</strong>
              <span>Protected by tenant-aware middleware</span>
            </div>
          </div>
        </section>

        <section className="statistics-grid" aria-label="Campaign statistics">
          {statistics.map((statistic) => (
            <article className="statistic-card" key={statistic.label}>
              <span>{statistic.label}</span>
              <strong>{statistic.value}</strong>
              <p>{statistic.description}</p>
            </article>
          ))}
        </section>

        <section className="content-grid">
          <article className="content-card">
            <div className="card-heading">
              <div>
                <p className="eyebrow">Tenant information</p>
                <h3>Campaign configuration</h3>
              </div>

              <button type="button" className="secondary-button">
                Manage settings
              </button>
            </div>

            <dl className="details-list">
              <div>
                <dt>Tenant name</dt>
                <dd>Cedra Campaign</dd>
              </div>

              <div>
                <dt>Tenant slug</dt>
                <dd>cedra-campaign</dd>
              </div>

              <div>
                <dt>Timezone</dt>
                <dd>Asia/Beirut</dd>
              </div>

              <div>
                <dt>Status</dt>
                <dd>
                  <span className="active-pill">Active</span>
                </dd>
              </div>
            </dl>
          </article>

          <article className="content-card">
            <div className="card-heading">
              <div>
                <p className="eyebrow">Implementation progress</p>
                <h3>MT-2 foundation</h3>
              </div>
            </div>

            <ul className="progress-list">
              <li>
                <span className="progress-check">✓</span>
                Tenant database tables
              </li>
              <li>
                <span className="progress-check">✓</span>
                Tenant models and relationships
              </li>
              <li>
                <span className="progress-check">✓</span>
                Tenant settings and seed data
              </li>
              <li>
                <span className="progress-check">✓</span>
                Tenant-aware middleware
              </li>
              <li>
                <span className="progress-check">✓</span>
                Automated isolation tests
              </li>
            </ul>
          </article>
        </section>
      </main>
    </div>
  )
}

export default App