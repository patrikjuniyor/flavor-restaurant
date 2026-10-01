<?php
/** Native details are usable without JS and accessible by keyboard. @package Flavor */
defined( 'ABSPATH' ) || exit;
if ( ! \Flavor\Bespoke_Demos::enabled( 'faq' ) ) { return; }
$demo = \Flavor\Bespoke_Demos::demo();
?>
<section class="fd-section fd-faq" id="faq" aria-labelledby="fd-faq-title">
	<div class="flavor-container fd-faq__grid"><div><span class="fd-eyebrow"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'faq_eyebrow', __( 'قبل از انتخاب', 'flavor' ) ) ); ?></span><h2 id="fd-faq-title"><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'faq_title', __( 'سؤالی مانده؟', 'flavor' ) ) ); ?></h2><p><?php echo esc_html( \Flavor\Bespoke_Demos::value( 'faq_text', __( 'برای راهنمایی بیشتر، با ما تماس بگیرید.', 'flavor' ) ) ); ?></p><a class="fd-text-link" href="<?php echo esc_url( \Flavor\Bespoke_Demos::action_url( 'phone' ) ); ?>"><?php esc_html_e( 'گفت‌وگو با ما', 'flavor' ); ?><?php \Flavor\Bespoke_Demos::icon( 'phone', 18 ); ?></a></div>
		<div class="fd-faq__items"><?php foreach ( $demo['landing']['faq'] ?? array() as $item ) : ?><details><summary><?php echo esc_html( $item['question'] ); ?><span aria-hidden="true">+</span></summary><div class="fd-faq__answer"><?php echo wp_kses_post( wpautop( $item['answer'] ) ); ?></div></details><?php endforeach; ?></div>
	</div>
</section>
