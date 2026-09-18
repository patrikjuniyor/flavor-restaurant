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
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 5 );
		add_action( 'admin_post_flavor_save_wizard', array( self::class, 'handle_submit' ) );
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
		$skins   = Design::skins();
		$current = Design::current_skin();
		$name    = get_bloginfo( 'name' );
		$tagline = get_bloginfo( 'description' );
		$phone   = get_theme_mod( 'flavor_phone', '02188001234' );
		$address = get_theme_mod( 'flavor_address', 'تهران، خیابان ولیعصر' );
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
				<div class="flavor-wizard-header">
					<h1><?php esc_html_e( 'راه‌اندازی سریع رستوران در ۱۰ گام ساده', 'flavor' ); ?></h1>
					<p><?php esc_html_e( 'اطلاعات اولیه رستوران را تکمیل کنید تا سایت و سیستم سفارش آنلاین شما آماده بهره‌برداری شود.', 'flavor' ); ?></p>
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
							<input type="hidden" name="custom_logo_id" id="flavor_logo_id" />
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
							<input type="text" name="hero_image_url" id="flavor_hero_url" placeholder="https://..." />
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
								<input type="color" name="primary_color" value="#8a5a2b" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px;" />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'رنگ ثانویه / تاکیدی', 'flavor' ); ?></label>
								<input type="color" name="secondary_color" value="#5c6b3a" style="width: 100%; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px;" />
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
							<input type="text" name="opening_hours" value="همه روزه از ساعت ۱۱:۳۰ الی ۲۳:۴۵" />
						</div>
						<div class="flavor-form-row">
							<label style="margin-bottom: 10px;"><?php esc_html_e( 'حالت‌های فعال سفارش‌گیری', 'flavor' ); ?></label>
							<label style="font-weight: normal; margin-left: 20px;"><input type="checkbox" name="mode_dine_in" value="1" checked /> <?php esc_html_e( 'سفارش سر میز سالن (QR)', 'flavor' ); ?></label>
							<label style="font-weight: normal; margin-left: 20px;"><input type="checkbox" name="mode_takeaway" value="1" checked /> <?php esc_html_e( 'سفارش بیرون‌بر', 'flavor' ); ?></label>
							<label style="font-weight: normal;"><input type="checkbox" name="mode_delivery" value="1" checked /> <?php esc_html_e( 'ارسال سریع با پیک', 'flavor' ); ?></label>
						</div>
					</div>

					<!-- Step 7: Address & Delivery Area -->
					<div class="flavor-step-pane" data-pane="7">
						<h3><?php esc_html_e( 'گام ۷: نشانی و موقعیت جغرافیایی', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'شهر', 'flavor' ); ?></label>
							<input type="text" name="city" value="تهران" />
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
								<input type="url" name="instagram" placeholder="https://instagram.com/..." />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'کانال تلگرام', 'flavor' ); ?></label>
								<input type="url" name="telegram" placeholder="https://t.me/..." />
							</div>
						</div>
					</div>

					<!-- Step 9: First Menu Item -->
					<div class="flavor-step-pane" data-pane="9">
						<h3><?php esc_html_e( 'گام ۹: افزودن اولین آیتم منوی غذا', 'flavor' ); ?></h3>
						<div class="flavor-form-row">
							<label><?php esc_html_e( 'نام غذا / نوشیدنی', 'flavor' ); ?></label>
							<input type="text" name="first_item_name" value="چلوکباب کوبیده مخصوص" />
						</div>
						<div class="flavor-form-row" style="display: flex; gap: 16px;">
							<div style="flex: 1;">
								<label><?php esc_html_e( 'قیمت (تومان)', 'flavor' ); ?></label>
								<input type="number" name="first_item_price" value="285000" />
							</div>
							<div style="flex: 1;">
								<label><?php esc_html_e( 'دسته‌بندی', 'flavor' ); ?></label>
								<input type="text" name="first_item_cat" value="کباب و خوراک" />
							</div>
						</div>
					</div>

					<!-- Step 10: Publish & Launch -->
					<div class="flavor-step-pane" data-pane="10">
						<div style="text-align: center; padding: 20px 0;">
							<div style="font-size: 54px; margin-bottom: 12px;">🎉</div>
							<h2><?php esc_html_e( 'همه چیز آماده راه‌اندازی است!', 'flavor' ); ?></h2>
							<p style="color: #64748b; max-width: 500px; margin: 0 auto 24px;">
								<?php esc_html_e( 'با کلیک روی دکمه زیر، تنظیمات ذخیره شده، صفحات اصلی و منوی رستوران ساخته می‌شوند و سایت زیبای شما آماده سفارش‌گیری می‌گردد.', 'flavor' ); ?>
							</p>
							<button type="submit" class="flavor-btn-step" style="font-size: 16px; padding: 14px 40px; margin: 0 auto;">
								<?php esc_html_e( '🚀 انتشار و مشاهده وبسایت رستوران', 'flavor' ); ?>
							</button>
						</div>
					</div>

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
					var currentStep = 1;
					var totalSteps = 10;
					var nextBtn = document.getElementById('flavor-next-btn');
					var prevBtn = document.getElementById('flavor-prev-btn');
					var navBar = document.getElementById('flavor-wizard-nav-bar');

					function goToStep(step) {
						if (step < 1 || step > totalSteps) return;
						currentStep = step;

						document.querySelectorAll('.flavor-step-node').forEach(function(node) {
							var s = parseInt(node.getAttribute('data-step'), 10);
							node.classList.toggle('is-active', s === currentStep);
							node.classList.toggle('is-done', s < currentStep);
						});

						document.querySelectorAll('.flavor-step-pane').forEach(function(pane) {
							var p = parseInt(pane.getAttribute('data-pane'), 10);
							pane.classList.toggle('is-active', p === currentStep);
						});

						prevBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
						navBar.style.display = currentStep === totalSteps ? 'none' : 'flex';
					}

					nextBtn.addEventListener('click', function() {
						goToStep(currentStep + 1);
					});

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
							});
							heroUploader.open();
						});
					}
				})();
			</script>
		</div>
		<?php
	}

	/**
	 * Handle form submission and save all configuration.
	 */
	public static function handle_submit(): void {
		check_admin_referer( 'flavor_save_wizard_action', 'flavor_wizard_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor' ) );
		}

		// Save site info
		if ( isset( $_POST['restaurant_name'] ) ) {
			update_option( 'blogname', sanitize_text_field( wp_unslash( $_POST['restaurant_name'] ) ) );
		}
		if ( isset( $_POST['restaurant_tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( wp_unslash( $_POST['restaurant_tagline'] ) ) );
		}
		if ( ! empty( $_POST['custom_logo_id'] ) ) {
			set_theme_mod( 'custom_logo', absint( $_POST['custom_logo_id'] ) );
		}

		// Save colors & skin
		if ( isset( $_POST['flavor_skin'] ) ) {
			set_theme_mod( 'flavor_skin', sanitize_key( wp_unslash( $_POST['flavor_skin'] ) ) );
		}
		if ( isset( $_POST['primary_color'] ) ) {
			set_theme_mod( 'flavor_primary', sanitize_hex_color( wp_unslash( $_POST['primary_color'] ) ) );
		}
		if ( isset( $_POST['secondary_color'] ) ) {
			set_theme_mod( 'flavor_secondary', sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ) ) );
		}

		// Save phone & address
		if ( isset( $_POST['phone'] ) ) {
			set_theme_mod( 'flavor_phone', sanitize_text_field( wp_unslash( $_POST['phone'] ) ) );
		}
		if ( isset( $_POST['address'] ) ) {
			set_theme_mod( 'flavor_address', sanitize_text_field( wp_unslash( $_POST['address'] ) ) );
		}

		// Save socials
		if ( isset( $_POST['instagram'] ) ) {
			set_theme_mod( 'flavor_social_instagram', esc_url_raw( wp_unslash( $_POST['instagram'] ) ) );
		}
		if ( isset( $_POST['telegram'] ) ) {
			set_theme_mod( 'flavor_social_telegram', esc_url_raw( wp_unslash( $_POST['telegram'] ) ) );
		}

		// Ensure primary pages exist
		self::ensure_pages();

		// Add first product if WooCommerce is active and item was provided
		if ( function_exists( 'wc_get_product' ) && ! empty( $_POST['first_item_name'] ) ) {
			$title = sanitize_text_field( wp_unslash( $_POST['first_item_name'] ) );
			$price = absint( $_POST['first_item_price'] ?? 0 );
			$cat   = sanitize_text_field( wp_unslash( $_POST['first_item_cat'] ?? 'اصلی' ) );

			$prod = new \WC_Product_Simple();
			$prod->set_name( $title );
			$prod->set_regular_price( (string) $price );
			$prod->set_status( 'publish' );
			$prod_id = $prod->save();

			if ( $prod_id && $cat ) {
				wp_set_object_terms( $prod_id, $cat, 'product_cat', true );
			}
		}

		update_option( 'flavor_setup_completed', true );

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Create core pages (Home, Menu, Reservation, Branches) if not existing.
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
					if ( 'home' === $p['slug'] ) {
						update_option( 'show_on_front', 'page' );
						update_option( 'page_on_front', $pid );
					} else {
						update_post_meta( $pid, '_wp_page_template', $p['template'] );
					}
				}
			}
		}
	}
}
