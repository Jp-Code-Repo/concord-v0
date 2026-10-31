# Concord Frontend Handoff

## User Management and Legacy Apps Access

**Audience:** Concord frontend implementation team\
**Backend owner:** Concord IAM (`services/iam`)\
**Status:** User Management backend complete and handed off; Legacy Apps Access APIs remain pending backend implementation and verification\
**Reference repository:** Concord v0.5

------------------------------------------------------------------------

## 1. Purpose and integration rule

This document communicates the approved user experience and the API
surface the frontend will consume.

The frontend must not begin integrating the new Grant Set, individual
assignment, or Batch Assign workflows until IAM has completed the
backend work and published the verified API contract.

The existing APIs listed in Section 7 may be used according to their
current verified behavior. Candidate endpoints in Section 8 are planning
names only and are not available APIs.

**Do not mock an endpoint as if it already exists.** During backend
development, UI components may be built against local fixtures only if
they are clearly isolated and later replaced with the verified API
client.

## 2. Ownership and trust boundaries

-   IAM owns user accounts and legacy application access assignments.
-   Concord Platform owns the legacy application directory and URLs.
-   IAM reads the app directory through its `concord_platform` database
    connection.
-   The frontend consumes IAM APIs. It must not connect directly to
    either database.
-   The frontend may hide or show links based on returned access data,
    but it is not the security boundary.
-   Legacy applications must enforce their own authorization for direct
    URL access unless a separately approved centralized integration is
    implemented.

## 3. Approved product behavior

### 3.1 User Management and Legacy Apps Access are separate

-   Create the account in User Management first.
-   Manage application access separately in Legacy Apps Access.
-   Legacy Apps Access must operate on existing users and must not
    create a second user record.

### 3.2 Users with no legacy app access

A user can log in to Concord without any legacy application assignment.

-   Do not block login because the legacy app list is empty.
-   Keep the Legacy Apps navigation entry visible.
-   When opened with no assigned apps, show:

**No Application Access**\
"You haven't been assigned access to any legacy applications yet. Please
contact your Concord administrator for assistance."

### 3.3 Legacy Apps Access landing page

The page should: - Display existing users and their current application
access summary. - Provide `Grant Sets` and `Batch Assign` actions near
the heading/breadcrumb area. - Allow the administrator to open an
individual user's access record.

### 3.4 Grant Sets

A Grant Set contains: - Name - Short description - Selected legacy
applications

The Grant Sets action opens a dialog that lists existing sets and
provides a create action. Create/edit uses the same application picker.

Application picker behavior: - Each application is a selectable
card/box. - Selected applications use the agreed selected styling (green
in the discussed mockup). - Unselected applications use the neutral
style. - Editing loads the currently selected applications. - Save
commits changes.

Grant Sets are templates, not live access policies: - Applying a set
copies its selected apps into individual user access. - Editing a set
does not change existing users. - Multiple sets can be applied to one
user. - Users can receive individual app grants and revocations after a
set is applied.

Do not implement automatic propagation of Grant Set edits.

### 3.5 Batch Assign

Batch Assign is a two-stage dialog/wizard.

**Stage 1: Select users** - Show existing users with selection
controls. - Allow multiple users to be selected. - Continue to Stage 2.

**Stage 2: Choose one method** The methods are mutually exclusive.

-   If `Apply Grant Set` is selected, show the Grant Set list and let
    the administrator choose one set.
-   If `Assign Individual Applications` is selected, show the active
    legacy app list and let the administrator choose applications.
-   Do not show a combined picker that permits both methods in the same
    operation.

Provide a review/confirmation before applying.

Batch Assign is additive: - Preserve existing user application
assignments. - Do not revoke anything. - Individual user access remains
editable afterward.

### 3.6 Individual user's Legacy Apps Access record

Use the approved Option A presentation:

1.  Show the Grant Set(s) applied to the user as informational/history
    context.
2.  Show the user's actual current application assignments separately.
3.  Show the `Granted On` date for each currently granted application.
4.  Show individually granted applications as well as apps copied from
    Grant Sets.
5.  Allow the administrator to grant or revoke individual apps.

The Grant Set is not the source of truth for current access. The actual
individual app assignments are authoritative.

The initial page does not display the full grant/revoke timeline. That
is reserved for a future Access Audit feature.

### 3.7 Grant date and audit behavior

-   Each active application shows its original `Granted On` date for the
    current active access period.
-   When a Grant Set grants an app that is not already active, its grant
    date is the Grant Set application date.
-   An individually granted app uses its individual grant date.
-   Reapplying a set when the app is already active must not reset its
    date.
-   Reapplying a set after an app was revoked grants the app again and
    begins a new active access period.
-   Backend retains grant and revoke events for future auditing.
-   The frontend must not derive or fabricate dates. Render the
    timestamps returned by IAM.

## 4. Suggested page structure

### Legacy Apps Access

-   Page heading and breadcrumbs.
-   `Grant Sets` action.
-   `Batch Assign` action.
-   User/access table.
-   Search, pagination, and filters only if supported by the final
    backend contract.

### Grant Sets dialog

-   Existing Grant Sets list.
-   Create Grant Set action.
-   Name and description fields.
-   Selectable application cards.
-   Save action.

### Batch Assign dialog

-   Stage 1: multi-select users.
-   Stage 2: mutually exclusive method selector.
-   Conditional Grant Set list or individual app list.
-   Review and confirm.

### Individual access detail

-   User identity summary.
-   Applied Grant Sets section.
-   Current application assignment section.
-   Granted On date per active app.
-   Individual grant/revoke controls.

## 5. Data handling and frontend rules

-   Use ULID IDs as opaque strings. Do not parse or generate them.
-   Treat the application directory's `id` as the stable app identifier.
-   Preserve the app directory's returned ordering unless product design
    explicitly changes it.
-   Handle nullable `icon` and `description`.
-   Handle an empty application list as a valid state.
-   Do not treat `access.all_access` or person type as proof of legacy
    app access.
-   Do not hardcode app names, URLs, Grant Sets, or grant dates.
-   Do not assume Grant Set names are employee classifications.
-   Use the backend's current authenticated session and existing API
    client conventions (`credentials: include`, JSON headers,
    `IamApiError` handling) rather than introducing a second HTTP
    client.
-   Refresh authenticated access data after relevant grant/revoke
    operations, or use the final response payload if the backend
    contract supports it.
-   Show clear feedback for validation errors, forbidden actions, stale
    records, and failed batch operations.
-   Do not optimistically show a grant/revoke as successful before the
    API confirms it.

## 6. Current frontend integration points

In v0.5, relevant files include:

-   `services/concord-frontend/src/lib/iam.ts`
    -   Existing IAM API client.
    -   `IamLegacyApp`, `IamAccess`, `IamSession` types.
    -   `login()`, `getCurrentUser()`, and common request/error
        handling.
-   `services/concord-frontend/src/contexts/AuthContext.tsx`
    -   Central authentication state.
    -   Stores `legacyApps` from `session.access.legacy_apps`.
-   `services/concord-frontend/src/components/dashboard/ApplicationsSection.tsx`
    -   Existing dashboard application rendering.
-   `services/concord-frontend/src/components/layout/Sidebar.tsx`
    -   Existing navigation.
-   `services/concord-frontend/src/router.tsx` and `src/routes/`
    -   Existing route structure.

Inspect the current working tree before changing these files. The
archive is a baseline, not a guarantee that the branch has not changed.

## 7. Existing backend endpoints in v0.5

These routes are present in the uploaded v0.5
`services/iam/routes/api.php`:

  ------------------------------------------------------------------------------------
  Method                  Endpoint                             Current behavior
  ----------------------- ------------------------------------ -----------------------
  POST                    `/api/iam/login`                     Authenticates and
                                                               returns user plus
                                                               access payload

  POST                    `/api/iam/logout`                    Ends current session

  GET                     `/api/iam/me`                        Returns authenticated
                                                               user plus access
                                                               payload

  GET                     `/api/iam/admin/users`               Returns paginated user
                                                               list

  POST                    `/api/iam/admin/users`               Creates person and user
                                                               account

  POST                    `/api/iam/users/{user}/deactivate`   Deactivates account

  POST                    `/api/iam/users/{user}/disable`      Disables account

  GET                     `/api/iam/legacy-apps`               Returns active
                                                               application directory,
                                                               not user-filtered
                                                               grants
  ------------------------------------------------------------------------------------

All are within the existing IAM API conventions. Protected routes use
`session.auth`; administrator routes use `it.admin`.

### Existing user creation request

The current `POST /api/iam/admin/users` request accepts:

``` json
{
  "username": "alex.santos",
  "first_name": "Alex",
  "middle_name": null,
  "last_name": "Santos",
  "name_suffix": null,
  "gender": null,
  "email": "alex.santos@example.edu",
  "types": ["employee"],
  "student_number": null
}
```

The current controller returns HTTP 201 with a `person`, `user`, and
`initial_password`. The initial password is returned once. The frontend
must present it securely according to the approved account-creation UX
and must not persist it in browser storage or logs.

The request shape above reflects the current v0.5 controller. Confirm
the working backend contract before implementation.

### Existing app directory item

`GET /api/iam/legacy-apps` currently returns:

``` json
{
  "data": [
    {
      "id": "01M5VMK03KYBH3921YCD4M8GXV",
      "name": "School Library",
      "url": "https://library.example.com",
      "icon": "books",
      "description": "Library catalog and circulation"
    }
  ]
}
```

This is the platform app directory, not a user's authorized app list.
The current v0.5 `/login` and `/me` access service also returns all
active legacy apps to every user. That behavior must change in the
backend before per-user filtering can be relied upon.

## 8. Candidate endpoints for the new feature

**These are planning candidates only. They are not present in the v0.5
route file and must not be called until IAM implements and verifies
them.** Final paths, payloads, and response envelopes will be frozen by
the backend handoff after implementation.

  -----------------------------------------------------------------------------------------------------------------------
  Method                  Candidate endpoint                                                      Frontend use
  ----------------------- ----------------------------------------------------------------------- -----------------------
  GET                     `/api/iam/admin/legacy-apps`                                            Load active app options
                                                                                                  for admin pickers

  GET                     `/api/iam/admin/legacy-apps/access`                                     Load users and access
                                                                                                  summaries

  GET                     `/api/iam/admin/users/{user}/legacy-apps`                               Load one user's app
                                                                                                  access and Grant Set
                                                                                                  context

  GET                     `/api/iam/admin/legacy-apps/grant-sets`                                 List Grant Sets

  POST                    `/api/iam/admin/legacy-apps/grant-sets`                                 Create a Grant Set

  GET                     `/api/iam/admin/legacy-apps/grant-sets/{grantSet}`                      Load one Grant Set

  PATCH                   `/api/iam/admin/legacy-apps/grant-sets/{grantSet}`                      Edit a Grant Set

  POST                    `/api/iam/admin/users/{user}/legacy-apps/{app}/grant`                   Grant an app
                                                                                                  individually

  POST                    `/api/iam/admin/users/{user}/legacy-apps/{app}/revoke`                  Revoke an app
                                                                                                  individually

  POST                    `/api/iam/admin/users/{user}/legacy-apps/grant-sets/{grantSet}/apply`   Apply a Grant Set to
                                                                                                  one user

  POST                    `/api/iam/admin/legacy-apps/batch-assignments`                          Apply one Grant Set or
                                                                                                  individual apps to
                                                                                                  multiple users
  -----------------------------------------------------------------------------------------------------------------------

### Batch request concept

The batch API must accept exactly one method. Illustrative payloads
only:

Apply one Grant Set:

``` json
{
  "user_ids": ["01USER_A", "01USER_B"],
  "method": "grant_set",
  "grant_set_id": "01GRANT_SET"
}
```

Assign individual apps:

``` json
{
  "user_ids": ["01USER_A", "01USER_B"],
  "method": "individual_apps",
  "legacy_app_ids": ["01APP_A", "01APP_B"]
}
```

Do not send `grant_set_id` and `legacy_app_ids` together. The final
request schema and response must come from the implemented backend.

## 9. Error and empty-state handling

Use the existing IAM error conventions: - `401`: session missing or
expired. Follow the existing authentication flow. - `403`: authenticated
user is not authorized for the administrator operation. - `404`:
requested user, Grant Set, or application does not exist or is not
available. - `409`: conflict, such as duplicate state transition or
stale/conflicting operation, if returned by the final backend. - `422`:
validation errors. Map field errors to the relevant controls. - `5xx`:
show a safe retry/error state. Do not claim the action succeeded.

The exact status codes for new endpoints must be confirmed from backend
implementation.

## 10. Frontend acceptance checklist

-   [ ] User Management remains separate from Legacy Apps Access.
-   [ ] Administrator can create an employee account without HRIS.
-   [ ] User with no legacy app grants can log in and sees the Legacy
    Apps empty state.
-   [ ] Grant Sets can be listed, created, and edited through verified
    APIs.
-   [ ] Selected app cards have the agreed visual state.
-   [ ] Batch Assign selects multiple users first.
-   [ ] Batch Assign allows exactly one method per operation.
-   [ ] Grant Set and individual app lists are conditionally displayed,
    not combined.
-   [ ] Batch operations preserve existing grants and never revoke.
-   [ ] Individual user access remains editable after batch assignment.
-   [ ] Individual access view shows Grant Set context and current app
    assignments.
-   [ ] Each active application displays the backend-provided
    `Granted On` date.
-   [ ] Reapplying a set does not visually reset an already-active app's
    original grant date.
-   [ ] The UI does not display the full grant/revoke timeline in this
    initial release.
-   [ ] Frontend never treats UI visibility as authorization
    enforcement.
-   [ ] All API calls use the final verified backend contract.

## 11. Frontend start condition

The frontend may begin page layout and isolated visual components using
fixtures, but it must wait for IAM to finish and verify: 1.
Administrator authorization for all new endpoints. 2. Grant Set CRUD. 3.
Individual user access retrieval and grant/revoke. 4. Single-user Grant
Set application. 5. Batch Assign. 6. Login and `/me` per-user app
filtering. 7. Final request/response schemas and error behavior.

IAM will issue an updated handoff with verified endpoint details before
the frontend integrates the API.

------------------------------------------------------------------------

## 12. Reconciliation note (2026-10-31)

The product behavior in this handoff is approved. The endpoint table in
Section 8 remains a **candidate implementation map only**, not a
verified API contract. Do not wire the frontend to these candidate paths
until the backend implementation and feature tests confirm the actual
routes, payloads, response fields, validation errors, and status codes.

### Confirmed authority contract

The frontend must consume an explicit
`platform_authority.is_administrator` value derived by IAM from
`PlatformAdministratorService::isDesignated(userId)`. It must not use
`is_it_admin`, username, email, or person type to infer Administrator
authority. Backend middleware remains the security boundary.

### Confirmed Legacy Apps behavior

-   Login and `/me` return only the user's currently effective legacy
    app assignments.
-   A user with zero assignments can still log in. The Legacy Apps page
    shows the approved empty-state message.
-   The app directory remains owned by Concord Platform. IAM owns
    assignment records and Grant Sets.
-   Current access is the source of truth. Grant Set application history
    is contextual and does not dynamically propagate future Grant Set
    edits.
-   Batch assignment is additive and uses exactly one method per
    operation.
-   Inactive apps are unavailable for new grants but remain visible in
    historical Grant Set configurations and access history.

### Frontend start condition

The frontend API integration remains blocked until the backend has
implemented and tested: 1. Platform Administrator authority in
authenticated identity responses. 2. User Management
CRUD/lifecycle/password flows. 3. Grant Set lifecycle and
archive/restore. 4. Individual user access read/save. 5. Single-user
Grant Set application. 6. Batch assignment. 7. Per-user effective app
filtering in login and `/me`.

The frontend may use isolated fixtures for visual construction, but
fixtures must not be mistaken for live API behavior. Replace them with
the exact verified backend contract before integration is considered
complete.

------------------------------------------------------------------------

## 13. Backend implementation status reconciliation (2026-10-31)

The approved frontend behavior and integration restrictions in this
document remain unchanged.

### Backend progress

-   Administrator authority replacement is reported complete.
-   Credential lifecycle is reported complete and was verified with 16
    tests and 54 assertions, with no failures.
-   User Management completion is currently in progress.
-   Legacy Apps grant persistence, Grant Sets, individual app
    assignment, Batch Assign, and per-user effective app integration
    remain later backend milestones unless the current repository
    inspection establishes otherwise.

### Frontend integration remains blocked

Do not treat the candidate endpoint paths in Section 8 as implemented
APIs. Do not begin live API integration for the new User Management or
Legacy Apps workflows until the backend has completed and verified the
relevant routes, payloads, response shapes, authorization, and error
behavior.

The frontend may continue isolated visual construction with clearly
separated fixtures, subject to the rules already stated in this handoff.

### Source-of-truth note

The uploaded v0.5 archive is a historical baseline. The current
repository ZIP is being uploaded for direct inspection. Reconcile this
handoff with the current working tree and the final backend tests before
publishing a new verified API contract.


---

## 14. Current frontend implementation readiness (2026-10-31)

This reconciliation preserves the original approved UX and Legacy Apps requirements above. It clarifies which frontend work can proceed now.

### 14.1 User Management: ready for frontend implementation

The User Management backend milestone is complete. Use the dedicated document:

`Concord-IAM-Frontend-Handoff-User-Management.md`

That document is the focused implementation specification for:
- User listing, search, filters, and pagination.
- User details and editing.
- Account creation and one-time initial password.
- Administrator password reset.
- Disable, deactivate, reactivate, and reinstate.
- Password-change/session behavior.
- Request/response contracts, validation, errors, audit behavior, and acceptance checklist.

Do not require the frontend team to rediscover these decisions or re-inspect backend source to reconstruct product behavior.

The standalone handoff calls out the limited exact response-contract follow-ups that should be confirmed against the current working tree or API fixtures. Do not guess those fields.

### 14.2 Legacy Apps Access: still pending

All approved Legacy Apps UX and product decisions in Sections 3–5 remain in force. However, the candidate endpoints in Section 8 and the old v0.5 routes in Section 7 must not be mistaken for the future verified access-management API.

The frontend may prepare isolated visual components with fixtures, but live integration remains blocked until IAM completes and verifies:
- Grant-period and immutable event persistence.
- Grant Set CRUD and archive/restore.
- Individual user access read/save and grant/revoke.
- Single-user additive Grant Set application and history context.
- Additive Batch Assign using exactly one method.
- Per-user effective application filtering in login and `/me`.
- Final route, request, response, authorization, and error contracts.

### 14.3 Separation rule

User Management creates and manages the Concord account. Legacy Apps Access manages which registered applications an existing account may use. Neither feature creates duplicate Person/User records, and completion of one does not imply completion of the other.

### 14.4 Verification checkpoint

The complete IAM suite passed on 2026-10-31: **48 tests, 233 assertions, zero failures**. This verifies the completed IAM backend work represented in the current roadmap; it does not mean the future Legacy Apps Access APIs are complete.
