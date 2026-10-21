---
title: IAM Internal Block Authorization Contract Proposal
date: 2026-10-21
tags:
  - concord/iam
  - concord/proposal
aliases:
  - internal authorization proposal
  - authorize-block proposal
status: proposal-not-finalized
---

# IAM Internal Block Authorization Contract Proposal

**Date:** 2026-10-21  
**Status:** Proposal only. Behavior decisions are approved in discussion; the endpoint contract is not finalized and nothing here claims implementation.

## Purpose and ownership

Concord business services such as HRIS must ask IAM for a current authorization decision before each protected backend operation. IAM owns effective-access calculation and the allow/deny decision. The calling service enforces the result. It must not recreate IAM's grant rules.

## Approved behavior

- Check the current decision at operation time; revocation applies on the next check.
- Normal users are checked against the requested Block and relevant office.
- A home department does not prevent access to another office when access to that office/resource is explicitly granted. Access must not leak across offices.
- The distinct conceptual `Administrator` authority has universal Block/action access without individual Block grants, but authentication is still required.
- IT staff do not inherit Administrator authority by department membership alone.
- Delegation of Administrator powers is on hold.
- If IAM cannot be reached or authorization cannot be verified, reject the protected operation.
- Log both allowed and denied checks. Records are append-only through normal application operations. Retention period is deferred.

## Proposed endpoint, not finalized

`POST /api/iam/internal/authorize-block`

The path is a candidate only. IAM must finalize the route and internal routing before HRIS integrates it.

### Proposed request shape

```json
{
  "user_id": "authenticated-user-ulid",
  "block_key": "hris.employee.update",
  "office_id": "target-office-ulid"
}
```

This schema is illustrative and pending approval. The user identity must come from trusted server-side Concord authentication context, not a browser-supplied user ID. IAM must decide the authoritative way the caller binds the request to the authenticated user.

### Proposed response shape

Allowed:

```json
{
  "allowed": true,
  "reason": "access_granted"
}
```

Denied:

```json
{
  "allowed": false,
  "reason": "grant_revoked"
}
```

The `allowed` and `reason` fields reflect the agreed response model. Example reason values are illustrative. IAM must finalize the exhaustive reason-code list, HTTP status/error behavior, validation failures, and unavailable-service behavior. HRIS must never override a denial.

## Service-to-service security requirements

- Only explicitly approved Concord services may call this endpoint.
- Each calling service receives a distinct private credential.
- IAM validates the calling service credential before processing authorization requests.
- Credentials remain server-side and are never exposed to browser code.
- Credentials can be rotated or revoked independently.
- The endpoint is accessible only on the internal service network and is not exposed through the public Concord edge.
- Network isolation and service authentication are both required.
- A service credential identifies the caller service; it does not establish the user's authorization.

The exact credential format, storage/configuration names, rotation procedure, and validation middleware remain to be finalized by IAM.

## Runtime audit requirements

Each check should record:

- Calling service
- User whose access was checked
- Block key
- Relevant office ID
- Allow/deny decision
- Reason code
- Timestamp

Never log service credentials or secrets. Runtime authorization audit records are append-only through normal application operations. Corrections must be new entries. Retention is deliberately deferred.

## Required finalization before HRIS integration

IAM must finalize and document:

1. Endpoint path and internal routing.
2. Exact request schema and validation rules.
3. Trusted user identity binding.
4. Service credential format, configuration, rotation/revocation, and validation.
5. Exact response schema, reason codes, and transport/error semantics.
6. How the Administrator authority is represented and evaluated without conflating it with IT Administrator.
7. Audit persistence and tests for successful/denied checks, revocation, office boundaries, disabled users, Administrator access, service authentication, and IAM unavailability.

## Integration rule for HRIS

For each protected backend operation, HRIS must request the current IAM decision and proceed only when IAM explicitly returns `allowed: true`. Denials and inability to verify authorization must reject the operation. UI visibility is not a security boundary.

## Implementation status

- Authorization behavior: approved in discussion on 2026-10-21.
- Proposed endpoint/schema: not finalized.
- Service-to-service authentication: approved requirement, not implemented.
- Runtime authorization audit: approved requirement, not implemented or verified.
- HRIS integration: pending final IAM contract.
- Audit retention period: deferred.

## Related notes

- [[iam-authorization-decisions]]: [[iam-authorization-decisions#DEC-AUTH-012 – Concord services must check current IAM authorization for protected operations|DEC-AUTH-012]], [[iam-authorization-decisions#DEC-AUTH-013 – Runtime authorization checks are audited with append-only records|DEC-AUTH-013]], [[iam-authorization-decisions#DEC-AUTH-014 – Administrator authority has universal Block/action access|DEC-AUTH-014]] and [[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries|OPEN-AUTH-005]].
- [[HRIS-IAM-Authorization-Handoff]]: the HRIS-facing summary.
- [[IAM - Access Model]] and [[edge]].
- [[hris-decisions]]
- [[Concord Index]]
