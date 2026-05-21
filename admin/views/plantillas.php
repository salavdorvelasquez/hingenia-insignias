<?php
/**
 * Pantalla Plantillas — grid de cursos + editor visual.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$courses = HI_Data::get_tutor_courses();
$rows    = array();
foreach ( $courses as $c ) {
	$tpl = HI_Data::get_template_by_course( $c['id'] );
	$rows[] = array(
		'id'       => $c['id'],
		'title'    => $c['title'],
		'template' => $tpl,
	);
}
$con   = array_filter( $rows, fn( $r ) => ! empty( $r['template'] ) );
$sin   = array_filter( $rows, fn( $r ) => empty( $r['template'] ) );
$preselect = isset( $_GET['curso'] ) ? (int) $_GET['curso'] : 0;
$default_layout = wp_json_encode( HI_Data::default_layout_json() );
?>
<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Plantillas de insignia</h1>
		<p class="hi-pageHead__sub">Una plantilla por curso de Tutor LMS. Subes la imagen base y marcas dónde van el nombre del estudiante y el QR.</p>
	</div>
</header>

<div class="hi-tpl-toolbar">
	<div class="hi-search">
		<?php echo HI_Icons::get( 'dashboard', 15 ); ?>
		<input type="text" id="hi-tpl-search" placeholder="Buscar curso…" autocomplete="off">
	</div>
	<div class="hi-chips" id="hi-tpl-filter">
		<button class="hi-chip is-active" data-filter="all">Todos <span><?php echo count( $rows ); ?></span></button>
		<button class="hi-chip" data-filter="con">Con plantilla <span><?php echo count( $con ); ?></span></button>
		<button class="hi-chip" data-filter="sin">Sin plantilla <span><?php echo count( $sin ); ?></span></button>
	</div>
</div>

<?php if ( empty( $rows ) ) : ?>
	<div class="hi-empty hi-empty--big">
		<span class="hi-empty__ico"><?php echo HI_Icons::get( 'layers', 36 ); ?></span>
		<h3>No hay cursos en Tutor LMS</h3>
		<p>Crea cursos en Tutor LMS y aparecerán aquí para asignarles una insignia.</p>
	</div>
<?php else : ?>
	<div class="hi-tpl-grid" id="hi-tpl-grid">
		<?php foreach ( $rows as $r ) :
			$tpl       = $r['template'];
			$has       = ! empty( $tpl );
			$png       = $has ? $tpl->png_url : '';
			$layout    = $has && $tpl->layout_json ? $tpl->layout_json : $default_layout;
			$state     = $has ? 'con' : 'sin';
			?>
			<article class="hi-tpl-card" data-state="<?php echo esc_attr( $state ); ?>" data-title="<?php echo esc_attr( strtolower( $r['title'] ) ); ?>">
				<div class="hi-tpl-card__thumb <?php echo $has ? '' : 'is-empty'; ?>">
					<?php if ( $png ) : ?>
						<img src="<?php echo esc_url( $png ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<?php echo HI_Icons::get( 'image', 30 ); ?>
					<?php endif; ?>
					<span class="hi-tpl-card__badge <?php echo $has ? 'is-on' : 'is-off'; ?>">
						<?php echo $has ? 'Activa' : 'Sin plantilla'; ?>
					</span>
				</div>
				<div class="hi-tpl-card__body">
					<h3 class="hi-tpl-card__title"><?php echo esc_html( $r['title'] ); ?></h3>
					<div class="hi-tpl-card__actions">
						<button
							class="hi-btn hi-btn--sm <?php echo $has ? 'hi-btn--ghost' : 'hi-btn--primary'; ?> hi-tpl-edit"
							data-course="<?php echo (int) $r['id']; ?>"
							data-course-title="<?php echo esc_attr( $r['title'] ); ?>"
							data-tpl-id="<?php echo $has ? (int) $tpl->id : 0; ?>"
							data-png-url="<?php echo esc_attr( $png ); ?>"
							data-png-id="<?php echo $has ? (int) $tpl->png_attachment_id : 0; ?>"
							data-layout="<?php echo esc_attr( $layout ); ?>"
						>
							<?php echo $has ? HI_Icons::get( 'settings', 14 ) . ' Editar' : HI_Icons::get( 'plus', 14 ) . ' Crear'; ?>
						</button>
						<?php if ( $has ) : ?>
							<button class="hi-btn hi-btn--sm hi-btn--danger-ghost hi-tpl-delete" data-tpl-id="<?php echo (int) $tpl->id; ?>" title="Eliminar plantilla">
								<?php echo HI_Icons::get( 'check', 14 ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<div class="hi-tpl-noresults" id="hi-tpl-noresults" hidden>
		<p>No hay cursos que coincidan con la búsqueda.</p>
	</div>
<?php endif; ?>

<!-- ============ EDITOR MODAL ============ -->
<div class="hi-editor" id="hi-editor" hidden aria-hidden="true">
	<div class="hi-editor__backdrop" data-close></div>
	<div class="hi-editor__panel" role="dialog" aria-modal="true">
		<header class="hi-editor__head">
			<div>
				<h2 class="hi-editor__title">Editor de insignia</h2>
				<p class="hi-editor__course" id="hi-ed-course">—</p>
			</div>
			<button class="hi-editor__close" data-close aria-label="Cerrar">✕</button>
		</header>

		<div class="hi-editor__body">
			<!-- Lienzo -->
			<div class="hi-editor__stage-wrap">
				<div class="hi-editor__stage" id="hi-ed-stage">
					<div class="hi-editor__drop" id="hi-ed-drop">
						<span class="hi-empty__ico"><?php echo HI_Icons::get( 'image', 30 ); ?></span>
						<p>Sube la imagen base de la insignia (PNG, idealmente cuadrada y de alta resolución).</p>
						<button class="hi-btn hi-btn--primary hi-btn--sm" id="hi-ed-upload"><?php echo HI_Icons::get( 'upload', 14 ); ?> Subir imagen</button>
					</div>
					<img class="hi-editor__img" id="hi-ed-img" alt="" hidden>
					<!-- Caja NOMBRE -->
					<div class="hi-box hi-box--name" id="hi-box-name" hidden>
						<span class="hi-box__label">Nombre</span>
						<span class="hi-box__text" id="hi-box-name-text">NOMBRE APELLIDO</span>
						<span class="hi-box__handle" data-resize></span>
					</div>
					<!-- Caja QR -->
					<div class="hi-box hi-box--qr" id="hi-box-qr" hidden>
						<span class="hi-box__label">QR</span>
						<span class="hi-box__qr" id="hi-box-qr-grid"></span>
						<span class="hi-box__handle" data-resize></span>
					</div>
				</div>
			</div>

			<!-- Controles -->
			<aside class="hi-editor__controls">
				<div class="hi-ctrl-group">
					<div class="hi-ctrl-head">
						<span><?php echo HI_Icons::get( 'image', 14 ); ?> Imagen base</span>
						<button class="hi-btn hi-btn--xs" id="hi-ed-change" hidden>Cambiar</button>
					</div>
					<p class="hi-help-text" id="hi-ed-imgmeta">Ninguna imagen aún.</p>
				</div>

				<div class="hi-ctrl-group" id="hi-ctrl-name" hidden>
					<div class="hi-ctrl-head"><span><?php echo HI_Icons::get( 'user', 14 ); ?> Nombre del estudiante</span></div>
					<label class="hi-ctrl-row">
						<span>Tamaño</span>
						<input type="range" id="hi-name-size" min="12" max="160" step="1">
						<output id="hi-name-size-out">46</output>
					</label>
					<label class="hi-ctrl-row">
						<span>Color</span>
						<input type="color" id="hi-name-color" value="#1e293b">
					</label>
					<label class="hi-ctrl-row">
						<span>Alineación</span>
						<select id="hi-name-align">
							<option value="left">Izquierda</option>
							<option value="center" selected>Centro</option>
							<option value="right">Derecha</option>
						</select>
					</label>
					<label class="hi-ctrl-row">
						<span>Grosor</span>
						<select id="hi-name-weight">
							<option value="400">Normal</option>
							<option value="600">Semibold</option>
							<option value="700" selected>Bold</option>
							<option value="800">Extra bold</option>
						</select>
					</label>
					<label class="hi-ctrl-row">
						<span>Fuente</span>
						<select id="hi-name-font">
							<option value="sans" selected>Sans (moderna)</option>
							<option value="serif">Serif (clásica)</option>
						</select>
					</label>
					<label class="hi-ctrl-check">
						<input type="checkbox" id="hi-name-upper" checked>
						<span>MAYÚSCULAS</span>
					</label>
				</div>

				<div class="hi-ctrl-group" id="hi-ctrl-qr" hidden>
					<div class="hi-ctrl-head">
						<span><?php echo HI_Icons::get( 'qr', 14 ); ?> Código QR</span>
						<label class="hi-switch">
							<input type="checkbox" id="hi-qr-enabled" checked>
							<span class="hi-switch__track"></span>
						</label>
					</div>
					<label class="hi-ctrl-row">
						<span>Color</span>
						<input type="color" id="hi-qr-fg" value="#000000">
					</label>
					<label class="hi-ctrl-row">
						<span>Fondo</span>
						<input type="color" id="hi-qr-bg" value="#ffffff">
					</label>
					<p class="hi-help-text">Arrastra la caja QR en el lienzo para moverla y usa la esquina para cambiar el tamaño.</p>
				</div>
			</aside>
		</div>

		<footer class="hi-editor__foot">
			<span class="hi-editor__status" id="hi-ed-status"></span>
			<div class="hi-editor__foot-actions">
				<button class="hi-btn hi-btn--ghost" data-close>Cancelar</button>
				<button class="hi-btn hi-btn--primary" id="hi-ed-save" disabled><?php echo HI_Icons::get( 'check', 14 ); ?> Guardar plantilla</button>
			</div>
		</footer>
	</div>
</div>

<script type="text/template" id="hi-default-layout"><?php echo $default_layout; ?></script>
<?php if ( $preselect ) : ?>
<script>window.HI_PRESELECT_COURSE = <?php echo (int) $preselect; ?>;</script>
<?php endif; ?>
