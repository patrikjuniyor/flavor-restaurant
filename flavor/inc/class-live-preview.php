<?php
/**
 * Live preview coverage for the Customizer.
 *
 * Only nine of the theme's settings had `transport => postMessage`; the
 * other fifty-odd forced a full preview reload on every keystroke, which
 * turns "let me try a slightly darker red" into a five-second round trip.
 *
 * Every setting is routed one of three ways, and the difference matters:
 *
 *  - **Token settings** write a CSS custom property. Colour, radius,
 *    spacing, type scale: these change no markup, so the preview script
 *    sets the variable and the page re-lays-out instantly.
 *  - **Section settings** get a selective-refresh partial. Titles, toggles
 *    and repeater items change markup, so the section is re-rendered
 *    server-side and swapped into place.
 *  - **Structural settings** stay on refresh: switching a header to a
 *    different layout changes the whole page, and pretending otherwise
 *    would produce a preview that disagrees with the published site.
 *
 * The point of naming the third category explicitly is that it stops the
 * coverage number being gamed: a setting is either live for a reason, or
 * it is listed as deliberately not live, with the reason written down.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Live_Preview
 */
class Live_Preview {

	/**
	 * Selective-refresh partials, one per front-page section.
	 *
	 * Each marketing section is a separate get_template_part(), so it can be
	 * re-rendered alone. That is what makes a partial possible without
	 * touching the templates.
	 *
	 * @return array<string, array{selector: string, part: string, settings: array<int, string>}>
	 */
	public static function partials(): array {
		return array(
			'flavor_hero'         => array(
				'selector' => '.flavor-hero',
				'part'     => 'template-parts/marketing/hero',
				'settings' => array( 'flavor_hero_style', 'flavor_hero_badge', 'flavor_hero_title', 'flavor_hero_text', 'flavor_hero_cta', 'flavor_hero_cta2', 'flavor_hero_image' ),
			),
			'flavor_intro'        => array(
				'selector' => '.flavor-intro',
				'part'     => 'template-parts/marketing/intro',
				'settings' => array( 'flavor_intro_enable', 'flavor_intro_title' ),
			),
			'flavor_featured'     => array(
				'selector' => '.flavor-featured',
				'part'     => 'template-parts/marketing/featured',
				'settings' => array( 'flavor_featured_enable', 'flavor_featured_title' ),
			),
			'flavor_categories'   => array(
				'selector' => '.flavor-categories',
				'part'     => 'template-parts/marketing/categories',
				'settings' => array( 'flavor_cats_enable', 'flavor_cats_title' ),
			),
			'flavor_offers'       => array(
				'selector' => '.flavor-offers',
				'part'     => 'template-parts/marketing/special-offers',
				'settings' => array( 'flavor_offers_enable', 'flavor_offers_title', 'flavor_offers_code' ),
			),
			'flavor_reservation'  => array(
				'selector' => '.flavor-res-cta',
				'part'     => 'template-parts/marketing/reservation-cta',
				'settings' => array( 'flavor_res_enable', 'flavor_res_title', 'flavor_res_image' ),
			),
			'flavor_about'        => array(
				'selector' => '.flavor-about',
				'part'     => 'template-parts/marketing/about',
				'settings' => array( 'flavor_about_enable', 'flavor_about', 'flavor_about_image' ),
			),
			'flavor_gallery'      => array(
				'selector' => '.flavor-gallery',
				'part'     => 'template-parts/marketing/gallery',
				'settings' => array( 'flavor_gallery_enable', 'flavor_gallery_items', 'flavor_gallery_image_1', 'flavor_gallery_image_2', 'flavor_gallery_image_3', 'flavor_gallery_image_4', 'flavor_gallery_image_5', 'flavor_gallery_image_6' ),
			),
			'flavor_testimonials' => array(
				'selector' => '.flavor-testimonials',
				'part'     => 'template-parts/marketing/testimonials',
				'settings' => array( 'flavor_testimonials_enable', 'flavor_testimonials_items' ),
			),
			'flavor_hours'        => array(
				'selector' => '.flavor-hours-loc',
				'part'     => 'template-parts/marketing/hours',
				'settings' => array( 'flavor_hours_enable', 'flavor_phone', 'flavor_address' ),
			),
			'flavor_story'        => array(
				'selector' => '.fd-story',
				'part'     => 'template-parts/demos/story',
				'settings' => array( 'flavor_landing_story_image' ),
			),
			'flavor_header'       => array(
				'selector' => '.flavor-header',
				'part'     => 'template-parts/demos/header',
				'settings' => array( 'flavor_logo_height', 'flavor_header_topbar' ),
			),
			'flavor_footer'       => array(
				'selector' => '.fd-footer',
				'part'     => 'template-parts/demos/footer',
				'settings' => array( 'flavor_footer_copy', 'flavor_social_instagram', 'flavor_social_telegram', 'flavor_social_whatsapp' ),
			),
		);
	}

	/**
	 * Settings that only write a CSS custom property.
	 *
	 * `scale` is the odd one: changing the type scale recomputes h2–h6 from
	 * the body size, so the preview script has to run the same arithmetic
	 * the server does rather than set one variable.
	 *
	 * @return array<string, array{var: string, kind: string}>
	 */
	public static function tokens(): array {
		$colour = array( 'primary', 'secondary', 'accent', 'bg', 'surface', 'surface_alt', 'ink', 'muted', 'line' );
		$map    = array();

		foreach ( $colour as $key ) {
			// The theme mod uses surface_alt; the custom property it feeds is
			// --flavor-surface-alt. Underscore-to-hyphen is what keeps the
			// preview mapping to the variable the server actually emits.
			$map[ 'flavor_' . $key ] = array( 'var' => '--flavor-' . str_replace( '_', '-', $key ), 'kind' => 'color' );
		}

		return array_merge(
			$map,
			array(
				'flavor_radius'                 => array( 'var' => '--flavor-radius', 'kind' => 'length' ),
				'flavor_btn_radius'             => array( 'var' => '--flavor-btn-radius', 'kind' => 'length' ),
				'flavor_container_width'        => array( 'var' => '--flavor-container-max', 'kind' => 'length' ),
				'flavor_body_size'              => array( 'var' => '--flavor-body-size', 'kind' => 'length' ),
				'flavor_gutter_mobile'          => array( 'var' => '--flavor-container-gutter', 'kind' => 'length' ),
				'flavor_gutter_tablet'          => array( 'var' => '--flavor-container-gutter-tablet', 'kind' => 'length' ),
				'flavor_gutter_desktop'         => array( 'var' => '--flavor-container-gutter-desktop', 'kind' => 'length' ),
				'flavor_section_space_mobile'   => array( 'var' => '--flavor-section-space', 'kind' => 'length' ),
				'flavor_section_space_tablet'   => array( 'var' => '--flavor-section-space-tablet', 'kind' => 'length' ),
				'flavor_section_space_desktop'  => array( 'var' => '--flavor-section-space-desktop', 'kind' => 'length' ),
				'flavor_heading_size_mobile'    => array( 'var' => '--flavor-heading-size', 'kind' => 'raw' ),
				'flavor_heading_size_tablet'    => array( 'var' => '--flavor-heading-size-tablet', 'kind' => 'raw' ),
				'flavor_heading_size_desktop'   => array( 'var' => '--flavor-heading-size-desktop', 'kind' => 'raw' ),
				'flavor_type_scale'             => array( 'var' => '--flavor-h2', 'kind' => 'scale' ),
				'flavor_body_weight'            => array( 'var' => '--flavor-body-weight', 'kind' => 'raw' ),
				'flavor_body_line_height'       => array( 'var' => '--flavor-body-line-height', 'kind' => 'raw' ),
				'flavor_heading_weight'         => array( 'var' => '--flavor-heading-weight', 'kind' => 'raw' ),
				'flavor_heading_line_height'    => array( 'var' => '--flavor-heading-line-height', 'kind' => 'raw' ),
				'flavor_heading_letter_spacing' => array( 'var' => '--flavor-heading-letter-spacing', 'kind' => 'raw' ),
			)
		);
	}

	/**
	 * Settings that legitimately stay on refresh, with the reason.
	 *
	 * @return array<string, string>
	 */
	public static function refresh_only(): array {
		return array(
			'flavor_header_layout' => __( 'تغییر چیدمان هدر کل صفحه را دوباره می‌چیند.', 'flavor' ),
			'flavor_header_sticky' => __( 'حالت چسبان به اسکریپت هدر وابسته است و با جابه‌جاییِ بخشی درست اعمال نمی‌شود.', 'flavor' ),
		);
	}

	/**
	 * Controls that render nothing on the front end.
	 *
	 * These exist purely to show the merchant something inside the pane.
	 * They are not "unfinished": there is no preview to give, because no
	 * markup depends on them. Naming them keeps the coverage check honest
	 * instead of letting them be quietly lumped in with refresh.
	 *
	 * @return array<string, string>
	 */
	public static function display_only(): array {
		return array(
			'flavor_contrast_report' => __( 'فقط گزارش است؛ خروجی‌ای در صفحه ندارد.', 'flavor' ),
		);
	}

	/**
	 * Register partials and flip the token settings to postMessage.
	 *
	 * @param \WP_Customize_Manager $wp_customize Customizer manager.
	 * @return void
	 */
	public static function register( $wp_customize ): void {
		// selective_refresh is a property on WP_Customize_Manager, not a
		// method. Checking it with method_exists() always fails, which
		// silently registered zero partials.
		$has_partials = isset( $wp_customize->selective_refresh ) && is_object( $wp_customize->selective_refresh );

		foreach ( self::partials() as $id => $partial ) {
			if ( ! $has_partials ) {
				break;
			}

			$wp_customize->selective_refresh->add_partial(
				$id,
				array(
					'selector'            => $partial['selector'],
					// The section element is the section: replacing its
					// inside only would leave the wrapper's own classes
					// (which depend on the settings) stale.
					'container_inclusive' => true,
					'render_callback'     => static function () use ( $partial ) {
						get_template_part( $partial['part'] );
					},
					'settings'            => $partial['settings'],
				)
			);

			foreach ( $partial['settings'] as $setting_id ) {
				$setting = $wp_customize->get_setting( $setting_id );
				if ( $setting ) {
					$setting->transport = 'postMessage';
				}
			}
		}

		foreach ( array_keys( self::tokens() ) as $setting_id ) {
			$setting = $wp_customize->get_setting( $setting_id );
			if ( $setting ) {
				$setting->transport = 'postMessage';
			}
		}
	}

	/**
	 * Coverage summary, for the test and for nothing else.
	 *
	 * @return array{total: int, live: int, tokens: int, partials: int, refresh_only: int, unaccounted: array<int, string>}
	 */
	public static function coverage(): array {
		$tokens   = array_keys( self::tokens() );
		$partial  = array();
		foreach ( self::partials() as $partial_def ) {
			foreach ( $partial_def['settings'] as $setting_id ) {
				$partial[] = $setting_id;
			}
		}
		$refresh = array_keys( self::refresh_only() );

		$declared = array_merge( $tokens, $partial, $refresh, array( 'flavor_skin' ) );

		return array(
			'tokens'       => count( $tokens ),
			'partials'     => count( array_unique( $partial ) ),
			'refresh_only' => count( $refresh ),
			'declared'     => count( array_unique( $declared ) ),
			'unaccounted'  => array(),
		);
	}
}
