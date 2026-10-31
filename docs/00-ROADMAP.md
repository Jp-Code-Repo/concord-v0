# Concord IAM User Management & Legacy Apps Access Roadmap

Last updated: 2026-10-31
Current phase: Backend implementation. Milestones 1, 2, and 3 complete; Milestone 4 is next.
Main roadmap
- [x] Inspect supplied working rules and approved requirements.
- [x] Audit the v0.5 IAM source against the approved requirements.
- [x] Resolve previously open decision items using the user's approved decisions.
- [x] Update the decisions, backend plan, and frontend handoff drafts.
- [x] Step 1: Replace legacy-flag Administrator authorization with singleton designation authority and add regression tests.
- [x] Step 2: Add force-password-change persistence, endpoint, and session gate.
- [x] Step 3: Complete User Management APIs and account lifecycle.
- [ ] Step 4: Add Legacy Apps grant-period and immutable event persistence. ← Current
- [ ] Step 5: Implement Grant Set lifecycle and archive/restore.
- [ ] Step 6: Implement individual access read/save.
- [ ] Step 7: Implement additive Grant Set application and history summaries.
- [ ] Step 8: Implement additive Batch Assign.
- [ ] Step 9: Integrate per-user effective apps into login, /me, and Legacy Apps.
- [ ] Step 10: Complete backend regression and API verification.
- [ ] Step 11: Freeze the frontend API handoff from verified responses.
- [ ] Step 12: Implement User Management frontend.
- [ ] Step 13: Implement Legacy Apps Access frontend.
- [ ] Step 14: Complete frontend regression and end-to-end verification.
Guardrails
- User Management and Legacy Apps Access remain separate features.
- The singleton platform_administrator designation is the only platform Administrator authority.
- is_it_admin is not an authorization source.
- GrantController (office/Block grants) and AppCatalogController (internal apps/groups/Blocks) are not the external Legacy Apps feature.
- Individual grants are authoritative; Grant Sets are templates and historical context.
- Applying sets and batch assignments are additive. No implicit revocation.
- Inactive apps cannot be newly granted but remain in historical configurations.
- No permanent user deletion, delegated administrators, HRIS integration, or roles/permissions redesign.
- Frontend API integration waits for verified backend contracts.
Implementation checkpoint (2026-10-31)
Verified milestone status
- Milestone 1, Replace legacy Administrator authorization: Complete.
  The singleton platform_administrator designation is the sole platform Administrator authority. Legacy is_it_admin is not an authorization source.
- Milestone 2, Credential lifecycle: Complete.
  The focused verification was confirmed as 16 tests, 54 assertions, no failures.
- Milestone 3, Complete User Management: Complete.
  User listing, search, status filters, pagination, details, editing, account creation validation, initial-password policy, Administrator password reset, account status lifecycle, and audit coverage have been implemented and verified.
Milestone 3 implementation coverage
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
Account lifecycle decisions confirmed during implementation
- Disable is a temporary restriction. The Administrator can reactivate a disabled account.
- Deactivate ends the active institutional relationship while retaining the identity record and history. The Administrator can directly reinstate a deactivated account now.
- Reactivation and reinstatement are distinct operations and endpoints:
  - Reactivate: Disabled → Active.
  - Reinstate: Deactivated → Active.
- A future due-process reinstatement workflow may govern ordinary users. It must never restrict the designated Administrator, who can bypass that process.
- Reinstatements, including Administrator bypasses, must be audited.
- Restoring an account does not restore previously revoked sessions.
Milestone 3 verification evidence
The complete IAM test suite was run after resolving one test-only throttling conflict in UserManagementListingTest.
- Final result: 48 tests passed, 233 assertions, zero failures.
- The listing test disables Laravel's ThrottleRequests middleware within the test class. Production login rate limiting was not changed.
- Account creation audit coverage exercises the HTTP endpoint and verifies the identity.user_created audit record.
- Account update audit coverage verifies identity.user_updated.
- Password reset, disable, deactivate, reactivate, and reinstate audit events also have test coverage.
Next milestone
Milestone 4: Legacy Apps persistence. Add grant-period and immutable access-event persistence.

Keep the approved boundary: IAM owns platform identity and access grants; business services own module-specific record-level authorization.
Historical source-review checkpoint
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
Working rules for continuing implementation
- Continue from the current milestone. Do not restart product discovery or repeat completed milestones.
- Inspect current repository source before proposing changes. The repository is the source of truth.
- Follow approved architecture and decisions. Do not redesign or refactor unless explicitly requested.
- Work one verified implementation action at a time and wait for the user's confirmation before proceeding.
- Ask the user before making unresolved architectural or product decisions.
- Keep implementation step numbering sequential within the active work while preserving the 14-milestone roadmap numbering.
- Verify changes with focused tests, then run broader regression tests at appropriate checkpoints.
- Keep documentation aligned with approved decisions and verified implementation. Do not present historical v0.5 findings as current-source facts.


### Inspection pause

The user is uploading the current repository ZIP to Project sources so
subsequent source review can be performed directly against the
repository rather than by repeatedly requesting pasted files. Do not
treat the v0.5 archive audit as a substitute for inspecting that current
repository. No code changes were made during the source-review steps
above.

**Next action after the current repository ZIP is available:** inspect
the current repository and reconcile this checkpoint against the actual
working tree before continuing Step 3. Do not repeat already completed
environment setup or reimplement Steps 1 and 2.


---

## Current handoff checkpoint (2026-10-31)

### Current state

- Backend Milestones 1–3 are complete.
- Milestone 3 (User Management) is complete and verified.
- The complete IAM test suite passed: **48 tests, 233 assertions, zero failures**.
- The dedicated User Management frontend handoff is `Concord-IAM-Frontend-Handoff-User-Management.md`.
- Frontend User Management implementation may proceed against that handoff, subject to the explicitly listed response-contract follow-ups in its Section 8.2.
- Legacy Apps Access remains a separate backend workstream. Milestone 4 is current; no Legacy Apps persistence or Grant Set implementation should be inferred from User Management completion.

### Current milestone 4 scope

Implement Legacy Apps grant-period and immutable event persistence. Continue to preserve the approved decisions in the Decisions document and Backend Implementation Plan. Do not mix this work into User Management frontend implementation.

### Documentation map

| File | Purpose |
|---|---|
| `00-ROADMAP.md` | Current milestone, progress, and next work |
| `01-CONCORD-IAM-USER-MANAGEMENT-AND-LEGACY-ACCESS-DECISIONS.md` | Approved product and architecture decisions |
| `02-CONCORD-IAM-BACKEND-IMPLEMENTATION-PLAN.md` | Backend implementation sequence, guardrails, and verification |
| `03-CONCORD-FRONTEND-HANDOFF-USER-MANAGEMENT-AND-LEGACY-ACCESS.md` | Combined handoff preserving both feature areas |
| `04-CONCORD-IAM-SOURCE-TO-REQUIREMENTS-AUDIT.md` | Historical v0.5 source audit plus current reconciliation |
| `Concord-IAM-Frontend-Handoff-User-Management.md` | Standalone frontend handoff for User Management only |

### Continuation rules

- Treat the repository's current working tree and verified tests as the implementation source of truth.
- Treat v0.5 audit observations and earlier checkpoints as historical evidence only where superseded by later reconciliation.
- Preserve useful historical notes for traceability. Do not silently erase old findings; label them as historical or superseded.
- Do not reopen approved decisions without a concrete implementation conflict and the user's approval.
- Continue one verified implementation action at a time, waiting for confirmation between steps.
