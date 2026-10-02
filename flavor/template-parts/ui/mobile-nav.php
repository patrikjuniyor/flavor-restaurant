<?php
/** Capability-aware mobile navigation. Never advertises a nonexistent table service. @package Flavor */
defined( 'ABSPATH' ) || exit;
$settings = \Flavor\UI::settings();
$is_menu  = is_page_template( 'page-templates/template-menu.php' );
$has_core = \Flavor\Theme_Setup::has_core();
$middle   = \Flavor\UI::reservation_available() && ! ( function_exists( 'is_account_page' ) && is_account_page() ) ? 'reservation' : ( \Flavor\UI::account_available() ? 'account' : 'contact' );
$labels   = array( 'reservation' => __( 'رزرو میز', 'flavor' ), 'account' => __( 'حساب من', 'flavor' ), 'contact' => __( 'تماس', 'flavor' ) );
$icons    = array( 'reservation' => 'calendar', 'account' => 'user', 'contact' => 'phone' );
$planning = 'catering' === \Flavor\Design::current_skin() && ! $is_menu;
$cart_url = add_query_arg( 'open_cart', '1', \Flavor\UI::url( 'menu' ) );
?>
<nav class="flavor-mobile-nav <?php echo 'minimal' === $settings['mobile_nav'] ? 'flavor-mobile-nav--minimal' : ''; ?>" aria-label="<?php esc_attr_e( 'دسترسی سریع موبایل', 'flavor' ); ?>">
	<a data-ui-nav-home href="<?php echo esc_url( home_url( '/' ) ); ?>" <?php if ( is_front_page() ) : ?>aria-current="page"<?php endif; ?>><?php \Flavor\UI::icon( 'home', 21 ); ?><span><?php esc_html_e( 'خانه', 'flavor' ); ?></span></a>
	<a href="<?php echo esc_url( \Flavor\UI::url( 'menu' ) ); ?>" <?php if ( $is_menu ) : ?>aria-current="page"<?php endif; ?>><?php \Flavor\UI::icon( 'menu', 21 ); ?><span><?php esc_html_e( 'منو', 'flavor' ); ?></span></a>
	<a href="<?php echo esc_url( \Flavor\UI::url( $middle ) ); ?>" <?php if ( is_page( $middle ) || ( 'account' === $middle && function_exists( 'is_account_page' ) && is_account_page() ) ) : ?>aria-current="page"<?php endif; ?>><?php \Flavor\UI::icon( $icons[ $middle ], 21 ); ?><span><?php echo esc_html( $labels[ $middle ] ); ?></span></a>
	<?php if ( $planning ) : ?>
		<a class="flavor-mobile-nav__primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( '#proposal' ) ); ?>"><?php \Flavor\UI::icon( 'calendar', 21 ); ?><span><?php esc_html_e( 'هماهنگی', 'flavor' ); ?></span></a>
	<?php elseif ( $has_core && $is_menu ) : ?>
		<a class="flavor-mobile-nav__primary" id="flavor-mobile-cart-btn" data-ui-nav-cart aria-haspopup="dialog" aria-controls="flavor-cart-panel" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : \Flavor\UI::url( 'menu' ) ); ?>"><?php \Flavor\UI::icon( 'bag', 21 ); ?><span><?php esc_html_e( 'سبد', 'flavor' ); ?> <b data-ui-nav-count hidden></b></span></a>
	<?php else : ?>
		<a class="flavor-mobile-nav__primary" href="<?php echo esc_url( $has_core ? $cart_url : \Flavor\UI::url( 'menu' ) ); ?>"><?php \Flavor\UI::icon( 'bag', 21 ); ?><span><?php echo $has_core ? esc_html__( 'سبد', 'flavor' ) : esc_html__( 'مشاهدهٔ منو', 'flavor' ); ?> <b data-ui-nav-count hidden></b></span></a>
	<?php endif; ?>
</nav>
