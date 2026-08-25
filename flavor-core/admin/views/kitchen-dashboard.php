<?php
/**
 * Kitchen dashboard — used by /kitchen-dashboard/ and wp-admin.
 *
 * @package FlavorCore
 *
 * @var bool $flavor_admin_wrap Set when rendered inside wp-admin.
 */

defined( 'ABSPATH' ) || exit;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;
use FlavorCore\Support\Settings;

$is_admin_wrap = ! empty( $flavor_admin_wrap );
$branch_id     = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$user_id       = get_current_user_id();
$allowed       = Roles::managed_branch_ids( $user_id );
if ( $branch_id && $allowed && ! in_array( $branch_id, $allowed, true ) && ! current_user_can( 'manage_options' ) ) {
	$branch_id = $allowed[0] ?? 0;
}

$poll = (int) Settings::get( 'kitchen_poll_seconds', 15 );
$rest = esc_url_raw( rest_url( FLAVOR_CORE_REST_NAMESPACE . '/kitchen/tickets' ) );
$nonce = wp_create_nonce( 'wp_rest' );

if ( ! $is_admin_wrap ) {
	?><!DOCTYPE html>
	<html <?php language_attributes(); ?> dir="rtl">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php esc_html_e( 'داشبورد آشپزخانه', 'flavor-core' ); ?></title>
		<?php wp_head(); ?>
		<style>
			body.flavor-kitchen-kiosk { margin: 0; background: #1a1a1a; color: #f5f5f5; font-family: Tahoma, Vazirmatn, sans-serif; }
			.flavor-kitchen { padding: 16px; }
		</style>
	</head>
	<body class="flavor-kitchen-kiosk">
	<?php
} else {
	echo '<div class="wrap">';
}
?>
<div class="flavor-kitchen" data-branch="<?php echo esc_attr( (string) $branch_id ); ?>" data-rest="<?php echo esc_url( $rest ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-poll="<?php echo esc_attr( (string) $poll ); ?>">
	<header class="flavor-kitchen__bar">
		<h1><?php esc_html_e( 'آشپزخانه', 'flavor-core' ); ?></h1>
		<p class="flavor-kitchen__hint">
			<?php esc_html_e( 'فاز ۱: اسکلت کانبان و polling. کارت‌های زنده از فاز ۲ به بعد پر می‌شوند.', 'flavor-core' ); ?>
		</p>
		<label>
			<?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
			<select id="flavor-kitchen-branch">
				<?php foreach ( $allowed ? $allowed : array( $branch_id ) as $id ) : ?>
					<?php if ( $id ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $branch_id, $id ); ?>><?php echo esc_html( get_the_title( $id ) ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</label>
	</header>
	<div class="flavor-kitchen__lanes">
		<section data-lane="new">
			<h2><?php esc_html_e( 'جدید', 'flavor-core' ); ?></h2>
			<div class="flavor-kitchen__cards"></div>
		</section>
		<section data-lane="preparing">
			<h2><?php esc_html_e( 'در حال آماده‌سازی', 'flavor-core' ); ?></h2>
			<div class="flavor-kitchen__cards"></div>
		</section>
		<section data-lane="ready">
			<h2><?php esc_html_e( 'آماده', 'flavor-core' ); ?></h2>
			<div class="flavor-kitchen__cards"></div>
		</section>
	</div>
</div>
<style>
	.flavor-kitchen__bar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
	.flavor-kitchen__lanes { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
	.flavor-kitchen__lanes section { background: #2a2a2a; border-radius: 12px; padding: 12px; min-height: 60vh; }
	.flavor-kitchen__lanes h2 { margin: 0 0 12px; font-size: 1rem; }
	.flavor-kitchen__card { background: #fff; color: #111; border-radius: 10px; padding: 10px; margin-bottom: 8px; }
	.flavor-kitchen__card[data-urgency="yellow"] { box-shadow: inset 4px 0 0 #e6b800; }
	.flavor-kitchen__card[data-urgency="red"] { box-shadow: inset 4px 0 0 #d63638; }
	@media (max-width: 800px) { .flavor-kitchen__lanes { grid-template-columns: 1fr; } }
</style>
<script>
(function () {
	var root = document.querySelector('.flavor-kitchen');
	if (!root) return;
	var rest = root.getAttribute('data-rest');
	var nonce = root.getAttribute('data-nonce');
	var poll = parseInt(root.getAttribute('data-poll') || '15', 10) * 1000;

	function lane(status) {
		var section = root.querySelector('[data-lane="' + status + '"] .flavor-kitchen__cards');
		return section;
	}

	function render(payload) {
		['new', 'preparing', 'ready'].forEach(function (s) {
			var el = lane(s);
			if (el) el.innerHTML = '';
		});
		(payload.tickets || []).forEach(function (t) {
			var host = lane(t.kitchen_status);
			if (!host) return;
			var card = document.createElement('article');
			card.className = 'flavor-kitchen__card';
			card.dataset.urgency = t.urgency || 'ok';
			card.innerHTML = '<strong>#' + (t.order_number || t.id) + '</strong> · ' +
				(t.order_mode || '') +
				(t.table_number ? ' · میز ' + t.table_number : '') +
				'<div>' + (t.customer_name || '') + '</div>';
			host.appendChild(card);
		});
	}

	function tick() {
		var branch = document.getElementById('flavor-kitchen-branch');
		var id = branch ? branch.value : root.getAttribute('data-branch');
		fetch(rest + '?branch_id=' + encodeURIComponent(id), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': nonce }
		}).then(function (r) { return r.json(); }).then(render).catch(function () {});
	}

	tick();
	setInterval(tick, poll);
})();
</script>
<?php
if ( ! $is_admin_wrap ) {
	wp_footer();
	echo '</body></html>';
} else {
	echo '</div>';
}
