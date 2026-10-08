<?php
/**
 * Embedded branch map without an API key.
 *
 * Google Maps embeds need a key, bill per load and are blocked on some Iranian
 * connections. The OpenStreetMap frame needs neither a key nor a third-party
 * script, loads lazily, and every action link opens the visitor's own maps app.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Branch_Map
 */
class Branch_Map {

	/**
	 * Half-size of the map frame in degrees (~450 m at Tehran's latitude).
	 */
	const SPAN = 0.004;

	/**
	 * Is the embed allowed on this site?
	 */
	public static function enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_branch_map_enable', 'yes' );
	}

	/**
	 * Validated coordinates of a branch.
	 *
	 * @param int $branch_id Branch post id.
	 * @return array<string, float>|null
	 */
	public static function coordinates( int $branch_id ): ?array {
		if ( $branch_id <= 0 ) {
			return null;
		}

		$lat = (string) get_post_meta( $branch_id, '_flavor_lat', true );
		$lng = (string) get_post_meta( $branch_id, '_flavor_lng', true );

		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return null;
		}

		$lat = (float) $lat;
		$lng = (float) $lng;

		if ( 0.0 === $lat && 0.0 === $lng ) {
			return null;
		}

		if ( abs( $lat ) > 90 || abs( $lng ) > 180 ) {
			return null;
		}

		return array( 'lat' => $lat, 'lng' => $lng );
	}

	/**
	 * Lazy iframe URL for a point.
	 *
	 * @param float $lat  Latitude.
	 * @param float $lng  Longitude.
	 * @param float $span Half-size of the frame in degrees.
	 * @return string
	 */
	public static function embed_url( float $lat, float $lng, float $span = self::SPAN ): string {
		$span = max( 0.0005, min( 0.5, $span ) );

		$bbox = implode(
			',',
			array(
				number_format( $lng - $span, 6, '.', '' ),
				number_format( $lat - $span, 6, '.', '' ),
				number_format( $lng + $span, 6, '.', '' ),
				number_format( $lat + $span, 6, '.', '' ),
			)
		);

		return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode( $bbox )
			. '&layer=mapnik&marker=' . rawurlencode( number_format( $lat, 6, '.', '' ) . ',' . number_format( $lng, 6, '.', '' ) );
	}

	/**
	 * Turn-by-turn link that opens the visitor's own maps application.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string
	 */
	public static function directions_url( float $lat, float $lng ): string {
		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( number_format( $lat, 6, '.', '' ) . ',' . number_format( $lng, 6, '.', '' ) );
	}

	/**
	 * Print the map block for a branch.
	 *
	 * @param int    $branch_id Branch post id.
	 * @param string $title     Optional heading.
	 */
	public static function render( int $branch_id, string $title = '' ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$point = self::coordinates( $branch_id );

		if ( ! $point ) {
			return;
		}

		$address = (string) get_post_meta( $branch_id, '_flavor_address', true );

		if ( '' === trim( $title ) ) {
			$title = __( 'نشانی روی نقشه', 'flavor' );
		}
		?>
		<section class="flavor-map" aria-label="<?php echo esc_attr( $title ); ?>">
			<h2 class="flavor-map__title"><?php UI::icon( 'pin', 20 ); ?><?php echo esc_html( $title ); ?></h2>
			<div class="flavor-map__frame">
				<iframe
					src="<?php echo esc_url( self::embed_url( $point['lat'], $point['lng'] ) ); ?>"
					title="<?php echo esc_attr( sprintf( /* translators: %s: branch name. */ __( 'نقشهٔ %s', 'flavor' ), get_the_title( $branch_id ) ) ); ?>"
					loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade"
					width="600" height="320" style="border:0" tabindex="-1"></iframe>
			</div>
			<div class="flavor-map__actions">
				<a class="flavor-btn flavor-btn--outline flavor-btn--sm" href="<?php echo esc_url( self::directions_url( $point['lat'], $point['lng'] ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php UI::icon( 'pin', 16 ); ?><?php esc_html_e( 'مسیریابی', 'flavor' ); ?>
				</a>
				<?php if ( '' !== trim( $address ) ) : ?>
					<button type="button" class="flavor-btn flavor-btn--ghost flavor-btn--sm" data-flavor-copy="<?php echo esc_attr( $address ); ?>">
						<?php UI::icon( 'check', 16 ); ?><span data-flavor-copy-label><?php esc_html_e( 'کپی نشانی', 'flavor' ); ?></span>
					</button>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
}
