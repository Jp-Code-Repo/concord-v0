---
title: Concord Index
date: 2026-10-21
tags:
  - concord/index
  - concord/moc
aliases:
  - Concord MOC
  - Concord Home
status: hub
---

# Concord Index

Hub for the Concord documentation set. Last reconciled 2026-10-21. See [[Reconciliation 2026-10-21]] for what was merged and which conflicts were resolved.

## Notes

| Note | Purpose | Owner |
|---|---|---|
| [[concord-frontend-working-document]] | Frontend scope, status, contract gaps, working rules | Frontend |
| [[IAM - Access Model]] | Catalog model, effective access, internal authorization, `/me` deferral | IAM |
| [[iam-authorization-decisions]] | Authoritative IAM decisions and open questions | IAM |
| [[iam-internal-authorization-contract-proposal]] | Proposed service-to-service Block check | IAM |
| [[HRIS-IAM-Authorization-Handoff]] | What HRIS may rely on from IAM | IAM to HRIS |
| [[edge]] | nginx front door, routing, trust boundaries | Edge/infra |
| [[hris-decisions]] | HRIS decision register, including onboarding and orientation | HRIS |
| [[responsible-for-what]] | Responsibility split between IAM, frontend, and edge/services | Shared |
| [[IMPLEMENTATION_NOTES]] | Application catalog implementation slice | IAM |

## Adopted IAM decisions

| ID | Decision |
|---|---|
| [[iam-authorization-decisions#DEC-AUTH-001 – Blocks are the smallest grantable unit\|DEC-AUTH-001]] | Blocks are the smallest grantable unit |
| [[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit\|DEC-AUTH-002]] | View access is explicit |
| [[iam-authorization-decisions#DEC-AUTH-003 – Office Head access is configured per office\|DEC-AUTH-003]] | Office Head access is configured per office |
| [[iam-authorization-decisions#DEC-AUTH-004 – can_delegate controls delegation, not personal access\|DEC-AUTH-004]] | can_delegate controls delegation, not personal access |
| [[iam-authorization-decisions#DEC-AUTH-005 – Staff access requires active grants and active membership\|DEC-AUTH-005]] | Staff access requires active grants and active membership |
| [[iam-authorization-decisions#DEC-AUTH-006 – Office boundaries are mandatory\|DEC-AUTH-006]] | Office boundaries are mandatory |
| [[iam-authorization-decisions#DEC-AUTH-007 – IT administrator access is global\|DEC-AUTH-007]] | IT administrator access is global |
| [[iam-authorization-decisions#DEC-AUTH-008 – Preserve authorization history\|DEC-AUTH-008]] | Preserve authorization history |
| [[iam-authorization-decisions#DEC-AUTH-009 – Disabled accounts lose access immediately\|DEC-AUTH-009]] | Disabled accounts lose access immediately |
| [[iam-authorization-decisions#DEC-AUTH-010 – Account disablement is restricted to IT Administrators\|DEC-AUTH-010]] | Account disablement is restricted to IT Administrators |
| [[iam-authorization-decisions#DEC-AUTH-011 – Defer Spatie Permission and retain Concord IAM as the access authority\|DEC-AUTH-011]] | Defer Spatie Permission and retain Concord IAM as the access authority |
| [[iam-authorization-decisions#DEC-AUTH-012 – Concord services must check current IAM authorization for protected operations\|DEC-AUTH-012]] | Concord services must check current IAM authorization for protected operations |
| [[iam-authorization-decisions#DEC-AUTH-013 – Runtime authorization checks are audited with append-only records\|DEC-AUTH-013]] | Runtime authorization checks are audited with append-only records |
| [[iam-authorization-decisions#DEC-AUTH-014 – Administrator authority has universal Block/action access\|DEC-AUTH-014]] | Administrator authority has universal Block/action access |

## Open IAM decisions

| ID | Question |
|---|---|
| [[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?\|OPEN-AUTH-001]] | Is an office an active runtime authorization context? |
| [[iam-authorization-decisions#OPEN-AUTH-002 – Which high-risk Blocks need extra restrictions?\|OPEN-AUTH-002]] | Which high-risk Blocks need extra restrictions? |
| [[iam-authorization-decisions#OPEN-AUTH-003 – Are there Block dependency rules beyond explicit grants?\|OPEN-AUTH-003]] | Are there Block dependency rules beyond explicit grants? |
| [[iam-authorization-decisions#OPEN-AUTH-004 – Office Head restrictions beyond office_head_blocks\|OPEN-AUTH-004]] | Office Head restrictions beyond office_head_blocks |
| [[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries\|OPEN-AUTH-005]] | Administrator authority representation and administration boundaries |

## HRIS decisions

| ID | Decision |
|---|---|
| [[hris-decisions#DEC-HRIS-001 – HRIS owns departments and initiates IAM account provisioning\|DEC-HRIS-001]] | HRIS owns departments and initiates IAM account provisioning |
| [[hris-decisions#DEC-HRIS-002 – One active department assignment per employee\|DEC-HRIS-002]] | One active department assignment per employee |
| [[hris-decisions#DEC-HRIS-003 – Use the institution's selected official CSC PDS as the field reference\|DEC-HRIS-003]] | Use the institution's selected official CSC PDS as the field reference |
| [[hris-decisions#DEC-HRIS-004 – Organize PDS data into logical groups linked to the employee ULID\|DEC-HRIS-004]] | Organize PDS data into logical groups linked to the employee ULID |
| [[hris-decisions#DEC-HRIS-005 – Basic Information is configurable globally and per service\|DEC-HRIS-005]] | Basic Information is configurable globally and per service |
| [[hris-decisions#DEC-HRIS-006 – Store employee email addresses and phone numbers as separate repeatable collections\|DEC-HRIS-006]] | Store employee email addresses and phone numbers as separate repeatable collections |
| [[hris-decisions#DEC-HRIS-007 – HRIS owns the authoritative employee profile and data pool\|DEC-HRIS-007]] | HRIS owns the authoritative employee profile and data pool |
| [[hris-decisions#DEC-HRIS-008 – One Onboarding Case per Employee\|DEC-HRIS-008]] | One Onboarding Case per Employee |
| [[hris-decisions#DEC-HRIS-009 – HRIS Requests IAM Account Creation\|DEC-HRIS-009]] | HRIS Requests IAM Account Creation |
| [[hris-decisions#DEC-HRIS-010 – Guided Employee Onboarding\|DEC-HRIS-010]] | Guided Employee Onboarding |
| [[hris-decisions#DEC-HRIS-011 – First-Time Concord User Orientation\|DEC-HRIS-011]] | First-Time Concord User Orientation |

## Contract status at a glance

| Item | Status | Where |
|---|---|---|
| `/api/iam/me` response schema | Deferred by IAM | [[IAM - Access Model]] |
| `/api/iam/internal/authorize-block` | Proposal, not implemented | [[iam-internal-authorization-contract-proposal]] |
| Service-to-service credentials | Approved requirement, not implemented | [[iam-authorization-decisions]] |
| Runtime authorization audit | Approved requirement, not implemented | [[iam-authorization-decisions]] |
| Administrator authority representation | Open | [[iam-authorization-decisions]] |
| IAM account-provisioning API for HRIS | Unconfirmed | [[hris-decisions]] |
| Session bug (login works, cookie not accepted) | Reported fixed 2026-10-20; edge note still says parked | [[edge]] |
| CSRF and cookie policy | Undecided | [[edge]] |
