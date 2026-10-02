# IAM - access model

IAM owns identity and access. Business services own business data (HR owns employees).

## The lego model
App -> Group -> Block. Only **blocks** are grantable; groups are containers. Each group has a
`view` block, and edit blocks depend on it. Key format: `registrar.student-profile.chinese-name`.

Apps declare their catalog (manifest); a new block starts with nobody holding it.

## Who decides what
| Authority | Decides | Owner |
|---|---|---|
| Placement | who belongs to an office; who heads it | HR |
| Delegation | which blocks each staff member gets | the office head (own office only) |
| Rules | apps, groups, blocks, baseline roles | IT |

IT sits above heads and can override (always logged).

## Effective access
IT admin = everything. Otherwise, per office: head = every block of the office's apps;
staff = blocks with an active grant on their membership. Users in several offices see one
combined dashboard, apps grouped by office; each head sees only their own office's staff.

## Records are never deleted
- Accounts: `status` active -> disabled (hidden from lists except IT's view). Disabling kills sessions at once.
- Grants: `granted_by/at`, `revoked_by/at`, `revoke_reason`. Re-granting makes a new row.
- Memberships close with `ended_at`; ending one closes only that office's grants.
- `audit_logs` records the actor and the office they acted under.

## Tables
users, auth_sessions, apps, app_groups, blocks, offices, office_apps, memberships, grants, audit_logs.
