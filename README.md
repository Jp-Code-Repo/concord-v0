# Concord

*Different systems. Different people. One connected experience.*

Concord is the school's unified digital platform. It connects systems such as HRIS, Ticketing, Inventory, and Clinic through a shared portal and identity foundation, while allowing each system to manage its own data and workflows.

## Repository structure

```text
concord-v0/
├── infra/
│   ├── edge/          # Nginx entry point, routing, and authentication integration
│   ├── php/           # Shared PHP runtime image for Laravel services
│   └── mysql/         # Database initialization
├── services/
│   └── iam/           # Identity and Access Management service (Laravel)
├── scaffold/
│   └── iam/           # IAM application scaffold used during initial bootstrap
├── apps/
│   └── portal/        # Portal frontend application
├── scripts/
│   ├── bootstrap.sh   # Sets up and starts the local environment
│   └── smoke.sh       # Runs an end-to-end check through the edge
├── docs/              # Project documentation and technical decisions
└── docker-compose.yml # Local service orchestration
```

- **`infra/`** contains shared infrastructure used to run the platform.
- **`services/`** contains backend services. Each service owns a specific platform or business domain.
- **`scaffold/`** contains starter application code used by the bootstrap process.
- **`apps/`** contains user-facing applications, such as the web portal.
- **`scripts/`** contains setup and verification scripts.
- **`docs/`** contains architecture notes, decisions, implementation plans, and handoff documentation.

The tree above shows the main project areas. Refer to the repository for the current list of services and applications.

## Technology

- PHP 8.3 and Laravel 12
- MySQL
- Nginx
- Docker and Docker Compose v2
- React, TypeScript, and Vite
- TanStack Router
- Tailwind CSS and shadcn/ui

## Getting started

### Requirements

- Git
- Docker with Docker Compose v2

### 1. Clone the repository

```bash
git clone <repository-url>
cd concord-v0
```

### 2. Bootstrap Concord

```bash
./scripts/bootstrap.sh
```

The bootstrap script prepares the local environment, builds the required image, initializes the Laravel application, runs migrations and seeders, and starts the services.

### 3. Run the smoke test

```bash
./scripts/smoke.sh
```

### 4. Run IAM tests

```bash
docker compose exec iam php artisan test
```

On Windows Git Bash, if Docker arguments are affected by MSYS path conversion:

```bash
MSYS_NO_PATHCONV=1 docker compose exec iam php artisan test
```

### IAM scaffold

After the initial bootstrap, `services/iam/` is the Laravel application used for development. `scaffold/iam/` is used when the Laravel application has not yet been generated. The bootstrap script determines when the scaffold is needed.

## How authentication works

1. Users sign in through IAM.
2. IAM establishes an HTTP-only `concord_session` cookie.
3. Nginx uses `auth_request` to verify sessions with IAM before forwarding protected requests.
4. The edge passes the authenticated user's ID to the upstream service through `CONCORD_USER_ID`.
5. Each service remains responsible for checking whether the user may perform the requested operation.

Protected services should only be reachable through the trusted edge.

## Configuration

- Keep local secrets and credentials in `.env` files. Never commit real secrets.
- Review the environment configuration required by `docker-compose.yml` and the bootstrap script.
- For production, configure HTTPS and secure cookies, restrict database exposure, and establish backup and restore procedures.

For detailed architecture and implementation information, see the documentation in `docs/`.
