<?php
/**
 * Branch → tables admin: CRUD, QR download, A6 print cards.
 *
 * @package FlavorCore
 */

namespace FlavorCore\Table;

use FlavorCore\PostTypes\BranchPostType;
use FlavorCore\Support\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Class TableAdmin
 */
class TableAdmin {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_post_flavor_save_tables', array( $this, 'handle' ) );
		add_action( 'admin_post_flavor_qr_download', array( $this, 'download' ) );
		add_action( 'admin_post_flavor_qr_print', array( $this, 'print_cards' ) );
	}

	/**
	 * Render the tables page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}

		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : BranchPostType::default_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$branches  = get_posts(
			array(
				'post_type'      => BranchPostType::POST_TYPE,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 100,
			)
		);
		$tables = $branch_id ? TableRepository::for_branch( $branch_id ) : array();
		$nonce  = wp_create_nonce( 'flavor_save_tables' );
		?>
		<div class="wrap flavor-admin">
			<h1><?php esc_html_e( 'میزها و QR', 'flavor-core' ); ?></h1>
			<form method="get" style="margin: 1rem 0;">
				<input type="hidden" name="page" value="flavor-tables" />
				<label>
					<?php esc_html_e( 'شعبه', 'flavor-core' ); ?>
					<select name="branch_id" onchange="this.form.submit()">
						<?php foreach ( $branches as $branch ) : ?>
							<option value="<?php echo esc_attr( (string) $branch->ID ); ?>" <?php selected( $branch_id, $branch->ID ); ?>>
								<?php echo esc_html( get_the_title( $branch ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<?php if ( $branch_id ) : ?>
				<div class="flavor-admin__grid">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'flavor_save_tables' ); ?>
						<input type="hidden" name="action" value="flavor_save_tables" />
						<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
						<input type="hidden" name="flavor_op" value="create" />
						<h2><?php esc_html_e( 'میز تکی', 'flavor-core' ); ?></h2>
						<p>
							<label><?php esc_html_e( 'شماره', 'flavor-core' ); ?> <input type="text" name="table_number" required /></label>
							<label><?php esc_html_e( 'برچسب', 'flavor-core' ); ?> <input type="text" name="label" /></label>
							<label><?php esc_html_e( 'ظرفیت', 'flavor-core' ); ?> <input type="number" name="capacity" value="4" min="1" max="20" /></label>
							<label><?php esc_html_e( 'بخش', 'flavor-core' ); ?>
								<select name="section">
									<option value="indoor"><?php esc_html_e( 'سالن', 'flavor-core' ); ?></option>
									<option value="outdoor"><?php esc_html_e( 'فضای باز', 'flavor-core' ); ?></option>
									<option value="bar"><?php esc_html_e( 'بار', 'flavor-core' ); ?></option>
									<option value="window"><?php esc_html_e( 'کنار پنجره', 'flavor-core' ); ?></option>
								</select>
							</label>
						</p>
						<button class="button"><?php esc_html_e( 'افزودن میز', 'flavor-core' ); ?></button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'flavor_save_tables' ); ?>
						<input type="hidden" name="action" value="flavor_save_tables" />
						<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
						<input type="hidden" name="flavor_op" value="bulk" />
						<h2><?php esc_html_e( 'ساخت گروهی', 'flavor-core' ); ?></h2>
						<p>
							<label><?php esc_html_e( 'از', 'flavor-core' ); ?> <input type="number" name="from" value="1" min="1" /></label>
							<label><?php esc_html_e( 'تا', 'flavor-core' ); ?> <input type="number" name="to" value="10" min="1" /></label>
							<label><?php esc_html_e( 'ظرفیت', 'flavor-core' ); ?> <input type="number" name="capacity" value="4" min="1" max="20" /></label>
						</p>
						<button class="button button-primary"><?php esc_html_e( 'ساخت میزها', 'flavor-core' ); ?></button>
					</form>
				</div>

				<p>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=flavor_qr_print&branch_id=' . $branch_id ), 'flavor_save_tables' ) ); ?>">
						<?php esc_html_e( 'چاپ کارت‌های A6', 'flavor-core' ); ?>
					</a>
				</p>

				<h2><?php esc_html_e( 'لیست میزها', 'flavor-core' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'شماره', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'برچسب', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'ظرفیت', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'بخش', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'فعال', 'flavor-core' ); ?></th>
							<th><?php esc_html_e( 'QR', 'flavor-core' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $tables ) ) : ?>
							<tr><td colspan="7"><?php esc_html_e( 'میزی ثبت نشده.', 'flavor-core' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( $tables as $table ) : ?>
							<?php
							$base = admin_url( 'admin-post.php?action=flavor_qr_download&table_id=' . (int) $table['id'] . '&_wpnonce=' . $nonce );
							?>
							<tr>
								<td><?php echo esc_html( (string) $table['table_number'] ); ?></td>
								<td><?php echo esc_html( (string) $table['label'] ); ?></td>
								<td><?php echo esc_html( (string) $table['capacity'] ); ?></td>
								<td><?php echo esc_html( (string) $table['section'] ); ?></td>
								<td><?php echo (int) $table['is_active'] ? '✓' : '—'; ?></td>
								<td>
									<a href="<?php echo esc_url( $base . '&format=png' ); ?>">PNG</a>
									· <a href="<?php echo esc_url( $base . '&format=svg' ); ?>">SVG</a>
									· <a href="<?php echo esc_url( $base . '&format=pdf' ); ?>">PDF</a>
								</td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'flavor_save_tables' ); ?>
										<input type="hidden" name="action" value="flavor_save_tables" />
										<input type="hidden" name="flavor_op" value="toggle" />
										<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
										<input type="hidden" name="table_id" value="<?php echo esc_attr( (string) $table['id'] ); ?>" />
										<button class="button-link"><?php echo (int) $table['is_active'] ? esc_html__( 'غیرفعال', 'flavor-core' ) : esc_html__( 'فعال', 'flavor-core' ); ?></button>
									</form>
									|
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'flavor_save_tables' ); ?>
										<input type="hidden" name="action" value="flavor_save_tables" />
										<input type="hidden" name="flavor_op" value="delete" />
										<input type="hidden" name="branch_id" value="<?php echo esc_attr( (string) $branch_id ); ?>" />
										<input type="hidden" name="table_id" value="<?php echo esc_attr( (string) $table['id'] ); ?>" />
										<button class="button-link-delete" onclick="return confirm('?');"><?php esc_html_e( 'حذف', 'flavor-core' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * admin-post handler.
	 */
	public function handle(): void {
		$this->guard();
		check_admin_referer( 'flavor_save_tables' );

		$branch_id = isset( $_POST['branch_id'] ) ? absint( wp_unslash( $_POST['branch_id'] ) ) : 0;
		$op        = isset( $_POST['flavor_op'] ) ? sanitize_key( wp_unslash( $_POST['flavor_op'] ) ) : '';
		$this->assert_branch( $branch_id );

		if ( 'bulk' === $op && $branch_id ) {
			$from     = isset( $_POST['from'] ) ? absint( wp_unslash( $_POST['from'] ) ) : 1;
			$to       = isset( $_POST['to'] ) ? absint( wp_unslash( $_POST['to'] ) ) : 1;
			$capacity = isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 4;
			TableRepository::bulk_create( $branch_id, $from, $to, $capacity );
		} elseif ( 'create' === $op && $branch_id ) {
			TableRepository::create(
				array(
					'branch_id'    => $branch_id,
					'table_number' => isset( $_POST['table_number'] ) ? sanitize_text_field( wp_unslash( $_POST['table_number'] ) ) : '',
					'label'        => isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '',
					'capacity'     => isset( $_POST['capacity'] ) ? absint( wp_unslash( $_POST['capacity'] ) ) : 4,
					'section'      => isset( $_POST['section'] ) ? sanitize_key( wp_unslash( $_POST['section'] ) ) : 'indoor',
				)
			);
		} elseif ( 'delete' === $op ) {
			$tid = isset( $_POST['table_id'] ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
			if ( $tid ) {
				TableRepository::delete( $tid );
			}
		} elseif ( 'toggle' === $op ) {
			$tid = isset( $_POST['table_id'] ) ? absint( wp_unslash( $_POST['table_id'] ) ) : 0;
			$row = $tid ? TableRepository::find( $tid ) : null;
			if ( $row ) {
				TableRepository::update( $tid, array( 'is_active' => ! (int) $row['is_active'] ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=flavor-tables&branch_id=' . $branch_id ) );
		exit;
	}

	/**
	 * Stream PNG / SVG / PDF.
	 */
	public function download(): void {
		$this->guard();
		check_admin_referer( 'flavor_save_tables' );
		$tid  = isset( $_GET['table_id'] ) ? absint( wp_unslash( $_GET['table_id'] ) ) : 0;
		$fmt  = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'png';
		$table = $tid ? TableRepository::find( $tid ) : null;
		if ( ! $table ) {
			wp_die( esc_html__( 'میز پیدا نشد.', 'flavor-core' ) );
		}
		$this->assert_branch( (int) $table['branch_id'] );

		$url  = TableRepository::public_url( $table );
		$logo = QrGenerator::logo_path();
		$name = 'table-' . sanitize_file_name( (string) $table['table_number'] );

		if ( 'svg' === $fmt ) {
			$bin = QrGenerator::svg( $url, 512, $logo );
			header( 'Content-Type: image/svg+xml; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $name . '.svg"' );
			echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}
		if ( 'pdf' === $fmt ) {
			$bin = QrGenerator::pdf( $url, 'Table ' . $table['table_number'], get_bloginfo( 'name' ), $logo );
			if ( $bin ) {
				header( 'Content-Type: application/pdf' );
				header( 'Content-Disposition: attachment; filename="' . $name . '.pdf"' );
				echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}
			$fmt = 'png';
		}
		$bin = QrGenerator::png( $url, 512, $logo );
		if ( '' === $bin ) {
			wp_die( esc_html__( 'برای PNG به پسوند GD نیاز است. SVG را دانلود کنید.', 'flavor-core' ) );
		}
		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: attachment; filename="' . $name . '.png"' );
		echo $bin; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Print-ready A6 cards for every table of a branch.
	 */
	public function print_cards(): void {
		$this->guard();
		check_admin_referer( 'flavor_save_tables' );
		$branch_id = isset( $_GET['branch_id'] ) ? absint( wp_unslash( $_GET['branch_id'] ) ) : 0;
		$this->assert_branch( $branch_id );
		$tables = TableRepository::for_branch( $branch_id );
		$logo   = QrGenerator::logo_path();
		$brand  = get_bloginfo( 'name' );
		$bname  = get_the_title( $branch_id );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
	<meta charset="utf-8" />
	<title><?php esc_html_e( 'کارت QR میز', 'flavor-core' ); ?></title>
	<style>
		@page { size: A6 portrait; margin: 8mm; }
		body { font-family: Tahoma, Vazirmatn, sans-serif; background: #f3efe8; }
		.card { width: 105mm; min-height: 148mm; background: #fff; margin: 0 auto 12mm; padding: 10mm; box-sizing: border-box; page-break-after: always; text-align: center; border: 1px solid #ddd; }
		.card h1 { font-size: 18px; margin: 0 0 4px; }
		.card h2 { font-size: 32px; margin: 8px 0; }
		.card img, .card svg { width: 70mm; height: 70mm; }
		.hint { color: #555; font-size: 13px; }
		.no-print { text-align: center; margin: 16px; }
		@media print { .no-print { display: none; } body { background: #fff; } .card { border: 0; margin: 0; } }
	</style>
</head>
<body>
	<div class="no-print"><button onclick="window.print()"><?php esc_html_e( 'چاپ / ذخیره PDF', 'flavor-core' ); ?></button></div>
	<?php foreach ( $tables as $table ) : ?>
		<?php
		$url = TableRepository::public_url( $table );
		$svg = QrGenerator::svg( $url, 420, $logo );
		?>
		<section class="card">
			<h1><?php echo esc_html( $brand ); ?></h1>
			<p><?php echo esc_html( $bname ); ?></p>
			<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<h2><?php echo esc_html( sprintf( /* translators: table */ __( 'میز %s', 'flavor-core' ), $table['table_number'] ) ); ?></h2>
			<p class="hint"><?php esc_html_e( 'اسکن کنید و از همین میز سفارش دهید.', 'flavor-core' ); ?></p>
		</section>
	<?php endforeach; ?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Capability check.
	 */
	private function guard(): void {
		if ( ! current_user_can( 'flavor_manage_branch' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی ندارید.', 'flavor-core' ) );
		}
	}

	/**
	 * Branch ACL.
	 */
	private function assert_branch( int $branch_id ): void {
		if ( $branch_id && ! Roles::can_access_branch( get_current_user_id(), $branch_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'این شعبه در دسترس شما نیست.', 'flavor-core' ) );
		}
	}
}
