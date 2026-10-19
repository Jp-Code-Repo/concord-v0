Concord Frontend Working Document
Purpose: Working reference for the concord-frontend implementation
chat.
Last updated: 2026-10-19
Status: Active working document. Update it as decisions are
verified.
1. Frontend Scope
The frontend chat owns the React application, UI, routing, layout, and
integration with confirmed backend contracts.

It does not own or independently define IAM authentication/authorization
rules, HRIS records, or unconfirmed backend API schemas. When a
requirement depends on another service, identify the contract gap and
ask that service's chat instead of inventing a payload or rule.
2. Current Stack and Location
- React, TypeScript, Vite
- Tailwind CSS v4
- shadcn/ui
- TanStack Router
- lucide-react
- Frontend location: services/concord-frontend/
The intended deployment model is for the React application to be served
at / by the Concord edge, with /api/iam/ routed to IAM. Verify
current repository configuration before changing deployment behavior.
3. Current Frontend Status
Implemented in preview mode
- Dashboard: welcome banner, applications section, recent activity,
  announcements, and preview indication.
- Shared layout: sidebar, topbar, global search, notification control,
  profile dropdown, sidebar collapse, and light/dark theme toggle.
- Profile route: /profile, rendered inside the existing Concord
  layout.
- Profile tabs: Personal Information, Employment, Account Settings,
  Security, and Preferences.
- Profile overview with preview identity and institutional
  information.
- Correction-request form, submission confirmation, reference number,
  Pending status, and request list.
- Appearance preferences connected to shared theme context.
- Quick actions include correction-request entry and My Correction
  Requests.
Known limitations
- Dashboard and profile values are sample data, not live IAM or HRIS
  data.
- Correction requests live only in React state and disappear after a
  full page refresh. They are not persisted to a backend.
- The correction-request UI is a preview, not an implemented HRIS
  workflow.
- Change Password, Notification Settings, Privacy & Security, and some
  other account actions remain placeholders unless source code has
  since changed.
- Live authentication, /me integration, IAM-driven navigation, and
  backend-connected profile workflows are not confirmed as
  implemented.
Do not describe preview features as production integrations.
4. Service Ownership
IAM owns
- Authentication and credential validation.
- Account identity and account status.
- Session lifecycle and revocation.
- Offices and office memberships.
- Applications, Groups, Blocks, Grants, and effective-access
  calculation.
- Authorization data and decisions.
HRIS owns
- Personal and employment records, including name, date of birth,
  contact information, employee ID, position, department, employment
  type, hire date, and employment status.
- The authoritative records and review process for personal/employment
  corrections, subject to confirmation by the HRIS owner.
concord-frontend owns
- Dashboard and profile presentation.
- Navigation and frontend routing.
- Rendering authorized applications and Blocks from confirmed IAM
  data.
- UI state, loading/error/empty states, and user-facing feedback.
- Calling backend operations through documented contracts.
Rule: The frontend renders access decisions; it does not recreate
IAM authorization logic. Hiding a navigation item is not a security
boundary. Protected backend operations must enforce authorization
server-side.
5. Access Model Relevant to the Frontend
The established conceptual hierarchy is:

User → Office Membership → Application → Group → Block

A Block is the smallest grantable unit in the original access model.
Normal staff
- Access comes from active office memberships and applicable explicit
  grants.
- Two staff members in the same office may have different Block
  access.
- Access from multiple eligible office memberships is additive.
- Ended memberships and revoked grants must no longer contribute to
  effective access.
- Historical memberships and grants should be preserved by the
  backend.
IT Administrator
- The original model uses is_it_admin = true to represent global
  access.
- IT administers the application pool and office-to-application
  assignments.
- Confirm the current API representation before implementing frontend
  behavior.
Office Head: preserve the decision history
- Earlier discussions described broad access to Blocks within an
  office's applications.
- A later, more specific IAM implementation model uses a configured
  office_head_blocks list and a separate can_delegate setting
  configured per office.
- In that later model, the configured list determines which Blocks the
  Office Head can use; can_delegate determines whether a Block can
  be delegated to staff. A false delegation setting does not by itself
  remove the Head's own access to that Block.
Do not assume every Block is automatically available to every Office
Head unless IAM confirms this as the current approved rule.
Multiple offices and navigation
The later implementation context records these intended behaviors: -
Office is not an active runtime context. Users do not need to switch
offices before using authorized functionality. - The primary office
establishes the home dashboard experience. - Additional office
memberships add authorized functionality without replacing the home
experience. - If the same application is accessible through two offices,
represent it separately under each office in navigation so the access
relationship remains visible. - An authenticated user with no office
assignments should still receive the basic Concord shell and a clear
"You aren't assigned yet" message, without fabricated office/application
navigation.

Implement these behaviors against the final IAM response contract, not
guessed fields.
Prerequisite View access
A separate discussion recorded a proposal to automatically include
required View access when granting an action permission. Its boundaries
included not granting unrelated actions, not exposing every sensitive
field, distinguishing direct grants from derived access, and
recalculating derived access when grants change.

This needs reconciliation with the current IAM Block model and
implementation. Do not build frontend assumptions about derived View
grants until IAM confirms the effective-access contract.
6. IAM Integration Contract: Confirm Before Coding
Prior testing notes reported these endpoints and behaviors, but recheck
them against the current repository and IAM owner before treating them
as final:
- POST /api/iam/login
- GET /api/iam/me
- POST /api/iam/logout
- GET /api/iam/verify for the edge's internal authentication
  subrequest
Prior observations included login returning HTTP 200, logout returning
HTTP 204, and unauthenticated /me returning HTTP 401. A previous /me
response was reported to include all_access, apps, and offices.
These observations are not a substitute for the current API contract.

The documented session model uses an opaque concord_session cookie and
server-side sessions. The edge design describes checking protected
requests through IAM's internal verification route. Current code and
runtime behavior must be verified separately from design notes.
Required contract confirmations
Before implementing live frontend integration, obtain: 1. Login request
fields and validation/error responses. 2. Cookie/session behavior,
including credentials and any CORS requirements. 3. The exact
/api/iam/me identity and effective-access response schema. 4.
Primary-office representation. Do not infer it from array order. 5. How
global access is represented. 6. Office Head access and delegation
behavior. 7. How applications, Groups, and Blocks are represented,
including office-specific relationships. 8. Logout and
expired/revoked-session behavior. 9. Whether /api/iam/verify is
internal-only and which component consumes it. 10. Which IAM
account/security operations are implemented now versus planned.

Use actual schemas and example JSON from IAM. Do not invent properties
or silently adapt the contract in the frontend.
7. Profile Data Ownership
The profile preview combines two ownership areas: - HRIS: personal
and employment information, such as name, date of birth, contact
details, employee ID, position, department, employment type, hire date,
and employment status. - IAM: login identity, account status,
authentication/security operations, offices, roles, permissions, and
application access.

The profile UI must eventually distinguish these sources where needed.
Sample data should remain visibly marked as preview data until replaced
by authoritative values.

The correction-request workflow must eventually submit to an agreed
backend owner and support the appropriate review process. The current
frontend-only implementation does not mean HRIS has accepted, stored, or
approved a request.
8. Working Rules for This Frontend Chat
1. Inspect sources first. Check project files, attachments, and
   prior conversation context before asking for a file or detail. Do
   not ask for information already available.
2. One step at a time. Provide one actionable step and wait for
   verification before continuing.
3. Maintain the plan. When an error occurs, diagnose it from actual
   code and the exact error, fix it one step at a time, then return to
   the original task.
4. Use exact paths and commands. Avoid vague instructions and
   invented files, functions, packages, or APIs.
5. Match the repository. Existing project conventions take
   precedence. Do not redesign architecture or refactor unrelated code
   without approval.
6. Verify changes. Run the relevant build or test and report the
   actual result.
7. Be explicit about uncertainty. Separate confirmed
   implementation, agreed design, historical decisions, and unresolved
   contract gaps.
8. Use handoffs. If another service is affected, identify the
   owning chat, the change/question, any exact contract addition, and
   the action required.
9. Ask before product/architecture decisions. Do not choose among
   unresolved options on the user's behalf.
10. Protect secrets. Never echo secret configuration values.
9. Recommended Next Work
The correction-request form has been judged sufficient for the current
preview. Do not redesign it without a new request.

Recommended sequence: 1. Confirm the current IAM API contract,
especially /api/iam/me. 2. Map the confirmed response to a typed
frontend data model without inventing fields. 3. Integrate
authentication/session-aware loading and error states according to the
contract. 4. Render navigation from IAM effective-access data. 5.
Replace preview identity/access data only when authoritative sources and
ownership boundaries are clear. 6. Connect placeholder account actions
only after the responsible service confirms endpoints and workflows.

This is a recommended sequence, not a claim that these steps are
implemented.
10. Open Questions / Contract Gaps
- What is the authoritative /api/iam/me response schema?
- How is the primary office represented?
- What is the exact response structure for effective applications,
  Groups, Blocks, and office relationships?
- What is the final Office Head Block/delegation rule?
- How is global IT Administrator access represented in the API?
- What are the agreed session-cookie and credential requirements for
  frontend requests?
- Which service owns and exposes the correction-request workflow?
- Which IAM endpoints currently support password changes, account
  security, and notification preferences, if any?
- How should the frontend handle authenticated but unassigned users?
- Has prerequisite View behavior been incorporated into the current
  IAM model, and how is it represented in effective access?
Resolve these with the owning service chat rather than guessing.
Change Log
- 2026-10-19: Initial working document consolidated from the
  frontend discussion and available Concord IAM/edge working notes.
  Preview status and unresolved API-contract questions are explicitly
  identified.