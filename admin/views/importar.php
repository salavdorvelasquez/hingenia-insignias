<?php
/**
 * Pantalla Importar CSV — emisión masiva.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
		<h1 class="hi-pageHead__title">Importar estudiantes</h1>
		<p class="hi-pageHead__sub">Sube un CSV con los estudiantes y emite las insignias en lote. Columnas: <code>nombre, correo</code> (el correo es opcional).</p>
	</div>
</header>

<?php if ( empty( $with_tpl ) ) : ?>
	<div class="hi-empty hi-empty--big">
		<span class="hi-empty__ico"><?php echo HI_Icons::get( 'layers', 36 ); ?></span>
		<h3>Primero crea una plantilla</h3>
		<p>Necesitas un curso con plantilla activa para importar. Ve a <a href="<?php echo esc_url( $base . '-plantillas' ); ?>">Plantillas</a>.</p>
	</div>
<?php else : ?>

<div class="hi-import">
	<!-- Paso 1: curso -->
	<div class="hi-card hi-import__step">
		<div class="hi-import__num">1</div>
		<div class="hi-import__body">
			<h2 class="hi-card__title">Elige el curso</h2>
			<select id="hi-imp-course" class="hi-select hi-csearch">
				<?php foreach ( $with_tpl as $c ) : ?>
					<option value="<?php echo (int) $c['id']; ?>"><?php echo esc_html( $c['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<!-- Paso 2: archivo -->
	<div class="hi-card hi-import__step">
		<div class="hi-import__num">2</div>
		<div class="hi-import__body">
			<h2 class="hi-card__title">Sube el CSV o pega los datos</h2>
			<div class="hi-drop" id="hi-imp-drop">
				<?php echo HI_Icons::get( 'upload', 28 ); ?>
				<p>Arrastra el archivo .csv aquí o</p>
				<button class="hi-btn hi-btn--ghost hi-btn--sm" id="hi-imp-browse">Elegir archivo</button>
				<input type="file" id="hi-imp-file" accept=".csv,text/csv" hidden>
			</div>
			<div class="hi-import__or">o pega aquí (una persona por línea: <code>nombre,correo</code>)</div>
			<textarea id="hi-imp-paste" class="hi-textarea" rows="5" placeholder="María López,maria@correo.com&#10;Juan Pérez,juan@correo.com&#10;Ana Torres"></textarea>
			<div class="hi-import__opts">
				<label class="hi-ctrl-check"><input type="checkbox" id="hi-imp-header"> La primera fila son encabezados (ignorar)</label>
				<label class="hi-ctrl-check"><input type="checkbox" id="hi-imp-reissue"> Reemitir si ya tiene insignia</label>
			</div>
			<button class="hi-btn hi-btn--ghost hi-btn--sm" id="hi-imp-preview"><?php echo HI_Icons::get( 'dashboard', 14 ); ?> Previsualizar</button>
		</div>
	</div>

	<!-- Paso 3: preview + emitir -->
	<div class="hi-card hi-import__step" id="hi-imp-step3" hidden>
		<div class="hi-import__num">3</div>
		<div class="hi-import__body">
			<h2 class="hi-card__title">Revisa y emite <span id="hi-imp-count" class="hi-pill"></span></h2>
			<div class="hi-table-wrap hi-import__preview">
				<table class="hi-table">
					<thead><tr><th>#</th><th>Nombre</th><th>Correo</th><th>Estado</th></tr></thead>
					<tbody id="hi-imp-tbody"></tbody>
				</table>
			</div>
			<div class="hi-import__progress" id="hi-imp-progress" hidden>
				<div class="hi-bar"><span id="hi-imp-bar" style="width:0%"></span></div>
				<p class="hi-help-text" id="hi-imp-progress-txt">Emitiendo…</p>
			</div>
			<div class="hi-import__actions">
				<button class="hi-btn hi-btn--primary" id="hi-imp-emit"><?php echo HI_Icons::get( 'send', 14 ); ?> Emitir todas</button>
				<div class="hi-import__summary" id="hi-imp-summary" hidden></div>
			</div>
		</div>
	</div>
</div>

<?php endif; ?>
