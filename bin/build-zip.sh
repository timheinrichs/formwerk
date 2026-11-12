#!/usr/bin/env bash
# Builds the installable plugin ZIP: dist/formwerk-<version>.zip with a top-level "formwerk" folder.
# Used by the release workflow and for local checks. Expects a clean checkout with composer and npm available.
#
#   bash bin/build-zip.sh            build
#   bash bin/build-zip.sh v0.1.0     build and fail if the plugin version is not 0.1.0
set -euo pipefail
cd "$(dirname "$0")/.."

version=$(sed -n 's/^ \* Version: *//p' formwerk.php | tr -d '[:space:]')
[ -n "$version" ] || { echo "No version found in formwerk.php" >&2; exit 1; }

expected=${1:-}
if [ -n "$expected" ] && [ "${expected#v}" != "$version" ]; then
    echo "Tag $expected does not match the plugin version $version" >&2
    exit 1
fi

# Runtime dependencies only; dev tools come back with a plain "composer install".
composer install --no-dev --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build

rm -rf dist
mkdir -p dist/formwerk
rsync -a --exclude-from=.distignore ./ dist/formwerk/

for file in formwerk.php uninstall.php vendor/autoload.php build/admin.js build/admin.css build/admin.asset.php; do
    [ -f "dist/formwerk/$file" ] || { echo "Missing in package: $file" >&2; exit 1; }
done

(cd dist && zip -qr "formwerk-$version.zip" formwerk)
rm -rf dist/formwerk
echo "dist/formwerk-$version.zip"
