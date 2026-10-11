<?php
/**
 * Elementor registration and front-page rendering regression tests.
 *
 * These checks exercise the same hook order Elementor uses: register the
 * category first, then register the theme widgets. They also cover pages whose
 * Elementor layout exists only in post meta while post_content is empty.
 *
 *   php tests/theme/test-elementor-registration.php
 *
 * @package Flavor
 */

namespace Flavor\Tests;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/elementor-stubs.php';
require_once FLAVOR_DIR . '/inc/class-elementor.php';

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

echo "Elementor registration and front-page rendering\n";

check(
	! class_exists( \Flavor\Elementor\Widget_Base::class, false ),
	'the Flavor widget base starts unloaded'
);

// Fire category registration before widgets/register to catch load-order issues.
\Flavor\Elementor::init();
$elements = new \Elementor\Elements_Manager();
do_action( 'elementor/elements/categories_registered', $elements );

check(
	class_exists( \Flavor\Elementor\Widget_Base::class, false ),
	'the category hook loads the Flavor widget base before using it'
);
check(
	isset( $elements->get_categories()['flavor'] ),
	'the Flavor category is registered even when it runs before widget registration'
);

$widgets = new \Elementor\Widgets_Manager();
do_action( 'elementor/widgets/register', $widgets );

check(
	17 === count( $widgets->widgets ),
	'all 17 Flavor widgets register through Elementor’s public hook'
);
check(
	'flavor_hero' === $widgets->widgets[0]->get_name(),
	'dynamic widget class names resolve to the actual Flavor classes'
);

// Elementor's layout can be saved in _elementor_data while post_content is blank.
$post_id = 9201;
update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
update_post_meta(
	$post_id,
	'_elementor_data',
	(string) json_encode( array( array( 'id' => 'abc1234', 'elType' => 'section', 'elements' => array() ) ) )
);
check(
	\Flavor\Elementor::has_builder_content( $post_id ),
	'non-empty Elementor metadata counts as front-page content'
);

update_post_meta( $post_id, '_elementor_data', '[]' );
check(
	! \Flavor\Elementor::has_builder_content( $post_id ),
	'an empty Elementor document does not create a blank content wrapper'
);

update_post_meta( $post_id, '_elementor_edit_mode', 'classic' );
update_post_meta( $post_id, '_elementor_data', '[{"id":"abc1234"}]' );
check(
	! \Flavor\Elementor::has_builder_content( $post_id ),
	'stale Elementor data is ignored when the post is not in builder mode'
);

echo "\nResults: {$passed} Passed, {$failed} Failed\n";
exit( $failed > 0 ? 1 : 0 );
