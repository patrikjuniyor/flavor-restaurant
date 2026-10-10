<?php
/**
 * Stats widget: a row of figures such as years open, dishes, branches.
 *
 * The figures are typed by the merchant and shown as written. Nothing is
 * counted or computed here, so a stat can never drift from what the
 * merchant decided to claim.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Stats_Widget
 */
class Stats_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_stats';
	}

	public function get_title() {
		return __( 'آمار رستوران', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-counter';
	}

	public function get_keywords() {
		return array( 'stats', 'آمار', 'عدد', 'counter', 'restaurant' );
	}

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'محتوا', 'flavor' ) ) );

		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'figure', array( 'label' => __( 'عدد یا متن کوتاه', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );
		$repeater->add_control( 'label', array( 'label' => __( 'برچسب', 'flavor' ), 'type' => \Elementor\Controls_Manager::TEXT ) );

		$this->add_control(
			'items',
			array(
				'label'   => __( 'آمارها', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::REPEATER,
				'fields'  => $repeater->get_controls(),
				'default' => array(
					array(
						'figure' => '۱۰+',
						'label'  => __( 'سال تجربه', 'flavor' ),
					),
					array(
						'figure' => '۵۰+',
						'label'  => __( 'نوع غذا', 'flavor' ),
					),
					array(
						'figure' => '۲۰۰۰+',
						'label'  => __( 'مشتری راضی', 'flavor' ),
					),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s     = $this->get_settings_for_display();
		$items = self::rows( $s, 'items' );

		// Always print the section shell, even with no figures, so the
		// widget's markup stays well formed like every other widget.
		$inner = '<dl class="flavor-stats">';

		foreach ( $items as $item ) {
			$inner .= '<div class="flavor-stat">';
			$inner .= '<dt class="flavor-stat__label">' . esc_html( (string) ( $item['label'] ?? '' ) ) . '</dt>';
			$inner .= '<dd class="flavor-stat__figure">' . esc_html( (string) ( $item['figure'] ?? '' ) ) . '</dd>';
			$inner .= '</div>';
		}

		$inner .= '</dl>';

		echo self::section( __( 'آمار', 'flavor' ), $inner, 'flavor-section--stats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	}
}
