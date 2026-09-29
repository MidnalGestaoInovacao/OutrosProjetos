<?php
/**
 * Consentimento de cookies: validação do payload enviado pelo banner do site e registro como prova (LGPD art. 8º, § 2º).
 *
 * @package EBCR
 */

namespace EBCR\Domain;

use EBCR\Database\CookieConsentRepository;
use EBCR\Support\Ip;

defined( 'ABSPATH' ) || exit;

/**
 * O IP nunca é gravado em claro: guarda-se HMAC-SHA256(IP, wp_salt('auth')).
 */
final class CookieConsent {

	/**
	 * Categorias aceitas (as demais chaves são ignoradas).
	 */
	const CATEGORIES = array( 'necessary', 'functional', 'analytics', 'advertising' );

	/**
	 * Ações aceitas.
	 */
	const ACTIONS = array( 'accept_all', 'reject_all', 'custom', 'withdraw' );

	/**
	 * UUID v4.
	 */
	const UUID_V4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

	/**
	 * Rótulos das ações.
	 *
	 * @return array
	 */
	public static function action_labels() {
		return array(
			'accept_all' => __( 'Aceitou todos', 'eb-credito-rural' ),
			'reject_all' => __( 'Recusou os opcionais', 'eb-credito-rural' ),
			'custom'     => __( 'Personalizou', 'eb-credito-rural' ),
			'withdraw'   => __( 'Revogou', 'eb-credito-rural' ),
		);
	}

	/**
	 * Converte um valor em booleano estrito (bool, 0/1, "true"/"false", "0"/"1"); null se inválido.
	 *
	 * @param mixed $v Valor.
	 * @return bool|null
	 */
	private static function to_bool( $v ) {
		if ( is_bool( $v ) ) {
			return $v;
		}
		if ( 0 === $v || 1 === $v ) {
			return (bool) $v;
		}
		if ( is_string( $v ) && in_array( strtolower( $v ), array( 'true', 'false', '0', '1' ), true ) ) {
			return in_array( strtolower( $v ), array( 'true', '1' ), true );
		}
		return null;
	}

	/**
	 * Valida e normaliza o payload. Retorna os dados limpos ou WP_Error (sem ecoar o conteúdo recebido).
	 * Regras: consent_id UUID v4; categories objeto com booleanos (necessary é sempre true); action da lista;
	 * version inteiro ≥ 0; path só o caminho (sem query/fragmento), até 200 caracteres. accept_all liga todas as
	 * categorias; reject_all e withdraw deixam só as necessárias.
	 *
	 * @param mixed $payload Corpo JSON decodificado.
	 * @return array|\WP_Error
	 */
	public static function validate( $payload ) {
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'ebcr_consent_invalid', __( 'Requisição inválida.', 'eb-credito-rural' ), array( 'status' => 400 ) );
		}
		$errors = array();
		$id     = isset( $payload['consent_id'] ) && is_string( $payload['consent_id'] ) ? strtolower( trim( $payload['consent_id'] ) ) : '';
		if ( ! preg_match( self::UUID_V4, $id ) ) {
			$errors[] = 'consent_id';
		}
		$action = isset( $payload['action'] ) && is_string( $payload['action'] ) ? $payload['action'] : '';
		if ( ! in_array( $action, self::ACTIONS, true ) ) {
			$errors[] = 'action';
		}
		$cats = array();
		if ( ! isset( $payload['categories'] ) || ! is_array( $payload['categories'] ) || ( $payload['categories'] && array_keys( $payload['categories'] ) === range( 0, count( $payload['categories'] ) - 1 ) ) ) {
			$errors[] = 'categories';
		} else {
			foreach ( self::CATEGORIES as $c ) {
				if ( ! array_key_exists( $c, $payload['categories'] ) ) {
					$cats[ $c ] = false;
					continue;
				}
				$b = self::to_bool( $payload['categories'][ $c ] );
				if ( null === $b ) {
					$errors[] = 'categories.' . $c;
					continue;
				}
				$cats[ $c ] = $b;
			}
		}
		$version = isset( $payload['version'] ) ? $payload['version'] : null;
		if ( is_string( $version ) && preg_match( '/^\d{1,9}$/', $version ) ) {
			$version = (int) $version;
		}
		if ( ! is_int( $version ) || $version < 0 || $version > 999999999 ) {
			$errors[] = 'version';
		}
		$path = '/';
		if ( isset( $payload['path'] ) ) {
			if ( ! is_string( $payload['path'] ) || strlen( $payload['path'] ) > 2000 ) {
				$errors[] = 'path';
			} else {
				$p    = (string) wp_parse_url( $payload['path'], PHP_URL_PATH );
				$p    = preg_replace( '/[\x00-\x1F\x7F<>"\'\\\\]/', '', $p );
				$path = '/' . ltrim( (string) $p, '/' );
				$path = mb_substr( $path, 0, 200 );
			}
		}
		if ( $errors ) {
			return new \WP_Error(
				'ebcr_consent_invalid',
				__( 'Requisição inválida.', 'eb-credito-rural' ),
				array(
					'status' => 400,
					'fields' => array_values( array_unique( $errors ) ),
				)
			);
		}
		if ( 'accept_all' === $action ) {
			$cats = array_fill_keys( self::CATEGORIES, true );
		} elseif ( in_array( $action, array( 'reject_all', 'withdraw' ), true ) ) {
			$cats = array_fill_keys( self::CATEGORIES, false );
		}
		$cats['necessary'] = true; // Cookies estritamente necessários não dependem de consentimento.
		return array(
			'consent_id' => $id,
			'categories' => $cats,
			'action'     => $action,
			'version'    => (int) $version,
			'path'       => $path,
		);
	}

	/**
	 * HMAC do IP (nunca o IP em claro).
	 *
	 * @param string $ip IP.
	 * @return string
	 */
	public static function ip_hash( $ip ) {
		return '' === (string) $ip ? '' : hash_hmac( 'sha256', (string) $ip, wp_salt( 'auth' ) );
	}

	/**
	 * Grava um consentimento validado.
	 *
	 * @param array $clean Resultado de validate().
	 * @return int ID.
	 */
	public static function record( array $clean ) {
		$uid = get_current_user_id();
		return ( new CookieConsentRepository() )->insert(
			array(
				'consent_id' => $clean['consent_id'],
				'categories' => (string) wp_json_encode( $clean['categories'] ),
				'action'     => $clean['action'],
				'version'    => (int) $clean['version'],
				'ip_hash'    => self::ip_hash( Ip::get() ),
				'user_agent' => mb_substr( Ip::user_agent(), 0, 180 ),
				'path'       => $clean['path'],
				'user_id'    => $uid ? $uid : null,
				'created_at' => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * Resumo legível das categorias de um registro.
	 *
	 * @param string $json JSON gravado.
	 * @return string
	 */
	public static function categories_text( $json ) {
		$cats = json_decode( (string) $json, true );
		if ( ! is_array( $cats ) ) {
			return '';
		}
		$labels = array(
			'necessary'   => __( 'necessários', 'eb-credito-rural' ),
			'functional'  => __( 'funcionais', 'eb-credito-rural' ),
			'analytics'   => __( 'estatísticos', 'eb-credito-rural' ),
			'advertising' => __( 'publicidade', 'eb-credito-rural' ),
		);
		$on     = array();
		foreach ( $labels as $k => $l ) {
			if ( ! empty( $cats[ $k ] ) ) {
				$on[] = $l;
			}
		}
		return implode( ', ', $on );
	}
}
