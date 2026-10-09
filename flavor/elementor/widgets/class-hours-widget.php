<?php
/**
 * Opening hours widget.
 *
 * @package Flavor
 */

namespace Flavor\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Hours_Widget
 */
class Hours_Widget extends Widget_Base {

	public function get_name() {
		return 'flavor_hours';
	}

	public function get_title() {
		return __( 'ساعات کاری', 'flavor' );
	}

	public function get_icon() {
		return 'eicon-clock-o';
	}

	public function get_keywords() {
		return array( 'hours', 'ساعات', 'زمان', 'opening', 'کاری' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'content',
			array( 'label' => __( 'محتوا', 'flavor' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'عنوان', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'ساعات کاری', 'flavor' ),
			)
		);

		// A repeater rather than a fixed number of slots: a restaurant that is
		// closed on Fridays should not have to leave a blank row behind.
		$repeater = new \Elementor\Repeater();

		$repeater->add_control(
			'day',
			array(
				'label'   => __( 'روز', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'شنبه تا پنج‌شنبه', 'flavor' ),
			)
		);

		$repeater->add_control(
			'hours',
			array(
				'label'   => __( 'ساعت', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( '۱۱:۳۰ تا ۲۳:۴۵', 'flavor' ),
			)
		);

		$repeater->add_control(
			'closed',
			array(
				'label'        => __( 'تعطیل', 'flavor' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'rows',
			array(
				'label'       => __( 'ردیف‌ها', 'flavor' ),
				'type'        => \Elementor\Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'day'   => __( 'شنبه تا پنج‌شنبه', 'flavor' ),
						'hours' => __( '۱۱:۳۰ تا ۲۳:۴۵', 'flavor' ),
					),
					array(
						'day'   => __( 'جمعه', 'flavor' ),
						'hours' => __( '۱۳:۰۰ تا ۲۳:۴۵', 'flavor' ),
					),
				),
				'title_field' => '{{{ day }}}',
			)
		);

		$this->add_control(
			'note',
			array(
				'label'   => __( 'یادداشت', 'flavor' ),
				'type'    => \Elementor\Controls_Manager::TEXTAREA,
				'default' => '',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$rows = (array) ( $s['rows'] ?? array() );

		$title = self::text( $s, 'title', __( 'ساعات کاری', 'flavor' ) );

		$inner = '<h2 class="flavor-section__title">' . esc_html( $title ) . '</h2>';
		$inner .= '<dl class="flavor-hours-list">';

		if ( empty( $rows ) ) {
			$inner .= '<p class="flavor-widget-note">' . esc_html__( 'هیچ ساعتی ثبت نشده است.', 'flavor' ) . '</p>';
		}

		foreach ( $rows as $row ) {
			$day    = self::text( (array) $row, 'day' );
			$hours  = self::text( (array) $row, 'hours' );
			$closed = 'yes' === ( $row['closed'] ?? '' );

			if ( '' === $day && '' === $hours ) {
				continue;
			}

			$value = $closed
				? '<span class="flavor-hours-list__closed">' . esc_html__( 'تعطیل', 'flavor' ) . '</span>'
				: esc_html( $hours );

			$inner .= '<div class="flavor-hours-list__row">';
			$inner .= '<dt>' . esc_html( $day ) . '</dt>';
			$inner .= '<dd>' . $value . '</dd>';
			$inner .= '</div>';
		}

		$inner .= '</dl>';

		$note = self::text( $s, 'note' );
		if ( '' !== $note ) {
			$inner .= '<p class="flavor-hours-list__note">' . esc_html( $note ) . '</p>';
		}

		echo self::section( $title, $inner, 'flavor-section--hours' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
