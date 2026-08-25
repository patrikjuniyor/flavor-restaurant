<?php
/**
 * Flavor theme bootstrap.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

define( 'FLAVOR_VERSION', '0.3.0' );
define( 'FLAVOR_DIR', get_template_directory() );
define( 'FLAVOR_URI', get_template_directory_uri() );

require_once FLAVOR_DIR . '/inc/class-theme-setup.php';
require_once FLAVOR_DIR . '/inc/class-enqueue.php';
require_once FLAVOR_DIR . '/inc/class-schema-output.php';
require_once FLAVOR_DIR . '/inc/template-tags.php';

Flavor\Theme_Setup::init();
Flavor\Enqueue::init();
Flavor\Schema_Output::init();
