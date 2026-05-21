<?php
/**
 * Lógica de emisión de una insignia a un estudiante:
 * resuelve plantilla y usuario, evita duplicados, genera el PNG y guarda la fila.
 *
 * @package HingeniaInsignias
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HI_Emit {

	/**
	 * Emite una insignia.
	 *
	 * @param int    $course_id
	 * @param string $name
	 * @param string $email
	 * @param int    $user_id   Opcional; si 0 se intenta resolver por email.
	 * @param bool   $reissue   Si true, vuelve a emitir aunque ya exista.
	 * @return array
	 */
	public static function issue( $course_id, $name, $email = '', $user_id = 0, $reissue = false ) {
		$course_id = (int) $course_id;
		$name      = trim( wp_strip_all_tags( (string) $name ) );
		$email     = $email ? sanitize_email( $email ) : '';

		if ( ! $course_id ) {
			return array( 'success' => false, 'error' => 'Falta el curso.' );
		}
		if ( '' === $name ) {
			return array( 'success' => false, 'error' => 'Falta el nombre del estudiante.' );
		}

		$tpl = HI_Data::get_template_by_course( $course_id );
		if ( ! $tpl ) {
			return array( 'success' => false, 'error' => 'El curso no tiene una plantilla activa. Crea la plantilla primero.' );
		}

		// Resolver usuario WP por email.
		if ( ! $user_id && $email ) {
			$u = get_user_by( 'email', $email );
			if ( $u ) {
				$user_id = (int) $u->ID;
			}
		}

		// Evitar duplicados salvo reemisión explícita.
		$existing = HI_Data::find_existing_certificate( $user_id, $email, $course_id );
		if ( $existing && ! $reissue ) {
			return array(
				'success' => true,
				'skipped' => true,
				'id'      => (int) $existing->id,
				'url'     => $existing->png_url,
				'msg'     => 'Ya tenía insignia (omitido).',
			);
		}

		$cert = HI_Data::insert_certificate( array(
			'user_id'      => $user_id,
			'user_name'    => $name,
			'user_email'   => $email,
			'course_id'    => $course_id,
			'course_title' => HI_Data::get_course_title( $course_id ),
			'template_id'  => (int) $tpl->id,
		) );
		$token  = $cert['token'];
		$verify = HI_Data::badge_url( $token );

		$gen = HI_Generator::generate( $tpl, $name, $verify, $token );
		if ( empty( $gen['success'] ) ) {
			// Si falla la imagen, revocamos para no dejar una insignia rota.
			HI_Data::revoke_certificate( $cert['id'] );
			return array( 'success' => false, 'error' => $gen['error'] ?? 'No se pudo generar la imagen.' );
		}

		HI_Data::update_certificate( $cert['id'], array( 'png_url' => $gen['url'] ) );

		return array(
			'success' => true,
			'id'      => (int) $cert['id'],
			'token'   => $token,
			'url'     => $gen['url'],
			'verify'  => $verify,
		);
	}

	/** Revoca una emisión y borra su PNG. */
	public static function revoke( $id ) {
		$id = (int) $id;
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT token FROM ' . HI_Data::table_certificates() . ' WHERE id = %d',
			$id
		) );
		if ( $row && $row->token ) {
			HI_Generator::delete_png( $row->token );
		}
		HI_Data::revoke_certificate( $id );
	}
}
