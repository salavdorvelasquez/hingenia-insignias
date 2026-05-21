<?php
/**
 * Plantilla pública: usa el header/footer del tema activo (Hingenia) y
 * renderiza el contenido del plugin (verificación o galería) en medio.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

echo '<main class="hp-main">';
HI_Public::get_instance()->render_body();
echo '</main>';

get_footer();
