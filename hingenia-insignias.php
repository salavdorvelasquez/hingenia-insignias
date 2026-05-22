<?php
/**
 * Plugin Name: Hingenia Insignias Digitales
 * Plugin URI: https://hingenia.com
 * Description: Sistema de insignias digitales (badges) por curso de Tutor LMS. Sube una insignia base por curso, emite manualmente o por importación CSV, cada insignia lleva un QR único de verificación y un perfil público por estudiante con todas sus insignias.
 * Version: 0.9.1
 * Author: Hingenia
 * Text Domain: hingenia-insignias
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: Proprietary
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HI_VERSION',  '0.9.1' );
define( 'HI_DIR',      plugin_dir_path( __FILE__ ) );
define( 'HI_URL',      plugin_dir_url( __FILE__ ) );
define( 'HI_BASENAME', plugin_basename( __FILE__ ) );

require_once HI_DIR . 'includes/class-hi-icons.php';
require_once HI_DIR . 'includes/class-hi-data.php';
require_once HI_DIR . 'includes/class-hi-qr.php';
require_once HI_DIR . 'includes/class-hi-generator.php';
require_once HI_DIR . 'includes/class-hi-emit.php';
require_once HI_DIR . 'includes/class-hi-public.php';

if ( is_admin() ) {
	require_once HI_DIR . 'admin/class-hi-admin.php';
}

add_action( 'plugins_loaded', function () {
	HI_Data::boot();
	HI_Public::get_instance();
	if ( is_admin() ) {
		HI_Admin::get_instance();
	}
} );

register_activation_hook( __FILE__, function () {
	require_once HI_DIR . 'includes/class-hi-data.php';
	HI_Data::create_tables();
	update_option( HI_Data::OPT_DB_VER, HI_Data::DB_VERSION );
	// Asegurar reglas de rewrite para URLs públicas.
	if ( class_exists( 'HI_Public' ) ) {
		HI_Public::register_rewrite_rules();
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
