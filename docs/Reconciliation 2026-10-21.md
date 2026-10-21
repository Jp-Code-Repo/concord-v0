---
title: Reconciliation 2026-10-21
date: 2026-10-21
tags:
  - concord/index
  - concord/log
aliases:
  - Reconciliation log
status: log
---

# Reconciliation 2026-10-21

What was merged into this vault, which version won, and what still needs an owner.

## Version choices

| Document | Versions received | Used |
|---|---|---|
| iam-authorization-decisions | Part 1 (reconciled 2026-10-21, DEC-AUTH-012 to 014); part 2 (reconciled 2026-10-19, ends at DEC-AUTH-011) | Part 1. Part 2 is a strict subset |
| edge | Part 1 (has the internal-endpoint boundary paragraph); part 2 (lacks it) | Part 1 |
| iam | Part 1 (internal authorization section); part 2 older copy; Obsidian copy (classification, `/me` deferral, cross-service note) | Merged: part 1 as base plus the unique Obsidian sections |
| internal authorization proposal, HRIS handoff | Identical in both parts | Either |
| HRIS decision register and onboarding note | Two files, DEC-HRIS-001 to 007 and 008 to 011 | Merged into one register |
| frontend working document | 2026-10-19 | Updated |
| IMPLEMENTATION_NOTES, responsible-for-what | Single copy each | Carried over |

The two screenshots were used as illustration only (edge serving `dist/`, the sign-in flow). They are not contracts.

## Conflicts found and how they were resolved

1. **Multi-office behavior.** The frontend note stated "office is not an active runtime context" as intended behavior. The decisions record leaves this unresolved ([[iam-authorization-decisions#OPEN-AUTH-001 – Is an office an active runtime authorization context?|OPEN-AUTH-001]]). The frontend note now labels these as working assumptions.
2. **IT Administrator and `can_delegate`.** The Obsidian IAM copy said IT grants remain subject to configured delegation restrictions, and also that IT may grant regardless. [[iam-authorization-decisions#DEC-AUTH-004 – can_delegate controls delegation, not personal access|DEC-AUTH-004]] says IT is exempt, so that wording was used.
3. **Account disabling.** The Obsidian IAM copy said sessions are invalidated "under the current implementation". [[iam-authorization-decisions#DEC-AUTH-009 – Disabled accounts lose access immediately|DEC-AUTH-009]] restores this as a requirement, so that wording was used.
4. **Prerequisite View.** The frontend note listed it as needing reconciliation. [[iam-authorization-decisions#DEC-AUTH-002 – View access is explicit|DEC-AUTH-002]] rejects auto-granting, so the note now says so.
5. **`is_it_admin` as global access.** The frontend note implied it covers all admin cases. [[iam-authorization-decisions#DEC-AUTH-014 – Administrator authority has universal Block/action access|DEC-AUTH-014]] adds a distinct Administrator authority, so the note warns against equating them.
6. **`/me` field nesting.** The frontend note lists `all_access`, `apps`, `offices`. The IAM handoff places them under `access`. Left as a flagged discrepancy.
7. **Session bug.** The edge note (section 8) says the IAM session bug is parked and `/me` smoke checks fail. The owner reports it fixed on 2026-10-20 (commit `03eb8c0`, 11 smoke tests passing). The edge note's status section is stale; its text was left unchanged and should be refreshed by its owner.
8. **HRIS ownership overlap.** [[hris-decisions#DEC-HRIS-001 – HRIS owns departments and initiates IAM account provisioning|DEC-HRIS-001]] and [[hris-decisions#DEC-HRIS-009 – HRIS Requests IAM Account Creation|DEC-HRIS-009]] both state that HRIS asks IAM for accounts. Kept both, with an editorial cross-reference.

## Edits to the authority documents

Kept to a minimum. In [[iam-authorization-decisions]] and [[hris-decisions]], decision titles became headings with an en dash in place of the colon, so they can be linked from other notes. Tab-separated tables became Markdown tables. References to other files became wikilinks. No rule text was changed.

## Items that still need an owner

- **IAM:** the `/me` schema, login and logout shapes, the CSRF and cookie policy, the account-provisioning API for HRIS, and the Administrator representation ([[iam-authorization-decisions#OPEN-AUTH-005 – Administrator authority representation and administration boundaries|OPEN-AUTH-005]]).
- **IAM checklist:** the test that `verify` returns 401 after a disable, and audit logging of an Office Head's denied delegation attempt, are unchecked in the decisions record.
- **HRIS and frontend:** whether concord-frontend hosts the onboarding and Create Concord Account screens, and where orientation progress is stored.
- **Housekeeping:** the change history in the decisions record lists the 2026-10-19 reconciliation entry after the 2026-10-18 entries. Content is intact, only the order is off.
- **IMPLEMENTATION_NOTES:** unclear whether the catalog tests were in the 41-test pass.

Back to [[Concord Index]].
