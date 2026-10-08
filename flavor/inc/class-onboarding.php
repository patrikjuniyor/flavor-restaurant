<?php
/**
 * 10-Step Restaurant Setup & Onboarding Wizard.
 *
 * Provides a guided "Install -> Choose Design -> Setup Restaurant -> Launch" experience.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Onboarding
 */
class Onboarding {

	/**
	 * User meta key used for resumable wizard drafts.
	 */
	private const DRAFT_META = 'flavor_wizard_draft';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 5 );
		add_action( 'admin_post_flavor_save_wizard', array( self::class, 'handle_submit' ) );
		add_action( 'wp_ajax_flavor_save_wizard_draft', array( self::class, 'ajax_save_draft' ) );
		add_action( 'admin_notices', array( self::class, 'setup_notice' ) );
	}

	/**
	 * Register menu item.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'راه‌اندازی سریع رستوران', 'flavor' ),
			__( '🚀 راه‌اندازی سریع Flavor', 'flavor' ),
			'manage_options',
			'flavor-setup',
			array( self::class, 'render' )
		);
	}

	/**
	 * Welcome notice for fresh installations.
	 */
	public static function setup_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'flavor_setup_completed', false ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'] ) && 'flavor-setup' === $_GET['page'] ) {
			return;
		}
		$url = admin_url( 'themes.php?page=flavor-setup' );
		?>
		<div class="notice notice-info is-dismissible" style="border-right-color: #8a5a2b; border-right-width: 4px;">
			<p>
				<strong><?php esc_html_e( 'به پلتفرم رستوران مستقیم Flavor خوش آمدید!', 'flavor' ); ?></strong>
				<?php esc_html_e( 'برای راه‌اندازی هویت بصری، منو، رزرو و شعب رستوران در کمتر از ۲ دقیقه، ویزارد راه‌اندازی سریع را اجرا کنید.', 'flavor' ); ?>
				<a class="button button-primary" href="<?php echo esc_url( $url ); ?>" style="margin-right: 10px; background: #8a5a2b; border-color: #6f441c;">
					<?php esc_html_e( 'شروع راه‌اندازی ۱۰ مرحله‌ای', 'flavor' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the full-screen setup wizard.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}

		wp_enqueue_media();
		$draft   = self::draft();
		$skins   = Design::skins();
		$current = isset( $draft['flavor_skin'] ) ? sanitize_key( (string) $draft['flavor_skin'] ) : Design::current_skin();
		if ( ! isset( $skins[ $current ] ) ) {
			$current = Design::current_skin();
		}
		$name             = (string) ( $draft['restaurant_name'] ?? get_bloginfo( 'name' ) );
		$tagline          = (string) ( $draft['restaurant_tagline'] ?? get_bloginfo( 'description' ) );
		$phone            = (string) ( $draft['phone'] ?? get_theme_mod( 'flavor_phone', '02188001234' ) );
		$address          = (string) ( $draft['address'] ?? get_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر' ) );
		$city             = (string) ( $draft['city'] ?? get_theme_mod( 'flavor_city', 'تهران' ) );
		$hero_image_url   = (string) ( $draft['hero_image_url'] ?? get_theme_mod( 'flavor_hero_image', '' ) );
		$hero_image_id    = absint( $draft['hero_image_id'] ?? 0 );
		$logo_id          = absint( $draft['custom_logo_id'] ?? get_theme_mod( 'custom_logo', 0 ) );
		$opening_hours    = (string) ( $draft['opening_hours'] ?? 'همه روزه از ساعت ۱۱:۳۰ الی ۲۳:۴۵' );
		$primary_color    = (string) ( $draft['primary_color'] ?? get_theme_mod( 'flavor_primary', '#8a5a2b' ) );
		$secondary_color  = (string) ( $draft['secondary_color'] ?? get_theme_mod( 'flavor_secondary', '#5c6b3a' ) );
		$instagram        = (string) ( $draft['instagram'] ?? get_theme_mod( 'flavor_social_instagram', '' ) );
		$telegram         = (string) ( $draft['telegram'] ?? get_theme_mod( 'flavor_social_telegram', '' ) );
		$first_item_name  = (string) ( $draft['first_item_name'] ?? 'چلوکباب کوبیده مخصوص' );
		$first_item_price = absint( $draft['first_item_price'] ?? 285000 );
		$first_item_cat   = (string) ( $draft['first_item_cat'] ?? 'کباب و خوراک' );
		$modes            = (array) ( $draft['modes'] ?? array( 'dine_in', 'takeaway', 'delivery' ) );
		$draft_step       = min( 10, max( 1, absint( $draft['step'] ?? 1 ) ) );
		$completed        = isset( $_GET['completed'] ) && '1' === sanitize_key( wp_unslash( $_GET['completed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$launch_checks    = class_exists( self::class ) && class_exists( Launch_Center::class ) ? Launch_Center::checks() : array();
		?>
		<div class="wrap flavor-wizard-wrap">
			<style>
				.flavor-wizard-wrap { max-width: 900px; margin: 30px auto; font-family: Tahoma, Vazirmatn, sans-serif; }
				.flavor-wizard-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 36px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); }
				.flavor-wizard-header { text-align: center; margin-bottom: 32px; }
				.flavor-wizard-header h1 { font-size: 26px; color: #1e293b; margin: 0 0 8px; }
				.flavor-wizard-header p { color: #64748b; font-size: 15px; margin: 0; }
				.flavor-steps-bar { display: flex; justify-content: space-between; position: relative; margin-bottom: 40px; padding: 0 10px; }
				.flavor-steps-bar::before { content: ''; position: absolute; top: 18px; right: 20px; left: 20px; height: 3px; background: #e2e8f0; z-index: 1; }
				.flavor-step-node { position: relative; z-index: 2; background: #fff; width: 38px; height: 38px; border-radius: 50%; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-weight: bold; color: #64748b; font-size: 14px; cursor: pointer; transition: all 0.2s; }
				.flavor-step-node.is-active { border-color: #8a5a2b; background: #8a5a2b; color: #fff; box-shadow: 0 0 0 4px rgba(138,90,43,0.2); }
				.flavor-step-node.is-done { border-color: #16a34a; background: #16a34a; color: #fff; }
				.flavor-step-pane { display: none; }
				.flavor-step-pane.is-active { display: block; animation: flavorFadeIn 0.3s ease; }
				@keyframes flavorFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
				.flavor-form-row { margin-bottom: 20px; }
				.flavor-form-row label { display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 14px; }
				.flavor-form-row input[type="text"], .flavor-form-row input[type="number"], .flavor-form-row input[type="url"], .flavor-form-row textarea, .flavor-form-row select { width: 100%; max-width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; }
				.flavor-presets-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; max-height: 400px; overflow-y: auto; padding: 4px; }
				.flavor-preset-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 16px; cursor: pointer; transition: all 0.2s; text-align: right; }
				.flavor-preset-card:hover { border-color: #cbd5e1; transform: translateY(-2px); }
				.flavor-preset-card.is-selected { border-color: #8a5a2b; background: #fdfaf6; box-shadow: 0 4px 14px rgba(138,90,43,0.15); }
				.flavor-preset-card h4 { margin: 0 0 6px; color: #1e293b; font-size: 15px; }
				.flavor-preset-card p { margin: 0; font-size: 12px; color: #64748b; line-height: 1.5; }
				.flavor-wizard-nav { display: flex; justify-content: space-between; margin-top: 36px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
				.flavor-btn-step { background: #8a5a2b; color: #fff; border: 0; padding: 12px 28px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
				.flavor-btn-step:hover { background: #71471f; color: #fff; }
				.flavor-btn-prev { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; }
			</style>

			<div class="flavor-wizard-card">
				<?php if ( $completed ) : ?>
					<div class="notice notice-success inline"><p><?php esc_html_e( 'راه‌اندازی ذخیره شد. مرکز آمادگی انتشار را برای بررسی درگاه، شعبه، منو و سفارش آزمایشی باز کنید.', 'flavor' ); ?> <a href="<?php echo esc_url( admin_url( 'themes.php?page=flavor-launch' ) ); ?>"><?php esc_html_e( 'باز کردن مرکز آمادگی انتشار', 'flavor' ); ?></a> <span aria-hidden="true">·</span> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'مشاهدهٔ سایت', 'flavor' ); ?></a></p></div>
				<?php endif; ?>
				<div class="flavor-wizard-header">
					<h1><?php esc_html_e( 'راه‌اندازی سریع رستوران در ۱۰ گام ساده', 'flavor' ); ?></h1>
					<p><?php esc_html_e( 'اطلاعات اولیه رستوران را تکمیل کنید تا سایت و سیستم سفارش آنلاین شما آماده بهره‌برداری شود. پیشرفت فرم به‌صورت خودکار ذخیره می‌شود.', 'flavor' ); ?></p>
				</div>

				<div class="flavor-steps-bar">
					<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
						<div class="flavor-step-node <?php echo 1 === $i ? 'is-active' : ''; ?>" data-step="<?php echo esc_attr( (string) $i ); ?>">
							<?php echo esc_html( (string) $i ); ?>
						</div>
					<?php endfor; ?>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="flavor-wizard-form">
					<?php wp_nonce_field( 'flavor_save_wizard_action', 'flavor_wizard_nonce' ); ?>
					<input type="hidden" name="action" value="flavor_save_wizard" />

					<!-- Step 1: Name & Tagline -->
					<div class="flavor-step-pane is-active" data-pane="1">
						<h3><?php esc_html_e( 'گام ۱: نام و شعار رستوران', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'نام رستوران / کافه', 'flavor' ); ?></label>
							<input type="text" name="restaurant_name" value="<?php echo esc_attr( $name ); ?>" required />
						</div>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'شعار یا زمینه فعالیت', 'flavor' ); ?></label>
							<input type="text" name="restaurant_tagline" value="<?php echo esc_attr( $tagline ); ?>" placeholder="<?php esc_attr_e( 'مثال: طعم اصیل کباب و نان داغ در محیطی آرام', 'flavor' ); ?>" />
						</div>
					</div>

					<!-- Step 2: Logo -->
					<div class="flavor-step-pane" data-pane="2">
						<h3><?php esc_html_e( 'گام ۲: نشان و لوگوی رستوران', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'لوگوی باکیفیت (PNG با پس‌زمینه شفاف پیشنهاد می‌شود)', 'flavor' ); ?></label>
							<input type="hidden" name="custom_logo_id" id="flavor_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
							<button type="button" class="button button-secondary" id="flavor_upload_logo_btn">
								<?php esc_html_e( 'انتخاب یا آپلود لوگو از رسانه', 'flavor' ); ?>
							</button>
							<div id="flavor_logo_preview" style="margin-top: 14px; max-height: 80px;"></div>
						</div>
					</div>

					<!-- Step 3: Cover Image -->
					<div class="flavor-step-pane" data-pane="3">
						<h3><?php esc_html_e( 'گام ۳: تصویر اصلی هیرو (کاور)', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'تصویر جذاب و باکیفیت از فضای رستوران یا غذاهای شاخص', 'flavor' ); ?></label>
							<input type="hidden" name="hero_image_id" id="flavor_hero_id" value="<?php echo esc_attr( (string) $hero_image_id ); ?>" />
							<input type="url" name="hero_image_url" id="flavor_hero_url" value="<?php echo esc_attr( $hero_image_url ); ?>" placeholder="https://..." />
							<button type="button" class="button button-secondary" id="flavor_upload_hero_btn" style="margin-top: 8px;">
								<?php esc_html_e( 'انتخاب از گالری رسانه', 'flavor' ); ?>
							</button>
						</div>
					</div>

					<!-- Step 4: Colors -->
					<div class="flavor-step-pane" data-pane="4">
						<h3><?php esc_html_e( 'گام ۴: رنگ‌های سازمانی برند', 'flavor' ); ?></h3>
						<div class="flavor-form-row" style="display: flex; gap: 20px;">
							<div style="flex: 1;">
								<label><?php esc_html_e( 'رنگ اصلی برند', 'flavor' ); ?></label>
								<input type="color" name="primary_color" value="<?php echo esc_attr( $primary_color ); ?>" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px;" />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'رنگ ثانویه / تاکیدی', 'flavor' ); ?></label>
								<input type="color" name="secondary_color" value="<?php echo esc_attr( $secondary_color ); ?>" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px;" />
							</div>
						</div>
					</div>

					<!-- Step 5: Design Preset -->
					<div class="flavor-step-pane" data-pane="5">
						<h3><?php esc_html_e( 'گام ۵: انتخاب سبک و پوسته طراحی', 'flavor' ); ?></h3>
						<input type="hidden" name="flavor_skin" id="flavor_skin_val" value="<?php echo esc_attr( $current ); ?>" />
						<div class="flavor-presets-grid">
							<?php foreach ( $skins as $slug => $data ) : ?>
								<div class="flavor-preset-card <?php echo $slug === $current ? 'is-selected' : ''; ?>" data-skin="<?php echo esc_attr( $slug ); ?>">
									<h4><?php echo esc_html( $data['title'] ); ?></h4>
									<p><?php echo esc_html( $data['desc'] ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Step 6: Opening Hours & Service Modes -->
					<div class="flavor-step-pane" data-pane="6">
						<h3><?php esc_html_e( 'گام ۶: ساعات کاری و حالت‌های سفارش', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'ساعات کاری رستوران', 'flavor' ); ?></label>
							<input type="text" name="opening_hours" value="<?php echo esc_attr( $opening_hours ); ?>" />
						</div>
						<div class="flavor-form-row">
							<label style="margin-bottom: 10px;"><?php esc_html_e( 'حالت‌های فعال سفارش‌گیری', 'flavor' ); ?></label>
							<label style="font-weight: normal; margin-left: 20px;"><input type="checkbox" name="mode_dine_in" value="1" <?php checked( in_array( 'dine_in', $modes, true ) ); ?> /> <?php esc_html_e( 'سفارش سر میز سالن (QR)', 'flavor' ); ?></label>
							<label style="font-weight: normal; margin-left: 20px;"><input type="checkbox" name="mode_takeaway" value="1" <?php checked( in_array( 'takeaway', $modes, true ) ); ?> /> <?php esc_html_e( 'سفارش بیرون‌بر', 'flavor' ); ?></label>
							<label style="font-weight: normal;"><input type="checkbox" name="mode_delivery" value="1" <?php checked( in_array( 'delivery', $modes, true ) ); ?> /> <?php esc_html_e( 'ارسال سریع با پیک', 'flavor' ); ?></label>
						</div>
					</div>

					<!-- Step 7: Address & Delivery Area -->
					<div class="flavor-step-pane" data-pane="7">
						<h3><?php esc_html_e( 'گام ۷: نشانی و موقعیت جغرافیایی', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'شهر', 'flavor' ); ?></label>
							<input type="text" name="city" value="<?php echo esc_attr( $city ); ?>" />
						</div>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'نشانی دقیق شعبه اصلی', 'flavor' ); ?></label>
							<textarea name="address" rows="2"><?php echo esc_textarea( $address ); ?></textarea>
						</div>
					</div>

					<!-- Step 8: Contact & Socials -->
					<div class="flavor-step-pane" data-pane="8">
						<h3><?php esc_html_e( 'گام ۸: تلفن و شبکه‌های اجتماعی', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'شماره تلفن رستوران (جهت تماس و سفارش تلفنی)', 'flavor' ); ?></label>
							<input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" />
						</div>
						<div class="flavor-form-row" style="display: flex; gap: 16px;">
							<div style="flex: 1;">
								<label><?php esc_html_e( 'آدرس اینستاگرام', 'flavor' ); ?></label>
								<input type="url" name="instagram" value="<?php echo esc_attr( $instagram ); ?>" placeholder="https://instagram.com/..." />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'کانال تلگرام', 'flavor' ); ?></label>
								<input type="url" name="telegram" value="<?php echo esc_attr( $telegram ); ?>" placeholder="https://t.me/..." />
							</div>
						</div>
					</div>

					<!-- Step 9: First Menu Item -->
					<div class="flavor-step-pane" data-pane="9">
						<h3><?php esc_html_e( 'گام ۹: افزودن اولین آیتم منوی غذا', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'نام غذا / نوشیدنی', 'flavor' ); ?></label>
							<input type="text" name="first_item_name" value="<?php echo esc_attr( $first_item_name ); ?>" />
						</div>
						<div class="flavor-form-row" style="display: flex; gap: 16px;">
							<div style="flex: 1;">
								<label><?php esc_html_e( 'قیمت (تومان)', 'flavor' ); ?></label>
								<input type="number" name="first_item_price" value="<?php echo esc_attr( (string) $first_item_price ); ?>" min="0" step="1" />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'دسته‌بندی', 'flavor' ); ?></label>
								<input type="text" name="first_item_cat" value="<?php echo esc_attr( $first_item_cat ); ?>" />
							</div>
						</div>
					</div>

					<!-- Step 10: Publish & Launch -->
					<div class="flavor-step-pane" data-pane="10">
						<div style="text-align: center; padding: 20px 0;">
							<div style="font-size: 54px; margin-bottom: 12px;">🎉</div>
							<h2><?php esc_html_e( 'همه چیز آماده راه‌اندازی است!', 'flavor' ); ?></h2>
							<p style="color: #64748b; max-width: 500px; margin: 0 auto 24px;">
								<?php esc_html_e( 'با کلیک روی دکمه زیر، اطلاعات ذخیره شده، صفحات اصلی، شعبه و اولین آیتم منو آماده می‌شوند. پس از آن مرکز آمادگی انتشار مواردی مثل درگاه و سفارش آزمایشی را بررسی می‌کند.', 'flavor' ); ?>
							</p>
							<?php if ( $launch_checks ) : ?>
								<ul style="max-width:620px;margin:0 auto 24px;text-align:right;padding:14px 20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;list-style:none;">
									<?php foreach ( array_slice( $launch_checks, 0, 6 ) as $launch_check ) : ?>
										<li style="padding:5px 0;"><strong><?php echo 'pass' === $launch_check['status'] ? '✓' : '!' ; ?></strong> <?php echo esc_html( $launch_check['label'] ); ?> — <?php echo esc_html( $launch_check['status_label'] ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
							<button type="submit" class="flavor-btn-step" style="font-size: 16px; padding: 14px 40px; margin: 0 auto;">
								<?php esc_html_e( '🚀 انتشار و مشاهده وبسایت رستوران', 'flavor' ); ?>
							</button>
						</div>
					</div>

					<div id="flavor-draft-status" role="status" aria-live="polite" style="min-height:20px;margin-top:16px;color:#64748b;font-size:12px;"></div>
					<div class="flavor-wizard-nav" id="flavor-wizard-nav-bar">
						<button type="button" class="flavor-btn-prev" id="flavor-prev-btn" style="visibility: hidden;">
							<?php esc_html_e( '← گام قبلی', 'flavor' ); ?>
						</button>
						<button type="button" class="flavor-btn-step" id="flavor-next-btn">
							<?php esc_html_e( 'گام بعدی →', 'flavor' ); ?>
						</button>
					</div>
				</form>
			</div>

			<script>
				(function() {
					var currentStep = <?php echo esc_js( (string) $draft_step ); ?>;
					var totalSteps = 10;
					var form = document.getElementById('flavor-wizard-form');
					var nextBtn = document.getElementById('flavor-next-btn');
					var prevBtn = document.getElementById('flavor-prev-btn');
					var navBar = document.getElementById('flavor-wizard-nav-bar');
					var draftStatus = document.getElementById('flavor-draft-status');
					var saveTimer = null;

					function saveDraft() {
						if (!form || !window.fetch || typeof ajaxurl === 'undefined') return;
						var data = new FormData(form);
						data.set('action', 'flavor_save_wizard_draft');
						data.set('nonce', form.querySelector('[name="flavor_wizard_nonce"]').value);
						data.set('step', String(currentStep));
						if (draftStatus) draftStatus.textContent = 'در حال ذخیرهٔ پیشرفت…';
						window.fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
							.then(function(response) { return response.json(); })
							.then(function(result) { if (draftStatus) draftStatus.textContent = result.success ? 'پیشرفت شما ذخیره شد.' : 'ذخیرهٔ پیشرفت انجام نشد؛ دوباره تلاش کنید.'; })
							.catch(function() { if (draftStatus) draftStatus.textContent = 'اتصال به پیشخوان برای ذخیرهٔ پیشرفت برقرار نشد.'; });
					}

					function scheduleDraft() {
						window.clearTimeout(saveTimer);
						saveTimer = window.setTimeout(saveDraft, 500);
					}

					function validateStep() {
						var pane = document.querySelector('[data-pane="' + currentStep + '"]');
						if (!pane) return true;
						var invalid = pane.querySelector(':invalid');
						if (invalid) { invalid.reportValidity(); return false; }
						if (currentStep === 6 && !pane.querySelector('input[name^="mode_"]:checked')) {
							window.alert('حداقل یکی از حالت‌های سفارش‌گیری را انتخاب کنید.');
							return false;
						}
						return true;
					}

					function goToStep(step) {
						if (step < 1 || step > totalSteps) return;
						if (step > currentStep && !validateStep()) return;
						currentStep = step;
						scheduleDraft();

						document.querySelectorAll('.flavor-step-node').forEach(function(node) {
							var s = parseInt(node.getAttribute('data-step'), 10);
							node.classList.toggle('is-active', s === currentStep);
							node.classList.toggle('is-done', s < currentStep);
							node.setAttribute('aria-current', s === currentStep ? 'step' : 'false');
						});

						document.querySelectorAll('.flavor-step-pane').forEach(function(pane) {
							var p = parseInt(pane.getAttribute('data-pane'), 10);
							pane.classList.toggle('is-active', p === currentStep);
						});

						prevBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
						navBar.style.display = currentStep === totalSteps ? 'none' : 'flex';
					}

					nextBtn.addEventListener('click', function() {
						if (validateStep()) goToStep(currentStep + 1);
					});

					if (form) {
						form.addEventListener('input', scheduleDraft);
						form.addEventListener('change', scheduleDraft);
						form.addEventListener('submit', function() { saveDraft(); });
					}

					prevBtn.addEventListener('click', function() {
						goToStep(currentStep - 1);
					});

					document.querySelectorAll('.flavor-step-node').forEach(function(node) {
						node.addEventListener('click', function() {
							var s = parseInt(this.getAttribute('data-step'), 10);
							goToStep(s);
						});
					});

					// Presets Selection
					document.querySelectorAll('.flavor-preset-card').forEach(function(card) {
						card.addEventListener('click', function() {
							document.querySelectorAll('.flavor-preset-card').forEach(function(c) { c.classList.remove('is-selected'); });
							this.classList.add('is-selected');
							document.getElementById('flavor_skin_val').value = this.getAttribute('data-skin');
						});
					});

					// Logo Media Uploader
					var logoBtn = document.getElementById('flavor_upload_logo_btn');
					if (logoBtn) {
						logoBtn.addEventListener('click', function(e) {
							e.preventDefault();
							var customUploader = wp.media({
								title: 'انتخاب لوگوی رستوران',
								button: { text: 'استفاده از این لوگو' },
								multiple: false
							});
							customUploader.on('select', function() {
								var attachment = customUploader.state().get('selection').first().toJSON();
								document.getElementById('flavor_logo_id').value = attachment.id;
								document.getElementById('flavor_logo_preview').innerHTML = '<img src="' + attachment.url + '" style="max-height: 70px; border-radius: 8px;" />';
								scheduleDraft();
							});
							customUploader.open();
						});
					}

					// Hero Media Uploader
					var heroBtn = document.getElementById('flavor_upload_hero_btn');
					if (heroBtn) {
						heroBtn.addEventListener('click', function(e) {
							e.preventDefault();
							var heroUploader = wp.media({
								title: 'انتخاب تصویر کاور هیرو',
								button: { text: 'استفاده از این تصویر' },
								multiple: false
							});
							heroUploader.on('select', function() {
								var attachment = heroUploader.state().get('selection').first().toJSON();
								document.getElementById('flavor_hero_url').value = attachment.url;
								document.getElementById('flavor_hero_id').value = attachment.id;
								scheduleDraft();
							});
							heroUploader.open();
						});
					}
					goToStep(currentStep);
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Read the current user's resumable draft.
	 *
	 * @return array<string, mixed>
	 */
	public static function draft(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}
		$draft = get_user_meta( $user_id, self::DRAFT_META, true );
		return is_array( $draft ) ? $draft : array();
	}

	/**
	 * Save a draft from the wizard without publishing any site configuration.
	 */
	public static function ajax_save_draft(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'flavor' ) ), 403 );
		}
		check_ajax_referer( 'flavor_save_wizard_action', 'nonce' );

		$data         = self::sanitize_submission( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$data['step'] = isset( $_POST['step'] ) ? min( 10, max( 1, absint( $_POST['step'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$draft        = array_merge( self::draft(), $data );
		update_user_meta( get_current_user_id(), self::DRAFT_META, $draft );

		wp_send_json_success(
			array(
				'step'     => $data['step'],
				'saved_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Handle form submission and save all configuration.
	 */
	public static function handle_submit(): void {
		check_admin_referer( 'flavor_save_wizard_action', 'flavor_wizard_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}

		$data = self::sanitize_submission( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		self::persist_configuration( $data );
		delete_user_meta( get_current_user_id(), self::DRAFT_META );
		update_option( 'flavor_setup_completed', true );

		wp_safe_redirect( admin_url( 'themes.php?page=flavor-setup&completed=1' ) );
		exit;
	}

	/**
	 * Normalize and sanitize all wizard fields in one place.
	 *
	 * @param array<string, mixed> $source Raw POST-like data.
	 * @return array<string, mixed>
	 */
	private static function sanitize_submission( array $source ): array {
		$value = static function ( string $key, string $default = '' ) use ( $source ): string {
			if ( ! isset( $source[ $key ] ) || ! is_scalar( $source[ $key ] ) ) {
				return $default;
			}
			return (string) wp_unslash( $source[ $key ] );
		};

		$primary        = sanitize_hex_color( $value( 'primary_color', '#8a5a2b' ) );
		$secondary      = sanitize_hex_color( $value( 'secondary_color', '#5c6b3a' ) );
		$hero_image_id  = absint( $value( 'hero_image_id', '0' ) );
		$hero_image_url = esc_url_raw( $value( 'hero_image_url' ) );
		if ( $hero_image_id && 'attachment' !== get_post_type( $hero_image_id ) ) {
			$hero_image_id = 0;
		}
		if ( $hero_image_id && '' === $hero_image_url ) {
			$hero_image_url = (string) wp_get_attachment_image_url( $hero_image_id, 'full' );
		}
		$modes = array();
		foreach ( array( 'dine_in', 'takeaway', 'delivery' ) as $mode ) {
			if ( ! empty( $source[ 'mode_' . $mode ] ) ) {
				$modes[] = $mode;
			}
		}
		$skin = sanitize_key( $value( 'flavor_skin' ) );
		if ( ! isset( Design::skins()[ $skin ] ) ) {
			$skin = Design::current_skin();
		}
		$name = sanitize_text_field( $value( 'restaurant_name' ) );
		if ( '' === $name ) {
			$name = sanitize_text_field( (string) get_bloginfo( 'name' ) );
		}

		return array(
			'restaurant_name'    => $name,
			'restaurant_tagline' => sanitize_text_field( $value( 'restaurant_tagline' ) ),
			'custom_logo_id'     => absint( $value( 'custom_logo_id', '0' ) ),
			'hero_image_id'      => $hero_image_id,
			'hero_image_url'     => $hero_image_url,
			'primary_color'      => $primary ? $primary : '#8a5a2b',
			'secondary_color'    => $secondary ? $secondary : '#5c6b3a',
			'flavor_skin'        => $skin,
			'opening_hours'      => sanitize_text_field( $value( 'opening_hours', 'همه روزه از ساعت ۱۱:۳۰ الی ۲۳:۴۵' ) ),
			'city'               => sanitize_text_field( $value( 'city', 'تهران' ) ),
			'address'            => sanitize_textarea_field( $value( 'address' ) ),
			'phone'              => sanitize_text_field( $value( 'phone' ) ),
			'instagram'          => esc_url_raw( $value( 'instagram' ) ),
			'telegram'           => esc_url_raw( $value( 'telegram' ) ),
			'first_item_name'    => sanitize_text_field( $value( 'first_item_name' ) ),
			'first_item_price'   => absint( $value( 'first_item_price', '0' ) ),
			'first_item_cat'     => sanitize_text_field( $value( 'first_item_cat', 'اصلی' ) ),
			'modes'             => $modes,
		);
	}

	/**
	 * Persist the wizard's site, branch, hours, Core and WooCommerce settings.
	 *
	 * @param array<string, mixed> $data Sanitized wizard data.
	 */
	private static function persist_configuration( array $data ): void {
		update_option( 'blogname', $data['restaurant_name'] );
		update_option( 'blogdescription', $data['restaurant_tagline'] );

		if ( ! empty( $data['custom_logo_id'] ) ) {
			set_theme_mod( 'custom_logo', absint( $data['custom_logo_id'] ) );
		} else {
			remove_theme_mod( 'custom_logo' );
		}
		set_theme_mod( 'flavor_hero_image', $data['hero_image_url'] );
		set_theme_mod( 'flavor_hero_image_id', absint( $data['hero_image_id'] ) );
		set_theme_mod( 'flavor_primary', $data['primary_color'] );
		set_theme_mod( 'flavor_secondary', $data['secondary_color'] );
		set_theme_mod( 'flavor_skin', $data['flavor_skin'] );
		set_theme_mod( 'flavor_phone', $data['phone'] );
		set_theme_mod( 'flavor_address', $data['address'] );
		set_theme_mod( 'flavor_city', $data['city'] );
		set_theme_mod( 'flavor_hours', $data['opening_hours'] );
		set_theme_mod( 'flavor_opening_hours', $data['opening_hours'] );
		set_theme_mod( 'flavor_social_instagram', $data['instagram'] );
		set_theme_mod( 'flavor_social_telegram', $data['telegram'] );

		self::ensure_pages();
		self::ensure_primary_menu();
		$branch_id = self::ensure_branch( $data );
		if ( $branch_id ) {
			self::sync_branch_hours( $branch_id, $data['opening_hours'] );
		}

		if ( class_exists( '\FlavorCore\Support\Settings' ) ) {
			$settings = array(
				'currency_display'  => 'irt',
				'default_branch_id' => $branch_id,
				'pay_at_counter'    => 'yes',
				'guest_checkout'    => 'yes',
			);
			if ( in_array( 'delivery', $data['modes'], true ) ) {
				$settings['cash_on_delivery'] = 'yes';
				$settings['card_on_delivery'] = 'yes';
			}
			\FlavorCore\Support\Settings::update( $settings );
		}

		if ( function_exists( 'wc_get_product' ) ) {
			if ( '' === (string) get_option( 'woocommerce_currency', '' ) || 'USD' === get_option( 'woocommerce_currency' ) ) {
				update_option( 'woocommerce_currency', 'IRR' );
			}
			self::ensure_first_product( $data );
		}
	}

	/**
	 * Ensure the default branch exists and receives onboarding values.
	 *
	 * @param array<string, mixed> $data Sanitized values.
	 * @return int Branch id or 0.
	 */
	private static function ensure_branch( array $data ): int {
		$branch_id = 0;
		if ( class_exists( '\FlavorCore\PostTypes\BranchPostType' ) ) {
			$branch_id = (int) \FlavorCore\PostTypes\BranchPostType::default_id();
		}
		if ( ! $branch_id ) {
			$ids = get_posts( array( 'post_type' => 'flavor_branch', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ) );
			$branch_id = empty( $ids ) ? 0 : (int) $ids[0];
		}
		if ( ! $branch_id && post_type_exists( 'flavor_branch' ) ) {
			$new_branch_id = wp_insert_post(
				array(
					'post_type'   => 'flavor_branch',
					'post_status' => 'publish',
					'post_title'  => $data['restaurant_name'] ? $data['restaurant_name'] . ' — شعبه مرکزی' : 'شعبه مرکزی',
				),
				true
			);
			if ( ! is_wp_error( $new_branch_id ) ) {
				$branch_id = absint( $new_branch_id );
			}
		}
		if ( ! $branch_id ) {
			return 0;
		}

		update_post_meta( $branch_id, '_flavor_phone', $data['phone'] );
		update_post_meta( $branch_id, '_flavor_address', $data['address'] );
		update_post_meta( $branch_id, '_flavor_city', $data['city'] );
		update_post_meta( $branch_id, '_flavor_province', $data['city'] );
		update_post_meta( $branch_id, '_flavor_order_modes', $data['modes'] ? $data['modes'] : array( 'takeaway' ) );
		update_post_meta( $branch_id, '_flavor_timezone', wp_timezone_string() ? wp_timezone_string() : 'Asia/Tehran' );
		update_post_meta( $branch_id, '_flavor_is_default', '1' );

		if ( class_exists( '\FlavorCore\Support\Settings' ) ) {
			\FlavorCore\Support\Settings::update( array( 'default_branch_id' => $branch_id ) );
		}
		return (int) $branch_id;
	}

	/**
	 * Store the human-readable hours in the Core branch-hours table.
	 */
	private static function sync_branch_hours( int $branch_id, string $label ): void {
		if ( ! class_exists( '\FlavorCore\Database\Schema' ) ) {
			return;
		}
		global $wpdb;
		$hours = self::parse_hours( $label );
		$table = \FlavorCore\Database\Schema::table( 'flavor_branch_hours' );
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return;
		}
		$wpdb->delete( $table, array( 'branch_id' => $branch_id, 'mode' => 'all' ), array( '%d', '%s' ) );
		for ( $day = 0; $day < 7; $day++ ) {
			$wpdb->insert(
				$table,
				array( 'branch_id' => $branch_id, 'day_of_week' => $day, 'mode' => 'all', 'open_time' => $hours['open'], 'close_time' => $hours['close'], 'is_closed' => 0 ),
				array( '%d', '%d', '%s', '%s', '%s', '%d' )
			);
		}
	}

	/**
	 * Parse common Persian/Latin opening-hour labels.
	 *
	 * @return array{open:string,close:string}
	 */
	private static function parse_hours( string $label ): array {
		$label = strtr( $label, array( '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9', '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9' ) );
		$pattern = '/(\d{1,2})(?:\s*[:٫.]\s*(\d{1,2}))?\s*(?:الی|تا|[-–—])\s*(\d{1,2})(?:\s*[:٫.]\s*(\d{1,2}))?/u';
		if ( preg_match( $pattern, $label, $match ) ) {
			$open_hour = min( 23, absint( $match[1] ) );
			$open_minute = isset( $match[2] ) && '' !== $match[2] ? min( 59, absint( $match[2] ) ) : 0;
			$close_hour = min( 23, absint( $match[3] ) );
			$close_minute = isset( $match[4] ) && '' !== $match[4] ? min( 59, absint( $match[4] ) ) : 0;
			return array( 'open' => sprintf( '%02d:%02d:00', $open_hour, $open_minute ), 'close' => sprintf( '%02d:%02d:00', $close_hour, $close_minute ) );
		}
		return array( 'open' => '11:30:00', 'close' => '23:45:00' );
	}

	/**
	 * Create or update the one product owned by the onboarding wizard.
	 */
	private static function ensure_first_product( array $data ): void {
		if ( empty( $data['first_item_name'] ) || ! class_exists( '\WC_Product_Simple' ) ) {
			return;
		}
		$ids = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_flavor_onboarding_product', 'meta_value' => '1' ) );
		$product = ! empty( $ids ) ? wc_get_product( (int) $ids[0] ) : new \WC_Product_Simple();
		if ( ! $product ) {
			return;
		}
		$product->set_name( $data['first_item_name'] );
		$price = (string) absint( $data['first_item_price'] );
		$product->set_regular_price( $price );
		$product->set_price( $price );
		$product->set_status( 'publish' );
		$product->set_stock_status( 'instock' );
		$product_id = $product->save();
		if ( $product_id ) {
			update_post_meta( $product_id, '_flavor_onboarding_product', '1' );
			if ( ! empty( $data['first_item_cat'] ) ) {
				wp_set_object_terms( $product_id, $data['first_item_cat'], 'product_cat', true );
			}
		}
	}

	/**
	 * Create a safe primary menu only when no primary location is configured.
	 */
	private static function ensure_primary_menu(): void {
		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		if ( ! empty( $locations['primary'] ) ) {
			return;
		}
		$menu = wp_get_nav_menu_object( 'منوی رستوران' );
		$menu_id = $menu ? (int) $menu->term_id : wp_create_nav_menu( 'منوی رستوران' );
		if ( is_wp_error( $menu_id ) || ! $menu_id ) {
			return;
		}
		$existing_items = wp_get_nav_menu_items( $menu_id );
		$existing_pages = array();
		foreach ( is_array( $existing_items ) ? $existing_items : array() as $item ) {
			if ( 'post_type' === $item->type && 'page' === $item->object ) {
				$existing_pages[] = (int) $item->object_id;
			}
		}
		foreach ( array( 'home', 'menu', 'reservation', 'branches', 'contact' ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && ! in_array( (int) $page->ID, $existing_pages, true ) ) {
				wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => $page->post_title, 'menu-item-object' => 'page', 'menu-item-object-id' => $page->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
			}
		}
		$locations['primary'] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Create missing shared pages without overwriting existing content or builders.
	 */
	public static function ensure_pages(): void {
		$pages = array(
			'home' => array(
				'title'    => __( 'خانه', 'flavor' ),
				'slug'     => 'home',
				'template' => 'front-page.php',
			),
			'menu' => array(
				'title'    => __( 'منوی غذا', 'flavor' ),
				'slug'     => 'menu',
				'template' => 'page-templates/template-menu.php',
			),
			'reservation' => array(
				'title'    => __( 'رزرو میز', 'flavor' ),
				'slug'     => 'reservation',
				'template' => 'page-templates/template-reservation.php',
			),
			'branches' => array(
				'title'    => __( 'شعبه‌ها', 'flavor' ),
				'slug'     => 'branches',
				'template' => 'page-templates/template-branches.php',
			),
			'about' => array( 'title' => __( 'درباره ما', 'flavor' ), 'slug' => 'about', 'template' => 'page-templates/template-about.php' ),
			'contact' => array( 'title' => __( 'تماس', 'flavor' ), 'slug' => 'contact', 'template' => 'page-templates/template-contact.php' ),
			'tracking' => array( 'title' => __( 'پیگیری سفارش', 'flavor' ), 'slug' => 'tracking', 'template' => 'page-templates/template-order-tracking.php' ),
		);

		foreach ( $pages as $p ) {
			$existing = get_page_by_path( $p['slug'] );
			if ( ! $existing ) {
				$pid = wp_insert_post(
					array(
						'post_title'  => $p['title'],
						'post_name'   => $p['slug'],
						'post_type'   => 'page',
						'post_status' => 'publish',
					)
				);
				if ( $pid && ! is_wp_error( $pid ) ) {
					$existing = get_post( $pid );
					if ( 'home' !== $p['slug'] ) {
						update_post_meta( $pid, '_wp_page_template', $p['template'] );
					}
				}
			}
			if ( 'home' === $p['slug'] && $existing && 'publish' === $existing->post_status ) {
				$front_id = absint( get_option( 'page_on_front', 0 ) );
				if ( ! $front_id || 'page' !== get_option( 'show_on_front' ) || 'publish' !== get_post_status( $front_id ) ) {
					update_option( 'show_on_front', 'page' );
					update_option( 'page_on_front', (int) $existing->ID );
				}
			}
		}
	}
}
