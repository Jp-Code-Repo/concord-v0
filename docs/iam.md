# IAM - Access Model

IAM owns identity, authentication, sessions, and effective access. Business services own their business data (for example, HR owns employee records).

> **Authorization decision authority:** [`iam-authorization-decisions.md`](iam-authorization-decisions.md). If this note, a historical note, or a code comment conflicts with it, follow that record. Do not silently resolve items marked Open; get an explicit decision first.

## The catalog model

Application → Group → Block.

- Applications declare their catalog of Groups and Blocks.
- Groups are organizational containers. **Blocks are the smallest grantable units.**
- A newly declared Block starts with nobody holding it unless access is explicitly configured or granted under an approved rule.
- **View access is explicit.** IAM does not grant a View Block because another Block is granted, and it does not assume every Group has a View Block or that every action Block depends on one. Any dependency must be explicitly declared and approved (DEC-AUTH-002, OPEN-AUTH-003).

## Who decides what

| Authority | Responsibility |
|---|---|
| HR | Office membership and office-head assignment. |
| Office Head | Delegates eligible Blocks to staff within their own office, subject to `can_delegate`. |
| IT | Manages applications, Groups, Blocks, platform-wide rules, and global IT-administrator access. |

IT administrators have global application access. Their grants are **not** limited by `can_delegate`; they remain subject to validation, office and application rules, and audit logging. All access changes are audited.

## Effective access

- **IT administrators:** Global access across applications, not tied to one office. May grant a Block regardless of `can_delegate`.
- **Office Heads:** Access only to Blocks configured for their office in `office_head_blocks`, limited to applications assigned to that office. They do not automatically receive every Block.
- **Delegation:** `office_head_blocks.can_delegate` controls whether an *Office Head* may delegate a configured Block to staff in their office. `false` prevents that delegation; it does not remove the Head's own access. It does not restrict IT administrators.
- **Staff:** Access comes from active grants on active office memberships.
- **Office boundaries:** Office Heads manage grants and staff only within their own office. Access in one office must not leak into another.

## Multiple offices and active office context

**Unresolved (OPEN-AUTH-001).** The project history conflicts on whether access is combined across a user's offices or whether a selected active office is a runtime context that affects authorization and audit. The current implementation returns combined office access, but that does not make it the approved model. Do not implement office switching, authorization scoping, or audit-context changes from this section. Record the decision in the decision record first, including its effect on `audit_logs` and on what the edge passes to services.

## Records and revocation

- **Accounts** are disabled, not deleted. Disabling an account must invalidate its sessions, and session verification must reject disabled users (DEC-AUTH-009).
- **Grants** keep grant and revocation history (who, when, reason where supported). Re-granting creates a new row; a revoked row is never restored.
- **Memberships** are ended with `ended_at`. Ending one must stop its grants from providing active access.
- **Audit logs** record the actor and relevant office context for access changes. What "office context" means is unresolved (OPEN-AUTH-001).
- **Caches:** effective-access caches must be invalidated after any grant, revoke, or membership change (DEC-AUTH-008).

## Main tables

`users`, `auth_sessions`, `apps`, `app_groups`, `blocks`, `offices`, `office_apps`, `memberships`, `grants`, `office_head_blocks`, and `audit_logs`.

## Related

- Adopted rules, open decisions, superseded proposals, and change control: [`iam-authorization-decisions.md`](iam-authorization-decisions.md).
- How requests are authenticated before reaching services: [`edge.md`](edge.md).

## Application catalog administration API

These endpoints manage the Application → Group → Block catalog. They require an authenticated session and IT Administrator privileges.

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
- Existing grants, office boundaries, explicit View access, and unresolved authorization decisions remain governed by this document and `iam-authorization-decisions.md`.