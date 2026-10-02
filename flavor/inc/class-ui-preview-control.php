<?php
/** Customizer-only guide; loaded after WordPress's control base is available. @package Flavor */
namespace Flavor;
defined( 'ABSPATH' ) || exit;

class UI_Preview_Control extends \WP_Customize_Control {
	public $type = 'flavor-ui-preview';
	public function render_content(): void {
		?>
		<span class="customize-control-title"><?php esc_html_e( 'اول پیش‌نمایش، بعد انتشار', 'flavor' ); ?></span>
		<p class="description"><?php esc_html_e( 'برای دیدن کارت‌ها، پیش‌نمایش را به صفحهٔ منو ببرید. تغییرات زیر بدون بارگذاری دوبارهٔ منو نمایش داده می‌شوند و تا زدن «انتشار» عمومی نیستند. از دکمه‌های پایین سفارشی‌ساز، اندازهٔ موبایل و دسکتاپ را هم بررسی کنید.', 'flavor' ); ?></p>
		<p><a class="button" data-flavor-ui-preview-url href="<?php echo esc_url( UI::url( 'menu' ) ); ?>"><?php esc_html_e( 'پیش‌نمایش منو', 'flavor' ); ?></a> <a class="button" data-flavor-ui-preview-url href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'پیش‌نمایش صفحهٔ نخست', 'flavor' ); ?></a></p>
		<p class="description"><?php esc_html_e( 'تراکم و قاب عکس برای منوی سفارش است، نه ترکیب صفحهٔ اصلی دمو. قاب مربع در نمایش کارتی دیده می‌شود؛ عکس فهرست با ارتفاع ردیف هماهنگ است. «پیش‌فرض پوسته» گردی و هویت همان دمو را نگه می‌دارد.', 'flavor' ); ?></p>
		<?php
	}
}
