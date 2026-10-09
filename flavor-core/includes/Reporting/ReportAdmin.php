<?php
/**
 * Reporting admin page and download endpoint (N-11).
 *
 * The page is a filter form and a preview; the download is a separate
 * admin-post action guarded by a nonce and a capability. Keeping them
 * apart means the export cannot be triggered by merely loading a URL, and
 * it cannot be triggered by a user who cannot see the page.
 *
 * Why CSV and not PDF: bundling a PDF engine means shipping a font with
 * Arabic presentation forms plus a bidirectional shaping pass, because the
 * standard PDF core fonts are Latin-only and would render every Persian
 * label as boxes. That is a large dependency for a report a browser can
 * produce correctly with Ctrl+P. The page therefore offers a print view as
 * well, which prints through the browser's own shaping engine.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Reporting;

defined( 'ABSPATH' ) || exit;

/**
 * Class ReportAdmin
 */
class ReportAdmin {

	public const SLUG = 'flavor-reports';
	public const ACTION = 'flavor_export_report';
	public const NONCE = 'flavor_export_report';

	/**
	 * Register hooks.
	 */
	public static function hooks(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 20 );
		add_action( 'admin_post_' . self::ACTION, array( self::class, 'download' ) );
	}

	/**
	 * Add the submenu under the restaurant menu.
	 */
	public static function menu(): void {
		add_submenu_page(
			'flavor-core',
			__( 'خروجی گزارش (CSV)', 'flavor-core' ),
			__( 'خروجی گزارش', 'flavor-core' ),
			'flavor_view_reports',
			self::SLUG,
			array( self::class, 'render' )
		);
	}

	/**
	 * The timezone a report is expressed in, stated rather than implied.
	 *
	 * @return string
	 */
	public static function timezone_label(): string {
		$tz = wp_timezone_string();
		return '' === $tz ? 'UTC' : $tz;
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_view_reports' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'flavor-core' ) );
		}

		$request = ReportQuery::normalise(
			array(
				'type'   => isset( $_GET['type'] ) ? wp_unslash( $_GET['type'] ) : 'orders', // phpcs:ignore WordPress.Security.NonceVerification
				'from'   => isset( $_GET['from'] ) ? wp_unslash( $_GET['from'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'to'     => isset( $_GET['to'] ) ? wp_unslash( $_GET['to'] ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				'branch' => isset( $_GET['branch'] ) ? wp_unslash( $_GET['branch'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
			)
		);

		$result = ReportQuery::run( $request['type'], $request['from'], $request['to'], $request['branch'] );
		$rows   = $result['rows'];
		$head   = ReportQuery::headers( $request['type'] );
		$pii    = ReportQuery::can_see_pii();
		$tz     = self::timezone_label();

		$labels = array(
			'orders'       => __( 'سفارش‌ها', 'flavor-core' ),
			'reservations' => __( 'رزروها', 'flavor-core' ),
			'loyalty'      => __( 'باشگاه مشتریان', 'flavor-core' ),
		);

		?>
		<div class="wrap flavor-report">
			<h1><?php esc_html_e( 'خروجی گزارش', 'flavor-core' ); ?></h1>

			<p class="description">
				<?php
				printf(
					/* translators: %s is the site's timezone. */
					esc_html__( 'همهٔ تاریخ‌ها بر پایهٔ منطقهٔ زمانیِ سایت (%s) هستند.', 'flavor-core' ),
					'<code>' . esc_html( $tz ) . '</code>'
				);
				?>
			</p>

			<?php if ( ! $pii ) : ?>
				<div class="notice notice-info"><p>
					<?php esc_html_e( 'شمارهٔ تلفنِ مشتریان در خروجی پوشانده می‌شود. دیدنِ شمارهٔ کامل نیازمندِ تواناییِ ویژه است.', 'flavor-core' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="get" action="">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="flavor-report-type"><?php esc_html_e( 'نوع گزارش', 'flavor-core' ); ?></label></th>
						<td>
							<select id="flavor-report-type" name="type">
								<?php foreach ( $labels as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $request['type'], $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="flavor-report-from"><?php esc_html_e( 'از تاریخ', 'flavor-core' ); ?></label></th>
						<td><input type="date" id="flavor-report-from" name="from" value="<?php echo esc_attr( $request['from'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="flavor-report-to"><?php esc_html_e( 'تا تاریخ', 'flavor-core' ); ?></label></th>
						<td><input type="date" id="flavor-report-to" name="to" value="<?php echo esc_attr( $request['to'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="flavor-report-branch"><?php esc_html_e( 'شعبه', 'flavor-core' ); ?></label></th>
						<td>
							<input type="number" id="flavor-report-branch" name="branch" min="0" value="<?php echo esc_attr( (string) $request['branch'] ); ?>" />
							<p class="description"><?php esc_html_e( 'صفر یعنی همهٔ شعبه‌ها.', 'flavor-core' ); ?></p>
						</td>
					</tr>
				</tbody></table>
				<?php submit_button( __( 'نمایش', 'flavor-core' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr />

			<h2>
				<?php
				printf(
					/* translators: 1: report label, 2: row count. */
					esc_html__( 'پیش‌نمایش: %1$s (%2$s ردیف)', 'flavor-core' ),
					esc_html( $labels[ $request['type'] ] ?? $request['type'] ),
					esc_html( number_format_i18n( $result['count'] ) )
				);
				?>
			</h2>

			<?php if ( $result['truncated'] ) : ?>
				<div class="notice notice-warning"><p>
					<?php
					printf(
						/* translators: %s is the maximum row count. */
						esc_html__( 'بازهٔ انتخابی بزرگ است؛ خروجی به %s ردیف محدود شد. بازه را کوتاه‌تر کنید.', 'flavor-core' ),
						esc_html( number_format_i18n( ReportQuery::MAX_ROWS ) )
					);
					?>
				</p></div>
			<?php endif; ?>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'در این بازه داده‌ای یافت نشد.', 'flavor-core' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="type" value="<?php echo esc_attr( $request['type'] ); ?>" />
					<input type="hidden" name="from" value="<?php echo esc_attr( $request['from'] ); ?>" />
					<input type="hidden" name="to" value="<?php echo esc_attr( $request['to'] ); ?>" />
					<input type="hidden" name="branch" value="<?php echo esc_attr( (string) $request['branch'] ); ?>" />
					<?php wp_nonce_field( self::NONCE ); ?>
					<?php submit_button( __( 'دانلود CSV', 'flavor-core' ), 'primary', 'submit', false ); ?>
				</form>

				<table class="widefat striped" style="margin-top:12px;">
					<thead><tr>
						<?php foreach ( $head as $column ) : ?>
							<th scope="col"><?php echo esc_html( $column ); ?></th>
						<?php endforeach; ?>
					</tr></thead>
					<tbody>
						<?php foreach ( array_slice( $rows, 0, 50 ) as $row ) : ?>
							<tr>
								<?php foreach ( $row as $cell ) : ?>
									<td><?php echo esc_html( $cell ); ?></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( count( $rows ) > 50 ) : ?>
					<p class="description">
						<?php esc_html_e( 'فقط ۵۰ ردیفِ نخست در پیش‌نمایش نمایش داده می‌شود؛ خروجیِ CSV کامل است.', 'flavor-core' ); ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Stream the CSV.
	 */
	public static function download(): void {
		if ( ! current_user_can( 'flavor_view_reports' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'flavor-core' ), 403 );
		}
		check_admin_referer( self::NONCE );

		$request = ReportQuery::normalise(
			array(
				'type'   => isset( $_POST['type'] ) ? wp_unslash( $_POST['type'] ) : 'orders',
				'from'   => isset( $_POST['from'] ) ? wp_unslash( $_POST['from'] ) : '',
				'to'     => isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '',
				'branch' => isset( $_POST['branch'] ) ? wp_unslash( $_POST['branch'] ) : 0,
			)
		);

		if ( ! $request['ok'] ) {
			wp_die( esc_html( implode( ' ', $request['errors'] ) ), 400 );
		}

		$result = ReportQuery::run( $request['type'], $request['from'], $request['to'], $request['branch'] );
		$csv    = CsvWriter::render( ReportQuery::headers( $request['type'] ), $result['rows'] );
		$name   = CsvWriter::filename( $request['type'], $request['from'], $request['to'], self::timezone_label() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $csv ) );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV is the payload, and every cell is quoted by CsvWriter.
		exit;
	}
}
