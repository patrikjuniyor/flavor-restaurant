#!/usr/bin/env bash
#
# Build the self-contained motion preview.
#
# Produces one HTML file that shows all twelve demos with the theme's real
# stylesheets and scripts inlined: no WordPress, no build step, no network.
# Open it in a browser and every entrance, hover, ripple, filter and night
# palette the theme ships is there to watch.
#
# The pages come from the demos' own template parts, rendered through the
# offline WordPress mock the test suites already use. The mock models
# wc_get_products() in its paginated shape only, while the demo templates call
# it unpaginated exactly as WooCommerce documents; rather than edit a fixture
# the whole regression suite depends on, this script renders from a throwaway
# copy in /tmp and applies that one correction there.
#
# Usage:  bash dev-tools/preview/build-preview.sh [output.html]
#
# Environment:
#   PHP_BIN                PHP binary (default: php)
#   FLAVOR_PREVIEW_WORK    Where the throwaway copy lives (default: /tmp/flavor-preview)
#   FLAVOR_PREVIEW_PAGES   Where per-skin JSON is written (default: /tmp)

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
WORK="${FLAVOR_PREVIEW_WORK:-/tmp/flavor-preview}"
PAGES="${FLAVOR_PREVIEW_PAGES:-/tmp}"
PHP="${PHP_BIN:-php}"
OUT="${1:-$REPO/flavor-motion-preview.html}"

SKINS=(
	modern-restaurant luxury-dining persian-traditional pizza-italian
	fast-food cafe-bistro bakery-pastry juice-bar dark-luxe minimal-clean
	cloud-kitchen catering
)

echo "1/3  copying the checkout to $WORK"
rm -rf "$WORK"
mkdir -p "$WORK"
tar --exclude=.git -C "$REPO" -cf - . | tar -C "$WORK" -xf -

echo "2/3  correcting the product mock in the copy"
python3 - "$WORK" <<'PY'
import pathlib
import sys

path = pathlib.Path(sys.argv[1]) / 'flavor-core/tests/mock-wp-environment.php'
source = path.read_text(encoding='utf-8')
old = """function wc_get_products( array $args ): object {
	return (object) array(
		'products' => array_values( $GLOBALS['_mock_wc_products'] ),
		'total'    => count( $GLOBALS['_mock_wc_products'] ),
	);
}"""
new = """function wc_get_products( array $args ) {
	$products = array_values( $GLOBALS['_mock_wc_products'] );
	/* Real WooCommerce returns an object only for paginate => true; every
	   other caller gets a plain array. The shared mock only modelled the
	   paginated shape, which the rendered demo pages do not use. */
	if ( ! empty( $args['paginate'] ) ) {
		return (object) array( 'products' => $products, 'total' => count( $products ) );
	}
	if ( ! empty( $args['limit'] ) && $args['limit'] > 0 ) {
		$products = array_slice( $products, 0, (int) $args['limit'] );
	}
	return $products;
}"""
if old not in source:
    sys.exit('mock-wp-environment.php no longer matches the shape this script corrects')
path.write_text(source.replace(old, new), encoding='utf-8')
PY

echo "3/3  rendering twelve demos and assembling"
export FLAVOR_PREVIEW_WORK="$WORK"
export FLAVOR_PREVIEW_PAGES="$PAGES"
for skin in "${SKINS[@]}"; do
	"$PHP" "$HERE/render.php" "$skin" "$PAGES/page-$skin.json"
done

python3 "$HERE/assemble.py" "$OUT"
echo "done: $OUT"
