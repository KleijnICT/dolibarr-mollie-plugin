#!/bin/sh
# Bouwt de installeerbare module-zip voor Dolibarr's "Installeer externe app/module".
# Gebruik: ./build.sh  ->  dist/module_mollie-<versie>.zip
set -eu

cd "$(dirname "$0")"

VERSION=$(sed -n "s/.*\$this->version = '\([0-9.]*\)'.*/\1/p" mollie/core/modules/modMollie.class.php)
if [ -z "$VERSION" ]; then
	echo "Kon versie niet bepalen uit modMollie.class.php" >&2
	exit 1
fi

mkdir -p dist
ZIP="dist/module_mollie-$VERSION.zip"
rm -f "$ZIP"
zip -r -q "$ZIP" mollie -x '*.DS_Store'
echo "Gebouwd: $ZIP"
unzip -l "$ZIP"
