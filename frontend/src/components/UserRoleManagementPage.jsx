import { useState } from 'react'
import RolesPanel from './RolesPanel.jsx'
import UsersPanel from './UsersPanel.jsx'

function UserRoleManagementPage({ user }) {
  const permissions = user.permissions ?? []

  const canManageUsers =
    permissions.includes('users.manage')
  const canManageRoles =
    permissions.includes('roles.manage')

  const firstAvailableSection = canManageUsers
    ? 'users'
    : 'roles'

  const [activeSection, setActiveSection] = useState(
    firstAvailableSection,
  )

  return (
    <section className="management-page">
      <div className="page-heading">
        <div>
          <p className="eyebrow">
            Tenant administration
          </p>

          <h2>Users and roles</h2>

          <p className="page-description">
            Manage tenant accounts, assign roles, and
            configure permission-based access.
          </p>
        </div>
      </div>

      <div
        className="messaging-tabs"
        role="tablist"
        aria-label="User and role management sections"
      >
        {canManageUsers && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'users'}
            className={
              activeSection === 'users' ? 'active' : ''
            }
            onClick={() => setActiveSection('users')}
          >
            Users
          </button>
        )}

        {canManageRoles && (
          <button
            type="button"
            role="tab"
            aria-selected={activeSection === 'roles'}
            className={
              activeSection === 'roles' ? 'active' : ''
            }
            onClick={() => setActiveSection('roles')}
          >
            Roles and permissions
          </button>
        )}
      </div>

      {activeSection === 'users' &&
        canManageUsers && <UsersPanel />}

      {activeSection === 'roles' &&
        canManageRoles && <RolesPanel />}

      {!canManageUsers && !canManageRoles && (
        <article className="content-card">
          <div className="empty-state">
            <h3>Administration is restricted</h3>

            <p>
              Your role does not have permission to manage
              users or roles.
            </p>
          </div>
        </article>
      )}
    </section>
  )
}

export default UserRoleManagementPage