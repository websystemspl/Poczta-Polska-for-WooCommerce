#!/usr/bin/env bash
# Builds the release ZIP in dist/: plugin files without the paths listed in .distignore.
set -euo pipefail

cd "$(dirname "$0")/.."

slug="poczta-polska-for-woocommerce"
version="$(sed -n 's/^ \* Version: *//p' "$slug.php" | tr -d '[:space:]')"
stable="$(sed -n 's/^Stable tag: *//p' readme.txt | tr -d '[:space:]')"

if [ "$version" != "$stable" ]; then
	echo "Version $version in $slug.php differs from Stable tag $stable in readme.txt." >&2
	exit 1
fi

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

rsync -a --exclude-from=.distignore ./ "$work/$slug/"

mkdir -p dist
rm -f "dist/$slug-$version.zip"
( cd "$work" && zip -qr - "$slug" ) > "dist/$slug-$version.zip"

echo "dist/$slug-$version.zip"
