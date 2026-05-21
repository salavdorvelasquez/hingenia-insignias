<?php
/**
 * Generador de la imagen final de la insignia.
 *
 * Compone con GD: imagen base (PNG del curso) + nombre del estudiante (TTF,
 * ajustado a su caja) + QR de verificación. Guarda el PNG en
 * uploads/hi-insignias/{token}.png.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Generator {

	const SUBDIR = 'hi-insignias';

	/** ¿El servidor tiene lo necesario (GD + freetype)? */
	public static function gd_ready() {
		return function_exists( 'imagecreatetruecolor' )
			&& function_exists( 'imagettftext' )
			&& function_exists( 'imagecreatefromstring' );
	}

	/**
	 * Genera el PNG de la insignia.
	 *
	 * @param object $template     Fila de hi_badge_templates.
	 * @param string $student_name Nombre a estampar.
	 * @param string $verify_url   URL que codifica el QR.
	 * @param string $token        Token (para el nombre del archivo).
	 * @return array{success:bool,url?:string,path?:string,error?:string}
	 */
	public static function generate( $template, $student_name, $verify_url, $token ) {
		if ( ! self::gd_ready() ) {
			return array( 'success' => false, 'error' => 'El servidor no tiene la extensión GD con FreeType activa.' );
		}

		$im = self::load_base_image( $template );
		if ( ! $im ) {
			return array( 'success' => false, 'error' => 'No se pudo cargar la imagen base de la plantilla.' );
		}

		$w = imagesx( $im );
		$h = imagesy( $im );

		$layout = json_decode( $template->layout_json, true );
		if ( ! is_array( $layout ) ) {
			$layout = HI_Data::default_layout_json();
		}
		$cw = max( 1, (int) ( $layout['canvas_w'] ?? $w ) );
		$ch = max( 1, (int) ( $layout['canvas_h'] ?? $h ) );
		$sx = $w / $cw;
		$sy = $h / $ch;

		// Nombre (solo si está activado en la plantilla).
		if ( ! empty( $layout['name'] ) && ! empty( $layout['name']['enabled'] ) ) {
			self::draw_name( $im, $layout['name'], $student_name, $sx, $sy );
		}

		// QR.
		if ( ! empty( $layout['qr'] ) && ! empty( $layout['qr']['enabled'] ) && $verify_url ) {
			self::draw_qr( $im, $layout['qr'], $verify_url, $sx, $sy );
		}

		$saved = self::save_png( $im, $token );
		imagedestroy( $im );
		return $saved;
	}

	/* ---------------------------------------------------------------- */

	private static function load_base_image( $template ) {
		$data = '';

		// 1) Por attachment local.
		if ( ! empty( $template->png_attachment_id ) ) {
			$p = get_attached_file( (int) $template->png_attachment_id );
			if ( $p && file_exists( $p ) ) {
				$data = file_get_contents( $p );
			}
		}
		// 2) Por URL → ruta local.
		if ( '' === $data && ! empty( $template->png_url ) ) {
			$p = self::url_to_path( $template->png_url );
			if ( $p && file_exists( $p ) ) {
				$data = file_get_contents( $p );
			}
		}
		// 3) Descarga remota como último recurso.
		if ( '' === $data && ! empty( $template->png_url ) ) {
			$resp = wp_remote_get( $template->png_url, array( 'timeout' => 20 ) );
			if ( ! is_wp_error( $resp ) ) {
				$data = wp_remote_retrieve_body( $resp );
			}
		}
		if ( '' === $data ) {
			return null;
		}

		$im = @imagecreatefromstring( $data );
		if ( ! $im ) {
			return null;
		}
		imagealphablending( $im, true );
		imagesavealpha( $im, true );
		return $im;
	}

	private static function url_to_path( $url ) {
		$up = wp_upload_dir();
		if ( ! empty( $up['baseurl'] ) && 0 === strpos( $url, $up['baseurl'] ) ) {
			return $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) );
		}
		$home = home_url();
		if ( 0 === strpos( $url, $home ) ) {
			return ABSPATH . ltrim( substr( $url, strlen( $home ) ), '/' );
		}
		return '';
	}

	private static function hex_rgb( $hex, $fallback = array( 0, 0, 0 ) ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( strlen( $hex ) !== 6 ) {
			return $fallback;
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	private static function font_path( $font, $weight ) {
		$bold = (int) $weight >= 600;
		$dir  = HI_DIR . 'assets/fonts/';
		if ( 'serif' === $font ) {
			return $dir . ( $bold ? 'DejaVuSerif-Bold.ttf' : 'DejaVuSerif.ttf' );
		}
		return $dir . ( $bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf' );
	}

	private static function draw_name( $im, $cfg, $text, $sx, $sy ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return;
		}
		if ( ! empty( $cfg['uppercase'] ) ) {
			$text = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
		}

		$bx = (int) round( ( $cfg['x'] ?? 0 ) * $sx );
		$by = (int) round( ( $cfg['y'] ?? 0 ) * $sy );
		$bw = (int) round( ( $cfg['w'] ?? 100 ) * $sx );
		$bh = (int) round( ( $cfg['h'] ?? 50 ) * $sy );

		$font = self::font_path( $cfg['font'] ?? 'sans', $cfg['weight'] ?? 700 );
		if ( ! file_exists( $font ) ) {
			return;
		}

		$rgb   = self::hex_rgb( $cfg['color'] ?? '#1e293b', array( 30, 41, 59 ) );
		$color = imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] );

		// Tamaño base escalado; reducir si no cabe en el ancho de la caja.
		$fs = max( 6, (int) round( ( $cfg['size'] ?? 40 ) * $sy ) );
		$bb = imagettfbbox( $fs, 0, $font, $text );
		$tw = abs( $bb[2] - $bb[0] );
		$guard = 0;
		while ( $tw > $bw && $fs > 6 && $guard < 200 ) {
			$fs--;
			$bb = imagettfbbox( $fs, 0, $font, $text );
			$tw = abs( $bb[2] - $bb[0] );
			$guard++;
		}
		$th = abs( $bb[7] - $bb[1] );

		// Alineación horizontal dentro de la caja.
		$align = $cfg['align'] ?? 'center';
		if ( 'left' === $align ) {
			$tx = $bx - $bb[0];
		} elseif ( 'right' === $align ) {
			$tx = $bx + $bw - $tw - $bb[0];
		} else {
			$tx = $bx + (int) round( ( $bw - $tw ) / 2 ) - $bb[0];
		}
		// Centrado vertical: baseline.
		$ty = $by + (int) round( ( $bh - $th ) / 2 ) - $bb[7];

		imagettftext( $im, $fs, 0, $tx, $ty, $color, $font, $text );
	}

	private static function draw_qr( $im, $cfg, $url, $sx, $sy ) {
		$bx  = (int) round( ( $cfg['x'] ?? 0 ) * $sx );
		$by  = (int) round( ( $cfg['y'] ?? 0 ) * $sy );
		$bw  = (int) round( ( $cfg['w'] ?? 150 ) * $sx );
		$bh  = (int) round( ( $cfg['h'] ?? 150 ) * $sy );
		$box = min( $bw, $bh ); // cuadrado
		$fg  = self::hex_rgb( $cfg['fg'] ?? '#000000', array( 0, 0, 0 ) );
		$bg  = self::hex_rgb( $cfg['bg'] ?? '#ffffff', array( 255, 255, 255 ) );
		$mg  = (int) round( ( $cfg['margin'] ?? 8 ) * $sx );

		HI_QR::draw_gd( $im, $url, $bx, $by, $box, $fg, $bg, $mg, 'M' );
	}

	private static function save_png( $im, $token ) {
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . self::SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$file = trailingslashit( $dir ) . 'badge-' . sanitize_file_name( $token ) . '.png';
		$ok   = imagepng( $im, $file, 6 );
		if ( ! $ok ) {
			return array( 'success' => false, 'error' => 'No se pudo escribir el PNG en uploads.' );
		}
		$url = trailingslashit( $up['baseurl'] ) . self::SUBDIR . '/badge-' . sanitize_file_name( $token ) . '.png';
		return array( 'success' => true, 'url' => $url, 'path' => $file );
	}

	/** Borra el PNG físico de una emisión (insignia + QR standalone). */
	public static function delete_png( $token ) {
		$up   = wp_upload_dir();
		$base = trailingslashit( $up['basedir'] ) . self::SUBDIR . '/';
		foreach ( array( 'badge-', 'qr-' ) as $pre ) {
			$f = $base . $pre . sanitize_file_name( $token ) . '.png';
			if ( file_exists( $f ) ) {
				@unlink( $f );
			}
		}
	}

	/* ================================================================
	   QR standalone (para la lupa / ampliación)
	   ================================================================ */

	public static function qr_png_url( $token ) {
		$up = wp_upload_dir();
		return trailingslashit( $up['baseurl'] ) . self::SUBDIR . '/qr-' . sanitize_file_name( $token ) . '.png';
	}

	/** Genera el PNG del QR aislado (grande y nítido). Devuelve URL o ''. */
	public static function generate_qr_png( $token, $url, $size = 720 ) {
		if ( ! self::gd_ready() ) {
			return '';
		}
		$up  = wp_upload_dir();
		$dir = trailingslashit( $up['basedir'] ) . self::SUBDIR;
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$file  = trailingslashit( $dir ) . 'qr-' . sanitize_file_name( $token ) . '.png';
		$im    = imagecreatetruecolor( $size, $size );
		$white = imagecolorallocate( $im, 255, 255, 255 );
		imagefilledrectangle( $im, 0, 0, $size, $size, $white );
		HI_QR::draw_gd( $im, $url, 0, 0, $size, array( 17, 24, 39 ), array( 255, 255, 255 ), (int) round( $size * 0.06 ), 'M' );
		imagepng( $im, $file, 6 );
		imagedestroy( $im );
		return self::qr_png_url( $token );
	}

	/** Devuelve la URL del QR; lo genera si aún no existe (lazy, para emisiones viejas). */
	public static function ensure_qr_png( $token, $url ) {
		$up   = wp_upload_dir();
		$file = trailingslashit( $up['basedir'] ) . self::SUBDIR . '/qr-' . sanitize_file_name( $token ) . '.png';
		if ( file_exists( $file ) ) {
			return self::qr_png_url( $token );
		}
		return self::generate_qr_png( $token, $url );
	}
}
