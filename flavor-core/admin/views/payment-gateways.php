<?php
/**
 * Admin View: Iranian payment gateways.
 *
 * @package FlavorCore
 */

use FlavorCore\Admin\PaymentGatewaysAdmin;
use FlavorCore\WooCommerce\Gateways\IranianGatewayManager;
use FlavorCore\WooCommerce\Gateways\IranianGateways;

defined( 'ABSPATH' ) || exit;

if ( ! isset( $statuses ) || ! is_array( $statuses ) ) {
	$statuses = IranianGateways::status();
}
if ( ! isset( $summary ) || ! is_array( $summary ) ) {
	$summary = IranianGateways::summary();
}
if ( ! isset( $settings ) || ! is_array( $settings ) ) {
	$settings = array();
}
if ( ! isset( $pending ) || ! is_array( $pending ) ) {
	$pending = IranianGatewayManager::pending();
}
if ( ! isset( $notice ) || ! is_string( $notice ) ) {
	$notice = '';
}

$flavor_page_url = PaymentGatewaysAdmin::page_url();

$flavor_notice_map = array(
	'enabled'      => array( 'success', __( 'درگاه پرداخت فعال شد؛ از این پس به مشتریان نمایش داده می‌شود.', 'flavor-core' ) ),
	'disabled'     => array( 'warning', __( 'درگاه پرداخت خاموش شد و از صفحهٔ تسویه‌حساب حذف می‌شود.', 'flavor-core' ) ),
	'preferred'    => array( 'success', __( 'درگاه پیش‌فرض ذخیره شد؛ در فهرست روش‌های پرداخت اول می‌آید.', 'flavor-core' ) ),
	'connected'    => array( 'success', __( 'اتصال کامل شد. درگاه فعال است و آمادهٔ دریافت پرداخت آنلاین است.', 'flavor-core' ) ),
	'pending'      => array( 'warning', __( 'انتخاب شما ذخیره شد. برای فعال شدن، افزونه را نصب/فعال و مرچنت کد را در تنظیمات درگاه وارد کنید؛ اتصال خودکار تکمیل می‌شود.', 'flavor-core' ) ),
	'disconnected' => array( 'success', __( 'اولویت درگاه پاک شد. روش پرداخت در تنظیمات ووکامرس دست‌نخورده باقی مانده است.', 'flavor-core' ) ),
	'saved'        => array( 'success', __( 'تنظیمات پرداخت ذخیره شد.', 'flavor-core' ) ),
	'invalid'      => array( 'error', __( 'درخواست معتبر نبود؛ دوباره تلاش کنید.', 'flavor-core' ) ),
);

$flavor_active_notice = isset( $flavor_notice_map[ $notice ] ) ? $flavor_notice_map[ $notice ] : null;

/**
 * Print a status pill.
 *
 * @param string $label Text.
 * @param string $tone  success|warning|error|neutral|building.
 * @return string
 */
$flavor_pill = static function ( string $label, string $tone ): string {
	return '<span class="flavor-badge flavor-badge--' . esc_attr( $tone ) . '">' . esc_html( $label ) . '</span>';
};

/**
 * Print a post form button for a gateway action.
 *
 * @param string $op    Action op.
 * @param string $value Gateway id or catalog key.
 * @param string $label Button label.
 * @param string $class Button class.
 * @return string
 */
$flavor_action = static function ( string $op, string $value, string $label, string $class = 'button' ): string {
	$nonce = wp_nonce_field( PaymentGatewaysAdmin::NONCE, '_wpnonce', false, false );
	return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">'
		. '<input type="hidden" name="action" value="flavor_gateway_action" />'
		. '<input type="hidden" name="op" value="' . esc_attr( $op ) . '" />'
		. '<input type="hidden" name="gateway" value="' . esc_attr( $value ) . '" />'
		. $nonce
		. '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>'
		. '</form>';
};

/**
 * Markup allowlist for gateway action buttons and pills.
 *
 * Every value inside is already escaped by its helper; wp_kses() is the
 * second lock and the reason this view can echo composed markup without a
 * blanket phpcs:ignore.
 *
 * @var array<string, array<string, bool>>
 */
$flavor_gateway_kses = array(
	'a'      => array(
		'href'   => true,
		'class'  => true,
		'target' => true,
		'rel'    => true,
	),
	'form'   => array(
		'method' => true,
		'action' => true,
		'style'  => true,
	),
	'input'  => array(
		'type'  => true,
		'name'  => true,
		'value' => true,
	),
	'button' => array(
		'type'  => true,
		'class' => true,
	),
	'span'   => array( 'class' => true ),
);

/*
 * The avatar letter is computed here, not in the loop: mb_substr() would
 * split a Persian letter only when mbstring is present, and substr() would
 * split its UTF-8 bytes. A /u regex works either way.
 */
foreach ( $statuses as $flavor_status_key => $flavor_status_row ) {
	$flavor_initial = '';
	if ( preg_match( '/^./u', (string) $flavor_status_row['name'], $flavor_initial_match ) ) {
		$flavor_initial = (string) $flavor_initial_match[0];
	}
	$statuses[ $flavor_status_key ]['initial'] = $flavor_initial;
}

$flavor_focus_key = (string) $summary['preferred'];
if ( '' === $flavor_focus_key ) {
	foreach ( $statuses as $flavor_candidate ) {
		if ( ! empty( $flavor_candidate['ready'] ) ) {
			$flavor_focus_key = (string) $flavor_candidate['key'];
			break;
		}
	}
}
if ( '' === $flavor_focus_key ) {
	foreach ( $statuses as $flavor_candidate ) {
		if ( ! empty( $flavor_candidate['installed'] ) ) {
			$flavor_focus_key = (string) $flavor_candidate['key'];
			break;
		}
	}
}

$flavor_focus = isset( $statuses[ $flavor_focus_key ] ) ? $statuses[ $flavor_focus_key ] : null;

$flavor_focus_url = '';
if ( null !== $flavor_focus ) {
	if ( ! $flavor_focus['installed'] && '' !== $flavor_focus['install_url'] ) {
		$flavor_focus_url = $flavor_focus['install_url'];
	} elseif ( ! $flavor_focus['active'] && '' !== $flavor_focus['activate_url'] ) {
		$flavor_focus_url = $flavor_focus['activate_url'];
	} elseif ( '' !== $flavor_focus['settings_url'] ) {
		$flavor_focus_url = $flavor_focus['settings_url'];
	} else {
		$flavor_focus_url = $flavor_focus['search_url'];
	}
}

$flavor_steps = array();
if ( null !== $flavor_focus ) {
	$flavor_steps = array(
		array(
			'label' => __( 'نصب افزونهٔ درگاه', 'flavor-core' ),
			'done'  => (bool) $flavor_focus['installed'],
			'help'  => $flavor_focus['installed'] ? __( 'افزونه روی سایت وجود دارد.', 'flavor-core' ) : __( 'افزونهٔ رسمی را از مخزن وردپرس نصب کنید.', 'flavor-core' ),
		),
		array(
			'label' => __( 'فعال‌سازی افزونه', 'flavor-core' ),
			'done'  => (bool) $flavor_focus['active'],
			'help'  => $flavor_focus['active'] ? __( 'افزونه فعال است و کلاس درگاه ثبت شده.', 'flavor-core' ) : __( 'بعد از نصب، افزونه را از صفحهٔ افزونه‌ها فعال کنید.', 'flavor-core' ),
		),
		array(
			'label' => __( 'ورود مرچنت کد / کلید API', 'flavor-core' ),
			'done'  => true === $flavor_focus['configured'],
			'help'  => true === $flavor_focus['configured']
				? __( 'اطلاعات پذیرندگی در تنظیمات درگاه ذخیره شده است.', 'flavor-core' )
				: ( null === $flavor_focus['configured']
					? __( 'وضعیت مرچنت کد از تنظیمات قابل تشخیص نیست؛ یک بار صفحهٔ تنظیمات درگاه را باز کنید.', 'flavor-core' )
					: __( 'تنظیمات درگاه هنوز خالی است؛ مرچنت کد را از پنل درگاه بگیرید.', 'flavor-core' ) ),
			'unknown' => null === $flavor_focus['configured'],
		),
		array(
			'label' => __( 'فعال شدن پرداخت آنلاین', 'flavor-core' ),
			'done'  => (bool) $flavor_focus['ready'],
			'help'  => $flavor_focus['ready'] ? __( 'درگاه در فهرست روش‌های پرداخت فعال است.', 'flavor-core' ) : __( 'با دکمهٔ «اتصال درگاه» در کارت پایین، پرداخت را روشن کنید.', 'flavor-core' ),
		),
	);
}

$flavor_pending_entry = array() !== $pending ? IranianGateways::get( $pending['key'] ) : null;

$flavor_companion_url = IranianGateways::install_url( 'persian-woocommerce' );
if ( '' === $flavor_companion_url ) {
	$flavor_companion_url = IranianGateways::search_url( 'ووکامرس فارسی' );
}
?>

<div class="wrap flavor-admin flavor-payments" dir="rtl">

	<div class="flavor-dashboard-header flavor-payments-header">
		<div class="flavor-dashboard-header__title">
			<h1><?php esc_html_e( 'درگاه‌های پرداخت ایرانی', 'flavor-core' ); ?></h1>
			<p class="flavor-dashboard-header__subtitle">
				<?php esc_html_e( 'اتصال زرین‌پال، زیبال، آی‌دی‌پی، پی‌پینگ، درگاه‌های بانکی و بسته‌های چنددرگاهی به ووکامرس — در چهار گام، بدون دست‌زدن به کد.', 'flavor-core' ); ?>
			</p>
		</div>
		<div class="flavor-payments-score">
			<strong><?php echo esc_html( number_format_i18n( (int) $summary['ready'] ) ); ?></strong>
			<span><?php esc_html_e( 'درگاه آمادهٔ دریافت پرداخت', 'flavor-core' ); ?></span>
		</div>
	</div>

	<?php if ( null !== $flavor_active_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $flavor_active_notice[0] ); ?> is-dismissible">
			<p><?php echo esc_html( $flavor_active_notice[1] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $summary['currency_ok'] ) : ?>
		<div class="flavor-payments-alert">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<div>
				<strong><?php esc_html_e( 'واحد پول فروشگاه با درگاه‌های ایرانی هم‌خوان نیست.', 'flavor-core' ); ?></strong>
				<?php
				/* translators: %s: current currency code. */
				echo esc_html( sprintf( __( 'واحد فعلی: %s. درگاه‌های ایرانی مبلغ را به ریال یا تومان می‌فرستند؛ واحد را در تنظیمات ووکامرس به IRR یا IRT تغییر دهید.', 'flavor-core' ), '' !== $summary['currency'] ? $summary['currency'] : '—' ) );
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ); ?>"><?php esc_html_e( 'تغییر واحد پول', 'flavor-core' ); ?></a>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( null !== $flavor_pending_entry ) : ?>
		<div class="flavor-payments-alert flavor-payments-alert--pending">
			<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			<div>
				<strong>
					<?php
					/* translators: %s: gateway name. */
					echo esc_html( sprintf( __( 'درگاه «%s» در انتظار تکمیل است.', 'flavor-core' ), (string) $flavor_pending_entry['name'] ) );
					?>
				</strong>
				<?php esc_html_e( 'به‌محض نصب و فعال‌سازی افزونه و ذخیرهٔ مرچنت کد، پرداخت آنلاین به‌صورت خودکار روشن می‌شود. تا آن زمان هیچ پرداختی از این درگاه گرفته نمی‌شود.', 'flavor-core' ); ?>
				<?php echo wp_kses( $flavor_action( 'disconnect', $pending['key'], __( 'لغو این انتخاب', 'flavor-core' ), 'button-link' ), $flavor_gateway_kses ); ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="flavor-kpi-grid">
		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'درگاه‌های شناخته‌شده', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-screenoptions flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['total'] ) ); ?></div>
			<div class="flavor-kpi-card__meta"><span><?php esc_html_e( 'واسط، بانکی، اعتباری و چنددرگاهی', 'flavor-core' ); ?></span></div>
		</div>

		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'افزونه‌های نصب‌شده', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-admin-plugins flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['installed'] ) ); ?></div>
			<div class="flavor-kpi-card__meta"><span><?php esc_html_e( 'از میان درگاه‌های پشتیبانی‌شده', 'flavor-core' ); ?></span></div>
		</div>

		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'درگاه‌های روشن', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-yes-alt flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['enabled'] ) ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( array() !== $summary['enabled_names'] ? implode( '، ', $summary['enabled_names'] ) : __( 'هنوز هیچ درگاهی فعال نیست', 'flavor-core' ) ); ?></span>
			</div>
		</div>

		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'آمادهٔ پرداخت', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-shield-alt flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( (int) $summary['ready'] ) ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( array() !== $summary['ready_names'] ? implode( '، ', $summary['ready_names'] ) : __( 'مرچنت کد را وارد کنید یا درگاه را روشن کنید', 'flavor-core' ) ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( null !== $flavor_focus ) : ?>
		<div class="flavor-section-box flavor-payments-flow">
			<h2 class="flavor-section-box__title">
				<span class="dashicons dashicons-randomize" aria-hidden="true"></span>
				<?php
				/* translators: %s: gateway name. */
				echo esc_html( sprintf( __( 'مسیر اتصال %s', 'flavor-core' ), (string) $flavor_focus['name'] ) );
				?>
			</h2>
			<div class="flavor-flow">
				<?php foreach ( $flavor_steps as $flavor_index => $flavor_step ) : ?>
					<?php
					$flavor_state = ! empty( $flavor_step['unknown'] ) ? 'unknown' : ( $flavor_step['done'] ? 'done' : 'todo' );
					?>
					<div class="flavor-flow-step is-<?php echo esc_attr( $flavor_state ); ?>">
						<span class="flavor-flow-step__num"><?php echo esc_html( (string) ( $flavor_index + 1 ) ); ?></span>
						<div class="flavor-flow-step__body">
							<strong><?php echo esc_html( $flavor_step['label'] ); ?></strong>
							<span><?php echo esc_html( $flavor_step['help'] ); ?></span>
						</div>
						<span class="dashicons <?php echo 'done' === $flavor_state ? 'dashicons-yes-alt' : ( 'unknown' === $flavor_state ? 'dashicons-editor-help' : 'dashicons-marker' ); ?>" aria-hidden="true"></span>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="flavor-payments-flow__cta">
				<a class="button button-primary button-hero" href="<?php echo esc_url( $flavor_focus_url ); ?>">
					<?php echo esc_html( $flavor_focus['ready'] ? __( 'باز کردن تنظیمات درگاه', 'flavor-core' ) : __( 'تکمیل اتصال این درگاه', 'flavor-core' ) ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( '#' . 'flavor-gateway-' . $flavor_focus['key'] ); ?>"><?php esc_html_e( 'دیدن کارت درگاه', 'flavor-core' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<h2 class="flavor-payments-title"><?php esc_html_e( 'درگاه‌های پشتیبانی‌شده', 'flavor-core' ); ?></h2>
	<p class="description flavor-payments-intro">
		<?php esc_html_e( 'کارت هر درگاه وضعیت واقعی همین لحظه را نشان می‌دهد: نصب افزونه، فعال بودن، روشن بودن روش پرداخت و ذخیرهٔ مرچنت کد. هر درگاهی که نصب نیست، از همین صفحه نصب می‌شود.', 'flavor-core' ); ?>
	</p>

	<div class="flavor-gateway-grid">
		<?php foreach ( $statuses as $flavor_key => $flavor_gateway ) : ?>
			<?php
			$flavor_actions = array();
			if ( ! $flavor_gateway['installed'] ) {
				if ( '' !== $flavor_gateway['install_url'] ) {
					$flavor_actions[] = '<a class="button button-primary" href="' . esc_url( $flavor_gateway['install_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'نصب افزونه', 'flavor-core' ) . '</a>';
				} elseif ( '' !== $flavor_gateway['install_slug'] ) {
					$flavor_actions[] = '<a class="button" href="' . esc_url( $flavor_gateway['search_url'] ) . '">' . esc_html__( 'جستجو در مخزن', 'flavor-core' ) . '</a>';
				} else {
					$flavor_actions[] = '<a class="button" href="' . esc_url( $flavor_gateway['search_url'] ) . '">' . esc_html__( 'جستجوی افزونه', 'flavor-core' ) . '</a>';
				}
			} elseif ( ! $flavor_gateway['active'] && '' !== $flavor_gateway['activate_url'] ) {
				$flavor_actions[] = '<a class="button button-primary" href="' . esc_url( $flavor_gateway['activate_url'] ) . '">' . esc_html__( 'فعال‌سازی افزونه', 'flavor-core' ) . '</a>';
			}

			if ( '' !== $flavor_gateway['settings_url'] ) {
				$flavor_actions[] = '<a class="button" href="' . esc_url( $flavor_gateway['settings_url'] ) . '">' . esc_html__( 'تنظیمات درگاه', 'flavor-core' ) . '</a>';
			} elseif ( $flavor_gateway['active'] ) {
				$flavor_actions[] = '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '">' . esc_html__( 'پرداخت‌های ووکامرس', 'flavor-core' ) . '</a>';
			}

			if ( '' !== $flavor_gateway['gateway_id'] ) {
				if ( ! $flavor_gateway['enabled'] ) {
					$flavor_actions[] = $flavor_action( 'connect', $flavor_key, __( 'اتصال درگاه', 'flavor-core' ), 'button button-primary' );
				} else {
					if ( ! $flavor_gateway['preferred'] ) {
						$flavor_actions[] = $flavor_action( 'prefer', $flavor_key, __( 'پیش‌فرض کن', 'flavor-core' ) );
					}
					$flavor_actions[] = $flavor_action( 'disable', $flavor_gateway['gateway_id'], __( 'خاموش کردن', 'flavor-core' ), 'button-link flavor-gateway-off' );
				}
			}

			if ( $flavor_gateway['preferred'] ) {
				$flavor_actions[] = $flavor_action( 'disconnect', $flavor_key, __( 'لغو پیش‌فرض', 'flavor-core' ), 'button-link flavor-gateway-off' );
			}

			if ( '' !== (string) $flavor_gateway['website'] ) {
				$flavor_actions[] = '<a class="flavor-gateway-site" href="' . esc_url( (string) $flavor_gateway['website'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'سایت رسمی', 'flavor-core' ) . ' ↗</a>';
			}
			?>
			<article class="flavor-gateway-card<?php echo $flavor_gateway['preferred'] ? ' is-preferred' : ''; ?>" id="flavor-gateway-<?php echo esc_attr( (string) $flavor_key ); ?>">
				<div class="flavor-gateway-card__head">
					<span class="flavor-gateway-avatar" aria-hidden="true"><?php echo esc_html( $flavor_gateway['initial'] ); ?></span>
					<div class="flavor-gateway-card__names">
						<h3><?php echo esc_html( (string) $flavor_gateway['name'] ); ?></h3>
						<span><?php echo esc_html( (string) $flavor_gateway['latin'] . ' · ' . (string) $flavor_gateway['type_label'] ); ?></span>
					</div>
					<?php if ( $flavor_gateway['preferred'] ) : ?>
						<?php echo wp_kses( $flavor_pill( __( 'پیش‌فرض', 'flavor-core' ), 'building' ), $flavor_gateway_kses ); ?>
					<?php endif; ?>
				</div>

				<p class="flavor-gateway-card__desc"><?php echo esc_html( (string) $flavor_gateway['description'] ); ?></p>

				<div class="flavor-gateway-card__badges">
					<?php
					echo wp_kses(
						$flavor_pill(
							$flavor_gateway['installed'] ? __( 'نصب‌شده', 'flavor-core' ) : __( 'نصب نشده', 'flavor-core' ),
							$flavor_gateway['installed'] ? 'success' : 'neutral'
						),
						$flavor_gateway_kses
					);
					if ( $flavor_gateway['installed'] ) {
						echo wp_kses(
							$flavor_pill(
								$flavor_gateway['active'] ? __( 'افزونه فعال', 'flavor-core' ) : __( 'افزونه غیرفعال', 'flavor-core' ),
								$flavor_gateway['active'] ? 'success' : 'warning'
							),
							$flavor_gateway_kses
						);
					}
					if ( '' !== $flavor_gateway['gateway_id'] ) {
						echo wp_kses(
							$flavor_pill(
								$flavor_gateway['enabled'] ? __( 'پرداخت روشن', 'flavor-core' ) : __( 'پرداخت خاموش', 'flavor-core' ),
								$flavor_gateway['enabled'] ? 'success' : 'neutral'
							),
							$flavor_gateway_kses
						);
						if ( false === $flavor_gateway['configured'] ) {
							echo wp_kses( $flavor_pill( __( 'مرچنت کد خالی', 'flavor-core' ), 'warning' ), $flavor_gateway_kses );
						}
						if ( $flavor_gateway['test_mode'] ) {
							echo wp_kses( $flavor_pill( __( 'حالت تست', 'flavor-core' ), 'building' ), $flavor_gateway_kses );
						}
					}
					?>
				</div>

				<div class="flavor-gateway-card__actions">
					<?php echo wp_kses( implode( '', $flavor_actions ), $flavor_gateway_kses ); ?>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<div class="flavor-dashboard-row">
		<div class="flavor-box flavor-box--half">
			<h2 class="flavor-box__title"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> <?php esc_html_e( 'تنظیمات پرداخت رستوران', 'flavor-core' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="flavor_gateway_action" />
				<input type="hidden" name="op" value="save_settings" />
				<?php wp_nonce_field( PaymentGatewaysAdmin::NONCE ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'درگاه پیش‌فرض', 'flavor-core' ); ?></th>
						<td>
							<select name="preferred_gateway">
								<option value=""><?php esc_html_e( 'بدون اولویت (ترتیب ووکامرس)', 'flavor-core' ); ?></option>
								<?php foreach ( $statuses as $flavor_key => $flavor_gateway ) : ?>
									<option value="<?php echo esc_attr( (string) $flavor_key ); ?>" <?php selected( (string) ( $settings['preferred_gateway'] ?? '' ), (string) $flavor_key ); ?>>
										<?php echo esc_html( (string) $flavor_gateway['name'] . ( $flavor_gateway['ready'] ? ' — ' . __( 'آماده', 'flavor-core' ) : '' ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'درگاه انتخابی در فهرست روش‌های پرداخت (وب و اپ موبایل) اول نمایش داده می‌شود. برای اینکه مشتری بتواند انتخاب کند، بقیهٔ روش‌ها هم می‌مانند.', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'پنهان‌کردن پرداخت در محل', 'flavor-core' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="hide_offline_when_online" value="1" <?php checked( (string) ( $settings['hide_offline_when_online'] ?? 'no' ), 'yes' ); ?> />
								<?php esc_html_e( 'وقتی یک درگاه آنلاین فعال است، پرداخت در محل و پای صندوق را نشان نده.', 'flavor-core' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'مناسب سفارش‌های پیش‌پرداخت. اگر رستوران هنوز پرداخت حضوری هم می‌پذیرد، این گزینه را خاموش بگذارید.', 'flavor-core' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'ذخیرهٔ تنظیمات پرداخت', 'flavor-core' ) ); ?>
			</form>
		</div>

		<div class="flavor-box flavor-box--half">
			<h2 class="flavor-box__title"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span> <?php esc_html_e( 'راهنمای اتصال', 'flavor-core' ); ?></h2>
			<ul class="flavor-payments-help">
				<li><?php esc_html_e( 'برای زرین‌پال، زیبال و بیشتر واسط‌ها فقط «مرچنت کد» (یا API Key) لازم است؛ آن را در تنظیمات همان درگاه در ووکامرس وارد کنید.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'درگاه‌های بانکی مستقیم (ملت، پارسیان، سداد، سامان) علاوه بر ترمینال، به فایل کلید و تنظیم IP سرور در پنل بانک نیاز دارند.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'واحد پول فروشگاه باید ریال یا تومان باشد؛ وگرنه مبلغ اشتباه به بانک ارسال می‌شود.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'برای تست، حالت sandbox همان درگاه را روشن کنید و یک سفارش کم‌مبلغ تا انتهای مسیر پرداخت ببیرید.', 'flavor-core' ); ?></li>
				<li><?php esc_html_e( 'افزونهٔ «ووکامرس فارسی» شهرها، تاریخ شمسی و برخی درگاه‌ها (زیبال و پی‌آی‌آر) را یک‌جا می‌آورد و با این صفحه سازگار است.', 'flavor-core' ); ?></li>
			</ul>
			<p class="flavor-payments-companion">
				<a class="button" href="<?php echo esc_url( $flavor_companion_url ); ?>"><?php esc_html_e( 'نصب ووکامرس فارسی', 'flavor-core' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>"><?php esc_html_e( 'روش‌های پرداخت ووکامرس', 'flavor-core' ); ?></a>
			</p>
		</div>
	</div>
</div>
