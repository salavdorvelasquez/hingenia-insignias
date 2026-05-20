<?php
/**
 * Dashboard del plugin — vista resumen.
 *
 * @package HingeniaInsignias
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$total_emit       = HI_Data::count_certificates();
$total_students   = HI_Data::count_unique_students();
$total_templates  = HI_Data::count_templates();
$active_templates = HI_Data::count_templates( 1 );
$tutor_courses    = HI_Data::get_tutor_courses();
$total_courses    = count( $tutor_courses );
$courses_with_tpl = 0;
foreach ( $tutor_courses as $c ) {
	if ( HI_Data::get_template_by_course( $c['id'] ) ) {
		$courses_with_tpl++;
	}
}
$pct_coverage = $total_courses ? round( $courses_with_tpl / $total_courses * 100 ) : 0;

$recent = HI_Data::get_certificates( array( 'limit' => 8 ) );
$base   = admin_url( 'admin.php?page=' . HI_Admin::MENU_SLUG );
?>

<header class="hi-pageHead">
	<div>
		<h1 class="hi-pageHead__title">Dashboard</h1>
		<p class="hi-pageHead__sub">Resumen del sistema de insignias digitales.</p>
	</div>
	<div class="hi-pageHead__actions">
		<a href="<?php echo esc_url( $base . '-plantillas' ); ?>" class="hi-btn hi-btn--ghost"><?php echo HI_Icons::get( 'layers', 16 ); ?> Plantillas</a>
		<a href="<?php echo esc_url( $base . '-importar' ); ?>" class="hi-btn hi-btn--primary"><?php echo HI_Icons::get( 'send', 16 ); ?> Emitir nuevas</a>
	</div>
</header>

<section class="hi-stats">
	<div class="hi-stat hi-stat--blue">
		<div class="hi-stat__top"><?php echo HI_Icons::get( 'award', 18 ); ?><span class="hi-stat__label">Insignias emitidas</span></div>
		<div class="hi-stat__value"><?php echo number_format_i18n( $total_emit ); ?></div>
		<div class="hi-stat__hint">Total acumulado, vigentes (no revocadas).</div>
	</div>
	<div class="hi-stat hi-stat--violet">
		<div class="hi-stat__top"><?php echo HI_Icons::get( 'users', 18 ); ?><span class="hi-stat__label">Estudiantes con insignia</span></div>
		<div class="hi-stat__value"><?php echo number_format_i18n( $total_students ); ?></div>
		<div class="hi-stat__hint">Únicos, por usuario o correo.</div>
	</div>
	<div class="hi-stat hi-stat--green">
		<div class="hi-stat__top"><?php echo HI_Icons::get( 'layers', 18 ); ?><span class="hi-stat__label">Plantillas activas</span></div>
		<div class="hi-stat__value"><?php echo number_format_i18n( $active_templates ); ?><small> / <?php echo number_format_i18n( $total_templates ); ?></small></div>
		<div class="hi-stat__hint"><?php echo (int) $courses_with_tpl; ?> de <?php echo (int) $total_courses; ?> cursos con insignia (<?php echo (int) $pct_coverage; ?>%).</div>
	</div>
	<div class="hi-stat hi-stat--amber">
		<div class="hi-stat__top"><?php echo HI_Icons::get( 'percent', 18 ); ?><span class="hi-stat__label">Cobertura</span></div>
		<div class="hi-stat__value"><?php echo (int) $pct_coverage; ?><small>%</small></div>
		<div class="hi-bar"><span style="width:<?php echo (int) $pct_coverage; ?>%"></span></div>
	</div>
</section>

<section class="hi-grid">
	<article class="hi-card">
		<header class="hi-card__head">
			<h2>Cursos sin plantilla</h2>
			<a href="<?php echo esc_url( $base . '-plantillas' ); ?>" class="hi-link">Configurar <?php echo HI_Icons::get( 'arrow-right', 12 ); ?></a>
		</header>
		<?php
		$pendientes = array();
		foreach ( $tutor_courses as $c ) {
			if ( ! HI_Data::get_template_by_course( $c['id'] ) ) {
				$pendientes[] = $c;
			}
		}
		if ( empty( $pendientes ) ) : ?>
			<div class="hi-empty hi-empty--ok">
				<span class="hi-empty__ico"><?php echo HI_Icons::get( 'check', 28 ); ?></span>
				<p>Todos los cursos tienen plantilla activa.</p>
			</div>
		<?php else : ?>
			<ul class="hi-list">
				<?php foreach ( array_slice( $pendientes, 0, 8 ) as $c ) : ?>
					<li class="hi-list__row">
						<span class="hi-list__title"><?php echo esc_html( $c['title'] ); ?></span>
						<a href="<?php echo esc_url( add_query_arg( array( 'curso' => $c['id'] ), $base . '-plantillas' ) ); ?>" class="hi-btn hi-btn--xs"><?php echo HI_Icons::get( 'plus', 12 ); ?> Crear</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( count( $pendientes ) > 8 ) : ?>
				<p class="hi-card__foot">+ <?php echo (int) ( count( $pendientes ) - 8 ); ?> cursos más sin plantilla.</p>
			<?php endif; ?>
		<?php endif; ?>
	</article>

	<article class="hi-card">
		<header class="hi-card__head">
			<h2>Últimas emisiones</h2>
			<a href="<?php echo esc_url( $base . '-emisiones' ); ?>" class="hi-link">Ver todas <?php echo HI_Icons::get( 'arrow-right', 12 ); ?></a>
		</header>
		<?php if ( empty( $recent ) ) : ?>
			<div class="hi-empty">
				<span class="hi-empty__ico"><?php echo HI_Icons::get( 'inbox', 32 ); ?></span>
				<p>Aún no has emitido ninguna insignia.</p>
				<a href="<?php echo esc_url( $base . '-importar' ); ?>" class="hi-btn hi-btn--primary hi-btn--sm"><?php echo HI_Icons::get( 'send', 14 ); ?> Empezar a emitir</a>
			</div>
		<?php else : ?>
			<ul class="hi-feed">
				<?php foreach ( $recent as $r ) : ?>
					<li class="hi-feed__item">
						<div class="hi-feed__ava"><?php echo esc_html( strtoupper( mb_substr( $r->user_name, 0, 1 ) ) ?: '·' ); ?></div>
						<div class="hi-feed__body">
							<div class="hi-feed__name"><?php echo esc_html( $r->user_name ); ?></div>
							<div class="hi-feed__meta"><?php echo esc_html( $r->course_title ); ?></div>
						</div>
						<time class="hi-feed__time"><?php echo esc_html( human_time_diff( strtotime( $r->emitido_at ), current_time( 'timestamp' ) ) ); ?></time>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</article>
</section>

<section class="hi-cards-row">
	<div class="hi-help">
		<div class="hi-help__icon"><?php echo HI_Icons::get( 'zap', 24 ); ?></div>
		<div>
			<h3>Cómo funciona</h3>
			<ol>
				<li>Subes una imagen base por curso en <a href="<?php echo esc_url( $base . '-plantillas' ); ?>">Plantillas</a> y marcas dónde van el nombre y el QR.</li>
				<li>Importas estudiantes (CSV) o los emites manualmente desde <a href="<?php echo esc_url( $base . '-emisiones' ); ?>">Emisiones</a>.</li>
				<li>Cada estudiante recibe un PNG con su nombre y un QR único que apunta a <code>/insignia/&lt;token&gt;</code>.</li>
				<li>Su perfil público en <code>/insignias/&lt;usuario&gt;</code> reúne todas las insignias obtenidas.</li>
			</ol>
		</div>
	</div>
</section>
