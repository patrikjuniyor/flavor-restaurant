<?php
/**
 * Admin View: Executive Analytics Dashboard & Restaurant Operations KPIs.
 *
 * @package FlavorCore
 */

use FlavorCore\Analytics\AnalyticsManager;
use FlavorCore\Support\Roles;
use FlavorCore\WooCommerce\Currency;

defined( 'ABSPATH' ) || exit;

$current_user_id = get_current_user_id();
$branch_id       = isset( $_GET['branch_id'] ) ? absint( $_GET['branch_id'] ) : null;
$start_date      = isset( $_GET['start_date'] ) ? sanitize_text_field( (string) $_GET['start_date'] ) : gmdate( 'Y-m-d', strtotime( '-30 days' ) );
$end_date        = isset( $_GET['end_date'] ) ? sanitize_text_field( (string) $_GET['end_date'] ) : gmdate( 'Y-m-d' );

$branches = get_posts( array( 'post_type' => 'flavor_branch', 'numberposts' => -1, 'post_status' => 'publish' ) );
$overview = AnalyticsManager::get_overview( $branch_id, $start_date, $end_date );
$peak_hrs = AnalyticsManager::get_peak_hours( $branch_id, $start_date, $end_date );
$popular  = AnalyticsManager::get_popular_dishes( $branch_id, 10, $start_date, $end_date );
$branches_summary = AnalyticsManager::get_branches_summary( $start_date, $end_date );
?>

<div class="wrap flavor-admin flavor-executive-dashboard" dir="rtl">
	<div class="flavor-dashboard-header">
		<div class="flavor-dashboard-header__title">
			<h1><?php esc_html_e( 'پیشخوان هوشمند عملیات و تحلیل کسب‌وکار رستوران', 'flavor-core' ); ?></h1>
			<p class="flavor-dashboard-header__subtitle">
				<?php esc_html_e( 'مانیتورینگ بلادرنگ شاخص‌های کلیدی عملکرد (KPIs)، فروش، رفتار مشتریان و راندمان شعب.', 'flavor-core' ); ?>
			</p>
		</div>

		<!-- Filter Bar -->
		<form method="get" action="" class="flavor-dashboard-filters">
			<input type="hidden" name="page" value="flavor-analytics" />
			
			<div class="flavor-filter-group">
				<label for="filter_branch"><?php esc_html_e( 'شعبه:', 'flavor-core' ); ?></label>
				<select id="filter_branch" name="branch_id" onchange="this.form.submit()">
					<option value=""><?php esc_html_e( 'همه شعب فعال', 'flavor-core' ); ?></option>
					<?php foreach ( $branches as $b ) : ?>
						<option value="<?php echo esc_attr( (string) $b->ID ); ?>" <?php selected( $branch_id, $b->ID ); ?>>
							<?php echo esc_html( $b->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="flavor-filter-group">
				<label for="filter_start"><?php esc_html_e( 'از تاریخ:', 'flavor-core' ); ?></label>
				<input type="date" id="filter_start" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
			</div>

			<div class="flavor-filter-group">
				<label for="filter_end"><?php esc_html_e( 'تا تاریخ:', 'flavor-core' ); ?></label>
				<input type="date" id="filter_end" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
			</div>

			<button type="submit" class="button button-primary"><?php esc_html_e( 'اعمال فیلتر', 'flavor-core' ); ?></button>
		</form>
	</div>

	<!-- Primary KPI Cards Grid -->
	<div class="flavor-kpi-grid">
		<!-- Card 1: Gross Sales -->
		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'فروش ناخالص کل', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-money-alt flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( Currency::format( $overview['sales']['gross_revenue'] ) ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( sprintf( __( 'تخفیف‌ها: %s', 'flavor-core' ), Currency::format( $overview['sales']['total_discounts'] ) ) ); ?></span>
			</div>
		</div>

		<!-- Card 2: Total Orders -->
		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'تعداد کل سفارش‌ها', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-clipboard flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( $overview['sales']['total_orders'] ) ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( sprintf( __( 'میانگین سفارش (AOV): %s', 'flavor-core' ), Currency::format( $overview['sales']['avg_order_value'] ) ) ); ?></span>
			</div>
		</div>

		<!-- Card 3: Customer Retention -->
		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'نرخ بازگشت مشتریان', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-groups flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( $overview['customers']['retention_rate_pct'] . '%' ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( sprintf( __( '%d مشتری وفادار از %d کل', 'flavor-core' ), $overview['customers']['repeat_customers'], $overview['customers']['total_active'] ) ); ?></span>
			</div>
		</div>

		<!-- Card 4: Reservations -->
		<div class="flavor-kpi-card">
			<div class="flavor-kpi-card__header">
				<span class="flavor-kpi-card__title"><?php esc_html_e( 'رزروهای سالن', 'flavor-core' ); ?></span>
				<span class="dashicons dashicons-calendar-alt flavor-kpi-card__icon"></span>
			</div>
			<div class="flavor-kpi-card__value"><?php echo esc_html( number_format_i18n( $overview['reservations']['total_reservations'] ) ); ?></div>
			<div class="flavor-kpi-card__meta">
				<span><?php echo esc_html( sprintf( __( 'مجموع %d میهمان پذیرش شده', 'flavor-core' ), $overview['reservations']['total_guests'] ) ); ?></span>
			</div>
		</div>
	</div>

	<!-- Section: Order Modes Breakdown & Peak Hours -->
	<div class="flavor-dashboard-row">
		<!-- Order Modes Box -->
		<div class="flavor-box flavor-box--half">
			<h3 class="flavor-box__title"><span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'تفکیک کانال‌های سفارش', 'flavor-core' ); ?></h3>
			<div class="flavor-modes-breakdown">
				<!-- Dine In -->
				<div class="flavor-mode-item">
					<div class="flavor-mode-item__header">
						<strong>🍽️ <?php esc_html_e( 'صرف در سالن (Dine-in)', 'flavor-core' ); ?></strong>
						<span><?php echo esc_html( $overview['order_modes']['dine_in']['count'] . ' سفارش (' . $overview['order_modes']['dine_in']['percentage'] . '%)' ); ?></span>
					</div>
					<div class="flavor-progress-bar">
						<div class="flavor-progress-fill" style="width: <?php echo esc_attr( (string) $overview['order_modes']['dine_in']['percentage'] ); ?>%; background: #2563eb;"></div>
					</div>
				</div>

				<!-- Takeaway -->
				<div class="flavor-mode-item">
					<div class="flavor-mode-item__header">
						<strong>🛍️ <?php esc_html_e( 'بیرون‌بر و تحویل حضوری (Takeaway)', 'flavor-core' ); ?></strong>
						<span><?php echo esc_html( $overview['order_modes']['takeaway']['count'] . ' سفارش (' . $overview['order_modes']['takeaway']['percentage'] . '%)' ); ?></span>
					</div>
					<div class="flavor-progress-bar">
						<div class="flavor-progress-fill" style="width: <?php echo esc_attr( (string) $overview['order_modes']['takeaway']['percentage'] ); ?>%; background: #16a34a;"></div>
					</div>
				</div>

				<!-- Delivery -->
				<div class="flavor-mode-item">
					<div class="flavor-mode-item__header">
						<strong>🛵 <?php esc_html_e( 'ارسال با پیک (Delivery)', 'flavor-core' ); ?></strong>
						<span><?php echo esc_html( $overview['order_modes']['delivery']['count'] . ' سفارش (' . $overview['order_modes']['delivery']['percentage'] . '%)' ); ?></span>
					</div>
					<div class="flavor-progress-bar">
						<div class="flavor-progress-fill" style="width: <?php echo esc_attr( (string) $overview['order_modes']['delivery']['percentage'] ); ?>%; background: #ea580c;"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- Peak Hours Box -->
		<div class="flavor-box flavor-box--half">
			<h3 class="flavor-box__title"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'ساعات اوج شلوغی و سفارش (Peak Hours Heatmap)', 'flavor-core' ); ?></h3>
			<div class="flavor-peak-grid">
				<?php 
				$max_orders = 1;
				foreach ( $peak_hrs as $p ) {
					if ( $p['order_count'] > $max_orders ) {
						$max_orders = $p['order_count'];
					}
				}
				foreach ( $peak_hrs as $ph ) : 
					$pct = round( ( $ph['order_count'] / $max_orders ) * 100 );
				?>
					<div class="flavor-peak-col" title="<?php echo esc_attr( sprintf( __( 'ساعت %s: %d سفارش', 'flavor-core' ), $ph['hour'], $ph['order_count'] ) ); ?>">
						<div class="flavor-peak-bar-wrap">
							<div class="flavor-peak-bar" style="height: <?php echo esc_attr( (string) max( 4, $pct ) ); ?>%;"></div>
						</div>
						<div class="flavor-peak-label"><?php echo esc_html( substr( $ph['hour'], 0, 2 ) ); ?></div>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="description" style="text-align: center; margin-top: 10px;"><?php esc_html_e( 'توزیع ۲۴ ساعته سفارش‌ها (جهت برنامه‌ریزی شیفت پرسنل و آماده‌سازی مواد اولیه)', 'flavor-core' ); ?></p>
		</div>
	</div>

	<!-- Section: Popular Dishes & Multi-branch comparison -->
	<div class="flavor-dashboard-row">
		<!-- Top Popular Dishes -->
		<div class="flavor-box flavor-box--half">
			<h3 class="flavor-box__title"><span class="dashicons dashicons-star-filled"></span> <?php esc_html_e( 'محبوب‌ترین آیتم‌های منو (Top 10)', 'flavor-core' ); ?></h3>
			<?php if ( empty( $popular ) ) : ?>
				<p class="description"><?php esc_html_e( 'داده‌ای برای بازه انتخابی ثبت نشده است.', 'flavor-core' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'نام غذا / نوشیدنی', 'flavor-core' ); ?></th>
							<th style="width: 100px; text-align: center;"><?php esc_html_e( 'تعداد سفارش', 'flavor-core' ); ?></th>
							<th style="width: 100px; text-align: center;"><?php esc_html_e( 'تعداد فاکتور', 'flavor-core' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $popular as $d ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $d['item_name'] ); ?></strong></td>
								<td style="text-align: center;"><?php echo esc_html( number_format_i18n( $d['total_quantity'] ) ); ?></td>
								<td style="text-align: center;"><?php echo esc_html( number_format_i18n( $d['tickets_count'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<!-- Multi-Branch Performance -->
		<div class="flavor-box flavor-box--half">
			<h3 class="flavor-box__title"><span class="dashicons dashicons-store"></span> <?php esc_html_e( 'مقایسه عملکرد و فروش شعب', 'flavor-core' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'نام شعبه', 'flavor-core' ); ?></th>
						<th style="width: 90px; text-align: center;"><?php esc_html_e( 'سفارشات', 'flavor-core' ); ?></th>
						<th style="width: 120px; text-align: left;"><?php esc_html_e( 'فروش کل', 'flavor-core' ); ?></th>
						<th style="width: 110px; text-align: left;"><?php esc_html_e( 'AOV', 'flavor-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $branches_summary as $bs ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $bs['branch_name'] ); ?></strong></td>
							<td style="text-align: center;"><?php echo esc_html( number_format_i18n( $bs['total_orders'] ) ); ?></td>
							<td style="text-align: left;"><?php echo esc_html( Currency::format( $bs['gross_revenue'] ) ); ?></td>
							<td style="text-align: left;"><?php echo esc_html( Currency::format( $bs['avg_order_value'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php /* Dashboard styles live in assets/admin/css/admin.css (Premium Admin Design System). */ ?>
