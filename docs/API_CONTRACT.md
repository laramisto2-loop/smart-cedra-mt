# Initial API Contract

Base URL:

```
Authentication
POST /api/login
Login user and return authentication token.
POST /api/logout
Logout authenticated user.
GET /api/me
Return current authenticated user.

Tenants
GET /api/tenants
List tenants.

POST /api/tenants
Create tenant.

GET /api/tenants/{id}
View tenant details.

PUT /api/tenants/{id}
Update tenant.

Users
GET /api/users

List users for current tenant.
POST /api/users
Create user.

GET /api/users/{id}
View user.

PUT /api/users/{id}
Update user.

Roles and Permissions
GET /api/roles
List roles.

POST /api/roles
Create role.

GET /api/permissions
List permissions.

POST /api/roles/{id}/permissions
Assign permissions to role.

Audit Logs
GET /api/audit-logs

List audit logs for current tenant.

API Rules
Tenant-scoped users can only access their own tenant data.
Sensitive actions must create audit log entries.
Input validation is required for all POST and PUT requests.
All protected endpoints require authentication.
Tenant-scoped endpoints must filter data by tenant_id.
Users must not access records from another tenant.
Input validation is required for all POST and PUT requests.
Passwords must be hashed before storage.

/api