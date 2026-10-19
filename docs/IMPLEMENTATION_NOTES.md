# Concord IAM implementation slice: application catalog

## Files

- `services/iam/app/Http/Middleware/RequireItAdmin.php`: enforces active IT-admin privileges against the current database record.
- `services/iam/app/Http/Controllers/Admin/AppCatalogController.php`: lists the catalog hierarchy and creates/updates app metadata.
- `services/iam/bootstrap/app.php`: registers `it.admin` middleware alias.
- `services/iam/routes/api.php`: adds IT-admin-only application catalog routes.
- `services/iam/tests/Feature/IamAppCatalogTest.php`: feature coverage for access control, create/update, audit, validation, and nested listing.
- `docs/iam.md`: documents the API slice.
- `postman/Concord-IAM-Application-Catalog.postman_collection.json`: starter Postman collection.

## Routes

- `GET /api/iam/admin/apps`
- `POST /api/iam/admin/apps`
- `PATCH /api/iam/admin/apps/{appId}`

Only authenticated, active IT administrators can access these routes. App keys are immutable through this API, and deletion is intentionally not implemented.

## Verification

- PHP syntax checks passed for application, route, and test PHP files.
- Postman collection JSON parsed successfully.
- PHPUnit could not run in this inspection workspace because `services/iam/vendor/autoload.php` is absent.
- Docker could not run because Docker CLI is unavailable in this workspace. Run tests in the project's actual Docker environment before merging.

No database migration or schema change is included.
