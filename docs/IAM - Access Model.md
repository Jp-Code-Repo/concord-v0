---
title: IAM - Access Model
date: 2026-10-21
tags:
  - concord/iam
  - concord/architecture
aliases:
  - IAM Access Model
  - IAM Documentation
  - iam
status: working-reference
---

# IAM - Access Model

IAM owns identity, authentication, sessions, and effective access. Business services own their business data (for example, HR owns employee records).

> **Authorization decision authority:** [[iam-authorization-decisions]]. If this note, a historical note, or a code comment conflicts with it, follow that record. Do not silently resolve items marked Open; get an explicit decision first.

## The catalog model

Application → Group → Block.

- Applications declare their catalog of Groups and Blocks.
- Groups are organizational containers. **Blocks are the smallest grantable units.**
- A newly declared Block starts with nobody holding it unless access is explicitly configured or granted under an approved rule.
- **View access is explicit.** IAM does not grant a View Block because another Block is granted, and it does not assume every Group has a View Block or that every action Block depends on one. Any dependency must be explicitly declared and approved ([[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit|DEC-AUTH-002]], [[iam-authorization-decisions#OPEN-AUTH-003 – Are there Block dependency rules beyond explicit grants?|OPEN-AUTH-003]]).

## Institutional user classification

Concord models people in the institution first as **employees**. An employee has an employment category, and may also hold an office leadership assignment:

- **Employee** is the common institutional identity for both staff and teachers.
- **Staff** is one employee category.
- **Teacher** is another employee category.
- **Head of Office** is an office assignment that may be held by either a Staff member or a Teacher. It is not a mutually exclusive employee category.

```text
Employee
├── Staff
│   └── may be Head of Office
└── Teacher
    └── may be Head of Office
```

This classification describes the institution's people and organizational roles. It must not be confused with IAM authorization profiles: being Staff, Teacher, or Head of Office does not by itself grant every application or Block. Effective access remains governed by the approved access model. A global IT administrator is an IAM administrative access profile, not a replacement for the person's employee category.

This section documents the owner's stated conceptual model. It does not approve database schema changes or define how HRIS and IAM store or synchronize these attributes. Employee data is owned by [[hris-decisions]].

## Who decides what

| Authority | Responsibility |
|---|---|
| HR | Office membership and office-head assignment. |
| Office Head | Delegates eligible Blocks to staff within their own office, subject to `can_delegate`. |
| IT | Manages applications, Groups, Blocks, platform-wide rules, and global IT-administrator access. |

IT administrators have global application access. Their grants are **not** limited by `can_delegate`; they remain subject to validation, office and application rules, and audit logging ([[iam-authorization-decisions#DEC-AUTH-004 – can_delegate controls delegation, not personal access|DEC-AUTH-004]]). All access changes are audited.

## Effective access

- **IT administrators:** Global access across applications, not tied to one office. May grant a Block regardless of `can_delegate`.
- **Office Heads:** Access only to Blocks configured for their office in `office_head_blocks`, limited to applications assigned to that office. They do not automatically receive every Block.
- **Delegation:** `office_head_blocks.can_delegate` controls whether an *Office Head* may delegate a configured Block to staff in their office. `false` prevents that delegation; it does not remove the Head's own access. It does not restrict IT administrators.
- **Staff:** Access comes from active grants on active office memberships.
- **Office boundaries:** Office Heads manage grants and staff only within their own office. Access in one office must not leak into another.
- **Administrator:** a distinct authority with universal Block/action access (see below).

## Multiple offices and active office context

**Unresolved ([[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]]).** The project history conflicts on whether access is combined across a user's offices or whether a selected active office is a runtime context that affects authorization and audit. The current implementation returns combined office access, but that does not make it the approved model. Do not implement office switching, authorization scoping, or audit-context changes from this section. Record the decision in [[iam-authorization-decisions]] first, including its effect on `audit_logs` and on what the [[edge]] passes to services.

## Records and revocation

- **Accounts** are disabled, not deleted. Disabling an account must invalidate its sessions, and session verification must reject disabled users ([[iam-authorization-decisions#DEC-AUTH-009 – Disabled accounts lose access immediately|DEC-AUTH-009]]).
- **Grants** keep grant and revocation history (`granted_by/at`, `revoked_by/at`, and `revoke_reason` where supported). Re-granting creates a new row; a revoked row is never restored.
- **Memberships** are ended with `ended_at`. Ending one must stop its grants from providing active access.
- **Audit logs** record the actor and relevant office context for access changes. What "office context" means is unresolved ([[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]]).
- **Caches:** effective-access caches must be invalidated after any grant, revoke, or membership change ([[iam-authorization-decisions#DEC-AUTH-008 – Preserve authorization history|DEC-AUTH-008]]).

## Main tables

`users`, `auth_sessions`, `apps`, `app_groups`, `blocks`, `offices`, `office_apps`, `memberships`, `grants`, `office_head_blocks`, and `audit_logs`.

## Internal Block authorization for Concord services

**Status: approved behavior, proposed API contract, not implemented.** See [[iam-authorization-decisions#DEC-AUTH-012 – Concord services must check current IAM authorization for protected operations|DEC-AUTH-012]], [[iam-authorization-decisions#DEC-AUTH-013 – Runtime authorization checks are audited with append-only records|DEC-AUTH-013]] and [[iam-authorization-decisions#DEC-AUTH-014 – Administrator authority has universal Block/action access|DEC-AUTH-014]] in [[iam-authorization-decisions]], and [[iam-internal-authorization-contract-proposal]]. The consumer-side view is [[HRIS-IAM-Authorization-Handoff]].

- Concord business services must ask IAM for a current decision before each protected backend operation.
- IAM owns the decision; the calling service enforces it. A prior allow decision must not survive a revocation as a cached authorization result.
- If IAM is unavailable or the decision cannot be verified, the protected operation must fail closed.
- The intended endpoint is internal-only: it must not be exposed through the public Concord [[edge]]. Network isolation and per-service credentials are both required.
- A service credential authenticates the calling service, not the user whose access is being checked.
- Both allow and deny decisions must be audit logged. Runtime authorization records are append-only; retention is undecided.
- The proposed route and payload are not stable API contracts. Do not integrate against them until IAM finalizes and implements the contract.

### Administrator authority distinction

The approved conceptual `Administrator` authority has universal Block/action access while still requiring authentication. It is distinct from the existing `IT Administrator` model. The final role/account representation and provisioning remain open ([[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries|OPEN-AUTH-005]]); do not implement this behavior based only on the current `is_it_admin` flag.

## Application catalog administration API

These endpoints manage the Application → Group → Block catalog. They require an authenticated session and IT Administrator privileges. Implementation details are in [[IMPLEMENTATION_NOTES]].

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/iam/admin/apps` | List the application catalog, including its Groups and Blocks. |
| POST | `/api/iam/admin/apps` | Create an application catalog entry. |
| PATCH | `/api/iam/admin/apps/{appId}` | Update an existing application catalog entry. |

### Authorization and behavior

- Requests must have a valid authenticated session.
- Only active IT Administrators may use these endpoints.
- Catalog changes are audited.
- These endpoints manage catalog definitions. They do not automatically grant Blocks to users.
- Existing grants, office boundaries, explicit View access, and unresolved authorization decisions remain governed by this note and [[iam-authorization-decisions]].

## Deferred IAM responsibility: `/api/iam/me` frontend contract

**Status: Deferred. Owner: IAM. Do not block unrelated frontend work on this item.**

The frontend handoff identified contract questions that IAM must resolve before office-grouped navigation is treated as fully integrated:

- Confirm the authoritative response schema for `access.offices[].apps`, `access.offices[].blocks`, and the office-head indicator (`is_head`).
- Confirm whether the returned Block list represents effective access and whether its entries are unique, so the frontend can safely derive Block counts.
- Confirm the stable distinction between a global IT administrator and an authenticated user who has not been assigned access, including the intended meanings of `access.all_access`, `access.apps`, and `access.offices`.
- Provide an authoritative example response and document these fields as the supported API contract.

These are IAM-owned contract/documentation tasks, not a request for the frontend to repeat its investigation or infer missing behavior. The code snapshot and frontend handoff are existing evidence, but they do not constitute formal contract approval. Revisit when IAM work resumes. Frontend context: [[concord-frontend-working-document]].

## Cross-service responsibility note

- HRIS is expected to determine an employee's home/primary office; IAM must store the resulting assignment. The representation and service-to-service workflow remain subject to explicit decisions in [[iam-authorization-decisions]].
- HRIS asks IAM to create accounts and memberships through an explicit API and never writes to the IAM database ([[hris-decisions#DEC-HRIS-001 – HRIS owns departments and initiates IAM account provisioning|DEC-HRIS-001]], [[hris-decisions#DEC-HRIS-009 – HRIS Requests IAM Account Creation|DEC-HRIS-009]] in [[hris-decisions]]). That API is unconfirmed.

## Related notes

- [[iam-authorization-decisions]]: authoritative record of approved authorization decisions, unresolved questions, and change control.
- [[edge]]: edge routing, trust boundaries, and internal-only IAM endpoints.
- [[concord-frontend-working-document]]: frontend integration context and the `/api/iam/me` contract handoff.
- [[IMPLEMENTATION_NOTES]]: implementation progress and engineering notes.
- [[iam-internal-authorization-contract-proposal]] and [[HRIS-IAM-Authorization-Handoff]]: internal Block authorization.
- [[hris-decisions]] and [[responsible-for-what]].
- [[Concord Index]]
