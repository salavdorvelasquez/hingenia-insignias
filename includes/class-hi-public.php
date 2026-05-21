<?php
/**
 * Páginas públicas (standalone, sin tema) del plugin.
 *
 * - /insignia/{token}          → verificación + descarga + compartir.
 * - /insignias/{slug-usuario}  → galería de todas las insignias del estudiante.
 *
 * Se renderizan como documentos HTML completos para tener control total del
 * diseño (estilo credencial tipo Credly) y evitar conflictos con el tema.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Public {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	public static function register_rewrite_rules() {
		$s           = HI_Data::get_settings();
		$public_slug = trim( $s['public_slug'], '/' );
		$badge_slug  = trim( $s['badge_slug'], '/' );

		add_rewrite_rule( '^' . preg_quote( $public_slug, '/' ) . '/([^/]+)/?$', 'index.php?hi_profile=$matches[1]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $badge_slug, '/' ) . '/([^/]+)/?$', 'index.php?hi_badge=$matches[1]', 'top' );
	}

	public static function register_query_vars( $vars ) {
		$vars[] = 'hi_profile';
		$vars[] = 'hi_badge';
		return $vars;
	}

	public function maybe_render() {
		$badge   = get_query_var( 'hi_badge' );
		$profile = get_query_var( 'hi_profile' );
		if ( ! $badge && ! $profile ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		if ( $badge ) {
			$this->render_badge( $badge );
		} else {
			$this->render_profile( $profile );
		}
		exit;
	}

	/* ================================================================
	   Render: verificación de insignia
	   ================================================================ */
	private function render_badge( $token ) {
		$cert = HI_Data::get_certificate_by_token( $token );
		if ( ! $cert ) {
			status_header( 404 );
			$this->render_notfound( 'Insignia no encontrada', 'El código de esta insignia no existe o fue revocado.' );
			return;
		}
		status_header( 200 );

		$s          = HI_Data::get_settings();
		$org        = $s['org_name'];
		$fecha      = date_i18n( 'd \d\e F \d\e Y', strtotime( $cert->emitido_at ) );
		$verify_url = HI_Data::badge_url( $cert->token );
		$first      = explode( ' ', trim( $cert->user_name ) )[0];
		$has_prof   = (int) $cert->user_id > 0;
		$prof_url   = $has_prof ? HI_Data::profile_url( $cert->user_id ) : '';

		$og = array(
			'title' => $cert->user_name . ' — ' . $cert->course_title,
			'desc'  => 'Insignia digital verificada, emitida por ' . $org . '.',
			'image' => $cert->png_url,
			'url'   => $verify_url,
		);

		$this->head( 'Insignia de ' . $cert->user_name, $og );
		?>
		<main class="hp-wrap">
			<div class="hp-verify">
				<div class="hp-verify__media">
					<?php if ( $cert->png_url ) : ?>
						<img src="<?php echo esc_url( $cert->png_url ); ?>" alt="Insignia de <?php echo esc_attr( $cert->user_name ); ?>">
					<?php endif; ?>
				</div>
				<div class="hp-verify__info">
					<span class="hp-chip"><?php echo HI_Icons::get( 'check', 15 ); ?> Insignia verificada</span>
					<h1 class="hp-verify__name"><?php echo esc_html( $cert->user_name ); ?></h1>
					<p class="hp-verify__course"><?php echo esc_html( $cert->course_title ); ?></p>

					<dl class="hp-meta">
						<div><dt>Emitida por</dt><dd><?php echo esc_html( $org ); ?></dd></div>
						<div><dt>Fecha de emisión</dt><dd><?php echo esc_html( $fecha ); ?></dd></div>
						<div><dt>ID de credencial</dt><dd class="hp-mono"><?php echo esc_html( $cert->token ); ?></dd></div>
					</dl>

					<div class="hp-actions">
						<?php if ( $cert->png_url ) : ?>
							<a class="hp-btn hp-btn--primary" href="<?php echo esc_url( $cert->png_url ); ?>" download><?php echo HI_Icons::get( 'upload', 15 ); ?> Descargar</a>
						<?php endif; ?>
						<a class="hp-btn" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( $verify_url ); ?>">LinkedIn</a>
						<a class="hp-btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $cert->user_name . ' obtuvo la insignia "' . $cert->course_title . '". Verifícala: ' . $verify_url ); ?>">WhatsApp</a>
						<button class="hp-btn hp-copy" data-url="<?php echo esc_attr( $verify_url ); ?>">Copiar enlace</button>
					</div>

					<?php if ( $has_prof ) : ?>
						<a class="hp-profile-link" href="<?php echo esc_url( $prof_url ); ?>">Ver todas las insignias de <?php echo esc_html( $first ); ?> <?php echo HI_Icons::get( 'arrow-right', 13 ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</main>
		<?php
		$this->foot();
	}

	/* ================================================================
	   Render: galería del estudiante
	   ================================================================ */
	private function render_profile( $slug ) {
		// Resolver usuario: numérico = ID; si no, user_nicename.
		$user = null;
		if ( ctype_digit( (string) $slug ) ) {
			$user = get_user_by( 'id', (int) $slug );
		}
		if ( ! $user ) {
			$user = get_user_by( 'slug', $slug );
		}

		if ( ! $user ) {
			status_header( 404 );
			$this->render_notfound( 'Perfil no encontrado', 'No encontramos un estudiante con esa dirección.' );
			return;
		}
		status_header( 200 );

		$certs = HI_Data::get_certificates_for_user( (int) $user->ID );
		$s     = HI_Data::get_settings();
		$name  = $user->display_name ? $user->display_name : $user->user_login;

		$og = array(
			'title' => 'Insignias de ' . $name,
			'desc'  => $name . ' tiene ' . count( $certs ) . ' insignia(s) digital(es) en ' . $s['org_name'] . '.',
			'image' => ( $certs && $certs[0]->png_url ) ? $certs[0]->png_url : '',
			'url'   => HI_Data::profile_url( (int) $user->ID ),
		);

		$this->head( 'Insignias de ' . $name, $og );
		?>
		<main class="hp-wrap">
			<div class="hp-profile">
				<header class="hp-profile__head">
					<div class="hp-avatar"><?php echo esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></div>
					<div>
						<h1 class="hp-profile__name"><?php echo esc_html( $name ); ?></h1>
						<p class="hp-profile__count"><?php echo (int) count( $certs ); ?> insignia<?php echo count( $certs ) === 1 ? '' : 's'; ?> digital<?php echo count( $certs ) === 1 ? '' : 'es'; ?></p>
					</div>
				</header>

				<?php if ( empty( $certs ) ) : ?>
					<div class="hp-empty"><?php echo HI_Icons::get( 'award', 40 ); ?><p>Este estudiante aún no tiene insignias.</p></div>
				<?php else : ?>
					<div class="hp-grid">
						<?php foreach ( $certs as $c ) :
							$url = HI_Data::badge_url( $c->token ); ?>
							<a class="hp-card" href="<?php echo esc_url( $url ); ?>">
								<div class="hp-card__media">
									<?php if ( $c->png_url ) : ?>
										<img src="<?php echo esc_url( $c->png_url ); ?>" alt="" loading="lazy">
									<?php else : ?>
										<?php echo HI_Icons::get( 'award', 36 ); ?>
									<?php endif; ?>
								</div>
								<div class="hp-card__body">
									<h3><?php echo esc_html( $c->course_title ); ?></h3>
									<time><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $c->emitido_at ) ) ); ?></time>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</main>
		<?php
		$this->foot();
	}

	/* ================================================================
	   Shell HTML
	   ================================================================ */
	private function head( $title, $og = array() ) {
		$s   = HI_Data::get_settings();
		$css = HI_URL . 'assets/public.css?ver=' . ( @filemtime( HI_DIR . 'assets/public.css' ) ?: HI_VERSION );
		?><!doctype html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?> · <?php echo esc_html( $s['org_name'] ); ?></title>
	<?php if ( ! empty( $og['image'] ) ) : ?>
	<meta property="og:type" content="website">
	<meta property="og:title" content="<?php echo esc_attr( $og['title'] ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( $og['desc'] ); ?>">
	<meta property="og:image" content="<?php echo esc_url( $og['image'] ); ?>">
	<meta property="og:url" content="<?php echo esc_url( $og['url'] ); ?>">
	<meta name="twitter:card" content="summary_large_image">
	<?php endif; ?>
	<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
</head>
<body class="hp-body">
	<header class="hp-top">
		<a class="hp-brand" href="<?php echo esc_url( $s['org_url'] ); ?>">
			<span class="hp-brand__logo"><?php echo HI_Icons::get( 'award', 18 ); ?></span>
			<span class="hp-brand__name"><?php echo esc_html( $s['org_name'] ); ?></span>
		</a>
		<span class="hp-top__tag">Verificación de credenciales digitales</span>
	</header>
		<?php
	}

	private function foot() {
		$s = HI_Data::get_settings();
		?>
	<footer class="hp-foot">
		<p>Credenciales digitales emitidas por <a href="<?php echo esc_url( $s['org_url'] ); ?>"><?php echo esc_html( $s['org_name'] ); ?></a>.</p>
	</footer>
	<script>
	document.addEventListener('click', function(e){
		var b = e.target.closest('.hp-copy'); if(!b) return;
		var u = b.getAttribute('data-url');
		navigator.clipboard.writeText(u).then(function(){
			var t = b.textContent; b.textContent = '¡Copiado!';
			setTimeout(function(){ b.textContent = t; }, 1500);
		});
	});
	</script>
</body>
</html>
		<?php
	}

	private function render_notfound( $title, $msg ) {
		$this->head( $title );
		?>
		<main class="hp-wrap">
			<div class="hp-notfound">
				<span class="hp-notfound__ico"><?php echo HI_Icons::get( 'award', 44 ); ?></span>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $msg ); ?></p>
			</div>
		</main>
		<?php
		$this->foot();
	}
}
