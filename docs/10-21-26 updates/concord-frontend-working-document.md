---
title: Concord Frontend Working Document
date: 2026-10-21
tags:
  - concord/frontend
  - concord/working-document
aliases:
  - Concord Frontend Working Document
  - concord-frontend
  - Concord Frontend
status: active-working-document
---

# Concord Frontend Working Document

**Purpose:** Working reference for the concord-frontend implementation chat.
**Last updated:** 2026-10-21 (previous version: 2026-10-19)
**Status:** Active working document. Update it as decisions are verified.

> **How to read this note.** Statements are labelled by how much weight they carry: **Agreed** (an approved decision exists), **Reported** (observed in earlier testing or a code snapshot, not a formal contract), **Historical** (superseded), or **Unresolved** (a contract gap or open decision). Where a decision record exists, it wins over this note: [[iam-authorization-decisions]] for IAM and [[hris-decisions]] for HRIS. Nothing here has been re-verified against the repository.

## 1. Frontend Scope

The frontend chat owns the React application, UI, routing, layout, and integration with confirmed backend contracts.

It does not own or independently define IAM authentication/authorization rules, HRIS records, or unconfirmed backend API schemas. When a requirement depends on another service, identify the contract gap and ask that service's chat instead of inventing a payload or rule.

## 2. Current Stack, Location and Deployment

- React, TypeScript, Vite
- Tailwind CSS v4
- shadcn/ui
- TanStack Router
- lucide-react
- Frontend location: `services/concord-frontend/`

**Deployment model (Agreed design, see [[edge]]):** the React app is served at `/` by the Concord edge, with `/api/iam/` routed to IAM. The edge falls back to `index.html` so TanStack Router client-side routes work. A discussion diagram (screenshot, 2026-10-19) shows the browser reaching the edge, which serves the built app from `services/concord-frontend/dist/`. `edge.md` still describes `html/index.html` as a placeholder that the React app replaces later.

**Not verified:** the current `docker-compose.yml` mount, nginx root, and build output path. Check the repository before changing deployment behavior.

## 3. Current Frontend Status

*As of the 2026-10-19 version of this note; not re-verified.*

### Implemented in preview mode

- Dashboard: welcome banner, applications section, recent activity, announcements, and preview indication.
- Shared layout: sidebar, topbar, global search, notification control, profile dropdown, sidebar collapse, and light/dark theme toggle.
- Profile route: `/profile`, rendered inside the existing Concord layout.
- Profile tabs: Personal Information, Employment, Account Settings, Security, and Preferences.
- Profile overview with preview identity and institutional information.
- Correction-request form, submission confirmation, reference number, Pending status, and request list.
- Appearance preferences connected to shared theme context.
- Quick actions include correction-request entry and My Correction Requests.

### Known limitations

- Dashboard and profile values are sample data, not live IAM or HRIS data.
- Correction requests live only in React state and disappear after a full page refresh. They are not persisted to a backend.
- The correction-request UI is a preview, not an implemented HRIS workflow.
- Change Password, Notification Settings, Privacy & Security, and some other account actions remain placeholders unless source code has since changed.
- Live authentication, `/me` integration, IAM-driven navigation, and backend-connected profile workflows are not confirmed as implemented.

**Do not describe preview features as production integrations.**

## 4. Service Ownership and Responsibilities

Summary of [[responsible-for-what]], extended with the HRIS decisions.

### IAM owns

- Authentication and credential validation.
- Account identity and account status; creation of user accounts.
- Session lifecycle and revocation.
- Offices and office memberships. HRIS determines an employee's home/primary office; IAM stores the resulting assignment (see [[IAM - Access Model]]).
- Applications, Groups, Blocks, Grants, and effective-access calculation.
- Authorization data and decisions.

### HRIS owns

- The authoritative employee profile and data pool ([[hris-decisions#DEC-HRIS-007 – HRIS owns the authoritative employee profile and data pool|DEC-HRIS-007]]): personal and employment records, including name, date of birth, contact information, employee ID, position, department, employment type, hire date, and employment status.
- The department catalog and department assignments ([[hris-decisions#DEC-HRIS-001 – HRIS owns departments and initiates IAM account provisioning|DEC-HRIS-001]], [[hris-decisions#DEC-HRIS-002 – One active department assignment per employee|DEC-HRIS-002]]).
- The authoritative records and review process for personal/employment corrections. The correction-request workflow itself is still unconfirmed by the HRIS owner.
- HRIS requests account creation from IAM through an explicit API. It never writes to the IAM database ([[hris-decisions#DEC-HRIS-001 – HRIS owns departments and initiates IAM account provisioning|DEC-HRIS-001]], [[hris-decisions#DEC-HRIS-009 – HRIS Requests IAM Account Creation|DEC-HRIS-009]]).

### concord-frontend owns

- Dashboard and profile presentation, navigation, and frontend routing.
- The login page: submitting credentials, checking whether a session exists, displaying the authenticated user's information.
- Route guards and redirects, logout, and handling of expired sessions.
- Rendering authorized applications and Blocks from confirmed IAM data.
- UI state, loading/error/empty states, and user-facing feedback.
- Calling backend operations through documented contracts.

### Edge and application services own

- The edge verifies sessions for protected service routes.
- Application services enforce authorization for their protected operations. They ask IAM for a current decision before each one ([[iam-authorization-decisions#DEC-AUTH-012 – Concord services must check current IAM authorization for protected operations|DEC-AUTH-012]]).
- Neither relies on the frontend's route guards as a security boundary.

**Rule:** The frontend renders access decisions; it does not recreate IAM authorization logic. Hiding a navigation item is not a security boundary. Protected backend operations must enforce authorization server-side.

## 5. Access Model Relevant to the Frontend

The conceptual hierarchy is **User → Office Membership → Application → Group → Block**. A Block is the smallest grantable unit ([[iam-authorization-decisions#DEC-AUTH-001 – Blocks are the smallest grantable unit|DEC-AUTH-001]]).

### Normal staff (Agreed)

- Access comes from active grants on active office memberships ([[iam-authorization-decisions#DEC-AUTH-005 – Staff access requires active grants and active membership|DEC-AUTH-005]]).
- Two staff members in the same office may have different Block access.
- Ended memberships and revoked grants must no longer contribute to effective access. History is preserved by the backend ([[iam-authorization-decisions#DEC-AUTH-008 – Preserve authorization history|DEC-AUTH-008]]).
- Office boundaries are mandatory: access in one office must not leak into another ([[iam-authorization-decisions#DEC-AUTH-006 – Office boundaries are mandatory|DEC-AUTH-006]]).

### View access is explicit (Agreed; replaces an earlier proposal)

The earlier proposal to auto-include View access when an action is granted is a **rejected historical proposal** ([[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit|DEC-AUTH-002]]). IAM does not grant a View Block because another Block is granted, and the frontend must not assume every Group has a View Block or that an action Block depends on one. Whether Block dependencies exist at all is open ([[iam-authorization-decisions#OPEN-AUTH-003 – Are there Block dependency rules beyond explicit grants?|OPEN-AUTH-003]]). Build no frontend assumptions about derived or prerequisite grants.

### Office Heads (Agreed)

- An Office Head uses only the Blocks configured for their office in `office_head_blocks`, limited to applications assigned to that office ([[iam-authorization-decisions#DEC-AUTH-003 – Office Head access is configured per office|DEC-AUTH-003]]). They do not receive every Block.
- `can_delegate` controls whether an Office Head may delegate a configured Block to staff. `false` does not remove the Head's own access ([[iam-authorization-decisions#DEC-AUTH-004 – can_delegate controls delegation, not personal access|DEC-AUTH-004]]).
- Historical: the older "Office Heads receive all Blocks" model must not return without a new decision. Further Office Head restrictions are open ([[iam-authorization-decisions#OPEN-AUTH-004 – Office Head restrictions beyond office_head_blocks|OPEN-AUTH-004]]).
- Employee category is separate: Head of Office is an assignment held by Staff or Teacher employees. It does not itself grant access.

### IT Administrator and Administrator (Agreed, representation open)

- An IT Administrator has global application access ([[iam-authorization-decisions#DEC-AUTH-007 – IT administrator access is global|DEC-AUTH-007]]). The original model represents this with `is_it_admin = true`. Confirm the current API representation before building behavior on it.
- A distinct `Administrator` authority has universal Block/action access, with authentication still required ([[iam-authorization-decisions#DEC-AUTH-014 – Administrator authority has universal Block/action access|DEC-AUTH-014]]). It is **not** the same as IT Administrator, and IT department membership does not confer it.
- How Administrator is represented (role, account, or both) is open ([[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries|OPEN-AUTH-005]]). Do not build admin-bypass UI or equate it with `is_it_admin`.

### Multiple offices and navigation (Unresolved)

Whether an active office is a runtime authorization context is **unresolved** ([[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]]). The current IAM implementation returns combined access across a user's offices, but that is not an approved model.

The 2026-10-19 version of this note recorded the following as intended behaviors. They are the frontend's **working assumptions** against the current combined-access response, not approved IAM rules:

- Users do not switch offices before using authorized functionality.
- The primary office establishes the home dashboard experience.
- Additional office memberships add authorized functionality without replacing the home experience.
- If the same application is accessible through two offices, represent it separately under each office in navigation so the access relationship remains visible.
- An authenticated user with no office assignments still gets the basic Concord shell and a clear "You aren't assigned yet" message, with no fabricated office/application navigation. This is frontend UX; telling this user apart from a global IT administrator depends on the deferred `/me` contract (section 6).

If [[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]] adopts an active office, the edge and `verify` must also return it and the frontend contract changes. Do not add an office selector or send office context from the browser until that decision is recorded.

## 6. IAM Integration Contract: Confirm Before Coding

### 6.1 Endpoints and who may call them

| Endpoint | Purpose | Frontend may call? | Status |
|---|---|---|---|
| `POST /api/iam/login` | Credential login, sets `concord_session` cookie | Yes | Reported working (HTTP 200). Rate limited at the edge: 30/min per IP, burst 10 |
| `GET /api/iam/me` | Current identity and effective access | Yes | Reported working; response schema is a deferred IAM contract task |
| `POST /api/iam/logout` | Revoke session, expire cookie | Yes | Reported HTTP 204 |
| `GET /api/iam/verify` | Edge's internal session check | **No.** The edge returns 404 to outside callers | Agreed design; tested against a mock backend |
| `POST /api/iam/internal/authorize-block` | Service-to-service Block check | **Never.** Internal network only, per-service credential | Proposal only, not implemented |
| `/api/iam/admin/apps` (GET, POST, PATCH `/{appId}`) | Application catalog administration | Only for an IT-admin UI, if one is built | Implemented per [[IMPLEMENTATION_NOTES]]; tests not run in that workspace |
| `/api/hr/...` | HRIS | Not yet | Only a commented template exists in the edge config |

The frontend never holds or sends service credentials, and never supplies a user ID for authorization. See [[iam-internal-authorization-contract-proposal]] and [[HRIS-IAM-Authorization-Handoff]].

### 6.2 Session model and a known blocker

The session is an opaque `concord_session` cookie backed by server-side sessions. The edge checks protected service routes through `verify`. `/api/iam/me` checks the cookie inside IAM and does not use `auth_request`.

**Session bug status.** [[edge]] (section 8) still describes a parked IAM session bug: login works, but the cookie is not accepted afterwards. The project owner reports it was fixed on 2026-10-20 (commit `03eb8c0`, "fix(iam): correct API error responses and smoke tests") with all 11 smoke tests passing, so that section of the edge note is stale. This is not independently verified here. Confirm on current `main` that login, `/me`, and logout work through the edge before building session-aware UI.

### 6.3 What `/me` is reported to contain (evidence, not contract)

The earlier note recorded `all_access`, `apps`, and `offices`. The IAM-side handoff describes them under an `access` object, including `access.offices[].apps`, `access.offices[].blocks`, and an office-head indicator `is_head`. These descriptions disagree on nesting and neither is an approved schema. Use IAM's actual example JSON. Do not code against these names until IAM confirms them.

### 6.4 Required contract confirmations

Obtain these from IAM before live integration. Use actual schemas and example JSON, and do not invent properties or silently adapt the contract in the frontend.

1. Login request fields and validation/error responses, including the rate-limit failure response.
2. Cookie/session behavior: credentials mode, any CORS or dev-proxy needs, `HttpOnly`, `SameSite`, and `COOKIE_SECURE` over plain HTTP.
3. The exact `/api/iam/me` identity and effective-access schema. IAM has **deferred** this and asks that unrelated frontend work not be blocked on it.
4. Primary-office representation. HRIS decides the home office and IAM stores it. Do not infer it from array order.
5. How global access is represented, and the stable distinction between a global IT administrator and an authenticated user with no assignments (`access.all_access`, `access.apps`, `access.offices`).
6. Office Head representation (`is_head`) against the adopted `office_head_blocks` rule.
7. How applications, Groups, and Blocks are represented per office, and whether Block entries are unique so counts can be derived safely.
8. Logout and expired/revoked-session behavior.
9. ~~Whether `verify` is internal-only~~ Answered by [[edge]]: it is hidden from outside and consumed only by the edge's `/_auth` subrequest.
10. Which IAM account/security operations (password change, notification preferences) are implemented now versus planned.
11. CSRF policy for state-changing requests. The edge note lists this as undecided, which affects logout and any later POST/PUT/DELETE.

## 7. Profile Data Ownership

The profile combines two ownership areas.

- **HRIS:** personal and employment information.
- **IAM:** login identity, account status, authentication/security operations, offices, roles, permissions, and application access.

The profile UI must distinguish these sources where needed. Sample data stays visibly marked as preview data until replaced by authoritative values.

HRIS decisions that shape the eventual profile integration (all Agreed, none verified as implemented):

- The employee profile is a presentation over one authoritative data pool, not a competing copy ([[hris-decisions#DEC-HRIS-007 – HRIS owns the authoritative employee profile and data pool|DEC-HRIS-007]]).
- Basic Information is a configurable view with a global default and per-service customization. The frontend should render what the API returns rather than hardcode the field list, and hiding a field is not a security control ([[hris-decisions#DEC-HRIS-005 – Basic Information is configurable globally and per service|DEC-HRIS-005]]).
- Employees can have multiple emails and phone numbers, each with a primary selection. Profile contact displays should expect repeatable records ([[hris-decisions#DEC-HRIS-006 – Store employee email addresses and phone numbers as separate repeatable collections|DEC-HRIS-006]]).
- Institutional classification: Employee is the common identity, with Staff and Teacher as categories, and Head of Office as an assignment held by either. This describes people, not access. Never derive navigation or permissions from employee category (see [[IAM - Access Model]]).

The correction-request workflow must eventually submit to an agreed backend owner and support the appropriate review process. The frontend-only implementation does not mean HRIS has accepted, stored, or approved a request.

## 8. Approved HRIS Product Requirements That Touch the Frontend

All are product requirements from [[hris-decisions]]. None is implemented or verified.

- **Guided employee onboarding ([[hris-decisions#DEC-HRIS-010 – Guided Employee Onboarding|DEC-HRIS-010]]).** After sign-in, a dialog with Previous/Next controls guides the employee through rules and process, the Personal Data Sheet in manageable sections, a physical-document checklist, HR confirmation of received items, and the physical file location. Contract workflow is out of scope.
- **First-time Concord orientation ([[hris-decisions#DEC-HRIS-011 – First-Time Concord User Orientation|DEC-HRIS-011]]).** A separate orientation for any new Concord user. It explains the interface, profile, where information lives, and how to request corrections where that exists. It must be aware of the user's role and permissions and must not describe features the user cannot access. Replay, progress persistence, role variation, and the final content are undecided.
- **Create Concord Account ([[hris-decisions#DEC-HRIS-009 – HRIS Requests IAM Account Creation|DEC-HRIS-009]]).** An HRIS action that asks IAM to create the account. The IAM provisioning API and minimum field set are unconfirmed.

Orientation that adapts to the user's access depends on the deferred `/me` contract.

## 9. Working Rules for This Frontend Chat

1. **Inspect sources first.** Check project files, attachments, and prior conversation context before asking for a file or detail. Do not ask for information already available.
2. **One step at a time.** Provide one actionable step and wait for verification before continuing.
3. **Maintain the plan.** When an error occurs, diagnose it from actual code and the exact error, fix it one step at a time, then return to the original task.
4. **Use exact paths and commands.** Avoid vague instructions and invented files, functions, packages, or APIs.
5. **Match the repository.** Existing project conventions take precedence. Do not redesign architecture or refactor unrelated code without approval.
6. **Verify changes.** Run the relevant build or test and report the actual result.
7. **Be explicit about uncertainty.** Separate confirmed implementation, agreed design, historical decisions, and unresolved contract gaps.
8. **Use handoffs.** If another service is affected, identify the owning chat, the change/question, any exact contract addition, and the action required.
9. **Ask before product/architecture decisions.** Do not choose among unresolved options on the user's behalf.
10. **Protect secrets.** Never echo secret configuration values.

## 10. Recommended Next Work

The correction-request form has been judged sufficient for the current preview. Do not redesign it without a new request.

Recommended sequence (not a claim that any step is implemented):

1. Confirm on current `main` that login, `/me`, and logout work through the edge (session fix reported 2026-10-20).
2. Confirm login request/response and error shapes with IAM.
3. Build the login page, session check, route guards, logout, and expired-session handling against those documented responses. This does not need the deferred `/me` schema.
4. When IAM publishes the `/me` contract, map it to a typed frontend model without inventing fields.
5. Render navigation from IAM effective-access data, including the "You aren't assigned yet" state.
6. Replace preview identity/access data only when authoritative sources and ownership boundaries are clear.
7. Connect placeholder account actions only after the responsible service confirms endpoints and workflows.

## 11. Open Questions / Contract Gaps

### Still open: IAM

- What is the authoritative `/api/iam/me` schema? (Deferred by IAM.)
- How is the primary office represented?
- How are effective applications, Groups, Blocks, and office relationships structured, and are Blocks unique?
- How is global IT Administrator access represented, and how does it differ from an unassigned user?
- Is an office an active runtime authorization context? ([[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]])
- How will the distinct Administrator authority be represented? ([[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries|OPEN-AUTH-005]])
- What session-cookie, credential, CORS, and CSRF requirements apply to frontend requests?
- Which IAM endpoints support password change, account security, and notification preferences, if any?

### Still open: HRIS and product

- Which service owns and exposes the correction-request workflow?
- Does concord-frontend host the HRIS screens (onboarding dialog, Create Concord Account), or does HRIS ship its own UI?
- Where is onboarding and orientation progress persisted, and who owns it?

### Answered since 2026-10-19

- **Prerequisite View behavior:** not part of the IAM model. Auto-granting is a rejected proposal ([[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit|DEC-AUTH-002]]); any dependency mechanism is open ([[iam-authorization-decisions#OPEN-AUTH-003 – Are there Block dependency rules beyond explicit grants?|OPEN-AUTH-003]]).
- **Office Head rule:** configured `office_head_blocks` plus `can_delegate` is adopted ([[iam-authorization-decisions#DEC-AUTH-003 – Office Head access is configured per office|DEC-AUTH-003]], [[iam-authorization-decisions#DEC-AUTH-004 – can_delegate controls delegation, not personal access|DEC-AUTH-004]]). The decisions record notes that approval history should be confirmed if it matters for audit.
- **`verify` is internal-only:** the edge hides it and consumes it itself.
- **Who determines the primary office:** HRIS decides, IAM stores. Representation is still open.
- **Access engine:** Spatie Permission is deferred ([[iam-authorization-decisions#DEC-AUTH-011 – Defer Spatie Permission and retain Concord IAM as the access authority|DEC-AUTH-011]]). Access remains Application → Group → Block in IAM.

Resolve open items with the owning service chat rather than guessing.

## Related notes

- [[Concord Index]]
- [[IAM - Access Model]]
- [[iam-authorization-decisions]]
- [[edge]]
- [[hris-decisions]]
- [[responsible-for-what]]
- [[iam-internal-authorization-contract-proposal]]
- [[HRIS-IAM-Authorization-Handoff]]
- [[IMPLEMENTATION_NOTES]]
- [[Reconciliation 2026-10-21]]

## Change Log

- 2026-10-21: Reconciled with the 2026-10-21 IAM, edge, HRIS, and responsibility notes. Marked the multi-office behaviors as working assumptions under [[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]]; replaced the prerequisite-View proposal with [[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit|DEC-AUTH-002]]; recorded Office Head rule as adopted; separated Administrator from IT Administrator; added the endpoint access table, the session-bug status, the `/me` evidence and deferral, the CSRF gap, HRIS ownership and product requirements, and the answered/open question split. Converted to Obsidian format.
- 2026-10-19: Initial working document consolidated from the frontend discussion and available Concord IAM/edge working notes. Preview status and unresolved API-contract questions are explicitly identified.
