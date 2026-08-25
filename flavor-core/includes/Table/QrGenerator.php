<?php
/**
 * QR PNG / SVG / print-PDF for dining tables.
 *
 * Encoder: bundled MIT library (Kazuhiko Arase).
 *
 * @package FlavorCore
 */

namespace FlavorCore\Table;

defined( 'ABSPATH' ) || exit;

/**
 * Class QrGenerator
 */
class QrGenerator {

	/**
	 * Load the bundled encoder once.
	 */
	private static function boot(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		require_once FLAVOR_CORE_PATH . 'includes/lib/qrcode.php';
		$done = true;
	}

	/**
	 * Matrix of modules (0/1) for a URL.
	 *
	 * @return int[][]
	 */
	public static function matrix( string $payload ): array {
		self::boot();
		$qr = \QRCode::getMinimumQRCode( $payload, QR_ERROR_CORRECT_LEVEL_M );
		$n  = $qr->getModuleCount();
		$out = array();
		for ( $r = 0; $r < $n; $r++ ) {
			$row = array();
			for ( $c = 0; $c < $n; $c++ ) {
				$row[] = $qr->isDark( $r, $c ) ? 1 : 0;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * SVG document (always available, no GD).
	 */
	public static function svg( string $payload, int $size = 512, string $logo_path = '' ): string {
		$m     = self::matrix( $payload );
		$n     = count( $m );
		$quiet = 4;
		$total = $n + ( $quiet * 2 );
		$cell  = $size / $total;

		$rects = '';
		for ( $r = 0; $r < $n; $r++ ) {
			for ( $c = 0; $c < $n; $c++ ) {
				if ( ! $m[ $r ][ $c ] ) {
					continue;
				}
				$x = ( $c + $quiet ) * $cell;
				$y = ( $r + $quiet ) * $cell;
				$rects .= sprintf( '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>', $x, $y, $cell, $cell );
			}
		}

		$logo = '';
		if ( $logo_path && is_readable( $logo_path ) ) {
			$bin  = file_get_contents( $logo_path );
			$mime = wp_check_filetype( $logo_path )['type'] ?: 'image/png';
			if ( $bin ) {
				$box = $size * 0.18;
				$x   = ( $size - $box ) / 2;
				$y   = ( $size - $box ) / 2;
				$b64 = base64_encode( $bin );
				$logo = sprintf(
					'<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" fill="#fff"/>' .
					'<image href="data:%s;base64,%s" x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>',
					$x - 4,
					$y - 4,
					$box + 8,
					$box + 8,
					$mime,
					$b64,
					$x,
					$y,
					$box,
					$box
				);
			}
		}

		return '<?xml version="1.0" encoding="UTF-8"?>' .
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">' .
			'<rect width="100%" height="100%" fill="#ffffff"/>' .
			'<g fill="#111111">' . $rects . '</g>' . $logo . '</svg>';
	}

	/**
	 * PNG binary. Empty string if GD is missing.
	 */
	public static function png( string $payload, int $size = 512, string $logo_path = '' ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}
		$m     = self::matrix( $payload );
		$n     = count( $m );
		$quiet = 4;
		$total = $n + ( $quiet * 2 );
		$img   = imagecreatetruecolor( $size, $size );
		if ( ! $img ) {
			return '';
		}
		$white = imagecolorallocate( $img, 255, 255, 255 );
		$black = imagecolorallocate( $img, 17, 17, 17 );
		imagefilledrectangle( $img, 0, 0, $size, $size, $white );
		$cell = $size / $total;
		for ( $r = 0; $r < $n; $r++ ) {
			for ( $c = 0; $c < $n; $c++ ) {
				if ( ! $m[ $r ][ $c ] ) {
					continue;
				}
				$x1 = (int) floor( ( $c + $quiet ) * $cell );
				$y1 = (int) floor( ( $r + $quiet ) * $cell );
				$x2 = (int) ceil( ( $c + $quiet + 1 ) * $cell );
				$y2 = (int) ceil( ( $r + $quiet + 1 ) * $cell );
				imagefilledrectangle( $img, $x1, $y1, $x2, $y2, $black );
			}
		}

		if ( $logo_path && is_readable( $logo_path ) ) {
			$logo = self::load_image( $logo_path );
			if ( $logo ) {
				$box = (int) round( $size * 0.18 );
				$x   = (int) ( ( $size - $box ) / 2 );
				$y   = (int) ( ( $size - $box ) / 2 );
				imagefilledrectangle( $img, $x - 4, $y - 4, $x + $box + 4, $y + $box + 4, $white );
				imagecopyresampled( $img, $logo, $x, $y, 0, 0, $box, $box, imagesx( $logo ), imagesy( $logo ) );
				imagedestroy( $logo );
			}
		}

		ob_start();
		imagepng( $img );
		imagedestroy( $img );
		return (string) ob_get_clean();
	}

	/**
	 * Minimal one-page PDF wrapping a PNG (or a note if GD missing).
	 */
	public static function pdf( string $payload, string $title, string $subtitle, string $logo_path = '' ): string {
		$png = self::png( $payload, 400, $logo_path );
		if ( '' === $png ) {
			// Fallback: "print this SVG as PDF" is handled by the A6 HTML view.
			return '';
		}
		return self::png_to_pdf( $png, $title, $subtitle );
	}

	/**
	 * Path of the custom logo if it is a local file we can read.
	 */
	public static function logo_path(): string {
		$id = (int) get_theme_mod( 'custom_logo' );
		if ( ! $id ) {
			return '';
		}
		$path = get_attached_file( $id );
		return ( $path && is_readable( $path ) ) ? $path : '';
	}

	/**
	 * @param string $path Image path.
	 * @return \GdImage|resource|null
	 */
	private static function load_image( string $path ) {
		$info = wp_check_filetype( $path );
		$type = $info['type'] ?? '';
		if ( 'image/png' === $type && function_exists( 'imagecreatefrompng' ) ) {
			$img = @imagecreatefrompng( $path );
			return $img ?: null;
		}
		if ( in_array( $type, array( 'image/jpeg', 'image/jpg' ), true ) && function_exists( 'imagecreatefromjpeg' ) ) {
			$img = @imagecreatefromjpeg( $path );
			return $img ?: null;
		}
		return null;
	}

	/**
	 * Tiny PDF writer: A6 page, Helvetica, one JPEG/PNG image.
	 * PNG is re-encoded as JPEG for simpler embedding.
	 */
	private static function png_to_pdf( string $png, string $title, string $subtitle ): string {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$src = imagecreatefromstring( $png );
		if ( ! $src ) {
			return '';
		}
		$w = imagesx( $src );
		$h = imagesy( $src );
		ob_start();
		imagejpeg( $src, null, 90 );
		imagedestroy( $src );
		$jpg = (string) ob_get_clean();

		$title    = self::pdf_escape( $title );
		$subtitle = self::pdf_escape( $subtitle );

		// A6 = 297.64 x 419.53 pt
		$page_w = 297.64;
		$page_h = 419.53;
		$img_w  = 200;
		$img_h  = 200;
		$img_x  = ( $page_w - $img_w ) / 2;
		$img_y  = 120;

		$objects = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$objects[] = sprintf(
			'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> /XObject << /Im1 6 0 R >> >> >>',
			$page_w,
			$page_h
		);
		$stream = sprintf(
			"BT /F1 16 Tf 40 370 Td (%s) Tj ET\nBT /F1 11 Tf 40 350 Td (%s) Tj ET\n%.2f %.2f %.2f %.2f re S\nq %.2f 0 0 %.2f %.2f %.2f cm /Im1 Do Q",
			$title,
			$subtitle,
			$img_x - 2,
			$img_y - 2,
			$img_w + 4,
			$img_h + 4,
			$img_w,
			$img_h,
			$img_x,
			$img_y
		);
		$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
		$objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $w . ' /Height ' . $h . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $jpg ) . " >>\nstream\n" . $jpg . "\nendstream";

		$pdf  = "%PDF-1.4\n";
		$xref = array( 0 );
		foreach ( $objects as $i => $obj ) {
			$xref[] = strlen( $pdf );
			$pdf   .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$start = strlen( $pdf );
		$pdf  .= 'xref' . "\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf  .= "0000000000 65535 f \n";
		foreach ( array_slice( $xref, 1 ) as $off ) {
			$pdf .= sprintf( "%010d 00000 n \n", $off );
		}
		$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R >>' . "\n";
		$pdf .= 'startxref' . "\n" . $start . "\n%%EOF";
		return $pdf;
	}

	/**
	 * Escape PDF literal string.
	 */
	private static function pdf_escape( string $s ): string {
		$s = preg_replace( '/[^\x20-\x7E]/', '', $s );
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), (string) $s );
	}
}
