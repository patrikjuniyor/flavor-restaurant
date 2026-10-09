<?php
/**
 * Header & Footer chrome builder.
 *
 * One place owns which header elements exist, in which order, which footer
 * columns are filled, and how both regions look. The templates stay thin: they
 * ask this class to render, which is exactly what the Customizer
 * selective-refresh partials do as well — a previewed change and a published
 * change therefore go through the same code path.
 *
 * Compatibility rules this class must keep:
 *
 * 1. A site that never touches a chrome setting renders what `header.php` and
 *    `footer.php` rendered before the builder existed.
 * 2. A stored order may be older than the registry (a component added later, or
 *    a JSON export from another version). Unknown keys are dropped and missing
 *    keys are appended, so an old value never renders an empty header.
 * 3. Nothing is decorative: every setting is read by a renderer or by
 *    `css_vars()`, and `tests/theme/test-chrome-builder.php` fails when a
 *    control stops being wired to real output.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chrome_Builder
 */
class Chrome_Builder {

	/**
	 * Chrome settings schema version; bump it when stored values migrate.
	 */
	public const VERSION = 1;

	/**
	 * Option that remembers which schema the stored chrome settings use.
	 */
	public const VERSION_OPTION = 'flavor_chrome_version';

	/**
	 * Prefix of the per-element visibility settings in the header.
	 */
	public const HEADER_SHOW_PREFIX = 'flavor_header_show_';

	/**
	 * Prefix of the per-block visibility settings in the footer.
	 */
	public const FOOTER_SHOW_PREFIX = 'flavor_footer_show_';

	/**
	 * How many footer widget areas exist: footer-1 is the legacy area that
	 * class-theme-setup already registers, footer-2 … footer-N are the builder's.
	 */
	const WIDGET_AREAS = 5;

	/**
	 * Ordered list of header elements, stored in one theme mod.
	 */
	public const HEADER_ORDER = 'flavor_header_order';

	/**
	 * Ordered list of footer blocks, stored in one theme mod.
	 */
	public const FOOTER_BLOCKS = 'flavor_footer_blocks';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'customize_register', array( self::class, 'register' ), 25 );
		add_action( 'wp_head', array( self::class, 'head_css' ), 21 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ), 45 );
		add_action( 'widgets_init', array( self::class, 'sidebars' ) );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_action( 'admin_init', array( self::class, 'migrate' ) );
		add_action( 'after_switch_theme', array( self::class, 'migrate' ) );
		add_action( 'customize_controls_enqueue_scripts', array( self::class, 'controls_assets' ), 20 );
		add_action( 'customize_preview_init', array( self::class, 'preview_assets' ), 20 );
	}

	/**
	 * Builder UI assets. Admin frame only — the public site never loads them.
	 */
	public static function controls_assets(): void {
		$js  = '/assets/js/customizer-chrome.js';
		$css = '/assets/css/customizer-chrome.css';

		if ( ! is_readable( FLAVOR_DIR . $js ) ) {
			return;
		}

		wp_enqueue_style(
			'flavor-customizer-chrome',
			FLAVOR_URI . $css,
			array( 'flavor-customizer-modern' ),
			(string) filemtime( FLAVOR_DIR . $css )
		);
		wp_enqueue_script(
			'flavor-customizer-chrome',
			FLAVOR_URI . $js,
			array( 'customize-controls', 'jquery-ui-sortable' ),
			(string) filemtime( FLAVOR_DIR . $js ),
			true
		);
		wp_localize_script(
			'flavor-customizer-chrome',
			'flavorChromeControls',
			array(
				'i18n'    => array(
					'resetConfirm' => __( 'چینش و تیک‌های این بخش به پیش‌فرض پوسته برمی‌گردد. ادامه می‌دهید؟', 'flavor' ),
					'resetDone'    => __( 'پیش‌فرض بازگردانده شد.', 'flavor' ),
					'moved'        => __( 'جابه‌جا شد.', 'flavor' ),
				),
				'version' => self::VERSION,
			)
		);
	}

	/**
	 * Preview bindings: the same schema the server used to build the CSS.
	 */
	public static function preview_assets(): void {
		$js = '/assets/js/customizer-chrome-preview.js';

		if ( ! is_readable( FLAVOR_DIR . $js ) ) {
			return;
		}

		wp_enqueue_script(
			'flavor-customizer-chrome-preview',
			FLAVOR_URI . $js,
			array( 'customize-preview' ),
			(string) filemtime( FLAVOR_DIR . $js ),
			true
		);
		wp_localize_script( 'flavor-customizer-chrome-preview', 'flavorChromePreview', self::preview_schema() );
	}

	/* ----------------------------------------------------------------- registry */

	/**
	 * Elements the header row can contain.
	 *
	 * `brand` and `nav` are deliberately not here: they are the two anchors the
	 * header layouts are built around, so they are always rendered and only
	 * styled by this panel.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function header_components(): array {
		return array(
			'phone'      => array(
				'label'   => __( 'شماره تماس', 'flavor' ),
				'default' => true,
				'hint'    => __( 'پیش از ۱۲۰۰ پیکسل خودش مخفی می‌شود تا هدر شلوغ نشود.', 'flavor' ),
			),
			'scheme'     => array(
				'label'   => __( 'کلید حالت شب و روز', 'flavor' ),
				'default' => true,
				'hint'    => __( 'فقط وقتی نمایش داده می‌شود که حالت شب روشن باشد.', 'flavor' ),
			),
			'wishlist'   => array(
				'label'   => __( 'علاقه‌مندی‌ها', 'flavor' ),
				'default' => true,
			),
			'order'      => array(
				'label'   => __( 'دکمهٔ سفارش آنلاین', 'flavor' ),
				'default' => true,
			),
			'mobile_nav' => array(
				'label'   => __( 'دکمهٔ منوی موبایل', 'flavor' ),
				'default' => true,
				'hint'    => __( 'اگر خاموشش کنید، منوی اصلی در موبایل همیشه نمایش داده می‌شود تا ناوبری گم نشود.', 'flavor' ),
			),
			'search'     => array(
				'label'   => __( 'جست‌وجو', 'flavor' ),
				'default' => false,
				'hint'    => __( 'از ۹۹۲ پیکسل بالا در هدر است؛ در موبایل داخل کشوی منو نمایش داده می‌شود.', 'flavor' ),
			),
			'cart'       => array(
				'label'   => __( 'سبد خرید', 'flavor' ),
				'default' => false,
				'hint'    => __( 'با ووکامرس فعال، تعداد را در بج نشان می‌دهد.', 'flavor' ),
			),
			'account'    => array(
				'label'   => __( 'حساب کاربری', 'flavor' ),
				'default' => false,
			),
		);
	}

	/**
	 * Blocks the footer grid is built from, in their default order.
	 *
	 * The order mirrors what `footer.php` used to hard-code: brand, quick
	 * links, hours, contact.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function footer_blocks(): array {
		return array(
			'brand'      => array(
				'label'   => __( 'لوگو و معرفی کوتاه', 'flavor' ),
				'default' => true,
			),
			'menu'       => array(
				'label'   => __( 'منوی دسترسی سریع', 'flavor' ),
				'default' => true,
				'hint'    => __( 'اگر منوی «پاورقی» را در نمایش ← منوها بسازید، همان جای فهرست ثابت می‌آید.', 'flavor' ),
			),
			'hours'      => array(
				'label'   => __( 'ساعات پذیرایی', 'flavor' ),
				'default' => true,
			),
			'contact'    => array(
				'label'   => __( 'نشانی و تلفن', 'flavor' ),
				'default' => true,
			),
			'social'     => array(
				'label'   => __( 'شبکه‌های اجتماعی', 'flavor' ),
				'default' => false,
				'hint'    => __( 'تا این بلوک جدا نشده باشد، لینک‌ها زیر معرفی کوتاه می‌آیند (مثل قبل).', 'flavor' ),
			),
			'newsletter' => array(
				'label'   => __( 'عضویت در خبرنامه', 'flavor' ),
				'default' => false,
				'hint'    => __( 'همان فرم خبرنامهٔ افزونه؛ ایمیل‌ها در فهرست محلی Flavor ذخیره می‌شوند.', 'flavor' ),
			),
			'widgets'    => array(
				'label'   => __( 'ویجت‌های همان ستون', 'flavor' ),
				'default' => false,
				'hint'    => __( 'هر ستون جایگاه ویجت خودش را دارد؛ در نمایش ← ویجت‌ها پرشان کنید.', 'flavor' ),
			),
		);
	}

	/**
	 * Extra footer toggles that are not columns.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function footer_extras(): array {
		return array(
			'copy'    => array(
				'label'   => __( 'نمایش خط کپی‌رایت', 'flavor' ),
				'default' => true,
			),
			'powered' => array(
				'label'   => __( 'نمایش «قدرت گرفته از Flavor»', 'flavor' ),
				'default' => true,
			),
			'map'     => array(
				'label'       => __( 'لینک «دیدن نقشهٔ شعب» در ستون تماس', 'flavor' ),
				'default'     => false,
				'description' => __( 'به صفحهٔ شعبه‌ها لینک می‌دهد؛ اگر آن صفحه را نساخته‌اید، لینک به همان نشانی می‌رود که هدر و فوتر استفاده می‌کنند.', 'flavor' ),
			),
		);
	}

	/* ----------------------------------------------------------------- sanitize */

	/**
	 * Normalise an ordered key list against a registry.
	 *
	 * Unknown keys are dropped, missing keys are appended in registry order, so
	 * a value written by an older or newer version still renders.
	 *
	 * @param mixed                               $value    Raw value.
	 * @param array<string, array<string, mixed>> $registry Registry the keys must exist in.
	 * @return string[]
	 */
	public static function normalize_order( $value, array $registry ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		$clean = array();
		$seen  = 0;

		foreach ( $value as $key ) {
			if ( $seen++ > 40 ) {
				break;
			}
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! isset( $registry[ $key ] ) || in_array( $key, $clean, true ) ) {
				continue;
			}
			$clean[] = $key;
		}

		foreach ( array_keys( $registry ) as $key ) {
			if ( ! in_array( $key, $clean, true ) ) {
				$clean[] = $key;
			}
		}

		return $clean;
	}

	/**
	 * Registry a stored order belongs to.
	 *
	 * @param string $id Setting id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function order_registry( string $id ): array {
		return self::HEADER_ORDER === $id ? self::header_components() : self::footer_blocks();
	}

	/**
	 * Turn any input into the canonical `a,b,c` form.
	 *
	 * @param mixed                               $value    Raw value.
	 * @param array<string, array<string, mixed>> $registry Registry.
	 * @return string
	 */
	public static function order_to_string( $value, array $registry ): string {
		return implode( ',', self::normalize_order( $value, $registry ) );
	}

	/**
	 * Sanitize callback for a stored order string.
	 *
	 * @param mixed                 $value   Raw value.
	 * @param \WP_Customize_Setting $setting Setting being saved.
	 * @return string
	 */
	public static function sanitize_order( $value, $setting = null ): string {
		return self::order_to_string( $value, self::order_registry( self::setting_id( $setting ) ) );
	}

	/**
	 * The setting id behind a Customizer setting object (or a bare id string).
	 *
	 * @param object|string|null $setting Setting object, id, or nothing.
	 * @return string Id, empty when the caller passed nothing.
	 */
	private static function setting_id( $setting ): string {
		if ( is_object( $setting ) && isset( $setting->id ) ) {
			return (string) $setting->id;
		}

		return is_scalar( $setting ) ? (string) $setting : '';
	}

	/**
	 * Report an order that had to be repaired instead of silently fixing it.
	 *
	 * @param \WP_Error             $validity Validity object.
	 * @param mixed                 $value    Raw value.
	 * @param \WP_Customize_Setting $setting  Setting.
	 * @return \WP_Error
	 */
	public static function validate_order( $validity, $value = null, $setting = null ) {
		if ( null === $value || ! is_object( $validity ) ) {
			return $validity;
		}

		$requested = array_filter( array_map( 'sanitize_key', is_string( $value ) ? explode( ',', $value ) : (array) $value ) );
		$accepted  = self::normalize_order( $value, self::order_registry( self::setting_id( $setting ) ) );

		if ( count( $requested ) !== count( $accepted ) ) {
			$validity->add( 'flavor_chrome_order', __( 'چند گزینهٔ ناشناخته از چینش حذف و بقیهٔ موارد به ترتیب استاندارد اضافه شدند.', 'flavor' ) );
		}

		return $validity;
	}

	/**
	 * Checkbox storage: always `yes`/`no`, so exports and JSON stay stable.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_checkbox( $value ): string {
		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true ) ? 'yes' : 'no';
	}

	/**
	 * Choices of a token setting.
	 *
	 * @param string $id Setting id.
	 * @return array<mixed, mixed>
	 */
	public static function choices( string $id ): array {
		$config = self::token_settings()[ $id ] ?? array();

		return isset( $config['choices'] ) ? $config['choices'] : array();
	}

	/**
	 * Keep only a listed choice.
	 *
	 * @param mixed                 $value   Raw value.
	 * @param \WP_Customize_Setting $setting Setting.
	 * @return mixed
	 */
	public static function sanitize_select( $value, $setting = null ) {
		$key = is_scalar( $value ) ? (string) $value : '';
		$id  = self::setting_id( $setting );

		foreach ( array_keys( self::choices( $id ) ) as $choice ) {
			if ( (string) $choice === $key ) {
				return is_int( $choice ) ? (int) $choice : $key;
			}
		}

		$spec = isset( self::token_settings()[ $id ] ) ? self::token_settings()[ $id ] : array();

		if ( array_key_exists( 'default', $spec ) ) {
			return $spec['default'];
		}

		return is_object( $setting ) && isset( $setting->default ) ? $setting->default : '';
	}

	/**
	 * Report an out-of-range choice rather than hiding it.
	 *
	 * @param \WP_Error             $validity Validity.
	 * @param mixed                 $value    Raw value.
	 * @param \WP_Customize_Setting $setting  Setting.
	 * @return \WP_Error
	 */
	public static function validate_select( $validity, $value = null, $setting = null ) {
		if ( null === $value || ! is_object( $validity ) ) {
			return $validity;
		}

		$key   = is_scalar( $value ) ? (string) $value : '';
		$found = false;

		foreach ( array_keys( self::choices( self::setting_id( $setting ) ) ) as $choice ) {
			if ( (string) $choice === $key ) {
				$found = true;
				break;
			}
		}

		if ( ! $found ) {
			$validity->add( 'flavor_chrome_choice', __( 'این مقدار برای این گزینه مجاز نیست.', 'flavor' ) );
		}

		return $validity;
	}

	/* ------------------------------------------------------------------- reading */

	/**
	 * Current ordered keys for a registry, defaults included.
	 *
	 * @param string                              $setting  Theme mod id.
	 * @param array<string, array<string, mixed>> $registry Registry.
	 * @return string[]
	 */
	public static function order( string $setting, array $registry ): array {
		$order = self::normalize_order( get_theme_mod( $setting, '' ), $registry );

		/**
		 * Filter a resolved chrome order.
		 *
		 * @param string[] $order   Ordered keys.
		 * @param string   $setting Theme mod id.
		 */
		return (array) apply_filters( 'flavor_chrome_order', $order, $setting );
	}

	/**
	 * Whether an element is switched on.
	 *
	 * @param string $key     Registry key.
	 * @param string $prefix  Visibility setting prefix.
	 * @param bool   $default Registry default.
	 * @return bool
	 */
	public static function show( string $key, string $prefix, bool $default ): bool {
		$value = get_theme_mod( $prefix . $key, $default ? 'yes' : 'no' );

		return in_array( $value, array( true, 1, '1', 'yes', 'on' ), true );
	}

	/**
	 * Header elements to render, in the owner's order.
	 *
	 * @return string[]
	 */
	public static function header_elements(): array {
		$registry = self::header_components();
		$ordered  = array();

		foreach ( self::order( self::HEADER_ORDER, $registry ) as $key ) {
			if ( self::show( $key, self::HEADER_SHOW_PREFIX, (bool) $registry[ $key ]['default'] ) ) {
				$ordered[] = $key;
			}
		}

		/**
		 * Filter the header elements about to be rendered.
		 *
		 * @param string[] $ordered Element keys.
		 */
		return (array) apply_filters( 'flavor_header_elements', $ordered );
	}

	/**
	 * Footer blocks to render, in order, capped at the configured column count.
	 *
	 * @return string[]
	 */
	public static function footer_elements(): array {
		$registry = self::footer_blocks();
		$blocks   = array();

		foreach ( self::order( self::FOOTER_BLOCKS, $registry ) as $key ) {
			if ( self::show( $key, self::FOOTER_SHOW_PREFIX, (bool) $registry[ $key ]['default'] ) ) {
				$blocks[] = $key;
			}
		}

		/**
		 * Filter the footer blocks about to be rendered.
		 *
		 * The column count is deliberately not applied here: a two-column grid
		 * wraps the remaining blocks onto further rows, while slicing would
		 * silently delete content the owner switched on.
		 *
		 * @param string[] $blocks Block keys.
		 */
		return (array) apply_filters( 'flavor_footer_blocks', $blocks );
	}

	/**
	 * How many columns the footer grid uses at one breakpoint.
	 *
	 * `0` means "auto": the theme's own `auto-fit` grid keeps working, which is
	 * what every site built before the footer builder had.
	 *
	 * @param string $breakpoint desktop|tablet|mobile.
	 * @return int
	 */
	public static function footer_columns( string $breakpoint ): int {
		$map = array(
			'desktop' => array( 'flavor_footer_columns', array( 0, 2, 3, 4, 5 ) ),
			'tablet'  => array( 'flavor_footer_columns_tablet', array( 0, 1, 2, 3 ) ),
			'mobile'  => array( 'flavor_footer_columns_mobile', array( 0, 1, 2 ) ),
		);

		if ( ! isset( $map[ $breakpoint ] ) ) {
			return 0;
		}

		$value = absint( get_theme_mod( $map[ $breakpoint ][0], 0 ) );

		return in_array( $value, $map[ $breakpoint ][1], true ) ? $value : 0;
	}

	/**
	 * Whether social links still belong inside the brand block.
	 *
	 * Legacy behaviour: the brand column printed them. Once the owner gives
	 * social links their own column they must not appear twice.
	 */
	public static function social_in_brand(): bool {
		return ! self::show( 'social', self::FOOTER_SHOW_PREFIX, false );
	}

	/**
	 * Whether the mobile drawer should exist at all.
	 */
	public static function has_mobile_nav(): bool {
		return self::show( 'mobile_nav', self::HEADER_SHOW_PREFIX, true );
	}

	/**
	 * Whether the search form belongs in the drawer as well as the row.
	 */
	public static function search_in_drawer(): bool {
		return self::show( 'search', self::HEADER_SHOW_PREFIX, false );
	}

	/**
	 * Whether the announcement bar has text to show.
	 */
	public static function has_topbar(): bool {
		return '' !== trim( (string) get_theme_mod( 'flavor_header_topbar', '' ) );
	}

	/**
	 * Whether the header currently offers a working cart button.
	 *
	 * Exposed for the PWA shell and the cart drawer script, which both count on
	 * the trigger existing before they bind to it.
	 *
	 * @return bool Visible.
	 */
	public static function has_cart(): bool {
		return self::show( 'cart', self::HEADER_SHOW_PREFIX, false ) && null !== self::cart();
	}

	/**
	 * The WooCommerce cart, or null when the shop is not serving this request.
	 *
	 * `class_exists( 'WooCommerce' )` is checked before `WC()` is ever called: a
	 * filter or a test double can replace the WooCommerce root object, and a
	 * header button must never be able to fatalise a page.
	 *
	 * @return object|null Cart.
	 */
	public static function cart() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) || ! did_action( 'wp_loaded' ) ) {
			return null;
		}

		$root = WC();

		return is_object( $root ) ? ( $root->cart ?? null ) : null;
	}

	/**
	 * Item count shown on the cart button.
	 *
	 * @return int Count, zero outside a web request.
	 */
	public static function cart_count(): int {
		$cart = self::cart();

		if ( ! $cart || ! method_exists( $cart, 'get_cart_contents_count' ) ) {
			return 0;
		}

		return (int) $cart->get_cart_contents_count();
	}

	/* -------------------------------------------------------------------- render */

	/**
	 * Small inline icons for chrome bits that have no shared glyph.
	 *
	 * @param string $name Icon name.
	 * @return string SVG markup, built from literal paths only.
	 */
	public static function icon( string $name ): string {
		$paths = array(
			'phone'  => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'bag'    => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>',
			'pin'    => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
			'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
			'user'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'arrow'  => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
		);

		return '<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
			. ( isset( $paths[ $name ] ) ? $paths[ $name ] : '' )
			. '</svg>';
	}

	/**
	 * The announcement bar above the header, if there is a message.
	 *
	 * @param string $variant `classic` or `bespoke`.
	 */
	public static function render_topbar( string $variant = 'classic' ): void {
		$default = '';

		if ( 'bespoke' === $variant && class_exists( Bespoke_Demos::class ) ) {
			$demo    = Bespoke_Demos::demo();
			$default = (string) ( $demo['theme_mods']['flavor_header_topbar'] ?? '' );
		}

		$message = trim( (string) get_theme_mod( 'flavor_header_topbar', $default ) );

		if ( '' === $message ) {
			return;
		}

		$link = trim( (string) get_theme_mod( 'flavor_header_topbar_link', '' ) );

		echo '<aside class="flavor-topbar' . ( 'bespoke' === $variant ? ' fd-topbar' : '' ) . '" id="flavor-topbar" aria-label="' . esc_attr__( 'پیام ویژه', 'flavor' ) . '">'
			. '<div class="flavor-container flavor-topbar__inner"><span>';

		if ( '' !== $link ) {
			echo '<a class="flavor-topbar__link" href="' . esc_url( $link ) . '">' . esc_html( $message ) . '</a>';
		} else {
			echo esc_html( $message );
		}

		echo '</span></div></aside>';
	}

	/**
	 * The site logo or name, plus the optional tagline.
	 *
	 * @param string $variant `classic` or `bespoke`.
	 */
	public static function render_brand( string $variant = 'classic' ): void {
		if ( has_custom_logo() ) {
			the_custom_logo();

			return;
		}

		$tagline = self::show( 'tagline', self::HEADER_SHOW_PREFIX, false )
			? trim( (string) get_bloginfo( 'description' ) )
			: '';

		if ( 'bespoke' === $variant && class_exists( Bespoke_Demos::class ) ) {
			$demo = Bespoke_Demos::demo();

			echo '<a class="fd-brand__link" href="' . esc_url( home_url( '/' ) ) . '"><span class="fd-brand__mark">';
			Bespoke_Demos::icon( (string) ( $demo['landing']['brand_icon'] ?? 'leaf' ), 27 );
			echo '</span><span><strong class="fd-brand__name">';
			flavor_site_name();
			echo '</strong>';

			if ( '' !== $tagline ) {
				echo '<span class="fd-brand__caption">' . esc_html( $tagline ) . '</span>';
			} elseif ( '' !== trim( (string) Bespoke_Demos::value( 'brand_caption' ) ) ) {
				echo '<span class="fd-brand__caption">' . esc_html( (string) Bespoke_Demos::value( 'brand_caption' ) ) . '</span>';
			}

			echo '</span></a>';

			return;
		}

		echo '<a class="flavor-brand__name" href="' . esc_url( home_url( '/' ) ) . '">';
		flavor_site_name();
		echo '</a>';

		if ( '' !== $tagline ) {
			echo '<span class="flavor-brand__tagline">' . esc_html( $tagline ) . '</span>';
		}
	}

	/**
	 * One header element.
	 *
	 * Returns false when the element cannot exist on this site (no WooCommerce,
	 * no phone number, night mode off), so nothing is printed as a dead button.
	 *
	 * @param string $key     Component key.
	 * @param string $variant `classic` or `bespoke`.
	 * @return bool Whether markup was printed.
	 */
	public static function render_header_element( string $key, string $variant = 'classic' ): bool {
		$phone = (string) get_theme_mod( 'flavor_phone', '02188001234' );
		$tel   = (string) preg_replace( '/\D+/', '', $phone );
		$besp  = 'bespoke' === $variant;

		switch ( $key ) {
			case 'phone':
				if ( '' === $tel ) {
					return false;
				}

				if ( $besp ) {
					echo '<a class="fd-header__phone" href="' . esc_url( 'tel:' . $tel ) . '" aria-label="' . esc_attr( sprintf( /* translators: site name */ __( 'تماس با %s', 'flavor' ), get_bloginfo( 'name' ) ) ) . '">';
					Bespoke_Demos::icon( 'phone', 18 );
					echo '<bdi>' . esc_html( Bespoke_Demos::digits( $phone ) ) . '</bdi></a>';

					return true;
				}

				echo '<a href="tel:' . esc_attr( $tel ) . '" class="flavor-header__phone" aria-label="' . esc_attr__( 'تماس تلفنی', 'flavor' ) . '">'
					. self::icon( 'phone' ) . '<span>' . esc_html( $phone ) . '</span></a>';

				return true;

			case 'order':
				if ( $besp ) {
					$demo  = Bespoke_Demos::demo();
					$label = (string) get_theme_mod( 'flavor_landing_primary_label', (string) ( $demo['landing']['primary_label'] ?? __( 'مشاهده منو', 'flavor' ) ) );

					echo '<a class="fd-button fd-button--primary fd-header__cta" href="' . esc_url( Bespoke_Demos::action_url( (string) ( $demo['landing']['primary_action'] ?? 'menu' ) ) ) . '">' . esc_html( $label );
					Bespoke_Demos::icon( 'arrow', 18 );
					echo '</a>';

					return true;
				}

				$menu_page = get_page_by_path( 'menu' );
				$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );

				echo '<a href="' . esc_url( $menu_url ) . '" class="flavor-btn flavor-btn--primary flavor-btn--sm flavor-header__order-btn">'
					. self::icon( 'bag' ) . esc_html__( 'سفارش آنلاین', 'flavor' ) . '</a>';

				return true;

			case 'mobile_nav':
				echo '<button type="button" class="flavor-hamburger" id="flavor-drawer-toggle" aria-label="' . esc_attr__( 'باز کردن منو', 'flavor' ) . '"'
					. ' aria-expanded="false" aria-controls="flavor-mobile-drawer"><span></span><span></span><span></span></button>';

				return true;

			case 'scheme':
				Dark_Mode::toggle( 'header', 'flavor-header__scheme' );

				return true;

			case 'wishlist':
				Wishlist::header_button();

				return true;

			case 'search':
				echo '<form class="flavor-header__search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
					. '<label class="screen-reader-text" for="flavor-header-search-' . ( $besp ? 'bespoke' : 'classic' ) . '">' . esc_html__( 'جست‌وجو در منو و صفحات', 'flavor' ) . '</label>'
					. '<input type="search" id="flavor-header-search-' . ( $besp ? 'bespoke' : 'classic' ) . '" name="s" maxlength="120" autocomplete="off"'
					. ' placeholder="' . esc_attr__( 'جست‌وجو…', 'flavor' ) . '" value="' . esc_attr( (string) get_search_query() ) . '" />'
					. '<button type="submit" class="flavor-header__search-submit" aria-label="' . esc_attr__( 'جست‌وجو', 'flavor' ) . '">' . self::icon( 'search' ) . '</button>'
					. '</form>';

				return true;

			case 'cart':
				$cart = self::cart();

				if ( ! $cart ) {
					return false;
				}

				$count = self::cart_count();

				echo '<a class="flavor-header__cart' . ( $count ? ' has-items' : '' ) . '" href="' . esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ) . '"'
					. ' aria-label="' . esc_attr__( 'سبد خرید', 'flavor' ) . '">' . self::icon( 'bag' )
					. '<span class="flavor-header__cart-count" data-flavor-cart-count>' . esc_html( (string) $count ) . '</span></a>';

				return true;

			case 'account':
				$url = '';

				if ( function_exists( 'wc_get_page_permalink' ) ) {
					$account = wc_get_page_permalink( 'myaccount' );
					$url     = is_wp_error( $account ) ? '' : (string) $account;
				}
				if ( '' === $url ) {
					$url = wp_login_url();
				}
				if ( '' === (string) $url ) {
					return false;
				}

				$label = is_user_logged_in() ? __( 'حساب من', 'flavor' ) : __( 'ورود یا ثبت‌نام', 'flavor' );

				echo '<a class="flavor-header__account' . ( is_user_logged_in() ? ' is-logged-in' : '' ) . '" href="' . esc_url( $url ) . '" aria-label="' . esc_attr( $label ) . '">'
					. self::icon( 'user' ) . '</a>';

				return true;
		}

		return false;
	}

	/**
	 * The action group of the header, in the owner's order.
	 *
	 * @param string $variant `classic` or `bespoke`.
	 */
	public static function render_header_actions( string $variant = 'classic' ): void {
		foreach ( self::header_elements() as $key ) {
			self::render_header_element( $key, $variant );
		}
	}

	/**
	 * Search form inside the mobile drawer, so hiding the header field on
	 * phones never means losing search.
	 */
	public static function render_drawer_search(): void {
		if ( ! self::search_in_drawer() ) {
			return;
		}

		echo '<form class="flavor-drawer__search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
			. '<label class="screen-reader-text" for="flavor-drawer-search">' . esc_html__( 'جست‌وجو در منو و صفحات', 'flavor' ) . '</label>'
			. '<input type="search" id="flavor-drawer-search" name="s" maxlength="120" autocomplete="off"'
			. ' placeholder="' . esc_attr__( 'جست‌وجو…', 'flavor' ) . '" />'
			. '<button type="submit" class="flavor-btn flavor-btn--primary flavor-btn--full">' . esc_html__( 'جست‌وجو', 'flavor' ) . '</button>'
			. '</form>';
	}

	/**
	 * The mobile drawer: nav, tools and search. Shared by both header variants
	 * so the builder's visibility switches behave the same everywhere.
	 *
	 * @param string $variant `classic` or `bespoke`.
	 */
	public static function render_mobile_drawer( string $variant = 'classic' ): void {
		if ( ! self::has_mobile_nav() ) {
			return;
		}

		$phone   = (string) get_theme_mod( 'flavor_phone', '02188001234' );
		$tel     = (string) preg_replace( '/\D+/', '', $phone );
		$besp    = 'bespoke' === $variant;
		$title_id = $besp ? 'fd-drawer-title' : 'flavor-drawer-title';

		echo '<div class="flavor-drawer-overlay" id="flavor-drawer-overlay" aria-hidden="true"></div>';
		echo '<aside class="flavor-drawer' . ( $besp ? ' fd-drawer' : '' ) . '" id="flavor-mobile-drawer" role="dialog" aria-modal="true" aria-hidden="true" inert aria-label="' . esc_attr__( 'منوی موبایل', 'flavor' ) . '"'
			. ( $besp ? ' aria-labelledby="' . esc_attr( $title_id ) . '"' : '' ) . '>';

		echo '<div class="flavor-drawer__header"><span class="flavor-drawer__title"' . ( $besp ? ' id="' . esc_attr( $title_id ) . '"' : '' ) . '>';
		flavor_site_name();
		echo '</span><button type="button" class="flavor-drawer__close" id="flavor-drawer-close" aria-label="' . esc_attr__( 'بستن منو', 'flavor' ) . '">✕</button></div>';

		echo '<div class="flavor-drawer__body">';
		echo '<nav class="flavor-drawer__nav" aria-label="' . esc_attr__( 'منوی موبایل', 'flavor' ) . '">';
		flavor_primary_nav();
		echo '</nav>';

		self::render_drawer_search();

		echo '<div class="flavor-drawer__tools">';
		Dark_Mode::toggle( 'drawer', 'flavor-scheme-toggle--drawer' );
		Wishlist::header_button();
		echo '</div>';

		if ( '' !== $tel ) {
			echo '<div class="flavor-drawer__contact"><a href="tel:' . esc_attr( $tel ) . '" class="flavor-btn flavor-btn--primary flavor-btn--full">'
				. self::icon( 'phone' )
				. esc_html( sprintf( /* translators: phone number */ __( 'تماس تلفنی: %s', 'flavor' ), $phone ) )
				. '</a>';
			if ( $besp ) {
				flavor_social_links();
			}
			echo '</div>';
		}

		echo '</div></aside>';
	}

	/**
	 * Rendered by the `flavor_header_actions` Customizer partial.
	 */
	public static function partial_header_actions(): void {
		self::render_header_actions( Bespoke_Demos::active() ? 'bespoke' : 'classic' );
	}

	/**
	 * Rendered by the `flavor_topbar` Customizer partial.
	 */
	public static function partial_topbar(): void {
		self::render_topbar( Bespoke_Demos::active() ? 'bespoke' : 'classic' );
	}

	/**
	 * Rendered by the `flavor_brand` Customizer partial.
	 */
	public static function partial_brand(): void {
		self::render_brand( Bespoke_Demos::active() ? 'bespoke' : 'classic' );
	}

	/* ------------------------------------------------------------ footer render */

	/**
	 * Fallback quick links, resolved the way the old footer did.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function footer_links(): array {
		$page = static function ( string $slug, string $fallback ): string {
			$post = get_page_by_path( $slug );

			return $post ? get_permalink( $post ) : home_url( '/' . $fallback . '/' );
		};

		$links = array(
			array(
				'url'   => home_url( '/' ),
				'label' => __( 'صفحه اصلی', 'flavor' ),
			),
			array(
				'url'   => $page( 'menu', 'menu' ),
				'label' => __( 'منوی سفارش آنلاین', 'flavor' ),
			),
		);

		if ( ! UI::inner() || UI::reservation_available() ) {
			$links[] = array(
				'url'   => $page( 'reservation', 'reservation' ),
				'label' => __( 'رزرو اینترنتی میز', 'flavor' ),
			);
		}

		$links[] = array(
			'url'   => $page( 'branches', 'branches' ),
			'label' => __( 'شعبه‌ها و ساعات کاری', 'flavor' ),
		);

		/**
		 * Filter the footer quick links.
		 *
		 * @param array $links Links with `url` and `label`.
		 */
		return (array) apply_filters( 'flavor_footer_links', $links );
	}

	/**
	 * One footer column.
	 *
	 * @param string $key      Block key.
	 * @param int    $position 1-based column position, used for the widget area.
	 */
	public static function render_footer_block( string $key, int $position = 1 ): void {
		$about   = (string) get_theme_mod( 'flavor_about', '' );
		$phone   = (string) get_theme_mod( 'flavor_phone', '02188001234' );
		$address = (string) get_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر، بالاتر از پارک‌وی' );

		switch ( $key ) {
			case 'brand':
				echo '<div class="flavor-footer__col flavor-footer__col--brand"><div class="flavor-footer__brand">';
				if ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					echo '<h3 class="flavor-footer__brand-name">';
					flavor_site_name();
					echo '</h3>';
				}
				echo '</div><p class="flavor-footer__bio">'
					. esc_html( '' !== trim( $about ) ? wp_trim_words( wp_strip_all_tags( $about ), 24 ) : __( 'تجربه طعم اصیل و فراموش‌نشدنی با تازه‌ترین مواد اولیه و میزبانی صمیمانه در محیطی آرام و دلپذیر.', 'flavor' ) )
					. '</p>';

				if ( self::social_in_brand() ) {
					flavor_social_links();
				}

				echo '</div>';
				return;

			case 'menu':
				echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">' . esc_html__( 'دسترسی سریع', 'flavor' ) . '</h4>';

				if ( has_nav_menu( 'footer' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'container'      => false,
							'items_wrap'     => '<ul class="flavor-footer__links">%3$s</ul>',
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
				} else {
					echo '<ul class="flavor-footer__links">';
					foreach ( self::footer_links() as $link ) {
						echo '<li><a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . '</a></li>';
					}
					echo '</ul>';
				}

				echo '</div>';
				return;

			case 'hours':
				echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">' . esc_html__( 'ساعات پذیرایی و ارسال', 'flavor' ) . '</h4>';

				if ( UI::inner() ) {
					UI_Pages::render_hours( Enqueue::current_branch_id() );
				} else {
					echo '<ul class="flavor-footer__schedule">'
						. '<li><span>' . esc_html__( 'شنبه تا چهارشنبه:', 'flavor' ) . '</span><strong>' . esc_html__( '۱۱:۳۰ الی ۲۳:۳۰', 'flavor' ) . '</strong></li>'
						. '<li><span>' . esc_html__( 'پنج‌شنبه و جمعه:', 'flavor' ) . '</span><strong>' . esc_html__( '۱۱:۳۰ الی ۲۴:۰۰', 'flavor' ) . '</strong></li>'
						. '<li><span>' . esc_html__( 'سفارش آنلاین:', 'flavor' ) . '</span><strong>' . esc_html__( 'پذیرش پیوسته', 'flavor' ) . '</strong></li>'
						. '</ul>';
				}

				echo '</div>';
				return;

			case 'contact':
				echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">' . esc_html__( 'اطلاعات تماس', 'flavor' ) . '</h4>'
					. '<div class="flavor-footer__contact-item">' . self::icon( 'pin' ) . '<span>' . esc_html( $address ) . '</span></div>'
					. '<div class="flavor-footer__contact-item">' . self::icon( 'phone' )
					. '<a href="tel:' . esc_attr( (string) preg_replace( '/\D+/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></div>';

				if ( self::show( 'map', self::FOOTER_SHOW_PREFIX, false ) ) {
					$branches = get_page_by_path( 'branches' );
					echo '<a class="flavor-footer__map" href="' . esc_url( $branches ? get_permalink( $branches ) : home_url( '/branches/' ) ) . '">'
						. esc_html__( 'دیدن نقشهٔ شعب', 'flavor' ) . '</a>';
				}

				echo '</div>';
				return;

			case 'social':
				echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">' . esc_html__( 'ما را دنبال کنید', 'flavor' ) . '</h4>';
				flavor_social_links();
				echo '</div>';
				return;

			case 'newsletter':
				echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">'
					. esc_html( (string) get_theme_mod( 'flavor_footer_newsletter_title', __( 'خبرنامهٔ منو و تخفیف‌ها', 'flavor' ) ) )
					. '</h4><p class="flavor-footer__bio">'
					. esc_html( (string) get_theme_mod( 'flavor_footer_newsletter_text', __( 'هر هفته یک ایمیل: منوی جدید، تخفیف‌های مناسبتی و ساعت ویژه.', 'flavor' ) ) )
					. '</p>'
					. '<form class="flavor-footer__newsletter" data-flavor-subscribe role="form">'
					. '<label class="screen-reader-text" for="flavor-footer-email-' . (int) $position . '">' . esc_html__( 'ایمیل شما', 'flavor' ) . '</label>'
					. '<input type="email" id="flavor-footer-email-' . (int) $position . '" name="email" dir="ltr" required maxlength="120" autocomplete="email" placeholder="name@example.com" />'
					. '<button type="submit" class="flavor-btn flavor-btn--primary flavor-btn--sm">' . esc_html__( 'عضویت', 'flavor' ) . '</button>'
					. '</form>'
					. '<p class="flavor-footer__status" role="status" aria-live="polite" data-flavor-subscribe-status></p>'
					. '</div>';
				return;

			case 'widgets':
				// Only footer-1 … footer-N exist, so a deep column reuses the last one.
				$index   = min( self::WIDGET_AREAS, max( 1, $position ) );
				$sidebar = 'footer-' . $index;

				if ( ! is_active_sidebar( $sidebar ) ) {
					echo '<div class="flavor-footer__col"><h4 class="flavor-footer__heading">' . esc_html__( 'جایگاه ویجت', 'flavor' ) . '</h4>'
						/* translators: %d: column number. */
						. '<p class="flavor-footer__bio">' . esc_html( sprintf( __( 'این ستون هنوز ویجتی ندارد. از نمایش ← ویجت‌ها، «ویجت‌های پاورقی %d» را پر کنید.', 'flavor' ), $index ) ) . '</p></div>';

					return;
				}

				echo '<div class="flavor-footer__col">';
				dynamic_sidebar( $sidebar );
				echo '</div>';
				return;
		}
	}

	/**
	 * The footer grid and its bottom bar.
	 *
	 * @param string $variant `classic` or `bespoke`.
	 */
	public static function render_footer( string $variant = 'classic' ): void {
		$blocks = self::footer_elements();

		echo '<footer class="flavor-footer' . ( 'bespoke' === $variant ? ' fd-footer' : '' ) . '" id="flavor-footer">'
			. '<div class="flavor-container flavor-footer__inner">';

		if ( $blocks ) {
			echo '<div class="flavor-footer__grid">';
			foreach ( array_values( $blocks ) as $position => $key ) {
				self::render_footer_block( $key, (int) $position + 1 );
			}
			echo '</div>';
		}

		$copy      = (string) get_theme_mod( 'flavor_footer_copy', '' );
		$show_copy = self::show( 'copy', self::FOOTER_SHOW_PREFIX, true );
		$show_meta = self::show( 'powered', self::FOOTER_SHOW_PREFIX, true );

		if ( $show_copy || $show_meta ) {
			echo '<div class="flavor-footer__bottom">';

			if ( $show_copy ) {
				echo '<p class="flavor-footer__copy">';
				if ( '' !== trim( $copy ) ) {
					echo esc_html( $copy );
				} else {
					echo '&copy; ' . esc_html( (string) wp_date( 'Y' ) ) . ' ';
					flavor_site_name();
					echo '. ' . esc_html__( 'تمامی حقوق محفوظ است.', 'flavor' );
				}
				echo '</p>';
			}

			if ( $show_meta ) {
				echo '<span class="flavor-footer__powered">' . esc_html__( 'قدرت گرفته از پلتفرم رستوران مستقیم Flavor', 'flavor' ) . '</span>';
			}

			echo '</div>';
		}

		echo '</div></footer>';
	}

	/**
	 * Rendered by the `flavor_footer` Customizer partial.
	 */
	public static function partial_footer(): void {
		self::render_footer( 'classic' );
	}

	/* --------------------------------------------------------------------- style */

	/**
	 * Select-backed chrome settings and the CSS variable each one writes.
	 *
	 * Shared by the Customizer registration, `css_vars()`, the preview script
	 * and the guard test, so a control can never drift away from the CSS that
	 * reads it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function token_settings(): array {
		return array(
			'flavor_header_align'             => array(
				'label'    => __( 'تراز ردیف هدر', 'flavor' ),
				'var'      => '--flavor-header-align',
				'default'  => '',
				'choices'  => array(
					''              => __( 'پیش‌فرض پوسته', 'flavor' ),
					'space-between' => __( 'لوگو یک سر، منو و دکمه‌ها سر دیگر', 'flavor' ),
					'flex-start'    => __( 'همه در ابتدا (راست در فارسی)', 'flavor' ),
					'center'        => __( 'همه وسط', 'flavor' ),
					'flex-end'      => __( 'همه در انتها (چپ در فارسی)', 'flavor' ),
				),
				'section'  => 'flavor_section_header',
				'priority' => 30,
			),
			'flavor_header_min_height'        => array(
				'label'    => __( 'کمترین ارتفاع هدر (دسکتاپ)', 'flavor' ),
				'var'      => '--flavor-header-height',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'64px'  => '64px',
					'72px'  => '72px',
					'84px'  => '84px',
					'96px'  => '96px',
					'112px' => '112px',
				),
				'section'  => 'flavor_section_header',
				'priority' => 31,
			),
			'flavor_header_min_height_mobile' => array(
				'label'    => __( 'کمترین ارتفاع هدر (موبایل)', 'flavor' ),
				'var'      => '--flavor-header-height-mobile',
				'default'  => '',
				'choices'  => array(
					''             => __( 'مثل دسکتاپ', 'flavor' ),
					'52px'  => '52px',
					'56px'  => '56px',
					'60px'  => '60px',
					'68px'  => '68px',
				),
				'section'  => 'flavor_section_header',
				'priority' => 32,
			),
			'flavor_header_gap'               => array(
				'label'    => __( 'فاصلهٔ بین عناصر هدر', 'flavor' ),
				'var'      => '--flavor-header-gap',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'10px'  => '10px',
					'14px'  => '14px',
					'20px'  => '20px',
					'28px'  => '28px',
				),
				'section'  => 'flavor_section_header',
				'priority' => 33,
			),
			'flavor_header_nav_size'          => array(
				'label'    => __( 'اندازهٔ آیتم‌های منو (دسکتاپ)', 'flavor' ),
				'var'      => '--flavor-nav-size',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'13px'  => '13px',
					'14px'  => '14px',
					'15px'  => '15px',
					'16px'  => '16px',
					'17px'  => '17px',
				),
				'section'  => 'flavor_section_header',
				'priority' => 34,
			),
			'flavor_header_nav_size_mobile'   => array(
				'label'    => __( 'اندازهٔ آیتم‌های منو (موبایل)', 'flavor' ),
				'var'      => '--flavor-nav-size-mobile',
				'default'  => '',
				'choices'  => array(
					''             => __( 'مثل دسکتاپ', 'flavor' ),
					'12px'  => '12px',
					'13px'  => '13px',
					'14px'  => '14px',
					'15px'  => '15px',
				),
				'section'  => 'flavor_section_header',
				'priority' => 35,
			),
			'flavor_header_border'            => array(
				'label'    => __( 'جدول پایین هدر', 'flavor' ),
				'var'      => '--flavor-header-border-size',
				'default'  => '',
				'choices'  => array(
					''            => __( 'پیش‌فرض پوسته (خط باریک)', 'flavor' ),
					'shadow'      => __( 'سایهٔ نرم بدون خط', 'flavor' ),
					'line-shadow' => __( 'خط و سایه', 'flavor' ),
					'none'        => __( 'بدون خط و سایه', 'flavor' ),
				),
				'section'  => 'flavor_section_header',
				'priority' => 36,
				'extra'    => array(
					'shadow'      => array(
						'--flavor-header-border-size' => '0px',
						'--flavor-header-shadow'      => '0 10px 28px -18px rgba(var(--flavor-ink-rgb), 0.5)',
					),
					'line-shadow' => array(
						'--flavor-header-border-size' => '1px',
						'--flavor-header-shadow'      => '0 10px 28px -18px rgba(var(--flavor-ink-rgb), 0.5)',
					),
					'none'        => array(
						'--flavor-header-border-size' => '0px',
						'--flavor-header-shadow'      => 'none',
					),
				),
			),
			'flavor_button_size'              => array(
				'label'       => __( 'ابعاد دکمه‌ها', 'flavor' ),
				'description' => __( 'به جای CSS آزاد، سه اندازهٔ بررسی‌شده؛ فاصلهٔ داخلی و فاصلهٔ آیکن با هم تنظیم می‌شوند تا چیدمان نشکند.', 'flavor' ),
				'var'         => '--flavor-btn-pad-y',
				'default'     => '',
				'choices'     => array(
					'پیش‌فرض پوسته',
					'compact'  => __( 'جمع‌وجور', 'flavor' ),
					'standard' => __( 'استاندارد قالب', 'flavor' ),
					'roomy'    => __( 'جای‌دار', 'flavor' ),
				),
				'section'     => 'flavor_section_layout',
				'priority'    => 22,
				'extra'       => array(
					'compact'  => array(
						'--flavor-btn-pad-y' => '8px',
						'--flavor-btn-pad-x' => '14px',
						'--flavor-btn-gap'   => '6px',
					),
					'standard' => array(
						'--flavor-btn-pad-y' => '12px',
						'--flavor-btn-pad-x' => '22px',
						'--flavor-btn-gap'   => '8px',
					),
					'roomy'    => array(
						'--flavor-btn-pad-y' => '16px',
						'--flavor-btn-pad-x' => '30px',
						'--flavor-btn-gap'   => '10px',
					),
				),
			),
			'flavor_footer_columns'           => array(
				'label'       => __( 'تعداد ستون در دسکتاپ', 'flavor' ),
				'description' => __( '«خودکار» همان شبکهٔ فعلی پوسته است و برای بیشتر رستوران‌ها بهترین انتخاب است.', 'flavor' ),
				'var'         => '--flavor-footer-cols',
				'default'     => 0,
				'choices'     => array(
					0 => __( 'خودکار', 'flavor' ),
					2 => '۲',
					3 => '۳',
					4 => '۴',
					5 => '۵',
				),
				'section'     => 'flavor_section_footer',
				'priority'    => 40,
				'format'      => 'grid',
			),
			'flavor_footer_columns_tablet'    => array(
				'label'    => __( 'تعداد ستون در تبلت', 'flavor' ),
				'var'      => '--flavor-footer-cols-tablet',
				'default'  => 0,
				'choices'  => array(
					0 => __( 'خودکار', 'flavor' ),
					1 => '۱',
					2 => '۲',
					3 => '۳',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 41,
				'format'   => 'grid',
			),
			'flavor_footer_columns_mobile'    => array(
				'label'    => __( 'تعداد ستون در موبایل', 'flavor' ),
				'var'      => '--flavor-footer-cols-mobile',
				'default'  => 0,
				'choices'  => array(
					0 => __( 'خودکار', 'flavor' ),
					1 => '۱',
					2 => '۲',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 42,
				'format'   => 'grid',
			),
			'flavor_footer_space'             => array(
				'label'    => __( 'فضای پاورقی (دسکتاپ)', 'flavor' ),
				'var'      => '--flavor-footer-space',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'40px'  => '40px',
					'56px'  => '56px',
					'80px'  => '80px',
					'104px' => '104px',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 43,
			),
			'flavor_footer_space_mobile'      => array(
				'label'    => __( 'فضای پاورقی (موبایل)', 'flavor' ),
				'var'      => '--flavor-footer-space-mobile',
				'default'  => '',
				'choices'  => array(
					''             => __( 'مثل دسکتاپ', 'flavor' ),
					'32px'  => '32px',
					'40px'  => '40px',
					'52px'  => '52px',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 44,
			),
			'flavor_footer_heading_size'      => array(
				'label'    => __( 'اندازهٔ تیتر ستون‌های پاورقی', 'flavor' ),
				'var'      => '--flavor-footer-heading-size',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'14px'  => '14px',
					'15px'  => '15px',
					'18px'  => '18px',
					'20px'  => '20px',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 45,
			),
			'flavor_footer_link_size'         => array(
				'label'    => __( 'اندازهٔ لینک‌های پاورقی', 'flavor' ),
				'var'      => '--flavor-footer-link-size',
				'default'  => '',
				'choices'  => array(
					'پیش‌فرض پوسته',
					'13px'  => '13px',
					'15px'  => '15px',
					'16px'  => '16px',
				),
				'section'  => 'flavor_section_footer',
				'priority' => 46,
			),
		);
	}

	/**
	 * Colour settings that need their own rule, because a skin may already paint
	 * the same element. Each carries the body class that switches the rule on.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function color_settings(): array {
		return array(
			'flavor_header_bg'         => array(
				'label'    => __( 'پس‌زمینهٔ هدر', 'flavor' ),
				'var'      => '--flavor-header-bg',
				'section'  => 'flavor_section_header',
				'priority' => 50,
			),
			'flavor_header_ink'        => array(
				'label'    => __( 'رنگ متن و آیکن‌های هدر', 'flavor' ),
				'var'      => '--flavor-header-ink',
				'section'  => 'flavor_section_header',
				'priority' => 51,
			),
			'flavor_header_line'       => array(
				'label'    => __( 'رنگ خط پایین هدر', 'flavor' ),
				'var'      => '--flavor-header-line',
				'section'  => 'flavor_section_header',
				'priority' => 52,
			),
			'flavor_header_topbar_bg'  => array(
				'label'    => __( 'پس‌زمینهٔ نوار اطلاعیه', 'flavor' ),
				'var'      => '--flavor-topbar-bg',
				'section'  => 'flavor_section_header',
				'priority' => 53,
			),
			'flavor_header_topbar_ink' => array(
				'label'    => __( 'رنگ متن نوار اطلاعیه', 'flavor' ),
				'var'      => '--flavor-topbar-ink',
				'section'  => 'flavor_section_header',
				'priority' => 54,
			),
			'flavor_footer_bg'         => array(
				'label'    => __( 'پس‌زمینهٔ پاورقی', 'flavor' ),
				'var'      => '--flavor-footer-bg',
				'section'  => 'flavor_section_footer',
				'priority' => 50,
			),
			'flavor_footer_ink'        => array(
				'label'    => __( 'رنگ متن پاورقی', 'flavor' ),
				'var'      => '--flavor-footer-ink',
				'section'  => 'flavor_section_footer',
				'priority' => 51,
			),
		);
	}

	/**
	 * A value that is safe to place inside a declaration.
	 *
	 * The token list already validates the input; this is the second gate so a
	 * value imported from a settings file cannot inject a declaration.
	 *
	 * @param string $value Token.
	 * @return string
	 */
	private static function css_value( string $value ): string {
		$value = trim( str_replace( array( "\n", "\r", "\0", ';', '{', '}', '@', '\\' ), '', $value ) );

		return preg_match( '/^[a-zA-Z0-9()#%.,_\- ]+$/', $value ) ? $value : '';
	}

	/**
	 * CSS custom properties for the chrome, or an empty string when untouched.
	 *
	 * Printing nothing while the owner changed nothing means the shipped
	 * stylesheet decides, which is exactly the pre-builder behaviour.
	 */
	public static function css_vars(): string {
		$vars = array();

		foreach ( self::token_settings() as $id => $config ) {
			$value = get_theme_mod( $id, $config['default'] );
			$value = is_scalar( $value ) ? (string) $value : '';

			/* Untouched or "skin default" means: print nothing, let the
			   stylesheet (and the twelve skins) decide. */
			if ( '' === $value || $value === (string) $config['default'] ) {
				continue;
			}
			if ( ! in_array( $value, array_map( 'strval', array_keys( $config['choices'] ) ), true ) ) {
				continue;
			}

			if ( isset( $config['extra'][ $value ] ) ) {
				foreach ( $config['extra'][ $value ] as $extra_var => $extra_value ) {
					$token          = self::css_value( (string) $extra_value );
					$vars[ $extra_var ] = '' === $token ? (string) $extra_value : $token;
				}
				continue;
			}

			$token = self::css_value( $value );
			if ( '' === $token ) {
				continue;
			}
			if ( isset( $config['format'] ) && 'grid' === $config['format'] ) {
				$token = 'repeat(' . absint( $token ) . ', minmax(0, 1fr))';
			}
			$vars[ $config['var'] ] = $token;
		}

		foreach ( self::color_settings() as $id => $config ) {
			$hex = sanitize_hex_color( (string) get_theme_mod( $id, '' ) );
			if ( $hex ) {
				$vars[ $config['var'] ] = $hex;
			}
		}

		foreach ( self::derived_vars() as $name => $value ) {
			$vars[ $name ] = $value;
		}

		if ( ! $vars ) {
			return '';
		}

		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			$css .= $name . ':' . $value . ';';
		}

		return $css . '}';
	}

	/**
	 * The chrome variables, printed right after the token block.
	 */
	public static function head_css(): void {
		$css = self::css_vars();

		if ( '' === $css ) {
			return;
		}

		echo '<style id="flavor-chrome">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tokens only, see css_value().
	}

	/**
	 * Class name that switches on the chrome rule for one setting.
	 *
	 * PHP and the preview script derive the same name from the setting id, so a
	 * new control needs no extra wiring to be live in the preview.
	 *
	 * @param string $id Setting id.
	 * @return string
	 */
	public static function gate_for( string $id ): string {
		$slug = preg_replace( '/[^a-z0-9]+/', '-', str_replace( 'flavor_', '', $id ) );

		return 'flavor-chrome-' . trim( (string) $slug, '-' );
	}

	/**
	 * Class-only switches that cannot be expressed as a custom property.
	 *
	 * @return array<string, array<string, string>> Setting id => state => class.
	 */
	public static function state_gates(): array {
		return array(
			'flavor_header_sticky_mobile'     => array( 'off' => 'flavor-chrome-sticky-mobile-off' ),
			'flavor_header_show_mobile_nav'   => array( 'off' => 'flavor-chrome-no-mobile-nav' ),
		);
	}

	/**
	 * Every chrome class that is currently active on <body>.
	 *
	 * @return string[]
	 */
	public static function active_gates(): array {
		$gates = array();

		foreach ( self::token_settings() as $id => $config ) {
			$value = get_theme_mod( $id, $config['default'] );
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( '' !== $value && $value !== (string) $config['default'] ) {
				$gates[] = self::gate_for( $id );
			}
		}

		foreach ( self::color_settings() as $id => $config ) {
			if ( sanitize_hex_color( (string) get_theme_mod( $id, '' ) ) ) {
				$gates[] = self::gate_for( $id );
			}
		}

		foreach ( self::state_gates() as $id => $map ) {
			if ( 'no' === self::sanitize_checkbox( get_theme_mod( $id, 'yes' ) ) && isset( $map['off'] ) ) {
				$gates[] = $map['off'];
			}
		}

		if ( self::has_topbar() ) {
			$gates[] = 'flavor-has-topbar';
		}

		return array_values( array_unique( $gates ) );
	}

	/**
	 * Variables a single choice expands into.
	 *
	 * @return array<string, string>
	 */
	public static function derived_vars(): array {
		return array();
	}

	/**
	 * What the live preview needs to know, so the script and PHP never diverge.
	 *
	 * @return array<string, mixed>
	 */
	public static function preview_schema(): array {
		$tokens = array();

		foreach ( self::token_settings() as $id => $config ) {
			$tokens[ $id ] = array(
				'var'     => $config['var'],
				'choices' => array_map( 'strval', array_keys( $config['choices'] ) ),
				'default' => (string) $config['default'],
				'extra'   => isset( $config['extra'] ) ? $config['extra'] : array(),
				'grid'    => isset( $config['format'] ) && 'grid' === $config['format'],
				'gate'    => self::gate_for( $id ),
			);
		}

		$colors = array();
		foreach ( self::color_settings() as $id => $config ) {
			$colors[ $id ] = array(
				'var'  => $config['var'],
				'gate' => self::gate_for( $id ),
			);
		}

		return array(
			'tokens'  => $tokens,
			'colors'  => $colors,
			'switches' => self::state_gates(),
		);
	}

	/**
	 * Chrome classes on <body>: the only switch the stylesheet needs.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		return array_values( array_unique( array_merge( $classes, self::active_gates() ) ) );
	}

	/**
	 * One extra stylesheet, front end only, loaded after the skins so it can
	 * win the cascade without `!important`.
	 */
	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}

		$css = '/assets/css/chrome.css';

		if ( ! is_readable( FLAVOR_DIR . $css ) ) {
			return;
		}

		$deps = array( 'flavor-main' );
		foreach ( array( 'flavor-marketing', 'flavor-premium', 'flavor-dark-mode' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
				$deps[] = $handle;
			}
		}

		$skin_handle = 'flavor-skin-' . sanitize_html_class( Design::current_skin() );
		if ( wp_style_is( $skin_handle, 'registered' ) || wp_style_is( $skin_handle, 'enqueued' ) || wp_style_is( $skin_handle, 'done' ) ) {
			$deps[] = $skin_handle;
		}

		wp_enqueue_style( 'flavor-chrome', FLAVOR_URI . $css, $deps, (string) filemtime( FLAVOR_DIR . $css ) );
	}

	/* ------------------------------------------------------------------- widgets */

	/**
	 * One widget area per footer column. `footer-1` already exists in
	 * Theme_Setup, so it is kept untouched for sites that use it.
	 */
	public static function sidebars(): void {
		for ( $index = 2; $index <= self::WIDGET_AREAS; $index++ ) {
			register_sidebar(
				array(
					/* translators: %d: column number. */
					'name'          => sprintf( __( 'ویجت‌های پاورقی %d', 'flavor' ), $index ),
					'id'            => 'footer-' . $index,
					'description'   => __( 'وقتی ستونی در سازندهٔ پاورقی روی «ویجت‌های همان ستون» باشد، اینجا پر می‌شود.', 'flavor' ),
					'before_widget' => '<section class="flavor-widget">',
					'after_widget'  => '</section>',
					'before_title'  => '<h2 class="flavor-widget__title">',
					'after_title'   => '</h2>',
				)
			);
		}
	}

	/* ----------------------------------------------------------------- migration */

	/**
	 * Bring stored chrome settings up to the current schema.
	 *
	 * Runs on theme switch and once more in the admin; after that it is a
	 * single option read. Idempotent, and it never invents content: it only
	 * writes explicit, validated values where the previous version stored none.
	 */
	public static function migrate(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::VERSION ) {
			return;
		}

		set_theme_mod( self::HEADER_ORDER, self::order_to_string( get_theme_mod( self::HEADER_ORDER, '' ), self::header_components() ) );
		set_theme_mod( self::FOOTER_BLOCKS, self::order_to_string( get_theme_mod( self::FOOTER_BLOCKS, '' ), self::footer_blocks() ) );

		set_theme_mod( 'flavor_header_sticky', self::sanitize_checkbox( get_theme_mod( 'flavor_header_sticky', 'yes' ) ) );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/* ---------------------------------------------------------------- customizer */

	/**
	 * Items for the builder control: label, hint, current value, default.
	 *
	 * @param array<string, array<string, mixed>> $registry Registry.
	 * @param string                              $prefix    Visibility prefix.
	 * @return array<int, array<string, mixed>>
	 */
	public static function builder_items( array $registry, string $prefix ): array {
		$items = array();

		foreach ( $registry as $key => $config ) {
			$items[] = array(
				'key'     => $key,
				'label'   => $config['label'],
				'hint'    => isset( $config['hint'] ) ? $config['hint'] : '',
				'setting' => $prefix . $key,
				'on'      => self::show( $key, $prefix, (bool) $config['default'] ),
				'default' => (bool) $config['default'],
			);
		}

		return $items;
	}

	/**
	 * Register the builder panel: settings, controls and live-preview plumbing.
	 *
	 * @param \WP_Customize_Manager $wp_customize Manager.
	 */
	public static function register( $wp_customize ): void {
		require_once FLAVOR_DIR . '/inc/class-chrome-control.php';

		self::ensure_section(
			$wp_customize,
			'flavor_section_header',
			array(
				'title'       => __( 'سازندهٔ هدر', 'flavor' ),
				'description' => __( 'عناصر هدر را روشن یا خاموش کنید و با کشیدن، ترتیبشان را بچینید. تغییرات روی همین پیش‌نمایش اعمال می‌شود.', 'flavor' ),
				'priority'    => 25,
			)
		);

		self::ensure_section(
			$wp_customize,
			'flavor_section_footer',
			array(
				'title'       => __( 'سازندهٔ پاورقی', 'flavor' ),
				'description' => __( 'ستون‌های پاورقی، بلوک هر ستون و ظاهرشان. محتوای ویجت‌ها از نمایش ← ویجت‌ها مدیریت می‌شود.', 'flavor' ),
				'priority'    => 40,
			)
		);

		/* ---- header order and visibility -------------------------------- */
		$header_settings = array_merge(
			array( self::HEADER_ORDER ),
			array_map(
				static function ( $key ) {
					return self::HEADER_SHOW_PREFIX . $key;
				},
				array_keys( self::header_components() )
			)
		);

		foreach ( self::header_components() as $key => $config ) {
			$wp_customize->add_setting(
				self::HEADER_SHOW_PREFIX . $key,
				array(
					'default'           => $config['default'] ? 'yes' : 'no',
					'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
					'transport'         => 'refresh',
				)
			);
		}

		$wp_customize->add_setting(
			self::HEADER_ORDER,
			array(
				'default'           => implode( ',', array_keys( self::header_components() ) ),
				'sanitize_callback' => static function ( $value ) {
					return self::sanitize_order( $value, self::HEADER_ORDER );
				},
				'transport'         => 'refresh',
			)
		);

		add_filter(
			'customize_validate_' . self::HEADER_ORDER,
			static function ( $validity, $value = null, $setting = null ) {
				return self::validate_order( $validity, $value, self::HEADER_ORDER );
			},
			30,
			3
		);

		$wp_customize->add_control(
			new Chrome_Order_Control(
				$wp_customize,
				self::HEADER_ORDER,
				array(
					'label'        => __( 'چینش عناصر هدر', 'flavor' ),
					'description'  => __( 'با درگ کردن یا دکمهٔ جابه‌جایی، ترتیب را عوض کنید. تیک هر مورد یعنی در هدر نمایش داده شود.', 'flavor' ),
					'section'      => 'flavor_section_header',
					'priority'     => 5,
					'settings'     => $header_settings,
					'flavor_items' => self::builder_items( self::header_components(), self::HEADER_SHOW_PREFIX ),
					'flavor_reset_label' => __( 'بازگردانی چینش پیش‌فرض هدر', 'flavor' ),
				)
			)
		);

		/* ---- header extras --------------------------------------------- */
		$wp_customize->add_setting(
			'flavor_header_topbar_link',
			array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'flavor_header_topbar_link',
			array(
				'label'       => __( 'لینک نوار اطلاعیه (اختیاری)', 'flavor' ),
				'description' => __( 'مثلاً نشانی صفحهٔ تخفیف‌ها. خالی بگذارید تا نوار فقط متن باشد.', 'flavor' ),
				'section'     => 'flavor_section_header',
				'type'        => 'url',
				'priority'    => 20,
			)
		);

		$wp_customize->add_setting(
			'flavor_header_sticky_mobile',
			array(
				'default'           => 'yes',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'transport'         => 'postMessage',
			)
		);
		$wp_customize->add_control(
			'flavor_header_sticky_mobile',
			array(
				'label'       => __( 'هدر چسبان در موبایل', 'flavor' ),
				'description' => __( 'خاموش کردنش جای بیشتری برای محتوا روی گوشی می‌گذارد.', 'flavor' ),
				'section'     => 'flavor_section_header',
				'type'        => 'checkbox',
				'priority'    => 22,
			)
		);

		$wp_customize->add_setting(
			'flavor_header_show_tagline',
			array(
				'default'           => 'no',
				'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'flavor_header_show_tagline',
			array(
				'label'       => __( 'نمایش شعار کوتاه کنار نام سایت', 'flavor' ),
				'description' => __( 'همان «شرح کوتاه سایت» در تنظیمات عمومی وردپرس. وقتی لوگوی اختصاصی بگذارید، این گزینه اثری ندارد.', 'flavor' ),
				'section'     => 'flavor_section_header',
				'type'        => 'checkbox',
				'priority'    => 24,
			)
		);

		/* ---- token selects --------------------------------------------- */
		foreach ( self::token_settings() as $id => $config ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => $config['default'],
					'sanitize_callback' => static function ( $value ) use ( $id ) {
						return self::sanitize_select( $value, $id );
					},
					'transport'         => 'postMessage',
				)
			);

			/*
			 * Validation runs on the filter rather than in the setting args: core
			 * registers an add_setting callback for one argument only, and a useful
			 * message needs the raw value and the setting next to it.
			 */
			add_filter(
				'customize_validate_' . $id,
				static function ( $validity, $value = null, $setting = null ) use ( $id ) {
					return self::validate_select( $validity, $value, $id );
				},
				30,
				3
			);
			$wp_customize->add_control(
				$id,
				array(
					'label'       => $config['label'],
					'description' => isset( $config['description'] ) ? $config['description'] : '',
					'section'     => $config['section'],
					'type'        => 'select',
					'choices'     => $config['choices'],
					'priority'    => $config['priority'],
				)
			);
		}

		/* ---- colours ----------------------------------------------------- */
		foreach ( self::color_settings() as $id => $config ) {
			$wp_customize->add_setting(
				$id,
				array(
					'default'           => '',
					'sanitize_callback' => 'sanitize_hex_color',
					'transport'         => 'postMessage',
				)
			);
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					$id,
					array(
						'label'       => $config['label'],
						'description' => __( 'خالی بگذارید تا رنگ خودِ پوسته حفظ شود.', 'flavor' ),
						'section'     => $config['section'],
						'priority'    => $config['priority'],
					)
				)
			);
		}

		/* ---- footer blocks --------------------------------------------- */
		$footer_settings = array_merge(
			array( self::FOOTER_BLOCKS ),
			array_map(
				static function ( $key ) {
					return self::FOOTER_SHOW_PREFIX . $key;
				},
				array_keys( self::footer_blocks() )
			)
		);

		foreach ( self::footer_blocks() as $key => $config ) {
			$wp_customize->add_setting(
				self::FOOTER_SHOW_PREFIX . $key,
				array(
					'default'           => $config['default'] ? 'yes' : 'no',
					'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
					'transport'         => 'refresh',
				)
			);
		}

		$wp_customize->add_setting(
			self::FOOTER_BLOCKS,
			array(
				'default'           => implode( ',', array_keys( self::footer_blocks() ) ),
				'sanitize_callback' => static function ( $value ) {
					return self::sanitize_order( $value, self::FOOTER_BLOCKS );
				},
				'transport'         => 'refresh',
			)
		);

		add_filter(
			'customize_validate_' . self::FOOTER_BLOCKS,
			static function ( $validity, $value = null, $setting = null ) {
				return self::validate_order( $validity, $value, self::FOOTER_BLOCKS );
			},
			30,
			3
		);
		$wp_customize->add_control(
			new Chrome_Order_Control(
				$wp_customize,
				self::FOOTER_BLOCKS,
				array(
					'label'        => __( 'بلوک‌های پاورقی', 'flavor' ),
					'description'  => __( 'ترتیب بلوک‌ها همان ترتیب ستون‌هاست. اگر «تعداد ستون در دسکتاپ» را کمتر از بلوک‌های روشن بگذارید، بلوک‌های اضافه در ستون آخر زیر هم می‌آیند.', 'flavor' ),
					'section'      => 'flavor_section_footer',
					'priority'     => 5,
					'settings'     => $footer_settings,
					'flavor_items' => self::builder_items( self::footer_blocks(), self::FOOTER_SHOW_PREFIX ),
					'flavor_reset_label' => __( 'بازگردانی چینش پیش‌فرض پاورقی', 'flavor' ),
				)
			)
		);

		$wp_customize->add_setting(
			'flavor_footer_newsletter_title',
			array(
				'default'           => __( 'خبرنامهٔ منو و تخفیف‌ها', 'flavor' ),
				'sanitize_callback' => 'sanitize_text_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'flavor_footer_newsletter_title',
			array(
				'label'    => __( 'عنوان بلوک خبرنامه', 'flavor' ),
				'section'  => 'flavor_section_footer',
				'type'     => 'text',
				'priority' => 60,
			)
		);

		$wp_customize->add_setting(
			'flavor_footer_newsletter_text',
			array(
				'default'           => __( 'هر هفته یک ایمیل: منوی جدید، تخفیف‌های مناسبتی و ساعت ویژه.', 'flavor' ),
				'sanitize_callback' => 'sanitize_textarea_field',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			'flavor_footer_newsletter_text',
			array(
				'label'    => __( 'متن بلوک خبرنامه', 'flavor' ),
				'section'  => 'flavor_section_footer',
				'type'     => 'textarea',
				'priority' => 61,
			)
		);

		foreach ( self::footer_extras() as $key => $config ) {
			$wp_customize->add_setting(
				self::FOOTER_SHOW_PREFIX . $key,
				array(
					'default'           => $config['default'] ? 'yes' : 'no',
					'sanitize_callback' => array( self::class, 'sanitize_checkbox' ),
					'transport'         => 'refresh',
				)
			);
			$wp_customize->add_control(
				self::FOOTER_SHOW_PREFIX . $key,
				array(
					'label'       => $config['label'],
					'description' => isset( $config['description'] ) ? $config['description'] : '',
					'section'     => 'flavor_section_footer',
					'type'        => 'checkbox',
				)
			);
		}

		self::register_partials( $wp_customize );
	}

	/**
	 * Update a section another class already registered, or create it.
	 *
	 * @param \WP_Customize_Manager $wp_customize Manager.
	 * @param string                $id           Section id.
	 * @param array<string, mixed>  $args         Section args.
	 */
	private static function ensure_section( $wp_customize, string $id, array $args ): void {
		$section = $wp_customize->get_section( $id );

		if ( ! $section ) {
			$wp_customize->add_section( $id, $args );

			return;
		}

		foreach ( $args as $key => $value ) {
			$section->$key = $value;
		}
	}

	/**
	 * Selective refresh: a structure change re-renders only its own region.
	 *
	 * @param \WP_Customize_Manager $wp_customize Manager.
	 */
	public static function register_partials( $wp_customize ): void {
		if ( ! isset( $wp_customize->selective_refresh ) ) {
			return;
		}

		$wp_customize->add_partial(
			'flavor_header_actions',
			array(
				'selector'            => '.flavor-header__actions',
				'container_inclusive' => false,
				'render_callback'     => array( self::class, 'partial_header_actions' ),
				'settings'            => array( self::HEADER_ORDER ),
			)
		);

		$wp_customize->add_partial(
			'flavor_brand',
			array(
				'selector'            => '.flavor-brand,.fd-brand',
				'container_inclusive' => false,
				'render_callback'     => array( self::class, 'partial_brand' ),
				'settings'            => array( 'flavor_header_show_tagline' ),
			)
		);

		$wp_customize->add_partial(
			'flavor_topbar',
			array(
				'selector'            => '#flavor-topbar',
				'container_inclusive' => true,
				'render_callback'     => array( self::class, 'partial_topbar' ),
				'settings'            => array( 'flavor_header_topbar', 'flavor_header_topbar_link' ),
				'fallback_refresh'    => true,
			)
		);

		$wp_customize->add_partial(
			'flavor_footer',
			array(
				'selector'            => '#flavor-footer',
				'container_inclusive' => true,
				'render_callback'     => array( self::class, 'partial_footer' ),
				'settings'            => array(
					self::FOOTER_BLOCKS,
					'flavor_footer_copy',
					'flavor_footer_newsletter_title',
					'flavor_footer_newsletter_text',
				),
			)
		);
	}

	/* -------------------------------------------------------------------- export */

	/**
	 * Everything the chrome registers, used by the guard test and by the
	 * settings screen that lists what an export file contains.
	 *
	 * @return array<string, string> Setting id → sanitize callback.
	 */
	public static function settings_map(): array {
		$map = array(
			self::HEADER_ORDER               => 'sanitize_order',
			self::FOOTER_BLOCKS              => 'sanitize_order',
			'flavor_header_topbar_link'      => 'esc_url_raw',
			'flavor_header_sticky_mobile'     => 'sanitize_checkbox',
			'flavor_header_show_tagline'      => 'sanitize_checkbox',
			'flavor_footer_newsletter_title'  => 'sanitize_text_field',
			'flavor_footer_newsletter_text'   => 'sanitize_textarea_field',
		);

		foreach ( array_keys( self::token_settings() ) as $id ) {
			$map[ $id ] = 'sanitize_select';
		}
		foreach ( array_keys( self::color_settings() ) as $id ) {
			$map[ $id ] = 'sanitize_hex_color';
		}
		foreach ( array_keys( self::header_components() ) as $key ) {
			$map[ self::HEADER_SHOW_PREFIX . $key ] = 'sanitize_checkbox';
		}
		foreach ( array_keys( self::footer_blocks() ) as $key ) {
			$map[ self::FOOTER_SHOW_PREFIX . $key ] = 'sanitize_checkbox';
		}
		foreach ( array_keys( self::footer_extras() ) as $key ) {
			$map[ self::FOOTER_SHOW_PREFIX . $key ] = 'sanitize_checkbox';
		}

		ksort( $map );

		return $map;
	}

	/**
	 * Snapshot of the chrome settings, for the reset flow.
	 *
	 * @return array<string, mixed>
	 */
	public static function snapshot(): array {
		$data = array();

		foreach ( array_keys( self::settings_map() ) as $id ) {
			$value = get_theme_mod( $id, null );
			if ( null !== $value ) {
				$data[ $id ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Restore a snapshot written by `snapshot()`.
	 *
	 * @param array<string, mixed> $data Snapshot.
	 */
	public static function restore( array $data ): void {
		foreach ( $data as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'flavor_' ) ) {
				set_theme_mod( sanitize_key( (string) $key ), $value );
			}
		}
	}
}
