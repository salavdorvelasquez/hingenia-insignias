<?php
/**
 * Páginas públicas del plugin, integradas con el TEMA (header/footer oficiales).
 *
 * - /insignia/{token}          → verificación + descarga + compartir.
 * - /insignias/{slug-usuario}  → galería de todas las insignias del estudiante.
 *
 * Se renderiza el contenido dentro de get_header()/get_footer() del tema activo
 * para usar el encabezado y menú oficiales de Hingenia.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Public {

	private static $instance = null;

	private $mode  = '';      // 'badge' | 'profile' | '404'
	private $cert  = null;
	private $user  = null;
	private $title = '';
	private $og    = array();

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

		$s = HI_Data::get_settings();

		if ( $badge ) {
			$this->cert = HI_Data::get_certificate_by_token( $badge );
			if ( $this->cert ) {
				$this->mode  = 'badge';
				$this->title = 'Insignia de ' . $this->cert->user_name;
				$this->og    = array(
					'title' => $this->cert->user_name . ' — ' . $this->cert->course_title,
					'desc'  => 'Insignia digital verificada, emitida por ' . $s['org_name'] . '.',
					'image' => $this->cert->png_url,
					'url'   => HI_Data::badge_url( $this->cert->token ),
				);
			} else {
				$this->mode  = '404';
				$this->title = 'Insignia no encontrada';
			}
		} else {
			$user = null;
			if ( ctype_digit( (string) $profile ) ) {
				$user = get_user_by( 'id', (int) $profile );
			}
			if ( ! $user ) {
				$user = get_user_by( 'slug', $profile );
			}
			if ( $user ) {
				$this->user  = $user;
				$this->mode  = 'profile';
				$name        = $user->display_name ? $user->display_name : $user->user_login;
				$this->title = 'Insignias de ' . $name;
				$certs       = HI_Data::get_certificates_for_user( (int) $user->ID );
				$this->og    = array(
					'title' => $this->title,
					'desc'  => $name . ' tiene ' . count( $certs ) . ' insignia(s) digital(es) en ' . $s['org_name'] . '.',
					'image' => ( $certs && $certs[0]->png_url ) ? $certs[0]->png_url : '',
					'url'   => HI_Data::profile_url( (int) $user->ID ),
				);
			} else {
				$this->mode  = '404';
				$this->title = 'Perfil no encontrado';
			}
		}

		// Forzar 200 (la query principal sería 404) salvo cuando no se encontró.
		global $wp_query;
		if ( '404' === $this->mode ) {
			status_header( 404 );
		} else {
			status_header( 200 );
			$wp_query->is_404 = false;
		}

		// CSS público + título + OG en el <head> del tema.
		wp_enqueue_style( 'hi-public', HI_URL . 'assets/public.css', array(), ( @filemtime( HI_DIR . 'assets/public.css' ) ?: HI_VERSION ) );
		add_filter( 'pre_get_document_title', array( $this, 'filter_title' ), 99 );
		add_filter( 'document_title_parts', array( $this, 'filter_title_parts' ), 99 );
		add_action( 'wp_head', array( $this, 'print_og' ), 5 );
		add_filter( 'template_include', array( $this, 'load_template' ), 99 );
	}

	public function filter_title() {
		return $this->title . ' · ' . HI_Data::get_settings()['org_name'];
	}

	public function filter_title_parts( $parts ) {
		$parts['title'] = $this->title;
		return $parts;
	}

	public function print_og() {
		if ( empty( $this->og['image'] ) ) {
			return;
		}
		echo "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $this->og['title'] ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $this->og['desc'] ) . '">' . "\n";
		echo '<meta property="og:image" content="' . esc_url( $this->og['image'] ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $this->og['url'] ) . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}

	public function load_template( $template ) {
		return HI_DIR . 'templates/public-page.php';
	}

	/* ================================================================
	   Render del cuerpo (dentro del header/footer del tema)
	   ================================================================ */
	public function render_body() {
		echo '<div class="hp-wrap">';
		if ( 'badge' === $this->mode ) {
			$this->render_badge();
		} elseif ( 'profile' === $this->mode ) {
			$this->render_profile();
		} else {
			$this->render_notfound();
		}
		echo '</div>';
	}

	private function render_badge() {
		$cert       = $this->cert;
		$s          = HI_Data::get_settings();
		$org        = $s['org_name'];
		$fecha      = date_i18n( 'd \d\e F \d\e Y', strtotime( $cert->emitido_at ) );
		$verify_url = HI_Data::badge_url( $cert->token );
		$first      = explode( ' ', trim( $cert->user_name ) )[0];
		$has_prof   = (int) $cert->user_id > 0;
		$prof_url   = $has_prof ? HI_Data::profile_url( $cert->user_id ) : '';
		?>
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
		<script>
		document.addEventListener('click',function(e){var b=e.target.closest('.hp-copy');if(!b)return;navigator.clipboard.writeText(b.getAttribute('data-url')).then(function(){var t=b.textContent;b.textContent='¡Copiado!';setTimeout(function(){b.textContent=t;},1500);});});
		</script>
		<?php
	}

	private function render_profile() {
		$user  = $this->user;
		$certs = HI_Data::get_certificates_for_user( (int) $user->ID );
		$name  = $user->display_name ? $user->display_name : $user->user_login;
		$n     = count( $certs );
		?>
		<div class="hp-profile">
			<header class="hp-profile__head">
				<div class="hp-avatar"><?php echo esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></div>
				<div>
					<h1 class="hp-profile__name"><?php echo esc_html( $name ); ?></h1>
					<p class="hp-profile__count"><?php echo (int) $n; ?> insignia<?php echo 1 === $n ? '' : 's'; ?> digital<?php echo 1 === $n ? '' : 'es'; ?></p>
				</div>
			</header>

			<?php if ( empty( $certs ) ) : ?>
				<div class="hp-empty"><?php echo HI_Icons::get( 'award', 40 ); ?><p>Este estudiante aún no tiene insignias.</p></div>
			<?php else : ?>
				<div class="hp-grid">
					<?php foreach ( $certs as $c ) : ?>
						<a class="hp-card" href="<?php echo esc_url( HI_Data::badge_url( $c->token ) ); ?>">
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
		<?php
	}

	private function render_notfound() {
		?>
		<div class="hp-notfound">
			<span class="hp-notfound__ico"><?php echo HI_Icons::get( 'award', 44 ); ?></span>
			<h1><?php echo esc_html( $this->title ); ?></h1>
			<p>El enlace que abriste no corresponde a ninguna credencial vigente.</p>
		</div>
		<?php
	}
}
