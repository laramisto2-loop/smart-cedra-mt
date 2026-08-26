# ElectoFlow Final Demonstration Checklist

## Before Recording

- [ ] Start Apache and MySQL from XAMPP.
- [ ] Start the Laravel backend.
- [ ] Start the React frontend.
- [ ] Confirm the browser zoom is 100%.
- [ ] Prepare the administrator and field-agent accounts.
- [ ] Hide passwords and private notifications.
- [ ] Confirm the Downloads folder is accessible.

## Introduction

Explain that:

- ElectoFlow is a multi-tenant campaign operations platform.
- It supports contacts, field operations, messaging, call-center work, turnout, and election-result reporting.
- It does not perform electronic voting.
- Tenant isolation and permission-based access protect the data.

## Dashboard

- [ ] Show the active tenant.
- [ ] Show the authenticated user.
- [ ] Explain the Ready and Restricted indicators.
- [ ] Mention tenant-aware authorization.

## Users and Roles

- [ ] Show tenant users and assigned roles.
- [ ] Create or edit a demonstration user.
- [ ] Demonstrate multiple-role assignment.
- [ ] Show standard and custom roles.
- [ ] Show role permissions.
- [ ] Explain administrator-deletion safeguards.

## Geography

- [ ] Show the configured geography.
- [ ] Show polling centers.
- [ ] Show polling stations.
- [ ] Explain that tally sheets belong to polling stations.

## Contacts and Segments

- [ ] Show contact records.
- [ ] Show consent and communication information.
- [ ] Demonstrate searching and filtering.
- [ ] Show contact segments.
- [ ] Mention CSV import and export.
- [ ] Show the interaction timeline.

## Tasks and Incidents

- [ ] Show task assignment and priority.
- [ ] Show task completion.
- [ ] Show field-agent ownership restrictions.
- [ ] Show an incident and its evidence.
- [ ] Mention offline-safe PWA support.

## Messaging

- [ ] Show a message template.
- [ ] Explain template approval.
- [ ] Show consent-aware recipients.
- [ ] Show delivery tracking.
- [ ] Explain quiet hours and opt-out protection.
- [ ] Clarify that production provider credentials are configured separately.

## Call Center

- [ ] Show an active call script.
- [ ] Show a call queue.
- [ ] Assign contacts to the queue.
- [ ] Show the generated assignment.
- [ ] Claim an unassigned assignment.
- [ ] Record a callback result.
- [ ] Show the immutable call history.
- [ ] Show the automatically generated follow-up task.
- [ ] Record a completed call result.

## Results — Contest Configuration

- [ ] Open Results → Election contests.
- [ ] Show the active contest.
- [ ] Show its ballot options.
- [ ] Explain that only active contests accept tally sheets.

## Results — Tally Sheet

- [ ] Open Results → Tally sheets.
- [ ] Create or open a polling-station tally sheet.
- [ ] Show its contest, polling center, and station.
- [ ] Upload a PDF or image as private evidence.
- [ ] Demonstrate authorized evidence download.
- [ ] Explain that evidence is stored privately.

## Results — Independent Double Entry

- [ ] Record Entry 1 using the administrator account.
- [ ] Show the Awaiting Second Entry status.
- [ ] Show that the same account cannot record Entry 2.
- [ ] Sign out.
- [ ] Sign in as Cedra Field Agent.
- [ ] Record Entry 2 using identical totals.
- [ ] Show both entries and their independent users.
- [ ] Show the Ready For Review status.

## Results — Review and Approval

- [ ] Sign in as the administrator.
- [ ] Open the tally sheet marked Ready For Review.
- [ ] Show both immutable entries.
- [ ] Mark the sheet reviewed.
- [ ] Approve the results.
- [ ] Show the Approved status.
- [ ] Explain that finalized results cannot be modified.
- [ ] Show that evidence download remains available.
- [ ] Show that evidence upload and deletion are disabled.

## Results — Discrepancy Protection

Explain that:

- Different entries produce a discrepancy.
- An authorized reviewer must select the accepted entry.
- A discrepancy cannot silently become an official result.

## Results Analytics

- [ ] Open Results → Analytics.
- [ ] Select the election contest.
- [ ] Show reporting coverage.
- [ ] Show registered voters.
- [ ] Show ballots cast.
- [ ] Show turnout percentage.
- [ ] Show valid, invalid, and blank ballots.
- [ ] Show ballot-option totals.
- [ ] Show polling-center reporting.

## CSV Export

- [ ] Click Export CSV.
- [ ] Open the downloaded file in Excel.
- [ ] Confirm that it opens in separate columns.
- [ ] Explain that only approved results are included.
- [ ] Show the dynamic ballot-option columns.

## Field-Agent Restrictions

- [ ] Sign in as Cedra Field Agent.
- [ ] Confirm administrator-only modules are unavailable.
- [ ] Confirm the agent sees only permitted tasks and call assignments.
- [ ] Confirm queue and script administration are unavailable.
- [ ] Confirm the agent can claim permitted call work.
- [ ] Confirm the agent can record call attempts.
- [ ] Confirm the agent can record an independent tally entry.
- [ ] Confirm the agent cannot approve official results.

## Quality Evidence

Show the successful terminal results:

- [ ] Laravel Pint passed for 287 files.
- [ ] All 286 Laravel tests passed.
- [ ] All 2,255 assertions passed.
- [ ] Frontend lint passed.
- [ ] Frontend production build passed.
- [ ] Service-worker syntax check passed.
- [ ] Git working tree is clean.

## Closing Statement

Explain that:

- The complete eight-week functional plan has been implemented.
- The platform supports tenant-safe workflows from contact management through approved result analytics.
- Critical operations are protected by permissions, tenant isolation, independent identities, and immutable records.
- Production hosting and external-provider configuration are separate deployment activities.