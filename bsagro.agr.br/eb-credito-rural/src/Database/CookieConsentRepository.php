<?php
/**
 * Registros de consentimento de cookies.
 *
 * @package EBCR
 */

namespace EBCR\Database;

defined( 'ABSPATH' ) || exit;

/**
 * ebcr_cookie_consents (somente inserção, consulta, anonimização e expurgo).
 */
class CookieConsentRepository extends Db {

	/**
	 * Insere.
	 *
	 * @param array $data Dados já validados.
	 * @return int
	 */
	public function insert( array $data ) {
		return self::insert_row( 'cookie_consents', $data );
	}

	/**
	 * Consulta paginada com filtros (consent_id, action, date_from, date_to, user_id).
	 *
	 * @param array $args Filtros.
	 * @return array [itens, total]
	 */
	public function query( array $args = array() ) {
		global $wpdb;
		$t      = self::table( 'cookie_consents' );
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['consent_id'] ) ) {
			$where[]  = 'consent_id = %s';
			$params[] = (string) $args['consent_id'];
		}
		if ( ! empty( $args['action'] ) ) {
			$where[]  = 'action = %s';
			$params[] = (string) $args['action'];
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		$per_page = max( 1, min( 10000, isset( $args['per_page'] ) ? (int) $args['per_page'] : 50 ) );
		$page     = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );
		$sql      = implode( ' AND ', $where );
		$count    = "SELECT COUNT(*) FROM `{$t}` WHERE {$sql}";
		$list     = "SELECT * FROM `{$t}` WHERE {$sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- tabela interna; valores via prepare().
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count, $params ) ) : $wpdb->get_var( $count ) );
		$items = (array) $wpdb->get_results( $wpdb->prepare( $list, array_merge( $params, array( $per_page, ( $page - 1 ) * $per_page ) ) ), ARRAY_A );
		// phpcs:enable
		return array( $items, $total );
	}

	/**
	 * Registros de um usuário (exportador de dados pessoais).
	 *
	 * @param int $user_id Usuário.
	 * @return array
	 */
	public function for_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'cookie_consents' );
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t}` WHERE user_id = %d ORDER BY created_at ASC, id ASC", (int) $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Desvincula os registros de um usuário (apagador de dados pessoais): remove user_id e o hash do IP,
	 * mantendo o registro anônimo do consentimento.
	 *
	 * @param int $user_id Usuário.
	 * @return int Registros alterados.
	 */
	public function anonymize_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'cookie_consents' );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE `{$t}` SET user_id = NULL, ip_hash = '', user_agent = '' WHERE user_id = %d", (int) $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}

	/**
	 * Expurgo por idade (rotina de retenção).
	 *
	 * @param int $days Dias.
	 * @return int Registros apagados.
	 */
	public function purge_older_than( $days ) {
		global $wpdb;
		$t = self::table( 'cookie_consents' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM `{$t}` WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nome de tabela interno.
	}
}
