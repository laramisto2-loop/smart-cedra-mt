import { useEffect, useState } from 'react'
import ConfirmDialog from './ConfirmDialog.jsx'
import PlatformTenantForm from './PlatformTenantForm.jsx'
import {
  listPlatformTenants,
  updatePlatformTenantStatus,
} from '../services/platformAdministration.js'

const emptyFilters = {
  search: '',
  status: '',
}

function formatDate(value) {
  if (!value) {
    return 'Not recorded'
  }

  return new Intl.DateTimeFormat('en', {
    dateStyle: 'medium',
  }).format(new Date(value))
}

export default function PlatformDashboard({
  user,
  onLogout,
}) {
  const [tenants, setTenants] = useState([])
  const [filters, setFilters] = useState(emptyFilters)
  const [, setAppliedFilters] = useState({
    per_page: 100,
  })
  const [editor, setEditor] = useState(null)
  const [statusTarget, setStatusTarget] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [flash, setFlash] = useState('')

  useEffect(() => {
    let active = true

    listPlatformTenants({ per_page: 100 })
      .then((result) => {
        if (!active) return

        setTenants(result.items)
      })
      .catch(() => {
        if (!active) return

        setError(
          'Tenant accounts could not be loaded. Please try again.',
        )
      })
      .finally(() => {
        if (active) {
          setIsLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const initials = user.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  const activeTenantCount = tenants.filter(
    (tenant) => tenant.status === 'active',
  ).length

  const suspendedTenantCount = tenants.filter(
    (tenant) => tenant.status === 'suspended',
  ).length

  const totalUserCount = tenants.reduce(
    (total, tenant) =>
      total + Number(tenant.users_count ?? 0),
    0,
  )

  async function loadTenants(nextFilters) {
    setIsLoading(true)
    setError('')

    try {
      const result = await listPlatformTenants(
        nextFilters,
      )

      setTenants(result.items)
    } catch {
      setError(
        'Tenant accounts could not be loaded. Please try again.',
      )
    } finally {
      setIsLoading(false)
    }
  }

  async function handleSearch(event) {
    event.preventDefault()

    const nextFilters = {
      per_page: 100,
      ...(filters.search.trim()
        ? { search: filters.search.trim() }
        : {}),
      ...(filters.status
        ? { status: filters.status }
        : {}),
    }

    setAppliedFilters(nextFilters)
    await loadTenants(nextFilters)
  }

  async function clearFilters() {
    const nextFilters = { per_page: 100 }

    setFilters(emptyFilters)
    setAppliedFilters(nextFilters)
    await loadTenants(nextFilters)
  }

  function handleTenantSaved(savedTenant) {
    setTenants((current) => {
      const alreadyExists = current.some(
        (tenant) => tenant.id === savedTenant.id,
      )

      return alreadyExists
        ? current.map((tenant) =>
            tenant.id === savedTenant.id
              ? savedTenant
              : tenant,
          )
        : [savedTenant, ...current]
    })

    setEditor(null)
    setFlash(
      `${savedTenant.name} was saved successfully.`,
    )
  }

  async function handleStatusConfirm() {
    const updatedTenant =
      await updatePlatformTenantStatus(
        statusTarget.tenant.id,
        statusTarget.nextStatus,
      )

    setTenants((current) =>
      current.map((tenant) =>
        tenant.id === updatedTenant.id
          ? updatedTenant
          : tenant,
      ),
    )

    setFlash(
      updatedTenant.status === 'active'
        ? `${updatedTenant.name} was reactivated successfully.`
        : `${updatedTenant.name} was suspended successfully.`,
    )

    setStatusTarget(null)
  }

  return (
    <div className="admin-layout">
      <aside className="sidebar">
        <div className="brand">
          <div className="brand-logo">EF</div>
          <div>
            <h1>ElectoFlow</h1>
            <p>Platform Administration</p>
          </div>
        </div>

        <div className="platform-sidebar-card">
          <span className="tenant-label">
            GLOBAL CONTROL
          </span>
          <strong>Platform Owner</strong>
          <span>
            Manage isolated campaign tenants
          </span>
        </div>

        <nav className="navigation">
          <button
            className="navigation-item active"
            type="button"
          >
            <span className="navigation-icon">▦</span>
            Tenants
          </button>
        </nav>

        <div className="sidebar-footer">
          <p>ElectoFlow platform environment</p>
          <span>Tenant isolation administration</span>
        </div>
      </aside>

      <main className="main-content">
        <header className="topbar">
          <div>
            <p className="eyebrow">
              PLATFORM ADMINISTRATION
            </p>
            <h2>Tenant management</h2>
          </div>

          <div className="user-profile">
            <div className="user-avatar">
              {initials}
            </div>

            <div className="user-details">
              <strong>{user.name}</strong>
              <span>{user.email}</span>
            </div>

            <button
              className="secondary-button"
              onClick={onLogout}
              type="button"
            >
              Sign out
            </button>
          </div>
        </header>

        <section className="platform-hero">
          <div>
            <span className="panel-badge">
              GLOBAL PLATFORM OWNER
            </span>
            <h3>Campaign tenant administration</h3>
            <p>
              Create isolated campaign workspaces,
              assign their first administrators, and
              control whether each tenant can access
              ElectoFlow.
            </p>
          </div>

          <div className="platform-isolation-indicator">
            <span className="shield-icon">✓</span>
            <div>
              <strong>Isolation enforced</strong>
              <span>
                Platform accounts cannot enter tenant APIs
              </span>
            </div>
          </div>
        </section>

        <section className="platform-statistics-grid">
          <article className="statistic-card">
            <span>Total tenants</span>
            <strong>{tenants.length}</strong>
            <p>Campaign workspaces in this result set</p>
          </article>

          <article className="statistic-card">
            <span>Active tenants</span>
            <strong>{activeTenantCount}</strong>
            <p>Campaigns currently allowed to sign in</p>
          </article>

          <article className="statistic-card">
            <span>Suspended tenants</span>
            <strong>{suspendedTenantCount}</strong>
            <p>Campaign access currently disabled</p>
          </article>

          <article className="statistic-card">
            <span>Tenant users</span>
            <strong>{totalUserCount}</strong>
            <p>Accounts across displayed tenants</p>
          </article>
        </section>

        <section className="platform-section-heading">
          <div>
            <p className="eyebrow">
              CAMPAIGN ACCOUNTS
            </p>
            <h3>Tenants</h3>
            <p>
              Search, configure, suspend, or reactivate
              campaign workspaces.
            </p>
          </div>

          <button
            className="primary-button"
            onClick={() =>
              setEditor({ tenant: null })
            }
            type="button"
          >
            Create tenant
          </button>
        </section>

        {flash && (
          <div
            className="form-message success-message"
            role="status"
          >
            {flash}
          </div>
        )}

        {error && (
          <div
            className="form-message error-message"
            role="alert"
          >
            {error}
          </div>
        )}

        <article className="content-card platform-filter-card">
          <form
            className="platform-search-form"
            onSubmit={handleSearch}
          >
            <label className="form-field">
              <span>Search tenants</span>
              <input
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    search: event.target.value,
                  }))
                }
                placeholder="Tenant name or slug"
                value={filters.search}
              />
            </label>

            <button
              className="primary-button"
              disabled={isLoading}
              type="submit"
            >
              Search
            </button>
          </form>

          <div className="platform-filter-row">
            <label className="form-field">
              <span>Status</span>
              <select
                onChange={(event) =>
                  setFilters((current) => ({
                    ...current,
                    status: event.target.value,
                  }))
                }
                value={filters.status}
              >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="suspended">
                  Suspended
                </option>
              </select>
            </label>

            <button
              className="secondary-button platform-clear-button"
              disabled={isLoading}
              onClick={clearFilters}
              type="button"
            >
              Clear filters
            </button>
          </div>
        </article>

        <article className="content-card platform-tenant-list">
          {isLoading ? (
            <p className="state-message">
              Loading tenant accounts...
            </p>
          ) : tenants.length === 0 ? (
            <div className="empty-state">
              <h3>No tenants found</h3>
              <p>
                Create the first tenant or change the
                current filters.
              </p>
            </div>
          ) : (
            <div className="platform-table-wrapper">
              <table className="platform-tenants-table">
                <thead>
                  <tr>
                    <th>Tenant</th>
                    <th>Status</th>
                    <th>Administrator</th>
                    <th>Users</th>
                    <th>Roles</th>
                    <th>Created</th>
                    <th>Actions</th>
                  </tr>
                </thead>

                <tbody>
                  {tenants.map((tenant) => {
                    const administrator =
                      tenant.administrators?.[0]

                    return (
                      <tr key={tenant.id}>
                        <td>
                          <strong>{tenant.name}</strong>
                          <span>
                            {tenant.slug}
                          </span>
                          {tenant.settings?.timezone && (
                            <small>
                              {
                                tenant.settings
                                  .timezone
                              }
                            </small>
                          )}
                        </td>

                        <td>
                          <span
                            className={`platform-status-pill ${tenant.status}`}
                          >
                            {tenant.status === 'active'
                              ? 'Active'
                              : 'Suspended'}
                          </span>
                        </td>

                        <td>
                          {administrator ? (
                            <>
                              <strong>
                                {administrator.name}
                              </strong>
                              <span>
                                {administrator.email}
                              </span>
                            </>
                          ) : (
                            <span>
                              No administrator
                            </span>
                          )}
                        </td>

                        <td>{tenant.users_count}</td>
                        <td>{tenant.roles_count}</td>
                        <td>
                          {formatDate(
                            tenant.created_at,
                          )}
                        </td>

                        <td>
                          <div className="platform-table-actions">
                            <button
                              className="platform-action-button"
                              onClick={() =>
                                setEditor({
                                  tenant,
                                })
                              }
                              type="button"
                            >
                              Edit
                            </button>

                            <button
                              className={
                                tenant.status === 'active'
                                  ? 'platform-action-button danger'
                                  : 'platform-action-button'
                              }
                              onClick={() =>
                                setStatusTarget({
                                  tenant,
                                  nextStatus:
                                    tenant.status
                                    === 'active'
                                      ? 'suspended'
                                      : 'active',
                                })
                              }
                              type="button"
                            >
                              {tenant.status === 'active'
                                ? 'Suspend'
                                : 'Reactivate'}
                            </button>
                          </div>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          )}
        </article>

        {editor && (
          <PlatformTenantForm
            key={editor.tenant?.id ?? 'new-tenant'}
            onCancel={() => setEditor(null)}
            onSaved={handleTenantSaved}
            tenant={editor.tenant}
          />
        )}

        {statusTarget && (
          <ConfirmDialog
            confirmLabel={
              statusTarget.nextStatus === 'active'
                ? 'Reactivate tenant'
                : 'Suspend tenant'
            }
            confirmingLabel={
              statusTarget.nextStatus === 'active'
                ? 'Reactivating...'
                : 'Suspending...'
            }
            errorMessage="The tenant status could not be changed."
            message={
              statusTarget.nextStatus === 'active'
                ? `Reactivate ${statusTarget.tenant.name} and allow its users to sign in again?`
                : `Suspend ${statusTarget.tenant.name}? Existing API tokens will be revoked and its users will no longer be able to sign in.`
            }
            onCancel={() => setStatusTarget(null)}
            onConfirm={handleStatusConfirm}
            title={
              statusTarget.nextStatus === 'active'
                ? 'Reactivate tenant'
                : 'Suspend tenant'
            }
          />
        )}
      </main>
    </div>
  )
}
