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
	private $mine  = false;   // true cuando es la página "Mis insignias" del usuario logueado

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 11 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		// Búsqueda pública por correo (página /insignias).
		add_action( 'wp_ajax_hi_search_badges', array( $this, 'ajax_search' ) );
		add_action( 'wp_ajax_nopriv_hi_search_badges', array( $this, 'ajax_search' ) );
	}

	public static function register_rewrite_rules() {
		$s           = HI_Data::get_settings();
		$public_slug = trim( $s['public_slug'], '/' );
		$badge_slug  = trim( $s['badge_slug'], '/' );

		// Base /insignias → buscador. Debe ir antes que la regla con segmento.
		add_rewrite_rule( '^' . preg_quote( $public_slug, '/' ) . '/?$', 'index.php?hi_search=1', 'top' );
		add_rewrite_rule( '^' . preg_quote( $public_slug, '/' ) . '/([^/]+)/?$', 'index.php?hi_profile=$matches[1]', 'top' );
		add_rewrite_rule( '^' . preg_quote( $badge_slug, '/' ) . '/([^/]+)/?$', 'index.php?hi_badge=$matches[1]', 'top' );
	}

	/** Flush una sola vez por versión (al actualizar el plugin se añaden reglas nuevas). */
	public static function maybe_flush_rewrite() {
		if ( get_option( 'hi_rewrite_v' ) !== HI_VERSION ) {
			self::register_rewrite_rules();
			flush_rewrite_rules();
			update_option( 'hi_rewrite_v', HI_VERSION );
		}
	}

	public static function register_query_vars( $vars ) {
		$vars[] = 'hi_profile';
		$vars[] = 'hi_badge';
		$vars[] = 'hi_search';
		return $vars;
	}

	public function maybe_render() {
		$badge   = get_query_var( 'hi_badge' );
		$profile = get_query_var( 'hi_profile' );
		$search  = get_query_var( 'hi_search' );
		if ( ! $badge && ! $profile && ! $search ) {
			return;
		}

		$s = HI_Data::get_settings();

		if ( $search ) {
			$this->mode  = 'search';
			$this->title = 'Verifica una insignia';
		} elseif ( $badge ) {
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
		} elseif ( in_array( strtolower( trim( (string) $profile ) ), array( 'mis-insignias', 'mis-insignia' ), true ) ) {
			// Palabra reservada → "Mis insignias" del usuario logueado.
			if ( ! is_user_logged_in() ) {
				wp_safe_redirect( home_url( '/login?redirect_to=' . rawurlencode( HI_Data::mine_url() ) ) );
				exit;
			}
			$this->user  = wp_get_current_user();
			$this->mine  = true;
			$this->mode  = 'profile';
			$this->title = __( 'Mis insignias', 'hingenia-insignias' );
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

		// La página personal "Mis insignias" no debe indexarse.
		if ( $this->mine ) {
			add_action( 'wp_head', function () { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }, 1 );
		}
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
		if ( 'search' === $this->mode ) {
			$this->render_search();
			return;
		}
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

	/* ================================================================
	   Búsqueda pública por correo (página /insignias)
	   ================================================================ */
	public function ajax_search() {
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			wp_send_json_error( array( 'msg' => 'Correo inválido.' ) );
		}
		$certs = HI_Data::get_certificates_for_email( $email );
		if ( empty( $certs ) ) {
			wp_send_json_success( array( 'found' => false ) );
		}
		$user    = get_user_by( 'email', $email );
		$name    = ( $user && $user->display_name ) ? $user->display_name : $certs[0]->user_name;
		$profile = ( $user ) ? HI_Data::profile_url( (int) $user->ID ) : '';
		$badges  = array();
		foreach ( $certs as $c ) {
			$badges[] = array(
				'title' => $c->course_title,
				'date'  => date_i18n( 'd M Y', strtotime( $c->emitido_at ) ),
				'url'   => HI_Data::badge_url( $c->token ),
				'png'   => $c->png_url,
			);
		}
		wp_send_json_success( array(
			'found'   => true,
			'name'    => $name,
			'count'   => count( $certs ),
			'profile' => $profile,
			'badges'  => $badges,
		) );
	}

	private function render_search() {
		$s   = HI_Data::get_settings();
		$org = $s['org_name'];
		$atc = ! empty( $s['atc_enabled'] ) && '' !== $s['atc_partner'];
		?>
		<main class="hp-srch">
			<section class="hp-srch-hero">
				<div class="hp-wrap">
					<span class="hp-srch-shield"><?php echo HI_Icons::get( 'check', 28 ); ?></span>
					<?php if ( $atc ) : ?><div class="hp-srch-eyebrow"><?php echo esc_html( __( 'Insignias digitales · ', 'hingenia-insignias' ) . $s['atc_partner'] . ' ' . $s['atc_label'] ); ?></div><?php endif; ?>
					<h1 class="hp-srch-h1"><?php esc_html_e( 'Verifica una', 'hingenia-insignias' ); ?> <span class="hp-srch-accent"><?php esc_html_e( 'insignia', 'hingenia-insignias' ); ?></span> <?php echo esc_html( $org ); ?>.</h1>
					<p class="hp-srch-sub"><?php esc_html_e( 'Credenciales digitales verificables que acreditan competencias reales — busca por el correo del titular para ver todas sus insignias.', 'hingenia-insignias' ); ?></p>

					<div class="hp-finder">
						<form id="hi-finder" autocomplete="off">
							<div class="hp-finder-field">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m3 6 9 7 9-7"/></svg>
								<input id="hi-finder-email" type="email" placeholder="<?php esc_attr_e( 'Ingresa el correo electrónico del titular…', 'hingenia-insignias' ); ?>">
							</div>
							<button type="submit"><?php esc_html_e( 'Buscar insignias', 'hingenia-insignias' ); ?></button>
						</form>
						<div class="hp-finder-hint"><?php esc_html_e( 'Busca todas las credenciales verificadas asociadas a una persona.', 'hingenia-insignias' ); ?></div>
					</div>

					<div class="hp-srch-trust">
						<span><?php echo HI_Icons::get( 'check', 15 ); ?> <?php esc_html_e( '100% verificables', 'hingenia-insignias' ); ?></span>
						<span><?php echo HI_Icons::get( 'check', 15 ); ?> <?php esc_html_e( 'Reconocimiento internacional', 'hingenia-insignias' ); ?></span>
						<?php if ( $atc ) : ?><span><?php echo HI_Icons::get( 'check', 15 ); ?> <?php printf( esc_html__( 'Respaldo %s', 'hingenia-insignias' ), esc_html( $s['atc_partner'] ) ); ?></span><?php endif; ?>
					</div>

					<div class="hp-srch-result" id="hi-result" aria-live="polite"></div>
				</div>
			</section>

			<section class="hp-srch-block">
				<div class="hp-wrap">
					<div class="hp-sec-head">
						<div class="hp-eyebrow"><?php esc_html_e( 'Verifica en segundos', 'hingenia-insignias' ); ?></div>
						<h2><?php esc_html_e( '3 pasos para validar una insignia.', 'hingenia-insignias' ); ?></h2>
					</div>
					<div class="hp-srch-cards">
						<div class="hp-srch-card"><span class="hp-srch-step">1</span><h3><?php esc_html_e( 'Ingresa el correo', 'hingenia-insignias' ); ?></h3><p><?php esc_html_e( 'Escribe el correo del titular en el buscador para encontrar todas sus credenciales.', 'hingenia-insignias' ); ?></p></div>
						<div class="hp-srch-card"><span class="hp-srch-step">2</span><h3><?php esc_html_e( 'Revisa la credencial', 'hingenia-insignias' ); ?></h3><p><?php esc_html_e( 'Verás sus insignias verificadas con el nombre del programa, fecha de emisión e ID único.', 'hingenia-insignias' ); ?></p></div>
						<div class="hp-srch-card"><span class="hp-srch-step">3</span><h3><?php esc_html_e( 'Valida con el QR o ID', 'hingenia-insignias' ); ?></h3><p><?php esc_html_e( 'Escanea el código QR o usa el ID de credencial para confirmar su autenticidad oficial.', 'hingenia-insignias' ); ?></p></div>
					</div>
				</div>
			</section>

			<?php if ( $atc && '' !== $s['atc_note'] ) : ?>
				<section class="hp-srch-block hp-srch-block--tight">
					<div class="hp-wrap">
						<div class="hp-atc-band">
							<span class="hp-seal"><?php echo HI_Icons::get( 'award', 26 ); ?></span>
							<div><div class="hp-at-name"><?php echo esc_html( $s['atc_partner'] ); ?></div><div class="hp-at-sub"><?php echo esc_html( $s['atc_label'] ); ?></div></div>
							<p><?php echo esc_html( $s['atc_note'] ); ?></p>
						</div>
					</div>
				</section>
			<?php endif; ?>
		</main>

		<script>
		(function(){
			var AJAX = "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>";
			var form = document.getElementById('hi-finder');
			var input = document.getElementById('hi-finder-email');
			var out = document.getElementById('hi-result');
			function esc(s){ return (s||'').replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }
			function render(d){
				if (!d.found){
					out.innerHTML = '<div class="hp-srch-notfound"><b><?php echo esc_js( __( 'Sin resultados', 'hingenia-insignias' ) ); ?></b><p><?php echo esc_js( __( 'No encontramos insignias asociadas a ese correo. Verifica el correo o pídelo al titular.', 'hingenia-insignias' ) ); ?></p></div>';
					return;
				}
				var badges = d.badges.map(function(b){
					var img = b.png ? '<img src="'+esc(b.png)+'" alt="">' : '';
					return '<a class="hp-rb" href="'+esc(b.url)+'"><div class="hp-rb-cover"><span class="hp-rb-v">✓</span>'+img+'</div><div class="hp-rb-body"><b>'+esc(b.title)+'</b><span><?php echo esc_js( __( 'Emitida el', 'hingenia-insignias' ) ); ?> '+esc(b.date)+'</span></div></a>';
				}).join('');
				var foot = d.profile ? '<div class="hp-rc-foot"><a href="'+esc(d.profile)+'"><?php echo esc_js( __( 'Ver perfil completo', 'hingenia-insignias' ) ); ?> →</a></div>' : '';
				var plural = d.count>1 ? 's':'';
				out.innerHTML = '<div class="hp-rc"><div class="hp-rc-head"><span class="hp-rc-av">'+esc(d.name.charAt(0).toUpperCase())+'</span><div><h3>'+esc(d.name)+'</h3><span class="hp-rc-vfy">'+d.count+' <?php echo esc_js( __( 'insignia', 'hingenia-insignias' ) ); ?>'+plural+' <?php echo esc_js( __( 'verificada', 'hingenia-insignias' ) ); ?>'+plural+'</span></div></div><div class="hp-rc-grid">'+badges+'</div>'+foot+'</div>';
			}
			form.addEventListener('submit', function(e){
				e.preventDefault();
				var em = (input.value||'').trim();
				if(!em){ input.focus(); return; }
				out.innerHTML = '<div class="hp-srch-loading"><?php echo esc_js( __( 'Buscando…', 'hingenia-insignias' ) ); ?></div>';
				var body = 'action=hi_search_badges&email='+encodeURIComponent(em);
				fetch(AJAX,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
					.then(function(r){return r.json();})
					.then(function(j){ if(j&&j.success){ render(j.data); } else { render({found:false}); } })
					.catch(function(){ out.innerHTML='<div class="hp-srch-notfound"><b><?php echo esc_js( __( 'Error', 'hingenia-insignias' ) ); ?></b><p><?php echo esc_js( __( 'No se pudo completar la búsqueda. Intenta de nuevo.', 'hingenia-insignias' ) ); ?></p></div>'; });
			});
		})();
		</script>
		<?php
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

		$tpl   = $cert->template_id ? HI_Data::get_template( (int) $cert->template_id ) : null;
		$meta  = $tpl ? HI_Data::template_meta( $tpl ) : HI_Data::default_meta();
		$qr    = HI_Generator::ensure_qr_png( $cert->token, $verify_url );
		$has_left = ( '' !== $meta['descripcion'] ) || ! empty( $meta['skills'] ) || ! empty( $meta['criterios'] );
		$wa_text  = $cert->user_name . ' obtuvo la insignia "' . $cert->course_title . '". Verifícala: ' . $verify_url;
		?>
		<section class="hp-cred">
			<div class="hp-cred-badge">
				<?php if ( $cert->png_url ) : ?>
					<img class="hp-medal-img" src="<?php echo esc_url( $cert->png_url ); ?>" alt="Insignia: <?php echo esc_attr( $cert->course_title ); ?>">
				<?php endif; ?>
				<?php if ( $qr ) : ?>
					<button class="hp-qr-zoom" data-qr="<?php echo esc_url( $qr ); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/><path d="M11 8v6M8 11h6"/></svg>
						Ampliar QR
					</button>
				<?php endif; ?>
			</div>

			<div class="hp-cred-info">
				<span class="hp-verified"><?php echo HI_Icons::get( 'check', 14 ); ?> Insignia verificada</span>
				<h1 class="hp-cred-name"><?php echo esc_html( $cert->user_name ); ?></h1>
				<p class="hp-cred-subtitle"><?php echo esc_html( $cert->course_title ); ?></p>

				<div class="hp-cred-meta">
					<div class="hp-meta-item"><div class="hp-k">Emitida por</div><div class="hp-v"><?php echo esc_html( $org ); ?></div></div>
					<div class="hp-meta-item"><div class="hp-k">Fecha de emisión</div><div class="hp-v"><?php echo esc_html( $fecha ); ?></div></div>
					<?php if ( '' !== $meta['nivel'] ) : ?>
						<div class="hp-meta-item"><div class="hp-k">Nivel</div><div class="hp-v"><?php echo esc_html( $meta['nivel'] ); ?></div></div>
					<?php endif; ?>
					<div class="hp-meta-item"><div class="hp-k">ID de credencial</div><div class="hp-v hp-mono"><?php echo esc_html( $cert->token ); ?></div></div>
				</div>

				<div class="hp-share">
					<?php if ( $cert->png_url ) : ?>
						<a class="hp-btn hp-btn-blue" href="<?php echo esc_url( $cert->png_url ); ?>" download><?php echo HI_Icons::get( 'upload', 15 ); ?> Descargar</a>
					<?php endif; ?>
					<a class="hp-btn hp-btn-li" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( $verify_url ); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zM8.3 18.3V10H5.7v8.3zM7 8.8a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm11.3 9.5v-4.6c0-2.4-1.3-3.6-3-3.6-1.4 0-2 .8-2.4 1.3V10h-2.6v8.3h2.6v-4.4c0-1.2.8-1.6 1.4-1.6.7 0 1.4.5 1.4 1.7v4.3z"/></svg>
						LinkedIn
					</a>
					<a class="hp-btn hp-btn-wa" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo rawurlencode( $wa_text ); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.3-.5 0-1 .2-3.2-.7-2.7-1-4.4-3.8-4.5-4-.2-.2-1.1-1.4-1.1-2.6s.6-1.8.9-2.1c.2-.2.5-.3.6-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.5c-.1.2-.3.3-.1.6.1.3.7 1.2 1.5 1.9 1 .9 1.9 1.2 2.2 1.3.2.1.4.1.5-.1l.7-.8c.2-.2.4-.2.6-.1l1.9.9c.3.1.4.2.5.3.1.2.1.7-.1 1.2z"/></svg>
						WhatsApp
					</a>
					<button class="hp-btn hp-btn-ghost hp-copy" data-url="<?php echo esc_attr( $verify_url ); ?>">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
						Copiar enlace
					</button>
				</div>

				<?php if ( $has_prof ) : ?>
					<div class="hp-cred-link"><a href="<?php echo esc_url( $prof_url ); ?>">Ver todas las insignias de <?php echo esc_html( $first ); ?> <?php echo HI_Icons::get( 'arrow-right', 14 ); ?></a></div>
				<?php endif; ?>
			</div>
		</section>

		<div class="hp-below <?php echo $has_left ? '' : 'hp-below--solo'; ?>">
			<?php if ( $has_left ) : ?>
				<div class="hp-below-main">
					<?php if ( '' !== $meta['descripcion'] || ! empty( $meta['skills'] ) ) : ?>
						<div class="hp-card">
							<h3><span class="hp-pip"></span>Acerca de esta insignia</h3>
							<?php if ( '' !== $meta['descripcion'] ) : ?>
								<p><?php echo esc_html( $meta['descripcion'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $meta['skills'] ) ) : ?>
								<div class="hp-skills">
									<?php foreach ( $meta['skills'] as $sk ) : ?>
										<span class="hp-skill"><?php echo esc_html( $sk ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $meta['criterios'] ) ) : ?>
						<div class="hp-card" style="margin-top:20px">
							<h3><span class="hp-pip hp-pip--green"></span>Criterios de obtención</h3>
							<div class="hp-crit">
								<?php foreach ( $meta['criterios'] as $cr ) : ?>
									<div class="hp-crit-item"><span class="hp-ck"><?php echo HI_Icons::get( 'check', 11 ); ?></span><?php echo esc_html( $cr ); ?></div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<aside class="hp-card hp-verify-card">
				<div class="hp-vc-top">
					<span class="hp-shield"><?php echo HI_Icons::get( 'check', 20 ); ?></span>
					<div><b>Credencial verificada</b><span>Emitida y validada por <?php echo esc_html( $org ); ?></span></div>
				</div>
				<div class="hp-vc-row"><div class="hp-k">ID de credencial</div><div class="hp-v hp-mono"><?php echo esc_html( $cert->token ); ?></div></div>
				<?php if ( ! empty( $s['atc_enabled'] ) && '' !== $s['atc_partner'] ) : ?>
					<div class="hp-atc">
						<span class="hp-seal"><?php echo HI_Icons::get( 'award', 22 ); ?></span>
						<div><div class="hp-at-name"><?php echo esc_html( $s['atc_partner'] ); ?></div><div class="hp-at-sub"><?php echo esc_html( $s['atc_label'] ); ?></div></div>
					</div>
					<?php if ( '' !== $s['atc_note'] ) : ?>
						<p class="hp-vc-note"><?php echo esc_html( $s['atc_note'] ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</aside>
		</div>

		<?php $this->qr_lightbox(); ?>
		<script>
		document.addEventListener('click',function(e){
			var c=e.target.closest('.hp-copy');
			if(c){navigator.clipboard.writeText(c.getAttribute('data-url')).then(function(){var s=c.querySelector('svg');var t=c.childNodes;c.lastChild.textContent=' ¡Copiado!';setTimeout(function(){c.lastChild.textContent=' Copiar enlace';},1500);});return;}
			var z=e.target.closest('.hp-qr-zoom');
			if(z){var lb=document.getElementById('hp-qr-lb');lb.querySelector('img').src=z.getAttribute('data-qr');lb.hidden=false;return;}
			if(e.target.closest('[data-qrclose]')){document.getElementById('hp-qr-lb').hidden=true;}
		});
		</script>
		<?php
	}

	private function render_profile() {
		$user   = $this->user;
		$s      = HI_Data::get_settings();
		$certs  = HI_Data::get_certificates_for_user( (int) $user->ID );
		$name   = $user->display_name ? $user->display_name : $user->user_login;
		$n      = count( $certs );
		$horas  = 0;
		$metas  = array();
		foreach ( $certs as $c ) {
			$tpl = $c->template_id ? HI_Data::get_template( (int) $c->template_id ) : null;
			$m   = $tpl ? HI_Data::template_meta( $tpl ) : HI_Data::default_meta();
			$metas[ $c->id ] = $m;
			$horas += (int) $m['horas'];
		}
		?>
		<div class="hp-student">
			<span class="hp-av"><?php echo esc_html( strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></span>
			<div>
				<h1><?php echo esc_html( $name ); ?></h1>
				<div class="hp-sub"><span class="hp-pill"><?php echo (int) $n; ?> insignia<?php echo 1 === $n ? '' : 's'; ?> digital<?php echo 1 === $n ? '' : 'es'; ?></span> <?php echo esc_html( $s['profile_tagline'] ); ?></div>
				<?php if ( '' !== $s['profile_desc'] ) : ?>
					<p class="hp-desc"><?php echo esc_html( $s['profile_desc'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="hp-meta-strip">
			<span class="hp-ms-item"><span class="hp-ic hp-ic--b"><?php echo HI_Icons::get( 'award', 15 ); ?></span><?php echo (int) $n; ?> <span>insignias</span></span>
			<?php if ( $horas > 0 ) : ?>
				<span class="hp-ms-item"><span class="hp-ic hp-ic--g"><?php echo HI_Icons::get( 'check', 15 ); ?></span><?php echo (int) $horas; ?>+ <span>horas certificadas</span></span>
			<?php endif; ?>
			<?php if ( ! empty( $s['atc_enabled'] ) && '' !== $s['atc_partner'] ) : ?>
				<span class="hp-ms-item"><span class="hp-ic hp-ic--k"><?php echo HI_Icons::get( 'award', 15 ); ?></span><?php echo esc_html( $s['atc_partner'] ); ?> <span><?php echo esc_html( $s['atc_label'] ); ?></span></span>
			<?php endif; ?>
		</div>

		<div class="hp-sec-head">
			<div class="hp-eyebrow"><?php echo $this->mine ? 'Mis credenciales' : 'Credenciales'; ?></div>
			<h2><?php echo $this->mine ? 'Mis insignias obtenidas' : 'Insignias obtenidas'; ?></h2>
		</div>

		<?php if ( empty( $certs ) ) : ?>
			<?php if ( $this->mine ) : ?>
				<div class="hp-empty hp-empty--mine">
					<?php echo HI_Icons::get( 'award', 40 ); ?>
					<h3>Aún no tienes insignias</h3>
					<p>Las insignias digitales de <?php echo esc_html( $s['org_name'] ); ?> son credenciales verificables que acreditan las competencias que demuestras al completar tus cursos. Cada una lleva un ID único y un código QR: son 100% verificables y puedes compartirlas en LinkedIn, en tu CV o por WhatsApp.</p>
					<p class="hp-empty-hint">Cuando completes un curso con insignia habilitada, tu insignia aparecerá aquí automáticamente.</p>
					<div class="hp-empty-cta">
						<a class="hp-btn hp-btn-blue" href="<?php echo esc_url( home_url( '/cursos' ) ); ?>">Ver cursos</a>
						<a class="hp-btn hp-btn-ghost" href="<?php echo esc_url( HI_Data::search_url() ); ?>">Verificar una insignia</a>
					</div>
				</div>
			<?php else : ?>
				<div class="hp-empty"><?php echo HI_Icons::get( 'award', 40 ); ?><p>Este estudiante aún no tiene insignias.</p></div>
			<?php endif; ?>
		<?php else : ?>
			<div class="hp-badges">
				<?php foreach ( $certs as $c ) :
					$m = $metas[ $c->id ]; ?>
					<a class="hp-bcard" href="<?php echo esc_url( HI_Data::badge_url( $c->token ) ); ?>">
						<div class="hp-bcard-cover">
							<span class="hp-vfy"><?php echo HI_Icons::get( 'check', 11 ); ?> Verificada</span>
							<?php if ( $c->png_url ) : ?>
								<img src="<?php echo esc_url( $c->png_url ); ?>" alt="" loading="lazy">
							<?php else : ?>
								<?php echo HI_Icons::get( 'award', 40 ); ?>
							<?php endif; ?>
						</div>
						<div class="hp-bcard-body">
							<h3><?php echo esc_html( $c->course_title ); ?></h3>
							<span class="hp-date">Emitida el <?php echo esc_html( date_i18n( 'd \d\e F \d\e Y', strtotime( $c->emitido_at ) ) ); ?></span>
							<?php if ( '' !== $m['descripcion'] ) : ?>
								<p class="hp-bdesc"><?php echo esc_html( wp_trim_words( $m['descripcion'], 22 ) ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $m['skills'] ) ) : ?>
								<div class="hp-bskills">
									<?php foreach ( array_slice( $m['skills'], 0, 3 ) as $sk ) : ?>
										<span class="hp-bskill"><?php echo esc_html( $sk ); ?></span>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
							<div class="hp-bcard-foot"><span class="hp-bcard-cta">Ver insignia <?php echo HI_Icons::get( 'arrow-right', 13 ); ?></span></div>
						</div>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $s['show_why'] ) ) : ?>
			<div class="hp-sec-head"><div class="hp-eyebrow">Por qué importan</div><h2>Credenciales con estándar global</h2></div>
			<div class="hp-about">
				<div class="hp-ab-card"><div class="hp-ic hp-ic--b"><?php echo HI_Icons::get( 'check', 19 ); ?></div><h3>100% verificables</h3><p>Cada insignia tiene un ID único y un QR. Cualquiera puede validar su autenticidad en línea, sin intermediarios.</p></div>
				<div class="hp-ab-card"><div class="hp-ic hp-ic--b"><?php echo HI_Icons::get( 'upload', 19 ); ?></div><h3>Compártelas donde importa</h3><p>Publícalas en LinkedIn, tu CV o por WhatsApp con un clic. Demuestra tus competencias ante reclutadores y empresas.</p></div>
				<div class="hp-ab-card"><div class="hp-ic hp-ic--b"><?php echo HI_Icons::get( 'award', 19 ); ?></div><h3>Respaldo internacional</h3><p>Emitidas por <?php echo esc_html( $s['org_name'] ); ?><?php echo ! empty( $s['atc_enabled'] ) ? ', ' . esc_html( $s['atc_label'] ) : ''; ?> — reconocimiento válido a nivel global.</p></div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $s['atc_enabled'] ) && '' !== $s['atc_partner'] && '' !== $s['atc_note'] ) : ?>
			<div class="hp-atc-band">
				<span class="hp-seal"><?php echo HI_Icons::get( 'award', 26 ); ?></span>
				<div><div class="hp-at-name"><?php echo esc_html( $s['atc_partner'] ); ?></div><div class="hp-at-sub"><?php echo esc_html( $s['atc_label'] ); ?></div></div>
				<p><?php echo esc_html( $s['atc_note'] ); ?></p>
			</div>
		<?php endif; ?>
		<?php
	}

	/** Lightbox para ampliar el QR. */
	private function qr_lightbox() {
		?>
		<div class="hp-lightbox" id="hp-qr-lb" hidden>
			<div class="hp-lightbox__bd" data-qrclose></div>
			<div class="hp-lightbox__box">
				<button class="hp-lightbox__x" data-qrclose aria-label="Cerrar">✕</button>
				<img src="" alt="Código QR de la credencial">
				<p>Escanea este código para verificar la credencial.</p>
			</div>
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
