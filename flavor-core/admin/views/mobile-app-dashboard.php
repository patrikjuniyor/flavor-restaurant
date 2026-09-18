<?php
/**
 * Admin View: Mobile App White-label Dashboard & CI/CD Orchestration.
 *
 * @package FlavorCore
 */

use FlavorCore\Mobile\BuildManager;
use FlavorCore\Mobile\MobileConfigManager;

defined( 'ABSPATH' ) || exit;

$config    = MobileConfigManager::get_all();
$readiness = MobileConfigManager::get_readiness_status();
$history   = BuildManager::get_history( 15 );
$branches  = get_posts( array( 'post_type' => 'flavor_branch', 'numberposts' => -1, 'post_status' => 'publish' ) );
$nonce     = wp_create_nonce( 'flavor_mobile_admin_nonce' );
?>

<div class="wrap flavor-admin flavor-mobile-app-wrap" dir="rtl">
	<div class="flavor-mobile-header">
		<div class="flavor-mobile-header__title">
			<h1><?php esc_html_e( 'مدیریت و بیلد اپلیکیشن موبایل (White-Label)', 'flavor-core' ); ?></h1>
			<p class="flavor-mobile-header__subtitle">
				<?php esc_html_e( 'سفارشی‌سازی برند رستوران، اتصال به CI/CD و ساخت خودکار خروجی‌های Android (APK/AAB) و iOS (IPA) بدون تغییر کدهای اصلی.', 'flavor-core' ); ?>
			</p>
		</div>
		<div class="flavor-mobile-header__actions">
			<a href="#build-trigger" class="button button-primary button-hero">
				<span class="dashicons dashicons-update" style="vertical-align: middle; margin-left: 4px;"></span>
				<?php esc_html_e( 'درخواست بیلد جدید', 'flavor-core' ); ?>
			</a>
		</div>
	</div>

	<!-- Status & Readiness Cards -->
	<div class="flavor-mobile-cards-grid">
		<div class="flavor-card flavor-card--status">
			<div class="flavor-card__icon"><span class="dashicons dashicons-smartphone"></span></div>
			<div class="flavor-card__content">
				<div class="flavor-card__label"><?php esc_html_e( 'وضعیت آماده‌سازی برند', 'flavor-core' ); ?></div>
				<div class="flavor-card__value">
					<span class="flavor-badge <?php echo $readiness['is_ready'] ? 'flavor-badge--success' : 'flavor-badge--warning'; ?>">
						<?php echo $readiness['is_ready'] ? esc_html__( 'آماده بیلد', 'flavor-core' ) : esc_html__( 'نیازمند تکمیل اطلاعات', 'flavor-core' ); ?>
					</span>
				</div>
				<div class="flavor-card__desc"><?php echo esc_html( sprintf( __( 'امتیاز تکمیل: %d%%', 'flavor-core' ), $readiness['readiness_score'] ) ); ?></div>
			</div>
		</div>

		<div class="flavor-card">
			<div class="flavor-card__icon"><span class="dashicons dashicons-admin-generic"></span></div>
			<div class="flavor-card__content">
				<div class="flavor-card__label"><?php esc_html_e( 'نسخه جاری اپلیکیشن', 'flavor-core' ); ?></div>
				<div class="flavor-card__value"><?php echo esc_html( $config['version_name'] . ' (' . $config['version_code'] . ')' ); ?></div>
				<div class="flavor-card__desc"><?php echo esc_html( sprintf( __( 'شناسه پکیج: %s', 'flavor-core' ), $config['package_name'] ) ); ?></div>
			</div>
		</div>

		<div class="flavor-card">
			<div class="flavor-card__icon"><span class="dashicons dashicons-rest-api"></span></div>
			<div class="flavor-card__content">
				<div class="flavor-card__label"><?php esc_html_e( 'وضعیت API و اتصال', 'flavor-core' ); ?></div>
				<div class="flavor-card__value">
					<span class="flavor-badge flavor-badge--success"><?php esc_html_e( 'REST API فعال v1', 'flavor-core' ); ?></span>
				</div>
				<div class="flavor-card__desc"><?php echo esc_html( home_url( '/wp-json/flavor/v1/' ) ); ?></div>
			</div>
		</div>

		<div class="flavor-card">
			<div class="flavor-card__icon"><span class="dashicons dashicons-bell"></span></div>
			<div class="flavor-card__content">
				<div class="flavor-card__label"><?php esc_html_e( 'سرویس پوش نوتیفیکیشن', 'flavor-core' ); ?></div>
				<div class="flavor-card__value">
					<?php if ( ! empty( $config['push_onesignal_app_id'] ) || ! empty( $config['push_fcm_sender_id'] ) ) : ?>
						<span class="flavor-badge flavor-badge--success"><?php esc_html_e( 'پیکربندی شده', 'flavor-core' ); ?></span>
					<?php else : ?>
						<span class="flavor-badge flavor-badge--neutral"><?php esc_html_e( 'غیرفعال', 'flavor-core' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="flavor-card__desc"><?php echo esc_html( ucfirst( $config['push_provider'] ?? 'onesignal' ) ); ?></div>
			</div>
		</div>
	</div>

	<!-- Readiness Checklist -->
	<div class="flavor-section-box">
		<h2 class="flavor-section-box__title"><span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'چک‌لیست الزامات انتشار', 'flavor-core' ); ?></h2>
		<div class="flavor-checklist-grid">
			<?php foreach ( $readiness['checklist'] as $key => $item ) : ?>
				<div class="flavor-checklist-item <?php echo $item['passed'] ? 'is-passed' : 'is-missing'; ?>">
					<span class="dashicons <?php echo $item['passed'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
					<div class="flavor-checklist-item__text">
						<strong><?php echo esc_html( $item['label'] ); ?></strong>
						<span><?php echo $item['passed'] ? esc_html__( 'تکمیل شده', 'flavor-core' ) : esc_html__( 'ضروری جهت ثبت', 'flavor-core' ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Tabs Navigation -->
	<h2 class="nav-tab-wrapper">
		<a href="#tab-branding" class="nav-tab nav-tab-active" data-tab="branding"><?php esc_html_e( 'تنظیمات برند و هویت بصری', 'flavor-core' ); ?></a>
		<a href="#tab-build" class="nav-tab" data-tab="build"><?php esc_html_e( 'سفارش بیلد و CI/CD', 'flavor-core' ); ?></a>
		<a href="#tab-history" class="nav-tab" data-tab="history"><?php esc_html_e( 'تاریخچه بیلدها و دانلود', 'flavor-core' ); ?></a>
		<a href="#tab-security" class="nav-tab" data-tab="security"><?php esc_html_e( 'امنیت و کلیدها', 'flavor-core' ); ?></a>
	</h2>

	<!-- Tab 1: Branding Configuration -->
	<div class="flavor-tab-content flavor-tab-active" id="tab-branding">
		<form method="post" action="" id="flavor-mobile-config-form">
			<?php wp_nonce_field( 'flavor_mobile_save_config', 'flavor_mobile_config_nonce' ); ?>
			<input type="hidden" name="flavor_action" value="save_mobile_config" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="app_name"><?php esc_html_e( 'نام اپلیکیشن (App Name)', 'flavor-core' ); ?></label></th>
						<td>
							<input type="text" id="app_name" name="app_name" value="<?php echo esc_attr( $config['app_name'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'نامی که زیر آیکون برنامه در گوشی مشتری و در فروشگاه‌ها نمایش داده می‌شود.', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="restaurant_id"><?php esc_html_e( 'شناسه مستاجر / Tenant ID', 'flavor-core' ); ?></label></th>
						<td>
							<input type="text" id="restaurant_id" name="restaurant_id" value="<?php echo esc_attr( $config['restaurant_id'] ); ?>" class="regular-text" required />
							<p class="description"><?php esc_html_e( 'شناسه یکتای رستوران در ساختار چندمستاجره (Multi-Tenant). مثال: shandiz_mashhad', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="package_name"><?php esc_html_e( 'شناسه پکیج اندروید (Package Name)', 'flavor-core' ); ?></label></th>
						<td>
							<input type="text" id="package_name" name="package_name" value="<?php echo esc_attr( $config['package_name'] ); ?>" class="regular-text" dir="ltr" required />
							<p class="description"><?php esc_html_e( 'فرمت استاندارد جاوا. مثال: com.flavor.restaurant.shandiz', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bundle_id"><?php esc_html_e( 'شناسه باندل iOS (Bundle Identifier)', 'flavor-core' ); ?></label></th>
						<td>
							<input type="text" id="bundle_id" name="bundle_id" value="<?php echo esc_attr( $config['bundle_id'] ); ?>" class="regular-text" dir="ltr" required />
							<p class="description"><?php esc_html_e( 'شناسه پکیج در App Store Connect. مثال: com.flavor.restaurant.shandiz', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="app_icon_url"><?php esc_html_e( 'آیکون برنامه (App Icon 1024x1024)', 'flavor-core' ); ?></label></th>
						<td>
							<input type="url" id="app_icon_url" name="app_icon_url" value="<?php echo esc_attr( $config['app_icon_url'] ); ?>" class="regular-text" dir="ltr" />
							<button type="button" class="button flavor-upload-btn" data-target="#app_icon_url"><?php esc_html_e( 'انتخاب از رسانه', 'flavor-core' ); ?></button>
							<p class="description"><?php esc_html_e( 'تصویر مربعی بدون حاشیه با فرمت PNG بدون ترنسپرنسی (1024x1024 پیکسل).', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="splash_image_url"><?php esc_html_e( 'تصویر صفحه خوش‌آمدگویی (Splash)', 'flavor-core' ); ?></label></th>
						<td>
							<input type="url" id="splash_image_url" name="splash_image_url" value="<?php echo esc_attr( $config['splash_image_url'] ); ?>" class="regular-text" dir="ltr" />
							<button type="button" class="button flavor-upload-btn" data-target="#splash_image_url"><?php esc_html_e( 'انتخاب از رسانه', 'flavor-core' ); ?></button>
							<p class="description"><?php esc_html_e( 'تصویر لوگو یا گرافیک اختصاصی صفحه اسپلش (حداقل 1080x1920).', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label><?php esc_html_e( 'رنگ‌بندی برند (Palette)', 'flavor-core' ); ?></label></th>
						<td>
							<div class="flavor-color-pickers">
								<div class="flavor-color-field">
									<label for="primary_color"><?php esc_html_e( 'رنگ اصلی (Primary)', 'flavor-core' ); ?></label>
									<input type="text" id="primary_color" name="primary_color" value="<?php echo esc_attr( $config['primary_color'] ); ?>" class="flavor-color-input" />
								</div>
								<div class="flavor-color-field">
									<label for="secondary_color"><?php esc_html_e( 'رنگ فرعی (Secondary)', 'flavor-core' ); ?></label>
									<input type="text" id="secondary_color" name="secondary_color" value="<?php echo esc_attr( $config['secondary_color'] ); ?>" class="flavor-color-input" />
								</div>
								<div class="flavor-color-field">
									<label for="accent_color"><?php esc_html_e( 'رنگ مکمل (Accent)', 'flavor-core' ); ?></label>
									<input type="text" id="accent_color" name="accent_color" value="<?php echo esc_attr( $config['accent_color'] ); ?>" class="flavor-color-input" />
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="default_branch_id"><?php esc_html_e( 'شعبه پیش‌فرض', 'flavor-core' ); ?></label></th>
						<td>
							<select id="default_branch_id" name="default_branch_id">
								<option value="0"><?php esc_html_e( 'انتخاب خودکار یا انتخاب توسط کاربر', 'flavor-core' ); ?></option>
								<?php foreach ( $branches as $b ) : ?>
									<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( (int) $config['default_branch_id'], $b->ID ); ?>>
										<?php echo esc_html( $b->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="support_phone"><?php esc_html_e( 'شماره تماس پشتیبانی', 'flavor-core' ); ?></label></th>
						<td>
							<input type="text" id="support_phone" name="support_phone" value="<?php echo esc_attr( $config['support_phone'] ); ?>" class="regular-text" dir="ltr" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="support_email"><?php esc_html_e( 'ایمیل پشتیبانی', 'flavor-core' ); ?></label></th>
						<td>
							<input type="email" id="support_email" name="support_email" value="<?php echo esc_attr( $config['support_email'] ); ?>" class="regular-text" dir="ltr" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="privacy_policy_url"><?php esc_html_e( 'آدرس صفحه حریم خصوصی', 'flavor-core' ); ?></label></th>
						<td>
							<input type="url" id="privacy_policy_url" name="privacy_policy_url" value="<?php echo esc_attr( $config['privacy_policy_url'] ); ?>" class="regular-text" dir="ltr" />
							<p class="description"><?php esc_html_e( 'برای انتشار در گوگل پلی و کافه بازار و مایکت الزامی است.', 'flavor-core' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="terms_url"><?php esc_html_e( 'آدرس شرایط و قوانین', 'flavor-core' ); ?></label></th>
						<td>
							<input type="url" id="terms_url" name="terms_url" value="<?php echo esc_attr( $config['terms_url'] ); ?>" class="regular-text" dir="ltr" />
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button( __( 'ذخیره تنظیمات برندینگ', 'flavor-core' ) ); ?>
		</form>
	</div>

	<!-- Tab 2: Build Trigger & CI/CD -->
	<div class="flavor-tab-content" id="tab-build" style="display:none;">
		<div class="flavor-build-trigger-box" id="build-trigger">
			<h3><?php esc_html_e( 'سفارش بیلد جدید از راه دور (Cloud CI/CD Trigger)', 'flavor-core' ); ?></h3>
			<p><?php esc_html_e( 'با ثبت این درخواست، تنظیمات و لوگوهای برندینگ شما از طریق وب‌هوک رمزنگاری شده HMAC به خط لوله CI/CD (GitHub Actions / Fastlane) ارسال شده و فرآیند کامپایل آغاز می‌شود.', 'flavor-core' ); ?></p>

			<form method="post" action="" id="flavor-trigger-build-form">
				<?php wp_nonce_field( 'flavor_mobile_trigger_build', 'flavor_mobile_build_nonce' ); ?>
				<input type="hidden" name="flavor_action" value="trigger_mobile_build" />

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="build_platform"><?php esc_html_e( 'پلتفرم هدف (Platform)', 'flavor-core' ); ?></label></th>
							<td>
								<select id="build_platform" name="build_platform" class="regular-text">
									<option value="all"><?php esc_html_e( 'تمامی پلتفرم‌ها (Android APK + AAB & iOS IPA)', 'flavor-core' ); ?></option>
									<option value="android"><?php esc_html_e( 'فقط اندروید (Android APK & AAB Bundle)', 'flavor-core' ); ?></option>
									<option value="ios"><?php esc_html_e( 'فقط آی‌او‌اس (iOS IPA / TestFlight)', 'flavor-core' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="build_environment"><?php esc_html_e( 'محیط بیلد (Environment)', 'flavor-core' ); ?></label></th>
							<td>
								<select id="build_environment" name="build_environment" class="regular-text">
									<option value="prod"><?php esc_html_e( 'تولید نهایی (Production / Release Store)', 'flavor-core' ); ?></option>
									<option value="staging"><?php esc_html_e( 'مرحله‌ای (Staging / Internal Test)', 'flavor-core' ); ?></option>
									<option value="dev"><?php esc_html_e( 'توسعه (Development / Debug)', 'flavor-core' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="version_name"><?php esc_html_e( 'شماره نسخه نمایشی (Version Name)', 'flavor-core' ); ?></label></th>
							<td>
								<input type="text" id="version_name" name="version_name" value="<?php echo esc_attr( $config['version_name'] ); ?>" class="small-text" dir="ltr" />
								<p class="description"><?php esc_html_e( 'مثال: 1.0.0 یا 1.1.0', 'flavor-core' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="version_code"><?php esc_html_e( 'کد نسخه ترتیبی (Build / Version Code)', 'flavor-core' ); ?></label></th>
							<td>
								<input type="number" id="version_code" name="version_code" value="<?php echo esc_attr( (string) $config['version_code'] ); ?>" class="small-text" dir="ltr" min="1" />
								<p class="description"><?php esc_html_e( 'برای هر بیلد جدید جهت انتشار در استورها باید افزایش یابد.', 'flavor-core' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large" <?php echo ! $readiness['is_ready'] ? 'disabled' : ''; ?>>
						<span class="dashicons dashicons-cloud-upload" style="vertical-align: middle;"></span>
						<?php esc_html_e( 'ارسال درخواست بیلد به سرویس CI/CD', 'flavor-core' ); ?>
					</button>
					<?php if ( ! $readiness['is_ready'] ) : ?>
						<span class="description" style="color: #d63638; margin-right: 12px; vertical-align: middle;">
							<?php esc_html_e( 'لطفاً ابتدا موارد الزامی در تب برندینگ را تکمیل نمایید.', 'flavor-core' ); ?>
						</span>
					<?php endif; ?>
				</p>
			</form>
		</div>
	</div>

	<!-- Tab 3: Build History & Download Artifacts -->
	<div class="flavor-tab-content" id="tab-history" style="display:none;">
		<h3><?php esc_html_e( 'تاریخچه بیلدها و بسته‌های خروجی', 'flavor-core' ); ?></h3>

		<?php if ( empty( $history ) ) : ?>
			<div class="flavor-empty-state">
				<span class="dashicons dashicons-cloud"></span>
				<p><?php esc_html_e( 'هنوز هیچ بیلد فعالی ثبت نشده است. از تب «سفارش بیلد» اولین پکیج را درخواست دهید.', 'flavor-core' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 130px;"><?php esc_html_e( 'شناسه بیلد', 'flavor-core' ); ?></th>
						<th style="width: 90px;"><?php esc_html_e( 'پلتفرم', 'flavor-core' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'نسخه', 'flavor-core' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'محیط', 'flavor-core' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'وضعیت', 'flavor-core' ); ?></th>
						<th><?php esc_html_e( 'فایل‌های خروجی (Artifacts)', 'flavor-core' ); ?></th>
						<th style="width: 150px;"><?php esc_html_e( 'تاریخ و زمان', 'flavor-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $history as $row ) : ?>
						<tr>
							<td><code><?php echo esc_html( substr( $row['build_uuid'], 0, 8 ) ); ?>...</code></td>
							<td><strong><?php echo esc_html( strtoupper( $row['platform'] ) ); ?></strong></td>
							<td><?php echo esc_html( $row['version_name'] . '+' . $row['version_code'] ); ?></td>
							<td><span class="flavor-badge flavor-badge--env-<?php echo esc_attr( $row['environment'] ); ?>"><?php echo esc_html( strtoupper( $row['environment'] ) ); ?></span></td>
							<td>
								<?php
								$status_cls = match ( $row['status'] ) {
									'success'  => 'flavor-badge--success',
									'failed'   => 'flavor-badge--error',
									'building' => 'flavor-badge--building',
									'pending'  => 'flavor-badge--warning',
									default    => 'flavor-badge--neutral',
								};
								$status_lbl = match ( $row['status'] ) {
									'success'  => __( 'موفق', 'flavor-core' ),
									'failed'   => __( 'ناموفق', 'flavor-core' ),
									'building' => __( 'در حال کامپایل', 'flavor-core' ),
									'pending'  => __( 'در صف انتظار', 'flavor-core' ),
									'canceled' => __( 'لغو شده', 'flavor-core' ),
									default    => $row['status'],
								};
								?>
								<span class="flavor-badge <?php echo esc_attr( $status_cls ); ?>"><?php echo esc_html( $status_lbl ); ?></span>
							</td>
							<td>
								<?php
								$artifacts = is_array( $row['artifacts'] ) ? $row['artifacts'] : json_decode( (string) $row['artifacts'], true );
								if ( ! empty( $artifacts ) && is_array( $artifacts ) ) :
									foreach ( $artifacts as $art ) :
										?>
										<div class="flavor-artifact-pill">
											<a href="<?php echo esc_url( $art['download_url'] ?? '#' ); ?>" target="_blank" class="button button-small">
												<span class="dashicons dashicons-download"></span>
												<?php echo esc_html( ( $art['type'] ?? 'package' ) . ' (' . ( $art['file_size_human'] ?? '' ) . ')' ); ?>
											</a>
											<?php if ( ! empty( $art['checksum_sha256'] ) ) : ?>
												<span class="flavor-checksum" title="<?php echo esc_attr( 'SHA256: ' . $art['checksum_sha256'] ); ?>">
													SHA256: <?php echo esc_html( substr( $art['checksum_sha256'], 0, 10 ) . '...' ); ?>
												</span>
											<?php endif; ?>
										</div>
										<?php
									endforeach;
								else :
									echo '<span class="description">' . esc_html__( 'فایلی تولید نشده', 'flavor-core' ) . '</span>';
								endif;
								?>
							</td>
							<td><?php echo esc_html( $row['created_at'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Tab 4: Security Architecture & Secrets Notice -->
	<div class="flavor-tab-content" id="tab-security" style="display:none;">
		<div class="flavor-security-card">
			<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'معماری امنیتی و مدیریت گواهی‌های امضا (Zero-Trust Secret Handling)', 'flavor-core' ); ?></h3>
			<p>
				<?php esc_html_e( 'در طراحی امنیتی سامانه وایت‌لیبل Flavor، هیچ‌گونه کلید حساس امضای اندروید (Keystore/JKS)، گواهی‌نامه اپل (iOS Distribution Certificate / Provisioning Profile) یا توکن‌های محرمانه استورها در دیتابیس وردپرس یا کدهای فرانت‌اند ذخیره نمی‌گردد.', 'flavor-core' ); ?>
			</p>
			<ul class="flavor-security-list">
				<li>
					<strong><?php esc_html_e( '۱. جداسازی هسته اجرا و کامپایل:', 'flavor-core' ); ?></strong>
					<?php esc_html_e( 'وردپرس صرفاً پیکربندی عمومی و هویت برند را ارائه داده و فرآیند سنگین کامپایل در گیت‌هاب اکشنز یا سرور CI ایزوله انجام می‌شود.', 'flavor-core' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '۲. امضای امن HMAC-SHA256:', 'flavor-core' ); ?></strong>
					<?php esc_html_e( 'تمامی وب‌هوک‌های ارسالی از CI به وردپرس با هدر اختصاصی X-Flavor-Signature و کلید مخفی اعتبارسنجی می‌شوند.', 'flavor-core' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '۳. محیط امن GitHub Actions Secrets / Fastlane Match:', 'flavor-core' ); ?></strong>
					<?php esc_html_e( 'کلیدهای امضای Release در محیط متغیرهای رمزگذاری شده سرور CI بارگذاری و پس از اتمام کامپایل نابود می‌شوند.', 'flavor-core' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '۴. یکپارچگی فایل‌های خروجی:', 'flavor-core' ); ?></strong>
					<?php esc_html_e( 'تمامی پکیج‌های تولیدی با متادیتای هش SHA256 جهت جلوگیری از دستکاری در سیستم ثبت می‌گردند.', 'flavor-core' ); ?>
				</li>
			</ul>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab switching logic
	const tabs = document.querySelectorAll('.nav-tab-wrapper .nav-tab');
	const contents = document.querySelectorAll('.flavor-tab-content');

	tabs.forEach(tab => {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			tabs.forEach(t => t.classList.remove('nav-tab-active'));
			contents.forEach(c => c.style.display = 'none');

			this.classList.add('nav-tab-active');
			const targetId = 'tab-' + this.getAttribute('data-tab');
			const target = document.getElementById(targetId);
			if (target) {
				target.style.display = 'block';
			}
		});
	});

	// WP Media Uploader trigger
	document.querySelectorAll('.flavor-upload-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			const targetInput = document.querySelector(this.getAttribute('data-target'));
			if (typeof wp !== 'undefined' && wp.media) {
				const frame = wp.media({
					title: '<?php echo esc_js( __( 'انتخاب تصویر اپلیکیشن', 'flavor-core' ) ); ?>',
					multiple: false,
					library: { type: 'image' }
				});
				frame.on('select', function() {
					const attachment = frame.state().get('selection').first().toJSON();
					if (targetInput) {
						targetInput.value = attachment.url;
					}
				});
				frame.open();
			}
		});
	});
});
</script>

<style>
.flavor-mobile-app-wrap {
	margin-top: 20px;
}
.flavor-mobile-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: #fff;
	padding: 24px 30px;
	border-radius: 8px;
	border: 1px solid #dcdcde;
	margin-bottom: 24px;
}
.flavor-mobile-header__title h1 {
	margin: 0 0 6px 0;
	font-size: 22px;
}
.flavor-mobile-header__subtitle {
	margin: 0;
	color: #646970;
	font-size: 14px;
}
.flavor-mobile-cards-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 16px;
	margin-bottom: 24px;
}
.flavor-card {
	background: #fff;
	padding: 18px 20px;
	border-radius: 8px;
	border: 1px solid #dcdcde;
	display: flex;
	align-items: center;
	gap: 16px;
}
.flavor-card__icon span {
	font-size: 32px;
	width: 32px;
	height: 32px;
	color: #2271b1;
}
.flavor-card__label {
	font-size: 13px;
	color: #646970;
	margin-bottom: 4px;
}
.flavor-card__value {
	font-size: 16px;
	font-weight: 700;
	color: #1d2327;
}
.flavor-card__desc {
	font-size: 12px;
	color: #8c8f94;
	margin-top: 4px;
}
.flavor-badge {
	display: inline-block;
	padding: 4px 10px;
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
}
.flavor-badge--success { background: #e7f6ed; color: #0a7b3e; }
.flavor-badge--warning { background: #fef7e0; color: #b26b00; }
.flavor-badge--error { background: #fce8e6; color: #c5221f; }
.flavor-badge--building { background: #e8f0fe; color: #1a73e8; }
.flavor-badge--neutral { background: #f0f0f1; color: #50575e; }
.flavor-section-box {
	background: #fff;
	padding: 20px;
	border-radius: 8px;
	border: 1px solid #dcdcde;
	margin-bottom: 24px;
}
.flavor-section-box__title {
	margin-top: 0;
	font-size: 16px;
	display: flex;
	align-items: center;
	gap: 8px;
}
.flavor-checklist-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
	gap: 12px;
	margin-top: 16px;
}
.flavor-checklist-item {
	display: flex;
	align-items: center;
	gap: 10px;
	padding: 10px 14px;
	border-radius: 6px;
	border: 1px solid #dcdcde;
	background: #f6f7f7;
}
.flavor-checklist-item.is-passed {
	border-color: #0a7b3e;
	background: #f2f9f5;
}
.flavor-checklist-item.is-passed span.dashicons {
	color: #0a7b3e;
}
.flavor-checklist-item.is-missing span.dashicons {
	color: #d63638;
}
.flavor-checklist-item__text strong {
	display: block;
	font-size: 13px;
}
.flavor-checklist-item__text span {
	font-size: 11px;
	color: #646970;
}
.flavor-tab-content {
	background: #fff;
	padding: 24px;
	border: 1px solid #c3c4c7;
	border-top: none;
}
.flavor-color-pickers {
	display: flex;
	gap: 20px;
}
.flavor-color-field label {
	display: block;
	font-size: 12px;
	margin-bottom: 4px;
	color: #50575e;
}
.flavor-color-input {
	width: 120px;
	direction: ltr;
	text-align: center;
}
.flavor-artifact-pill {
	margin-bottom: 6px;
}
.flavor-checksum {
	font-size: 11px;
	color: #8c8f94;
	margin-right: 6px;
	font-family: monospace;
}
.flavor-security-card {
	padding: 10px 0;
}
.flavor-security-list {
	margin-top: 16px;
	line-height: 1.8;
}
.flavor-security-list li {
	margin-bottom: 12px;
}
</style>
