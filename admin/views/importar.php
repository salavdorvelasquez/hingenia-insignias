<?php
/**
 * Pantalla Importar CSV — placeholder de Fase 4.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Importar estudiantes</h1>
		<p class="hi-pageHead__sub">Subes un CSV con nombre, correo y curso. El plugin emite la insignia a cada uno (si el correo ya existe como usuario WP se vincula).</p>
	</div>
</header>

<div class="hi-empty hi-empty--big">
	<span class="hi-empty__ico"><?php echo HI_Icons::get( 'upload', 36 ); ?></span>
	<h3>Importador CSV</h3>
	<p>En la siguiente fase: drag-and-drop del CSV, preview de filas, matching por correo y emisión masiva con barra de progreso.</p>
</div>
