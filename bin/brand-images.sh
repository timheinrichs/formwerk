#!/usr/bin/env bash
# Renders the PNG icons and banners for the update dialog from their SVG sources, with headless Chrome.
# Run after changing assets/brand/icon.svg or assets/src/brand/banner.svg:  bash bin/brand-images.sh
set -euo pipefail
cd "$(dirname "$0")/.."

chrome=${CHROME:-"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"}
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# render <svg> <css width> <css height> <scale> <output>
render() {
	cp "$1" "$work/image.svg"
	printf '<!doctype html><style>html,body{margin:0;overflow:hidden}img{display:block;width:%spx;height:%spx}</style><img src="image.svg">' "$2" "$3" > "$work/page.html"
	"$chrome" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor="$4" \
		--window-size="$2,$3" --screenshot="$work/out.png" "file://$work/page.html" >/dev/null 2>&1
	mv "$work/out.png" "$5"
	echo "$5"
}

render assets/brand/icon.svg 128 128 1 assets/brand/icon-128x128.png
render assets/brand/icon.svg 128 128 2 assets/brand/icon-256x256.png
render assets/src/brand/banner.svg 772 250 1 assets/brand/banner-772x250.png
render assets/src/brand/banner.svg 772 250 2 assets/brand/banner-1544x500.png
