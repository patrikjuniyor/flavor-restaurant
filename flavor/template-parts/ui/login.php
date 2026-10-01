<?php
/** Progressive phone login using Core's existing OTP endpoints. @package Flavor */
defined( 'ABSPATH' ) || exit;
?>
<section class="flavor-account-login" aria-labelledby="flavor-phone-login-title">
 <div class="flavor-account-login__intro"><span class="flavor-ui-overline"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span><h2 id="flavor-phone-login-title"><?php esc_html_e( 'ورود ساده، ادامهٔ راحت‌تر.', 'flavor' ); ?></h2><p><?php esc_html_e( 'با تأیید شمارهٔ موبایل وارد حساب شوید. اگر حسابی ندارید، پس از تأیید شماره یک حساب مشتری ساخته می‌شود.', 'flavor' ); ?></p><ul><li><?php \Flavor\UI::icon( 'bag', 18 ); ?><?php esc_html_e( 'سفارش‌های متصل به حساب', 'flavor' ); ?></li><li><?php \Flavor\UI::icon( 'pin', 18 ); ?><?php esc_html_e( 'مدیریت نشانی و اطلاعات دریافت', 'flavor' ); ?></li><li><?php \Flavor\UI::icon( 'user', 18 ); ?><?php esc_html_e( 'ادامه با نشست امن سایت', 'flavor' ); ?></li></ul></div>
 <div class="flavor-account-login__panel"><span class="flavor-account-login__mark" aria-hidden="true"><?php \Flavor\UI::icon( 'user', 28 ); ?></span><h3><?php esc_html_e( 'ورود با کد یک‌بارمصرف', 'flavor' ); ?></h3>
  <form data-flavor-auth data-auth-context="account" hidden>
   <div class="flavor-ui-field"><label for="flavor-account-mobile"><?php esc_html_e( 'شمارهٔ موبایل', 'flavor' ); ?></label><input type="tel" id="flavor-account-mobile" data-auth-mobile inputmode="tel" dir="ltr" autocomplete="tel" maxlength="16" placeholder="09xxxxxxxxx" required /></div>
   <button type="button" class="flavor-btn flavor-btn--primary flavor-btn--full" data-auth-send><?php esc_html_e( 'دریافت کد ورود', 'flavor' ); ?></button>
   <div class="flavor-auth-code" data-auth-code-box hidden><div class="flavor-ui-field"><label for="flavor-account-code"><?php esc_html_e( 'کد دریافتی', 'flavor' ); ?></label><input type="text" id="flavor-account-code" data-auth-code inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="6" placeholder="<?php esc_attr_e( 'کد یک‌بارمصرف', 'flavor' ); ?>" disabled /></div><button type="button" class="flavor-btn flavor-btn--primary flavor-btn--full" data-auth-verify><?php esc_html_e( 'تأیید و ورود', 'flavor' ); ?></button></div>
   <p data-auth-status role="status" aria-live="polite" class="flavor-auth-status"></p>
  </form>
  <noscript><p class="flavor-ui-note"><?php esc_html_e( 'ورود پیامکی به JavaScript نیاز دارد. از فرم اصلی نام کاربری و رمز عبور در ادامه استفاده کنید.', 'flavor' ); ?></p></noscript>
  <p class="flavor-account-login__help"><?php esc_html_e( 'ورود با رمز عبور نیز در فرم اصلی ووکامرس، پایین این بخش، در دسترس است.', 'flavor' ); ?></p>
 </div>
</section>
