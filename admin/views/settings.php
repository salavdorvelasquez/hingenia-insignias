<?php
/**
 * Pantalla Configuración — placeholder.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s = HI_Data::get_settings();
?>
<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Configuración</h1>
		<p class="hi-pageHead__sub">URLs públicas, datos de la organización y opciones del QR.</p>
	</div>
</header>

<form class="hi-form" method="post" action="">
	<div class="hi-grid">
		<div class="hi-card">
			<h2 class="hi-card__title">URLs públicas</h2>
			<div class="hi-field">
				<label>Slug del perfil de estudiante</label>
				<div class="hi-input-prefix"><span><?php echo esc_html( home_url( '/' ) ); ?></span><input type="text" name="public_slug" value="<?php echo esc_attr( $s['public_slug'] ); ?>"><span>/{usuario}</span></div>
				<p class="hi-help-text">Ejemplo: <code><?php echo esc_html( HI_Data::profile_url( 'leoncio' ) ); ?></code></p>
			</div>
			<div class="hi-field">
				<label>Slug de verificación de insignia</label>
				<div class="hi-input-prefix"><span><?php echo esc_html( home_url( '/' ) ); ?></span><input type="text" name="badge_slug" value="<?php echo esc_attr( $s['badge_slug'] ); ?>"><span>/{token}</span></div>
				<p class="hi-help-text">El QR de cada insignia apunta a esta URL.</p>
			</div>
		</div>
		<div class="hi-card">
			<h2 class="hi-card__title">Organización</h2>
			<div class="hi-field">
				<label>Nombre de la organización</label>
				<input type="text" name="org_name" value="<?php echo esc_attr( $s['org_name'] ); ?>">
			</div>
			<div class="hi-field">
				<label>URL pública</label>
				<input type="url" name="org_url" value="<?php echo esc_attr( $s['org_url'] ); ?>">
			</div>
		</div>
	</div>
	<div class="hi-actions">
		<button type="submit" class="hi-btn hi-btn--primary" disabled>Guardar (próxima fase)</button>
		<span class="hi-help-text">El submit se activa en la siguiente fase.</span>
	</div>
</form>
