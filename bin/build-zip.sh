#!/usr/bin/env bash
# Builds the installable plugin ZIP: dist/formwerk-<version>.zip with a top-level "formwerk" folder,
# and dist/formwerk-<version>.json with the requirements the updater shows before updating.
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

for file in formwerk.php uninstall.php vendor/autoload.php build/admin.js build/admin.css build/admin.asset.php assets/brand/icon.svg assets/brand/banner-772x250.png; do
    [ -f "dist/formwerk/$file" ] || { echo "Missing in package: $file" >&2; exit 1; }
done

(cd dist && zip -qr "formwerk-$version.zip" formwerk)
rm -rf dist/formwerk

requires=$(sed -n 's/^ \* Requires at least: *//p' formwerk.php | tr -d '[:space:]')
requires_php=$(sed -n 's/^ \* Requires PHP: *//p' formwerk.php | tr -d '[:space:]')
tested=$(sed -n 's/^ \* Tested up to: *//p' formwerk.php | tr -d '[:space:]')
printf '{"version":"%s","requires":"%s","requires_php":"%s","tested":"%s"}\n' "$version" "$requires" "$requires_php" "$tested" > "dist/formwerk-$version.json"

# Locally, bring the dev tools back, so tests and lint keep working after a build.
[ -n "${CI:-}" ] || composer install --no-interaction --no-progress --quiet

echo "dist/formwerk-$version.zip"
echo "dist/formwerk-$version.json"
