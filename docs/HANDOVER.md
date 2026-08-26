# ElectoFlow Project Handover

## Project Information

- Project: ElectoFlow Multi-Tenant Campaign Operations Platform
- Developer: Lara Misto
- Organization: Smart Cedra
- Company supervisor: Mr. Abdelkarim Hamed
- University: Lebanese University, Faculty of Technology
- Program: Master’s Degree in Technology and Science of Information Systems
- University supervisor: Dr. Firas Abdallah
- Academic year: 2025–2026
- Handover status: Functional scope implemented; final documentation and merge pending
- Last verified: 25 August 2026

## Purpose

ElectoFlow is a multi-tenant platform for campaign operations, contact management, field coordination, messaging, call-center work, turnout monitoring, and election-result reporting.

The platform does not provide electronic voting and does not record individual voter choices.

## Technology

### Backend

- PHP 8.2
- Laravel 12
- Laravel Sanctum authentication
- REST API
- PHPUnit 11
- Laravel Pint

### Frontend

- React 19
- Vite 8
- Axios
- ESLint
- Progressive Web App support

### Storage

- SQLite is the default development database.
- MySQL can be configured through the Laravel environment file.
- Sensitive tally evidence is stored on Laravel’s private local filesystem.

## Delivered Modules

### Tenant and access control

- Tenant-isolated data access
- Authentication and session handling
- Role-based permissions
- User creation, editing, role assignment, and deletion safeguards
- Standard and custom role management
- Protected administrator continuity

### Geography

- Administrative geography
- Polling centers
- Polling stations
- Tenant-scoped location relationships

### Contact management

- Contact profiles
- Contact references and communication information
- Consent and opt-out information
- CSV import and export
- Contact segmentation
- Interaction history

### Campaign operations

- Campaign tasks
- Assignment and completion workflows
- Field incidents
- Evidence attachments
- Offline-safe/PWA support
- Aggregate turnout reporting

### Messaging

- Message templates
- Consent-aware outbound messaging
- Delivery records
- Quiet-hours and opt-out enforcement
- WhatsApp/SMS-ready workflow foundation

External provider credentials and production sending configuration are deployment concerns and are not stored in the repository.

### Call center

- Call scripts
- Script activation
- Calling queues
- Bulk contact assignment
- Agent claiming
- Immutable call attempts
- Callback scheduling
- Automatic follow-up task creation
- Role-aware agent interface

### Results ingestion and analytics

- Election contest configuration
- Ballot-option management
- Polling-station tally sheets
- Private tally evidence
- Two independent tally entries
- Identity separation between Entry 1 and Entry 2
- Automatic reconciliation
- Discrepancy review
- Administrative approval and rejection
- Finalized-sheet immutability
- Contest and polling-center analytics
- Ballot-option totals
- Reporting coverage and turnout percentages
- Excel-compatible CSV export

## Results Workflow

1. A tenant administrator creates an election contest.
2. Ballot options are added and the contest is activated.
3. A tally sheet is created for an active contest and polling station.
4. Private evidence may be uploaded.
5. An authorized user records and submits Entry 1.
6. A different authorized user records and submits Entry 2.
7. Matching entries move the sheet to `ready_for_review`.
8. Different entries move the sheet to `discrepancy`.
9. An authorized reviewer examines the entries.
10. An authorized approver approves or rejects the official result.
11. Approved sheets contribute to analytics and CSV exports.
12. Finalized sheets and submitted entries cannot be altered.

## Security Guarantees

- Tenant-owned records are scoped to the authenticated tenant.
- Authorization is enforced through permissions and model policies.
- Submitted tally entries are immutable.
- Finalized tally sheets are immutable.
- Entry 2 must be submitted by a different user from Entry 1.
- Evidence files are tenant-protected and stored privately.
- Upload types and sizes are validated.
- Sensitive operations are protected by backend authorization.
- User-provided tenant identifiers cannot override the active tenant.
- No secrets or production credentials are committed.

## Verification Baseline

The following checks passed on 25 August 2026:

- Laravel Pint: 287 files passed
- Laravel tests: 286 tests passed
- Laravel assertions: 2,255
- Results tests: 13 passed with 145 assertions
- Frontend ESLint: passed
- Frontend production build: passed
- Frontend modules transformed: 162
- Service-worker syntax check: passed
- Git whitespace check: passed after the final whitespace correction

## Demonstrated Results Data

### Polling Station 1

- Registered voters: 500
- Ballots cast: 400
- Valid ballots: 390
- Invalid ballots: 5
- Blank ballots: 5
- Cedar Reform List: 210
- Community Development List: 180
- Status: Approved

### Polling Station 2

- Registered voters: 790
- Ballots cast: 775
- Valid ballots: 760
- Invalid ballots: 10
- Blank ballots: 5
- Cedar Reform List: 410
- Community Development List: 350
- Entry 1: Cedra Admin
- Entry 2: Cedra Field Agent
- Independent-entry validation: Passed

## Repository State

Week 8 development branch:

`feature/mt8-results-analytics-handover`

Important Week 8 commits:

- `76f7dac` — Build tenant-safe results ingestion foundation
- `d796548` — Complete results analytics and evidence workflows

The branch must receive the final documentation commit before its pull request is merged into `main`.

## Known Non-Blocking Items

- The production JavaScript bundle exceeds Vite’s default 500 kB advisory threshold.
- The application still builds successfully.
- Route-level lazy loading can be introduced as a future optimization.
- Production hosting, HTTPS, scheduled backups, monitoring, and external messaging-provider credentials require deployment-specific configuration.
- Browser confirmation dialogs are currently used for some destructive actions.
- Seeded demonstration passwords must not be reused in production.

## Handover Acceptance

The implementation can be considered handed over when:

- Final documentation is committed.
- The Week 8 branch passes the complete test suite.
- The Week 8 pull request is reviewed and merged.
- `main` is pulled and verified locally.
- A final demonstration is recorded.
- Production-specific secrets and infrastructure are configured separately.