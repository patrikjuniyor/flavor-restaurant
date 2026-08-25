<?php
/**
 * Kitchen dashboard — /kitchen-dashboard/ and wp-admin.
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
if ( empty( $allowed ) && current_user_can( 'manage_options' ) ) {
	$allowed = $branch_id ? array( $branch_id ) : array();
}

$poll    = (int) Settings::get( 'kitchen_poll_seconds', 15 );
$sound   = 'yes' === Settings::get( 'kitchen_sound', 'yes' );
$rest    = esc_url_raw( rest_url( FLAVOR_CORE_REST_NAMESPACE . '/kitchen/tickets' ) );
$nonce   = wp_create_nonce( 'wp_rest' );
$home    = home_url( '/' );

if ( ! $is_admin_wrap ) {
	?><!DOCTYPE html>
	<html <?php language_attributes(); ?> dir="rtl">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php esc_html_e( 'داشبورد آشپزخانه', 'flavor-core' ); ?></title>
		<?php wp_head(); ?>
		<link rel="stylesheet" href="<?php echo esc_url( FLAVOR_CORE_URL . 'assets/public/css/kitchen-dashboard.css' ); ?>?ver=<?php echo esc_attr( FLAVOR_CORE_VERSION ); ?>" />
	</head>
	<body class="flavor-kitchen-kiosk">
	<?php
} else {
	echo '<div class="wrap">';
	wp_enqueue_style( 'flavor-kitchen', FLAVOR_CORE_URL . 'assets/public/css/kitchen-dashboard.css', array(), FLAVOR_CORE_VERSION );
}
?>
<div
	class="flavor-kitchen"
	id="flavor-kitchen"
	data-branch="<?php echo esc_attr( (string) $branch_id ); ?>"
	data-rest="<?php echo esc_url( $rest ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-poll="<?php echo esc_attr( (string) $poll ); ?>"
	data-sound="<?php echo $sound ? '1' : '0'; ?>"
	data-home="<?php echo esc_url( $home ); ?>"
>
	<header class="flavor-kitchen__bar">
		<h1><?php esc_html_e( 'آشپزخانه', 'flavor-core' ); ?></h1>
		<label>
			<?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
			<select id="flavor-kitchen-branch">
				<?php foreach ( $allowed as $id ) : ?>
					<?php if ( $id ) : ?>
						<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $branch_id, $id ); ?>><?php echo esc_html( get_the_title( $id ) ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</label>
		<div class="flavor-kitchen__filters" role="group">
			<button type="button" class="is-active" data-filter="all"><?php esc_html_e( 'همه', 'flavor-core' ); ?></button>
			<button type="button" data-filter="dine_in"><?php esc_html_e( 'سالن', 'flavor-core' ); ?></button>
			<button type="button" data-filter="takeaway"><?php esc_html_e( 'بیرون‌بر', 'flavor-core' ); ?></button>
			<button type="button" data-filter="delivery"><?php esc_html_e( 'ارسال', 'flavor-core' ); ?></button>
		</div>
		<button type="button" id="flavor-kitchen-fs"><?php esc_html_e( 'تمام‌صفحه', 'flavor-core' ); ?></button>
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
<script src="<?php echo esc_url( FLAVOR_CORE_URL . 'assets/public/js/kitchen-dashboard.js' ); ?>?ver=<?php echo esc_attr( FLAVOR_CORE_VERSION ); ?>"></script>
<?php
if ( ! $is_admin_wrap ) {
	wp_footer();
	echo '</body></html>';
} else {
	echo '</div>';
}
