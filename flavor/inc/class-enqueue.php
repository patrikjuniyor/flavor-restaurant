<?php
/**
 * Front-end assets enqueueing.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Enqueue
 */
class Enqueue {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'front' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'elementor_widgets' ), 20 );

		/*
		The motion layer is an overlay: it has to land after the skin sheets
			and after premium.css so that a rule it shares a selector with
			(`.flavor-filters__chip`, say) resolves in its favour. Skin art
			direction still wins on specificity, which is what we want.
			*/
		add_action( 'wp_enqueue_scripts', array( self::class, 'motion' ), 50 );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor' ) );
		add_action( 'wp_head', array( self::class, 'preload' ), 1 );
		add_filter( 'wp_lazy_loading_enabled', array( self::class, 'keep_hero_eager' ), 10, 3 );
	}

	/**
	 * Shared motion layer for all twelve demos.
	 *
	 * The bespoke five keep `demo-animations.*` for their `fd-*` landing
	 * templates; this pair drives the `flavor-*` vocabulary the seven classic
	 * demos use plus every commerce surface all twelve share — including the
	 * dock, mega nav, night-scheme toggle, menu filters, wishlist, quick view
	 * and engagement surfaces that were added after the first animation pass.
	 *
	 * Both files are inert without JavaScript: every rule that can hide or move
	 * content is gated on a class the script sets, so a blocked or failed
	 * script leaves the site exactly as it renders today.
	 */
	public static function motion(): void {
		if ( is_admin() ) {
			return;
		}

		$css = '/assets/css/motion.css';
		$js  = '/assets/js/motion.js';

		if ( ! is_readable( FLAVOR_DIR . $css ) || ! is_readable( FLAVOR_DIR . $js ) ) {
			return;
		}

		/*
		Depend on whatever is actually registered, so this still works if the
			premium layer is ever removed or if a skin sheet is missing.
			*/
		$deps = array( 'flavor-main' );
		foreach ( array( 'flavor-marketing', 'flavor-premium', 'flavor-dark-mode' ) as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) || wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
				$deps[] = $handle;
			}
		}

		$skin = Design::current_skin();
		$skin_handle = 'flavor-skin-' . sanitize_html_class( $skin );
		if ( wp_style_is( $skin_handle, 'registered' ) || wp_style_is( $skin_handle, 'enqueued' ) || wp_style_is( $skin_handle, 'done' ) ) {
			$deps[] = $skin_handle;
		}

		wp_enqueue_style(
			'flavor-motion',
			FLAVOR_URI . $css,
			$deps,
			(string) filemtime( FLAVOR_DIR . $css )
		);

		/*
		Depends on main.js because the toast wrapper in motion.js decorates
			`window.flavorToast`, which main.js is what defines.
			*/
		wp_enqueue_script(
			'flavor-motion-js',
			FLAVOR_URI . $js,
			array( 'flavor-main-js' ),
			(string) filemtime( FLAVOR_DIR . $js ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Preload the primary Persian font.
	 */
	public static function preload(): void {
		$href = FLAVOR_URI . '/assets/fonts/vazirmatn/Vazirmatn-Regular.woff2';
		echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $href ) . '" crossorigin />' . "\n";

		$skin = Design::current_skin();
		if ( Bespoke_Demos::active() || in_array( $skin, array( 'persian-traditional', 'luxury-dining', 'fast-food', 'cafe-bistro', 'pizza-italian', 'bakery-pastry' ), true ) ) {
			$display_font = FLAVOR_URI . '/assets/fonts/estedad/Estedad-Variable.woff2';
			echo '<link rel="preload" as="font" type="font/woff2" href="' . esc_url( $display_font ) . '" crossorigin />' . "\n";
		}
	}

	/**
	 * Front-end CSS/JS — only what the view needs.
	 */
	public static function front(): void {
		wp_enqueue_style(
			'flavor-fonts',
			FLAVOR_URI . '/assets/css/fonts.css',
			array(),
			FLAVOR_VERSION
		);

		wp_enqueue_style(
			'flavor-main',
			FLAVOR_URI . '/assets/css/main.css',
			array( 'flavor-fonts' ),
			FLAVOR_VERSION
		);

		wp_enqueue_style(
			'flavor-marketing',
			FLAVOR_URI . '/assets/css/marketing.css',
			array( 'flavor-main' ),
			FLAVOR_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'flavor-rtl',
				FLAVOR_URI . '/assets/css/rtl.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);
		}

		// Each commercial demo can ship its own art direction without adding
		// unused CSS to other sites. The filename must match the sanitized skin.
		$skin       = Design::current_skin();
		$skin_file  = '/assets/css/skins/' . sanitize_file_name( $skin ) . '.css';
		$skin_path  = FLAVOR_DIR . $skin_file;
		$skin_deps  = is_rtl() ? array( 'flavor-marketing', 'flavor-rtl' ) : array( 'flavor-marketing' );
		if ( Bespoke_Demos::active() ) {
			wp_enqueue_style(
				'flavor-bespoke',
				FLAVOR_URI . '/assets/css/bespoke-demos.css',
				$skin_deps,
				(string) filemtime( FLAVOR_DIR . '/assets/css/bespoke-demos.css' )
			);
			wp_enqueue_style(
				'flavor-demo-animations',
				FLAVOR_URI . '/assets/css/demo-animations.css',
				array( 'flavor-bespoke' ),
				(string) filemtime( FLAVOR_DIR . '/assets/css/demo-animations.css' )
			);
			$skin_deps[] = 'flavor-bespoke';
		}
		if ( is_readable( $skin_path ) ) {
			wp_enqueue_style(
				'flavor-skin-' . sanitize_html_class( $skin ),
				FLAVOR_URI . $skin_file,
				$skin_deps,
				(string) filemtime( $skin_path )
			);
		}

		$menu_page = get_page_by_path( 'menu' );
		$menu_url  = $menu_page ? get_permalink( $menu_page ) : home_url( '/menu/' );

		$rest    = rest_url( 'flavor/v1/' );
		$payload = array(
			'rest'    => esc_url_raw( $rest ),
			'nonce'   => wp_create_nonce( 'wp_rest' ),
			'menuUrl' => esc_url_raw( $menu_url ),
			'hasCore' => Theme_Setup::has_core(),
			'i18n'    => array(
				'add'     => __( 'افزودن', 'flavor' ),
				'loading' => __( 'در حال بارگذاری منو…', 'flavor' ),
				'empty'   => __( 'آیتمی پیدا نشد.', 'flavor' ),
				'offline' => __( 'افزونه Flavor Core فعال نیست. منو از ووکامرس خوانده نمی‌شود.', 'flavor' ),
				// Everything menu.js renders, so the script holds no copy of
				// its own. See menu_strings().
				'menu'    => self::menu_strings(),
				// Copy shared by the flavor-ui-js family (customer.js, auth.js,
				// pages.js, navigation.js). It travels on flavorData rather than on
				// its own handle because navigation.js loads on shells where
				// flavor-ui-js is not registered. See UI::strings().
				'ui'      => UI::strings(),
				// The two demo-page scripts (prompt 21): catering.js and
				// bespoke-demos.js read their copy from here too.
				'catering' => self::catering_strings(),
				'bespoke'  => Bespoke_Demos::strings(),
				// Prompt 53: reservation.js graduated from the debt ledger and
				// the new contact form script reads its copy from these maps.
				'reservation' => self::reservation_strings(),
				'contact'     => self::contact_strings(),
			),
		);

		if ( class_exists( '\\FlavorCore\\WooCommerce\\Currency' ) ) {
			$payload['currency'] = array(
				'storage' => \FlavorCore\WooCommerce\Currency::storage_unit(),
				'display' => \FlavorCore\WooCommerce\Currency::display_unit(),
				'label'   => \FlavorCore\WooCommerce\Currency::display_label(),
			);
		}

		// Card chips label the same dietary flags the filter bar does; the map is
		// PHP's so both stay in the same catalogue.
		$payload['dietaryLabels'] = UI::dietary_labels();

		/*
		 * Number formatting spec (prompt 22), top level rather than inside i18n:
		 * it is a formatting rule, not a translatable string. digits.js and
		 * Currency::format() read the same zero character and the same thousands
		 * separator — tests/ui/digits.mjs proves that by running both sides.
		 */
		$payload['digits'] = UI::digits_config();

		$payload['ajax']     = esc_url_raw( admin_url( 'admin-ajax.php' ) );
		$payload['branchId'] = self::current_branch_id();
		$presentation = UI::context();
		$payload['ui'] = UI::settings();
		$payload['accountUrl'] = esc_url_raw( UI::url( 'account' ) );
		if ( $presentation['id'] ) {
			$payload['orderModes'] = $presentation['modes'];
			$payload['branchName'] = $presentation['name'];
			$payload['tableId'] = $presentation['table_id'];
			$payload['tableLabel'] = $presentation['table'];
			$payload['defaultMode'] = Bespoke_Demos::active() ? ( Bespoke_Demos::demo()['landing']['default_mode'] ?? 'takeaway' ) : 'takeaway';
			if ( $presentation['table_id'] ) {
$payload['defaultMode'] = 'dine_in'; }
		}

		if ( is_page_template( 'page-templates/template-reservation.php' ) ) {
			$payload['reservationBranchIds'] = array();
			foreach ( get_posts( array(
				'post_type' => 'flavor_branch',
				'post_status' => 'publish',
				'posts_per_page' => 50,
				'fields' => 'ids',
			) ) as $branch_id ) {
				if ( UI::reservation_available( (int) $branch_id ) ) {
$payload['reservationBranchIds'][] = (int) $branch_id; }
			}
		}

		/*
		 * The number formatter loads first and everywhere: flavor-main-js depends on
		 * it, so every storefront script that already depends on main (ui.js,
		 * menu.js, premium.js, the catering and demo scripts) can assume
		 * window.FlavorDigits is there. It needs no payload to be useful — see the
		 * header of the file.
		 */
		wp_enqueue_script(
			'flavor-digits',
			FLAVOR_URI . '/assets/js/digits.js',
			array(),
			(string) filemtime( FLAVOR_DIR . '/assets/js/digits.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		/* The nav disclosure CSS (premium.css) collapses submenu panels only
		   once this marker sits on <html>: a shop whose scripts never run
		   would otherwise hide panels no code could reopen. Inline and
		   before every deferred script, so the class lands before first
		   paint and no panel ever flashes hidden-then-visible. */
		wp_add_inline_script( 'flavor-digits', "document.documentElement.classList.add( 'flavor-js' );", 'before' );

		// Enqueue global theme script
		wp_enqueue_script(
			'flavor-main-js',
			FLAVOR_URI . '/assets/js/main.js',
			array( 'flavor-digits' ),
			(string) filemtime( FLAVOR_DIR . '/assets/js/main.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script( 'flavor-main-js', 'flavorData', $payload );

		if ( Bespoke_Demos::active() ) {
			wp_enqueue_script(
				'flavor-bespoke-js',
				FLAVOR_URI . '/assets/js/bespoke-demos.js',
				array( 'flavor-main-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/bespoke-demos.js' ),
				array(
					'in_footer' => true,
					'strategy' => 'defer',
				)
			);
			wp_enqueue_script(
				'flavor-demo-animations-js',
				FLAVOR_URI . '/assets/js/demo-animations.js',
				array( 'flavor-bespoke-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/demo-animations.js' ),
				array(
					'in_footer' => true,
					'strategy' => 'defer',
				)
			);
		}

		// The catering brief stays local to the homepage; no unused planner code
		// is loaded on other demos or on commerce/reservation pages.
		if ( 'catering' === $skin && is_front_page() ) {
			wp_enqueue_script(
				'flavor-catering-js',
				FLAVOR_URI . '/assets/js/catering.js',
				array( 'flavor-bespoke-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/catering.js' ),
				array(
					'in_footer' => true,
					'strategy' => 'defer',
				)
			);
		}

		$is_menu = is_page_template( 'page-templates/template-menu.php' );
		if ( $is_menu ) {
			wp_enqueue_script(
				'flavor-menu',
				FLAVOR_URI . '/assets/js/menu.js',
				array( 'flavor-main-js', 'flavor-ui-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/menu.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			wp_enqueue_style(
				'flavor-search',
				FLAVOR_URI . '/assets/css/search.css',
				array( 'flavor-main' ),
				FLAVOR_VERSION
			);

			wp_enqueue_script(
				'flavor-search',
				FLAVOR_URI . '/assets/js/search.js',
				array( 'flavor-main-js', 'flavor-ui-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/search.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);

			$search_payload         = $payload;
			$search_payload['i18n'] = array_merge(
				$payload['i18n'],
				array(
					'recent'       => __( 'جست‌وجوهای اخیر', 'flavor' ),
					'popular'      => __( 'پرجست‌وجوها', 'flavor' ),
					'noResults'    => __( 'چیزی پیدا نشد.', 'flavor' ),
					'didYouMean'   => __( 'منظورتان این بود؟', 'flavor' ),
					'resultsFound' => __( 'نتیجه برای', 'flavor' ),
					'unavailable'  => __( 'ناموجود', 'flavor' ),
					'minutes'      => __( 'دقیقه', 'flavor' ),
					'kcal'         => __( 'کالری', 'flavor' ),
					'error'        => __( 'جست‌وجو ناموفق بود. دوباره تلاش کنید.', 'flavor' ),
				)
			);

			wp_localize_script( 'flavor-search', 'flavorSearchData', $search_payload );
		}

		if ( is_page_template( 'page-templates/template-reservation.php' ) ) {
			wp_enqueue_script(
				'flavor-reservation',
				FLAVOR_URI . '/assets/js/reservation.js',
				array( 'flavor-main-js', 'flavor-ui-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/reservation.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		if ( is_page_template( 'page-templates/template-contact.php' ) ) {
			wp_enqueue_script(
				'flavor-contact',
				FLAVOR_URI . '/assets/js/contact.js',
				array( 'flavor-main-js', 'flavor-ui-js' ),
				(string) filemtime( FLAVOR_DIR . '/assets/js/contact.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}
	}

	/**
	 * Branch id from the Flavor Core order context, when available.
	 */
	/**
	 * Every piece of copy the customer-facing scripts render.
	 *
	 * menu.js used to hold its own Persian literals, which meant the front-end
	 * text could not be translated at all: an English or Arabic site showed
	 * Persian buttons on an otherwise translated page. The script now reads this
	 * map from flavorData.i18n.menu and carries no UI text of its own, so the
	 * catalogue is the only place a string exists. tests/theme/test-js-i18n.php
	 * fails if a script asks for a key that is not here — a missing entry should
	 * be a broken build, not a shopper staring at "add_to_cart".
	 *
	 * Placeholder tokens are positional (%s, %1$s, %2$s) because the script
	 * substitutes already-escaped values into them; a translator may move a token
	 * inside the sentence but not drop it.
	 *
	 * @return array<string,string>
	 */
	/**
	 * Copy catering.js prints on the catering homepage. The script keeps none of
	 * its own (prompt 21) — the summary it builds is assembled from these lines,
	 * so an untranslated release would hand a Persian text file to a customer
	 * who reads Arabic.
	 */
	public static function catering_strings(): array {
		return array(
			'guests_range'     => __( 'تعداد پیشنهادی را بین ۱ تا ۱۰۰۰ وارد کنید؛ برای تعداد بیشتر تماس بگیرید.', 'flavor' ),
			'field_required'   => __( 'لطفاً این بخش را تکمیل کنید.', 'flavor' ),
			'summary_title'    => __( 'خلاصه برای هماهنگی پذیرایی', 'flavor' ),
			/* translators: %s values. */
			'service_line'     => __( 'نوع پذیرایی: %s', 'flavor' ),
			/* translators: %s values. */
			'guests_line'      => __( 'تعداد پیشنهادی: %s نفر', 'flavor' ),
			/* translators: %s values. */
			'date_line'        => __( 'روز و ساعت پیشنهادی: %s', 'flavor' ),
			/* translators: %s values. */
			'location_line'    => __( 'شهر و محل: %s', 'flavor' ),
			/* translators: %s values. */
			'notes_line'       => __( 'توضیحات و نیاز غذایی: %s', 'flavor' ),
			'notes_none'       => __( 'در گفت‌وگو مشخص می‌شود.', 'flavor' ),
			'summary_notice'   => __( 'این خلاصه ارسال نشده است؛ ظرفیت، هزینه و شرایط اجرا به تأیید جداگانه نیاز دارند.', 'flavor' ),
			'summary_copied'   => __( 'خلاصه کپی شد؛ برای هماهنگی در اختیار تیم بگذارید. هنوز هیچ درخواستی ارسال نشده.', 'flavor' ),
			'copy_manual'      => __( 'کپی خودکار در این مرورگر ممکن نیست. متن انتخاب شده است؛ آن را دستی کپی کنید. هیچ درخواستی ارسال نشده.', 'flavor' ),
		);
	}

		public static function menu_strings(): array {
		return array(
			// Menu cards and the category bar.
			'currency'          => __( 'تومان', 'flavor' ),
			'no_image'          => __( 'بدون تصویر', 'flavor' ),
			/* translators: %s values. */
			'prep_time'         => __( '%s دقیقه آماده‌سازی', 'flavor' ),
			/* translators: %s values. */
			'calories'          => __( '%s کالری', 'flavor' ),
			/* translators: %s values. */
			'details'           => __( 'جزئیات %s', 'flavor' ),
			'unavailable'       => __( 'فعلاً ناموجود', 'flavor' ),
			/* translators: %s values. */
			'choose'            => __( 'انتخاب %s', 'flavor' ),
			'choose_dish'       => __( 'انتخاب غذا', 'flavor' ),
			'all_menu'          => __( 'همهٔ منو', 'flavor' ),
			/* translators: %s values. */
			'shown_count'       => __( '%s انتخاب نمایش داده می‌شود.', 'flavor' ),
			/* translators: %s values. */
			'menu_count'        => __( '%s انتخاب در منو', 'flavor' ),
			'load_failed'       => __( 'بارگذاری موجودی زنده ناموفق بود؛ جزئیات منتشرشده را ببینید یا دوباره تلاش کنید.', 'flavor' ),

			// The modifier sheet.
			'saving'            => __( 'در حال ذخیرهٔ انتخاب‌ها…', 'flavor' ),
			'choice_unavailable' => __( 'این انتخاب فعلاً ناموجود است', 'flavor' ),
			'save_changes'      => __( 'ذخیرهٔ تغییرات', 'flavor' ),
			'add_to_cart'       => __( 'افزودن به سبد', 'flavor' ),
			/* translators: %s values. */
			'unit_count'        => __( '%s عدد', 'flavor' ),
			'max_qty'           => __( 'حداکثر ۲۰ واحد از یک انتخاب؛ تعداد را کم کنید.', 'flavor' ),
			'need_required'     => __( 'برای ادامه، انتخاب‌های ضروری این غذا را مشخص کنید.', 'flavor' ),
			'no_sheet'          => __( 'پنل جزئیات در دسترس نیست. صفحه را دوباره باز کنید.', 'flavor' ),
			'prep_note'         => __( 'زمان آماده‌سازی برآورد است، نه زمان تضمین‌شدهٔ رسیدن سفارش.', 'flavor' ),
			'edit_note'         => __( 'انتخاب فعلی این ردیف را ویرایش می‌کنید؛ تا زدن «ذخیرهٔ تغییرات» چیزی در سبد عوض نمی‌شود.', 'flavor' ),
			'group_size'        => __( 'اندازه و پرس', 'flavor' ),
			'group_topping'     => __( 'افزودنی‌ها', 'flavor' ),
			'group_cook'        => __( 'درجهٔ پخت', 'flavor' ),
			'group_removal'     => __( 'حذف مخلفات', 'flavor' ),
			'group_choices'     => __( 'انتخاب‌ها', 'flavor' ),
			// No leading space in the msgid: a catalogue key with whitespace around
			// it is stripped at build time, and the stripped key is what gettext then
			// looks up — a padded msgid would silently never translate. The space is
			// markup, so it belongs in the template.
			'required_suffix'   => __( '(انتخاب ضروری)', 'flavor' ),
			'no_change'         => __( 'بدون تغییر در این گروه', 'flavor' ),
			'no_fee'            => __( 'بدون هزینهٔ اضافه', 'flavor' ),
			/* translators: %s values. */
			'per_unit'          => __( '%s برای هر واحد', 'flavor' ),
			'kitchen_note'      => __( 'یادداشت برای آشپزخانه', 'flavor' ),
			'optional'          => __( '(اختیاری)', 'flavor' ),
			'note_placeholder'  => __( 'مثلاً سس جداگانه یا کم‌نمک', 'flavor' ),
			'note_help'         => __( 'حساسیت غذایی دارید؟ پیش از ثبت سفارش دربارهٔ امکان آماده‌سازی و آلودگی متقاطع هماهنگ کنید.', 'flavor' ),
			/* translators: %s values. */
			'added_to_cart'     => __( '«%s» به سبد خرید اضافه شد.', 'flavor' ),
			'dish_added'        => __( 'غذا به سبد اضافه شد.', 'flavor' ),
			'choices_saved'     => __( 'انتخاب‌های این غذا در سبد ذخیره شد.', 'flavor' ),
			'choice_gone'       => __( 'این انتخاب در حال حاضر موجود نیست.', 'flavor' ),

			// Cart drawer.
			'cart_empty'        => __( 'هنوز انتخابی ندارید', 'flavor' ),
			// Trailing space stripped for the same reason; the joiner adds it.
			'mod_separator'     => __( '،', 'flavor' ),
			/* translators: %s values. */
			'line_note'         => __( 'یادداشت: %s', 'flavor' ),
			'edit_line'         => __( 'ویرایش انتخاب‌ها', 'flavor' ),
			/* translators: %s values. */
			'edit_line_aria'    => __( 'ویرایش انتخاب‌ها و یادداشت %s', 'flavor' ),
			/* translators: %s values. */
			'quantity_aria'     => __( 'تعداد %s', 'flavor' ),
			/* translators: %s values. */
			'decrease_aria'     => __( 'کاهش تعداد %s', 'flavor' ),
			/* translators: %s values. */
			'increase_aria'     => __( 'افزایش تعداد %s', 'flavor' ),
			'remove'            => __( 'حذف', 'flavor' ),
			/* translators: %s values. */
			'remove_aria'       => __( 'حذف %s', 'flavor' ),
			'shipping_row'      => __( 'ارسال ثبت‌شده در سبد', 'flavor' ),
			/* translators: %s values. */
			'remove_coupon'     => __( 'حذف کد %s ×', 'flavor' ),
			/* translators: %s values. */
			'cart_updated'      => __( 'سبد به‌روزرسانی شد؛ %s انتخاب.', 'flavor' ),
			'total_note'        => __( 'مبالغ از سرور خوانده می‌شوند؛ هزینه‌های تأییدنشده به جمع سبد اضافه نمی‌شوند.', 'flavor' ),
			/* translators: %s values. */
			'cart_fetch_failed' => __( 'دریافت سبد ناموفق بود؛ صفحه را دوباره باز کنید. %s', 'flavor' ),

			// Order mode, payment and delivery zone.
			'no_mode'           => __( 'برای این شعبه روش سفارش فعالی تنظیم نشده است؛ با مجموعه تماس بگیرید.', 'flavor' ),
			'pay_methods'       => __( 'روش پرداخت', 'flavor' ),
			'pay_loading'       => __( 'در حال دریافت روش‌های مجاز…', 'flavor' ),
			'pay_none'          => __( 'برای این روش دریافت، پرداخت فعالی تنظیم نشده است. با مجموعه تماس بگیرید.', 'flavor' ),
			'pay_failed'        => __( 'دریافت روش‌های پرداخت ناموفق بود.', 'flavor' ),
			'table_pick'        => __( 'میز را انتخاب کنید', 'flavor' ),
			/* translators: %s values. */
			'table_number'      => __( 'میز %s', 'flavor' ),
			'zone_checking'     => __( 'در حال بررسی محدودهٔ ارسال…', 'flavor' ),
			/* translators: %1$s, %2$s values. */
			'zone_ok'           => __( 'محدوده تأیید شد · هزینهٔ ارسال: %1$s · برآورد منطقه: حدود %2$s دقیقه؛ زمان تضمین‌شده نیست.', 'flavor' ),
			'zone_out'          => __( 'نشانی خارج از محدودهٔ ارسال است.', 'flavor' ),
			'zone_note'         => __( 'هزینهٔ منطقه جداگانه نمایش داده شده و در مبلغ فعلی سبد منظور نشده است؛ سرور مبلغ نهایی را هنگام ثبت بررسی می‌کند.', 'flavor' ),
			'coupon_empty'      => __( 'کد تخفیف را وارد کنید.', 'flavor' ),
			'coupon_applied'    => __( 'کد تخفیف اعمال شد.', 'flavor' ),

			// Placing the order.
			'mobile_invalid'    => __( 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.', 'flavor' ),
			'zone_unconfirmed'  => __( 'محدودهٔ ارسال هنوز تأیید نشده است؛ شهر و محله را بررسی کنید.', 'flavor' ),
			'pay_required'      => __( 'یک روش پرداخت فعال را انتخاب کنید.', 'flavor' ),
			'placing'           => __( 'در حال بررسی و ثبت…', 'flavor' ),
			'bad_redirect'      => __( 'نشانی ادامهٔ پرداخت معتبر نیست؛ با مجموعه تماس بگیرید.', 'flavor' ),
			/* translators: %s values. */
			'order_placed'      => __( 'سفارش %s ثبت شد.', 'flavor' ),
			'place_order'       => __( 'ثبت سفارش و ادامهٔ پرداخت', 'flavor' ),
		);
	}

	/**
	 * Every string reservation.js renders (prompt 53).
	 *
	 * The script used to be on the translation-debt ledger with its own
	 * Persian literals; it now reads this map from flavorData.i18n.reservation.
	 * Validation copy states the fix, not just the failure — «شمارهٔ موبایل
	 * باید ۱۱ رقم و با ۰۹ شروع شود» instead of a bare «خطا».
	 */
	public static function reservation_strings(): array {
		return array(
			// Calendar and live capacity.
			'day_selected'      => __( 'روز انتخاب شد؛ یک ساعت دارای ظرفیت انتخاب کنید.', 'flavor' ),
			'checking_capacity' => __( 'در حال بررسی ظرفیت…', 'flavor' ),
			'no_capacity'       => __( 'برای این تاریخ و انتخاب، ظرفیت قابل رزرو وجود ندارد. تاریخ یا بخش دیگری را بررسی کنید.', 'flavor' ),
			'capacity_failed'   => __( 'بررسی ظرفیت ناموفق بود.', 'flavor' ),
			/* translators: %s value. */
			'time_selected'     => __( 'ساعت انتخاب‌شده: %s؛ هنوز رزروی ارسال نشده است.', 'flavor' ),

			// Section names from Core's table taxonomy.
			'section_any'       => __( 'هر بخشِ دارای ظرفیت', 'flavor' ),
			'section_indoor'    => __( 'سالن', 'flavor' ),
			'section_outdoor'   => __( 'فضای باز', 'flavor' ),
			'section_window'    => __( 'کنار پنجره', 'flavor' ),
			'section_bar'       => __( 'بار', 'flavor' ),

			// Submission validation — each message carries its solution.
			'pick_datetime'     => __( 'تاریخ و ساعت را از تقویم و فهرست ساعت‌ها انتخاب کنید.', 'flavor' ),
			'party_range'       => __( 'تعداد مهمان باید بین ۱ و ۲۰ باشد؛ برای تعداد بیشتر با مجموعه تماس بگیرید.', 'flavor' ),
			'name_required'     => __( 'نام خود را وارد کنید؛ بدون نام، درخواست قابل پیگیری نیست.', 'flavor' ),
			'mobile_invalid'    => __( 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.', 'flavor' ),
			'sending'           => __( 'در حال ارسال درخواست…', 'flavor' ),

			// Result.
			'status_confirmed'  => __( 'تأیید شده', 'flavor' ),
			'status_pending'    => __( 'در انتظار تأیید', 'flavor' ),
			'status_cancelled'  => __( 'لغوشده', 'flavor' ),
			/* translators: %1$s jalali date, %2$s time, %3$s status. */
			'request_recorded'  => __( 'درخواست ثبت شد (%1$s ساعت %2$s). وضعیت: %3$s', 'flavor' ),
		);
	}

	/**
	 * Every string contact.js renders (prompt 53).
	 *
	 * The contact form is the theme's first submitting form: the endpoint
	 * lives in Contact_Form, the copy lives here, and the script itself
	 * carries no text of its own.
	 */
	public static function contact_strings(): array {
		return array(
			'name_required'  => __( 'نام خود را وارد کنید تا پیام قابل پیگیری باشد.', 'flavor' ),
			'message_short'  => __( 'پیام را کامل‌تر بنویسید؛ حداقل ۱۰ نویسه لازم است.', 'flavor' ),
			'reply_required' => __( 'شمارهٔ موبایل یا ایمیل را وارد کنید تا بتوانیم پاسخ دهیم.', 'flavor' ),
			'mobile_invalid' => __( 'شمارهٔ موبایل باید ۱۱ رقم و با ۰۹ شروع شود؛ مانند ۰۹۱۲۱۲۳۴۵۶۷.', 'flavor' ),
			'email_invalid'  => __( 'قالب ایمیل درست نیست؛ کامل وارد کنید، مانند name@example.com.', 'flavor' ),
			'sending'        => __( 'در حال ارسال پیام…', 'flavor' ),
			'sent'           => __( 'پیام شما ارسال شد؛ از راهی که وارد کرده‌اید پاسخ می‌دهیم.', 'flavor' ),
			'send_failed'    => __( 'ارسال پیام ناموفق بود؛ کمی بعد دوباره تلاش کنید یا تلفنی هماهنگ کنید.', 'flavor' ),
		);
	}

	public static function current_branch_id(): int {
		if ( class_exists( \FlavorCore\Order\OrderModes::class ) ) {
			$ctx = \FlavorCore\Order\OrderModes::get();
			if ( is_array( $ctx ) && ! empty( $ctx['branch_id'] ) && 'flavor_branch' === get_post_type( (int) $ctx['branch_id'] ) && 'publish' === get_post_status( (int) $ctx['branch_id'] ) ) {
				return (int) $ctx['branch_id'];
			}
		}
		return class_exists( \FlavorCore\PostTypes\BranchPostType::class ) ? \FlavorCore\PostTypes\BranchPostType::default_id() : 0;
	}

	/**
	 * Editor styles.
	 */
	public static function editor(): void {
		wp_enqueue_style(
			'flavor-fonts',
			FLAVOR_URI . '/assets/css/fonts.css',
			array(),
			FLAVOR_VERSION
		);
	}

	/**
	 * Hero images stay eager for LCP.
	 *
	 * @param bool   $default Default.
	 * @param string $tag     Tag.
	 * @param string $context Context.
	 */
	public static function keep_hero_eager( bool $default, string $tag, string $context ): bool {
		unset( $tag, $context );
		return $default;
	}

	/**
	 * Elementor widget styling, only when Elementor is actually active.
	 *
	 * Elementor loads its own base stylesheet for a widget but not a theme's
	 * opinion about how that widget should look. Registering here rather than
	 * in Elementor's own hook keeps the file off every site that does not use
	 * the page builder.
	 */
	public static function elementor_widgets(): void {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		$css = '/assets/css/elementor-widgets.css';
		$path = FLAVOR_DIR . $css;

		wp_enqueue_style(
			'flavor-elementor-widgets',
			FLAVOR_URI . $css,
			array( 'flavor-main' ),
			is_file( $path ) ? (string) filemtime( $path ) : FLAVOR_VERSION
		);
	}
}
