<?php
/**
 * In-panel changelog (the server-free half of N-10).
 *
 * A full update channel needs a release server and signed packages, which
 * is an infrastructure decision rather than a code one. What does not need
 * a server is the part an owner actually looks for after updating: "what
 * changed, and is anything I rely on gone?"
 *
 * The answer is read from readme.txt rather than duplicated here, so the
 * panel can never drift from the file a marketplace reads. If readme.txt is
 * missing or unreadable the page says so plainly instead of inventing
 * content.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Changelog
 */
class Changelog {

	public const PAGE = 'flavor-changelog';

	/** How many past versions to list. The full history stays in readme.txt. */
	private const MAX_VERSIONS = 12;

	/**
	 * Register the admin page.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ), 8 );
	}

	/**
	 * Add the page next to the launch centre.
	 */
	public static function menu(): void {
		add_theme_page(
			__( 'تغییرات Flavor', 'flavor' ),
			__( 'تغییرات', 'flavor' ),
			'manage_options',
			self::PAGE,
			array( self::class, 'render' )
		);
	}

	/**
	 * Path to the readme shipped with the theme.
	 *
	 * @return string
	 */
	public static function readme_path(): string {
		return FLAVOR_DIR . '/readme.txt';
	}

	/**
	 * Parse the changelog section of readme.txt.
	 *
	 * Returns a list of releases, newest first:
	 *   array{ version: string, entries: string[] }
	 *
	 * readme.txt is authored by us and shipped inside the theme, so the
	 * parsing is trusted; everything is still escaped at the point of
	 * output, because a future edit should not be able to turn a changelog
	 * line into markup.
	 *
	 * @return array<int, array{version: string, entries: array<int, string>}>
	 */
	public static function releases(): array {
		$path = self::readme_path();

		if ( ! is_readable( $path ) ) {
			return array();
		}

		$lines    = file( $path, FILE_IGNORE_NEW_LINES );
		$releases = array();
		$in_log   = false;
		$current  = null;

		if ( false === $lines ) {
			return array();
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Sections are "== Name =="; the changelog is the one we want.
			if ( preg_match( '/^==\s*(.+?)\s*==$/', $line, $m ) ) {
				$heading = strtolower( $m[1] );
				if ( 'changelog' === $heading ) {
					$in_log = true;
					continue;
				}
				if ( $in_log ) {
					break; // Any later section ends the changelog.
				}
				continue;
			}

			if ( ! $in_log ) {
				continue;
			}

			// Versions are "= 1.6.1 =".
			if ( preg_match( '/^=\s*(.+?)\s*=$/', $line, $m ) ) {
				if ( null !== $current ) {
					$releases[] = $current;
				}
				$current = array(
					'version' => $m[1],
					'entries' => array(),
				);
				continue;
			}

			// Everything else inside a version is a bullet or its continuation.
			if ( null === $current ) {
				continue;
			}

			if ( 0 === strpos( $line, '*' ) ) {
				$current['entries'][] = trim( ltrim( $line, '*' ) );
			} elseif ( '' !== $line && ! empty( $current['entries'] ) ) {
				// Wrapped continuation line: append to the previous bullet.
				$last                        = count( $current['entries'] ) - 1;
				$current['entries'][ $last ] .= ' ' . $line;
			}
		}

		if ( null !== $current ) {
			$releases[] = $current;
		}

		return array_slice( $releases, 0, self::MAX_VERSIONS );
	}

	/**
	 * The version currently installed, for highlighting the top release.
	 *
	 * @return string
	 */
	public static function current_version(): string {
		$theme = wp_get_theme( get_template() );
		return (string) $theme->get( 'Version' );
	}

	/**
	 * Render the page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازهٔ دسترسی به این صفحه را ندارید.', 'flavor' ) );
		}

		$releases = self::releases();
		$current  = self::current_version();
		$missing  = ! is_readable( self::readme_path() );

		?>
		<div class="wrap flavor-changelog">
			<h1><?php esc_html_e( 'تغییرات Flavor', 'flavor' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'این فهرست مستقیماً از readme.txtِ قالب خوانده می‌شود، بنابراین با همان فایلی که مارکت‌پلیس می‌خواند همگام است.', 'flavor' ); ?>
			</p>

			<?php if ( $missing ) : ?>
				<div class="notice notice-warning"><p>
					<?php esc_html_e( 'فایل readme.txt در پوشهٔ قالب یافت نشد. فهرستِ تغییرات در دسترس نیست، ولی قالب سالم است.', 'flavor' ); ?>
				</p></div>
			<?php elseif ( empty( $releases ) ) : ?>
				<div class="notice notice-info"><p>
					<?php esc_html_e( 'بخشِ Changelog در readme.txt خالی است.', 'flavor' ); ?>
				</p></div>
			<?php endif; ?>

			<?php if ( ! empty( $releases ) ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s is the installed theme version. */
						esc_html__( 'نسخهٔ نصب‌شده: %s', 'flavor' ),
						'<code>' . esc_html( $current ) . '</code>'
					);
					?>
				</p>

				<?php foreach ( $releases as $index => $release ) : ?>
					<?php $is_current = ( 0 === $index && $release['version'] === $current ); ?>
					<div class="flavor-changelog__release" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin:0 0 16px;max-width:760px;">
						<h2 style="margin-top:0;font-size:1.1em;">
							<?php
							printf(
								/* translators: %s is a version number such as 1.6.1. */
								esc_html__( 'نسخهٔ %s', 'flavor' ),
								esc_html( $release['version'] )
							);
							?>
							<?php if ( $is_current ) : ?>
								<span class="flavor-changelog__badge" style="background:#0a7d32;color:#fff;border-radius:10px;padding:2px 10px;font-size:.75em;vertical-align:middle;">
									<?php esc_html_e( 'نصب‌شده', 'flavor' ); ?>
								</span>
							<?php endif; ?>
						</h2>

						<?php if ( empty( $release['entries'] ) ) : ?>
							<p class="description"><?php esc_html_e( 'برای این نسخه یادداشتی ثبت نشده است.', 'flavor' ); ?></p>
						<?php else : ?>
							<ul style="list-style:disc;padding-right:20px;">
								<?php foreach ( $release['entries'] as $entry ) : ?>
									<li><?php echo esc_html( $entry ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php
	}
}
