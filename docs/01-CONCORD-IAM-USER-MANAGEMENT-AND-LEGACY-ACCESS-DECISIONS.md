# Concord IAM: User Management and Legacy Apps Access Decisions

**Project:** Concord v0\
**Owner:** Identity and Access Management (IAM)\
**Status:** Approved requirements baseline; implementation status reconciled 2026-10-31\
**Date:** 2026-10-30\
**Scope:** Initial User Management and Legacy Apps Access capabilities

------------------------------------------------------------------------

## 1. Purpose

This document records the decisions made for the first usable IAM
administration release. It is the requirements baseline for the IAM
backend implementation and the frontend handoff.

The scope is deliberately limited to: 1. Creating and managing Concord
user accounts. 2. Managing each user's access to registered legacy
applications. 3. Managing reusable Grant Sets. 4. Assigning access to
multiple users through Batch Assign. 5. Preserving grant and revocation
history for future auditing.

This does not finalize the broader Roles, Permissions, capability
catalog, office delegation, or HRIS architecture.

## 2. Ownership and boundaries

  -----------------------------------------------------------------------
  Capability / data       Owner                   Responsibility
  ----------------------- ----------------------- -----------------------
  User identity and       IAM                     People, user accounts,
  account                                         credentials, account
                                                  status

  Legacy application      Concord Platform        Registered application
  directory                                       records and destination
                                                  URLs

  Legacy application      IAM                     Which user may access
  access assignments                              which registered legacy
                                                  application

  Grant Sets              IAM                     Reusable collections of
                                                  legacy applications

  Grant and revoke        IAM                     Durable record of
  history                                         access changes and the
                                                  administrator
                                                  responsible

  Legacy application      Each legacy application Must enforce its own
  business authorization                          access rules where
                                                  direct URL access is
                                                  possible
  -----------------------------------------------------------------------

The application directory is platform-wide data. IAM consumes it through
the existing `concord_platform` database connection. The directory must
not be duplicated into the IAM database.

**Security boundary:** Showing or hiding an application link in Concord
is not sufficient to protect the destination. A user may know or
bookmark the URL. The legacy application itself must enforce
authorization, or a future centralized integration must provide
equivalent protection.

## 3. Approved decisions

### ULA-001: User Management and Legacy Apps Access are separate management areas

-   User Management creates and manages Concord accounts.
-   Legacy Apps Access manages application assignments for existing
    users.
-   Legacy Apps Access must not create a second user/person record.
-   The administrator creates the account first, then manages
    application access separately.

### ULA-002: A user may log in without legacy application access

-   A newly created user can authenticate to Concord even when no legacy
    applications have been assigned.
-   The user can navigate Concord.
-   The Legacy Apps navigation entry remains visible.
-   If the user has no assigned applications, opening Legacy Apps
    displays an empty-state message.

Approved empty-state wording:

**No Application Access**\
"You haven't been assigned access to any legacy applications yet. Please
contact your Concord administrator for assistance."

No legacy application access does not mean no Concord account access.

### ULA-003: Grant Sets are managed inside Legacy Apps Access

-   The Legacy Apps Access page has a `Grant Sets` action near the page
    heading/breadcrumb area.
-   Grant Sets are created and edited in a dialog.
-   A Grant Set has a name, short description, and selected legacy
    applications.
-   The application picker uses selectable application cards/boxes.
-   Selected applications are visually distinct (the agreed mockup used
    green).
-   Editing a Grant Set opens the same picker with its current
    applications selected.
-   Saving commits the changes.
-   Grant Set names are administrator-defined labels. They may describe
    a department, office, common user profile, or another useful
    grouping.
-   Examples include Generic Teacher, Generic Staff, Finance Department
    Access, Registrar Office Access, Library Personnel Access, and
    Administrative Office Access.

The exact discard-on-close behavior and deletion/archive behavior have
not been separately approved. Do not silently choose destructive
behavior.

### ULA-004: Grant Sets are reusable templates, not live access policies

-   A Grant Set is a reusable starting configuration.
-   Applying a Grant Set copies/grants its selected applications into
    individual user application assignments.
-   The user's individual application assignments are the source of
    truth for current access.
-   Users can be customized independently after a Grant Set is applied.
-   Adding or removing an application in a Grant Set does not
    automatically change users who received it previously.
-   Multiple Grant Sets may be applied to the same user. Their
    applications accumulate.
-   A manually granted application can be added to a user's access
    independently.
-   An application can be revoked for one user without affecting other
    users or the Grant Set.

### ULA-005: Reapplying a Grant Set grants its selected applications again

-   If an application was previously revoked and is included in a Grant
    Set, applying that Grant Set again grants the application again.
-   Reapplication does not remove applications that are not in the
    selected Grant Set.
-   The administrator may revoke an application again afterward.

### ULA-006: Applying a Grant Set is additive

-   Applying a Grant Set to an existing user only grants the
    applications included in that set.
-   Existing application assignments remain unchanged.
-   Grant Set application never implicitly revokes or replaces existing
    access.
-   Revocation is a separate, explicit administrator action.

### ULA-007: Batch Assign is a two-stage, mutually exclusive workflow

The Legacy Apps Access page provides a `Batch Assign` action.

**Stage 1: Select users** - The administrator selects multiple existing
users. - The administrator proceeds to the assignment method.

**Stage 2: Choose exactly one assignment method** - `Apply Grant Set`:
show the saved Grant Sets and let the administrator choose a Grant
Set. - `Assign Individual Applications`: show the registered legacy
applications and let the administrator choose applications.

The two methods are mutually exclusive in one batch operation. Do not
combine a Grant Set and individual application selection in the same
batch operation.

After selection, the administrator reviews and confirms the batch
operation.

Batch assignment is additive. Existing user assignments are preserved.
No revocation is performed by Batch Assign.

### ULA-008: Individual customization remains available after batch assignment

Batch Assign is a convenience for applying common access. It does not
replace individual user access management.

After a batch operation, the administrator can open any user's own
access record and grant or revoke applications independently.

### ULA-009: Individual user access shows Grant Set application context and actual assignments

The individual user's Legacy Apps Access view follows Option A:

-   Show the Grant Set(s) applied to the user.
-   Show the user's actual individual application assignments
    separately.
-   Show applications added outside a Grant Set as individual
    assignments.
-   The Grant Set display is informational/history context only. It does
    not control current access.
-   The current application assignment remains authoritative.

### ULA-010: Show a grant date for each currently granted application

-   Each currently granted application displays its `Granted On` date.
-   Applications granted by a Grant Set use the date the Grant Set was
    applied, if that application was not already active.
-   Individually granted applications use the date the individual grant
    was made.
-   Each application's grant date is maintained independently.

### ULA-011: Reapplying an already-active Grant Set does not reset an application's original grant date

Example: - Library is granted to Alex on October 20. - The same Grant
Set is applied again on October 25. - Library remains active and its
`Granted On` date remains October 20. - The later Grant Set application
is still recorded as an administrative event.

The current active grant date must not be overwritten by a redundant
grant.

### ULA-012: Preserve grant and revocation events for future auditing

-   IAM must retain when application access was granted and when it was
    revoked.
-   Revocation must not delete the historical grant record.
-   The current User Access view shows current access and its grant
    date, not the full event timeline.
-   A future Access Audit feature may display the full grant/revoke
    timeline.
-   Historical events should identify the affected user, application,
    event type, timestamp, and administrator responsible.
-   The audit record should also preserve relevant context, such as
    whether the grant came from an individual action, Grant Set
    application, or batch assignment, where available.

A grant that is revoked and later granted again creates a new access
period/event. The history must retain both periods. The precise
persistence model is an implementation design item to be reviewed before
migration.

## 4. User Management scope for the initial release

The current project already has an identity foundation with separate
`people` and `users` records, person types, user creation, account
listing, deactivation, and disabling.

For the initial operational release discussed here: - The administrator
must be able to create employee accounts manually because HRIS is not
yet ready. - This is an interim provisioning path. It does not make IAM
the long-term owner of detailed employee/employment data. - HRIS may
later provide employee identity/onboarding data and lifecycle changes. -
User creation and legacy application assignment remain separate
workflows. - A user's Concord account status and their legacy
application assignments are separate concepts.

The current implementation's `is_it_admin` authorization checks and the
separate `platform_administrator` designation must be reviewed for
consistency before adding or extending administrator-only endpoints. Do
not infer that these are interchangeable.

## 5. UI behavior summary

### Legacy Apps Access landing page

-   Displays existing users and their current legacy application access.
-   Has `Grant Sets` and `Batch Assign` actions near the
    heading/breadcrumb area.
-   Provides a path to open an individual user's access record.

### Grant Set dialog

-   Create or edit a Grant Set.
-   Capture name and short description.
-   Select registered applications using selectable cards/boxes.
-   Show selected applications distinctly.
-   Save the Grant Set.

### Batch Assign dialog

-   Stage 1: select users.
-   Stage 2: choose one method, Grant Set or Individual Applications.
-   Show only the list relevant to the chosen method.
-   Review and confirm.
-   Do not revoke existing assignments.

### Individual user access

-   Show the Grant Set application context.
-   Show the final/current application assignments.
-   Show `Granted On` for each active application.
-   Permit individual grants and revocations.
-   Keep the full audit timeline out of this page for the initial
    release.

### Empty state

A user with no application assignments still has a valid Concord
account. Show the approved No Application Access message when the user
opens Legacy Apps.

## 6. Explicitly out of scope for this release

-   Full future Access Audit timeline UI.
-   Office-based delegation and capability-level grants.
-   Role and permission redesign.
-   Automatic propagation of Grant Set edits to previously assigned
    users.
-   HRIS onboarding integration.
-   Direct authorization inside every legacy application, unless
    separately scoped and approved.
-   Parent/student relationship management and unrelated identity
    workflows.

## 7. Decisions requiring implementation review before schema is finalized

These are not permission to silently choose an architecture. IAM must
surface them for approval if they materially affect the implementation:

1.  Exact representation of Grant Set application history and how the UI
    identifies the Grant Set(s) previously applied.
2.  How an application grant records its origin when the same
    application may be included in multiple Grant Sets or also granted
    individually.
3.  Whether Grant Sets can be deleted, archived, or only deactivated.
4.  Whether closing a dirty Grant Set dialog discards changes after
    confirmation.
5.  Exact endpoint naming and response envelope, aligned with existing
    API conventions.
6.  The authoritative platform Administrator guard for new endpoints,
    given the current code's `is_it_admin` checks and separate
    `platform_administrator` table.

------------------------------------------------------------------------

**Maintenance rule:** Update this document when a decision changes. Mark
the previous decision as superseded rather than silently rewriting
history.

------------------------------------------------------------------------

## 8. Reconciliation record: approved decisions resolved (2026-10-31)

The following items previously listed as requiring implementation review
are now resolved by the user's approved decisions and are not open
design questions:

1.  **Grant Set application history:** retain every application event
    with Grant Set ID, target user ID, administrator actor, timestamp,
    and exact snapshot of application IDs. In the User Access view, show
    each Grant Set once with First Applied and Last Applied dates.
2.  **Grant origin:** current access is represented by active individual
    grant periods. Preserve grant/revoke events and source context
    (`individual`, `grant_set`, or `batch`). A Grant Set is historical
    context, not the live source of truth for access.
3.  **Grant Set lifecycle:** archive, do not delete. Archived sets
    remain viewable historically, can be restored, and are unavailable
    for new assignments while archived. Restoring a set does not grant
    access.
4.  **Inactive applications:** preserve them in existing Grant Set
    configuration and history, visibly marked inactive. Do not allow new
    grants while inactive. Existing grants remain stored and become
    effective again if the app is reactivated.
5.  **Dirty dialog behavior:** Cancel/discard behavior is governed by
    the approved UI interaction: pending individual-access changes are
    discarded on Cancel, with confirmation when there are unsaved
    changes.
6.  **API paths and envelopes:** endpoint names and payloads are
    implementation details. They must follow existing IAM route
    conventions and be frozen only after implementation and tests. No
    candidate endpoint in a draft is an available API.
7.  **Administrator authority:** the singleton `platform_administrator`
    row with id `1`, evaluated by
    `PlatformAdministratorService::isDesignated(userId)`, is the only
    platform Administrator authority. `is_it_admin`, username, email,
    and person type are not authority sources.

### Additional implementation invariants

-   Applying a Grant Set is additive. It never revokes or replaces
    existing app grants.
-   Applying the same set again is recorded as a new application event.
    Already-active app grant dates do not change.
-   Regranting an app after revocation creates a new active grant
    period.
-   Applying a set that includes an inactive app preserves the set's
    original configuration but does not create a new grant for that
    inactive app.
-   Batch assignment selects multiple users, then exactly one method:
    one saved Grant Set or an explicit list of individual apps. It
    requires review/confirmation and is additive.
-   Account deactivation/disable and password reset revoke all active
    sessions. They do not delete or rewrite app grant history.
-   User Management and Legacy Apps Access remain separate interfaces
    and workflows.
-   No delegated administrators and no permanent user deletion are part
    of this release.

The earlier Section 7 "Decisions requiring implementation review" is
superseded by this reconciliation record. It must not be used to reopen
the decisions above.

------------------------------------------------------------------------

## 9. Implementation status note (2026-10-31)

This section records implementation progress only. It does not change or
reopen the approved product and architecture decisions above.

-   Administrator authority replacement is reported complete. The
    singleton `platform_administrator` designation, evaluated by
    `PlatformAdministratorService::isDesignated(userId)`, remains the
    sole platform Administrator authority.
-   Credential lifecycle is reported complete. The focused verification
    was confirmed as 16 tests and 54 assertions with no failures.
-   User Management completion is in progress.
-   The reviewed `User` model uses Laravel's `hashed` cast for
    `password`; `must_change_password` is fillable, boolean-cast, and
    hidden from serialized output.
-   The migration
    `2026_10_31_071359_add_must_change_password_to_users_table.php`
    gives `must_change_password` a default of `false`.
-   The reviewed `UserCreationService::create()` and
    `provisionForPerson()` methods do not explicitly set
    `must_change_password`. This observation is not yet a decision to
    change either path. The current repository must be inspected to
    establish all callers and intended password modes.
-   A search of `app` and `routes` found
    `UserCreationController::store()` as the caller of
    `UserCreationService::create()`. No caller of `provisionForPerson()`
    was found in those locations.
-   Current repository ZIP upload is pending. The next implementation
    review must reconcile these observations against the uploaded
    current working tree.

No approved decision in Sections 1 through 8 is superseded by this
status note.


---

## 10. User Management implementation reconciliation (2026-10-31)

This section records implementation status. It does not replace or silently rewrite the approved decisions in Sections 1–9.

### 10.1 Completed backend scope

User Management backend implementation is complete. The following are implemented and covered by the full IAM test suite:

- User listing, search by username/person name, status filtering, and pagination.
- Administrator user detail retrieval.
- Editing personal information, username, person types, and student number.
- Account creation validation and initial-password policy.
- Administrator password reset with generated or custom temporary password.
- Forced password change at first login for temporary credentials.
- Disable, deactivate, reactivate, and reinstate account operations.
- Audit coverage for create, update, password reset, disable, deactivate, reactivate, and reinstate.

Verification: **48 tests passed, 233 assertions, zero failures** (2026-10-31).

### 10.2 Account lifecycle clarification

The implementation uses distinct operations and endpoints:

- Disable: Active → Disabled. Temporary restriction.
- Deactivate: Active → Deactivated. Ends the active institutional relationship while retaining identity and history.
- Reactivate: Disabled → Active.
- Reinstate: Deactivated → Active.

The designated Administrator can directly reactivate a disabled account and reinstate a deactivated account now. A future due-process reinstatement workflow may govern ordinary users, but it must never restrict the designated Administrator. Reinstatements, including Administrator bypasses, are audited.

Restoring an account does not restore sessions revoked during disable/deactivation. The user must authenticate again.

Current implementation restriction: Disable and Deactivate reject the designated Administrator as the target. Do not extend that restriction to unrelated account-management operations by assumption.

### 10.3 Frontend handoff boundary

User Management and Legacy Apps Access remain separate features and management areas. User Management is ready for frontend implementation using the standalone document:

`Concord-IAM-Frontend-Handoff-User-Management.md`

The combined frontend handoff remains the source for the approved Legacy Apps Access product behavior, but its Legacy Apps APIs remain unavailable until the later backend milestones are implemented and verified.

### 10.4 Historical status notes

Earlier status notes in this document that describe User Management as incomplete, or describe specific features as missing, are historical checkpoints from before implementation completion. Preserve them for traceability, but use this Section 10 and the current roadmap for present status.

### 10.5 Maintenance rule

When an approved decision changes, record the new decision and explicitly mark the previous decision as superseded. When implementation status changes, update the status/reconciliation section without deleting the original decision history.
