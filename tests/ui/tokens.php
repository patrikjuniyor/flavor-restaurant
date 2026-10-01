<?php
/** Non-destructive UI token and enum checks: php tests/ui/tokens.php */
define( 'ABSPATH', __DIR__ . '/' );
function __( $value, $domain = '' ) { return $value; }
$GLOBALS['ui_test_mods'] = array();
function get_theme_mod( $key, $default = false ) { return $GLOBALS['ui_test_mods'][ $key ] ?? $default; }
require dirname( __DIR__, 2 ) . '/flavor/inc/class-design.php';
require dirname( __DIR__, 2 ) . '/flavor/inc/class-ui.php';
function ui_check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
foreach ( \Flavor\Design::skins() as $slug => $skin ) {
 $t = \Flavor\Design::tokens( $slug );
 $ink = \Flavor\UI::contrast( $t['primary'], '#ffffff' ) >= \Flavor\UI::contrast( $t['primary'], '#000000' ) ? '#ffffff' : '#000000';
 ui_check( \Flavor\UI::contrast( $t['primary'], $ink ) >= 4.5, 'Button contrast: ' . $slug );
 $muted = \Flavor\UI::contrast( $t['muted'], $t['surface'] ) >= 4.5 && \Flavor\UI::contrast( $t['muted'], $t['bg'] ) >= 4.5 ? $t['muted'] : $t['ink'];
 ui_check( \Flavor\UI::contrast( $muted, $t['surface'] ) >= 4.5 && \Flavor\UI::contrast( $muted, $t['bg'] ) >= 4.5, 'Secondary-text contrast: ' . $slug );
 echo 'PASS UI tokens ' . $slug . PHP_EOL;
}
ui_check( \Flavor\UI::luminance( '#fff' ) === \Flavor\UI::luminance( '#ffffff' ), 'Short hex normalization.' );
ui_check( \Flavor\UI::luminance( 'invalid' ) === 0.0, 'Invalid color guard.' );
$GLOBALS['ui_test_mods'] = array( 'flavor_ui_menu_layout' => 'list', 'flavor_ui_density' => 'compact', 'flavor_ui_image_ratio' => 'square', 'flavor_ui_card_style' => 'sharp' );
ui_check( 'list' === \Flavor\UI::settings()['menu_layout'], 'Valid menu setting ignored.' );
$GLOBALS['ui_test_mods']['flavor_ui_menu_layout'] = 'grid; background:url(javascript:bad)';
ui_check( 'grid' === \Flavor\UI::settings()['menu_layout'], 'Unsafe setting not rejected.' );
echo 'PASS UI enum allowlists and color guards.' . PHP_EOL;
