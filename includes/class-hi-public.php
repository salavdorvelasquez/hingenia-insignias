<?php
/**
 * Páginas públicas del plugin (placeholder de Fase 5).
 *
 * - /insignias/{slug-usuario}  → galería de todas las insignias del estudiante.
 * - /insignia/{token}          → verificación + vista grande de una insignia.
 *
 * En esta fase 1 solo registramos las rewrite rules; las plantillas reales se
 * construyen en la Fase 5.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Public {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	public static function register_rewrite_rules() {
		$s = HI_Data::get_settings();
		$public_slug = trim( $s['public_slug'], '/' );
		$badge_slug  = trim( $s['badge_slug'], '/' );

		add_rewrite_rule(
			'^' . preg_quote( $public_slug, '/' ) . '/([^/]+)/?$',
			'index.php?hi_profile=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^' . preg_quote( $badge_slug, '/' ) . '/([^/]+)/?$',
			'index.php?hi_badge=$matches[1]',
			'top'
		);
	}

	public static function register_query_vars( $vars ) {
		$vars[] = 'hi_profile';
		$vars[] = 'hi_badge';
		return $vars;
	}

	public function maybe_render() {
		$badge   = get_query_var( 'hi_badge' );
		$profile = get_query_var( 'hi_profile' );

		if ( ! $badge && ! $profile ) {
			return;
		}

		// Placeholder simple — la plantilla bonita llega en Fase 5.
		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		echo '<!doctype html><meta charset="utf-8"><title>Insignia</title>';
		echo '<body style="font-family:system-ui;background:#0f172a;color:#e2e8f0;padding:60px;text-align:center;">';
		echo '<h1 style="font-size:32px;font-weight:800;">Insignia digital</h1>';
		echo '<p style="opacity:.7">Frontend en construcción (Fase 5).</p>';

		if ( $badge ) {
			$cert = HI_Data::get_certificate_by_token( $badge );
			if ( $cert ) {
				echo '<p style="margin-top:20px"><strong>' . esc_html( $cert->user_name ) . '</strong></p>';
				echo '<p style="opacity:.7">' . esc_html( $cert->course_title ) . '</p>';
				if ( $cert->png_url ) {
					echo '<img src="' . esc_url( $cert->png_url ) . '" style="max-width:380px;margin-top:20px" alt="">';
				}
			} else {
				echo '<p style="color:#f87171">Insignia no encontrada.</p>';
			}
		}
		if ( $profile ) {
			echo '<p>Perfil de estudiante: <code>' . esc_html( $profile ) . '</code></p>';
		}
		echo '</body>';
		exit;
	}
}
