<?php
/**
 * Branch info widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Branch_Info_Widget
 */
class Branch_Info_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_branch_info';
	}

	public function get_title() {
		return __( 'اطلاعات شعبه', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-map-pin';
	}

	protected function render() {
		if ( ! class_exists( '\\FlavorCore\\PostTypes\\BranchPostType' ) ) {
			echo '<p>' . esc_html__( 'افزونه Flavor Core لازم است.', 'flavor' ) . '</p>';
			return;
		}
		$id = \FlavorCore\PostTypes\BranchPostType::default_id();
		$row = $id ? \FlavorCore\PostTypes\BranchPostType::to_array( $id ) : null;
		if ( ! $row ) {
			return;
		}
		echo '<section class="flavor-container"><h2>' . esc_html( $row['name'] ) . '</h2>';
		echo '<p>' . esc_html( $row['address'] ) . '</p>';
		echo '<p dir="ltr">' . esc_html( $row['phone'] ) . '</p></section>';
	}
}
