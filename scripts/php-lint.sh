#!/usr/bin/env bash
# Syntax-check the given PHP files (php -l only takes one file at a time).
set -euo pipefail

status=0
for file in "$@"; do
	php -l "${file}" > /dev/null || status=1
done
exit "${status}"
