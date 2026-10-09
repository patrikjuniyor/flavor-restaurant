<?php
/**
 * Repeating section contract tests.
 *
 * The repeater replaces two things that could not be edited: six fixed
 * gallery slots with no alt text, and three reviews hard-coded into a
 * template. The tests concentrate on where that kind of control usually
 * breaks — hostile JSON arriving from the browser, an item count that grows
 * without limit, an image field accepting javascript: — and on keeping the
 * existing site intact via migration and fallback.
 *
 *   php tests/theme/test-repeater.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/chrome-wp-stubs.php';
require_once __DIR__ . '/fixtures/page-options-stubs.php';
require_once __DIR__ . '/fixtures/preset-control-stubs.php';

require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';
require_once FLAVOR_DIR . '/inc/class-repeater.php';
require_once FLAVOR_DIR . '/inc/class-repeater-control.php';

use Flavor\Repeater;
use Flavor\Repeater_Control;

$passed = 0;
$failed = 0;

/**
 * Assert helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Description.
 */
function check( bool $condition, string $message ): void {
	global $passed, $failed;
	if ( $condition ) {
		++$passed;
		echo "  [PASS] {$message}\n";
	} else {
		++$failed;
		echo "  [FAIL] {$message}\n";
	}
}

echo "=== Flavor Repeating Section Contract Tests ===\n\n";

echo "--- 1. Schemas are coherent ---\n";

$schemas = Repeater::schemas();
check( isset( $schemas['gallery'], $schemas['testimonials'] ), 'gallery and testimonials are both repeatable' );

foreach ( $schemas as $key => $schema ) {
	check( count( $schema['fields'] ) >= 2, "{$key} has at least two fields" );
	check( ( $schema['max'] ?? 0 ) > 0 && ( $schema['max'] ?? 0 ) <= Repeater::MAX_ITEMS, "{$key} caps its item count within the global limit" );
	foreach ( $schema['fields'] as $field => $definition ) {
		check( '' !== (string) ( $definition['label'] ?? '' ), "{$key}.{$field} has a label" );
		check( in_array( $definition['type'], array( 'text', 'textarea', 'image', 'rating' ), true ), "{$key}.{$field} has a known type" );
	}
}

// The whole point of the gallery repeater is alt text the old slots lacked.
check( isset( $schemas['gallery']['fields']['alt'] ), 'gallery items carry alt text' );

echo "\n--- 2. The sanitizer rejects hostile input ---\n";

$hostile_images = array(
	'javascript:alert(1)',
	'data:text/html;base64,PHNjcmlwdD4=',
	'vbscript:msgbox(1)',
	'  javascript:alert(1)',
);
foreach ( $hostile_images as $payload ) {
	check(
		'' === Repeater::sanitize_field( $payload, 'image' ),
		'the image field rejects ' . substr( $payload, 0, 34 )
	);
}
check( 'https://example.com/a.jpg' === Repeater::sanitize_field( 'https://example.com/a.jpg', 'image' ), 'a normal https image is accepted' );
check( 'https://evil.example/x.png' === Repeater::sanitize_field( '//evil.example/x.png', 'image' ), 'a protocol-relative URL is pinned to https rather than rejected' );
check( '/wp-content/uploads/a.jpg' === Repeater::sanitize_field( '/wp-content/uploads/a.jpg', 'image' ), 'a site-relative image is accepted' );

check( '5' === Repeater::sanitize_field( '99', 'rating' ), 'a rating above 5 is clamped to 5' );
check( '0' === Repeater::sanitize_field( '-3', 'rating' ), 'a negative rating is clamped to 0' );
check( '' === Repeater::sanitize_field( array( 'x' ), 'text' ), 'an array in a text field is rejected' );
check( '' === Repeater::sanitize_field( array( 'x' ), 'image' ), 'an array in an image field is rejected' );

echo "\n--- 3. Whole-value sanitizing is bounded and shaped ---\n";

$valid = array( array( 'image' => 'https://example.com/a.jpg', 'alt' => 'نمای سالن' ) );
check( 1 === count( Repeater::sanitize( $valid, 'gallery' ) ), 'a valid item survives' );
check( 'نمای سالن' === Repeater::sanitize( $valid, 'gallery' )[0]['alt'], 'Persian alt text survives sanitizing' );

check( array() === Repeater::sanitize( 'not json', 'gallery' ), 'a non-JSON string becomes an empty list' );
check( array() === Repeater::sanitize( 'null', 'gallery' ), 'JSON null becomes an empty list' );
check( array() === Repeater::sanitize( '[{"nope":1}]', 'gallery' ), 'a list of unusable items becomes empty' );

// An unknown key must be dropped, not carried through to the page.
$with_extra = Repeater::sanitize( array( array( 'image' => 'https://e.com/a.jpg', 'alt' => 'x', 'evil' => '<script>' ) ), 'gallery' );
check( ! array_key_exists( 'evil', $with_extra[0] ), 'an unknown field is dropped rather than kept' );
check( false === strpos( (string) wp_json_encode( $with_extra ), '<script>' ), 'no markup survives a round trip' );

$max = Repeater::schema( 'gallery' )['max'];
$many = array();
for ( $i = 0; $i < $max + 10; $i++ ) {
	$many[] = array( 'image' => 'https://e.com/' . $i . '.jpg', 'alt' => 'x' );
}
check( count( Repeater::sanitize( $many, 'gallery' ) ) === $max, 'the item count is capped at the schema limit (' . $max . ')' );

echo "\n--- 4. Migration keeps an existing site's photos ---\n";

// A site that used the old six slots must not lose them.
for ( $slot = 1; $slot <= 6; $slot++ ) {
	remove_theme_mod( 'flavor_gallery_image_' . $slot );
}
remove_theme_mod( 'flavor_gallery_items' );

check( empty( Repeater::migrate( 'gallery' ) ), 'with no legacy slots there is nothing to migrate' );

set_theme_mod( 'flavor_gallery_image_1', 'https://example.com/one.jpg' );
set_theme_mod( 'flavor_gallery_image_3', 'https://example.com/three.jpg' );

$migrated = Repeater::migrate( 'gallery' );
check( 2 === count( $migrated ), 'only the slots that were actually set are migrated' );
check( 'https://example.com/one.jpg' === $migrated[0]['image'], 'the first set slot migrates in order' );
check( 'https://example.com/three.jpg' === $migrated[1]['image'], 'a gap in the slots does not break the order' );
check( '' !== $migrated[0]['alt'], 'a migrated image gets alt text rather than none' );

check( 'https://example.com/one.jpg' === Repeater::items( 'gallery' )[0]['image'], 'items() serves migrated photos when the repeater is empty' );

echo "\n--- 5. Fallback keeps an untouched site looking the same ---\n";

for ( $slot = 1; $slot <= 6; $slot++ ) {
	remove_theme_mod( 'flavor_gallery_image_' . $slot );
}
remove_theme_mod( 'flavor_gallery_items' );

$gallery = Repeater::items( 'gallery' );
check( 6 === count( $gallery ), 'an untouched gallery still shows six images' );
check( false !== strpos( $gallery[0]['image'], '/demos/' ), 'the first fallback image is the active skin demo art' );
foreach ( $gallery as $index => $item ) {
	check( '' !== $item['image'], "fallback item {$index} has an image" );
	check( '' !== $item['alt'], "fallback item {$index} has alt text" );
}

$testimonials = Repeater::items( 'testimonials' );
check( 3 === count( $testimonials ), 'an untouched testimonials section still shows three reviews' );
foreach ( $testimonials as $index => $item ) {
	check( '' !== $item['name'], "fallback review {$index} has a name" );
	check( '' !== $item['text'], "fallback review {$index} has text" );
	check( '5' === (string) $item['rating'], "fallback review {$index} is rated" );
}

echo "\n--- 6. A saved list wins over migration and fallback ---\n";

set_theme_mod(
	'flavor_gallery_items',
	array( array( 'image' => 'https://example.com/mine.jpg', 'alt' => 'میز من' ) )
);
check( 1 === count( Repeater::items( 'gallery' ) ), 'the merchant\'s own list is what renders' );
check( 'https://example.com/mine.jpg' === Repeater::items( 'gallery' )[0]['image'], 'their image wins over the migrated slots' );

remove_theme_mod( 'flavor_gallery_items' );

echo "\n--- 7. Row markup is complete, escaped and actionable ---\n";

$fields = Repeater::schema( 'gallery' )['fields'];
$row    = Repeater_Control::row( array( 'image' => 'https://e.com/a.jpg', 'alt' => 'x' ), $fields, 1 );

foreach ( array( 'flavor-repeater__up', 'flavor-repeater__down', 'flavor-repeater__duplicate', 'flavor-repeater__remove' ) as $action ) {
	check( false !== strpos( $row, $action ), "each row offers {$action}" );
}
check( false !== strpos( $row, 'data-field="image"' ), 'the image input is bound to its field' );
check( false !== strpos( $row, 'data-field="alt"' ), 'the alt input is bound to its field' );

$hostile_row = Repeater_Control::row( array( 'image' => 'https://e.com/a.jpg', 'alt' => '"><script>alert(1)</script>' ), $fields, 2 );
check( false === strpos( $hostile_row, '<script>' ), 'a script payload in a value is escaped' );
check( false !== strpos( $hostile_row, '&#8221;') || false !== strpos( $hostile_row, '&quot;' ) || false !== strpos( $hostile_row, '&gt;' ), 'the quote and bracket are encoded' );

$testimonial_row = Repeater_Control::row(
	array( 'name' => 'سارا', 'role' => 'مهمان', 'rating' => '4', 'text' => 'عالی بود' ),
	Repeater::schema( 'testimonials' )['fields'],
	1
);
check( false !== strpos( $testimonial_row, 'value="4" selected' ), 'the saved rating is selected in the dropdown' );
check( false !== strpos( $testimonial_row, 'سارا' ), 'the saved name appears in the row' );

$field = Repeater_Control::field( 'flavor_gallery_items', '[{"image":"x"}]', 'gallery' );
check( false !== strpos( $field, 'data-schema="gallery"' ), 'the hidden field records its schema' );
check( false !== strpos( $field, 'type="hidden"' ), 'the value is carried in a hidden input' );

echo "\n--- 8. The JS covers the actions the rows advertise ---\n";

$js = (string) file_get_contents( FLAVOR_DIR . '/assets/js/customizer-repeater.js' );

foreach ( array( 'flavor-repeater__remove', 'flavor-repeater__duplicate', 'flavor-repeater__up', 'flavor-repeater__down', 'flavor-repeater__add' ) as $action ) {
	check( false !== strpos( $js, $action ), "the script handles {$action}" );
}
check( false !== strpos( $js, 'JSON.stringify' ), 'the script serialises to JSON' );
check( false !== strpos( $js, 'setting.set(' ), 'the script writes through the Customizer setting API' );
check( false !== strpos( $js, 'confirm(' ), 'deleting asks for confirmation' );

// Reordering must move DOM nodes, not keep an index in the data: an index
// column is how two items end up claiming the same position after a delete.
check( false !== strpos( $js, 'insertBefore' ), 'reordering moves nodes rather than renumbering data' );
check( false === strpos( $js, 'dataset.position =' ) || false !== strpos( $js, 'renumber' ), 'position is derived from DOM order' );

echo "\n--- 9. The stylesheet covers the classes the PHP prints ---\n";

$css = (string) file_get_contents( FLAVOR_DIR . '/assets/css/customizer-presets.css' );
// The hidden value input is deliberately unstyled, so only the visible
// row is scanned for classes that need a rule.
preg_match_all( '/class="(flavor-repeater__[a-z-]+)"/', $row, $used );
foreach ( array_unique( $used[1] ) as $class ) {
	check( false !== strpos( $css, '.' . $class ), ".{$class} is styled" );
}

echo "\n--- 10. The templates consume the repeater ---\n";

$gallery_tpl = (string) file_get_contents( FLAVOR_DIR . '/template-parts/marketing/gallery.php' );
check( false !== strpos( $gallery_tpl, 'Repeater::items' ), 'the gallery template reads from the repeater' );
check( false !== strpos( $gallery_tpl, 'Repeater::image_alt' ), 'the gallery template uses each image\'s own alt text' );
check( false === strpos( $gallery_tpl, '$fallbacks' ), 'the old hard-coded fallback list is gone' );

$testimonial_tpl = (string) file_get_contents( FLAVOR_DIR . '/template-parts/marketing/testimonials.php' );
check( false !== strpos( $testimonial_tpl, 'Repeater::items' ), 'the testimonials template reads from the repeater' );
check( false === strpos( $testimonial_tpl, "'سارا محمدی'" ), 'the hard-coded review content is gone' );

echo "\n=======================================================\n";
echo "Results: {$passed} Passed, {$failed} Failed\n";
echo "=======================================================\n";

exit( $failed > 0 ? 1 : 0 );
