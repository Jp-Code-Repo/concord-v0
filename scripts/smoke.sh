#!/usr/bin/env bash
# End-to-end check of the edge + IAM. Needs the stack running.
set -uo pipefail
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

BASE="${BASE_URL:-http://localhost}"
JAR="$(mktemp)"; trap 'rm -f "$JAR"' EXIT
JSON=(-H 'Content-Type: application/json' -H 'Accept: application/json')
fail=0

code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
check() { # name expected actual
  if [ "$2" = "$3" ]; then echo "  ok   $1"; else echo "  FAIL $1 (expected $2, got $3)"; fail=1; fi
}

echo "Smoke test against $BASE"
check "portal page loads"                         200 "$(code "$BASE/")"
check "me without a cookie is rejected"           401 "$(code "${JSON[@]}" "$BASE/api/iam/me")"
check "verify is hidden from the outside"         404 "$(code "$BASE/api/iam/verify")"
check "edge-check without a cookie is rejected"   401 "$(code "${JSON[@]}" "$BASE/api/iam/edge-check")"
check "wrong password is rejected"                401 "$(code "${JSON[@]}" -X POST -d '{"username":"x","password":"y"}' "$BASE/api/iam/login")"
check "admin login works"                         200 "$(code "${JSON[@]}" -c "$JAR" -X POST \
  -d "{\"username\":\"$IAM_ADMIN_USERNAME\",\"password\":\"$IAM_ADMIN_PASSWORD\"}" "$BASE/api/iam/login")"
check "me with the cookie works"                  200 "$(code "${JSON[@]}" -b "$JAR" "$BASE/api/iam/me")"
check "edge vouches for the cookie (auth_request)" 200 "$(code "${JSON[@]}" -b "$JAR" "$BASE/api/iam/edge-check")"
check "a forged identity header does not help"    401 "$(code "${JSON[@]}" -H 'Concord-User-Id: forged' -H 'X-User-Id: forged' "$BASE/api/iam/edge-check")"
check "logout works"                              204 "$(code "${JSON[@]}" -b "$JAR" -X POST "$BASE/api/iam/logout")"
check "old cookie is dead right after logout"     401 "$(code "${JSON[@]}" -b "$JAR" "$BASE/api/iam/me")"

[ "$fail" = 0 ] && echo "All good." || { echo "Some checks failed."; exit 1; }
