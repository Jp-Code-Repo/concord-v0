# Concord

*Different systems. Different people. One connected experience.*

Concord is the portal and platform that brings the school's systems (HRIS, Ticketing,
Inventory, Clinic, ...) behind a single login. What a person sees is decided by **IAM**.

## Layout

```
infra/edge/      nginx edge: portal + routing + edge authentication (auth_request)
infra/php/       shared PHP 8.3 BASE image (extensions, php.ini, php-fpm) for every Laravel service
infra/mysql/     first-run init: one database + one user per service
services/iam/    IAM service (Laravel 12); its Dockerfile installs composer deps into the image
services/concord-frontend/ React + TypeScript + Vite portal frontend
scripts/         bootstrap.sh (setup), smoke.sh (end-to-end check), bench.sh (timings), optimize.sh (Laravel caches)
docs/            IAM / FRONTEND / HRIS specs, IMPLEMENTATION_LOG, PERFORMANCE
```

Next to come: `services/gate` (portal backend), `services/hr`, `apps/portal`
(React + TanStack Router + shadcn/ui), a notification service (Reverb).

## Run it

Needs Docker with Compose v2.

```bash
./scripts/bootstrap.sh     # secrets, images, Laravel, migrations, seed, start
./scripts/smoke.sh         # end-to-end check through the edge
docker compose exec iam php artisan test
./scripts/bench.sh          # login / me / logout timings
```

Speed notes (vendor volume, rebuild rules, optional caches): see `docs/PERFORMANCE.md`.

`services/iam/` is the committed Laravel application. Bootstrap refuses to generate a replacement app when its source is missing.

## Security notes

- Secrets live only in `.env` (git-ignored); the compose file passes them as environment variables.
- Only the edge is published. MySQL is bound to 127.0.0.1:3307 for local dev - remove it in production.
- Login: rate-limited at the edge and in Laravel; one generic error for unknown user, wrong password, deactivated account, and disabled account.
- Accounts link to a separate `people` identity record by ULID. A person has at most one human account.
- Personal preferences are stored on `people`; account/session settings are stored on `users`.
- Deactivating or disabling an account revokes its sessions and clears the Redis cache immediately.


## Status

Current implementation pass:
- PHP syntax checks pass for the IAM application, migrations, seeders, routes, and tests.
- Laravel route registration passes (`php artisan route:list --path=api/iam`).
- Frontend TypeScript project check passes, and ESLint passes for the modified frontend files.
- PHPUnit could not run in this environment because PHP lacks `dom`, `mbstring`, and `xmlwriter` extensions, and no PDO database drivers are installed.
- Vite production build is blocked by a missing platform-specific Rolldown native binding in the archived `node_modules`.
- Database migrations and end-to-end behavior still need to be run in the supported Docker/MySQL environment.
