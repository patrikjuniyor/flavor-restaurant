<?php
/**
 * Staff phone-order desk.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Order;

use FlavorCore\Customer\OtpAuth;
use FlavorCore\Loyalty\PointsManager;
use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Iran;
use FlavorCore\WooCommerce\CartSession;
use FlavorCore\WooCommerce\CheckoutService;
use FlavorCore\WooCommerce\ProductModifiers;

defined( 'ABSPATH' ) || exit;

/**
 * Class PhoneOrderAdmin
 */
class PhoneOrderAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_phone_order', array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Assets on this screen.
	 *
	 * @param string $hook Hook.
	 */
	public function assets( string $hook ): void {
		if ( false === strpos( $hook, 'flavor-phone' ) ) {
			return;
		}
		wp_enqueue_script(
			'flavor-phone-order',
			FLAVOR_CORE_URL . 'assets/admin/js/phone-order.js',
			array(),
			FLAVOR_CORE_VERSION,
			true
		);
		wp_localize_script(
			'flavor-phone-order',
			'flavorPhone',
			array(
				'rest'  => esc_url_raw( rest_url( FLAVOR_CORE_REST_NAMESPACE . '/' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Render.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_create_phone_order' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$mobile    = isset( $_GET['mobile'] ) ? sanitize_text_field( wp_unslash( $_GET['mobile'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$branches  = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'posts_per_page' => 50,
				'post_status'    => array( 'publish', 'draft' ),
			)
		);
		$customer = null;
		$recent   = array();
		$norm     = Iran::normalize_mobile( $mobile );
		if ( $norm ) {
			$found = get_users(
				array(
					'meta_key'   => OtpAuth::META_MOBILE,
					'meta_value' => $norm,
					'number'     => 1,
				)
			);
			if ( $found ) {
				$customer = $found[0];
				$recent   = wc_get_orders(
					array(
						'customer_id' => $customer->ID,
						'limit'       => 5,
						'orderby'     => 'date',
						'order'       => 'DESC',
					)
				);
			}
		}
		$products = function_exists( 'wc_get_products' ) ? wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => 40,
				'type'   => 'simple',
			)
		) : array();
		?>
		<div class="wrap flavor-admin" id="flavor-phone-desk">
			<h1><?php esc_html_e( 'سفارش تلفنی', 'flavor-core' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="flavor-phone" />
				<select name="branch_id">
					<?php foreach ( $branches as $b ) : ?>
						<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $branch_id, $b->ID ); ?>><?php echo esc_html( get_the_title( $b ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="text" name="mobile" value="<?php echo esc_attr( $mobile ); ?>" placeholder="09xxxxxxxxx" dir="ltr" />
				<button class="button"><?php esc_html_e( 'جستجوی مشتری', 'flavor-core' ); ?></button>
			</form>

			<?php if ( $customer ) : ?>
				<p>
					<strong><?php echo esc_html( $customer->display_name ); ?></strong>
					— <?php echo esc_html( wp_json_encode( PointsManager::summary( $customer->ID ), JSON_UNESCAPED_UNICODE ) ); ?>
				</p>
				<?php if ( $recent ) : ?>
					<p><?php esc_html_e( 'سفارش‌های اخیر:', 'flavor-core' ); ?>
						<?php foreach ( $recent as $o ) : ?>
							<code>#<?php echo esc_html( $o->get_order_number() ); ?></code>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="flavor-phone-form">
				<?php wp_nonce_field( 'flavor_phone_order' ); ?>
				<input type="hidden" name="action" value="flavor_phone_order" />
				<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
				<p>
					<label><?php esc_html_e( 'نام', 'flavor-core' ); ?> <input type="text" name="name" value="<?php echo $customer ? esc_attr( $customer->display_name ) : ''; ?>" required /></label>
					<label><?php esc_html_e( 'موبایل', 'flavor-core' ); ?> <input type="text" name="mobile" value="<?php echo esc_attr( $norm ?: $mobile ); ?>" dir="ltr" required /></label>
				</p>
				<p>
					<label><?php esc_html_e( 'حالت', 'flavor-core' ); ?>
						<select name="order_mode">
							<option value="delivery"><?php esc_html_e( 'ارسال', 'flavor-core' ); ?></option>
							<option value="takeaway"><?php esc_html_e( 'بیرون‌بر', 'flavor-core' ); ?></option>
							<option value="dine_in"><?php esc_html_e( 'سالن', 'flavor-core' ); ?></option>
						</select>
					</label>
					<label><?php esc_html_e( 'پرداخت', 'flavor-core' ); ?>
						<select name="payment_method">
							<option value="flavor_cod"><?php esc_html_e( 'نقد پیک', 'flavor-core' ); ?></option>
							<option value="flavor_card_on_delivery"><?php esc_html_e( 'کارت‌خوان', 'flavor-core' ); ?></option>
							<option value="flavor_pay_at_counter"><?php esc_html_e( 'پای صندوق', 'flavor-core' ); ?></option>
						</select>
					</label>
				</p>
				<p>
					<label><?php esc_html_e( 'شهر', 'flavor-core' ); ?> <input type="text" name="city" /></label>
					<label><?php esc_html_e( 'محله', 'flavor-core' ); ?> <input type="text" name="neighborhood" /></label>
					<label><?php esc_html_e( 'نشانی', 'flavor-core' ); ?> <input type="text" name="line" size="40" /></label>
				</p>
				<h2><?php esc_html_e( 'منو', 'flavor-core' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'آیتم', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'تعداد', 'flavor-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $products as $p ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $p->get_name() ); ?>
									<?php
									$mods = ProductModifiers::get_modifiers( $p->get_id() );
									if ( $mods ) {
										echo '<div class="description">';
										foreach ( $mods as $m ) {
											echo '<label style="margin-inline-end:8px;"><input type="checkbox" name="mods[' . esc_attr( (string) $p->get_id() ) . '][]" value="' . esc_attr( (string) $m['id'] ) . '" /> ' . esc_html( (string) $m['name'] ) . '</label>';
										}
										echo '</div>';
									}
									?>
								</td>
								<td><input type="number" name="qty[<?php echo esc_attr( (string) $p->get_id() ); ?>]" value="0" min="0" max="20" style="width:4rem" /></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><label><?php esc_html_e( 'کد تخفیف', 'flavor-core' ); ?> <input type="text" name="coupon" /></label></p>
				<p><button class="button button-primary"><?php esc_html_e( 'ثبت سفارش و ارسال به آشپزخانه', 'flavor-core' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Place the order via the official checkout path.
	 */
	public function handle(): void {
		if ( ! current_user_can( 'flavor_create_phone_order' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
		check_admin_referer( 'flavor_phone_order' );

		CartSession::ensure();
		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		$qtys = isset( $_POST['qty'] ) ? (array) wp_unslash( $_POST['qty'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$mods = isset( $_POST['mods'] ) ? (array) wp_unslash( $_POST['mods'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		foreach ( $qtys as $pid => $q ) {
			$q = (int) $q;
			if ( $q <= 0 ) {
				continue;
			}
			$ids = isset( $mods[ $pid ] ) ? array_map( 'sanitize_title', (array) $mods[ $pid ] ) : array();
			CartSession::add( (int) $pid, $q, array( 'ids' => $ids, 'instructions' => '' ) );
		}

		$coupon = isset( $_POST['coupon'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon'] ) ) : '';
		if ( $coupon && WC()->cart ) {
			WC()->cart->apply_coupon( $coupon );
		}

		$result = CheckoutService::place(
			array(
				'order_mode'     => isset( $_POST['order_mode'] ) ? sanitize_key( wp_unslash( $_POST['order_mode'] ) ) : 'delivery',
				'branch_id'      => isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0,
				'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'mobile'         => isset( $_POST['mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['mobile'] ) ) : '',
				'payment_method' => isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : 'flavor_cod',
				'source'         => 'phone',
				'address'        => array(
					'city'         => isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '',
					'neighborhood' => isset( $_POST['neighborhood'] ) ? sanitize_text_field( wp_unslash( $_POST['neighborhood'] ) ) : '',
					'line'         => isset( $_POST['line'] ) ? sanitize_text_field( wp_unslash( $_POST['line'] ) ) : '',
				),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=flavor-kitchen&placed=' . (int) $result['order_id'] ) );
		exit;
	}
}
