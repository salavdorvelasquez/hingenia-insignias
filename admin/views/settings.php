<?php
/**
 * Pantalla Configuración — guarda vía admin-post.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$s       = HI_Data::get_settings();
$updated = isset( $_GET['updated'] );
?>
<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Configuración</h1>
		<p class="hi-pageHead__sub">URLs públicas, organización y la banda de Centro Autorizado que aparece en las páginas de verificación.</p>
	</div>
</header>

<?php if ( $updated ) : ?>
	<div class="hi-flash"><?php echo HI_Icons::get( 'check', 16 ); ?> Configuración guardada.</div>
<?php endif; ?>

<form class="hi-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="hi_save_settings">
	<?php wp_nonce_field( 'hi_settings' ); ?>

	<div class="hi-grid">
		<div class="hi-card">
			<h2 class="hi-card__title">URLs públicas</h2>
			<div class="hi-field">
				<label>Slug del perfil de estudiante</label>
				<div class="hi-input-prefix"><span><?php echo esc_html( home_url( '/' ) ); ?></span><input type="text" name="s[public_slug]" value="<?php echo esc_attr( $s['public_slug'] ); ?>"><span>/{usuario}</span></div>
			</div>
			<div class="hi-field">
				<label>Slug de verificación de insignia</label>
				<div class="hi-input-prefix"><span><?php echo esc_html( home_url( '/' ) ); ?></span><input type="text" name="s[badge_slug]" value="<?php echo esc_attr( $s['badge_slug'] ); ?>"><span>/{token}</span></div>
				<p class="hi-help-text">El QR de cada insignia apunta aquí.</p>
			</div>
		</div>

		<div class="hi-card">
			<h2 class="hi-card__title">Organización</h2>
			<div class="hi-field"><label>Nombre</label><input type="text" name="s[org_name]" value="<?php echo esc_attr( $s['org_name'] ); ?>"></div>
			<div class="hi-field"><label>URL pública</label><input type="url" name="s[org_url]" value="<?php echo esc_attr( $s['org_url'] ); ?>"></div>
		</div>
	</div>

	<div class="hi-grid">
		<div class="hi-card">
			<h2 class="hi-card__title">Banda de Centro Autorizado (ATC)</h2>
			<label class="hi-ctrl-check" style="margin-bottom:12px"><input type="checkbox" name="s[atc_enabled]" value="1" <?php checked( ! empty( $s['atc_enabled'] ) ); ?>> Mostrar la banda en las páginas públicas</label>
			<div class="hi-field"><label>Partner / Marca</label><input type="text" name="s[atc_partner]" value="<?php echo esc_attr( $s['atc_partner'] ); ?>" placeholder="Autodesk"></div>
			<div class="hi-field"><label>Etiqueta</label><input type="text" name="s[atc_label]" value="<?php echo esc_attr( $s['atc_label'] ); ?>" placeholder="Authorized Training Center"></div>
			<div class="hi-field"><label>Nota / descripción</label><textarea name="s[atc_note]" rows="3"><?php echo esc_textarea( $s['atc_note'] ); ?></textarea></div>
		</div>

		<div class="hi-card">
			<h2 class="hi-card__title">Perfil del estudiante</h2>
			<div class="hi-field"><label>Texto junto al nombre</label><input type="text" name="s[profile_tagline]" value="<?php echo esc_attr( $s['profile_tagline'] ); ?>"></div>
			<div class="hi-field"><label>Descripción del perfil</label><textarea name="s[profile_desc]" rows="4"><?php echo esc_textarea( $s['profile_desc'] ); ?></textarea></div>
			<label class="hi-ctrl-check"><input type="checkbox" name="s[show_why]" value="1" <?php checked( ! empty( $s['show_why'] ) ); ?>> Mostrar sección "Por qué importan"</label>
		</div>
	</div>

	<div class="hi-actions">
		<button type="submit" class="hi-btn hi-btn--primary"><?php echo HI_Icons::get( 'check', 15 ); ?> Guardar configuración</button>
	</div>
</form>
<?php
// estilo del flash (pequeño, inline para no tocar el css por algo tan chico).
?>
<style>
.hi-flash{display:inline-flex;align-items:center;gap:8px;background:#d1fae5;color:#047857;font-weight:600;font-size:13px;padding:10px 16px;border-radius:10px;margin-bottom:18px}
.hi-form .hi-card{margin-bottom:0}
</style>
