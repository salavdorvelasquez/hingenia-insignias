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
		wp_enqueue_style(
			'hi-admin',
			HI_URL . 'assets/admin.css',
			array(),
			HI_VERSION
		);
		wp_enqueue_script(
			'hi-admin',
			HI_URL . 'assets/admin.js',
			array( 'jquery' ),
			HI_VERSION,
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
}
