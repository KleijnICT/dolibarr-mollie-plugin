#!/bin/sh
# Builds the installable module zip for Dolibarr's "Deploy/install external app/module".
# Usage: ./build.sh  ->  dist/module_mollie-<version>.zip
# The version is read from the module descriptor (single source of truth).
set -eu

cd "$(dirname "$0")"

VERSION=$(sed -n "s/.*\$this->version = '\([0-9.]*\)'.*/\1/p" mollie/core/modules/modMollie.class.php)
if [ -z "$VERSION" ]; then
	echo "Could not determine the version from modMollie.class.php" >&2
	exit 1
fi

mkdir -p dist
ZIP="dist/module_mollie-$VERSION.zip"
rm -f "$ZIP"
zip -r -q -X "$ZIP" mollie -x '*.DS_Store'
echo "Built: $ZIP"
unzip -l "$ZIP"
