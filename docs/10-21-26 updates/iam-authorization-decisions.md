---
title: IAM Authorization Decisions
date: 2026-10-21
tags:
  - concord/iam
  - concord/decisions
aliases:
  - IAM decisions
  - authorization decisions
status: authoritative-record
---

**Status:** Working authority for IAM authorization decisions

**Last reconciled:** 2026-10-21 (includes internal Block authorization contract decisions; implementation pending)

**Scope:** Authorization semantics, service-to-service Block checks, runtime authorization auditing, and the rules that affect effective access. This document does not replace the repository as the source of truth for implementation.

**Purpose:** Keep approved rules, current implementation behavior, superseded proposals, and open decisions distinguishable.

## 1. How to use this record

1. Adopted rules are the current implementation direction.
2. Implementation lines say what the code does or is expected to do. Implementation alone does not prove architectural approval, and every Implementation line is unverified until it is checked against the repository and a test run.
3. Open decisions must not be resolved by guessing. Ask for an explicit decision, record it here, then implement.
4. Historical notes and handoffs give context. This document records the reconciled outcome.
5. If another note (including docs/iam.md, code comments, or chat summaries) conflicts with this record, follow this record and do not silently pick a side on anything marked Open.

## 2. Adopted authorization rules

### DEC-AUTH-001 – Blocks are the smallest grantable unit

- The hierarchy is Application → Group → Block.
- Groups are organizational containers. Access is granted at Block level.
- Applications declare their catalog of Groups and Blocks.
- A newly declared Block gives nobody access unless an explicitly approved policy says otherwise.

### DEC-AUTH-002 – View access is explicit

- IAM must not automatically grant a view Block because another Block is granted.
- Do not assume every Group has a View Block or that every edit/action Block depends on View. A Block may be self-contained, or have a dependency defined by its application.
- Any prerequisite behavior must be explicitly represented and approved, never inferred from a Block's name. See OPEN-AUTH-003.
- Historical conflict: a Daemon handoff proposed auto-granting View when another action required it. That is a rejected historical proposal, not the current rule.

### DEC-AUTH-003 – Office Head access is configured per office

- office_head_blocks defines which Blocks an Office Head may use for their office.
- Access is limited to applications assigned to that office (office_apps).
- An Office Head does not receive every Block in every office application by default.
- Approval note: an earlier revision labeled this "current implementation model; approval history must remain visible." The latest revision lists it as adopted. Confirm explicitly if approval history matters for audit.
- Historical conflict: the older "Office Heads receive all Blocks by default" model must not return without an explicit decision.

### DEC-AUTH-004 – can_delegate controls delegation, not personal access

- can_delegate = true lets an Office Head delegate the configured Block to eligible staff in their office.
- can_delegate = false prevents Office Head delegation of that Block. It does not remove the Office Head's own configured access.
- IT administrators are exempt. They may grant a Block regardless of can_delegate, because they administer the access policy. They remain subject to validation, office/application rules, and audit logging.
- Supersedes: the earlier record (AUTH-004 / AUTH-007) said the GrantController also enforced can_delegate for IT-admin grants and that this must be preserved. That is reversed here.
- Implementation: if the GrantController rejects an IT-admin grant solely because can_delegate = false, that is a defect to fix, not a rule to preserve.

### DEC-AUTH-005 – Staff access requires active grants and active membership

- Staff receive Blocks from active grants attached to active office memberships.
- A grant must be inside the target membership's office boundary.
- The target membership must be active and belong to the office in which the grant is managed.
- Access is never inferred from job title alone.

### DEC-AUTH-006 – Office boundaries are mandatory

- Office Heads manage access and staff only within their own office.
- An application must be assigned to the office for its Blocks to be managed there.
- Access in one office must not leak into another.

### DEC-AUTH-007 – IT administrator access is global

- An authenticated IT administrator has global application access.
- Administrative grant and revoke actions remain subject to validation, office/application rules, and audit logging. They are not subject to can_delegate (DEC-AUTH-004).

### DEC-AUTH-008 – Preserve authorization history

- Grant history is never deleted on revocation. Revocation records actor, time, and reason where the schema supports it.
- Re-granting creates a new grant row; a revoked row is never reactivated.
- Memberships are ended with ended_at, not erased. Ending a membership must stop its grants from providing active access.
- Every access-changing action is audit logged.
- Grant, revoke, and membership changes must invalidate affected effective-access caches.

### DEC-AUTH-009 – Disabled accounts lose access immediately

- Accounts are disabled, not deleted.
- Disabling an account must invalidate its sessions, and session verification must reject disabled users.
- The edge relies on this: /_auth calls IAM verify, and a 401 there is what makes disabling take effect at once (see [[edge]]).
- Provenance: carried over from the original [[IAM - Access Model|iam]] ("must invalidate") and the edge design. The later [[IAM - Access Model|iam]] softened it to "under the current implementation"; it is restored as a requirement here.

### DEC-AUTH-010 – Account disablement is restricted to IT Administrators

- Approval status: Approved
- Implementation status: Implemented and verified by automated tests (`UserManagementTest`)
- Only an authenticated IT Administrator may disable an account.
- The disable operation must set the account status to disabled, populate disabled_at, revoke all active sessions with reason account_disabled, invalidate both effective-access cache keys, and write an audit entry.
- The operation must preserve the account record and must not leave valid sessions usable after the disable action succeeds.
- This decision establishes the immediate authorization rule only. The distinction between a System Administrator and an IT Administrator remains unresolved under OPEN-AUTH-005.

### DEC-AUTH-011 – Defer Spatie Permission and retain Concord IAM as the access authority

- Approval status: Approved
- Implementation status: No Spatie integration is planned at this stage.
- Concord's existing Application → Group → Block model remains the source of truth for platform access, including grants and effective-access decisions.
- Do not mirror every Concord Block, grant, or role into Spatie Permission by default. A parallel representation would introduce synchronization requirements and ambiguity if the records disagree.
- Concord's existing model already covers its current requirements for office boundaries, Office Head delegation, IT Administrator authority, grant/revocation history, effective-access calculation, auditing, and cache invalidation.
- Laravel's built-in Gates and Policies may be used to organize authorization checks within individual services without introducing Spatie Permission.
- Reconsider Spatie only when a concrete requirement demonstrates a meaningful benefit that cannot be addressed cleanly by Concord IAM or Laravel's built-in authorization facilities.
- Any future proposal must identify Spatie's exact responsibility, preserve Concord IAM as the authority for platform access, explain any data duplication and synchronization, and demonstrate that revocation, membership, office-boundary, and delegation rules cannot be bypassed.
- This decision does not resolve OPEN-AUTH-001, OPEN-AUTH-003, OPEN-AUTH-005, or any other open IAM decision.

### DEC-AUTH-012 – Concord services must check current IAM authorization for protected operations

- Approval status: Approved in project discussion on 2026-10-21.
- Implementation status: Not implemented; internal endpoint and service authentication are pending.
- A Concord business service must ask IAM for the current authorization decision before each protected backend operation.
- IAM is the authority for the decision; the calling service enforces the result and must not recreate IAM grant logic.
- A revoked grant or ended membership must deny the next check. A prior successful response must not be treated as lasting authorization.
- If IAM cannot be reached or the decision cannot be verified, the caller must fail closed and reject the protected operation.
- Normal users are checked against the requested Block and relevant office. Explicit access in another office may authorize that office's resource, but access must not leak between offices.
- The final request schema, endpoint path, transport errors, and response reason-code list remain pending in [[iam-internal-authorization-contract-proposal]].

### DEC-AUTH-013 – Runtime authorization checks are audited with append-only records

- Approval status: Approved in project discussion on 2026-10-21.
- Implementation status: Not implemented or verified.
- Log both successful and denied internal authorization checks.
- Record the calling service, user being checked, Block key, relevant office ID, allow/deny decision, reason code, and timestamp.
- Never log service credentials or secrets.
- Authorization audit records are append-only through normal application operations. Corrections or additional context must be recorded as new entries, not by editing or deleting prior records.
- Retention period is deliberately deferred and remains open; it does not block the HRIS integration contract.

### DEC-AUTH-014 – Administrator authority has universal Block/action access

- Approval status: Approved in project discussion on 2026-10-21.
- Implementation status: Not implemented; role representation and enforcement require reconciliation with the existing IAM model.
- The conceptual `Administrator` authority is above department/office heads and has universal access to any Block/action without individual Block grants.
- Authentication remains mandatory. Universal access bypasses Block-level grant checks, not authentication.
- IT department membership or IT staff status does not itself confer this authority.
- Delegation of Administrator powers is on hold and must not be implemented from this decision.
- This is distinct from the existing `IT Administrator` behavior described in DEC-AUTH-007. Do not silently equate the two, rename the existing role, or change IT Administrator behavior as part of implementation.
- The authority is represented through a dedicated, extensible platform-authority mechanism, separate from `users.is_it_admin`, office memberships, and ordinary Block grants. The logical model requires an authority catalog and user-to-authority assignments; physical table and column names must follow repository conventions during implementation.
- `Administrator` is the first platform authority and has universal Block/action access while authenticated.
- `Demi-Admin` is a separate delegated authority. Its application access uses existing Blocks/actions; sensitive IAM operations use a fixed administrative capability catalog.
- Each administrative capability must explicitly declare whether it is delegable. Only capabilities marked delegable may be assigned to a Demi-Admin. The Administrator explicitly assigns the capability and its scope. Demi-Admins cannot assign or revoke capabilities for themselves or others.
- Only an Administrator may grant or revoke the `Demi-Admin` authority. IT Administrator status does not confer Administrator or Demi-Admin authority and does not permit assigning either authority.
- Platform-authority assignments preserve history. Revocation closes the assignment rather than deleting it; a later assignment creates a new record. Record actor, timestamps, and reason where supported. Use append-only audit records for authority changes.
- Capability revocation takes effect on the next authorization check. Authorization must use current state, not stale cached permissions.
- The first Administrator is established through a controlled, one-time, server-side bootstrap. The bootstrap may create the initial IAM user and Administrator authority assignment together. Account creation, authority assignment, and activation record must be atomic in the database and safely retryable.
- Bootstrap creates a cryptographically random, single-use activation secret with a 24-hour lifetime. Store only its hash in the database. Write plaintext only to a restricted temporary file inside the IAM container, never to logs or a public endpoint. The trusted operator retrieves it through an administrative Docker command.
- If the secret expires unused, a trusted recovery command may issue a replacement only while initial activation remains pending. It must invalidate the previous secret, safely replace the existing file, and not duplicate the user or authority assignment. A failed file operation must not be reported as successful.
- After successful activation, IAM attempts to remove the plaintext secret file. Cleanup failure must be safely recorded; the consumed secret remains unusable. Bootstrap and reissuance must be unavailable after initial activation succeeds.
- Disabled-Administrator behavior remains unresolved. Do not infer a bypass or special recovery path.


### DEC-AUTH-015 – Platform authority assignments and Demi-Admin delegation

- Approval status: Approved in project discussion on 2026-10-21.
- Implementation status: Not implemented or verified.
- Represent platform authorities through a dedicated extensible authority catalog and user-to-authority assignment mechanism, separate from `is_it_admin`, office memberships, and Block grants. Physical schema names are to be chosen consistently with the repository during implementation.
- `Administrator` is the first platform authority. Only an Administrator may grant or revoke the `Demi-Admin` authority. IT Administrator status does not imply either platform authority.
- Demi-Admin scope is assigned explicitly by an Administrator. Application access is expressed through existing Blocks/actions. Sensitive IAM operations are expressed through a fixed administrative capability catalog.
- Each administrative capability declares whether it is delegable. Only explicitly delegable capabilities may be assigned to Demi-Admins. Demi-Admins cannot grant, revoke, or sub-delegate platform authorities or administrative capabilities.
- Administrative capability assignments include explicit scope. Do not assume platform-wide scope unless the capability is explicitly designed for it and the Administrator grants that scope.
- Authority and capability assignments preserve history. Revocation closes an assignment record; a later assignment creates a new record. Record actor, timestamps, and reason where supported. Audit changes with append-only records.
- Revocation takes effect on the next authorization check. Checks must use current authorization state rather than stale cached permissions.
- The first Administrator is established through a controlled, one-time server-side bootstrap. The bootstrap may create the initial IAM account and Administrator assignment together. Account, authority assignment, and activation record must be created atomically in the database and the command must be safely retryable.
- Bootstrap issues a cryptographically secure, single-use activation secret that expires after 24 hours. Store only its hash in the database. Deliver plaintext through a restricted temporary file inside the IAM container, retrievable by a trusted operator through an administrative Docker command. Never log the secret or expose it through a public endpoint.
- If the activation secret expires unused, a trusted recovery command may issue a replacement only while initial activation remains pending. It must invalidate the previous secret and safely replace the file without duplicating the account or authority assignment. Filesystem and database failures require explicit recovery handling; do not claim a successful reissue unless the new secret file is ready and the old secret is unusable.
- After successful activation, IAM attempts automatic removal of the plaintext secret file. If cleanup fails, record a safe operational error; the consumed secret must remain unusable. Bootstrap and reissuance must not work after activation succeeds.
- This decision does not resolve what happens when an Administrator account is disabled. It does not authorize additional platform authorities, capability delegation, or Demi-Admin sub-delegation.

## 3. Open decisions requiring explicit approval

### OPEN-AUTH-001 – Is an office an active runtime authorization context?

*(legacy ID: CONFLICT-001)*

Sources conflict:
- Model A, active office: the primary office sets the default workspace; users switch among authorized offices through a Workspace selector; the selected active office persists and affects authorization and audit context.
- Model B, additive: access is combined across all active memberships; the primary office only sets the default dashboard/workspace; there is no active authorization context. The current implementation returns combined office access.
Do not choose silently. Before implementing office switching or changing authorization/audit behavior, record a decision covering:
1. Whether an active office context exists.
2. How it is selected and persisted.
3. Whether requests are authorized only within that context or across all memberships.
4. How the office context is recorded in audit_logs.
5. Edge contract: if an active office exists, verify / /_auth must also return it and the edge must pass it to services (for example a CONCORD_OFFICE_ID FastCGI parameter, never a client-settable header). Until decided, the edge passes only CONCORD_USER_ID.

### OPEN-AUTH-002 – Which high-risk Blocks need extra restrictions?

*(legacy ID: OPEN-001)*

No approved list of high-risk Blocks or separate policy exists. Do not invent a list, and do not assume all high-risk actions share one rule. Record concrete cases and obtain approval here before adding exceptions.

### OPEN-AUTH-003 – Are there Block dependency rules beyond explicit grants?

IAM must not auto-grant View (DEC-AUTH-002). If applications need Block dependencies, the project still needs one explicit, consistent way to declare and enforce them. This item is not permission to infer dependencies from names or conventions.

Suggested direction, not a decision: a grant UI could warn "this Block usually needs X" using application-declared prerequisites, without auto-granting.

### OPEN-AUTH-004 – Office Head restrictions beyond office_head_blocks

*(legacy ID: OPEN-002, restored)*

office_head_blocks lets IT configure which Blocks a Head can use and whether each can be delegated. Any additional role-wide or high-risk restrictions need an explicit decision.

### OPEN-AUTH-005 – Disabled-Administrator behavior

- Status: Partially resolved in project discussion on 2026-10-21.
- The distinct `Administrator` authority representation, Demi-Admin delegation model, and initial bootstrap lifecycle are approved under DEC-AUTH-014 and DEC-AUTH-015.
- Behavior when an Administrator account is disabled remains unresolved. Do not infer a bypass or a special recovery flow.
- Continue to apply DEC-AUTH-010 to the existing IT-Administrator-only account-disable operation unless a later approved decision changes it.

## 4. Superseded or conflicting proposals

| Proposal | Current treatment |
|---|---|
| Auto-grant View whenever another Block is granted | Conflicts with DEC-AUTH-002. Do not implement unless re-approved. |
| Office Heads get every Block in their office's applications | Conflicts with DEC-AUTH-003. Do not restore without a decision. |
| IT-admin grants are bound by can_delegate (earlier AUTH-004 / AUTH-007) | Superseded by DEC-AUTH-004 on 2026-10-18. |
| Primary office is dashboard-only; active office has no authorization meaning | Unresolved under OPEN-AUTH-001. Not settled. |
| Active office is an authorization/audit context | Unresolved under OPEN-AUTH-001. Not settled. |
| Administrator has universal Block/action access distinct from IT Administrator | Approved in DEC-AUTH-014; implementation and account/role representation remain pending under OPEN-AUTH-005. |

## 5. Implementation notes and alignment checklist

Notes describe the reviewed snapshot. Verify against the repository before relying on them.
- services/iam/app/Services/AccessService.php implements configured Office Head Blocks via office_head_blocks. Its comments must say "configured Blocks", not "all Blocks".
- services/iam/app/Http/Controllers/GrantController.php implements office-scoped grant/revoke validation, can_delegate checks, audit logging, and cache invalidation.
- Feature tests cover Office Head configuration, office boundaries, grant/revoke, and cache invalidation.

Checklist (checked = verified in the reviewed snapshot; unchecked = pending or only partly verified):
- [x] docs/iam.md links to this record and identifies it as the authorization decision authority.
- [x] GrantController applies can_delegate to Office Heads only; IT admins are exempt (DEC-AUTH-004).
- [ ] Test: IT admin grants a Block with can_delegate = false → succeeds and is audit logged (verified). Office Head does the same → rejected (verified). Audit logging of the Office Head's denied attempt has not been separately verified.
- [x] Test: IT admin is still rejected for a staff membership that is ended or in another office.
- [x] Test: disabling an account invalidates its sessions and /api/iam/me rejects the disabled user's session (DEC-AUTH-009/010).
- [ ] Test: after disabling an account, IAM verify itself returns 401. Only /api/iam/me is currently evidenced, and the edge's /_auth depends on verify (DEC-AUTH-009).
- [x] AccessService.php comments describe configured Office Head Blocks.
- [x] Code comments, tests, and notes that cite legacy IDs (see section 7) are updated. A legacy-reference search returned no matches in services/iam/app or services/iam/tests; references in docs/iam.md use the adopted decision IDs.
- [ ] Office-context behavior is unchanged until OPEN-AUTH-001 is resolved. No explicit office-context change was identified in the reviewed route diff, but the full change set has not been compared.
- [ ] Tests are run against a verified test database before claiming validation. PHPUnit targets concord_iam_test and the suite passed, but isolation from development data has not been independently established.

**Latest verification run:** 41 tests passed, 236 assertions, using the PHPUnit testing configuration.

**Additional test coverage added:**
- `test_it_admin_cannot_grant_to_an_ended_membership`
- `test_it_admin_cannot_grant_a_block_from_another_office`
- `test_it_admin_can_grant_a_block_configured_as_not_delegable` now asserts the corresponding `access.grant_created` audit record.

## 6. Change control

When a decision changes:
1. Update this document first, mark the previous rule as superseded, and add a dated change-history entry.
2. Update docs/iam.md to summarize the same rule.
3. Review affected implementation, tests, API/UI behavior, and [[edge]].
4. Implement only once the decision is clear.
5. Record code and test references after verification.
Do not erase historical context because a proposal was rejected. Label it so it cannot be mistaken for the current rule. When replacing this file in a project or chat context, replace older copies rather than adding alongside them.

## 7. Legacy ID map

| Legacy ID | Current ID |
|---|---|
| AUTH-001 | DEC-AUTH-001 |
| AUTH-002 | DEC-AUTH-002 |
| AUTH-003 | DEC-AUTH-003 |
| AUTH-004 | DEC-AUTH-004 (IT-admin part reversed) |
| AUTH-005 | DEC-AUTH-005 |
| AUTH-006 | DEC-AUTH-006 |
| AUTH-007 | DEC-AUTH-007 (IT-admin can_delegate part reversed) |
| (history/records rules in [[IAM - Access Model]]) | DEC-AUTH-008, DEC-AUTH-009 |
| CONFLICT-001 | OPEN-AUTH-001 |
| OPEN-001 | OPEN-AUTH-002 |
| (new) | OPEN-AUTH-003 |
| OPEN-002 | OPEN-AUTH-004 |
| (new) | OPEN-AUTH-005 |

## 8. Change history

- 2026-10-21: Added DEC-AUTH-015 for the dedicated platform-authority assignment model, Administrator-controlled Demi-Admin delegation, fixed administrative capabilities, explicit scope, immediate revocation, and the controlled initial Administrator bootstrap/activation/recovery lifecycle. OPEN-AUTH-005 now covers only disabled-Administrator behavior. The HRIS internal endpoint contract remains a proposal until implemented and verified.
- 2026-10-19: Added DEC-AUTH-011 to defer Spatie Permission and retain Concord IAM as the authoritative platform-access model.
- 2026-10-18: Added DEC-AUTH-010 approving IT-admin-only account disablement (implementation remains pending) and OPEN-AUTH-005 to defer the System Administrator vs IT Administrator separation discussion until after the immediate fixes. Removed obsolete scaffold/iam/ references because the scaffold has been deleted.
- 2026-10-18 (reconciliation): Merged the two records. Adopted the DEC-AUTH/OPEN-AUTH numbering with a legacy map. Reversed the rule that IT-admin grants are bound by can_delegate (DEC-AUTH-004). Restored: change history, implementation checklist, per-rule Implementation/approval notes, OPEN-AUTH-004 (was OPEN-002), and session invalidation as a requirement (DEC-AUTH-009). Added edge-contract impact to OPEN-AUTH-001 and test requirements for the IT-admin exemption. No open decision was resolved.
- 2026-10-18 (earlier): Consolidated configured-Blocks model, explicit View rule, delegation distinction, and the unresolved office-context conflict.
- 2026-10-19: Reconciled IAM implementation evidence and checklist. Full IAM suite passed (41 tests, 236 assertions). Added IT Administrator tests for ended-membership and cross-office grant restrictions, and an audit assertion for successful grants when `can_delegate = false`. Verified the Office Head delegation exemption in GrantController, configured-Block comments in AccessService, current documentation references, and the Edge FastCGI cookie-forwarding configuration. OPEN-AUTH-001 through OPEN-AUTH-005 remain unresolved. Test database isolation and unchanged office-context behavior remain pending independent verification.

## Related notes

- [[IAM - Access Model]]: the access model summary this record governs.
- [[edge]]: how requests are authenticated before reaching services.
- [[iam-internal-authorization-contract-proposal]] and [[HRIS-IAM-Authorization-Handoff]]: DEC-AUTH-012 to DEC-AUTH-014 contract detail.
- [[concord-frontend-working-document]]: frontend consumer of these rules.
- [[hris-decisions]]: HRIS-side decisions.
- [[Concord Index]]
