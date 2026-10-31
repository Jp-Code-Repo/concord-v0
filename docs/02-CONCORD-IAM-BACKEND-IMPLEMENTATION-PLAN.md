# Concord IAM Backend Implementation Plan

## User Management and Legacy Apps Access

**Project:** Concord v0\
**Service:** `services/iam`\
**Status:** Backend Milestones 1–3 complete; Milestone 4 Legacy Apps persistence is current\
**Reference baseline:** `concord-v0-05.zip`, uploaded 2026-10-30; local
laptop baseline independently reported migrated and passing\
**Working mode:** One verified step at a time. Do not start a later step
until the current step is verified and the user is satisfied.

------------------------------------------------------------------------

## 1. Objective

Deliver the backend capabilities required for Concord to operate
initially with IAM and legacy application access management, without
depending on HRIS.

The work has two related but separate administration areas:

1.  User Management: create, list, inspect, and manage Concord accounts.
2.  Legacy Apps Access: manage Grant Sets, individual application
    grants, batch assignments, and durable grant/revocation history.

## 2. Repository facts observed in v0.5

The following are present in the uploaded repository:

-   Laravel 12 service at `services/iam`.
-   API route prefix `/api/iam`.
-   `people` and `users` are separate tables.
-   `users.person_id` is a unique ULID relationship to `people`.
-   Person types include `employee`, `student`, `parent_guardian`, and
    `visitor`.
-   `UserCreationService` creates a person and account atomically and
    returns an initial password once.
-   `GET /api/iam/admin/users` lists users.
-   `POST /api/iam/admin/users` creates a user.
-   `POST /api/iam/users/{user}/deactivate` deactivates an account.
-   `POST /api/iam/users/{user}/disable` disables an account.
-   The `legacy_apps` directory is stored through the `concord_platform`
    connection, not the IAM default database.
-   `GET /api/iam/legacy-apps` returns active legacy app directory
    records.
-   Login and `GET /api/iam/me` include an `access.legacy_apps` list.
-   `AccessService` currently returns the active application directory
    to all users. It does not implement per-user legacy application
    authorization.
-   Existing audit infrastructure includes `audit_logs` and
    `App\Support\Audit`.

Relevant source paths: - `services/iam/routes/api.php` -
`services/iam/app/Http/Controllers/Admin/UserCreationController.php` -
`services/iam/app/Http/Controllers/UserManagementController.php` -
`services/iam/app/Http/Controllers/LegacyAppController.php` -
`services/iam/app/Services/UserCreationService.php` -
`services/iam/app/Services/AccessService.php` -
`services/iam/app/Models/User.php` -
`services/iam/app/Models/Person.php` -
`services/iam/database/migrations/2026_10_24_000001_create_iam_identity_foundation.php` -
`services/iam/database/migrations/2026_10_26_064350_create_legacy_apps_table.php`

### Important current gaps

1.  `GET /api/iam/legacy-apps` is a directory endpoint, not an
    authorization-filtered per-user endpoint.
2.  The login `/me` access payload currently exposes the active
    directory to all authenticated users.
3.  No Grant Set model or CRUD API is present in the inspected v0.5
    routes/models/migrations.
4.  No per-user legacy application grant/revoke API is present.
5.  No batch assignment API is present.
6.  Existing user creation validates person types but the current
    controller's authorization uses `$actor->is_it_admin`.
7.  `platform_administrator` and `PlatformAdministratorService` exist
    separately. The distinction between this designation and the legacy
    `is_it_admin` flag must be resolved for the new endpoints before
    implementation.
8.  The current `audit_logs` table can record events, but the scoped
    requirement needs durable grant/revoke history that can be queried
    and retained independently of the current active assignment.

## 3. Implementation guardrails

-   Repository is the source of truth. Inspect the current checked-out
    repository before editing; v0.5 is the baseline supplied for this
    plan, not proof that the working branch is unchanged.
-   Follow current Laravel 12 and service conventions.
-   Keep `legacy_apps` in `concord_platform`. Do not create a duplicate
    application registry in the IAM database.
-   Keep per-user access assignment and Grant Set management in IAM.
-   Do not redesign Roles, Permissions, Spatie, office delegation, or
    capability authorization as part of this task.
-   Do not make Grant Sets live policies. They are copied/applied as
    templates.
-   Do not delete grant/revoke history.
-   Do not let batch assignment revoke access.
-   Do not allow frontend-only authorization.
-   All grant/revoke writes must be atomic and audited.
-   Do not invent endpoint contracts as implemented. Final response
    schemas must be verified from the controllers and tests.
-   Preserve the user's requirement to approve significant architecture
    decisions before proceeding.
-   Use globally sequential implementation step numbers during
    execution.
-   Present each execution step using the supplied STEP PRESENTATION
    format. One action per step, verify state-changing work, then wait.

## 4. Main implementation roadmap

### Phase A: Verify current baseline and settle the remaining design gates

**Step 1. Inspect the working repository and confirm the v0.5
baseline** - Confirm branch/commit and working tree. - Re-read the
current route file, relevant controllers, models, services, migrations,
middleware, tests, and database connection config. - Confirm whether any
changes after the uploaded archive already exist. - Do not request files
that are already available.

**Step 2. Confirm the Administrator authorization source** - Inspect
`RequireItAdmin`, `PlatformAdministratorService`, and current account
provisioning. - Decide whether the scoped endpoints must use the central
`platform_administrator` designation or an existing authorization
convention. - Do not silently treat `is_it_admin` as equivalent to the
singleton Administrator designation. - Record the approved choice.

**Step 3. Approve the access-history and Grant Set provenance model** -
Confirm how the UI will show Grant Set application history. - Confirm
how a current app grant will identify its origin when multiple Grant
Sets or individual grants overlap. - Confirm how Grant Set
edits/deactivation affect historical application records. - Confirm
whether Grant Sets can be deleted or only deactivated. - Approve table
responsibilities and lifecycle before migration work.

### Phase B: Complete User Management for the interim IAM-only launch

**Step 4. Verify and complete administrator user listing** - Preserve
existing pagination and identity response conventions. - Confirm listing
includes account status, person identity, person types, and student
number where applicable. - Determine whether filters/search are required
for the agreed frontend page. Ask before expanding scope. - Add feature
tests for pagination and response shape.

**Step 5. Complete manual employee account creation** - The current
create endpoint accepts the existing person fields and person types. -
Verify employee can be selected as a person type and created without
HRIS. - Verify username uniqueness, student-specific validation,
password generation, and atomic person/account creation. - Ensure the
initial password is returned only once and is never logged. - Add tests
for employee creation and validation failures.

**Step 6. Verify individual account detail and status management** - Add
or complete an administrator-only user detail endpoint if the frontend
needs one. - Preserve separate account status semantics: active,
deactivated, disabled. - Ensure status actions revoke sessions as
already implemented. - Confirm protected endpoints cannot
disable/deactivate the designated platform Administrator. - Add tests
for unauthorized actors, invalid transitions, and Administrator
protection.

### Phase C: Build Legacy Apps Access backend

**Step 7. Define and approve the data model** At minimum, the data model
must support: - Grant Sets and their selected legacy app references. - A
user's current active app assignments. - Grant and revocation history
with actor and timestamp. - Grant Set application history sufficient to
display which set(s) were applied. - A way to associate set application
events with the app grants they produced, without making the Grant Set a
live access dependency.

Potential table concepts for review, not yet approved names: -
`legacy_app_grant_sets` - `legacy_app_grant_set_items` -
`user_legacy_app_grants` or an append-only access-grant history table -
`legacy_app_grant_set_applications` - `legacy_app_access_events`

Do not create both a mutable assignment table and an event table
redundantly without defining which is authoritative. A valid design may
use a current-state grant row with grant/revoke timestamps and immutable
event rows, or a fully append-only grant-period model with an
active-period query. Select one after review.

References to `legacy_apps.id` are cross-database identifiers. Do not
add a cross-database foreign key. Validate that referenced app IDs exist
in `concord_platform` and are active where required.

**Step 8. Implement Grant Set management** - List Grant Sets. - Create
Grant Set with name, description, and selected active legacy app IDs. -
Retrieve one Grant Set for editing. - Update Grant Set details and
selected app list. - Preserve Grant Set application history if a Grant
Set is later renamed or deactivated. - Grant Set edits must not mutate
existing users' assignments. - Add validation, transaction handling, and
tests.

**Step 9. Implement individual user access retrieval** - Retrieve a user
with their current active legacy app assignments. - Include `granted_at`
for each currently granted application. - Include Grant Set application
history/context required by the approved Option A display. - Include
individual grants not associated with a Grant Set. - Return only active
directory applications for current selectable/access presentation, while
retaining historical references for audit even if a directory record
later becomes inactive. - Add tests for empty access, mixed grant
sources, and inactive directory entries.

**Step 10. Implement individual grant and revoke** - Grant one app to
one user. - Revoke one app from one user. - A grant to an already-active
app must not reset its original `granted_at`. - Reapplying a Grant Set
must grant an app again if it had been revoked. - Revocation must
preserve the previous grant period and record `revoked_at` and
`revoked_by`. - A later re-grant must create a new access
period/event. - All changes must record the administrator actor and
request context. - Add tests for grant, duplicate grant, revoke,
duplicate revoke, and re-grant.

**Step 11. Implement Grant Set application** - Apply one Grant Set to
one user. - Grant all selected apps additively. - Preserve unrelated
existing assignments. - Do not reset the grant date for already-active
apps. - Record that the Grant Set was applied, including actor and
timestamp. - Preserve sufficient information to display historical set
application context even after the Grant Set itself is edited. - Add
tests for overlap, reapplication, previously revoked app, and Grant Set
edits after application.

**Step 12. Implement Batch Assign** - Accept multiple user IDs and
exactly one assignment method. - Method `grant_set`: accept one Grant
Set ID only. - Method `individual_apps`: accept a list of legacy app IDs
only. - Reject payloads that mix both methods. - Validate all users and
app/set references before applying. - Apply additively and atomically
according to an explicitly approved transaction strategy. - Return a
useful per-user result or a single clear success response. Define
partial-failure behavior before implementation. - Add tests for multiple
users, existing grants, invalid IDs, mixed methods, and no-revocation
behavior.

**Step 13. Integrate the authenticated user's effective legacy app
list** - Change the current behavior that exposes every active legacy
app to every user. - `/login` and `/me` must return only the user's
currently granted legacy applications, subject to account status. - A
user with zero grants receives an empty array and remains able to
authenticate and navigate Concord. - Keep the existing `access.apps` and
`access.offices` compatibility fields unchanged unless separately
approved. - Invalidate the per-user access cache after
grant/revoke/account changes. - Add tests proving two users receive
different app lists and a zero-access user receives an empty list.

### Phase D: Verification and frontend contract

**Step 14. Add authorization and audit regression coverage** -
Non-administrator users cannot manage users, Grant Sets, or other users'
access. - A user cannot grant or revoke access through the admin
endpoints. - A user with no legacy app grants can still authenticate. -
Grant and revoke events preserve actor, target, app, timestamps, and
source context. - The platform Administrator cannot be locked out by
ordinary account status operations. - Verify direct destination
authorization is not falsely claimed to be enforced by the Concord link
list.

**Step 15. Verify in Docker/MySQL and freeze the API contract** - Run
migrations using the project's Docker conventions. - Run focused feature
tests, then the full IAM suite. - Verify route registration and actual
HTTP responses. - Verify cache invalidation and session behavior. -
Update the frontend handoff with the final endpoint methods, paths,
request/response payloads, and error cases. - Mark each endpoint as
verified only after an actual request/test confirms it.

------------------------------------------------------------------------

## 5. Existing API baseline

These endpoints are present in the v0.5 route file:

  ------------------------------------------------------------------------------------------
  Method            Path                                 Current purpose   Status
  ----------------- ------------------------------------ ----------------- -----------------
  POST              `/api/iam/login`                     Authenticate and  Existing
                                                         return            
                                                         session/access    
                                                         payload           

  POST              `/api/iam/logout`                    End current       Existing
                                                         session           

  GET               `/api/iam/me`                        Return current    Existing
                                                         authenticated     
                                                         user and access   
                                                         payload           

  GET               `/api/iam/admin/users`               Paginated         Existing
                                                         administrator     
                                                         user list         

  POST              `/api/iam/admin/users`               Create person and Existing
                                                         user account      

  POST              `/api/iam/users/{user}/deactivate`   Deactivate        Existing
                                                         account           

  POST              `/api/iam/users/{user}/disable`      Disable account   Existing

  GET               `/api/iam/legacy-apps`               Return active     Existing
                                                         platform app      
                                                         directory, not    
                                                         per-user          
                                                         authorization     
  ------------------------------------------------------------------------------------------

The routes are protected by `session.auth`; administrator routes use
`it.admin`. The current controller code also checks `is_it_admin`.
Verify and resolve the authoritative administrator designation before
expanding these routes.

## 6. Candidate API surface for the new work

The following is a planning proposal, not an implemented or approved
contract. Finalize it after the data model and authorization gate are
approved.

  -----------------------------------------------------------------------------------------------------------------------
  Method                  Candidate path                                                          Purpose
  ----------------------- ----------------------------------------------------------------------- -----------------------
  GET                     `/api/iam/admin/legacy-apps`                                            List active app
                                                                                                  directory for
                                                                                                  administrator
                                                                                                  assignment UI

  GET                     `/api/iam/admin/legacy-apps/access`                                     List users with their
                                                                                                  current access summary

  GET                     `/api/iam/admin/users/{user}/legacy-apps`                               Read one user's current
                                                                                                  access and Grant Set
                                                                                                  application context

  GET                     `/api/iam/admin/legacy-apps/grant-sets`                                 List Grant Sets

  POST                    `/api/iam/admin/legacy-apps/grant-sets`                                 Create a Grant Set

  GET                     `/api/iam/admin/legacy-apps/grant-sets/{grantSet}`                      Read one Grant Set

  PATCH                   `/api/iam/admin/legacy-apps/grant-sets/{grantSet}`                      Update a Grant Set

  POST                    `/api/iam/admin/users/{user}/legacy-apps/{app}/grant`                   Grant one application

  POST                    `/api/iam/admin/users/{user}/legacy-apps/{app}/revoke`                  Revoke one application

  POST                    `/api/iam/admin/users/{user}/legacy-apps/grant-sets/{grantSet}/apply`   Apply one Grant Set to
                                                                                                  one user

  POST                    `/api/iam/admin/legacy-apps/batch-assignments`                          Batch grant one set or
                                                                                                  a list of individual
                                                                                                  apps to selected users
  -----------------------------------------------------------------------------------------------------------------------

Do not implement a route from this table until its path and payload are
approved. Use the existing `/api/iam` prefix and session/admin
middleware conventions.

## 7. Acceptance criteria

The scoped backend is ready for frontend integration when:

-   An administrator can create an employee account without HRIS.
-   User listing and account status behavior are verified.
-   The platform app directory is read from `concord_platform`.
-   Grant Sets can be created, listed, read, and updated.
-   An administrator can inspect one user's current access and Grant Set
    context.
-   Individual grant and revoke work and preserve history.
-   Grant Set application is additive and repeat-safe.
-   Batch Assign accepts exactly one method and applies it additively to
    multiple users.
-   Grant dates remain stable when an already-active grant is repeated.
-   Grant/revoke actor and timestamps are retained.
-   `/login` and `/me` expose only each user's granted apps.
-   A user with no legacy app access can still log in.
-   Feature tests and Docker/MySQL verification pass.
-   The frontend handoff contains the verified contract.

## 8. Definition of done

The task is not done merely because routes exist. It is done when the
behavior is verified through tests and real API requests, the audit
history is durable, the frontend contract is frozen, and the user
confirms satisfaction with the implementation.

------------------------------------------------------------------------

## 9. Source audit reconciliation and corrected implementation order (2026-10-31)

See `04-CONCORD-IAM-SOURCE-TO-REQUIREMENTS-AUDIT.md` for the full source
evidence and gap matrix.

### Confirmed security correction

The current `RequireItAdmin` middleware and user creation/status
controllers check `users.is_it_admin`. This is not the approved
authority. The first implementation change must centralize the route
gate on `PlatformAdministratorService::isDesignated($userId)` and add
regression coverage proving that a legacy-flag account is denied while
the designated account is allowed.

The `is_it_admin` column may remain temporarily for compatibility, but
it must not be used for platform Administrator authorization or exposed
as the frontend authority signal.

### Corrected implementation sequence

The earlier phase sequence is refined to respect the existing code
dependencies:

1.  **Administrator authority:** middleware, route protection, existing
    controller checks, and regression tests. Keep office/Block grant
    behavior out of this feature.
2.  **Credential lifecycle foundation:** add the force-password-change
    state and migration; implement password-change endpoint and session
    gating without breaking login, logout, `/me`, or preferences.
3.  **User Management completion:** list filters/search/pagination,
    detail, edit, create password modes, reset password,
    self-management, reactivate, disable/deactivate, audit, and session
    revocation.
4.  **Legacy Apps persistence:** user grant periods and immutable
    grant/revoke events, using `concord_platform.legacy_apps` as the
    directory.
5.  **Grant Sets:** create/edit/list/archive/restore and app membership;
    retain inactive apps in historical configuration.
6.  **Individual access management:** read current access and set
    context; save a combined grant/revoke selection atomically.
7.  **Grant Set application:** additive single-user application, event
    snapshot, first/last applied summaries, idempotent active grant
    dates.
8.  **Batch assignment:** multi-user, exactly one assignment method,
    review-confirmed, additive.
9.  **Effective access integration:** login, `/me`, and Legacy Apps
    listing return only active app grants; zero grants remains a valid
    login.
10. **Verification and frontend contract:** full automated tests, route
    and response verification, cache/session checks, then freeze the
    frontend handoff.

### Scope protection

-   `GrantController` is the office/Block grant controller. Do not reuse
    it for external Legacy Apps.
-   `AppCatalogController` manages the internal `apps`/groups/Blocks
    catalog. It is not the external `legacy_apps` directory.
-   `AccessService` currently builds the authentication access payload
    and returns all active legacy directory rows. Refactor its
    legacy-app portion carefully and preserve unrelated response
    fields/consumers.
-   `GrantController::store()` has an apparent pre-existing
    `Audit::log()` argument-count defect. It is outside this scope
    unless a shared audit change or regression test makes it necessary
    to address.
-   Existing login by username or uniquely matching email must remain
    supported.
-   Person email is stored on `people`, not `users`.
-   Do not introduce permanent deletion, delegated administrators, HRIS
    integration, or a roles/permissions redesign.

### Baseline verification already completed by the user

On the laptop checkout, fresh migrations completed and the baseline
suite passed: **8 tests, 14 assertions**. This is the pre-implementation
baseline. Re-run focused and full tests after each relevant code change,
not as a repeated environment setup ritual.

------------------------------------------------------------------------

## 10. Active implementation checkpoint (2026-10-31)

### Completed

**Step 1: Administrator authority** - Reported complete in the active
implementation history. - Do not repeat or redesign this work unless
inspection of the current repository reveals a concrete regression.

**Step 2: Credential lifecycle** - Reported complete. - Focused
verification confirmed by the user: **16 tests, 54 assertions, no
failures**. - The completed behavior includes password-change
enforcement, temporary-password handling, password hashing,
current-session preservation after password change, revocation of other
active sessions, and audit coverage, as previously verified in the
implementation conversation.

### Current: Step 3, User Management

The user has defined the initial User Management scope as: - User
listing, search, filtering, and pagination. - User details and
editing. - Account creation and password options. - Administrator
password reset. - Account status management. - Audit coverage.

The following current-working-tree source was reviewed from
user-provided code during the implementation conversation:

  -------------------------------------------------------------------------------------------------------------------------
  File                                                                                  Confirmed observations
  ------------------------------------------------------------------------------------- -----------------------------------
  `app/Http/Controllers/UserManagementController.php`                                   Deactivate and disable actions are
                                                                                        distinct; they protect the
                                                                                        designated Administrator, revoke
                                                                                        all target sessions, audit the
                                                                                        operation, invalidate cached
                                                                                        access, and preserve the user
                                                                                        record. No reactivation method was
                                                                                        present in this controller.

  `app/Http/Controllers/Admin/UserCreationController.php`                               `index()` provides username-ordered
                                                                                        pagination (default 25, clamped
                                                                                        1--100) and includes
                                                                                        person/type/student information.
                                                                                        Search and status filtering were
                                                                                        not present in the reviewed method.
                                                                                        `store()` validates creation data,
                                                                                        uses the designated Administrator
                                                                                        service, calls
                                                                                        `UserCreationService::create()`,
                                                                                        audits creation, and returns the
                                                                                        initial password once.

  `app/Services/UserCreationService.php`                                                `create()` atomically creates
                                                                                        Person, person types, optional
                                                                                        Student, and User.
                                                                                        `provisionForPerson()` provisions
                                                                                        an account for an existing Person.
                                                                                        Both generate a 16-character
                                                                                        initial password and set
                                                                                        `is_it_admin=false`.

  `app/Models/User.php`                                                                 `password` uses Laravel's `hashed`
                                                                                        cast. `must_change_password` is
                                                                                        fillable, boolean-cast, and hidden.

  `database/migrations/2026_10_31_071359_add_must_change_password_to_users_table.php`   `must_change_password` defaults to
                                                                                        `false`.
  -------------------------------------------------------------------------------------------------------------------------

### Caller search result

From `services/iam`, the search:
`grep -RInE 'provisionForPerson|userCreation->create|UserCreationService' app routes`

found: - `UserCreationController` imports and injects
`UserCreationService`. - `UserCreationController::store()` calls
`create()`. - No `provisionForPerson()` caller was found under `app` or
`routes`.

This does not rule out callers outside those searched paths.

### Open verification items

-   Inspect the current repository ZIP when it becomes available; the
    uploaded v0.5 archive is only a baseline.
-   Inspect all references to `must_change_password` and verify the
    intended account-creation password modes before changing
    provisioning behavior.
-   Inspect current routes, tests, and remaining User Management code to
    establish the exact gap list.
-   Determine whether search/status filtering, detail/edit, reset, and
    reactivation already exist elsewhere before implementing them.
-   Preserve the approved rule that the user must approve significant
    decisions. Do not invent reactivation semantics or password-mode
    behavior.

### Working checkpoint

No code changes were made during the source-review steps above. The next
action is current-repository inspection, not a new environment setup and
not a repeat of Steps 1 or 2.


---

## 11. Current implementation reconciliation (2026-10-31)

This section supersedes earlier progress statements that describe User Management as in progress. Earlier v0.5 observations and planning steps remain as historical context and must not be mistaken for the current implementation state.

### 11.1 Completed backend milestones

**Milestone 1: Replace legacy Administrator authorization**
- Complete.
- The singleton `platform_administrator` designation, evaluated through `PlatformAdministratorService::isDesignated(userId)`, is the sole platform Administrator authority.
- `is_it_admin` is not an authorization source.

**Milestone 2: Credential lifecycle**
- Complete.
- Temporary passwords require a password change at first login.
- Passwords are hashed.
- Successful password change preserves the current session and revokes other active sessions.
- Focused verification was confirmed as 16 tests and 54 assertions with no failures.

**Milestone 3: Complete User Management**
- Complete.
- Listing/search/filtering/pagination, details, editing, creation validation, initial-password policy, Administrator password reset, account lifecycle, and audit coverage are implemented.
- Full IAM suite: **48 tests passed, 233 assertions, zero failures**.

### 11.2 User Management behavior to preserve

- A person and a user account remain separate records, linked by `users.person_id`.
- A person may have multiple person types, including Student and Employee.
- Changing person types must not delete the Student identity record or student number.
- Account creation and password reset return temporary credentials once. Do not persist or log them.
- Password reset revokes all active sessions and requires a password change at next login.
- Disable and Deactivate are distinct from Reactivate and Reinstate.
- Reactivate only accepts Disabled; Reinstate only accepts Deactivated.
- The designated Administrator can directly perform both restoration operations now.
- A future ordinary-user reinstatement due-process process must not restrict the designated Administrator.
- Every lifecycle operation is audited. Account records and audit history are retained.

### 11.3 Current milestone

**Milestone 4: Legacy Apps persistence**

Next implementation work is grant-period and immutable access-event persistence, following the approved decisions. Keep the existing external directory in `concord_platform`; IAM owns user-to-app assignments and history. Do not conflate this work with the internal `apps`/groups/Blocks catalog or office/Block grants.

### 11.4 Frontend readiness

- User Management frontend may proceed using `Concord-IAM-Frontend-Handoff-User-Management.md`.
- The dedicated handoff intentionally isolates User Management from Legacy Apps Access.
- The combined frontend handoff retains the approved Legacy Apps UX and historical candidate endpoint discussion.
- Do not integrate Legacy Apps Access APIs until the corresponding backend milestones and response contracts are implemented and verified.
- The User Management handoff identifies specific response-contract details that need confirmation rather than guessing. These are contract clarifications, not a reason to reopen approved product decisions.

### 11.5 Historical planning material

The earlier phases and steps in Sections 4–10 are retained because they document why the implementation sequence was chosen, the original v0.5 gaps, candidate designs, acceptance criteria, and prior verification checkpoints. Where those sections say User Management work is missing or pending, read them as historical baseline notes. This Section 11 and `00-ROADMAP.md` describe current status.
