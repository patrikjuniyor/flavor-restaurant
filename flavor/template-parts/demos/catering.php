<?php
/**
 * Mizan: an editorial corporate-catering composition and an honest local planner.
 *
 * @package Flavor
 */
defined( 'ABSPATH' ) || exit;
$demo  = \Flavor\Bespoke_Demos::demo();
$image = get_theme_mod( 'flavor_hero_image', '' ) ?: \Flavor\Bespoke_Demos::asset( 'hero.jpg' );
?>
<section class="fd-hero fd-mizan-hero" aria-labelledby="fd-hero-title">
	<div class="flavor-container">
		<div class="fd-mizan-hero__layout">
			<div class="fd-hero__copy">
				<span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'eyebrow' ) ); ?></span>
				<h1 id="fd-hero-title"><?php echo \Flavor\Bespoke_Demos::hero_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by helper. ?></h1>
				<p class="fd-hero__text"><?php echo esc_html( get_theme_mod( 'flavor_hero_text', $demo['hero_text'] ) ); ?></p>
				<div class="fd-hero__actions">
					<a class="fd-button fd-button--primary" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( '#proposal' ) ); ?>"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'primary_label' ) ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></a>
					<a class="fd-text-link" href="#services"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'secondary_label' ) ); ?></a>
				</div>
				<div class="fd-mizan-hero__signature" lang="en" dir="ltr" aria-hidden="true">WITH CARE.<br /><strong>WITH MIZAN.</strong></div>
			</div>
			<figure class="fd-mizan-hero__art">
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( \Flavor\Bespoke_Demos::value( 'hero_alt' ) ); ?>" width="1200" height="1000" fetchpriority="high" loading="eager" decoding="async" />
				<span class="fd-mizan-hero__seal" aria-hidden="true"><?php \Flavor\Bespoke_Demos::icon( 'cloche', 32 ); ?><span lang="en" dir="ltr">MIZAN<br /><small>CATERING & EVENTS</small></span></span>
				<figcaption><span class="fd-mizan-hero__caption-line" aria-hidden="true"></span><span><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'hero_caption' ) ); ?></span></figcaption>
			</figure>
		</div>
		<ul class="fd-mizan-promises" aria-label="<?php esc_attr_e( 'رویکرد پذیرایی میزان', 'flavor' ); ?>">
			<?php foreach ( $demo['landing']['perks'] as $perk ) : ?><li><?php \Flavor\Bespoke_Demos::icon( 'check', 18 ); ?><span><?php echo esc_html( $perk ); ?></span></li><?php endforeach; ?>
		</ul>
	</div>
</section>

<section class="fd-section fd-mizan-services" id="services" aria-labelledby="fd-services-title">
	<div class="flavor-container">
		<div class="fd-section-head">
			<div><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'services_eyebrow' ) ); ?></span><h2 id="fd-services-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'services_title' ) ); ?></h2></div>
			<p class="fd-mizan-services__intro"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'services_text' ) ); ?></p>
		</div>
		<div class="fd-mizan-services__grid">
			<?php foreach ( $demo['landing']['services'] as $index => $service ) : ?>
				<article class="fd-mizan-service">
					<a class="fd-mizan-service__media" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( '#proposal' ) ); ?>" data-fd-service="<?php echo esc_attr( $service['id'] ); ?>" aria-label="<?php echo esc_attr( $service['link'] ); ?>"><img src="<?php echo esc_url( \Flavor\Bespoke_Demos::asset( $service['image'] ) ); ?>" alt="<?php echo esc_attr( $service['alt'] ); ?>" width="840" height="650" loading="lazy" decoding="async" /></a>
					<div class="fd-mizan-service__body">
						<span class="fd-mizan-service__overline" lang="en" dir="ltr"><?php echo esc_html( $service['english'] ); ?></span>
						<div class="fd-mizan-service__title"><h3><?php echo esc_html( $service['label'] ); ?></h3><span aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $index + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span></div>
						<p><?php echo esc_html( $service['text'] ); ?></p>
						<ul><?php foreach ( $service['details'] as $detail ) : ?><li><?php \Flavor\Bespoke_Demos::icon( 'check', 14 ); ?><?php echo esc_html( $detail ); ?></li><?php endforeach; ?></ul>
						<a class="fd-text-link" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( '#proposal' ) ); ?>" data-fd-service="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['link'] ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 19 ); ?></a>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php get_template_part( 'template-parts/demos/menu' ); ?>
<?php get_template_part( 'template-parts/demos/story' ); ?>
<?php get_template_part( 'template-parts/demos/process' ); ?>

<?php if ( \Flavor\Bespoke_Demos::enabled( 'feature' ) ) : ?>
<section class="fd-section fd-mizan-proposal" id="proposal" aria-labelledby="fd-proposal-title">
	<div class="flavor-container fd-mizan-proposal__grid">
		<div class="fd-mizan-proposal__copy">
			<span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_eyebrow' ) ); ?></span>
			<h2 id="fd-proposal-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_title' ) ); ?></h2>
			<p><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'feature_text' ) ); ?></p>
			<ul><?php foreach ( $demo['landing']['proposal_notes'] as $note ) : ?><li><?php \Flavor\Bespoke_Demos::icon( 'check', 18 ); ?><span><?php echo esc_html( $note ); ?></span></li><?php endforeach; ?></ul>
			<a class="fd-mizan-proposal__phone" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><?php \Flavor\Bespoke_Demos::icon( 'phone', 22 ); ?><span><small><?php esc_html_e( 'گفت‌وگو با دفتر هماهنگی', 'flavor' ); ?></small><bdi><?php echo esc_html( \Flavor\Bespoke_Demos::digits( (string) get_theme_mod( 'flavor_phone', $demo['phone'] ) ) ); ?></bdi></span><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></a>
		</div>
		<div class="fd-mizan-proposal__panel">
			<div class="fd-mizan-proposal__panel-head"><h3 id="fd-planner-title"><?php esc_html_e( 'برنامه‌ریز پذیرایی', 'flavor' ); ?></h3><?php \Flavor\Bespoke_Demos::icon( 'calendar', 27 ); ?></div>
			<p class="fd-mizan-proposal__privacy" id="fd-planner-privacy"><?php esc_html_e( 'این ابزار فقط خلاصهٔ قابل‌کپی می‌سازد؛ هیچ اطلاعاتی ارسال یا ذخیره نمی‌شود و هیچ رزرو یا سفارشی ثبت نمی‌کند.', 'flavor' ); ?></p>
			<form class="fd-mizan-planner" data-fd-proposal aria-labelledby="fd-planner-title" aria-describedby="fd-planner-privacy" hidden>
				<div class="fd-mizan-planner__fields">
					<div class="fd-mizan-planner__field fd-mizan-planner__field--wide"><label for="fd-proposal-service"><?php esc_html_e( 'نوع پذیرایی', 'flavor' ); ?></label><select id="fd-proposal-service" name="service" required><?php foreach ( $demo['landing']['services'] as $service ) : ?><option value="<?php echo esc_attr( $service['id'] ); ?>"><?php echo esc_html( $service['label'] ); ?></option><?php endforeach; ?></select></div>
					<div class="fd-mizan-planner__field"><label for="fd-proposal-guests"><?php esc_html_e( 'تعداد مهمان پیشنهادی', 'flavor' ); ?> <span class="fd-mizan-planner__required"><?php esc_html_e( '(ضروری)', 'flavor' ); ?></span></label><input id="fd-proposal-guests" name="guests" type="text" inputmode="numeric" pattern="[0-9۰-۹٠-٩]+" maxlength="4" required placeholder="مثلاً ۵۰" aria-describedby="fd-guests-help" /><small id="fd-guests-help"><?php esc_html_e( '۱ تا ۱۰۰۰ نفر در برنامه‌ریز؛ ظرفیت باید تلفنی تأیید شود.', 'flavor' ); ?></small></div>
					<div class="fd-mizan-planner__field"><label for="fd-proposal-date"><?php esc_html_e( 'روز و ساعت پیشنهادی', 'flavor' ); ?> <span class="fd-mizan-planner__required"><?php esc_html_e( '(ضروری)', 'flavor' ); ?></span></label><input id="fd-proposal-date" name="date" type="text" maxlength="80" required placeholder="مثلاً ۲۵ مهر، ساعت ۱۳" /></div>
					<div class="fd-mizan-planner__field fd-mizan-planner__field--wide"><label for="fd-proposal-location"><?php esc_html_e( 'شهر و محل برنامه', 'flavor' ); ?> <span class="fd-mizan-planner__required"><?php esc_html_e( '(ضروری)', 'flavor' ); ?></span></label><input id="fd-proposal-location" name="location" type="text" maxlength="180" required placeholder="مثلاً تهران، دفتر شرکت در ونک" /></div>
					<div class="fd-mizan-planner__field fd-mizan-planner__field--wide"><label for="fd-proposal-notes"><?php esc_html_e( 'نیاز غذایی یا توضیح تکمیلی', 'flavor' ); ?> <span class="fd-mizan-planner__required"><?php esc_html_e( '(اختیاری)', 'flavor' ); ?></span></label><textarea id="fd-proposal-notes" name="notes" rows="3" maxlength="600" placeholder="حساسیت غذایی، شیوهٔ سرو، تجهیزات یا منوی مدنظر"></textarea></div>
				</div>
				<button class="fd-button fd-button--primary" type="submit"><?php esc_html_e( 'آماده‌کردن خلاصهٔ هماهنگی', 'flavor' ); ?><?php \Flavor\Bespoke_Demos::icon( 'arrow', 20 ); ?></button>
			</form>
			<noscript><div class="fd-mizan-planner__fallback"><p><?php esc_html_e( 'برای گفت‌وگو، نوع پذیرایی، تعداد، روز و محل برنامه و نیازهای غذایی را آماده کنید. برنامه‌ریز به JavaScript نیاز دارد؛ هماهنگی تلفنی بدون آن هم ممکن است.', 'flavor' ); ?></p><a class="fd-button fd-button--outline" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><?php esc_html_e( 'تماس برای هماهنگی', 'flavor' ); ?></a></div></noscript>
			<div class="fd-mizan-summary" data-fd-proposal-result role="region" aria-labelledby="fd-summary-title" tabindex="-1" hidden>
				<h3 id="fd-summary-title"><?php esc_html_e( 'خلاصهٔ شما آماده است؛ هنوز ارسال نشده.', 'flavor' ); ?></h3>
				<label class="screen-reader-text" for="fd-proposal-summary"><?php esc_html_e( 'متن خلاصهٔ هماهنگی برای کپی', 'flavor' ); ?></label>
				<textarea id="fd-proposal-summary" data-fd-summary rows="9" readonly></textarea>
				<div class="fd-mizan-summary__actions"><button class="fd-button fd-button--outline" type="button" data-fd-copy><?php esc_html_e( 'کپی خلاصه', 'flavor' ); ?><?php \Flavor\Bespoke_Demos::icon( 'copy', 18 ); ?></button><a class="fd-text-link" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><?php esc_html_e( 'تماس با تیم هماهنگی', 'flavor' ); ?></a></div>
				<p class="fd-mizan-summary__status" role="status" data-fd-copy-status></p>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_template_part( 'template-parts/demos/faq' ); ?>
<?php get_template_part( 'template-parts/demos/visit' ); ?>
