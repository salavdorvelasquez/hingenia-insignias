<?php
/**
 * Pantalla Emisiones — listado + emisión manual individual.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$certs    = HI_Data::get_certificates( array( 'limit' => 2000 ) );
$courses  = HI_Data::get_tutor_courses();
$with_tpl = array();
foreach ( $courses as $c ) {
	if ( HI_Data::get_template_by_course( $c['id'] ) ) {
		$with_tpl[] = $c;
	}
}
$base = admin_url( 'admin.php?page=' . HI_Admin::MENU_SLUG );
?>
<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Emisiones</h1>
		<p class="hi-pageHead__sub">Todas las insignias emitidas a estudiantes. Emite una individual o importa muchas por CSV.</p>
	</div>
	<div class="hi-pageHead__actions">
		<a href="<?php echo esc_url( $base . '-importar' ); ?>" class="hi-btn hi-btn--ghost"><?php echo HI_Icons::get( 'upload', 16 ); ?> Importar CSV</a>
		<button class="hi-btn hi-btn--primary" id="hi-emit-open" <?php echo empty( $with_tpl ) ? 'disabled title="Crea una plantilla primero"' : ''; ?>><?php echo HI_Icons::get( 'send', 16 ); ?> Emitir insignia</button>
	</div>
</header>

<?php if ( empty( $with_tpl ) ) : ?>
	<div class="hi-empty hi-empty--big">
		<span class="hi-empty__ico"><?php echo HI_Icons::get( 'layers', 36 ); ?></span>
		<h3>Primero crea una plantilla</h3>
		<p>Para emitir insignias necesitas al menos un curso con plantilla activa. Ve a <a href="<?php echo esc_url( $base . '-plantillas' ); ?>">Plantillas</a>.</p>
	</div>
<?php else : ?>

<div class="hi-tpl-toolbar">
	<div class="hi-search">
		<?php echo HI_Icons::get( 'dashboard', 15 ); ?>
		<input type="text" id="hi-em-search" placeholder="Buscar por nombre o correo…" autocomplete="off">
	</div>
	<select id="hi-em-course" class="hi-select">
		<option value="">Todos los cursos</option>
		<?php foreach ( $courses as $c ) : ?>
			<option value="<?php echo (int) $c['id']; ?>"><?php echo esc_html( $c['title'] ); ?></option>
		<?php endforeach; ?>
	</select>
</div>

<?php if ( empty( $certs ) ) : ?>
	<div class="hi-empty hi-empty--big">
		<span class="hi-empty__ico"><?php echo HI_Icons::get( 'send', 36 ); ?></span>
		<h3>Aún no hay insignias emitidas</h3>
		<p>Dale a "Emitir insignia" para crear la primera, o importa un CSV con tus estudiantes.</p>
	</div>
<?php else : ?>
	<div class="hi-table-wrap">
		<table class="hi-table" id="hi-em-table">
			<thead>
				<tr>
					<th>Estudiante</th>
					<th>Curso</th>
					<th>Emitida</th>
					<th>Insignia</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $certs as $r ) :
					$verify = HI_Data::badge_url( $r->token ); ?>
					<tr data-search="<?php echo esc_attr( strtolower( $r->user_name . ' ' . $r->user_email ) ); ?>" data-course="<?php echo (int) $r->course_id; ?>">
						<td>
							<div class="hi-cell-user">
								<span class="hi-feed__ava"><?php echo esc_html( strtoupper( mb_substr( $r->user_name, 0, 1 ) ) ?: '·' ); ?></span>
								<div>
									<div class="hi-cell-name"><?php echo esc_html( $r->user_name ); ?></div>
									<div class="hi-cell-mail"><?php echo $r->user_email ? esc_html( $r->user_email ) : '<span class="hi-muted">sin correo</span>'; ?></div>
								</div>
							</div>
						</td>
						<td><?php echo esc_html( $r->course_title ); ?></td>
						<td><span class="hi-muted"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r->emitido_at ) ) ); ?></span></td>
						<td>
							<?php if ( $r->png_url ) : ?>
								<a href="<?php echo esc_url( $r->png_url ); ?>" target="_blank" rel="noopener" class="hi-thumb-link" title="Ver PNG">
									<img src="<?php echo esc_url( $r->png_url ); ?>" alt="" loading="lazy">
								</a>
							<?php else : ?>
								<span class="hi-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="hi-cell-actions">
							<a href="<?php echo esc_url( $verify ); ?>" target="_blank" rel="noopener" class="hi-btn hi-btn--xs" title="Página de verificación">Ver</a>
							<?php if ( $r->png_url ) : ?>
								<a href="<?php echo esc_url( $r->png_url ); ?>" download class="hi-btn hi-btn--xs">Descargar</a>
							<?php endif; ?>
							<button class="hi-btn hi-btn--xs hi-btn--danger-ghost hi-em-revoke" data-id="<?php echo (int) $r->id; ?>" title="Revocar">Revocar</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<div class="hi-tpl-noresults" id="hi-em-noresults" hidden><p>Sin resultados.</p></div>
	</div>
<?php endif; ?>

<!-- Modal emitir individual -->
<div class="hi-editor" id="hi-emit-modal" hidden aria-hidden="true">
	<div class="hi-editor__backdrop" data-close></div>
	<div class="hi-editor__panel hi-editor__panel--sm" role="dialog" aria-modal="true">
		<header class="hi-editor__head">
			<div>
				<h2 class="hi-editor__title">Emitir insignia</h2>
				<p class="hi-editor__course">Genera una insignia para un estudiante.</p>
			</div>
			<button class="hi-editor__close" data-close aria-label="Cerrar">✕</button>
		</header>
		<div class="hi-emit-form">
			<div class="hi-field">
				<label>Curso</label>
				<select id="hi-emit-course">
					<?php foreach ( $with_tpl as $c ) : ?>
						<option value="<?php echo (int) $c['id']; ?>"><?php echo esc_html( $c['title'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="hi-help-text">Solo aparecen cursos con plantilla activa.</p>
			</div>
			<div class="hi-field">
				<label>Nombre del estudiante *</label>
				<input type="text" id="hi-emit-name" placeholder="Ej. María Fernanda López">
			</div>
			<div class="hi-field">
				<label>Correo (opcional)</label>
				<input type="email" id="hi-emit-email" placeholder="estudiante@correo.com">
				<p class="hi-help-text">Si coincide con un usuario de WordPress, la insignia se vincula a su cuenta.</p>
			</div>
		</div>
		<footer class="hi-editor__foot">
			<span class="hi-editor__status" id="hi-emit-status"></span>
			<div class="hi-editor__foot-actions">
				<button class="hi-btn hi-btn--ghost" data-close>Cancelar</button>
				<button class="hi-btn hi-btn--primary" id="hi-emit-go"><?php echo HI_Icons::get( 'send', 14 ); ?> Emitir</button>
			</div>
		</footer>
	</div>
</div>

<?php endif; ?>
