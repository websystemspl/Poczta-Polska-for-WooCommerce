#!/usr/bin/env bash
# Builds the release ZIP in dist/: plugin files without the paths listed in .distignore.
#
# Usage: build-zip.sh [--wporg]
#   --wporg  WordPress.org package: also leaves out the bundled translations
#            (languages/*.po, *.mo, *.json). WordPress.org serves them from
#            translate.wordpress.org; the template (.pot) stays in the package.
set -euo pipefail

cd "$(dirname "$0")/../.."

slug="poczta-polska-for-woocommerce"
version="$(sed -n 's/^ \* Version: *//p' "$slug.php" | tr -d '[:space:]')"
stable="$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')"

if [ "$version" != "$stable" ]; then
	echo "Version $version in $slug.php differs from Stable tag $stable in readme.txt." >&2
	exit 1
fi

suffix=""
extra=()

if [ "${1:-}" = "--wporg" ]; then
	suffix="-wporg"
	extra=(--exclude='/languages/*.po' --exclude='/languages/*.mo' --exclude='/languages/*.json')
elif [ -n "${1:-}" ]; then
	echo "Unknown option: $1" >&2
	exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

rsync -a --exclude-from=.distignore "${extra[@]}" ./ "$work/$slug/"

mkdir -p dist
zip_file="dist/$slug-$version$suffix.zip"
rm -f "$zip_file"
( cd "$work" && zip -qr - "$slug" ) > "$zip_file"

echo "$zip_file"
