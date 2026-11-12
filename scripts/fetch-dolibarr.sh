#!/usr/bin/env bash
# Download the Dolibarr sources used by PHPStan to resolve Dolibarr symbols.
# Usage: scripts/fetch-dolibarr.sh [version]   (default: the latest tested version)
set -euo pipefail

version="${1:-24.0.1}"
dest=".cache/dolibarr"

if [[ -f "${dest}/VERSION" && "$(cat "${dest}/VERSION")" == "${version}" ]]; then
	exit 0
fi

rm -rf "${dest}"
mkdir -p "${dest}"
curl -fsSL "https://github.com/Dolibarr/dolibarr/archive/refs/tags/${version}.tar.gz" \
	| tar -xz -C "${dest}" --strip-components=1 "dolibarr-${version}/htdocs"
echo "${version}" > "${dest}/VERSION"
