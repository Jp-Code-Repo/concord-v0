---
title: HRIS Handoff: IAM Block Authorization Contract
date: 2026-10-21
tags:
  - concord/hris
  - concord/iam
  - concord/handoff
aliases:
  - HRIS IAM handoff
status: handoff-pending-iam-finalization
---

# HRIS Handoff: IAM Block Authorization Contract

**Date:** 2026-10-21  
**Status:** Authorization behavior agreed; proposed endpoint/schema pending IAM finalization and implementation.

## What HRIS can rely on

- IAM is the authority for current Block-level access decisions.
- HRIS must check IAM before each protected backend operation and proceed only when IAM explicitly allows it.
- A revocation must deny the next check. HRIS must not rely on stale authorization results.
- Access explicitly granted in another office can be valid for that office's resource; office access must not leak across boundaries.
- If IAM is unavailable or the decision cannot be verified, HRIS must fail closed.
- Both allowed and denied checks will be audited by IAM; runtime authorization audit records are append-only. Retention is deferred.

## Proposed contract, not a finalized API

Candidate endpoint: `POST /api/iam/internal/authorize-block`

Proposed request fields: `user_id`, `block_key`, `office_id`.

Proposed response fields: `allowed` and `reason`.

The endpoint path, exact schema, trusted user identity binding, reason-code list, HTTP/error semantics, and service credential mechanism are not finalized. HRIS must not code against this proposal as though it were an existing API.

## Security requirements

- Internal service network only; not exposed through the public Concord edge.
- Only explicitly approved Concord services may call it.
- Each service has its own private server-side credential, validated by IAM.
- A service credential identifies the calling service, not the user's authorization.
- HRIS must not trust a browser-supplied user ID.

## Administrator rule requiring careful implementation

A distinct conceptual `Administrator` authority has been approved for universal Block/action access, with authentication still required. This is not the same as assuming every IT Administrator has that authority. The final role/account representation and provisioning remain open; IAM must finalize them before integration relies on this bypass.

## IAM deliverables before integration

1. Final endpoint path and internal routing.
2. Final request/response schema and validation/error behavior.
3. Service authentication and credential operations.
4. Administrator authority representation and evaluation.
5. Runtime audit implementation and automated tests.

Reference: IAM's [[iam-authorization-decisions]], [[IAM - Access Model|iam]], and [[iam-internal-authorization-contract-proposal]]. HRIS-side decisions: [[hris-decisions]]. Back to [[Concord Index]].
