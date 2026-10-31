# Concord IAM Source-to-Requirements Audit

**Date:** 2026-10-31\
**Scope:** User Management and Legacy Apps Access only\
**Source baseline:** `concord-v0-05.zip`, extracted to a separate review
workspace\
**Important:** This is a source audit, not a claim that the user's local
checkout has been modified.

## 1. Executive result

The identity foundation and the single Platform Administrator
designation exist. The scoped operational features are not yet
implemented end-to-end.

The largest security correction is already unambiguous: the current
authorization middleware and several controllers use
`users.is_it_admin`, while the approved authority is the singleton
`platform_administrator` record evaluated by
`PlatformAdministratorService`. The legacy boolean must not remain an
authorization source.

The external Legacy Apps directory exists in the `concord` platform
database, but there are no IAM-owned user-to-legacy-app assignments,
Grant Sets, Grant Set application records, or batch assignment
implementation in this source baseline. The current login and `/me`
payload exposes the active directory to every user rather than each
user's assigned applications.

## 2. Source inventory and observed behavior

  ----------------------------------------------------------------------------------------------------------------------
  Source                               Observed behavior                Requirement gap
  ------------------------------------ -------------------------------- ------------------------------------------------
  `routes/api.php`                     Login/logout/me,                 No user detail/update/reactivate/password reset;
                                       preferences/settings, verify,    no external legacy-app assignment or Grant Set
                                       deactivate/disable, admin user   routes
                                       list/create, student enrollment, 
                                       legacy app directory             

  `RequireItAdmin.php`                 Loads user and checks            Must use
                                       `is_it_admin` plus active status `PlatformAdministratorService::isDesignated()`

  `SessionAuth.php`                    Resolves opaque session and      No force-password-change gate
                                       attaches auth payload            

  `PlatformAdministratorService.php`   Correct singleton lookup at row  Existing service should be the sole
                                       id `1`                           administrator designation authority

  `PlatformAdministrator.php`          Uses IAM `mysql` connection;     Reuse as-is
                                       singleton model                  

  `User.php`                           ULID, username, password hash    No force-change field/cast; preserve existing
                                       cast, status, legacy flag,       identity/account separation
                                       settings; email/display-name     
                                       accessors                        

  `Person.php`                         Personal information, email,     User profile edits must update Person and User
                                       preferences, types, optional     in one transaction
                                       student                          

  `UserCreationController.php`         Lists and creates; creation      Search/status filters and detail/edit are
                                       checks `is_it_admin`; list only  absent; password mode/force-change absent
                                       paginates                        

  `UserCreationService.php`            Atomically creates Person,       Add approved password modes and flag without
                                       types, optional Student, User;   breaking current create/provision behavior
                                       random password returned once;   
                                       provision-for-existing-person    
                                       path                             

  `UserManagementController.php`       Deactivate/disable; checks actor Must use designated-admin service;
                                       and protects target using legacy activate/reactivate, profile edit, reset absent
                                       boolean; revokes sessions and    
                                       audits                           

  `AuthController.php`                 Login, logout, me,               Must expose explicit platform authority;
                                       preferences/settings, verify;    password-change flow and session gate absent;
                                       login and me call AccessService  access payload needs per-user apps
                                       using `is_it_admin`              

  `SessionService.php`                 SHA-256 token hashes, HTTP-only  Suitable foundation; session cache still
                                       opaque cookie support, cache     includes legacy admin flag; password reset can
                                       invalidation, revoke all         reuse revoke-all
                                       sessions                         

  `LegacyAppController.php`            Returns all active               No user-specific filtering or administrator
                                       `concord_platform.legacy_apps`   access-management endpoints
                                       rows                             

  `AccessService.php`                  Builds access response with all  It is not an external app grant service; replace
                                       active `legacy_apps`,            this part carefully while preserving other
                                       `all_access` from passed legacy  access consumers
                                       flag, and empty office arrays    

  `Audit.php`                          Append-only insert into          Suitable general audit sink; scoped access
                                       `audit_logs`                     history still needs dedicated durable records

  `GrantController.php`                Office membership/Block          Separate domain; do not repurpose as Legacy Apps
                                       grant/revoke logic; not routed   grants
                                       in current `routes/api.php`      

  `AppCatalogController.php`           Manages internal `apps` / groups This is not `concord_platform.legacy_apps`; keep
                                       / Blocks catalog                 separate

  `DatabaseSeeder.php`                 Provisions singleton admin and   Legacy marker may remain for transition, but
                                       synchronizes legacy markers      must not authorize; seeder conflict logic should
                                                                        be reviewed before changing it

  `PlatformAdministratorTest.php`      7 tests verify singleton and     Good existing security regression base
                                       legacy-flag non-authority        

  Migrations                           Identity foundation, external    No force-change column or external app grant /
                                       legacy app directory, singleton  set / event tables
                                       admin constraints                
  ----------------------------------------------------------------------------------------------------------------------

## 3. Confirmed schema facts

### IAM database

`2026_10_24_000001_create_iam_identity_foundation.php` creates: -
`people` - `users` - `person_types` - `students` -
`student_enrollments` - `auth_sessions` - `audit_logs`

`users.person_id` is unique and references `people`, preserving one
account per person. User and person identifiers are ULIDs. The `users`
table currently has `status`, `is_it_admin`, `administrator_key`,
disabled/deactivated timestamps, and settings. It does not have a
force-password-change field.

### Platform database

`2026_10_26_064350_create_legacy_apps_table.php` creates `legacy_apps`
on the `concord_platform` connection. It stores directory metadata and
active state. It does not store assignments.

### Missing IAM-owned legacy access persistence

No migrations or tables were found for: - individual user-to-legacy-app
grant periods - Grant Sets - Grant Set app membership - Grant Set
application history / per-user set context - batch assignment records
(if separately required beyond individual events)

The approved history requirements require more than a mutable pivot with
a single boolean. Regrant after revocation must create a new active
period while preserving old periods.

## 4. Authorization findings

### Must change

`RequireItAdmin`, `UserCreationController::store`,
`UserManagementController::deactivate`, and
`UserManagementController::disable` all currently rely on `is_it_admin`.
Target-protection checks also rely on the flag.

The approved authority is:
`PlatformAdministratorService::isDesignated($userId)`

The middleware should be the consistent route-level gate. Controller
checks should not create a second competing authority rule. The
designated administrator must be protected from deactivation/disable.
The designation is not transferred by status changes.

### Compatibility boundary

The legacy `is_it_admin` column and seeder markers can remain
temporarily to avoid unrelated schema/data migration. They must not be
used for access decisions. Do not remove the field as part of this
scoped implementation unless the repository has a separately approved
migration.

### Other legacy code

`GrantController` uses `is_it_admin` to distinguish office-head and IT
override behavior. It is currently not registered in `routes/api.php`,
and it belongs to the office/Block domain, not external Legacy Apps
Access. Do not silently change that separate domain as part of this
feature. Before any future route exposure, its authority semantics need
their own review.

## 5. User Management gap map

  -----------------------------------------------------------------------
  Requirement                         Source status
  ----------------------------------- -----------------------------------
  Separate Person and User            Implemented

  One account per Person              Database unique constraint
                                      implemented

  Create person/account atomically    Implemented

  Existing person provisioning        Implemented
  service                             

  List users                          Implemented, basic only

  Search name/username                Missing

  Status filter                       Missing

  Pagination                          Basic pagination exists

  User detail                         Missing

  Edit username/email/profile/person  Missing
  types                               

  Admin self-edit                     Not implemented

  Generate or administrator-defined   Missing
  password                            

  Optional force password change      Missing

  Password reset for another user     Missing

  Administrator self-reset            Missing

  Revoke all sessions on reset        Session service capability exists,
                                      integration missing

  Deactivate/disable and revoke       Partially implemented; authority
  sessions                            source wrong

  Reactivate                          Missing

  Preserve account and app assignment No permanent delete path in current
  history                             user controller; future app grants
                                      must remain independent of account
                                      status

  Audit user operations               Creation/deactivate/disable
                                      audited; edit/reset/reactivate
                                      missing
  -----------------------------------------------------------------------

## 6. Legacy Apps Access gap map

  -----------------------------------------------------------------------
  Requirement                         Source status
  ----------------------------------- -----------------------------------
  Read platform legacy app directory  Implemented

  Return only user's active           Missing; currently returns all
  assignments                         active directory records

  Empty state supported by API        Not yet; all active apps are
                                      returned

  Admin app picker endpoint           Directory endpoint exists but is
                                      not admin-scoped

  User access summary table           Missing

  Individual grant/revoke with one    Missing
  Save Changes operation              

  Preserve grant periods and regrant  Missing
  dates                               

  Grant Set CRUD                      Missing

  Grant Set archive/restore           Missing

  Grant Set application context per   Missing
  user                                

  Set apply is additive and           Missing
  repeat-safe                         

  Batch assignment, mutually          Missing
  exclusive methods                   

  Record exact app snapshot for every Missing
  set application                     

  Preserve inactive app in historical Missing
  set config                          

  Block new grants for inactive apps  Missing

  Re-enable prior assignment when app Requires retained grant records and
  reactivated                         active-directory filtering

  Audit actor/source/timestamp        General audit sink exists;
                                      dedicated access history absent
  -----------------------------------------------------------------------

## 7. Important implementation notes discovered

1.  `AccessService` is currently the login `/me` access-payload builder.
    It is not a dedicated external legacy-app grant service. Avoid
    renaming/replacing it wholesale until all consumers are accounted
    for.
2.  `GrantController` is office/Block grants and is not routed in this
    route file. It is not the correct place to implement external
    legacy-app grants.
3.  `AppCatalogController` manages the internal `apps` catalog and its
    groups/Blocks. It is distinct from `concord_platform.legacy_apps`.
4.  `GrantController::store()` appears to pass seven arguments to
    `Audit::log()`, whose signature accepts six. This is an existing
    defect in that office/Block controller and is outside the requested
    implementation scope. Do not broaden this task to fix it unless
    tests or a required shared refactor force it.
5.  The session cache currently carries `is_it_admin`. This value must
    not be trusted as authority. The designated-admin middleware can
    resolve the singleton by user id, so the cached boolean should
    eventually be removed from the auth payload to avoid accidental
    future use.
6.  Login accepts username or an email that uniquely identifies a Person
    with an account. Preserve this behavior.
7.  User profile email is stored on `people`, not `users`. Update
    validation and uniqueness rules against the Person table, not a
    nonexistent users.email column.
8.  Password is hidden on the User model and hashed via the model cast.
    Never return or log it except the one-time temporary credential
    response explicitly approved for administrator display.
9.  Current `AccessService::forUser()` treats `is_it_admin` as
    `all_access` and returns all active legacy apps. This behavior is
    incompatible with the approved individual assignment model and must
    be changed with care so unrelated `apps` and `offices` fields are
    not broken.
10. The `PlatformAdministratorTest` suite already asserts that a legacy
    `is_it_admin=true` account is not designated unless the singleton
    table says so. Keep this regression test passing.

## 8. Implementation order recommended by source dependencies

1.  Replace the administrator route gate with
    `PlatformAdministratorService` and add middleware tests.
2.  Add user lifecycle/password-change persistence and session gate,
    preserving current login semantics.
3.  Complete User Management
    list/detail/update/create/reset/reactivate/deactivate/disable with
    transactions, session revocation, and audit.
4.  Add legacy-app assignment and event persistence.
5.  Add Grant Set and application history persistence.
6.  Implement individual access read/save and additive Grant Set
    application.
7.  Implement batch assignment using the same underlying grant
    operation.
8.  Filter login `/me` and Legacy Apps listing by active per-user
    grants.
9.  Add complete feature tests and verify migrations/API contracts.
10. Freeze frontend handoff from actual response payloads and error
    behavior.

## 9. Verification boundary

This audit was performed against the uploaded v0.5 archive. The user
separately reported that the laptop checkout on `main` has completed the
fresh database migration and passes its baseline test suite (8 tests, 14
assertions). The archive review workspace is not the local checkout and
has not been used to claim those local tests ran here.

------------------------------------------------------------------------

## 10. Implementation reconciliation note (2026-10-31)

**Important distinction:** Sections 1--9 are the source audit of the
uploaded `concord-v0.5` archive. They remain a historical baseline and
must not be read as a fresh audit of the current working tree.

### Reported implementation progress after the v0.5 audit

-   Administrator authority replacement: reported complete.
-   Credential lifecycle: reported complete; the user confirmed 16
    tests, 54 assertions, and no failures.
-   User Management: currently under implementation review.

### Current code observations supplied during the active implementation review

The following observations come from current source code pasted by the
user in the implementation conversation. They have not yet been
reconciled against the current repository ZIP.

1.  `UserManagementController.php` contains separate deactivate and
    disable actions. Both revoke all sessions, write distinct audit
    events, invalidate cached access, and preserve the account. The
    reviewed controller did not contain a reactivation action.
2.  `UserCreationController.php` contains a paginated listing method and
    an account creation method. The reviewed listing did not include
    search or status filters.
3.  `UserCreationService.php` contains both `create()` and
    `provisionForPerson()`. The former is called by
    `UserCreationController::store()`. A search under `app` and `routes`
    found no caller of `provisionForPerson()`.
4.  `User.php` uses the Laravel `hashed` cast for `password`. It
    includes `must_change_password` as a fillable, boolean-cast, hidden
    attribute.
5.  Migration
    `2026_10_31_071359_add_must_change_password_to_users_table.php` adds
    `must_change_password` with a default of `false`.
6.  Neither reviewed provisioning method explicitly sets
    `must_change_password`. This is an observation requiring full caller
    and password-mode review, not yet a confirmed defect or approved
    change.

### Audit boundary and next action

The current repository ZIP is being uploaded to Project sources. Once
available, inspect it directly and reconcile every relevant finding
against the actual current branch. Do not request repeated pasted source
files when the repository is accessible. Do not claim the v0.5 audit
proves the state of the current branch.


---

## 11. Current implementation reconciliation (2026-10-31)

### 11.1 How to read this audit

Sections 1–10 preserve the original v0.5 source audit and its historical observations. They describe the uploaded v0.5 archive, not the completed current implementation. Findings such as missing search, filters, detail/edit, password reset, reactivation, and audit coverage were valid for that baseline and are superseded by the implementation checkpoint below.

Do not delete those historical findings. They explain the original gaps, the rationale for the work, and the source-review boundary.

### 11.2 Current verified status

The current roadmap records Milestones 1–3 complete:

- Administrator authorization uses the singleton `platform_administrator` designation as the only platform Administrator authority.
- Credential lifecycle, including forced password change, password-change endpoint, and session handling, is complete.
- User Management APIs and account lifecycle are complete.
- Full IAM suite passed: **48 tests, 233 assertions, zero failures**.

User Management implementation includes:
- User listing, search by username/person name, status filters, and pagination.
- User details.
- Editing personal information, username, person types, and student number.
- Account creation validation and initial-password policy.
- Administrator password reset with generated/custom temporary password.
- Disable, deactivate, reactivate, and reinstate.
- Audit coverage for creation, update, password reset, disable, deactivate, reactivate, and reinstate.

### 11.3 Current lifecycle clarification

- Disable: Active → Disabled.
- Deactivate: Active → Deactivated.
- Reactivate: Disabled → Active.
- Reinstate: Deactivated → Active.

The designated Administrator can directly reactivate and reinstate accounts now. A future due-process reinstatement workflow may govern ordinary users but must not restrict the designated Administrator. Reinstatements, including bypasses, are audited. Restoring an account does not restore sessions previously revoked.

The current disable/deactivate implementation rejects the designated Administrator as the target. Preserve this behavior unless the user explicitly approves a change.

### 11.4 Remaining scope

Legacy Apps Access remains the next backend workstream. The v0.5 findings about missing grant persistence, Grant Sets, individual assignments, batch assignment, and per-user effective app filtering remain relevant unless later implementation work changes them. Milestone 4 is current.

### 11.5 Frontend handoff

User Management is ready for frontend implementation using `Concord-IAM-Frontend-Handoff-User-Management.md`. The combined frontend handoff remains useful for the approved Legacy Apps UX and should be retained. Legacy Apps API integration remains pending until its backend work and contracts are verified.

### 11.6 Source confidence boundary

The 2026-10-31 verification result is recorded in the implementation roadmap and active implementation history. The original v0.5 source audit itself is not retroactively converted into an audit of the current repository. For any future source-level claim, inspect the current working tree and cite the current file/test evidence.
