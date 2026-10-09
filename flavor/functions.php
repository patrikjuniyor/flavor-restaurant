<?php
/**
 * Flavor theme bootstrap.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

define( 'FLAVOR_VERSION', '1.6.1' );
define( 'FLAVOR_DIR', get_template_directory() );
define( 'FLAVOR_URI', get_template_directory_uri() );

require_once FLAVOR_DIR . '/inc/template-tags.php';
require_once FLAVOR_DIR . '/inc/class-block-patterns.php';
require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-design.php';
require_once FLAVOR_DIR . '/inc/class-bespoke-demos.php';
require_once FLAVOR_DIR . '/inc/class-ui.php';
require_once FLAVOR_DIR . '/inc/class-customer-ui.php';
require_once FLAVOR_DIR . '/inc/class-ui-pages.php';
require_once FLAVOR_DIR . '/inc/class-customizer.php';
require_once FLAVOR_DIR . '/inc/class-contrast.php';
require_once FLAVOR_DIR . '/inc/class-repeater.php';
require_once FLAVOR_DIR . '/inc/class-live-preview.php';
require_once FLAVOR_DIR . '/inc/class-ui-customizer.php';
require_once FLAVOR_DIR . '/inc/class-chrome-builder.php';
require_once FLAVOR_DIR . '/inc/class-onboarding.php';
require_once FLAVOR_DIR . '/inc/class-launch-center.php';
require_once FLAVOR_DIR . '/inc/class-changelog.php';
require_once FLAVOR_DIR . '/inc/class-transfer.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-schema-output.php';
require_once FLAVOR_DIR . '/inc/class-page-options.php';
require_once FLAVOR_DIR . '/inc/class-meta.php';
require_once FLAVOR_DIR . '/inc/class-pwa.php';
require_once FLAVOR_DIR . '/inc/class-demo-importer.php';
require_once FLAVOR_DIR . '/inc/class-gutenberg.php';
require_once FLAVOR_DIR . '/inc/class-elementor.php';
require_once FLAVOR_DIR . '/inc/class-builder.php';
require_once FLAVOR_DIR . '/inc/class-dark-mode.php';
require_once FLAVOR_DIR . '/inc/class-wishlist.php';
require_once FLAVOR_DIR . '/inc/class-nav-mega.php';
require_once FLAVOR_DIR . '/inc/class-shopping-extras.php';
require_once FLAVOR_DIR . '/inc/class-engagement.php';
require_once FLAVOR_DIR . '/inc/class-floating-dock.php';
require_once FLAVOR_DIR . '/inc/class-branch-map.php';
require_once FLAVOR_DIR . '/inc/class-premium-customizer.php';
require_once FLAVOR_DIR . '/inc/class-premium-assets.php';

Flavor\Theme_Setup::init();
Flavor\Bespoke_Demos::init();
Flavor\UI::init();
Flavor\Customer_UI::init();
Flavor\UI_Pages::init();
Flavor\Customizer::init();
Flavor\UI_Customizer::init();
Flavor\Chrome_Builder::init();
Flavor\Onboarding::init();
Flavor\Launch_Center::init();
Flavor\Changelog::init();
Flavor\Transfer::init();
Flavor\Enqueue::init();
Flavor\Schema_Output::init();
Flavor\Meta::init();
Flavor\PWA::init();
Flavor\Demo_Importer::init();
Flavor\Gutenberg::init();
Flavor\Block_Patterns::init();
Flavor\Page_Options::init();
Flavor\Elementor::init();
Flavor\Builder::init();
Flavor\Dark_Mode::init();
Flavor\Wishlist::init();
Flavor\Nav_Mega::init();
Flavor\Shopping_Extras::init();
Flavor\Engagement::init();
Flavor\Floating_Dock::init();
Flavor\Premium_Customizer::init();
Flavor\Premium_Assets::init();
