<?php
/**
 * Flavor Builder — the theme's built-in drag-and-drop page builder.
 *
 * Ported from Rasta Commerce "Rasta Builder" (inc/builder.php) and adapted
 * for the restaurant: the shop elements are replaced with menu elements
 * (WooCommerce food items) plus a reservation call-to-action.
 *
 * Editors compose a page from registered building blocks. The layout is
 * stored as an array in post meta (`_flavor_builder_data`) and rendered
 * server-side on the front end, so no JavaScript is required for visitors.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Builder
 */
class Builder {

	/**
	 * Post meta key holding the layout.
	 */
	public const META = '_flavor_builder_data';

	/**
	 * Nonce action used when saving.
	 */
	public const NONCE = 'flavor_builder_save';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register_metabox' ) );
		add_action( 'save_post', array( self::class, 'save' ) );
		add_action( 'init', array( self::class, 'register_shortcode' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'frontend_styles' ) );
		add_filter( 'the_content', array( self::class, 'render_content' ), 9 );
	}

	/* ─── Element registry ─────────────────────────────────────────────── */

	/**
	 * Return the registered builder elements.
	 *
	 * Each element declares:
	 *  - label       (string) human-readable name
	 *  - icon        (string) dashicons class
	 *  - category    (string) grouping
	 *  - fields      (array)  editable fields { key, label, type }
	 *  - defaults    (array)  default values keyed by field key
	 *  - render      (callable) receives the props array, returns escaped HTML
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function elements(): array {
		$elements = array(
			'heading'         => array(
				'label'    => __( 'تیتر', 'flavor' ),
				'icon'     => 'dashicons-heading',
				'category' => __( 'محتوا', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'متن تیتر', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'level', 'label' => __( 'سطح', 'flavor' ), 'type' => 'select', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ) ),
					array( 'key' => 'align', 'label' => __( 'تراز', 'flavor' ), 'type' => 'select', 'options' => array( 'start' => __( 'راست', 'flavor' ), 'center' => __( 'وسط', 'flavor' ), 'end' => __( 'چپ', 'flavor' ) ) ),
				),
				'defaults' => array( 'title' => __( 'یک تیتر جدید', 'flavor' ), 'level' => 'h2', 'align' => 'center' ),
				'render'   => function ( $p ) {
					$level = in_array( $p['level'], array( 'h2', 'h3', 'h4' ), true ) ? $p['level'] : 'h2';
					$align = in_array( $p['align'], array( 'start', 'center', 'end' ), true ) ? $p['align'] : 'center';
					return sprintf( '<%1$s class="fb-heading" style="text-align:%2$s">%3$s</%1$s>', $level, esc_attr( $align ), esc_html( $p['title'] ) );
				},
			),
			'text'            => array(
				'label'    => __( 'متن', 'flavor' ),
				'icon'     => 'dashicons-text',
				'category' => __( 'محتوا', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'content', 'label' => __( 'متن', 'flavor' ), 'type' => 'textarea' ),
				),
				'defaults' => array( 'content' => __( 'متن خود را اینجا بنویسید…', 'flavor' ) ),
				'render'   => function ( $p ) {
					return '<div class="fb-text">' . wpautop( esc_html( $p['content'] ) ) . '</div>';
				},
			),
			'button'          => array(
				'label'    => __( 'دکمه', 'flavor' ),
				'icon'     => 'dashicons-button',
				'category' => __( 'محتوا', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'label', 'label' => __( 'متن دکمه', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'url', 'label' => __( 'پیوند', 'flavor' ), 'type' => 'url' ),
					array( 'key' => 'align', 'label' => __( 'تراز', 'flavor' ), 'type' => 'select', 'options' => array( 'start' => __( 'راست', 'flavor' ), 'center' => __( 'وسط', 'flavor' ), 'end' => __( 'چپ', 'flavor' ) ) ),
				),
				'defaults' => array( 'label' => __( 'مشاهدهٔ منو', 'flavor' ), 'url' => '#', 'align' => 'center' ),
				'render'   => function ( $p ) {
					$align = in_array( $p['align'], array( 'start', 'center', 'end' ), true ) ? $p['align'] : 'center';
					return sprintf( '<div class="fb-button" style="text-align:%1$s"><a class="fb-btn" href="%2$s">%3$s</a></div>', esc_attr( $align ), esc_url( $p['url'] ), esc_html( $p['label'] ) );
				},
			),
			'image'           => array(
				'label'    => __( 'تصویر', 'flavor' ),
				'icon'     => 'dashicons-format-image',
				'category' => __( 'محتوا', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'url', 'label' => __( 'نشانی تصویر', 'flavor' ), 'type' => 'url' ),
					array( 'key' => 'alt', 'label' => __( 'متن جایگزین', 'flavor' ), 'type' => 'text' ),
				),
				'defaults' => array( 'url' => '', 'alt' => '' ),
				'render'   => function ( $p ) {
					if ( ! $p['url'] ) {
						return '';
					}
					return sprintf( '<div class="fb-image"><img src="%1$s" alt="%2$s" loading="lazy" /></div>', esc_url( $p['url'] ), esc_attr( $p['alt'] ) );
				},
			),
			'divider'         => array(
				'label'    => __( 'جداکننده', 'flavor' ),
				'icon'     => 'dashicons-minus',
				'category' => __( 'چیدمان', 'flavor' ),
				'fields'   => array(),
				'defaults' => array(),
				'render'   => function () {
					return '<hr class="fb-divider" />';
				},
			),
			'spacer'          => array(
				'label'    => __( 'فاصله', 'flavor' ),
				'icon'     => 'dashicons-editor-expand',
				'category' => __( 'چیدمان', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'height', 'label' => __( 'ارتفاع (پیکسل)', 'flavor' ), 'type' => 'number' ),
				),
				'defaults' => array( 'height' => 40 ),
				'render'   => function ( $p ) {
					$h = max( 8, absint( $p['height'] ) );
					return sprintf( '<div class="fb-spacer" style="height:%1$dpx" aria-hidden="true"></div>', $h );
				},
			),
			'cta'             => array(
				'label'    => __( 'فراخوان اقدام', 'flavor' ),
				'icon'     => 'dashicons-megaphone',
				'category' => __( 'بخش‌ها', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'تیتر', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'text', 'label' => __( 'توضیح', 'flavor' ), 'type' => 'textarea' ),
					array( 'key' => 'btn', 'label' => __( 'متن دکمه', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'url', 'label' => __( 'پیوند دکمه', 'flavor' ), 'type' => 'url' ),
				),
				'defaults' => array( 'title' => __( 'همین حالا سفارش دهید', 'flavor' ), 'text' => __( 'سفارش آنلاین، سریع و تازه، مستقیم از آشپزخانهٔ ما.', 'flavor' ), 'btn' => __( 'شروع سفارش', 'flavor' ), 'url' => '#' ),
				'render'   => function ( $p ) {
					return '<div class="fb-cta"><h3>' . esc_html( $p['title'] ) . '</h3><p>' . esc_html( $p['text'] ) . '</p><a class="fb-btn fb-btn--light" href="' . esc_url( $p['url'] ) . '">' . esc_html( $p['btn'] ) . '</a></div>';
				},
			),
			'features'        => array(
				'label'    => __( 'لیست ویژگی', 'flavor' ),
				'icon'     => 'dashicons-yes',
				'category' => __( 'بخش‌ها', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'عنوان بخش', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'items', 'label' => __( 'ویژگی‌ها (هر خط: عنوان | توضیح)', 'flavor' ), 'type' => 'textarea' ),
				),
				'defaults' => array( 'title' => __( 'چرا ما؟', 'flavor' ), 'items' => "مواد تازه | تهیهٔ روزانه از بازار محلی\nارسال سریع | تحویل داغ در کمتر از ۴۵ دقیقه\nپخت لحظه‌ای | سفارش شما همان لحظه آماده می‌شود" ),
				'render'   => function ( $p ) {
					$items = array_filter( array_map( 'trim', explode( "\n", (string) $p['items'] ) ) );
					$html  = '<div class="fb-features"><h3>' . esc_html( $p['title'] ) . '</h3><ul>';
					foreach ( $items as $line ) {
						$parts = array_map( 'trim', explode( '|', $line, 2 ) );
						$html .= '<li><strong>' . esc_html( $parts[0] ) . '</strong>';
						if ( isset( $parts[1] ) && '' !== $parts[1] ) {
							$html .= '<span>' . esc_html( $parts[1] ) . '</span>';
						}
						$html .= '</li>';
					}
					return $html . '</ul></div>';
				},
			),
			'testimonials'    => array(
				'label'    => __( 'نظرات مشتریان', 'flavor' ),
				'icon'     => 'dashicons-format-quote',
				'category' => __( 'بخش‌ها', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'عنوان بخش', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'items', 'label' => __( 'نظرات (هر خط: نام | نظر)', 'flavor' ), 'type' => 'textarea' ),
				),
				'defaults' => array( 'title' => __( 'مشتریان چه می‌گویند؟', 'flavor' ), 'items' => "علی رضایی | کیفیت غذا فوق‌العاده بود.\nسارا احمدی | ارسال سریع و بسته‌بندی عالی." ),
				'render'   => function ( $p ) {
					$items = array_filter( array_map( 'trim', explode( "\n", (string) $p['items'] ) ) );
					$html  = '<div class="fb-testimonials"><h3>' . esc_html( $p['title'] ) . '</h3><div class="fb-testimonials__grid">';
					foreach ( $items as $line ) {
						$parts = array_map( 'trim', explode( '|', $line, 2 ) );
						$html .= '<blockquote><p>' . esc_html( isset( $parts[1] ) ? $parts[1] : $parts[0] ) . '</p><cite>' . esc_html( $parts[0] ) . '</cite></blockquote>';
					}
					return $html . '</div></div>';
				},
			),
			'menu_grid'       => array(
				'label'    => __( 'شبکهٔ منو', 'flavor' ),
				'icon'     => 'dashicons-food',
				'category' => __( 'منو', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'عنوان بخش', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'limit', 'label' => __( 'تعداد آیتم‌ها', 'flavor' ), 'type' => 'number' ),
				),
				'defaults' => array( 'title' => __( 'پرفروش‌ترین‌ها', 'flavor' ), 'limit' => 4 ),
				'render'   => function ( $p ) {
					$limit = max( 1, absint( $p['limit'] ) );
					$items = self::menu_items( $limit );
					$html  = '<div class="fb-menu"><h3>' . esc_html( $p['title'] ) . '</h3>';
					if ( empty( $items ) ) {
						return $html . '<p class="fb-empty">' . esc_html__( 'هنوز آیتمی در منو منتشر نشده است.', 'flavor' ) . '</p></div>';
					}
					$html .= '<ul class="fb-menu__grid">';
					foreach ( $items as $item ) {
						$html .= '<li><a class="fb-menu__img" href="' . esc_url( $item['url'] ) . '">';
						if ( $item['image'] ) {
							$html .= '<img src="' . esc_url( $item['image'] ) . '" alt="' . esc_attr( $item['name'] ) . '" loading="lazy" />';
						}
						$html .= '</a><a class="fb-menu__name" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['name'] ) . '</a>';
						if ( $item['price'] ) {
							$html .= '<span class="fb-menu__price">' . wp_kses_post( $item['price'] ) . '</span>';
						}
						$html .= '</li>';
					}
					return $html . '</ul></div>';
				},
			),
			'menu_categories' => array(
				'label'    => __( 'دسته‌بندی‌های منو', 'flavor' ),
				'icon'     => 'dashicons-category',
				'category' => __( 'منو', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'عنوان بخش', 'flavor' ), 'type' => 'text' ),
				),
				'defaults' => array( 'title' => __( 'دسته‌بندی‌های منو', 'flavor' ) ),
				'render'   => function ( $p ) {
					$terms = self::menu_categories_terms( 6 );
					$html  = '<div class="fb-categories"><h3>' . esc_html( $p['title'] ) . '</h3>';
					if ( empty( $terms ) ) {
						return $html . '<p class="fb-empty">' . esc_html__( 'دسته‌بندی‌ای یافت نشد.', 'flavor' ) . '</p></div>';
					}
					$html .= '<ul class="fb-categories__grid">';
					foreach ( $terms as $term ) {
						$link = get_term_link( $term );
						if ( is_wp_error( $link ) ) {
							continue;
						}
						$html .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a></li>';
					}
					return $html . '</ul></div>';
				},
			),
			'reservation'     => array(
				'label'    => __( 'رزرو میز', 'flavor' ),
				'icon'     => 'dashicons-calendar-alt',
				'category' => __( 'منو', 'flavor' ),
				'fields'   => array(
					array( 'key' => 'title', 'label' => __( 'تیتر', 'flavor' ), 'type' => 'text' ),
					array( 'key' => 'text', 'label' => __( 'توضیح', 'flavor' ), 'type' => 'textarea' ),
					array( 'key' => 'btn', 'label' => __( 'متن دکمه', 'flavor' ), 'type' => 'text' ),
				),
				'defaults' => array( 'title' => __( 'میز خود را رزرو کنید', 'flavor' ), 'text' => __( 'برای تجربهٔ یک شام بی‌نقص، از قبل میز خود را ثبت کنید.', 'flavor' ), 'btn' => __( 'رزرو میز', 'flavor' ) ),
				'render'   => function ( $p ) {
					$url = self::reservation_url();
					return '<div class="fb-reserve"><h3>' . esc_html( $p['title'] ) . '</h3><p>' . esc_html( $p['text'] ) . '</p><a class="fb-btn fb-btn--light" href="' . esc_url( $url ) . '">' . esc_html( $p['btn'] ) . '</a></div>';
				},
			),
		);

		/**
		 * Filter the registered builder elements.
		 *
		 * @param array<string, array<string, mixed>> $elements Element registry.
		 */
		return (array) apply_filters( 'flavor_builder_elements', $elements );
	}

	/**
	 * Get a single element definition by slug.
	 *
	 * @param string $slug Element slug.
	 * @return array<string, mixed>|null
	 */
	public static function get_element( string $slug ): ?array {
		$elements = self::elements();
		return isset( $elements[ $slug ] ) ? $elements[ $slug ] : null;
	}

	/* ─── Data helpers (restaurant adaptations) ────────────────────────── */

	/**
	 * Fetch published menu items (WooCommerce products) for the grid element.
	 *
	 * @param int $limit Max items.
	 * @return array<int, array{name:string,url:string,image:string,price:string}>
	 */
	private static function menu_items( int $limit ): array {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$query = new \WP_Query(
			array(
				'post_type'           => 'product',
				'post_status'         => 'publish',
				'posts_per_page'      => max( 1, $limit ),
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$price = '';
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $post->ID );
				if ( $product ) {
					$price = $product->get_price_html();
				}
			}
			$items[] = array(
				'name'  => get_the_title( $post ),
				'url'   => (string) get_permalink( $post ),
				'image' => (string) get_the_post_thumbnail_url( $post, 'medium' ),
				'price' => $price,
			);
		}
		wp_reset_postdata();

		return $items;
	}

	/**
	 * Fetch menu (product) categories for the categories element.
	 *
	 * @param int $limit Max terms.
	 * @return array<int, \WP_Term>
	 */
	private static function menu_categories_terms( int $limit ): array {
		if ( ! taxonomy_exists( 'product_cat' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'number'     => max( 1, $limit ),
				'hide_empty' => true,
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Resolve the reservation page URL, following the theme's conventions.
	 *
	 * @return string
	 */
	private static function reservation_url(): string {
		$page = get_page_by_path( 'reservation' );
		$url  = $page ? get_permalink( $page ) : home_url( '/reservation/' );

		/**
		 * Filter the reservation button URL used by the builder element.
		 *
		 * @param string $url Reservation page URL.
		 */
		return (string) apply_filters( 'flavor_builder_reservation_url', $url );
	}

	/* ─── Layout rendering ─────────────────────────────────────────────── */

	/**
	 * Render a builder layout (list of elements) to HTML.
	 *
	 * @param array $elements Layout: list of { type, props }.
	 * @return string
	 */
	public static function render_layout( $elements ): string {
		$registry = self::elements();
		$output   = '';

		foreach ( (array) $elements as $block ) {
			$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
			if ( ! isset( $registry[ $type ] ) ) {
				continue;
			}
			$def      = $registry[ $type ];
			$props    = isset( $block['props'] ) && is_array( $block['props'] ) ? $block['props'] : array();
			$props    = wp_parse_args( $props, $def['defaults'] );
			$rendered = is_callable( $def['render'] ) ? call_user_func( $def['render'], $props ) : '';

			if ( '' === $rendered ) {
				continue;
			}

			$output .= '<div class="fb-block fb-block--' . esc_attr( $type ) . '">' . $rendered . '</div>';
		}

		return $output;
	}

	/**
	 * Sanitize a builder layout for storage.
	 *
	 * @param mixed $input Raw layout (JSON string or array).
	 * @return array Sanitized layout.
	 */
	public static function sanitize_layout( $input ): array {
		if ( is_string( $input ) ) {
			$input = json_decode( $input, true );
		}

		if ( ! is_array( $input ) ) {
			return array();
		}

		$registry = self::elements();
		$clean    = array();

		foreach ( $input as $block ) {
			if ( ! is_array( $block ) || empty( $block['type'] ) ) {
				continue;
			}
			$type = sanitize_key( $block['type'] );
			if ( ! isset( $registry[ $type ] ) ) {
				continue;
			}
			$def   = $registry[ $type ];
			$props = array();
			foreach ( $def['fields'] as $field ) {
				$key = $field['key'];
				$val = isset( $block['props'][ $key ] ) ? $block['props'][ $key ] : ( isset( $def['defaults'][ $key ] ) ? $def['defaults'][ $key ] : '' );

				if ( 'number' === $field['type'] ) {
					$props[ $key ] = absint( $val );
				} elseif ( 'url' === $field['type'] ) {
					$props[ $key ] = esc_url_raw( (string) $val );
				} elseif ( 'textarea' === $field['type'] ) {
					$props[ $key ] = sanitize_textarea_field( (string) $val );
				} elseif ( 'select' === $field['type'] ) {
					$allowed         = array_keys( $field['options'] );
					$props[ $key ]   = in_array( $val, $allowed, true ) ? $val : $def['defaults'][ $key ];
				} else {
					$props[ $key ] = sanitize_text_field( (string) $val );
				}
			}
			$clean[] = array(
				'type'  => $type,
				'props' => $props,
			);
		}

		return $clean;
	}

	/* ─── Meta box (admin editor) ──────────────────────────────────────── */

	/**
	 * Post types that get the builder meta box.
	 *
	 * @return array<int, string>
	 */
	public static function supported_post_types(): array {
		/**
		 * Filter the post types the builder is available for.
		 *
		 * @param array<int, string> $post_types Post type slugs.
		 */
		return (array) apply_filters( 'flavor_builder_post_types', array( 'page', 'post', 'flavor_branch' ) );
	}

	/**
	 * Register the builder meta box on supported post types.
	 */
	public static function register_metabox(): void {
		foreach ( self::supported_post_types() as $post_type ) {
			add_meta_box(
				'flavor-builder',
				__( 'فلیور ساز — صفحه‌ساز', 'flavor' ),
				array( self::class, 'metabox_html' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the builder meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public static function metabox_html( $post ): void {
		wp_nonce_field( self::NONCE, 'flavor_builder_nonce' );

		$layout   = get_post_meta( $post->ID, self::META, true );
		$layout   = is_array( $layout ) ? $layout : array();
		$elements = self::elements();
		?>
		<div class="fb-admin" data-fb-admin>
			<div class="fb-admin__toolbar">
				<label class="fb-admin__toggle">
					<input type="checkbox" name="flavor_builder_enabled" value="1" <?php checked( ! empty( $layout ) ); ?> data-fb-enabled />
					<?php esc_html_e( 'فعال‌سازی صفحه‌ساز برای این صفحه', 'flavor' ); ?>
				</label>
				<span class="fb-admin__hint"><?php esc_html_e( 'المنت‌ها را از فهرست زیر بکشید و در چیدمان رها کنید.', 'flavor' ); ?></span>
			</div>

			<div class="fb-admin__cols">
				<div class="fb-admin__palette">
					<?php
					$cats = array();
					foreach ( $elements as $slug => $def ) {
						$cats[ $def['category'] ][] = $slug;
					}
					foreach ( $cats as $cat => $slugs ) :
						?>
						<div class="fb-admin__cat-title"><?php echo esc_html( $cat ); ?></div>
						<div class="fb-admin__palette-grid">
							<?php foreach ( $slugs as $slug ) : ?>
								<div class="fb-pitem" draggable="true" data-fb-type="<?php echo esc_attr( $slug ); ?>">
									<span class="dashicons <?php echo esc_attr( $elements[ $slug ]['icon'] ); ?>"></span>
									<span><?php echo esc_html( $elements[ $slug ]['label'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="fb-admin__canvas" data-fb-canvas>
					<div class="fb-admin__canvas-empty" data-fb-empty>
						<?php esc_html_e( 'المنت‌ها را اینجا رها کنید', 'flavor' ); ?>
					</div>
					<?php foreach ( $layout as $index => $block ) : ?>
						<?php self::metabox_block( $block, $index ); ?>
					<?php endforeach; ?>
				</div>
			</div>

			<input type="hidden" name="flavor_builder_data" data-fb-input value="<?php echo esc_attr( wp_json_encode( $layout ) ); ?>" />

			<script type="application/json" data-fb-schema>
				<?php echo wp_json_encode( self::schema_for_js() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON for inline script. ?>
			</script>
		</div>
		<?php
	}

	/**
	 * Build a JS-friendly schema of the element registry.
	 *
	 * @return array
	 */
	private static function schema_for_js(): array {
		$out = array();
		foreach ( self::elements() as $slug => $def ) {
			$out[ $slug ] = array(
				'label'    => $def['label'],
				'icon'     => $def['icon'],
				'category' => $def['category'],
				'fields'   => $def['fields'],
				'defaults' => $def['defaults'],
			);
		}
		return $out;
	}

	/**
	 * Output a single builder block row in the admin canvas.
	 *
	 * @param array $block Block data.
	 * @param int   $index Block index.
	 */
	private static function metabox_block( array $block, int $index ): void {
		$type = isset( $block['type'] ) ? sanitize_key( $block['type'] ) : '';
		$def  = self::get_element( $type );
		if ( ! $def ) {
			return;
		}
		$props = wp_parse_args( isset( $block['props'] ) ? $block['props'] : array(), $def['defaults'] );
		?>
		<div class="fb-block-item" draggable="true" data-fb-block data-fb-type="<?php echo esc_attr( $type ); ?>" data-fb-index="<?php echo esc_attr( $index ); ?>">
			<div class="fb-block-item__head">
				<span class="dashicons <?php echo esc_attr( $def['icon'] ); ?>"></span>
				<strong><?php echo esc_html( $def['label'] ); ?></strong>
				<span class="fb-block-item__tools">
					<button type="button" class="fb-tool" data-fb-up title="<?php esc_attr_e( 'بالا', 'flavor' ); ?>">↑</button>
					<button type="button" class="fb-tool" data-fb-down title="<?php esc_attr_e( 'پایین', 'flavor' ); ?>">↓</button>
					<button type="button" class="fb-tool fb-tool--danger" data-fb-del title="<?php esc_attr_e( 'حذف', 'flavor' ); ?>">✕</button>
				</span>
			</div>
			<div class="fb-block-item__fields">
				<?php foreach ( $def['fields'] as $field ) : ?>
					<?php $val = isset( $props[ $field['key'] ] ) ? $props[ $field['key'] ] : ''; ?>
					<label class="fb-field">
						<span><?php echo esc_html( $field['label'] ); ?></span>
						<?php if ( 'textarea' === $field['type'] ) : ?>
							<textarea data-fb-prop="<?php echo esc_attr( $field['key'] ); ?>" rows="2"><?php echo esc_textarea( $val ); ?></textarea>
						<?php elseif ( 'select' === $field['type'] ) : ?>
							<select data-fb-prop="<?php echo esc_attr( $field['key'] ); ?>">
								<?php foreach ( $field['options'] as $ov => $ol ) : ?>
									<option value="<?php echo esc_attr( $ov ); ?>" <?php selected( $val, $ov ); ?>><?php echo esc_html( $ol ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'number' === $field['type'] ) : ?>
							<input type="number" data-fb-prop="<?php echo esc_attr( $field['key'] ); ?>" value="<?php echo esc_attr( $val ); ?>" min="1" />
						<?php elseif ( 'url' === $field['type'] ) : ?>
							<input type="url" data-fb-prop="<?php echo esc_attr( $field['key'] ); ?>" value="<?php echo esc_attr( $val ); ?>" />
						<?php else : ?>
							<input type="text" data-fb-prop="<?php echo esc_attr( $field['key'] ); ?>" value="<?php echo esc_attr( $val ); ?>" />
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save the builder layout when the post is saved.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ): void {
		if ( ! isset( $_POST['flavor_builder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['flavor_builder_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$enabled = isset( $_POST['flavor_builder_enabled'] );
		$raw     = isset( $_POST['flavor_builder_data'] ) ? wp_unslash( $_POST['flavor_builder_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_layout().

		if ( $enabled ) {
			$layout = self::sanitize_layout( $raw );
			if ( ! empty( $layout ) ) {
				update_post_meta( $post_id, self::META, $layout );
			} else {
				delete_post_meta( $post_id, self::META );
			}
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}

	/* ─── Front-end rendering ──────────────────────────────────────────── */

	/**
	 * Replace the post content with the builder layout when one exists.
	 *
	 * @param string $content Original post content.
	 * @return string
	 */
	public static function render_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$layout = get_post_meta( get_the_ID(), self::META, true );

		if ( empty( $layout ) ) {
			return $content;
		}

		return self::render_layout( $layout );
	}

	/* ─── Shortcode ────────────────────────────────────────────────────── */

	/**
	 * Register the builder shortcode.
	 */
	public static function register_shortcode(): void {
		add_shortcode( 'flavor_builder', array( self::class, 'shortcode' ) );
	}

	/**
	 * Render a saved builder layout via shortcode.
	 *
	 * Usage: [flavor_builder id="123"] renders the layout of page/post 123.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts( array( 'id' => 0 ), (array) $atts, 'flavor_builder' );
		$id   = absint( $atts['id'] );

		if ( ! $id ) {
			return '';
		}

		$layout = get_post_meta( $id, self::META, true );
		if ( empty( $layout ) ) {
			return '';
		}

		return self::render_layout( $layout );
	}

	/* ─── Assets ───────────────────────────────────────────────────────── */

	/**
	 * Enqueue builder admin assets on the post edit screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function admin_assets( $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, self::supported_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'flavor-builder-admin', FLAVOR_URI . '/assets/css/builder-admin.css', array(), FLAVOR_VERSION );
		wp_enqueue_script( 'flavor-builder-admin', FLAVOR_URI . '/assets/js/builder-admin.js', array(), FLAVOR_VERSION, true );
	}

	/**
	 * Enqueue builder front-end styles only where a layout exists.
	 */
	public static function frontend_styles(): void {
		$load = false;

		if ( is_singular() ) {
			$layout = get_post_meta( get_queried_object_id(), self::META, true );
			$load   = ! empty( $layout );
		}

		/**
		 * Filter whether builder styles should load (e.g. for shortcode usage).
		 *
		 * @param bool $load Whether to enqueue builder.css.
		 */
		if ( ! (bool) apply_filters( 'flavor_builder_enqueue_styles', $load ) ) {
			return;
		}

		wp_enqueue_style( 'flavor-builder', FLAVOR_URI . '/assets/css/builder.css', array( 'flavor-main' ), FLAVOR_VERSION );
	}
}
