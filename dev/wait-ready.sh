#!/usr/bin/env bash
# Wait until the dev Dolibarr has run its first-boot seed.
# Usage: dev/wait-ready.sh [timeout seconds]
set -euo pipefail

timeout="${1:-600}"
compose="${COMPOSE:-docker-compose}"

for ((elapsed = 0; elapsed < timeout; elapsed += 5)); do
	logs="$(${compose} logs web 2>&1)"
	if grep -q 'Seed failed' <<< "${logs}"; then
		grep 'Seed failed' <<< "${logs}" >&2
		exit 1
	fi
	# Apache starts after the seed.
	if grep -q 'Seeded [0-9]* invoices' <<< "${logs}" \
		&& curl -fsS -o /dev/null "http://localhost:${DOLIBARR_PORT:-8080}/index.php"; then
		exit 0
	fi
	sleep 5
done
echo "Dolibarr was not seeded after ${timeout}s" >&2
${compose} logs web | tail -50 >&2
exit 1
