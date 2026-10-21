# HRIS Decisions: Onboarding and Concord Orientation

**Decision date:** 2026-10-21  
**Scope:** HRIS employee onboarding and first-time Concord user orientation  
**Status:** Product decisions approved; implementation remains unverified against the repository and tests.

## DEC-HRIS-008: One Onboarding Case per Employee

**Status:** Approved  
**Date:** 2026-10-21

### Decision
Create one onboarding case per employee, rather than creating a new onboarding case for each hiring event.

### Rationale
The agreed model is one onboarding case associated with each employee. This decision should not be reinterpreted as one case per hire or rehire.

### Implementation status
Unverified. Confirm the repository's existing models and constraints before implementation.

---

## DEC-HRIS-009: HRIS Requests IAM Account Creation

**Status:** Approved  
**Date:** 2026-10-21

### Decision
HRIS initiates Concord account creation by sending a request to IAM. IAM remains the owner of user accounts and is responsible for creating them. HRIS must not create or modify IAM records by writing directly to the IAM database.

The HRIS action may be labelled **Create Concord Account**, because it submits the account-creation request to IAM.

### Onboarding context
HR collects the initial employee information and intended department/office assignment, then submits the request through HRIS. The exact minimum fields and request/response contract must be confirmed against the IAM API; the proposed initial fields discussed were first name, middle name, last name, age, and gender, and are not a verified API schema.

### Implementation status
Unverified. The required IAM provisioning API, minimum data contract, department-to-office mapping, and membership/primary-office behavior must be confirmed.

---

## DEC-HRIS-010: Guided Employee Onboarding

**Status:** Approved as a product requirement  
**Date:** 2026-10-21

### Decision
After an employee signs into Concord, provide a guided employee-onboarding process. The process should use a dialog with **Previous** and **Next** controls and guide the employee through the agreed stages:

1. Explain the basic rules and process.
2. Prompt the employee to complete their Personal Data Sheet (PDS), presented in manageable sections or chunks.
3. Present a checklist of required physical documents, such as a birth certificate or medical certificate.
4. Allow HR staff to mark each physical requirement as received or confirmed, with the status reflected in the employee's view.
5. Record the physical storage location of the employee's file so HR can retrieve it.

The physical-document workflow is a record of documents submitted to HR; it does not imply that the documents are uploaded digitally.

### Requirement catalog and physical storage
Requirements may change across years or policy periods. The system should preserve the requirements applicable to each employee's onboarding, rather than silently changing historical checklists when the catalog changes. The design should support recording a structured physical location, such as storage unit/cabinet, drawer, and employee-folder identifier.

### Scope boundary
Contract workflow comes after these stages and is outside the scope of this decision.

### Implementation status
Unverified. Requirement versioning, employee-specific requirement records, physical-location fields, and HR confirmation actions require repository-level design and verification.

---

## DEC-HRIS-011: First-Time Concord User Orientation

**Status:** Approved as a product requirement  
**Date:** 2026-10-21

### Decision
Provide a separate first-time orientation for new Concord users, regardless of whether they are employees completing HR onboarding. It should explain how to use the Concord interface, including controls and buttons, the profile, where information is located, how to request corrections where that function is available, and other functions the user can access.

The orientation should use **Previous** and **Next** controls and be aware of the user's role and permissions. It must not describe or promise features the user cannot access.

### Relationship to employee onboarding
Platform orientation and HR employee onboarding have different purposes:

- **Concord orientation:** teaches users how to navigate and use the platform.
- **Employee onboarding:** collects PDS information, tracks physical requirements, and records the physical employee-file location.

They may share a dialog or presentation mechanism, but they remain distinct processes.

### Open implementation details
The following details remain to be designed: whether orientation appears only once or can be replayed, how progress is persisted, how steps vary by role/access, and the final step content and wording.

### Implementation status
Unverified. The existing frontend documentation describes some correction-request UI as preview-only; do not assume it is connected to a live backend without verification.

---

## Shared implementation guardrails

- Treat the repository as the source of truth and verify existing conventions before implementation.
- Do not infer that a product decision is already implemented.
- IAM owns account creation, authentication, memberships, and authorization; HRIS owns employee and HR data.
- Keep platform orientation separate in purpose from the employee-onboarding workflow.
- Preserve historical onboarding requirements and physical-file retrieval information.
- Do not begin contract workflow implementation based on this note; it was explicitly left for a later stage.

## Change history

| Date | Change |
|---|---|
| 2026-10-21 | Consolidated decisions DEC-HRIS-008 through DEC-HRIS-011 into one note. |
