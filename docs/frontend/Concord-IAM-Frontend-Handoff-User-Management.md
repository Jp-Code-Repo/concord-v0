# Concord IAM Frontend Handoff
## User Management

**Document date:** 2026-10-31  
**Feature:** User Management  
**Backend owner:** Concord IAM (`services/iam`)  
**Frontend audience:** Concord frontend implementation team  
**Backend status:** User Management backend implementation complete and verified  
**Scope:** User Management only. Legacy Apps Access is explicitly excluded.

---

## 1. Purpose

This document is the implementation handoff for the Concord User Management frontend. It records the approved behavior, backend ownership boundaries, verified routes, request contracts, response information established by the implementation, lifecycle rules, validation expectations, and test evidence.

The frontend team should use this document as the product and integration handoff. Do not restart product discovery or redesign the approved backend behavior. Do not infer API behavior from UI assumptions.

**Backend verification:** The complete IAM test suite passed on 2026-10-31: **48 tests, 233 assertions, zero failures**.

### Scope boundary

Included:
- User listing, search, filtering, pagination.
- User detail retrieval.
- Account creation.
- Personal information and username editing.
- Person type and student number management.
- Administrator password reset.
- Disable, deactivate, reactivate, and reinstate.
- Initial-password handling and forced password change.
- Authentication/session behavior relevant to User Management.
- Administrator authority and error handling.

Not included:
- Legacy Apps access assignment.
- Grant Sets.
- Individual app grants/revocations.
- Batch Assign.
- HRIS workflows or employee PDS.
- Parent/guardian relationship management.
- Future ordinary-user reinstatement due-process workflow.

---

## 2. Product and architecture decisions

### 2.1 Identity ownership

- IAM is the source of truth for Concord login accounts and identity information shared platform-wide.
- A `Person` is the human identity record. A `User` is the login account.
- `users.person_id` links the account to its person.
- A person may exist without a Concord account.
- One person may have at most one Concord account.
- A person may have multiple person types at the same time.
- Person types currently include `employee`, `student`, `parent`, and `visitor`. Use the exact values supplied by the API; do not invent additional types.
- Student identity is separate from school-year enrollment. The student number belongs to the Student identity record, not to a school-year enrollment.
- Changing person types must not delete the underlying Student record or student number.
- A person can be both Student and Employee simultaneously.
- Detailed employee information/PDS belongs to HRIS, not IAM.
- Guardian imports do not automatically create parent accounts or relationships.
- Do not automatically create a login account just because a Person exists.

### 2.2 Administrator authority

There is exactly **one designated Concord Administrator**.

- IAM determines Administrator authority from the singleton `platform_administrator` designation.
- Frontend must not infer authority from `is_it_admin`, username, email, person type, or any other user field.
- The backend authorization middleware is the security boundary.
- The Administrator can manage ordinary users, including account creation, editing, password reset, and account lifecycle operations.
- The Administrator can manage their own account in general account-management flows. The current disable/deactivate endpoints specifically reject attempts to disable or deactivate the designated Administrator account with `409`; do not present those actions as available for that account.
- Do not build an Administrator assignment screen, a second Administrator role, delegated administrators, or a generic admin-role picker.
- Backend routes currently use the legacy middleware alias `it.admin`; its authority is based on the designated Administrator service, not the legacy flag.

### 2.3 Account statuses and lifecycle

The three account statuses are:

| Status | Meaning | Available Administrator action |
|---|---|---|
| `active` | Account is active | Disable or deactivate |
| `disabled` | Temporarily restricted | Reactivate |
| `deactivated` | Active institutional relationship has ended; record retained | Reinstate |

The transitions are distinct:

- **Disable:** Active → Disabled.
- **Deactivate:** Active → Deactivated.
- **Reactivate:** Disabled → Active.
- **Reinstate:** Deactivated → Active.

Rules:
- Disabled and deactivated accounts cannot authenticate.
- Disable/deactivate revoke the target user's active sessions.
- Reactivate/reinstate do not restore previously revoked sessions. The user must log in again.
- Reactivate only accepts a disabled account.
- Reinstate only accepts a deactivated account.
- Repeating a transition against an account already in that target state returns a conflict (`409`).
- The designated Administrator can directly reinstate a deactivated account now.
- A future due-process reinstatement workflow may apply to ordinary users. It must not restrict the designated Administrator, who can bypass it.
- Reinstatements, including Administrator bypasses, are audited.
- Account records are retained. There is no permanent user deletion in this scope.

**Important current implementation detail:** The disable and deactivate endpoints reject the designated Administrator as the target with `409`. The frontend should disable or omit these actions when the target is the designated Administrator. Do not assume this same restriction applies to ordinary profile edits or password management.

### 2.4 Password policy

- IAM-generated initial passwords are temporary credentials.
- Account creation and trusted person provisioning set `must_change_password = true`.
- Administrator password reset also sets `must_change_password = true`.
- Passwords are hashed by the backend before storage.
- Minimum new-password length is 12 characters.
- The frontend must never store temporary passwords in local storage, session storage, persistent application state, analytics, logs, or URLs.
- Show a generated or custom temporary password only in the immediate success flow, with an explicit copy action and warning to securely deliver it.
- Do not assume the frontend can retrieve the temporary password again after leaving the success view. The API returns it once.

---

## 3. Frontend integration rules

- Use the existing IAM API client and authentication/session conventions in the current frontend repository. Do not introduce a second HTTP client.
- Send JSON requests with the existing JSON headers.
- Include browser credentials/cookies (`credentials: 'include'` or the equivalent already used by the project).
- The session cookie is HTTP-only. Frontend code must not read or manually manage its value.
- Do not connect directly to the IAM or Platform databases.
- Treat ULID identifiers as opaque strings. Do not parse or generate them.
- Do not use `is_it_admin` as an authority check.
- Do not assume person type determines Administrator authority.
- Do not treat Legacy Apps access or application links as part of User Management.
- Do not optimistically show a mutation as successful before IAM confirms it.
- Handle `401`, `403`, `404`, `409`, `422`, and `5xx` distinctly.
- Refresh the relevant user detail/list query after successful mutations, or use the verified response if it returns the complete updated resource.
- Avoid logging request bodies that contain passwords or temporary passwords.

### 3.1 Existing frontend integration points from the original handoff

The original combined handoff identified these v0.5 locations. They are navigation hints, not a guarantee that the current branch has identical structure. Inspect the current frontend working tree only to confirm the present component/file locations and project conventions:

- `services/concord-frontend/src/lib/iam.ts`
  - Existing IAM API client and shared request/error handling.
- `services/concord-frontend/src/contexts/AuthContext.tsx`
  - Central authentication state.
- `services/concord-frontend/src/router.tsx` and `src/routes/`
  - Route structure.

Do not re-inspect backend implementation to rediscover the behavior in this handoff. Backend contract changes must be coordinated with the IAM owner.

---

## 4. API contract

All paths below are relative to the API origin and include `/api/iam`.

Protected endpoints use the current session cookie. Administrator endpoints additionally require the designated Administrator authority.

### 4.1 Endpoint matrix

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| `POST` | `/api/iam/login` | Public, rate-limited | Authenticate and establish session |
| `POST` | `/api/iam/logout` | Session | End current session |
| `GET` | `/api/iam/me` | Session | Current authenticated identity |
| `POST` | `/api/iam/me/password` | Session | Change current user's password |
| `GET` | `/api/iam/admin/users` | Administrator | List/search/filter/paginate users |
| `POST` | `/api/iam/admin/users` | Administrator | Create person and account |
| `GET` | `/api/iam/admin/users/{user}` | Administrator | Retrieve one user's details |
| `PATCH` | `/api/iam/admin/users/{user}` | Administrator | Update user/person information |
| `POST` | `/api/iam/admin/users/{user}/password` | Administrator | Reset a user's password |
| `POST` | `/api/iam/admin/users/{user}/disable` | Administrator | Disable account |
| `POST` | `/api/iam/admin/users/{user}/deactivate` | Administrator | Deactivate account |
| `POST` | `/api/iam/admin/users/{user}/reactivate` | Administrator | Reactivate disabled account |
| `POST` | `/api/iam/admin/users/{user}/reinstate` | Administrator | Reinstate deactivated account |

The four account lifecycle routes are in the `admin` route group in the current implementation. The two self-service authentication routes are under the session-authenticated group.

### 4.2 Session and authenticated identity

#### `POST /api/iam/login`

Request:

```json
{
  "username": "alex.santos",
  "password": "user-supplied-password"
}
```

- `username` is required, string, maximum 100 characters.
- `password` is required, string, maximum 200 characters.
- The backend also accepts an email address as the login identifier only when it identifies exactly one person with a linked account.
- Successful login establishes the HTTP-only session cookie.
- The JSON response contains `user` and `access`.
- If `must_change_password` is true, `access` is `null` until the password is changed.
- Invalid username, wrong password, and non-active account return the same `401` response message: `Invalid credentials.`

The `user` identity payload currently includes:
- `id`
- `person_id`
- `username`
- `is_it_admin` (legacy data field, not an authorization source)
- `status`
- `must_change_password`
- `settings`
- `person` (nullable), containing:
  - `id`
  - `first_name`
  - `middle_name`
  - `last_name`
  - `name_suffix`
  - `gender`
  - `email`
  - `display_name`
  - `preferences`
  - `types`
  - `student_number`

**Known backend handoff discrepancy:** The inspected `AuthController::userPayload()` in the v0.6 source does not include `platform_authority.is_administrator`, although the earlier combined handoff says the frontend should consume it. Do not infer authority from `is_it_admin`. Treat the explicit authority field as a backend contract item that must be confirmed/added before the frontend uses it to render Administrator-only navigation or controls. Backend authorization remains authoritative regardless.

#### `GET /api/iam/me`

Returns the same `user` and `access` structure as login, based on the current session. If `must_change_password` is true, `access` is `null`.

#### `POST /api/iam/logout`

Ends the current session and clears the session cookie. Successful response is `204 No Content`.

#### `POST /api/iam/me/password`

Request:

```json
{
  "current_password": "current-password",
  "new_password": "new-password-at-least-12-characters",
  "new_password_confirmation": "new-password-at-least-12-characters"
}
```

Validation:
- `current_password`: required string.
- `new_password`: required string, minimum 12 characters, confirmed against `new_password_confirmation`.
- Incorrect current password returns `422` with a `current_password` field error.
- Success returns:

```json
{
  "message": "Password changed successfully.",
  "must_change_password": false
}
```

Session behavior:
- Current device's session remains active.
- Other active sessions for the same user are revoked.
- The user can access normal authenticated endpoints after the next request resolves the refreshed session state.

### 4.3 User listing

#### `GET /api/iam/admin/users`

Query parameters:

| Parameter | Meaning |
|---|---|
| `search` | Search username, first name, middle name, last name, or suffix |
| `status` | Filter by `active`, `disabled`, or `deactivated` |
| `per_page` | Page size, clamped by backend to 1–100; default 25 |
| `page` | Laravel pagination page |

Search is applied to username and person-name fields. Status filtering uses exact status values. Pagination is returned using Laravel's paginator response envelope.

The list item structure established by the implementation includes:

```json
{
  "id": "USER_ULID",
  "person_id": "PERSON_ULID",
  "username": "alex.santos",
  "status": "active",
  "is_it_admin": false,
  "created_at": "2026-10-31T00:00:00.000000Z",
  "person": {
    "id": "PERSON_ULID",
    "first_name": "Alex",
    "middle_name": null,
    "last_name": "Santos",
    "name_suffix": null,
    "gender": null,
    "email": "alex@example.edu",
    "display_name": "Alex Santos",
    "types": ["employee"],
    "student_number": null
  }
}
```

The example values are illustrative. The frontend must render values from the response and tolerate nullable `person`, nullable personal fields, and empty `types`.

Laravel pagination metadata is included by the paginator. Use the returned `current_page`, `last_page`, `per_page`, `total`, and `links`/`meta` fields rather than calculating totals from the current page.

**Search/filter contract note:** The implementation and tests verify username/name search, status filtering, and pagination. The exact query parameter names and paginator envelope should be taken from the current `UserCreationController::index()` response and existing test assertions when implementing the API client. Do not invent alternative parameter names.

### 4.4 User details

#### `GET /api/iam/admin/users/{user}`

Returns the selected user's account and linked person information, including the information needed by the edit/detail view. A missing user returns `404`.

The detail response's exact JSON envelope and field list must match the current `UserCreationController::show()` implementation. The frontend must not assume the list row is a complete detail object. If the exact response envelope is not included in the API client or contract test fixture supplied by IAM, request that exact response example from the backend owner rather than reverse-engineering the controller.

### 4.5 Account creation

#### `POST /api/iam/admin/users`

Request:

```json
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

Validation:
- `username`: required string, maximum 100.
- `first_name`: required string, maximum 100.
- `middle_name`: nullable string, maximum 100.
- `last_name`: required string, maximum 100.
- `name_suffix`: nullable string, maximum 30.
- `gender`: nullable string, maximum 32.
- `email`: nullable valid email, maximum 255.
- `types`: required array with at least one item.
- `types.*`: required string, must be one of the supported `PersonType::TYPES`.
- `student_number`: nullable string, maximum 64.
- Student type requires a student number.
- Username must be unique.
- Student number must be unique.

Success: `201 Created`

Response:

```json
{
  "message": "Account created.",
  "person": {
    "id": "PERSON_ULID",
    "first_name": "Alex",
    "middle_name": null,
    "last_name": "Santos",
    "name_suffix": null,
    "gender": null,
    "email": "alex.santos@example.edu",
    "preferences": {},
    "types": ["employee"],
    "student_number": null
  },
  "user": {
    "id": "USER_ULID",
    "person_id": "PERSON_ULID",
    "username": "alex.santos",
    "status": "active"
  },
  "initial_password": "ONE_TIME_TEMPORARY_PASSWORD"
}
```

Important:
- `initial_password` is returned once. Present it in a dedicated success view/modal with a copy action.
- Do not include it in normal user list/detail state, browser storage, telemetry, or logs.
- The account is created with `must_change_password = true`.
- The password is not returned by later detail/list calls.
- The frontend should communicate that the user must change the temporary password on first login.

### 4.6 Update user

#### `PATCH /api/iam/admin/users/{user}`

The update endpoint supports:
- `first_name`
- `middle_name`
- `last_name`
- `name_suffix`
- `gender`
- `email`
- `username`
- `types`
- `student_number`

Personal information and username validation follows the corresponding creation field limits and uniqueness rules. `types` must contain valid person types. Student number is required when the resulting person types include `student`, and must be unique.

Example partial update:

```json
{
  "first_name": "Alexandra",
  "email": "alexandra.santos@example.edu"
}
```

Example person-type/student-number update:

```json
{
  "types": ["employee", "student"],
  "student_number": "2026-000123"
}
```

Behavior:
- Partial updates are supported.
- Personal information is stored on the Person record.
- Username is stored on the User account.
- Person type associations are synchronized.
- Student record/number is created or updated when the resulting person types require it.
- Changing person types does not delete the existing Student record or student number.
- Password is not changed through this endpoint. Use the dedicated password-reset endpoint.
- Account status is not changed through this endpoint. Use the lifecycle endpoints.
- Successful update is audited as `identity.user_updated`.

**Response contract:** The exact successful JSON envelope from `UserCreationController::update()` should be used as implemented. Do not assume it returns the entire detail resource unless the actual response confirms that. The frontend can refresh the detail query after success.

### 4.7 Administrator password reset

#### `POST /api/iam/admin/users/{user}/password`

Two mutually exclusive modes:

**Generate a temporary password**

```json
{
  "mode": "generate"
}
```

**Set a custom temporary password**

```json
{
  "mode": "custom",
  "password": "TemporaryPassword123"
}
```

Rules:
- `mode` must be `generate` or `custom`.
- Custom password must be at least 12 characters.
- Generated password is generated by IAM.
- Both modes set `must_change_password = true`.
- Password is hashed before persistence.
- All active sessions belonging to the target user are revoked.
- The temporary password is returned once in the response.
- The actual password must never be written to audit metadata or frontend logs.
- Audit event: `identity.user_password_reset`.
- The audit metadata records reset mode and forced-change state, not the password.

Frontend UX:
- Present Generate and Set Custom as separate choices.
- For custom mode, provide password and confirmation inputs if required by the frontend UX, but send only the backend contract fields.
- Display the returned temporary password once and provide Copy.
- Clearly state that the user must change it at next login.
- The target user must sign in again because all active sessions were revoked.

**Response envelope:** Read the exact response returned by `UserCreationController::resetPassword()` when wiring the API client. The essential returned value is the one-time temporary password; do not assume an unverified property name.

### 4.8 Disable account

#### `POST /api/iam/admin/users/{user}/disable`

No request body is required by the current controller.

Success response:

```json
{
  "message": "Account disabled."
}
```

Behavior:
- Only the designated Administrator may call it.
- Target must not be the designated Administrator.
- Already-disabled account returns `409`.
- Account status becomes `disabled`.
- `disabled_at` is set.
- All target sessions are revoked.
- Access cache is invalidated.
- Audit event: `identity.user_disabled`.
- Account record is retained.

### 4.9 Deactivate account

#### `POST /api/iam/admin/users/{user}/deactivate`

No request body is required by the current controller.

Success response:

```json
{
  "message": "Account deactivated."
}
```

Behavior:
- Only the designated Administrator may call it.
- Target must not be the designated Administrator.
- Already-deactivated account returns `409`.
- Account status becomes `deactivated`.
- `deactivated_at` is set.
- All target sessions are revoked.
- Access cache is invalidated.
- Audit event: `identity.user_deactivated`.
- Account record is retained.

### 4.10 Reactivate disabled account

#### `POST /api/iam/admin/users/{user}/reactivate`

No request body is required.

Success response:

```json
{
  "message": "Account reactivated."
}
```

Behavior:
- Only the designated Administrator may call it.
- Only a `disabled` account is accepted.
- A non-disabled account returns `409`.
- Account status becomes `active`.
- `disabled_at` is cleared.
- Audit event: `identity.user_reactivated`.
- Access cache is invalidated.
- Previously revoked sessions are not restored.

### 4.11 Reinstate deactivated account

#### `POST /api/iam/admin/users/{user}/reinstate`

No request body is required.

Success response:

```json
{
  "message": "Account reinstated."
}
```

Behavior:
- Only the designated Administrator may call it.
- Only a `deactivated` account is accepted.
- A non-deactivated account returns `409`.
- Account status becomes `active`.
- `deactivated_at` is cleared.
- Audit event: `identity.user_reinstated`.
- Audit metadata records that the action was performed by the Administrator.
- Access cache is invalidated.
- Previously revoked sessions are not restored.
- This is a direct Administrator action now. Do not build an ordinary-user due-process workflow in this frontend scope.

---

## 5. Recommended User Management screens and flows

### 5.1 User list

Required:
- Search input.
- Status filter: All, Active, Disabled, Deactivated.
- Paginated table/list.
- Loading, empty, and error states.
- Open user detail action.
- Create user action.

Suggested columns:
- Name / display name.
- Username.
- Person types.
- Student number when present.
- Account status.
- Created date, if useful and returned.
- Row action to view/manage.

Do not display `is_it_admin` as a permission indicator. If the API supplies the explicit `platform_authority.is_administrator` field after backend reconciliation, use that field for authority-aware presentation.

### 5.2 Create user

Fields:
- Username.
- First name.
- Middle name.
- Last name.
- Suffix.
- Gender.
- Email.
- Person types.
- Student number, conditionally shown/required when Student is selected.

Behavior:
- Permit multiple person types.
- Do not assume Employee and Student are mutually exclusive.
- Validate required Student number when Student is selected.
- On success, show the one-time initial password in a dedicated result view.
- Provide Copy Password.
- Explain that the user must change it at first login.
- Do not show the temporary password again after the one-time result view is dismissed.

### 5.3 User detail/edit

Separate editable identity fields from account lifecycle controls:
- Personal information.
- Username.
- Person types.
- Student number when applicable.
- Account status and permitted lifecycle actions.
- Password reset action.

Do not expose password values. Do not combine status changes with the ordinary profile update request.

### 5.4 Password reset

Offer:
- Generate temporary password.
- Set custom temporary password.

After successful reset:
- Show the returned temporary password once.
- Provide Copy.
- Explain forced password change at next login.
- Explain that the user's existing sessions were revoked.

### 5.5 Account status actions

Use clear confirmation dialogs:
- Disable: explain temporary restriction.
- Deactivate: explain end of active institutional relationship and record retention.
- Reactivate: only show for disabled accounts.
- Reinstate: only show for deactivated accounts.

Do not use one generic “restore” action for both disabled and deactivated states. The API endpoints and semantics are distinct.

For the designated Administrator account, do not offer Disable or Deactivate because the current backend returns `409` for those targets. Do not infer this designation from `is_it_admin`; wait for the explicit authority field contract or treat the backend response as authoritative.

---

## 6. Validation and error handling

Use the shared IAM error handling already present in the frontend.

| HTTP status | Meaning | Frontend behavior |
|---|---|---|
| `401` | Missing/expired session or invalid login | Follow existing authentication flow; do not treat as validation |
| `403` | Authenticated user lacks Administrator authority | Show access-denied state; do not retry as another user |
| `404` | Target user not found | Show not-found state and refresh list if appropriate |
| `409` | Invalid lifecycle transition or state conflict | Explain that the account state changed or action is not allowed; refresh detail |
| `422` | Validation failure | Map `errors` to corresponding form fields |
| `429` | Login rate limit | Show a retry-later message; do not loop automatic login attempts |
| `5xx` | Server failure | Show safe error and retry option; do not claim success |

Validation details:
- Duplicate username is rejected.
- Duplicate student number is rejected.
- Student type requires student number.
- Invalid person types are rejected.
- New/custom passwords must meet the 12-character minimum.
- Password confirmation is required for the self-service password-change endpoint.

Never display raw stack traces or internal exception details.

---

## 7. Audit behavior

The backend records these User Management audit actions:

| Action | Trigger |
|---|---|
| `identity.user_created` | Account created |
| `identity.user_updated` | Account/person information updated |
| `identity.user_password_reset` | Administrator resets password |
| `identity.user_disabled` | Account disabled |
| `identity.user_deactivated` | Account deactivated |
| `identity.user_reactivated` | Disabled account reactivated |
| `identity.user_reinstated` | Deactivated account reinstated |

Audit records identify the actor and target entity. Password values are not stored in audit metadata.

The initial User Management frontend does not need to build an audit timeline unless separately approved. Audit persistence is backend responsibility.

---

## 8. Backend verification and known contract follow-ups

### 8.1 Verified test evidence

The full IAM test suite passed on 2026-10-31:

**48 tests passed, 233 assertions, zero failures.**

Relevant feature test coverage includes:
- `AdminPasswordResetTest`
- `CredentialLifecycleHttpTest`
- `CredentialLifecycleTest`
- `PlatformAdministratorTest`
- `UserAccountReactivationTest`
- `UserAccountStatusTest`
- `UserCreationAuditTest`
- `UserCreationPasswordPolicyTest`
- `UserCreationValidationTest`
- `UserManagementDetailsTest`
- `UserManagementListingTest`
- `UserManagementUpdateTest`

The listing test disables Laravel's `ThrottleRequests` middleware within the test class to avoid a test-only `429` during repeated login setup. Production login rate limiting remains enabled.

### 8.2 Explicit contract follow-ups, do not guess

The handoff source and implementation history establish the feature behavior and route inventory. A few exact response details should be confirmed from the live working tree or a backend-generated API fixture before final API client typing:

1. **Administrator authority field:** The original combined handoff specifies `platform_authority.is_administrator`, but the inspected `AuthController::userPayload()` source did not contain it. The frontend must not substitute `is_it_admin`. Backend owner must confirm/add the explicit field before the frontend relies on it for conditional controls.
2. **List query parameter names and paginator envelope:** The behavior is verified, but use the exact current `UserCreationController::index()` query names and Laravel response shape.
3. **User detail response envelope:** Use the exact `show()` response, not an assumed list-row shape.
4. **Update response envelope:** Use the exact `update()` response; refreshing detail after success is safe.
5. **Password reset response key:** Use the exact one-time temporary-password response key from `resetPassword()`.
6. **Account creation response:** Confirmed as `201 Created` with `message`, `person`, `user`, and `initial_password`.

These are narrow response-contract confirmations, not invitations to reopen product decisions or redesign the backend. The frontend team should not reverse-engineer business rules from controller code when the behavior is already specified here.

---

## 9. Frontend acceptance checklist

### User listing
- [ ] Lists existing IAM users.
- [ ] Searches username and person-name fields.
- [ ] Filters by active, disabled, and deactivated status.
- [ ] Uses backend pagination metadata.
- [ ] Handles empty, loading, and error states.

### User details and editing
- [ ] Loads full user detail.
- [ ] Updates personal information.
- [ ] Updates username with duplicate validation feedback.
- [ ] Supports multiple person types.
- [ ] Supports Student and Employee simultaneously.
- [ ] Shows and validates student number when Student applies.
- [ ] Does not imply that changing person types deletes Student identity.

### Account creation
- [ ] Creates account through the verified API.
- [ ] Handles all validation rules.
- [ ] Shows initial password once.
- [ ] Provides copy action.
- [ ] Explains forced password change at first login.
- [ ] Does not persist or log the temporary password.

### Password reset
- [ ] Supports generated and custom temporary passwords.
- [ ] Enforces 12-character minimum for custom password.
- [ ] Shows returned password once with copy action.
- [ ] Explains forced password change.
- [ ] Explains that existing sessions were revoked.

### Account lifecycle
- [ ] Distinguishes Disable from Deactivate.
- [ ] Offers Reactivate only for Disabled.
- [ ] Offers Reinstate only for Deactivated.
- [ ] Uses separate endpoints and confirmation wording.
- [ ] Refreshes account state after successful action.
- [ ] Does not imply old sessions are restored.
- [ ] Does not offer Disable/Deactivate for the designated Administrator.

### Security and integration
- [ ] Uses the existing IAM API client.
- [ ] Sends session cookies using existing project conventions.
- [ ] Never uses `is_it_admin` as authority.
- [ ] Does not treat UI visibility as authorization enforcement.
- [ ] Handles `401`, `403`, `404`, `409`, `422`, `429`, and `5xx`.
- [ ] Does not log passwords or temporary passwords.
- [ ] Uses only verified request/response contracts.

---

## 10. Implementation instruction to the frontend team

Implement the User Management frontend against this handoff and the exact API contracts from the current IAM backend.

Do not:
- Reopen the approved identity model.
- Introduce another account or person record.
- Treat person types as mutually exclusive.
- Delete Student identity when the Student person type changes.
- Introduce multiple Administrators, delegated administrators, or an Administrator role picker.
- Use `is_it_admin` as the source of Administrator authority.
- Merge account editing, password reset, and account lifecycle into one generic update operation.
- Treat Reactivate and Reinstate as interchangeable.
- Implement Legacy Apps Access in this User Management feature.
- Invent response fields where the handoff identifies an explicit contract follow-up.

If an exact response key is not specified in Section 8.2, ask the IAM backend owner for that specific response example. Do not ask the backend team to re-explain the product behavior already documented here.

**User Management backend is complete. The frontend team can proceed with its implementation.**
