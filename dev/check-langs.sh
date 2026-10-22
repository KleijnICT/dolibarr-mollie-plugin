#!/bin/sh
# Checks that every language file of the module defines exactly the same set of
# translation keys as the reference file (en_US). Exit code 1 on any difference.
set -eu

cd "$(dirname "$0")/.."

ref="mollie/langs/en_US/mollie.lang"
tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT

keys() {
	grep -v '^[[:space:]]*#' "$1" | grep '=' | sed 's/=.*//' | sort -u
}

keys "$ref" > "$tmpdir/ref"
status=0
for f in mollie/langs/*/mollie.lang; do
	[ "$f" = "$ref" ] && continue
	keys "$f" > "$tmpdir/cur"
	missing=$(comm -23 "$tmpdir/ref" "$tmpdir/cur")
	extra=$(comm -13 "$tmpdir/ref" "$tmpdir/cur")
	if [ -n "$missing" ] || [ -n "$extra" ]; then
		status=1
		echo "$f differs from $ref:"
		[ -n "$missing" ] && echo "  missing: $(echo $missing)"
		[ -n "$extra" ] && echo "  extra:   $(echo $extra)"
	else
		echo "$f: OK ($(wc -l < "$tmpdir/cur" | tr -d ' ') keys, same as $ref)"
	fi
done
exit $status
