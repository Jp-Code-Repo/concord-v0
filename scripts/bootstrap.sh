#!/usr/bin/env bash
# One-time setup for a fresh checkout. Safe to re-run.
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."

# The IAM Laravel application must be part of the repository.
# Do not generate a bare Laravel app: the old scaffold/ directory has been removed.
if [ ! -f services/iam/artisan ]; then
  echo "ERROR: services/iam/artisan was not found."
  echo "The IAM Laravel application must already exist in the checkout."
  echo "Restore the committed IAM application files before running bootstrap."
  exit 1
fi

[ -f .env ] || cp .env.example .env

set_var()  { sed -i "s|^$1=.*|$1=$2|" .env; }                                   # always set
fill_var() { grep -qE "^$1=$" .env && set_var "$1" "$2" || true; }              # only if blank
rand()     { openssl rand -hex 16; }

set_var HOST_UID "$(id -u)"
set_var HOST_GID "$(id -g)"
fill_var MYSQL_ROOT_PASSWORD "$(rand)"
fill_var IAM_DB_PASSWORD     "$(rand)"
fill_var HR_DB_PASSWORD      "$(rand)"
fill_var REDIS_PASSWORD      "$(rand)"
fill_var IAM_APP_KEY         "base64:$(openssl rand -base64 32)"
fill_var IAM_ADMIN_PASSWORD  "$(rand)"

echo "==> Building the PHP image"
docker compose build iam

echo "==> Starting MySQL and Redis"
docker compose up -d --wait mysql redis

echo "==> Migrating and seeding IAM"
docker compose run --rm iam php artisan migrate --force
docker compose run --rm iam php artisan db:seed --force

echo "==> Starting everything"
docker compose up -d

echo
echo "Concord is up at http://localhost"
echo "IT admin login:  $(grep ^IAM_ADMIN_USERNAME= .env | cut -d= -f2)  /  password in .env (IAM_ADMIN_PASSWORD)"
echo "Verify with:     ./scripts/smoke.sh"
