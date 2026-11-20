# Concord DEV Environment

| Property | Value |
|---|---|
| Environment | Development (DEV) |
| Host OS | Windows |
| Container Runtime | Docker Desktop |
| Application URL | `http://localhost` |
| Git Branch | `dev` |
| Compose Project | `concord` |

## 1. Purpose and Scope

This document defines the configuration and operating procedures for the Concord development environment.

DEV is used for application development, integration, debugging, and testing before changes are considered for production.

This document covers:

- Development environment architecture
- Docker Compose services
- Environment variables and secrets
- Database configuration and persistence
- Initial setup and bootstrap
- Daily development operations
- Frontend and backend development
- Validation and smoke testing
- Data protection considerations

**Scope limitation:** This document describes DEV only. Production deployment, server configuration, and automated backup implementation are outside its scope.

## 2. Technology Stack

| Component | Technology | Version |
|---|---|---|
| Web Server / Edge | Nginx | 1.27-alpine |
| Backend Framework | Laravel | 12 |
| PHP Runtime | PHP-FPM | 8.3 |
| Frontend Framework | React | 19 |
| Frontend Build Tool | Vite | 8 |
| Language | TypeScript | 6 |
| Styling | Tailwind CSS | 4 |
| Database | MySQL | 8.4 |
| Cache / Queue | Redis | 7-alpine |
| Container Management | Docker Compose | V2 |

The PHP runtime is built from a shared PHP base image. The IAM application has its own Docker image based on that shared image.

## 3. Architecture Overview

### 3.1 Service Architecture

Concord uses Nginx as its single public entry point.

The request flow is:

1. The browser connects to Nginx.
2. Nginx serves the compiled React frontend.
3. API requests are routed to the appropriate backend service.
4. IAM handles identity and authentication requests.
5. Protected requests use Nginx `auth_request` to validate the user's session through IAM.
6. IAM communicates with MySQL and Redis.

### 3.2 Docker Compose Services

| Service | Image / Build | Responsibility | Port |
|---|---|---|---|
| `edge` | `nginx:1.27-alpine` | Frontend delivery and API routing | Host 80 |
| `iam` | `concord/iam:dev` | Identity and Access Management | Internal 9000 |
| `mysql` | `mysql:8.4` | Persistent relational database | Host 3307 |
| `redis` | `redis:7-alpine` | Cache and queue storage | Internal 6379 |
| `php-base` | `concord/php:8.3` | Shared PHP runtime image | Build only |

The `php-base` service uses the `build` profile. It is an image build target, not a persistent application service.

### 3.3 Network Exposure

- Nginx publishes port `80` to the host.
- MySQL publishes port `3307` bound to `127.0.0.1`.
- IAM PHP-FPM is accessible internally through the Docker network.
- Redis is accessible internally through the Docker network.
- The services communicate through the `concord-net` bridge network.

The MySQL host binding is intended for local development access.

## 4. Environment Configuration

### 4.1 Environment File

The root `.env` file contains local environment configuration and secrets.

The repository maintains `.env.example` as the configuration template.

Rules:

- Never commit `.env`.
- Never put actual credentials in `.env.example`.
- Keep environment-specific secrets in the local `.env`.
- Update `.env.example` when introducing new required configuration variables.

The root `.gitignore` uses:

```gitignore
.env*
!.env.example
node_modules/
vendor/
.idea/
.vscode/
*.log
```

This ignores local environment files while keeping `.env.example` tracked.

### 4.2 Environment Variables

| Variable | Purpose |
|---|---|
| `HOST_UID` | Host user ID used for container file ownership |
| `HOST_GID` | Host group ID used for container file ownership |
| `APP_ENV` | Laravel environment |
| `APP_DEBUG` | Laravel debug mode |
| `COOKIE_SECURE` | Controls the Secure attribute on authentication cookies |
| `MYSQL_ROOT_PASSWORD` | MySQL root account password |
| `IAM_DB_PASSWORD` | IAM database user password |
| `CONCORD_DB_PASSWORD` | Concord platform database user password |
| `REDIS_PASSWORD` | Redis authentication password |
| `IAM_APP_KEY` | Laravel application encryption key |
| `IAM_ADMIN_USERNAME` | Designated Platform Administrator username |
| `IAM_ADMIN_EMAIL` | Designated Platform Administrator email |
| `IAM_ADMIN_PASSWORD` | Initial Platform Administrator password |
| `IAM_DEMO_SEED` | Optional demo identity seeding toggle; defaults to `false` in Compose |

`IAM_DEMO_SEED` is accepted by Docker Compose but is not currently listed in the root `.env.example`.

For local HTTP development, `COOKIE_SECURE=false` is used. Revisit this setting when HTTPS is introduced.

### 4.3 Database Credential Separation

The environment uses separate credentials for:

- MySQL root administration
- IAM database access
- Concord platform database access

`CONCORD_DB_PASSWORD` is explicitly configured in:

- `.env.example`
- `docker-compose.yml`
- `infra/mysql/init.sh`
- `scripts/bootstrap.sh`

The bootstrap script generates this password when it is blank, preserving an existing nonempty value.

The unused `HR_DB_PASSWORD` variable has been removed from the DEV configuration. HR database provisioning has also been removed from the MySQL initialization script.

## 5. Database Configuration

### 5.1 Databases

Concord currently uses two MySQL databases.

| Database | Database User | Purpose |
|---|---|---|
| `concord_iam` | `iam` | Identity, authentication, sessions, audit logs, and IAM-related records |
| `concord` | `concord` | Shared platform data, including legacy application records |

Each database has its own application database user.

### 5.2 Laravel Database Connections

The IAM application defines two database connections:

| Laravel Connection | Database | Description |
|---|---|---|
| `mysql` | `concord_iam` | Default Laravel connection |
| `concord_platform` | `concord` | Shared Concord platform connection |

Docker Compose sets `DB_CONNECTION=mysql`.

Migrations that explicitly use `concord_platform` operate on the `concord` database.

### 5.3 Migration History

Laravel's migration repository uses the `migrations` table in the default database, `concord_iam`.

Some migrations create or modify tables in `concord` through the `concord_platform` connection.

Consequently, migration history is centralized in `concord_iam`, even when a migration modifies the other database.

**Operational requirement:** Both databases must be backed up and restored together to maintain consistency between the physical schemas and Laravel's migration history.

### 5.4 MySQL Initialization

The initialization script is:

```text
infra/mysql/init.sh
```

It creates:

- `concord`
- `concord_iam`
- The corresponding database users
- The corresponding database privileges

The script is mounted into the MySQL container under:

```text
/docker-entrypoint-initdb.d/10-init.sh
```

MySQL initialization scripts run when the container initializes an empty data directory.

They do not automatically rerun on ordinary container restarts.

Changing a password in `.env` does not automatically update an existing MySQL user's password. Existing database credentials must be managed separately.

## 6. Persistent Storage

Docker Compose defines two named volumes.

| Volume | Purpose |
|---|---|
| `concord_mysql-data` | Persistent MySQL database files |
| `concord_redis-data` | Persistent Redis data |

These volumes preserve data independently of the lifecycle of the containers.

### Important Data Protection Rules

- Stopping containers does not delete named volumes.
- Removing containers with `docker compose down` retains named volumes.
- Recreating containers does not normally remove named volumes.
- `docker compose down -v` removes the Compose-managed volumes and destroys their persisted data.

**Never use `docker compose down -v` as a routine shutdown command.**

Do not run destructive database commands such as `migrate:fresh` against an environment containing data that must be preserved.

Automated database backup and restoration procedures are not currently defined by this DEV configuration.

## 7. Initial DEV Setup

### 7.1 Prerequisites

- Git
- Docker Desktop with Linux container support
- Docker Compose V2
- Node.js and npm
- Git Bash for executing the repository's shell scripts

### 7.2 Clone the Repository

```bash
git clone https://github.com/Jp-Code-Repo/concord-v0.git
cd concord
git switch dev
```

Use the `dev` branch for development work.

### 7.3 Install Frontend Dependencies

Navigate to the frontend directory:

```bash
cd services/concord-frontend
```

Install dependencies using the committed lockfile:

```bash
npm ci
```

### 7.4 Build the Frontend

```bash
npm run build
```

The build command runs TypeScript project compilation followed by the Vite production build.

The output is generated in:

```text
services/concord-frontend/dist/
```

Nginx serves this directory through a read-only bind mount.

**Important:** The Docker bootstrap script does not build the frontend. The frontend build must be performed separately.

Return to the repository root:

```bash
cd ../..
```

### 7.5 Configure the Environment

The bootstrap script creates `.env` from `.env.example` if `.env` does not exist.

It also fills blank generated secret values.

If `.env` already exists, existing nonempty values are preserved by the bootstrap script.

Review the local environment configuration before proceeding.

### 7.6 Run Bootstrap

From the repository root:

```bash
./scripts/bootstrap.sh
```

The bootstrap script performs the following operations:

1. Verifies that `services/iam/artisan` exists.
2. Creates `.env` from `.env.example` if necessary.
3. Sets `HOST_UID` and `HOST_GID`.
4. Generates missing database passwords:
   - `MYSQL_ROOT_PASSWORD`
   - `IAM_DB_PASSWORD`
   - `CONCORD_DB_PASSWORD`
   - `REDIS_PASSWORD`
5. Generates a missing `IAM_APP_KEY`.
6. Generates a missing `IAM_ADMIN_PASSWORD`.
7. Builds the shared PHP base image.
8. Builds the IAM image.
9. Starts MySQL and Redis and waits for them.
10. Runs Laravel migrations.
11. Runs Laravel database seeders.
12. Starts the complete Docker Compose environment.

The script preserves existing nonempty generated values.

It does not generate the administrator username or email. Those values are configured through `.env`.

**Important:** Bootstrap is not merely a container startup command. It executes migrations and seeders. Review the target database state before running it against an existing installation.

### 7.7 Demo Identity Seeding

Demo identity seeding is controlled by:

```text
IAM_DEMO_SEED
```

Docker Compose defaults this setting to `false`.

When enabled, `DemoIdentitySeeder` creates predefined demo identities using a shared hardcoded demo password.

Enable demo seeding only when intentionally preparing a local testing environment. Do not enable it in production.

## 8. Daily Development Operations

Run Docker Compose commands from the repository root.

### Start the Environment

```bash
docker compose up -d
```

### Check Service Status

```bash
docker compose ps
```

### View Logs

```bash
docker compose logs -f
```

To inspect an individual service:

```bash
docker compose logs -f iam
docker compose logs -f mysql
docker compose logs -f redis
docker compose logs -f edge
```

### Stop Services

```bash
docker compose stop
```

This stops the running containers without removing them or deleting persistent data.

### Remove Containers and Network

```bash
docker compose down
```

This removes the containers and Compose network but retains the named volumes.

### Validate Compose Configuration

```bash
docker compose config --quiet
```

This checks the Compose configuration for errors. It does not start services or modify database contents.

## 9. Backend Development

The IAM Laravel application is located at:

```text
services/iam/
```

The source directory is bind-mounted into the IAM container at:

```text
/var/www
```

This allows source changes to be reflected without rebuilding the IAM image.

### Execute Artisan Commands

Use Docker to execute Artisan commands:

```bash
docker compose exec iam php artisan <command>
```

Example:

```bash
docker compose exec iam php artisan migrate:status
```

### Composer Dependency Changes

The IAM image installs Composer dependencies into the image.

The `vendor` directory is maintained in a separate Docker volume at `/var/www/vendor` to avoid accessing dependencies through the Windows source bind mount.

When `composer.json` or `composer.lock` changes, rebuild and recreate IAM:

```bash
docker compose up -d --build -V iam
```

### Shared PHP Runtime Changes

The shared PHP base image is built from:

```text
infra/php/Dockerfile
```

It includes the PHP runtime, extensions, Composer, and shared PHP configuration.

Build the shared image with:

```bash
docker compose build php-base
```

After changing the shared PHP base image, rebuild the dependent IAM image as well.

## 10. Frontend Development

The frontend application is located at:

```text
services/concord-frontend/
```

### Install Dependencies

```bash
npm ci
```

### Start Vite Development Server

```bash
npm run dev
```

### Build Production Assets

```bash
npm run build
```

### Run Linting

```bash
npm run lint
```

The current Docker Compose configuration does not define a Vite development-server service. Nginx serves the compiled `dist` directory.

After modifying frontend source code, run the production build to update the assets served by Nginx.

## 11. Validation and Smoke Testing

The repository provides an end-to-end smoke test:

```bash
./scripts/smoke.sh
```

The Docker stack must already be running.

The smoke test validates:

1. Portal homepage availability.
2. Rejection of unauthenticated user requests.
3. Public inaccessibility of the IAM verification endpoint.
4. Rejection of unauthenticated edge-check requests.
5. Rejection of incorrect login credentials.
6. Successful administrator login.
7. Authenticated user endpoint access.
8. Nginx `auth_request` integration.
9. Rejection of forged identity headers.
10. Successful logout.
11. Immediate invalidation of the previous session cookie.

The script reads administrator credentials from `.env`.

It creates a temporary cookie-jar file and removes it on normal exit.

The test may create authentication and audit-log records. It does not reset or delete existing application data.

Run the smoke test after meaningful changes to IAM authentication, session handling, or Nginx routing.

## 12. Git Workflow

The repository uses separate branches for development and production.

| Branch | Purpose |
|---|---|
| `main` | Baseline branch |
| `dev` | Development and integration |
| `prod` | Production code branch |

Development work should be performed on `dev`.

Before committing:

```bash
git status --short --branch
git diff --check
git diff
```

After reviewing the changes:

```bash
git add <files>
git commit -m "type: description"
```

Publish development changes with:

```bash
git push origin dev
```

Do not commit `.env`, database credentials, application keys, or other secrets.

## 13. Current DEV Validation Baseline

The following represents the DEV environment validation completed during the November 2026 configuration review.

| Item | Verified State |
|---|---|
| Git branch | `dev` |
| Environment configuration | Updated |
| Compose configuration | Valid |
| Edge service | Running |
| IAM service | Running |
| MySQL | Healthy |
| Redis | Healthy |
| IAM database | Existing |
| Concord platform database | Existing |
| Recorded migrations | 12 |
| Laravel migration status | All 12 marked `Ran` |
| Smoke test | 11/11 passed |
| Configuration commit | `abf228c` |

The configuration commit is:

```text
abf228c chore: align dev environment database configuration
```

The five files included in that commit are:

- `.env.example`
- `.gitignore`
- `docker-compose.yml`
- `infra/mysql/init.sh`
- `scripts/bootstrap.sh`

The commit:

- Replaced the unused `HR_DB_PASSWORD` template entry with `CONCORD_DB_PASSWORD`.
- Updated `.gitignore` to ignore local `.env*` files while preserving `.env.example`.
- Removed the HR database password from Docker Compose.
- Removed HR database provisioning from the MySQL initialization script.
- Added generation of `CONCORD_DB_PASSWORD` to the bootstrap script.

These changes were validated with `docker compose config --quiet` and the existing application's 11-check smoke test.

## 14. Operational Rules

1. Use the `dev` branch for development work.
2. Keep local secrets in `.env`; never commit them.
3. Preserve Docker named volumes during routine operations.
4. Review migrations before executing them.
5. Treat bootstrap as a setup procedure that runs migrations and seeders, not as a simple startup command.
6. Build the frontend separately because Docker Compose serves its compiled assets.
7. Keep `concord_iam` and `concord` consistent during database backup and restoration.
8. Keep demo seeding disabled unless demo identities are intentionally required.
9. Rebuild dependent images when shared runtime or Composer dependencies change.
10. Keep this document aligned with the actual repository configuration.

---

**Document status:** DEV environment configuration documented.  
**Production deployment:** Not covered by this document.  
**Automated backup and restoration:** Not yet defined.