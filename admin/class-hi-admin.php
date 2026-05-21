<?php
/**
 * Capa de administración: menú, encolado de assets propios y
 * registro de pantallas (cada vista vive en admin/views/*.php).
 *
 * El chrome de WordPress queda oculto dentro de las pantallas del plugin
 * (sin .wrap, sin h1 con dashicons) — el layout es 100% custom.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Admin {

	const MENU_SLUG = 'hi-insignias';

	private static $instance = null;
	private static $screens  = array();

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( $this, 'maybe_body_class' ) );

		// AJAX (admin).
		add_action( 'wp_ajax_hi_save_template',   array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_hi_delete_template', array( $this, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_hi_emit_single',     array( $this, 'ajax_emit_single' ) );
		add_action( 'wp_ajax_hi_emit_batch',      array( $this, 'ajax_emit_batch' ) );
		add_action( 'wp_ajax_hi_revoke_cert',     array( $this, 'ajax_revoke_cert' ) );
	}

	public function add_menu() {
		$cap = 'manage_options';

		$hook = add_menu_page(
			__( 'Insignias Digitales', 'hingenia-insignias' ),
			__( 'Insignias', 'hingenia-insignias' ),
			$cap,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			HI_Icons::menu_icon_uri(),
			31
		);
		self::$screens[] = $hook;

		$pages = array(
			array( self::MENU_SLUG,              'Dashboard',     'render_dashboard'   ),
			array( self::MENU_SLUG . '-plantillas', 'Plantillas', 'render_plantillas'  ),
			array( self::MENU_SLUG . '-emisiones',  'Emisiones',  'render_emisiones'   ),
			array( self::MENU_SLUG . '-importar',   'Importar CSV', 'render_importar' ),
			array( self::MENU_SLUG . '-settings',   'Configuración', 'render_settings' ),
		);
		foreach ( $pages as $p ) {
			$h = add_submenu_page(
				self::MENU_SLUG,
				$p[1],
				$p[1],
				$cap,
				$p[0],
				array( $this, $p[2] )
			);
			self::$screens[] = $h;
		}

		// Renombrar el primer submenú para que se llame "Dashboard" y no el título largo.
		global $submenu;
		if ( isset( $submenu[ self::MENU_SLUG ][0][0] ) ) {
			$submenu[ self::MENU_SLUG ][0][0] = __( 'Dashboard', 'hingenia-insignias' );
		}
	}

	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, self::$screens, true ) ) {
			return;
		}
		// Necesario para el selector de medios del editor de plantillas.
		wp_enqueue_media();
		// Cache buster por filemtime: cualquier cambio en el archivo invalida el cache del browser,
		// incluso si no bumpeamos HI_VERSION. Más robusto contra opcache / CDN / LiteSpeed.
		$css_ver = @filemtime( HI_DIR . 'assets/admin.css' ) ?: HI_VERSION;
		$js_ver  = @filemtime( HI_DIR . 'assets/admin.js' )  ?: HI_VERSION;
		wp_enqueue_style(
			'hi-admin',
			HI_URL . 'assets/admin.css',
			array(),
			$css_ver
		);
		wp_enqueue_script(
			'hi-admin',
			HI_URL . 'assets/admin.js',
			array( 'jquery' ),
			$js_ver,
			true
		);
		wp_localize_script( 'hi-admin', 'HI_ADMIN', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'hi_admin' ),
			'base'     => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
		) );
	}

	public function maybe_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && in_array( $screen->id, self::$screens, true ) ) {
			$classes .= ' hi-app ';
		}
		return $classes;
	}

	/* ====================================================================
	   RENDERS
	   ==================================================================== */

	private function shell_open( $current ) {
		$base = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$nav  = array(
			'dashboard'  => array( 'label' => 'Dashboard',     'icon' => 'dashboard', 'href' => $base ),
			'plantillas' => array( 'label' => 'Plantillas',    'icon' => 'layers',    'href' => $base . '-plantillas' ),
			'emisiones'  => array( 'label' => 'Emisiones',     'icon' => 'send',      'href' => $base . '-emisiones' ),
			'importar'   => array( 'label' => 'Importar CSV',  'icon' => 'upload',    'href' => $base . '-importar' ),
			'settings'   => array( 'label' => 'Configuración', 'icon' => 'settings',  'href' => $base . '-settings' ),
		);
		?>
		<div class="hi-shell">
			<aside class="hi-side">
				<div class="hi-brand">
					<div class="hi-brand__logo"><?php echo HI_Icons::get( 'award', 22 ); ?></div>
					<div>
						<div class="hi-brand__name">Insignias</div>
						<div class="hi-brand__tag">Hingenia · digital badges</div>
					</div>
				</div>
				<nav class="hi-nav">
					<?php foreach ( $nav as $key => $item ) :
						$active = ( $key === $current ) ? ' is-active' : ''; ?>
						<a href="<?php echo esc_url( $item['href'] ); ?>" class="hi-nav__item<?php echo $active; ?>">
							<span class="hi-nav__icon"><?php echo HI_Icons::get( $item['icon'], 18 ); ?></span>
							<span><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</nav>
				<div class="hi-side__foot">
					<span class="hi-pill">v<?php echo esc_html( HI_VERSION ); ?></span>
				</div>
			</aside>
			<main class="hi-main">
		<?php
	}

	private function shell_close() {
		echo '</main></div>';
	}

	public function render_dashboard() {
		$this->shell_open( 'dashboard' );
		require HI_DIR . 'admin/views/dashboard.php';
		$this->shell_close();
	}

	public function render_plantillas() {
		$this->shell_open( 'plantillas' );
		require HI_DIR . 'admin/views/plantillas.php';
		$this->shell_close();
	}

	public function render_emisiones() {
		$this->shell_open( 'emisiones' );
		require HI_DIR . 'admin/views/emisiones.php';
		$this->shell_close();
	}

	public function render_importar() {
		$this->shell_open( 'importar' );
		require HI_DIR . 'admin/views/importar.php';
		$this->shell_close();
	}

	public function render_settings() {
		$this->shell_open( 'settings' );
		require HI_DIR . 'admin/views/settings.php';
		$this->shell_close();
	}

	/* ====================================================================
	   AJAX
	   ==================================================================== */

	private function check_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'Sin permisos.' ), 403 );
		}
		check_ajax_referer( 'hi_admin', 'nonce' );
	}

	/** Guarda (crea o actualiza) la plantilla de un curso. */
	public function ajax_save_template() {
		$this->check_ajax();

		$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		if ( ! $course_id ) {
			wp_send_json_error( array( 'msg' => 'Falta el curso.' ) );
		}

		$png_id  = isset( $_POST['png_attachment_id'] ) ? (int) $_POST['png_attachment_id'] : 0;
		$png_url = isset( $_POST['png_url'] ) ? esc_url_raw( wp_unslash( $_POST['png_url'] ) ) : '';
		if ( ! $png_id && ! $png_url ) {
			wp_send_json_error( array( 'msg' => 'Sube primero la imagen base de la insignia.' ) );
		}

		$layout_raw = isset( $_POST['layout_json'] ) ? wp_unslash( $_POST['layout_json'] ) : '';
		$layout     = json_decode( $layout_raw, true );
		if ( ! is_array( $layout ) ) {
			$layout = HI_Data::default_layout_json();
		}
		$layout = $this->sanitize_layout( $layout );

		$nombre = isset( $_POST['nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['nombre'] ) ) : '';
		if ( '' === $nombre ) {
			$nombre = HI_Data::get_course_title( $course_id );
		}

		$existing = HI_Data::get_template_by_course( $course_id );
		$tpl_id   = $existing ? (int) $existing->id : 0;

		$id = HI_Data::save_template( array(
			'course_id'         => $course_id,
			'course_title'      => HI_Data::get_course_title( $course_id ),
			'nombre'            => $nombre,
			'png_attachment_id' => $png_id,
			'png_url'           => $png_url,
			'layout_json'       => $layout,
			'activa'            => 1,
		), $tpl_id );

		wp_send_json_success( array(
			'id'      => $id,
			'msg'     => $existing ? 'Plantilla actualizada.' : 'Plantilla creada.',
			'png_url' => $png_url,
		) );
	}

	public function ajax_delete_template() {
		$this->check_ajax();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			HI_Data::delete_template( $id );
		}
		wp_send_json_success( array( 'msg' => 'Plantilla eliminada.' ) );
	}

	/** Emite una insignia individual. */
	public function ajax_emit_single() {
		$this->check_ajax();
		$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$reissue   = ! empty( $_POST['reissue'] );

		$res = HI_Emit::issue( $course_id, $name, $email, 0, $reissue );
		if ( empty( $res['success'] ) ) {
			wp_send_json_error( array( 'msg' => $res['error'] ?? 'Error.' ) );
		}
		wp_send_json_success( $res );
	}

	/** Emite un lote (importación CSV). Recibe filas JSON: [{name,email}]. */
	public function ajax_emit_batch() {
		$this->check_ajax();
		$course_id = isset( $_POST['course_id'] ) ? (int) $_POST['course_id'] : 0;
		$reissue   = ! empty( $_POST['reissue'] );
		$rows_raw  = isset( $_POST['rows'] ) ? wp_unslash( $_POST['rows'] ) : '[]';
		$rows      = json_decode( $rows_raw, true );
		if ( ! is_array( $rows ) ) {
			wp_send_json_error( array( 'msg' => 'Datos inválidos.' ) );
		}

		$out = array( 'ok' => 0, 'skip' => 0, 'err' => 0, 'items' => array() );
		foreach ( $rows as $r ) {
			$name  = isset( $r['name'] ) ? sanitize_text_field( $r['name'] ) : '';
			$email = isset( $r['email'] ) ? sanitize_email( $r['email'] ) : '';
			$res   = HI_Emit::issue( $course_id, $name, $email, 0, $reissue );
			if ( empty( $res['success'] ) ) {
				$out['err']++;
				$out['items'][] = array( 'name' => $name, 'status' => 'err', 'msg' => $res['error'] ?? 'error' );
			} elseif ( ! empty( $res['skipped'] ) ) {
				$out['skip']++;
				$out['items'][] = array( 'name' => $name, 'status' => 'skip' );
			} else {
				$out['ok']++;
				$out['items'][] = array( 'name' => $name, 'status' => 'ok', 'url' => $res['url'] );
			}
		}
		wp_send_json_success( $out );
	}

	public function ajax_revoke_cert() {
		$this->check_ajax();
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $id ) {
			HI_Emit::revoke( $id );
		}
		wp_send_json_success( array( 'msg' => 'Insignia revocada.' ) );
	}

	/** Normaliza el layout recibido del editor a tipos/rangos seguros. */
	private function sanitize_layout( $l ) {
		$d = HI_Data::default_layout_json();
		$out = array(
			'canvas_w' => max( 1, (int) ( $l['canvas_w'] ?? $d['canvas_w'] ) ),
			'canvas_h' => max( 1, (int) ( $l['canvas_h'] ?? $d['canvas_h'] ) ),
			'name'     => array(
				'x'         => (int) ( $l['name']['x'] ?? $d['name']['x'] ),
				'y'         => (int) ( $l['name']['y'] ?? $d['name']['y'] ),
				'w'         => max( 1, (int) ( $l['name']['w'] ?? $d['name']['w'] ) ),
				'h'         => max( 1, (int) ( $l['name']['h'] ?? $d['name']['h'] ) ),
				'align'     => in_array( $l['name']['align'] ?? '', array( 'left', 'center', 'right' ), true ) ? $l['name']['align'] : 'center',
				'size'      => max( 6, min( 400, (int) ( $l['name']['size'] ?? $d['name']['size'] ) ) ),
				'color'     => sanitize_hex_color( $l['name']['color'] ?? '' ) ?: $d['name']['color'],
				'weight'    => in_array( (int) ( $l['name']['weight'] ?? 700 ), array( 400, 600, 700, 800 ), true ) ? (int) $l['name']['weight'] : 700,
				'uppercase' => ! empty( $l['name']['uppercase'] ),
				'font'      => in_array( $l['name']['font'] ?? '', array( 'sans', 'serif' ), true ) ? $l['name']['font'] : 'sans',
			),
			'qr'       => array(
				'x'      => (int) ( $l['qr']['x'] ?? $d['qr']['x'] ),
				'y'      => (int) ( $l['qr']['y'] ?? $d['qr']['y'] ),
				'w'      => max( 1, (int) ( $l['qr']['w'] ?? $d['qr']['w'] ) ),
				'h'      => max( 1, (int) ( $l['qr']['h'] ?? $d['qr']['h'] ) ),
				'fg'     => sanitize_hex_color( $l['qr']['fg'] ?? '' ) ?: '#000000',
				'bg'     => sanitize_hex_color( $l['qr']['bg'] ?? '' ) ?: '#ffffff',
				'margin' => max( 0, min( 64, (int) ( $l['qr']['margin'] ?? 8 ) ) ),
				'enabled'=> ! isset( $l['qr']['enabled'] ) || ! empty( $l['qr']['enabled'] ),
			),
		);
		return $out;
	}
}
