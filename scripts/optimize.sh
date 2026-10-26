#!/usr/bin/env bash
# Laravel's config/route/event/view caches for the IAM service.
#   ./scripts/optimize.sh on     build the caches (fewer files read per request)
#   ./scripts/optimize.sh off    clear them (do this while you are editing config/routes)
#
# The caches live in services/iam/bootstrap/cache. They freeze config and routes, so after
# changing config/*.php, routes/*.php, .env or docker-compose environment values, run
# "off" then "on" again (or just "off" while developing).
set -euo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."

case "${1:-}" in
  on)  docker compose exec -u www-data iam php artisan optimize ;;
  off) docker compose exec -u www-data iam php artisan optimize:clear ;;
  *)   echo "usage: $0 on|off"; exit 1 ;;
esac
