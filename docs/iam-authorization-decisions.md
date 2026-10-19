Concord IAM Authorization Decisions
Status: Working authority for IAM authorization decisions
Last reconciled: 2026-10-18 (merges the earlier "AUTH/CONFLICT" record with the later "DEC-AUTH/OPEN-AUTH" record)
Scope: Authorization semantics: who may hold, grant and revoke access, and how effective access is computed. This document does not replace the repository as the source of truth for implementation.
Purpose: Keep approved rules, current implementation behavior, superseded proposals, and open decisions distinguishable.
1. How to use this record
1. Adopted rules are the current implementation direction.
2. Implementation lines say what the code does or is expected to do. Implementation alone does not prove architectural approval, and every Implementation line is unverified until it is checked against the repository and a test run.
3. Open decisions must not be resolved by guessing. Ask for an explicit decision, record it here, then implement.
4. Historical notes and handoffs give context. This document records the reconciled outcome.
5. If another note (including docs/iam.md, code comments, or chat summaries) conflicts with this record, follow this record and do not silently pick a side on anything marked Open.
2. Adopted authorization rules
DEC-AUTH-001: Blocks are the smallest grantable unit
- The hierarchy is Application → Group → Block.
- Groups are organizational containers. Access is granted at Block level.
- Applications declare their catalog of Groups and Blocks.
- A newly declared Block gives nobody access unless an explicitly approved policy says otherwise.
DEC-AUTH-002: View access is explicit
- IAM must not automatically grant a view Block because another Block is granted.
- Do not assume every Group has a View Block or that every edit/action Block depends on View. A Block may be self-contained, or have a dependency defined by its application.
- Any prerequisite behavior must be explicitly represented and approved, never inferred from a Block's name. See OPEN-AUTH-003.
- Historical conflict: a Daemon handoff proposed auto-granting View when another action required it. That is a rejected historical proposal, not the current rule.
DEC-AUTH-003: Office Head access is configured per office
- office_head_blocks defines which Blocks an Office Head may use for their office.
- Access is limited to applications assigned to that office (office_apps).
- An Office Head does not receive every Block in every office application by default.
- Approval note: an earlier revision labeled this "current implementation model; approval history must remain visible." The latest revision lists it as adopted. Confirm explicitly if approval history matters for audit.
- Historical conflict: the older "Office Heads receive all Blocks by default" model must not return without an explicit decision.
DEC-AUTH-004: can_delegate controls delegation, not personal access
- can_delegate = true lets an Office Head delegate the configured Block to eligible staff in their office.
- can_delegate = false prevents Office Head delegation of that Block. It does not remove the Office Head's own configured access.
- IT administrators are exempt. They may grant a Block regardless of can_delegate, because they administer the access policy. They remain subject to validation, office/application rules, and audit logging.
- Supersedes: the earlier record (AUTH-004 / AUTH-007) said the GrantController also enforced can_delegate for IT-admin grants and that this must be preserved. That is reversed here.
- Implementation: if the GrantController rejects an IT-admin grant solely because can_delegate = false, that is a defect to fix, not a rule to preserve.
DEC-AUTH-005: Staff access requires active grants and active membership
- Staff receive Blocks from active grants attached to active office memberships.
- A grant must be inside the target membership's office boundary.
- The target membership must be active and belong to the office in which the grant is managed.
- Access is never inferred from job title alone.
DEC-AUTH-006: Office boundaries are mandatory
- Office Heads manage access and staff only within their own office.
- An application must be assigned to the office for its Blocks to be managed there.
- Access in one office must not leak into another.
DEC-AUTH-007: IT administrator access is global
- An authenticated IT administrator has global application access.
- Administrative grant and revoke actions remain subject to validation, office/application rules, and audit logging. They are not subject to can_delegate (DEC-AUTH-004).
DEC-AUTH-008: Preserve authorization history
- Grant history is never deleted on revocation. Revocation records actor, time, and reason where the schema supports it.
- Re-granting creates a new grant row; a revoked row is never reactivated.
- Memberships are ended with ended_at, not erased. Ending a membership must stop its grants from providing active access.
- Every access-changing action is audit logged.
- Grant, revoke, and membership changes must invalidate affected effective-access caches.
DEC-AUTH-009: Disabled accounts lose access immediately
- Accounts are disabled, not deleted.
- Disabling an account must invalidate its sessions, and session verification must reject disabled users.
- The edge relies on this: /_auth calls IAM verify, and a 401 there is what makes disabling take effect at once (see edge.md).
- Provenance: carried over from the original iam.md ("must invalidate") and the edge design. The later iam.md softened it to "under the current implementation"; it is restored as a requirement here.
DEC-AUTH-010: Account disablement is restricted to IT Administrators
- Approval status: Approved
- Implementation status: Not implemented
- Only an authenticated IT Administrator may disable an account.
- The disable operation must set the account status to disabled, populate disabled_at, revoke all active sessions with reason account_disabled, invalidate both effective-access cache keys, and write an audit entry.
- The operation must preserve the account record and must not leave valid sessions usable after the disable action succeeds.
- This decision establishes the immediate authorization rule only. The distinction between a System Administrator and an IT Administrator remains unresolved under OPEN-AUTH-005.
3. Open decisions requiring explicit approval
OPEN-AUTH-001: Is an office an active runtime authorization context?
(legacy ID: CONFLICT-001)
Sources conflict:
- Model A, active office: the primary office sets the default workspace; users switch among authorized offices through a Workspace selector; the selected active office persists and affects authorization and audit context.
- Model B, additive: access is combined across all active memberships; the primary office only sets the default dashboard/workspace; there is no active authorization context. The current implementation returns combined office access.
Do not choose silently. Before implementing office switching or changing authorization/audit behavior, record a decision covering:
1. Whether an active office context exists.
2. How it is selected and persisted.
3. Whether requests are authorized only within that context or across all memberships.
4. How the office context is recorded in audit_logs.
5. Edge contract: if an active office exists, verify / /_auth must also return it and the edge must pass it to services (for example a CONCORD_OFFICE_ID FastCGI parameter, never a client-settable header). Until decided, the edge passes only CONCORD_USER_ID.
OPEN-AUTH-002: Which high-risk Blocks need extra restrictions?
(legacy ID: OPEN-001)
No approved list of high-risk Blocks or separate policy exists. Do not invent a list, and do not assume all high-risk actions share one rule. Record concrete cases and obtain approval here before adding exceptions.
OPEN-AUTH-003: Are there Block dependency rules beyond explicit grants?
IAM must not auto-grant View (DEC-AUTH-002). If applications need Block dependencies, the project still needs one explicit, consistent way to declare and enforce them. This item is not permission to infer dependencies from names or conventions.
Suggested direction, not a decision: a grant UI could warn "this Block usually needs X" using application-declared prerequisites, without auto-granting.
OPEN-AUTH-004: Office Head restrictions beyond office_head_blocks
(legacy ID: OPEN-002, restored)
office_head_blocks lets IT configure which Blocks a Head can use and whether each can be delegated. Any additional role-wide or high-risk restrictions need an explicit decision.
OPEN-AUTH-005: Should System Administrator and IT Administrator be separate authority levels?
- Approval status: Open
- Implementation status: Not implemented
- The project owner is considering a distinct System Administrator account/authority separate from the existing IT Administrator role.
- The intended concept is a highest-trust creator/owner authority whose powers exceed those of ordinary administrators, while delegating selected operational responsibilities to IT Administrators or other trusted staff.
- Current operational context: the project owner is presently the sole person responsible for the systems; two specialized coworkers sometimes assist, and an assistant may be hired in the future.
- The design discussion must decide whether this is a separate role, a separate account, or both; what powers are exclusive to it; what IT Administrators may manage; how delegation and recovery work; and how the exceptional authority is authenticated, audited, and protected.
- Do not implement a super-admin role, special account, bypass, or additional privileges until this decision is explicitly resolved. Continue using DEC-AUTH-010 for the immediate IT-admin-only account-disable rule until the broader authority model is approved.
4. Superseded or conflicting proposals
Proposal	Current treatment
Auto-grant View whenever another Block is granted	Conflicts with DEC-AUTH-002. Do not implement unless re-approved.
Office Heads get every Block in their office's applications	Conflicts with DEC-AUTH-003. Do not restore without a decision.
IT-admin grants are bound by can_delegate (earlier AUTH-004 / AUTH-007)	Superseded by DEC-AUTH-004 on 2026-10-18.
Primary office is dashboard-only; active office has no authorization meaning	Unresolved under OPEN-AUTH-001. Not settled.
Active office is an authorization/audit context	Unresolved under OPEN-AUTH-001. Not settled.
System Administrator is a separate authority from IT Administrator	Unresolved under OPEN-AUTH-005. Do not implement yet.


5. Implementation notes and alignment checklist
Notes describe the reviewed snapshot. Verify against the repository before relying on them.
- services/iam/app/Services/AccessService.php implements configured Office Head Blocks via office_head_blocks. Its comments must say "configured Blocks", not "all Blocks".
- services/iam/app/Http/Controllers/GrantController.php implements office-scoped grant/revoke validation, can_delegate checks, audit logging, and cache invalidation.
- Feature tests cover Office Head configuration, office boundaries, grant/revoke, and cache invalidation.
Checklist:
- [ ] docs/iam.md matches this record and links here.
- [ ] GrantController applies can_delegate to Office Heads only; IT admins are exempt (DEC-AUTH-004).
- [ ] Test: IT admin grants a Block with can_delegate = false → succeeds. Office Head does the same → rejected. Both cases audit logged.
- [ ] Test: IT admin is still rejected for a staff membership that is ended or in another office.
- [ ] Test: disabling an account invalidates its sessions and verify returns 401 (DEC-AUTH-009).
- [ ] AccessService.php comments describe configured Office Head Blocks.
- [ ] Code comments, tests, and notes that cite legacy IDs (see section 7) are updated.
- [ ] Office-context behavior is unchanged until OPEN-AUTH-001 is resolved.
- [ ] Tests are run against a verified test database before claiming validation.
6. Change control
When a decision changes:
1. Update this document first, mark the previous rule as superseded, and add a dated change-history entry.
2. Update docs/iam.md to summarize the same rule.
3. Review affected implementation, tests, API/UI behavior, and edge.md.
4. Implement only once the decision is clear.
5. Record code and test references after verification.
Do not erase historical context because a proposal was rejected. Label it so it cannot be mistaken for the current rule. When replacing this file in a project or chat context, replace older copies rather than adding alongside them.
7. Legacy ID map
Legacy ID	Current ID
AUTH-001	DEC-AUTH-001
AUTH-002	DEC-AUTH-002
AUTH-003	DEC-AUTH-003
AUTH-004	DEC-AUTH-004 (IT-admin part reversed)
AUTH-005	DEC-AUTH-005
AUTH-006	DEC-AUTH-006
AUTH-007	DEC-AUTH-007 (IT-admin can_delegate part reversed)
(history/records rules in iam.md)	DEC-AUTH-008, DEC-AUTH-009
CONFLICT-001	OPEN-AUTH-001
OPEN-001	OPEN-AUTH-002
(new)	OPEN-AUTH-003
OPEN-002	OPEN-AUTH-004
(new)	OPEN-AUTH-005


8. Change history
- 2026-10-18: Added DEC-AUTH-010 approving IT-admin-only account disablement (implementation remains pending) and OPEN-AUTH-005 to defer the System Administrator vs IT Administrator separation discussion until after the immediate fixes. Removed obsolete scaffold/iam/ references because the scaffold has been deleted.
- 2026-10-18 (reconciliation): Merged the two records. Adopted the DEC-AUTH/OPEN-AUTH numbering with a legacy map. Reversed the rule that IT-admin grants are bound by can_delegate (DEC-AUTH-004). Restored: change history, implementation checklist, per-rule Implementation/approval notes, OPEN-AUTH-004 (was OPEN-002), and session invalidation as a requirement (DEC-AUTH-009). Added edge-contract impact to OPEN-AUTH-001 and test requirements for the IT-admin exemption. No open decision was resolved.
- 2026-10-18 (earlier): Consolidated configured-Blocks model, explicit View rule, delegation distinction, and the unresolved office-context conflict.