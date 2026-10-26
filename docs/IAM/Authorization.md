# Concord Authorization: Decided Direction

Status: Decided (2026-10-26). Details marked **Open** are settled one step at a time.
Applies to: Concord IAM and every Concord app (HRIS, Ticketing, Clinic, Inventory, Registrar, and the rest).

---

## 1. The question this answers

"Who can access what?" has two parts, and each part has one owner.

| Question | Owner | Mechanism |
|---|---|---|
| What is this person allowed to do? (capabilities, per office, who granted them, when) | **IAM** | Grants table, capability catalog |
| May they do it to *this* record? (ownership, office scope, record status) | **The service** | Laravel Gates and Policies |

---

## 2. Decision summary

1. IAM is the single source of truth for grants.
2. Each service enforces locally, using Gates for capability checks and Policies for record-level rules.
3. Spatie is not used as the source of truth.
4. Effective capabilities are delivered to services as a **local projection updated by events**, not as token claims and not by a call to IAM on every request.
5. The backend always enforces. The frontend only decides what to show.

---

## 3. Context and constraints

- Microservices. Each service has its own database and no cross-database foreign keys.
- IAM owns identity and access. Business services own business data.
- Stack: Laravel 12, MySQL, Redis (also the event broker, behind an interface), React + Vite, TanStack Router, shadcn/ui.
- Scale target: 200 concurrent users, about 2000 accounts. Performance is not the deciding factor at this size. Coupling and revocation speed are.
- Access is **lego-style**: apps contain modules, and modules are containers. The smallest blocks (capabilities) are what get granted, for example Registrar > Student Profile > "Chinese name update".
- Authority layers: IT sits above office heads. The office head chooses which of their office's blocks each staff member gets.
- A user can belong to several offices. They see one combined dashboard, with apps grouped by office.
- Nobody can grant a power they don't hold.
- Accounts are never deleted, only disabled, and disabled accounts are visible only to IT.
- Every grant records its grant date and its revoke date.

---

## 4. Decision log

| ID | Decision | Why | Rejected alternative |
|---|---|---|---|
| D-001 | IAM is the source of truth for grants | One place to audit and to change access | Per-service permission tables |
| D-002 | Services enforce locally with Gates and Policies | No per-request call to IAM, so an IAM outage doesn't take the apps down | IAM decides every request (single point of failure, extra hop, IAM would need every app's business rules) |
| D-003 | Spatie is not the source of truth | No grantor, grant date or revoke date. Revoking deletes rows. No delegation ceiling. Awkward for users in several offices. Duplicated tables in every service. | Spatie in IAM or in each service |
| D-004 | Custom grants table in IAM. Rows are never deleted, only marked revoked. | Audit requirement, and grants must survive a head leaving | Delete-on-revoke |
| D-005 | Each app declares its capabilities as keys. IAM stores the catalog and which blocks each office can offer. | The head picks from "the complete list of blocks my office is capable of" | Hardcoded capability lists in IAM |
| D-006 | Effective capabilities reach services as an event-fed local projection | Cheap checks, immediate revocation, no token growth | Token claims (token grows with blocks, revocation waits for expiry unless a denylist is added) |
| D-007 | Missing or stale projection means fail closed (deny, or ask IAM) | Security over convenience | Fail open |
| D-008 | The delegation ceiling is enforced in IAM, not in the apps | A grantor can only give what they hold or what their office offers. IT sits above heads. | Checks in each app |
| D-009 | Account disable and grant revocation events get the highest priority | Eventual consistency is acceptable, but access removal must not lag | Same priority as other events |
| D-010 | `/me` returns the effective capability list for the UI only. The backend never trusts the frontend. | Backend always enforces | Frontend-only checks |

---

## 5. Responsibilities

**IAM**
- Users, credentials, sessions and tokens
- Capability catalog (registered by apps)
- Which capabilities each office can offer
- Grants: grantor, grantee, office, capability, granted_at, revoked_at, revoked_by
- Enforcing the delegation ceiling
- Publishing events when grants or accounts change

**Each service**
- Subscribes to IAM events and keeps a local projection of effective capabilities per user per office
- A single Gate check (for example in `Gate::before` or one helper) that reads the projection
- Policies for record rules: ownership, office scope, record status
- Declaring its own capabilities to IAM

**Frontend**
- Reads `/me` to show or hide menus, apps and buttons
- Never the final authority

---

## 6. Request flow

1. The user sends a request with their IAM-issued session or token.
2. The service identifies the user and reads their capabilities from the local projection.
3. The Gate checks the capability key (for example `registrar.student_profile.chinese_name.update`).
4. The Policy checks the record (this office? this owner? allowed status?).
5. Allow or deny. Denials are logged in the service's `audit_logs`, including the office the user was acting under.

---

## 7. Change flow

1. A head or IT grants or revokes a capability through IAM.
2. IAM validates the ceiling, writes the grant row (never deletes), and publishes an event.
3. Each service updates its projection.
4. Revocation and disable take effect as soon as the event is processed.

---

## 8. Open items (to be decided one step at a time)

- Capability key convention. A proposal is `app.module.block.action`.
- Exact table and column names in IAM.
- Projection storage in services: Redis, the service database, or both.
- Event names and payload shape.
- How IT's "sees everything" authority is represented, as a flag or as real grants.
- Edge cases: an office head leaving, a user changing home office, a user in several offices.
- Policy conventions: where Policies live, and how office scope is resolved.

---

## 9. Implementation order (each step needs approval before the next)

1. Fix the capability key convention and write the catalog format.
2. IAM: migrations for the catalog, office offerings and grants.
3. IAM: grant and revoke service with the ceiling rule.
4. IAM: publish grant and account events.
5. Service: projection consumer and storage.
6. Service: Gate that reads the projection, fail closed.
7. Service: first Policy (a real record-level rule).
8. IAM: `/me` returns effective capabilities.
9. Verify revocation end to end: grant, use, revoke, denied.

---

## 10. Working agreement for the assistant

This document is the basis for the work, and the following rules apply to every step.

1. **One step at a time.** Do one step, verify it, and only then move on. Don't skip ahead.
2. **No changes until I approve.** Ask before significant architectural decisions. Don't redo discovery or redesign what is decided here.
3. **Paste, don't download.** If it can be pasted in chat, give it in chat. Don't hand me downloadable files.
4. **Be specific about edits.** For every change, say:
   - **File:** the exact path
   - **Location:** where in the file
   - **Find:** the exact existing block
   - **Replace with:** the exact new block
5. **If I give you the full code**, return the full updated or corrected version of that file.
6. **Document every decision.** After each decision, add a row to the decision log (section 4) with the next ID, what was decided, why, and what was rejected.
7. **Always ask if I'm satisfied** with the implementation at the end of each step, before proposing the next one.

Edit format to use:

```
File: services/iam/app/...
Location: inside the `grant()` method, after the validation block
Find:
<exact existing code>
Replace with:
<exact new code>
```

Reference: repo `Jp-Code-Repo/concord-v0`, with IAM context in `CONCORD_IAM_IMPLEMENTATION_CONTEXT.md`.