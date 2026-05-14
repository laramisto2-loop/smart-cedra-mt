# Initial ERD 

## Core Tables

### tenants
- id
- name
- slug
- status
- logo_url
- primary_color
- created_at
- updated_at

### users
- id
- tenant_id
- name
- email
- password
- status
- created_at
- updated_at

### roles
- id
- tenant_id
- name
- created_at
- updated_at

### permissions
- id
- name
- created_at
- updated_at

### role_user
- id
- user_id
- role_id

### permission_role
many-to-many relationship between permissions and roles.
- id
- permission_id
- role_id

### audit_logs
- id
- tenant_id
- user_id
- action
- entity_type
- entity_id
- old_values
- new_values
- ip_address
- created_at

## Relationships

- Tenant has many Users
- Tenant has many Roles
- User belongs to Tenant
- User belongs to many Roles
- Role belongs to many Permissions
- Tenant has many Audit Logs
- User has many Audit Logs
Tenant has many Users
Tenant has many Roles
Tenant has many Contacts
Tenant has many Audit Logs

User belongs to Tenant
User belongs to many Roles

Role belongs to Tenant
Role belongs to many Permissions

Permission belongs to many Roles

Contact belongs to Tenant

AuditLog belongs to Tenant
AuditLog belongs to User

## Notes

This schema supports:
- multi-tenancy
- tenant isolation
- RBAC
- audit tracking
- future CRM, geography, messaging, and results modules