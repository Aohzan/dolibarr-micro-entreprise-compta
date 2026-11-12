#!/usr/bin/env bash
# Prepare a release: set the module version and build the installable zip.
# Called by semantic-release (prepareCmd) with the next version.
# Usage: scripts/release.sh <version>
set -euo pipefail

version="${1:?usage: scripts/release.sh <version>}"
descriptor="core/modules/modAutoMicroEnt.class.php"

sed -i -E "s/(\\\$this->version = ')[^']*(';)/\1${version}\2/" "${descriptor}"
grep -q "\$this->version = '${version}';" "${descriptor}" || {
	echo "Version not set in ${descriptor}" >&2
	exit 1
}

# Dolibarr expects the zip to hold the module directory, named after the module.
# git archive honours the export-ignore entries of .gitattributes; the stash
# keeps the version bump that is not committed yet.
mkdir -p dist
stash="$(git stash create)"
git archive --format=zip --prefix=automicroent/ \
	--output="dist/module_automicroent-${version}.zip" "${stash:-HEAD}"
echo "Built dist/module_automicroent-${version}.zip"
