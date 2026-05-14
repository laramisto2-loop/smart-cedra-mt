# Initial Security and Threat Model

## Purpose

This document defines the initial security risks and planned protections for the Smart Cedra Multi-Tenant Campaign Management Platform.

The platform handles campaign operations, CRM contacts, messaging workflows, field activity, and election result ingestion. It does not support electronic voting.

---

## Security Goals

- Protect tenant data from unauthorized access.
- Enforce role-based access control.
- Track sensitive actions using audit logs.
- Protect personal data using minimization and consent.
- Prevent accidental or malicious cross-tenant data exposure.
- Avoid any electronic voting or ballot-recording functionality.

---

## Key Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Cross-tenant data access | High | Use `tenant_id` filtering, middleware, and policies |
| Unauthorized admin actions | High | Use RBAC, permissions, and authorization checks |
| Weak password handling | High | Store only hashed passwords |
| SQL injection | High | Use Laravel Eloquent and validation |
| Personal data overcollection | Medium | Collect only required operational data |
| Messaging without consent | High | Store consent status and opt-out records |
| Sensitive changes without traceability | High | Use immutable audit logs |
| Exposed environment secrets | High | Never commit `.env`; use `.env.example` only |
| Insecure file uploads | Medium | Validate file type, size, and storage path |
| Missing backups | Medium | Add backup/restore plan in later phase |

---

## Tenant Isolation

Tenant isolation is required for all tenant-owned data.

Tenant-owned tables must include:

```Examples:

users
roles
contacts
audit_logs
tasks
incidents
messages
results
tenant_id
Every query must be scoped to the authenticated user's tenant.

RBAC Strategy

Initial roles:

Role	Description
Super Admin	Platform-level administration
Campaign Manager	Manages tenant campaign operations
Field Coordinator	Assigns and reviews field activities
Field Agent	Handles field tasks and incidents
Call Center Agent	Handles call outcomes and contact follow-up

Permissions will control access to:

users
roles
contacts
geography
messaging
incidents
results
audit logs
Audit Logging

Sensitive actions must be logged, including:

login/logout
user creation/update/deletion
role and permission changes
contact import/export
messaging approvals
results upload/update/approval
tenant settings changes

Audit logs should be append-only and not editable by normal users.

Data Privacy Notes

The system should follow data minimization principles.

Only collect data required for lawful campaign operations.

Consent must be tracked for messaging workflows.

Opt-out status must be respected before sending WhatsApp/SMS messages.

Non-Negotiables
No electronic voting.
No vote recording.
No ballot secrecy violation.
No delegate-entered individual voter tracking.
No cross-tenant access.

