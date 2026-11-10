# Concord IAM User Management & Legacy Apps Access Roadmap

Last updated: 2026-11-10

Current phase: Backend implementation. Milestones 1–5 complete; Milestone 6 is in progress.

---

## Previous roadmap checkpoints

The following dates and statuses are preserved for historical traceability. They are superseded by the current status at the top of this document.

**2026-10-31**

Last updated: 2026-10-31

Current phase: Backend implementation. Milestones 1, 2, and 3 complete; Milestone 4 is next.

**2026-11-04**

Last updated: 2026-11-04

Current phase: Backend implementation. Milestones 1–5 complete; Milestone 6 is in progress.

---

# Main roadmap

- [x] Inspect supplied working rules and approved requirements.
- [x] Audit the v0.5 IAM source against the approved requirements.
- [x] Resolve previously open decision items using the user's approved decisions.
- [x] Update the decisions, backend plan, and frontend handoff drafts.
- [x] Step 1: Replace legacy-flag Administrator authorization with singleton designation authority and add regression tests.
- [x] Step 2: Add force-password-change persistence, endpoint, and session gate.
- [x] Step 3: Complete User Management APIs and account lifecycle.
- [x] Step 4: Add Legacy Apps grant-period and immutable event persistence.
- [x] Step 5: Implement Grant Set lifecycle and archive/restore.
- [ ] Step 6: Implement individual access read/save. ← Current
- [ ] Step 7: Implement additive Grant Set application and history summaries. (Service implementation and focused tests partially completed; API integration remains.)
- [ ] Step 8: Implement additive Batch Assign.
- [ ] Step 9: Integrate per-user effective apps into login, /me, and Legacy Apps.
- [ ] Step 10: Complete backend regression and API verification.
- [ ] Step 11: Freeze the frontend API handoff from verified responses.
- [ ] Step 12: Implement User Management frontend.
- [ ] Step 13: Implement Legacy Apps Access frontend.
- [ ] Step 14: Complete frontend regression and end-to-end verification.

## Guardrails

- User Management and Legacy Apps Access remain separate features.
- The singleton platform_administrator designation is the only platform Administrator authority.
- is_it_admin is not an authorization source.
- GrantController (office/Block grants) and AppCatalogController (internal apps/groups/Blocks) are not the external Legacy Apps feature.
- Individual grants are authoritative; Grant Sets are templates and historical context.
- Applying sets and batch assignments are additive. No implicit revocation.
- The Administrator may individually grant an inactive app. It remains unavailable until reactivated.
- Applying a Grant Set does not create a grant for an inactive app. Inactive apps remain in historical configurations.
- No permanent user deletion, delegated administrators, HRIS integration, or roles/permissions redesign.
- Frontend API integration waits for verified backend contracts.

---

# Implementation checkpoint (2026-11-10)

## Verified milestone status

- Milestone 1, Replace legacy Administrator authorization: Complete.

  The singleton platform_administrator designation is the sole platform Administrator authority. Legacy is_it_admin is not an authorization source.

- Milestone 2, Credential lifecycle: Complete.

  The focused verification was confirmed as 16 tests, 54 assertions, no failures.

- Milestone 3, Complete User Management: Complete.

  User listing, search, status filters, pagination, details, editing, account creation validation, initial-password policy, Administrator password reset, account status lifecycle, and audit coverage have been implemented and verified.

- Milestone 4, Legacy Apps persistence: Complete.

  Grant-period persistence and immutable access-event persistence have been implemented.

- Milestone 5, Grant Set lifecycle: Complete.

  Grant Set creation, membership management, listing, archiving, restoration, and corresponding service verification have been implemented.

- Milestone 6, Individual Access Management: In progress.

  Individual access-read service and minimum Grant Set application-history persistence have been implemented. Grant Set application service work has also progressed, with focused tests passing. API integration and the remaining individual-access save workflow are not yet complete.

## Milestone 3 implementation coverage

- User listing supports search by username and person name, status filtering, and pagination.
- User details can be retrieved by the Administrator.
- Personal information, username, person types, and student number can be updated.
- A person can have multiple person types, including Student and Employee.
- Changing person types does not delete the underlying Student record or student number.
- Account creation validates usernames, person types, and student-number requirements.
- IAM-generated initial passwords require a password change at first login.
- Administrator password reset supports generated and custom temporary passwords. The temporary password is returned once, stored hashed, and requires a password change.
- Account status operations distinguish disabling, deactivation, reactivation, and reinstatement.
- User creation, update, password reset, disable, deactivate, reactivate, and reinstate audit events have test coverage.

## Account lifecycle decisions confirmed during implementation

- Disable is a temporary restriction. The Administrator can reactivate a disabled account.
- Deactivate ends the active institutional relationship while retaining the identity record and history. The Administrator can directly reinstate a deactivated account now.
- Reactivation and reinstatement are distinct operations and endpoints:

  - Reactivate: Disabled → Active.
  - Reinstate: Deactivated → Active.

- A future due-process reinstatement workflow may govern ordinary users. It must never restrict the designated Administrator, who can bypass that process.
- Reinstatements, including Administrator bypasses, must be audited.
- Restoring an account does not restore previously revoked sessions.

## Milestone 3 verification evidence

The complete IAM test suite was run after resolving one test-only throttling conflict in UserManagementListingTest.

- Final result: 48 tests passed, 233 assertions, zero failures.
- The listing test disables Laravel's ThrottleRequests middleware within the test class. Production login rate limiting was not changed.
- Account creation audit coverage exercises the HTTP endpoint and verifies the identity.user_created audit record.
- Account update audit coverage verifies identity.user_updated.
- Password reset, disable, deactivate, reactivate, and reinstate audit events also have test coverage.

## Milestone 6 implementation coverage

### Completed and verified

- [x] Created `legacy_app_grant_set_application_events` migration.
- [x] Applied the migration successfully.
- [x] Created `LegacyAppGrantSetApplicationEvent` model.
- [x] Added immutable-event protection.
- [x] Added `LegacyAppGrantSet::applicationEvents()` relationship.
- [x] Implemented `LegacyAppGrantService::getUserAccess()`.
- [x] Added individual access-read service test.
- [x] Verified `LegacyAppGrantServiceTest`: 10 tests, 44 assertions, zero failures.
- [x] Implemented `LegacyAppGrantSetService::apply()`.
- [x] Added Grant Set application-history recording.
- [x] Added active/inactive application handling.
- [x] Added duplicate active-grant prevention.
- [x] Added new grant-period creation after revocation.
- [x] Added Grant Set application authorization checks at service level.
- [x] Added rejection of archived Grant Sets.
- [x] Verified `LegacyAppGrantSetServiceTest`: 13 tests, 68 assertions, zero failures.
- [x] Verified PHP syntax for `LegacyAppGrantSetService.php`.

### Verified Grant Set application behavior

The following behaviors have been covered by the focused service tests:

- Active applications in a Grant Set receive individual grants.
- Inactive applications are skipped during application.
- Inactive applications remain in the immutable application-history snapshot.
- Grant events identify `grant_set` as their source.
- Existing active grants are not duplicated when a Grant Set is reapplied.
- Reapplication creates a separate application-history event.
- Reapplying after revocation creates a new grant period instead of reactivating the old grant.
- Non-designated users cannot apply Grant Sets.
- Archived Grant Sets cannot be applied.

### Test evidence at this checkpoint

| Test suite / verification | Result |
|---|---|
| LegacyAppGrantServiceTest | 10 tests, 44 assertions, passed |
| LegacyAppGrantSetServiceTest | 13 tests, 68 assertions, passed |
| LegacyAppGrantSetService PHP syntax check | Passed |

These are focused verification results. They are not a substitute for the full IAM regression suite.

### Remaining Milestone 6 work

- [ ] Expose individual access read API.
- [ ] Expose Grant Set application API.
- [ ] Implement atomic combined grant/revoke saving.
- [ ] Add API authorization tests.
- [ ] Add API response-contract tests.
- [ ] Verify Grant Set application-history responses.
- [ ] Verify transaction rollback behavior.
- [ ] Verify immutable history behavior under the application workflow.
- [ ] Run the complete IAM regression suite.
- [ ] Update the frontend API handoff using verified responses.

### Approved implementation boundaries

- Individual active grants remain the authoritative source of current access.
- Grant Sets are reusable templates and historical context, not live access policies.
- Grant Set edits do not mutate existing user grants.
- Applying a Grant Set is additive. It never implicitly revokes existing grants.
- Every successful Grant Set application creates a separate immutable application-history event.
- Each application event preserves the Grant Set ID, target user ID, Administrator actor ID, timestamp, and exact application-ID snapshot.
- The application-ID snapshot includes configured inactive applications.
- Only active applications receive new grants during Grant Set application.
- Existing active grants are preserved without creating duplicates.
- Reapplying a Grant Set after revocation creates a new grant period.
- Current effective access and historical Grant Set context must be returned separately.
- The existing architecture, service boundaries, database ownership, and IAM conventions remain authoritative.

## Current implementation checkpoint

### Current working environment

- Repository: `Jp-Code-Repo/concord-v0`
- Branch: `main`
- Latest synchronized commit at the PC: `93696a9` (Roadmap update)
- Working tree was confirmed clean after synchronization.
- Docker services verified running:
  - `iam`
  - `mysql`
  - `redis`
  - `edge`
- MySQL reported healthy.

### Current code location

Grant Set service:

`services/iam/app/Services/LegacyAppGrantSetService.php`

Individual Legacy Apps grant service:

`services/iam/app/Services/LegacyAppGrantService.php`

Grant Set controller:

`services/iam/app/Http/Controllers/LegacyAppGrantSetController.php`

IAM routes:

`services/iam/routes/api.php`

Grant Set service tests:

`services/iam/tests/Feature/LegacyAppGrantSetServiceTest.php`

### Current API integration status

The existing Grant Set controller provides:

- Grant Set listing.
- Grant Set retrieval.
- Grant Set creation.
- Grant Set updating.
- Grant Set archiving.
- Grant Set restoration.

The existing routes are inside the `admin` route group, protected by `session.auth` and `it.admin`.

The new Grant Set application endpoint has not yet been added.

The approved endpoint is:

`POST /api/iam/admin/users/{user}/legacy-apps/grant-sets/{grantSet}/apply`

The current implementation must preserve the approved authorization boundary. The singleton `platform_administrator` designation is the sole authority for platform Administrator actions. `is_it_admin` must not become an additional authorization requirement.

The `RequireItAdmin` middleware has been identified at:

`services/iam/app/Http/Middleware/RequireItAdmin.php`

Its actual implementation still needs to be inspected and verified before finalizing the route placement. Do not assume middleware behavior based only on its name.

### Next implementation action

Inspect `RequireItAdmin.php` and confirm that the existing `admin` route group is compatible with the designated platform Administrator authorization model.

Then:

1. Add the Grant Set application controller method.
2. Add the approved application endpoint.
3. Add API authorization and response tests.
4. Verify the individual access read endpoint.
5. Continue the atomic combined grant/revoke save implementation.

Do not mark Milestone 6 complete until all required implementation and regression work is verified.

---

# Next milestone

Milestone 7: Additive Grant Set application and history summaries.

The service implementation and focused tests have already been started as part of the November 10 work. The remaining API integration and complete workflow verification must be finished before this milestone can be considered complete.

Milestone 8: Additive Batch Assign.

Continue to preserve the approved rule that batch assignment and Grant Set application are additive and do not implicitly revoke access.

---

Keep the approved boundary: IAM owns platform identity and access grants; business services own module-specific record-level authorization.

---

# Historical source-review checkpoint

The following notes preserve the original roadmap's v0.5 source-review checkpoint for traceability. They describe the earlier review state and are superseded by the verified implementation status above. They must not be used as a description of the current implementation.

During the earlier inspection, the following files were reviewed from the working tree as pasted by the user:

- app/Http/Controllers/UserManagementController.php
- app/Http/Controllers/Admin/UserCreationController.php
- app/Services/UserCreationService.php
- app/Models/User.php
- database/migrations/2026_10_31_071359_add_must_change_password_to_users_table.php

At that earlier checkpoint:

- User listing and pagination existed, but search and status filters were not present in the reviewed method.
- Account creation existed in UserCreationController::store() and delegated to UserCreationService::create().
- UserCreationService::provisionForPerson() existed, but the repository search in app and routes found no caller.
- The User model used Laravel's hashed cast for password.
- must_change_password was fillable, boolean-cast, and hidden from serialization.
- The migration defaulted must_change_password to false.
- The earlier review had not yet confirmed whether account-provisioning paths explicitly set must_change_password.

The original inspection pause noted that the user was uploading the current repository ZIP for source review. That pause is historical and is no longer the current next action. Milestones 1 and 2 were not reimplemented during the later work.

## Working rules for continuing implementation

- Continue from the current milestone. Do not restart product discovery or repeat completed milestones.
- Inspect current repository source before proposing changes. The repository is the source of truth.
- Follow approved architecture and decisions. Do not redesign or refactor unless explicitly requested.
- Work one verified implementation action at a time and wait for the user's confirmation before proceeding.
- Ask the user before making unresolved architectural or product decisions.
- Keep implementation step numbering sequential within the active work while preserving the 14-milestone roadmap numbering.
- Verify changes with focused tests, then run broader regression tests at appropriate checkpoints.
- Keep documentation aligned with approved decisions and verified implementation. Do not present historical v0.5 findings as current-source facts.

---

# Historical inspection pause (2026-10-31)

The user was uploading the current repository ZIP to Project sources so subsequent source review could be performed directly against the repository rather than by repeatedly requesting pasted files.

Do not treat the v0.5 archive audit as a substitute for inspecting that current repository.

No code changes were made during the source-review steps above.

**Original next action after the current repository ZIP was available:**

Inspect the current repository and reconcile this checkpoint against the actual working tree before continuing Step 3. Do not repeat already completed environment setup or reimplement Steps 1 and 2.

This instruction is preserved as a historical record and is superseded by the current November 10 implementation checkpoint.

---

# Historical handoff checkpoint (2026-10-31)

## Original current state

- Backend Milestones 1–3 are complete.
- Milestone 3 (User Management) is complete and verified.
- The complete IAM test suite passed: **48 tests, 233 assertions, zero failures**.
- The dedicated User Management frontend handoff is `Concord-IAM-Frontend-Handoff-User-Management.md`.
- Frontend User Management implementation may proceed against that handoff, subject to the explicitly listed response-contract follow-ups in its Section 8.2.
- Legacy Apps Access remains a separate backend workstream. Milestone 4 was current at that historical checkpoint; no Legacy Apps persistence or Grant Set implementation should be inferred from User Management completion.

## Original milestone 4 scope

Implement Legacy Apps grant-period and immutable event persistence. Continue to preserve the approved decisions in the Decisions document and Backend Implementation Plan. Do not mix this work into User Management frontend implementation.

## Documentation map

| File | Purpose |
|---|---|
| `00-ROADMAP.md` | Current milestone, progress, and next work |
| `01-CONCORD-IAM-USER-MANAGEMENT-AND-LEGACY-ACCESS-DECISIONS.md` | Approved product and architecture decisions |
| `02-CONCORD-IAM-BACKEND-IMPLEMENTATION-PLAN.md` | Backend implementation sequence, guardrails, and verification |
| `03-CONCORD-FRONTEND-HANDOFF-USER-MANAGEMENT-AND-LEGACY-ACCESS.md` | Combined handoff preserving both feature areas |
| `04-CONCORD-IAM-SOURCE-TO-REQUIREMENTS-AUDIT.md` | Historical v0.5 source audit plus current reconciliation |
| `Concord-IAM-Frontend-Handoff-User-Management.md` | Standalone frontend handoff for User Management only |

## Original continuation rules

- Treat the repository's current working tree and verified tests as the implementation source of truth.
- Treat v0.5 audit observations and earlier checkpoints as historical evidence only where superseded by later reconciliation.
- Preserve useful historical notes for traceability. Do not silently erase old findings; label them as historical or superseded.
- Do not reopen approved decisions without a concrete implementation conflict and the user's approval.
- Continue one verified implementation action at a time, waiting for confirmation between steps.