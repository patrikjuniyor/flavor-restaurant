<?php
/**
 * Ready-made restaurant page sections for the block inserter.
 *
 * A paid theme is expected to hand the buyer a finished starting point, not
 * an empty canvas: the buyer picks "Reservation call to action" and gets the
 * section designed for them, already wearing the active skin's colours
 * through the theme.json presets.
 *
 * These are core blocks only — no custom block, no build step, no JavaScript.
 * That keeps them working after the theme is deactivated (the content is
 * portable), and it means they inherit every colour, font and spacing token
 * from flavor/theme.json automatically.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Block_Patterns
 */
class Block_Patterns {

	/**
	 * Pattern category slug.
	 */
	public const CATEGORY = 'flavor';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_category' ) );
		add_action( 'init', array( self::class, 'register_patterns' ) );
	}

	/**
	 * A "Flavor" category so the patterns are findable in the inserter.
	 */
	public static function register_category(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			self::CATEGORY,
			array(
				'label' => _x( 'رستوران مستقیم', 'Block pattern category', 'flavor' ),
			)
		);
	}

	/**
	 * Pattern definitions.
	 *
	 * Each entry is [ title, description, keywords, content builder ].
	 *
	 * @return array<string, array{title: string, description: string, keywords: string[], content: string}>
	 */
	public static function patterns(): array {
		return array(
			'flavor/hero'          => array(
				'title'       => __( 'هیروی رستوران', 'flavor' ),
				'description' => __( 'سربرگ بزرگ با تیتر، توضیح کوتاه و دو دکمهٔ سفارش و رزرو.', 'flavor' ),
				'keywords'    => array( 'hero', 'هیرو', 'سربرگ', 'رستوران' ),
				'content'     => self::hero(),
			),
			'flavor/menu-teaser'   => array(
				'title'       => __( 'معرفی دسته‌های منو', 'flavor' ),
				'description' => __( 'سه ستون برای معرفی دسته‌های اصلی منو با دکمهٔ مشاهده.', 'flavor' ),
				'keywords'    => array( 'menu', 'منو', 'دسته', 'غذا' ),
				'content'     => self::menu_teaser(),
			),
			'flavor/reservation'   => array(
				'title'       => __( 'دعوت به رزرو میز', 'flavor' ),
				'description' => __( 'بخش پایانی با تیتر، توضیح و دکمهٔ رزرو روی پس‌زمینهٔ برند.', 'flavor' ),
				'keywords'    => array( 'reservation', 'رزرو', 'میز', 'cta' ),
				'content'     => self::reservation(),
			),
			'flavor/hours'         => array(
				'title'       => __( 'ساعات کاری و نشانی', 'flavor' ),
				'description' => __( 'دو ستونه: ساعات کاری در یک ستون و نشانی و تلفن در ستون دیگر.', 'flavor' ),
				'keywords'    => array( 'hours', 'ساعات', 'آدرس', 'تماس', 'شعبه' ),
				'content'     => self::hours(),
			),
			'flavor/chef-story'    => array(
				'title'       => __( 'داستان سرآشپز', 'flavor' ),
				'description' => __( 'تصویر در کنار متن برای روایت داستان رستوران یا معرفی سرآشپز.', 'flavor' ),
				'keywords'    => array( 'about', 'درباره', 'سرآشپز', 'داستان' ),
				'content'     => self::chef_story(),
			),
			'flavor/testimonials'  => array(
				'title'       => __( 'نظرات مشتریان', 'flavor' ),
				'description' => __( 'سه نقل‌قول در کنار هم با نام و نقش هر مشتری.', 'flavor' ),
				'keywords'    => array( 'testimonial', 'نظر', 'مشتری', 'review' ),
				'content'     => self::testimonials(),
			),
			'flavor/faq'           => array(
				'title'       => __( 'پرسش‌های پرتکرار', 'flavor' ),
				'description' => __( 'فهرستی از پرسش و پاسخ‌های رایج دربارهٔ سفارش، ارسال و رزرو.', 'flavor' ),
				'keywords'    => array( 'faq', 'سؤال', 'پرسش', 'راهنما' ),
				'content'     => self::faq(),
			),
			'flavor/order-steps'   => array(
				'title'       => __( 'مراحل سفارش', 'flavor' ),
				'description' => __( 'سه گام ساده برای توضیح مسیر سفارش از انتخاب غذا تا دریافت.', 'flavor' ),
				'keywords'    => array( 'steps', 'مراحل', 'سفارش', 'process' ),
				'content'     => self::order_steps(),
			),
		);
	}

	/**
	 * Register every pattern.
	 */
	public static function register_patterns(): void {
		if ( ! function_exists( 'register_block_pattern' ) ) {
			return; // WordPress < 5.5.
		}

		foreach ( self::patterns() as $slug => $pattern ) {
			register_block_pattern(
				$slug,
				array(
					'title'         => $pattern['title'],
					'description'   => $pattern['description'],
					'keywords'      => $pattern['keywords'],
					'categories'    => array( self::CATEGORY ),
					'content'       => $pattern['content'],
					// Patterns are portable: core blocks only, no custom block.
					'viewportWidth' => 1200,
				)
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Pattern content.
	 *
	 * Preset colour classes (has-primary-color, has-surface-background-color)
	 * come from the slugs declared in flavor/theme.json, so each pattern
	 * repaints itself when the merchant changes skin.
	 * ---------------------------------------------------------------- */

	/**
	 * Hero.
	 */
	private static function hero(): string {
		return '<!-- wp:cover {"overlayColor":"ink","isDark":true,"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|240","bottom":"var:preset|spacing|240","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}}} -->
<div class="wp-block-cover alignfull is-dark"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1,"style":{"elements":{"link":{"color":{"text":"var:preset|color|surface"}}}},"textColor":"surface"} -->
<h1 class="wp-block-heading has-text-align-center has-surface-color has-text-color has-link-color">' . esc_html__( 'طعمی که به خانه می‌برید', 'flavor' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","style":{"elements":{"link":{"color":{"text":"var:preset|color|surface"}}}},"textColor":"surface","fontSize":"large"} -->
<p class="has-text-align-center has-surface-color has-text-color has-link-color has-large-font-size">' . esc_html__( 'سفارش آنلاین، رزرو میز و تحویل در کمتر از ۳۰ دقیقه.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"surface","className":"is-style-fill"} -->
<div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-surface-color has-primary-background-color has-text-color has-background wp-element-button">' . esc_html__( 'سفارش آنلاین', 'flavor' ) . '</a></div>
<!-- /wp:button -->

<!-- wp:button {"textColor":"surface","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-surface-color has-text-color wp-element-button">' . esc_html__( 'رزرو میز', 'flavor' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->';
	}

	/**
	 * Menu teaser.
	 */
	private static function menu_teaser(): string {
		return '<!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'منوی امروز', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color">' . esc_html__( 'هر روز با مواد تازه آماده می‌شود.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|60","left":"var:preset|spacing|60"}}}} -->
<div class="wp-block-columns alignwide"><!-- wp:column {"backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-column has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color">' . esc_html__( 'غذاهای اصلی', 'flavor' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html__( 'کباب، خورشت و غذاهای گریل‌شده با برنج ایرانی.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="has-primary-color has-text-color" href="#menu">' . esc_html__( 'مشاهدهٔ منو ←', 'flavor' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-column has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color">' . esc_html__( 'پیش‌غذا و سالاد', 'flavor' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html__( 'سالاد فصل، ماست‌موسیر و پیش‌غذاهای خانگی.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="has-primary-color has-text-color" href="#menu">' . esc_html__( 'مشاهدهٔ منو ←', 'flavor' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column {"backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-column has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color">' . esc_html__( 'نوشیدنی و دسر', 'flavor' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html__( 'دوغ، نوشیدنی‌های سرد و دسرهای روز.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a class="has-primary-color has-text-color" href="#menu">' . esc_html__( 'مشاهدهٔ منو ←', 'flavor' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
	}

	/**
	 * Reservation call to action.
	 */
	private static function reservation(): string {
		return '<!-- wp:group {"align":"full","backgroundColor":"surface-alt","style":{"spacing":{"padding":{"top":"var:preset|spacing|160","bottom":"var:preset|spacing|160","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-alt-background-color has-background" style="padding-top:var(--wp--preset--spacing--160);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--160);padding-left:var(--wp--preset--spacing--60)"><!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'میز شما آماده است', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color">' . esc_html__( 'رزرو آنلاین در چند ثانیه، بدون تماس تلفنی.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button {"backgroundColor":"primary","textColor":"surface"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-surface-color has-primary-background-color has-text-color has-background wp-element-button">' . esc_html__( 'رزرو میز', 'flavor' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->';
	}

	/**
	 * Opening hours and address.
	 */
	private static function hours(): string {
		return '<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'ساعات کاری', 'flavor' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:table {"className":"is-style-regular"} -->
<figure class="wp-block-table is-style-regular"><table><tbody><tr><td>' . esc_html__( 'شنبه تا پنج‌شنبه', 'flavor' ) . '</td><td>' . esc_html__( '۱۱:۳۰ تا ۲۳:۴۵', 'flavor' ) . '</td></tr><tr><td>' . esc_html__( 'جمعه', 'flavor' ) . '</td><td>' . esc_html__( '۱۳:۰۰ تا ۲۳:۴۵', 'flavor' ) . '</td></tr></tbody></table></figure>
<!-- /wp:table --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'نشانی و تماس', 'flavor' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'تهران، خیابان ولیعصر', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="tel:02188001234">۰۲۱-۸۸۰۰۱۲۳۴</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size">' . esc_html__( 'سفارش بیرون‌بر و ارسال در تمام ساعات کاری فعال است.', 'flavor' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
	}

	/**
	 * Chef's story.
	 */
	private static function chef_story(): string {
		return '<!-- wp:media-text {"align":"wide","mediaPosition":"right","mediaType":"image"} -->
<div class="wp-block-media-text alignwide has-media-on-the-right is-stacked-on-mobile"><div class="wp-block-media-text__content"><!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html__( 'داستان ما', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'از سال ۱۳۹۵ با یک هدف ساده شروع کردیم: غذای خانگی با مواد تازه و قیمت منصفانه. امروز هر روز صبح مواد اولیه را خودمان از بازار می‌خریم.', 'flavor' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html__( 'سرآشپز: شما اینجا نام سرآشپز را بنویسید.', 'flavor' ) . '</p>
<!-- /wp:paragraph --></div><figure class="wp-block-media-text__media"></figure></div>
<!-- /wp:media-text -->';
	}

	/**
	 * Testimonials.
	 */
	private static function testimonials(): string {
		$quote = static function ( string $text, string $name, string $role ): string {
			return '<!-- wp:column {"backgroundColor":"surface","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60","left":"var:preset|spacing|60","right":"var:preset|spacing|60"}},"border":{"radius":"16px"}}} -->
<div class="wp-block-column has-surface-background-color has-background" style="border-radius:16px;padding-top:var(--wp--preset--spacing--60);padding-right:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60);padding-left:var(--wp--preset--spacing--60)"><!-- wp:quote {"style":{"border":{"width":"0px","left":{"width":"4px"}}}} -->
<blockquote class="wp-block-quote" style="border-left-width:4px;border-top-width:0px;border-right-width:0px;border-bottom-width:0px"><p>' . esc_html( $text ) . '</p><cite>' . esc_html( $name ) . '</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:paragraph {"textColor":"muted","fontSize":"small"} -->
<p class="has-muted-color has-text-color has-small-font-size">' . esc_html( $role ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->';
		};

		return '<!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'مشتریان ما چه می‌گویند', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">' .
			$quote(
				__( 'کیفیت غذا عالی بود و زودتر از زمان اعلام‌شده رسید.', 'flavor' ),
				__( 'مریم احمدی', 'flavor' ),
				__( 'سفارش ارسال', 'flavor' )
			) .
			$quote(
				__( 'رزرو آنلاین خیلی راحت بود؛ میز آماده بود و منتظر نماندیم.', 'flavor' ),
				__( 'رضا کریمی', 'flavor' ),
				__( 'رزرو سالن', 'flavor' )
			) .
			$quote(
				__( 'قیمت‌ها منصفانه است و برخورد پرسنل عالی.', 'flavor' ),
				__( 'سارا موسوی', 'flavor' ),
				__( 'سفارش بیرون‌بر', 'flavor' )
			) . '</div>
<!-- /wp:columns -->';
	}

	/**
	 * FAQ.
	 */
	private static function faq(): string {
		$item = static function ( string $question, string $answer ): string {
			return '<!-- wp:details -->
<details class="wp-block-details"><summary>' . esc_html( $question ) . '</summary>

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html( $answer ) . '</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->';
		};

		return '<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">' . esc_html__( 'پرسش‌های پرتکرار', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">' .
			$item(
				__( 'زمان آماده‌سازی سفارش چقدر است؟', 'flavor' ),
				__( 'سفارش‌های ارسال معمولاً بین ۲۰ تا ۳۰ دقیقه آماده می‌شوند.', 'flavor' )
			) .
			$item(
				__( 'هزینهٔ ارسال چگونه محاسبه می‌شود؟', 'flavor' ),
				__( 'بر اساس فاصلهٔ شما از شعبه محاسبه می‌شود و پیش از پرداخت نمایش داده می‌شود.', 'flavor' )
			) .
			$item(
				__( 'آیا می‌توانم رزرو را لغو کنم؟', 'flavor' ),
				__( 'بله؛ از طریق پیامکِ تأیید یا تماس با شعبه تا یک ساعت پیش از زمان رزرو.', 'flavor' )
			) . '</div>
<!-- /wp:group -->';
	}

	/**
	 * Order steps.
	 */
	private static function order_steps(): string {
		$step = static function ( string $number, string $title, string $text ): string {
			return '<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-column"><!-- wp:paragraph {"textColor":"primary","fontSize":"x-large"} -->
<p class="has-primary-color has-text-color has-x-large-font-size">' . esc_html( $number ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html( $title ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color">' . esc_html( $text ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->';
		};

		return '<!-- wp:heading {"textAlign":"center","level":2} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'سه گام تا سفارش', 'flavor' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:columns {"align":"wide"} -->
<div class="wp-block-columns alignwide">' .
			$step( '۱', __( 'غذایتان را انتخاب کنید', 'flavor' ), __( 'منو را مرور کنید و هرچه خواستید به سبد بیفزایید.', 'flavor' ) ) .
			$step( '۲', __( 'روش دریافت را مشخص کنید', 'flavor' ), __( 'سالن، بیرون‌بر یا ارسال با پیک.', 'flavor' ) ) .
			$step( '۳', __( 'پرداخت و پیگیری', 'flavor' ), __( 'پرداخت آنلاین یا در محل، همراه با پیگیری لحظه‌ای.', 'flavor' ) ) . '</div>
<!-- /wp:columns -->';
	}
}
