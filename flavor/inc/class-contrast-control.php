<?php
/**
 * Customizer notice for unreadable colour combinations.
 *
 * UI::variables() corrects a failing colour pair in silence. This control
 * says out loud what it corrected, while the merchant still has the colour
 * picker open.
 *
 * It is information, never a gate: nothing here refuses a save or rewrites
 * a value. A restaurant may legitimately need a low-contrast accent for a
 * decorative band, and a control that blocks saving would be worked around
 * rather than listened to.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Contrast_Control
 */
class Contrast_Control extends \WP_Customize_Control {

	/**
	 * Control type, matched by customizer-contrast.js.
	 *
	 * @var string
	 */
	public $type = 'flavor_contrast';

	/**
	 * Build the report markup.
	 *
	 * Static so it can be tested without a Customizer instance.
	 *
	 * @param array<string, string> $tokens Resolved design tokens.
	 * @return string
	 */
	public static function render_report( array $tokens ): string {
		$rows = Contrast::report( $tokens );

		if ( empty( $rows ) ) {
			return '<p class="flavor-contrast__empty">'
				. esc_html__( 'رنگ‌هایی که وارد کنید اینجا سنجیده می‌شوند.', 'flavor' )
				. '</p>';
		}

		$html = '<div class="flavor-contrast" role="status">';

		foreach ( $rows as $key => $row ) {
			// A live sample is worth more than the number: the merchant can
			// see the failure instead of trusting a ratio they cannot picture.
			$sample = sprintf(
				'<span class="flavor-contrast__sample" style="color:%1$s;background:%2$s">%3$s</span>',
				esc_attr( $row['fg'] ),
				esc_attr( $row['bg'] ),
				esc_html__( 'نمونه', 'flavor' )
			);

			$html .= sprintf(
				'<div class="flavor-contrast__row flavor-contrast__row--%1$s" data-pair="%2$s">' .
					'<span class="flavor-contrast__label">%3$s</span>' .
					'<span class="flavor-contrast__value"><span class="flavor-contrast__ratio">%4$s</span> / %5$s</span>' .
					'%6$s' .
				'</div>',
				$row['pass'] ? 'pass' : 'fail',
				esc_attr( $key ),
				esc_html( $row['label'] ),
				esc_html( number_format_i18n( $row['ratio'], 2 ) ),
				esc_html( number_format_i18n( $row['min'], 1 ) ),
				$sample // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above.
			);
		}

		$failing = Contrast::failing( $tokens );

		if ( ! empty( $failing ) ) {
			$html .= '<p class="flavor-contrast__warn">'
				. esc_html__( 'قالب رنگ‌های بی‌خوانا را خودکار اصلاح می‌کند، اما بهتر است خودتان ترکیب خواناتری انتخاب کنید.', 'flavor' )
				. '</p>';
		} else {
			$html .= '<p class="flavor-contrast__ok">'
				. esc_html__( 'همهٔ ترکیب‌ها از حداقل استاندارد WCAG AA بالاترند.', 'flavor' )
				. '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * Render the control.
	 *
	 * The initial markup is rendered from the saved tokens; the JS re-renders
	 * it as the merchant changes colours, without a preview refresh.
	 *
	 * @return void
	 */
	public function render_content(): void {
		printf( '<div class="flavor-contrast-wrap" data-contrast>' );

		if ( ! empty( $this->label ) ) {
			printf( '<span class="customize-control-title">%s</span>', esc_html( $this->label ) );
		}
		if ( ! empty( $this->description ) ) {
			printf( '<span class="description customize-control-description">%s</span>', esc_html( $this->description ) );
		}

		echo self::render_report( Design::resolved() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value escaped in render_report().

		echo '</div>';
	}

	/**
	 * Hand the server-rendered markup to the Customizer.
	 *
	 * Customizer builds custom controls from a JS template, not from
	 * render_content(). Without this the template is empty and the control
	 * renders nothing.
	 *
	 * @return void
	 */
	public function to_json(): void {
		parent::to_json();

		ob_start();
		$this->render_content();
		$this->json['html'] = (string) ob_get_clean();
	}

	/**
	 * The JS template: the markup computed in to_json().
	 *
	 * @return void
	 */
	protected function content_template() {
		echo '{{{ data.html }}}';
	}
}
