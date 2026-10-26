#!/usr/bin/env bash
# Times login, /me and logout through the edge and prints the median of N runs.
#   ./scripts/bench.sh        (N = 6)
#   ./scripts/bench.sh 8
# Needs the stack running and IAM_ADMIN_USERNAME / IAM_ADMIN_PASSWORD in .env.
# Keep N small: login is rate-limited to 10 per minute (Laravel) per IP; one extra login
# is made first as a warm-up. Run it before and after any performance change.
set -uo pipefail
export MSYS_NO_PATHCONV=1
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

BASE="${BASE_URL:-http://localhost}"
N="${1:-6}"
JAR=".bench-cookie-$$.txt"; trap 'rm -f "$JAR"' EXIT
JSON=(-H 'Content-Type: application/json' -H 'Accept: application/json')
BODY="{\"username\":\"$IAM_ADMIN_USERNAME\",\"password\":\"$IAM_ADMIN_PASSWORD\"}"

# Runs curl, checks the HTTP status, prints the time in whole milliseconds.
# A wrong status (e.g. 401 because the login failed) aborts: timings of failed requests
# are meaningless.
timed() {
  local want="$1" out code secs; shift
  out="$(curl -s -o /dev/null -w '%{http_code} %{time_total}' "$@")"
  code="${out%% *}"; secs="${out##* }"
  if [ "$code" != "$want" ]; then
    echo "ERROR: expected HTTP $want, got $code for: ${*: -1}" >&2
    echo "Check IAM_ADMIN_USERNAME / IAM_ADMIN_PASSWORD in .env and that the stack is up." >&2
    exit 1
  fi
  awk -v s="$secs" 'BEGIN{printf "%d", s*1000}'
}
median() { sort -n | awk '{a[NR]=$1} END{print a[int((NR+1)/2)]}'; }

# warm-up (opcache, connections)
timed 200 -c "$JAR" "${JSON[@]}" -X POST -d "$BODY" "$BASE/api/iam/login" >/dev/null
timed 200 -b "$JAR" "${JSON[@]}" "$BASE/api/iam/me" >/dev/null
timed 204 -b "$JAR" "${JSON[@]}" -X POST "$BASE/api/iam/logout" >/dev/null

L=(); M=(); O=()
for _ in $(seq 1 "$N"); do
  L+=("$(timed 200 -c "$JAR" "${JSON[@]}" -X POST -d "$BODY" "$BASE/api/iam/login")") || exit 1
  M+=("$(timed 200 -b "$JAR" "${JSON[@]}" "$BASE/api/iam/me")") || exit 1
  O+=("$(timed 204 -b "$JAR" "${JSON[@]}" -X POST "$BASE/api/iam/logout")") || exit 1
done

echo "Median of $N runs through $BASE (milliseconds)"
echo "  login   $(printf '%s\n' "${L[@]}" | median) ms   (includes one bcrypt check, ~250 ms by design)"
echo "  /me     $(printf '%s\n' "${M[@]}" | median) ms"
echo "  logout  $(printf '%s\n' "${O[@]}" | median) ms"
