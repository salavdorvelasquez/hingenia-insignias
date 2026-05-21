<?php
/**
 * Wrapper sobre phpqrcode (vendored en includes/lib/phpqrcode.php).
 *
 * Expone la matriz de módulos para dibujarla nosotros con GD y colores
 * personalizados (phpqrcode::png solo hace blanco/negro).
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_QR {

	private static $loaded = false;

	private static function load_lib() {
		if ( self::$loaded ) {
			return;
		}
		require_once HI_DIR . 'includes/lib/phpqrcode.php';
		self::$loaded = true;
	}

	/**
	 * Matriz NxN de booleanos (true = módulo oscuro), sin quiet zone.
	 *
	 * @param string $text
	 * @param string $ecc  L | M | Q | H
	 * @return array
	 */
	public static function matrix( $text, $ecc = 'M' ) {
		self::load_lib();
		$levels = array(
			'L' => QR_ECLEVEL_L,
			'M' => QR_ECLEVEL_M,
			'Q' => QR_ECLEVEL_Q,
			'H' => QR_ECLEVEL_H,
		);
		$level = $levels[ strtoupper( $ecc ) ] ?? QR_ECLEVEL_M;

		// QRcode::text() devuelve un array de strings de '1'/'0', margin 0 = sin quiet zone.
		$rows = QRcode::text( $text, false, $level, 3, 0 );
		$m    = array();
		foreach ( (array) $rows as $row ) {
			$line = array();
			$len  = strlen( $row );
			for ( $i = 0; $i < $len; $i++ ) {
				$line[] = ( '1' === $row[ $i ] );
			}
			$m[] = $line;
		}
		return $m;
	}

	/**
	 * Dibuja el QR sobre una imagen GD existente, dentro de un cuadrado.
	 *
	 * @param resource|\GdImage $im        Imagen GD destino.
	 * @param string            $text      Contenido del QR.
	 * @param int               $x         Esquina sup-izq (px).
	 * @param int               $y         Esquina sup-izq (px).
	 * @param int               $box       Lado del cuadrado (px).
	 * @param array             $fg        [r,g,b] color de módulos.
	 * @param array             $bg        [r,g,b] color de fondo.
	 * @param int               $margin_px Padding blanco interno (px).
	 * @param string            $ecc
	 */
	public static function draw_gd( $im, $text, $x, $y, $box, $fg, $bg, $margin_px = 8, $ecc = 'M' ) {
		$mat = self::matrix( $text, $ecc );
		$n   = count( $mat );
		if ( $n < 1 ) {
			return;
		}

		$bg_col = imagecolorallocate( $im, $bg[0], $bg[1], $bg[2] );
		$fg_col = imagecolorallocate( $im, $fg[0], $fg[1], $fg[2] );

		// Fondo del recuadro completo.
		imagefilledrectangle( $im, $x, $y, $x + $box - 1, $y + $box - 1, $bg_col );

		$inner = $box - 2 * (int) $margin_px;
		if ( $inner < $n ) {
			$inner = $box; // si el margen no cabe, ignóralo.
		}
		$mpx = (int) floor( $inner / $n );
		if ( $mpx < 1 ) {
			$mpx = 1;
		}
		$qr_px = $mpx * $n;
		$ox    = $x + (int) floor( ( $box - $qr_px ) / 2 );
		$oy    = $y + (int) floor( ( $box - $qr_px ) / 2 );

		for ( $r = 0; $r < $n; $r++ ) {
			for ( $c = 0; $c < $n; $c++ ) {
				if ( $mat[ $r ][ $c ] ) {
					imagefilledrectangle(
						$im,
						$ox + $c * $mpx,
						$oy + $r * $mpx,
						$ox + ( $c + 1 ) * $mpx - 1,
						$oy + ( $r + 1 ) * $mpx - 1,
						$fg_col
					);
				}
			}
		}
	}
}
