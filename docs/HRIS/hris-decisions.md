# Concord HRIS Decision Register

Last updated: 2026-10-20

This register records approved architectural and data-model decisions for the HRIS service. Approval records the intended design; it does not mean the implementation has been verified in the repository or through tests.

## DEC-HRIS-001: HRIS owns departments and initiates IAM account provisioning

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** HRIS owns the department catalog and employee-department assignments. When HR onboards an employee and requests an IAM account, HRIS calls an explicit IAM API. IAM remains the sole owner of user accounts, office memberships, and authorization records. HRIS must never write directly to the IAM database.
- **Why:** Employment and department data belong to HRIS, while identity and access control belong to IAM. An explicit service contract preserves that boundary.
- **Implementation status:** Unverified. IAM account-provisioning and membership/primary-office APIs, the department-to-office relationship, and end-to-end behavior must be confirmed before implementation is considered complete.

## DEC-HRIS-002: One active department assignment per employee

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** An employee may have only one active department assignment at a time. A transfer ends the previous assignment and creates a new assignment, preserving the history of both.
- **Why:** This provides a clear current department while retaining an auditable assignment history.
- **Implementation status:** Unverified. Confirm the database constraints and assignment service behavior during implementation. HRIS assignment history is separate from IAM office-membership history.

## DEC-HRIS-003: Follow the institution's official CSC PDS template

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** The HRIS Personal Data Sheet (PDS) follows the institution's chosen official template: CSC Form No. 212, Revised 2026. Use the form and its filling guide to identify sections, fields, and validation requirements rather than inventing a replacement PDS structure.
- **Why:** The form HR actually uses should guide the PDS data model and user interface.
- **Implementation status:** Unverified. The field-by-field inventory and schema mapping have not yet been completed.
- **Source:** Civil Service Commission, CSC Form No. 212, Revised 2026, provided by the user: https://csc.gov.ph/downloads/category/540-csc-form-212-revised-2026-personal-data-sheet?download=3404:cs-form-no-212-revised-2026-personal-data-sheet

## DEC-HRIS-004: Store PDS information in section-specific tables linked to the employee ULID

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** Organize PDS information into section-specific tables linked to the persistent employee record through its ULID. Use one-to-one relationships for sections that have one current record per employee and one-to-many relationships for repeatable entries. Retrieve only the PDS sections needed for a given operation, subject to server-side authorization.
- **Why:** This mirrors the logical structure of the official PDS, supports selective retrieval, and accommodates repeatable information without forcing every field into one wide table.
- **Implementation status:** Unverified. Exact table boundaries, fields, keys, cardinalities, and naming must be determined from the PDS inventory and existing repository conventions before schema implementation.

### Change history

- **2026-10-20:** Recorded DEC-HRIS-001 through DEC-HRIS-004 from the approved decisions and current project context. Implementation status remains unverified pending repository and test verification.
