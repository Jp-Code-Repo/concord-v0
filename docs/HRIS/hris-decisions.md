# Concord HRIS Decision Register

Last updated: 2026-10-21

This register records approved architectural and data-model decisions for the HRIS service. Approval records the intended design; it does not mean the implementation has been verified in the repository or through tests. Decisions describe the approved direction, while exact schema details remain subject to repository conventions and the implementation review noted under each decision.

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

## DEC-HRIS-003: Use the institution's selected official CSC PDS as the field reference

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** The PDS field inventory is based on the official CSC Form No. 212 edition selected for institutional use. The form and its filling guide identify the required questions and fields, but do not dictate Concord's table boundaries or database layout. Concord may group and normalize the fields according to real usage, relationships, security, and maintainability.
- **Why:** HRIS must capture the information required by the institution's PDS without copying the paper form's layout blindly into the database.
- **Implementation status:** In progress; not verified against the target edition. The user supplied a link to the CSC Form No. 212 Revised 2026 page, but the uploaded PDF examined during field inventory is labelled Revised 2025. The inventory must be compared with the actual Revised 2026 form and guide before the field list is considered final.
- **Source link supplied by user:** https://csc.gov.ph/downloads/category/540-csc-form-212-revised-2026-personal-data-sheet?download=3404:cs-form-no-212-revised-2026-personal-data-sheet
- **Uploaded reference currently inspected:** `Personal Data Sheet (PDS) 2025 _ CS Form 212 Revised 2025.pdf`.

## DEC-HRIS-004: Organize PDS data into logical groups linked to the employee ULID

- **Status:** Approved
- **Date:** 2026-10-20
- **Rule:** Store employee/PDS data in logically grouped tables linked to the persistent employee record through its ULID. Use one-to-one relationships for single-record groups and one-to-many relationships for repeatable data. Retrieve only the groups needed for an operation, subject to server-side authorization. PDS sections are a field-discovery reference, not a requirement to create exactly one table per paper section; Concord decides the grouping.
- **Why:** This supports selective retrieval and a maintainable schema while keeping the PDS field requirements separate from the physical database design.
- **Implementation status:** Unverified. Exact table boundaries, fields, keys, cardinalities, and naming must be determined from the PDS inventory and existing repository conventions before schema implementation.

## DEC-HRIS-005: Basic Information is configurable globally and per service

- **Status:** Approved
- **Date:** 2026-10-21
- **Rule:** Basic Information is a configurable view of employee data, not a fixed set of columns or a dedicated duplicate of the employee record. Provide a system-wide default configuration, while allowing individual services to customize the fields and presentation they need. A configuration selects and arranges existing approved employee-data fields; changing the view must not create database columns or duplicate the underlying data.
- **Why:** Different services may need different employee attributes. A global default preserves consistency, while service-specific configuration supports each service's workflow.
- **Security rule:** Display configuration is not authorization. Every service/backend must independently enforce the requesting user's and service's permissions and return only authorized fields. Hiding a field in the UI is not a security control.
- **Implementation status:** Approved design; implementation unverified. The configuration ownership, storage format, supported field registry, and API contract remain to be designed in accordance with existing repository conventions.

## DEC-HRIS-006: Store employee email addresses and phone numbers as separate repeatable collections

- **Status:** Approved
- **Date:** 2026-10-21
- **Rule:** Maintain a separate email table and a separate phone/contact-number table. Each table supports multiple records per employee and references the employee ULID. Email records capture the address, an email type such as company/personal, primary selection, and an optional date-added/audit timestamp. Phone records capture the number, a phone type such as mobile/telephone, primary selection, and an optional date-added/audit timestamp. Exact names and columns are subject to repository conventions.
- **Primary display behavior:** The employee/authorized encoder chooses a primary email and a primary phone number. Basic Information displays the selected records; it should not store independent copies of those selected values. The primary selection is maintained with the respective email/phone records, not through a separate joining table unless a future requirement establishes a need for one.
- **Why:** Employees can have multiple personal and work email addresses and multiple contact numbers. Separate repeatable collections are straightforward to query and manage, while the selected primary records can be displayed in Basic Information.
- **Implementation status:** Approved design; implementation unverified. The database must enforce the primary-selection rule server-side and prevent multiple active primary emails or multiple active primary phone numbers per employee. Exact uniqueness/transaction strategy depends on the database and existing conventions. Verification workflows and historical retention behavior are not yet decided.


## DEC-HRIS-007: HRIS owns the authoritative employee profile and data pool

- **Status:** Approved
- **Date:** 2026-10-21
- **Rule:** HRIS is the authoritative source of employee data. The employee profile represents the complete HRIS-managed employee record, bringing together PDS information and operational HR data such as department assignments and other employment information. HRIS-defined groupings organize the underlying data for management; they do not have to match PDS sections.
- **Presentation rule:** The same underlying employee data may be presented through different views or documents, including Basic Information, the PDS, and purpose-specific views for other services. These presentations must draw from authoritative records rather than create competing copies of the same data.
- **Document-history qualification:** Where a completed or signed PDS must preserve what an employee declared at a particular time, HRIS may need a generated document or snapshot. This is a future workflow/design detail, not yet an approved implementation specification.
- **Why:** One authoritative employee data pool prevents competing sources of truth while allowing HR, the Concord frontend, and other authorized consumers to use views suited to their purposes.
- **Security rule:** A shared source of truth does not grant unrestricted access. Every consumer and operation must enforce its authorization rules and return only data the requester is permitted to access.
- **Implementation status:** Approved design; implementation unverified. Confirm repository conventions and define the employee-profile data model, presentation/view boundaries, API contracts, and document-retention behavior before implementation.

### Open items (not approved decisions)

- **PDS field inventory:** Verify the uploaded Revised 2025 PDF against the target Revised 2026 form and guide before finalizing fields and validation rules.
- **IAM contracts:** Confirm explicit account-provisioning, membership/primary-office assignment, and service authorization contracts before implementing cross-service flows.
- **Basic Information configuration:** Define who can edit the global default and service-specific views, how fields are registered, and how field-level authorization is enforced.
- **Primary email/phone:** Define whether inactive entries can remain primary, how primary selection changes are audited, and the database-specific constraint/transaction approach.
- **Repository alignment:** Verify existing project conventions and current code before creating migrations or claiming implementation status.

### Change history

- **2026-10-20:** Recorded DEC-HRIS-001 through DEC-HRIS-004 from approved decisions and project context. Implementation status remains unverified pending repository and test verification.
- **2026-10-21:** Refined DEC-HRIS-003 and DEC-HRIS-004 to clarify that the PDS is the field reference and Concord chooses logical database groupings. Added DEC-HRIS-005 for a global default plus per-service configurable Basic Information views. Added DEC-HRIS-006 for separate repeatable email and phone collections with selected primary records displayed in Basic Information. Corrected the PDS source note to distinguish the linked Revised 2026 edition from the uploaded Revised 2025 PDF.
- **2026-10-21:** Added DEC-HRIS-007 establishing HRIS as the owner of the authoritative employee profile/data pool, with HRIS groupings and consumer-specific presentations drawing from the same underlying records. Noted the potential need for historical snapshots of completed/signed PDS documents without prematurely deciding the workflow.
