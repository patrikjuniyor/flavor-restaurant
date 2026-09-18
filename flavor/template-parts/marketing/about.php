<?php
/**
 * About and Story section.
 *
 * @package Flavor
 *
 * @var array<string, string> $args title, text.
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_about_enable', 'yes' ) ) {
	return;
}

$title = $args['title'] ?? __( 'داستان و فلسفه آشپزی ما', 'flavor' );
$text  = $args['text'] ?? get_theme_mod( 'flavor_about', '' );

if ( ! $text ) {
	$text = __( 'ما فعالیت خود را با یک باور ساده آغاز کردیم: غذا فراتر از یک وعده روزمره است؛ غذا روایتی از اصالت، گردهمایی عزیزان و خلق خاطرات ماندگار است. در آشپزخانه ما، تمامی مواد اولیه روزانه و با وسواس از برترین مزارع و تأمین‌کنندگان تهیه می‌شوند و بدون هیچ‌گونه ماده نگه‌دارنده یا فرآوری‌شده، با مهارت و عشق سرآشپزان باسابقه طبخ می‌گردند.', 'flavor' );
}

$skin     = \Flavor\Design::current_skin();
$hero_img = FLAVOR_URI . '/demos/' . $skin . '/hero.jpg';
?>
<section class="flavor-section flavor-about" aria-label="<?php echo esc_attr( $title ); ?>">
	<div class="flavor-container">
		<div class="flavor-about__inner">
			<div class="flavor-about__media">
				<div class="flavor-about__img-wrapper">
					<img src="<?php echo esc_url( $hero_img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" width="600" height="450" />
					<div class="flavor-about__exp-badge">
						<strong>۱۰+</strong>
						<span><?php esc_html_e( 'سال تجربه و افتخار میزبانی', 'flavor' ); ?></span>
					</div>
				</div>
			</div>

			<div class="flavor-about__content">
				<span class="flavor-section-header__tag"><?php esc_html_e( 'درباره ما', 'flavor' ); ?></span>
				<h2 class="flavor-about__title"><?php echo esc_html( $title ); ?></h2>
				<div class="flavor-about__text">
					<?php echo wp_kses_post( wpautop( $text ) ); ?>
				</div>

				<div class="flavor-about__stats">
					<div class="flavor-about__stat">
						<span class="flavor-about__stat-num">۱۰۰٪</span>
						<span class="flavor-about__stat-lbl"><?php esc_html_e( 'مواد اولیه تازه روزانه', 'flavor' ); ?></span>
					</div>
					<div class="flavor-about__stat">
						<span class="flavor-about__stat-num">۴.۹ ★</span>
						<span class="flavor-about__stat-lbl"><?php esc_html_e( 'رضایت مهمان‌ها', 'flavor' ); ?></span>
					</div>
					<div class="flavor-about__stat">
						<span class="flavor-about__stat-num">۳۵+</span>
						<span class="flavor-about__stat-lbl"><?php esc_html_e( 'تنوع آیتم‌های منو', 'flavor' ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
