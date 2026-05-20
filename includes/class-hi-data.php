<?php
/**
 * Capa de datos del plugin Insignias:
 * - Plantillas de insignia (una imagen base por curso).
 * - Emisiones (cada estudiante con su insignia generada).
 * - Configuración global.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Data {

	const OPT_SETTINGS = 'hi_settings';
	const OPT_DB_VER   = 'hi_db_version';
	const TABLE_TPL    = 'hi_badge_templates';
	const TABLE_CERT   = 'hi_certificates';
	const DB_VERSION   = '1';

	public static function boot() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	public static function table_templates() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_TPL;
	}

	public static function table_certificates() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_CERT;
	}

	/** Auto-instala/actualiza las tablas cuando cambia DB_VERSION. */
	public static function maybe_upgrade() {
		if ( get_option( self::OPT_DB_VER ) !== self::DB_VERSION ) {
			self::create_tables();
			update_option( self::OPT_DB_VER, self::DB_VERSION );
		}
	}

	public static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$t_tpl   = self::table_templates();
		$t_cert  = self::table_certificates();

		$sql_tpl = "CREATE TABLE {$t_tpl} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			course_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			course_title VARCHAR(255) NOT NULL DEFAULT '',
			nombre VARCHAR(190) NOT NULL DEFAULT '',
			png_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			png_url TEXT NULL,
			layout_json LONGTEXT NULL,
			activa TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY course_id (course_id),
			KEY activa (activa)
		) {$charset};";

		$sql_cert = "CREATE TABLE {$t_cert} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_name VARCHAR(190) NOT NULL DEFAULT '',
			user_email VARCHAR(190) NOT NULL DEFAULT '',
			course_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			course_title VARCHAR(255) NOT NULL DEFAULT '',
			template_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			token VARCHAR(64) NOT NULL DEFAULT '',
			png_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			png_url TEXT NULL,
			emitido_at DATETIME NOT NULL,
			revoked_at DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY user_id (user_id),
			KEY course_id (course_id),
			KEY template_id (template_id),
			KEY emitido_at (emitido_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_tpl );
		dbDelta( $sql_cert );
	}

	/* ====================================================================
	   SETTINGS
	   ==================================================================== */

	public static function default_settings() {
		return array(
			'public_slug'        => 'insignias',          // /insignias/{slug-usuario}
			'badge_slug'         => 'insignia',           // /insignia/{token}
			'org_name'           => 'Hingenia',
			'org_url'            => home_url( '/' ),
			'qr_destination'     => 'badge',              // 'badge' (recomendado) | 'profile'
			'verify_subtitle'    => 'Verificación de insignia digital emitida por Hingenia',
			'profile_intro'      => 'Estas son las insignias digitales que ha obtenido el estudiante.',
			'allow_share_linkedin' => '1',
		);
	}

	public static function get_settings() {
		$s = get_option( self::OPT_SETTINGS, array() );
		return wp_parse_args( is_array( $s ) ? $s : array(), self::default_settings() );
	}

	public static function save_settings( $settings ) {
		update_option( self::OPT_SETTINGS, $settings );
	}

	/* ====================================================================
	   PLANTILLAS
	   ==================================================================== */

	public static function default_layout_json() {
		return array(
			'canvas_w' => 1080,
			'canvas_h' => 1080,
			'name'     => array(
				'x' => 540, 'y' => 720, 'w' => 880, 'h' => 90,
				'align' => 'center', 'size' => 44, 'color' => '#ffffff',
				'weight' => 700, 'uppercase' => true,
			),
			'qr' => array(
				'x' => 80, 'y' => 80, 'w' => 160, 'h' => 160,
				'bg' => '#ffffff', 'fg' => '#000000', 'margin' => 8,
			),
		);
	}

	public static function get_templates( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'activa'    => null,
			'course_id' => null,
			'search'    => '',
			'limit'     => 500,
		);
		$args  = wp_parse_args( $args, $defaults );
		$table = self::table_templates();
		$where = array( '1=1' );
		$prep  = array();

		if ( null !== $args['activa'] ) {
			$where[] = 'activa = %d';
			$prep[]  = (int) $args['activa'];
		}
		if ( null !== $args['course_id'] ) {
			$where[] = 'course_id = %d';
			$prep[]  = (int) $args['course_id'];
		}
		if ( $args['search'] ) {
			$like   = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(nombre LIKE %s OR course_title LIKE %s)';
			$prep[]  = $like;
			$prep[]  = $like;
		}
		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$prep[] = (int) $args['limit'];
		return $wpdb->get_results( $wpdb->prepare( $sql, $prep ) );
	}

	public static function get_template( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_templates() . ' WHERE id = %d', (int) $id ) );
	}

	public static function get_template_by_course( $course_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table_templates() . ' WHERE course_id = %d AND activa = 1 ORDER BY id DESC LIMIT 1',
			(int) $course_id
		) );
	}

	public static function save_template( $data, $id = 0 ) {
		global $wpdb;
		$now    = current_time( 'mysql' );
		$row    = array(
			'course_id'         => (int) ( $data['course_id'] ?? 0 ),
			'course_title'      => (string) ( $data['course_title'] ?? '' ),
			'nombre'            => (string) ( $data['nombre'] ?? '' ),
			'png_attachment_id' => (int) ( $data['png_attachment_id'] ?? 0 ),
			'png_url'           => (string) ( $data['png_url'] ?? '' ),
			'layout_json'       => is_array( $data['layout_json'] ?? null )
				? wp_json_encode( $data['layout_json'] )
				: (string) ( $data['layout_json'] ?? wp_json_encode( self::default_layout_json() ) ),
			'activa'            => ! empty( $data['activa'] ) ? 1 : 0,
			'updated_at'        => $now,
		);
		$formats = array( '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s' );

		if ( $id ) {
			$wpdb->update( self::table_templates(), $row, array( 'id' => (int) $id ), $formats, array( '%d' ) );
			return (int) $id;
		}
		$row['created_at'] = $now;
		$formats[]         = '%s';
		$wpdb->insert( self::table_templates(), $row, $formats );
		return (int) $wpdb->insert_id;
	}

	public static function delete_template( $id ) {
		global $wpdb;
		$wpdb->delete( self::table_templates(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	public static function count_templates( $activa = null ) {
		global $wpdb;
		if ( null === $activa ) {
			return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_templates() );
		}
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . self::table_templates() . ' WHERE activa = %d',
			(int) $activa
		) );
	}

	/* ====================================================================
	   EMISIONES (certificates)
	   ==================================================================== */

	public static function get_certificates( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'user_id'   => null,
			'course_id' => null,
			'search'    => '',
			'limit'     => 1000,
		);
		$args  = wp_parse_args( $args, $defaults );
		$where = array( 'revoked_at IS NULL' );
		$prep  = array();

		if ( null !== $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$prep[]  = (int) $args['user_id'];
		}
		if ( null !== $args['course_id'] ) {
			$where[] = 'course_id = %d';
			$prep[]  = (int) $args['course_id'];
		}
		if ( $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(user_name LIKE %s OR user_email LIKE %s OR course_title LIKE %s)';
			$prep[]  = $like;
			$prep[]  = $like;
			$prep[]  = $like;
		}
		$sql    = 'SELECT * FROM ' . self::table_certificates() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY emitido_at DESC LIMIT %d';
		$prep[] = (int) $args['limit'];

		if ( empty( $prep ) ) {
			return $wpdb->get_results( $sql );
		}
		return $wpdb->get_results( $wpdb->prepare( $sql, $prep ) );
	}

	public static function get_certificate_by_token( $token ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table_certificates() . ' WHERE token = %s AND revoked_at IS NULL',
			(string) $token
		) );
	}

	public static function get_certificates_for_user( $user_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table_certificates() . ' WHERE user_id = %d AND revoked_at IS NULL ORDER BY emitido_at DESC',
			(int) $user_id
		) );
	}

	public static function get_certificates_for_email( $email ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table_certificates() . ' WHERE user_email = %s AND revoked_at IS NULL ORDER BY emitido_at DESC',
			(string) $email
		) );
	}

	public static function find_existing_certificate( $user_id, $email, $course_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM ' . self::table_certificates() . '
			  WHERE course_id = %d AND revoked_at IS NULL
			    AND ( ( %d > 0 AND user_id = %d ) OR ( %s <> "" AND user_email = %s ) )
			  ORDER BY id DESC LIMIT 1',
			(int) $course_id,
			(int) $user_id,
			(int) $user_id,
			(string) $email,
			(string) $email
		) );
	}

	public static function insert_certificate( $data ) {
		global $wpdb;
		$token = self::unique_token();
		$row   = array(
			'user_id'           => (int) ( $data['user_id'] ?? 0 ),
			'user_name'         => (string) ( $data['user_name'] ?? '' ),
			'user_email'        => (string) ( $data['user_email'] ?? '' ),
			'course_id'         => (int) ( $data['course_id'] ?? 0 ),
			'course_title'      => (string) ( $data['course_title'] ?? '' ),
			'template_id'       => (int) ( $data['template_id'] ?? 0 ),
			'token'             => $token,
			'png_attachment_id' => (int) ( $data['png_attachment_id'] ?? 0 ),
			'png_url'           => (string) ( $data['png_url'] ?? '' ),
			'emitido_at'        => current_time( 'mysql' ),
		);
		$wpdb->insert(
			self::table_certificates(),
			$row,
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
		return array(
			'id'    => (int) $wpdb->insert_id,
			'token' => $token,
		);
	}

	public static function update_certificate( $id, $data ) {
		global $wpdb;
		$wpdb->update( self::table_certificates(), $data, array( 'id' => (int) $id ) );
	}

	public static function revoke_certificate( $id ) {
		global $wpdb;
		$wpdb->update(
			self::table_certificates(),
			array( 'revoked_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function count_certificates() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_certificates() . ' WHERE revoked_at IS NULL' );
	}

	public static function count_unique_students() {
		global $wpdb;
		return (int) $wpdb->get_var(
			'SELECT COUNT(DISTINCT IF(user_id>0, CONCAT("u:",user_id), CONCAT("e:",user_email))) FROM '
			. self::table_certificates() . ' WHERE revoked_at IS NULL'
		);
	}

	public static function unique_token() {
		do {
			$token = wp_generate_password( 24, false, false );
			global $wpdb;
			$exists = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . self::table_certificates() . ' WHERE token = %s',
				$token
			) );
		} while ( $exists );
		return $token;
	}

	/* ====================================================================
	   INTEGRACIÓN TUTOR LMS
	   ==================================================================== */

	/**
	 * Lista todos los cursos de Tutor LMS (CPT 'courses').
	 * Devuelve [ id, title ].
	 */
	public static function get_tutor_courses() {
		$out  = array();
		$args = array(
			'post_type'      => 'courses',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		$q = new WP_Query( $args );
		foreach ( $q->posts as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( $p ),
			);
		}
		return $out;
	}

	public static function get_course_title( $course_id ) {
		$t = get_the_title( (int) $course_id );
		return $t ? $t : '';
	}

	/* ====================================================================
	   URLs públicas
	   ==================================================================== */

	public static function badge_url( $token ) {
		$s = self::get_settings();
		return home_url( '/' . trim( $s['badge_slug'], '/' ) . '/' . rawurlencode( $token ) . '/' );
	}

	public static function profile_url( $user_id_or_slug ) {
		$s = self::get_settings();
		return home_url( '/' . trim( $s['public_slug'], '/' ) . '/' . rawurlencode( $user_id_or_slug ) . '/' );
	}
}
